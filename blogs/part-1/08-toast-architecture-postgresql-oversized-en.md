---
seo_title: "PostgreSQL TOAST Architecture: Storing Oversized Data"
seo_description: "How PostgreSQL TOAST architecture compresses, chunks, and stores oversized TEXT/JSONB/BYTEA values out-of-line without breaking the 8KB page model."
focus_keyword: "PostgreSQL TOAST architecture"
---

# PostgreSQL's TOAST Architecture: The Art of Memory Management and Oversized Data Storage Engineering

## The Problem: Big Values, Small Pages

Every fixed-block storage engine runs into the same wall eventually: how do you fit a TEXT field, a JSONB document, or a BYTEA blob that's tens of megabytes into a disk page that's only 8 kilobytes?

The naive fix — just make pages bigger, say 1MB — would wreck the efficiency of ordinary OLTP queries. Small reads would drag megabytes off disk for no reason, and I/O amplification would spiral.

PostgreSQL doesn't touch the page size to solve this. Instead it routes oversized values through a separate subsystem called **TOAST (The Oversized-Attribute Storage Technique)**.

TOAST quietly chunks, compresses, and relocates large attributes into out-of-line storage, and it does this transparently — nothing in the SQL layer has to know or care. Understanding how TOAST actually works underneath a JSONB column also happens to be a good tour of CPU cache behavior (TLB, L1/L2) and how PostgreSQL avoids polluting the OS page cache. That's the real payoff of digging into this architecture.

---

## Page Boundaries and the TOAST Threshold Constant

To see why TOAST exists, start with the physical layout of a PostgreSQL disk page.

By default, `BLCKSZ` (the block size constant) is 8192 bytes ($2^{13}$). Nothing gets dumped into that space arbitrarily — every page follows the Slotted Page layout:

1. **PageHeaderData:** 24 bytes, holding metadata like `pd_lsn` (the log sequence number used for WAL crash recovery), `pd_checksum`, and the `pd_lower`/`pd_upper` pointers.
2. **Line Pointer Array (ItemIdData):** a static array at the tail of the page, each entry pointing to an actual tuple.

One design rule governs everything else: **no page fragmentation**. A tuple must fit entirely inside a single atomic I/O read of one page — it's never allowed to span two pages.

That constraint alone isn't enough, though. PostgreSQL also wants reasonable tuple density — a page should hold a handful of rows, not one giant record eating the whole 8KB. So the designers introduced a hard limit, `TOAST_TUPLE_THRESHOLD`, defined in the C source as:

$$ TOAST\_TUPLE\_THRESHOLD = \lfloor \frac{BLCKSZ}{4} \rfloor - 1 $$

With $BLCKSZ = 8192$, that works out to **2047 bytes** (in practice rounded down for alignment, so PostgreSQL typically treats it as roughly 2040 bytes).

Once an inserted tuple crosses that ~2040-byte line, the storage engine has no choice but to kick off TOAST routing: trim the tuple back under the limit so it still fits on a heap page, and push whatever doesn't fit out to a separate table.

---

## Inside the TOAST Pointer: `varatt_external`

An oversized value — say a 10MB PDF stored in a BYTEA column — gets replaced in place by an 18-byte pointer structure called **`varatt_external`**.

The underlying `varlena` (variable-length array) format uses a bit-masking scheme to encode state. Reading the first bits of the header byte tells the parser exactly what it's looking at:
- `PLAIN`: raw, uncompressed data stored inline.
- `COMPRESSED`: compressed data, still stored inline.
- `EXTERNAL`: data pushed out-of-line, referenced by a TOAST pointer.

That 18-byte `varatt_external` pointer carries four fields:
1. `va_rawsize` — the original, uncompressed size.
2. `va_extsize` — the physical size on disk after compression and chunking.
3. `va_valueid` — the OID assigned to this particular data chunk set.
4. `va_toastrelid` — the OID of the TOAST table holding the chunks.

Once this indirection is in place, the row in the main heap effectively becomes a lookup key into a side table. Take a table with columns `(id INT, created_at TIMESTAMP, payload JSONB)` where `payload` is 5MB. On the main heap, that tuple only occupies $4 \text{ bytes (id)} + 8 \text{ bytes (timestamp)} + 18 \text{ bytes (TOAST pointer)} = 30 \text{ bytes}$ — the 5MB never touches the heap page at all.

### Why This Matters for MVCC

The 18-byte pointer isn't just a space-saving trick — it changes the economics of MVCC updates entirely.

PostgreSQL never overwrites a row in place. Run `UPDATE table SET status='done' WHERE id=1` without touching `payload`, and the engine still has to build a brand-new physical tuple version.

Without out-of-line storage, that update would mean copying the full 5MB JSONB value into the new tuple — a textbook case of write amplification. With the TOAST pointer, PostgreSQL copies only the 18-byte `va_valueid` reference. Old and new tuple versions both point at the same 5MB payload sitting in the TOAST table. No duplication, no extra write volume, and considerably less wear on the underlying SSD.

---

## The Shadow TOAST Table and Binary Chunking

So where does the data actually go? PostgreSQL silently creates a companion table named `pg_toast.pg_toast_XXX` (XXX being the OID of the parent table). It's invisible to a normal `\d` command.

This TOAST table has a fixed three-column shape:
1. `chunk_id` (OID) — matches the `va_valueid` from the 18-byte pointer.
2. `chunk_seq` (INT) — a sequence number starting at 0, used to reassemble chunks in order.
3. `chunk_data` (BYTEA) — the raw bytes of that chunk.

### The Chunking Math

Rows in this shadow table have to respect `TOAST_TUPLE_THRESHOLD` too, so PostgreSQL caps each chunk, `TOAST_MAX_CHUNK_SIZE`, at **1996 bytes** by default.

The number of chunks needed follows a simple ceiling function:
$$ N_{chunks} = \lceil \frac{S_{compressed}}{1996} \rceil $$

For a 10MB file (10,485,760 bytes), that works out to roughly $10,485,760 / 1996 \approx 5254$ rows in `pg_toast_XXX`.

To make reads fast, PostgreSQL builds a dedicated B-Tree index, `pg_toast_XXX_index`, on `(chunk_id, chunk_seq)`. When a query touches that column, the executor walks the B-Tree in $\mathcal{O}(\log n + K)$ time (K being the chunk count), stitches those 5254 fragments back into one contiguous buffer, decompresses it, and hands the result back.

```mermaid
graph TD;
    subgraph Main Table (Main Heap)
        A[Tuple Page 1] -->|Exceeds 2040B threshold| B(Header: TOAST_EXTERNAL);
        B --> C{18-Byte Pointer \nva_valueid: 998877};
    end
    subgraph Shadow TOAST Table [pg_toast_12345]
        C -.->|Index Scan O(log N)| D[(pg_toast_12345_index)];
        D -->|chunk_seq = 0| E[1996 Bytes Payload];
        D -->|chunk_seq = 1| F[1996 Bytes Payload];
        D -->|chunk_seq = 2| G[Partial Payload];
    end
    subgraph Executor Memory Context
        E --> H((Memory Concatenation));
        F --> H;
        G --> H;
        H -->|PGLZ/LZ4 Algorithm| I[Original JSONB/Text];
    end
```

---

## Compression and Its Information-Theoretic Limits

Before slicing anything into 1996-byte chunks, PostgreSQL tries to compress it first, to cut down on physical I/O. Two algorithms are available: **PGLZ (PostgreSQL Lempel-Ziv)**, the long-standing default, and **LZ4**, added in PG 14 for speed. Both rely on a ring-dictionary scheme and repeated-string matching.

But compression isn't attempted blindly — PostgreSQL evaluates the risk first, using an idea straight out of information theory: Shannon entropy. The information content of a binary string $X$ is:

$$ H(X) = -\sum_{i=1}^{n} P(x_i) \log_2 P(x_i) $$

Insert an already-compressed file — a JPEG, an MP4, a gzip archive — and the bytes look like white noise: entropy near its theoretical ceiling. Lempel-Ziv-style algorithms gain nothing there; they can even bloat the output while burning CPU cycles doing it.

To avoid that waste, PostgreSQL applies a simple heuristic. It runs a trial compression pass over just the first chunk of data. If that trial doesn't cut the size by at least 25% (i.e., get below 75% of the original), the whole compression attempt is aborted right there. The value gets tagged uncompressed in its header and stored raw. That one check is what keeps PostgreSQL's throughput steady even when an application insists on inserting incompressible blobs all day.

```cpp
// Pseudocode: TOAST Heuristic Compression Micro-Architecture
struct varlena* toast_insert_or_update(struct varlena* datum, StorageType strategy) {
    size_t raw_size = VARSIZE_ANY_EXHDR(datum);
    if (raw_size <= 2040) return datum; // Data chunk is safe

    // Trial Compression Phase (Heuristic Check)
    struct varlena* compressed_datum = perform_lz4_compression(datum);
    if (compressed_datum != nullptr && VARSIZE_ANY_EXHDR(compressed_datum) < raw_size * 0.75) {
        // Low entropy, LZ4 compression succeeded
        datum = compressed_datum;
    } else {
        // Entropy too high, skip compression to save CPU cycles
        free_memory_context(compressed_datum);
    }

    // Proceed with Binary Chunking into the Shadow Table...
    // Return 18-byte External Pointer
}
```

---

## TOAST, Hardware Caches, and Garbage Collection (VACUUM)

Where the TOAST design really earns its keep is in how it interacts with the CPU's cache hierarchy (TLB, L1/L2) and the Linux page cache.

### Avoiding OS Page Cache Pollution

Picture an analytics query doing a full scan — `SELECT id, status FROM massive_table` — across millions of rows. If a 10MB JSONB value sat inline in each record, that scan would have to pull gigabytes of irrelevant JSONB off NVMe storage into RAM, evicting genuinely useful pages (like the root of a B-Tree index) from the Linux page cache in the process. That's OS cache pollution, and it's exactly the kind of thing that turns a cheap scan into a slow one.

With TOAST, the main tuple carries only an 18-byte pointer. Millions of rows now fit inside a few megabytes of RAM, and the actual TOAST data stays on disk until something explicitly asks for it via `SELECT payload FROM ...`. Sequential scans over the core columns stay fast because they're no longer dragging dead weight through the cache.

### Keeping the TLB Happy

Dense tuple packing has a second-order benefit: when the CPU loads a 64-byte cache line or a 4KB memory page, it picks up many more useful records per fetch. That reduces TLB (Translation Lookaside Buffer) misses on the virtual-to-physical address translation path, which in turn avoids the stalls that come from an expensive page table walk.

### Orphaned Chunks and VACUUM

The TOAST table deliberately skips a foreign key back to the main table — maintaining that reference would mean extra lock contention on every operation. The tradeoff: when you `DELETE` a row from the main table, its corresponding chunks in the TOAST table aren't removed immediately. They just sit there as dangling chunks until something cleans them up.

That's autovacuum's job. When it scans the main table and finds a dead tuple, it pulls the 18-byte OID out of the pointer, jumps to `pg_toast_XXX`, uses the B-Tree index to physically remove the matching chunks, and returns the freed space to the Free Space Map for future inserts.

Put together, this is more than a compression trick bolted onto the storage layer — it's a coordinated system spanning tuple encoding, a shadow table, an index, a compression heuristic, and a garbage collector, all working in lockstep. That coordination is what lets a classic row-oriented RDBMS like PostgreSQL comfortably host the JSON-heavy, document-style workloads that used to be NoSQL's exclusive territory — without paying for it in performance.
