---
seo_title: "HTAP Databases Explained: Delta-Main Architecture and MVCC"
seo_description: "A deep dive into HTAP databases — how Delta-Main memory architecture, MVCC across dual formats, and SIMD/JIT execution unify OLTP and OLAP in one engine."
focus_keyword: "HTAP databases"
---

# Technical Whitepaper #51: HTAP Databases - The Architecture of Hybrid Transactional/Analytical Processing

## Executive Summary
This piece takes a close, micro-architectural look at HTAP databases — Hybrid Transactional/Analytical Processing systems. For decades the database world lived with a hard split between OLTP and OLAP, and HTAP is the attempt to break that compromise. We'll walk through the math behind dual-format memory layouts (Delta-Main architecture), how MVCC has to work across two very different data layouts at once, and the raw computational tricks — SIMD vectorization, LLVM JIT compilation — that make it all fast enough to matter.

---

## Introduction: Why Data Processing Split in Two
For over four decades, databases have lived with a basic fork in the road: a system is either built to write fast, or to read fast, rarely both.
- **OLTP (Online Transaction Processing):** systems like PostgreSQL or MySQL are tuned for high-frequency, low-latency point queries and updates. They use row-oriented storage (the N-ary storage model), so an entire record — a user profile, say — can be fetched or changed in one disk I/O or cache-line fetch.
- **OLAP (Online Analytical Processing):** systems like Snowflake, ClickHouse, or BigQuery are built for complex, read-heavy queries that aggregate huge volumes of data. They rely on column-oriented storage (the decomposition storage model), which maximizes sequential memory bandwidth and allows for aggressive compression.

The problem is that businesses now want real-time analytics on live transactional data. The traditional fix — extract, transform, and load data from OLTP into an OLAP warehouse — introduces a lag window that turns "real-time analytics" into "yesterday's analytics."

HTAP databases are the architectural answer: unify both workloads into a single engine, eliminate the ETL pipeline, and still keep performance isolation between the two workloads. The hard question is how one engine can serve two fundamentally opposed goals at once.

---

## The Core Problem: Impedance Mismatch

### The Analytical Penalty of Row Stores
Run an OLAP query like `SELECT SUM(salary) FROM employees` against a row store, and the CPU has to load entire rows into L1/L2 cache just to read one `salary` field. That means pulling in unneeded attributes like `address` or `biography`, polluting the cache and wasting memory bandwidth. The CPU ends up starved for useful data, and the whole system bottlenecks on it.

### The Transactional Inflexibility of Column Stores
Flip it around and try to `INSERT` or `UPDATE` a column store, and you get the opposite nightmare. A single logical row change means seeking and writing to dozens or hundreds of separate columnar files scattered across disk. That scattered random I/O destroys transactional throughput — a microsecond operation turns into a millisecond one.

### The Cost of Moving Data Around (ETL)
Keeping two separate databases in sync means running an ETL pipeline, and ETL is notoriously fragile, computationally expensive, and introduces a staleness window $\Delta T$ that often runs from minutes to hours. In algorithmic trading, fraud detection, or dynamic pricing, an hour of stale data can mean millions in lost revenue.

---

## The Fix: Delta-Main Architecture

HTAP databases like TiDB, SingleStore, and SAP HANA solve the impedance mismatch with a multi-format, dual-storage architecture. Data logically lives in one unified schema but is physically stored in both row and column format at the same time, inside the same system.

### The Delta-Main Memory Layout
Keeping two physical copies perfectly in sync for every microsecond-level write would be prohibitively expensive, so HTAP engines lean on an **in-memory delta-main architecture**:
- **Delta store (row-oriented):** every incoming write — `INSERT`, `UPDATE`, `DELETE` — lands in a highly concurrent, lock-free row-oriented buffer in RAM. This absorbs fast-moving transactional writes and keeps commits under a millisecond.
- **Main store (column-oriented):** the bulk of historical data lives here, heavily compressed and optimized for reads.

### Asynchronous Compaction and Tuple Movers
To stop the row-oriented delta store from eating all available RAM and dragging down analytics, a background pipeline — usually called a Tuple Mover or Compactor — runs continuously. It snapshots the immutable rows sitting in the delta store, transposes them into columnar format, applies heavy compression (run-length encoding, dictionary encoding, bit-packing), and flushes the result into the main store.

The cost of an analytical query $C_{olap}$ over attribute $A$ ends up being a combination of both scans:
$$C_{olap} = C_{column\_scan}(N - \Delta) + C_{row\_scan}(\Delta)$$
Since $C_{row\_scan}$ is far more expensive per tuple than $C_{column\_scan}$, the Tuple Mover has to be aggressive enough to keep $\Delta$ small, but careful enough not to starve the OLTP threads of CPU cycles and memory bandwidth.

```mermaid
graph TD
    subgraph Transactional Workload (OLTP)
        A[Client App] -->|High-Frequency Writes| B(Transaction Manager)
        B -->|Row Mutations| C{Write-Optimized Delta Store (RAM)}
    end
    
    subgraph Analytical Workload (OLAP)
        E[Analytics App] -->|Complex Aggregation| F(Vectorized Execution Engine)
        F -->|Scan & Filter| C
        F -->|High-Speed Sequential Scan| D[(Compressed Columnar Main Store)]
    end

    subgraph The Background Pipeline
        C -.->|Asynchronous Tuple Mover| G(Transposition & Compression)
        G -.->|Immutable Column Chunks| D
    end
```

### Hardware Implications: NUMA, TLB, and Huge Pages
Building an in-memory HTAP system means paying close attention to OS and hardware limits. Scanning terabytes of columnar data with standard 4KB memory pages triggers a flood of TLB misses. HTAP engines nearly always configure the Linux kernel to use huge pages (2MB or 1GB) instead, keeping the TLB resident and the page walker mostly idle.

NUMA topology adds another wrinkle: if a thread on CPU socket 0 tries to scan columnar data sitting in socket 1's RAM, that data has to cross the QPI/UPI interconnect, adding latency and capping throughput. HTAP allocators pin specific column chunks to the NUMA node where the analytical thread is actually running, to keep memory access local.

---

## MVCC in an HTAP Context

Serving high-throughput writes and long-running reads on the same engine at the same time requires genuinely solid isolation. Traditional two-phase locking falls apart here — a five-minute OLAP read would acquire shared locks across millions of rows, blocking every OLTP write for the entire five minutes.

### Snapshot Isolation via MVCC
HTAP systems lean uniformly on MVCC. Writers never overwrite data in place — they append a new version of the tuple. Readers never take locks — they read from an immutable, time-consistent snapshot of the database corresponding to their own logical read timestamp, $T_{read}$.

### Resolving Visibility Across Two Formats
The tricky part is reconciling visibility across both storage formats.
1. The analytical query scans the heavily compressed columnar main store — but some of those tuples may have since been updated or deleted in the delta store. The engine uses a Roaring Bitmap or an invalidation list to filter those stale tuples out.
2. At the same time, the query scans the delta store, evaluating a visibility predicate for every row:
   $$Visible(V) = (BeginTS \le T_{read}) \land (EndTS > T_{read})$$
Only the tuple version satisfying that condition gets merged into the final result.

### Garbage Collection and Watermarks
An engine processing 10,000 updates a second is also generating 10,000 obsolete tuple versions a second. The system tracks a global watermark, $T_{watermark}$, representing the oldest still-active transaction. A background vacuum process continuously scans the delta store and physically removes any tuple version where $EndTS < T_{watermark}$, reclaiming memory and keeping cache density healthy.

---

## Hardware-Accelerated Query Execution

To make up for the overhead MVCC and dual-format reconciliation add, HTAP analytical engines skip the traditional tuple-at-a-time (Volcano-style) processing model entirely and lean hard on modern CPU microarchitecture.

### Vectorized Execution and SIMD
Vectorized execution processes data in batches — typically 1024 or 4096 values at a time — which amortizes the overhead of virtual function dispatch and keeps tight inner loops resident in L1 instruction cache.
More importantly, it unlocks SIMD instructions like Intel AVX-512. The CPU can load 16 32-bit integers into a single 512-bit register and run an arithmetic operation or predicate check (`salary > 50000`) against all 16 values in a single clock cycle — a theoretical 16x speedup over scalar execution.

### JIT Compilation via LLVM
A complementary technique is LLVM-based JIT compilation. Instead of running generic, pre-compiled C++ functions, the database writes and compiles machine code tailored to the specific SQL query at runtime. JIT compilation fuses multiple operators together, keeps data inside CPU registers, and eliminates round-trips to L1 cache almost entirely.

### The Freshness vs. Performance Trade-off
Data freshness in HTAP isn't all-or-nothing — it's a dial. If an analyst can tolerate data that's 5 minutes stale ($\Delta T = 5\text{ min}$), the query optimizer can skip the row-oriented delta store entirely and scan only the immutable columnar main store. Skipping the CPU-heavy visibility checks and dual-format merging makes the query run orders of magnitude faster. It's a clean trade-off: give up microsecond-level freshness, get gigabytes-per-second more scan throughput in return.

---

## Lessons Learned

A few things stand out from watching HTAP systems evolve.

1. **Unified systems are really about smart trade-offs, not magic.** HTAP doesn't repeal the laws of physics — it trades RAM capacity (storing two formats) and background CPU cycles (Tuple Movers) for operational simplicity (no ETL) and real-time freshness.
2. **Data freshness is a spectrum, not a boolean.** Modern systems should let applications negotiate their own freshness requirements. Exposing the staleness bound $\Delta T$ to the query optimizer opens up large optimizations and shows that strict serializability is often overkill for analytical workloads.
3. **Hardware-software co-design isn't optional here.** You can't build an engine that processes a billion rows a second on generic POSIX abstractions. Getting real HTAP performance means writing code that explicitly respects cache lines, NUMA boundaries, TLB page sizes, and SIMD registers — the software has to bend to the physical realities of the silicon it runs on.

---

## Conclusion
HTAP databases represent a genuine breakthrough in data engineering — tearing down the decades-old wall between operational and analytical systems. By coordinating in-memory delta stores, background columnar compression, lock-free MVCC, and SIMD vectorization, HTAP engines let businesses run complex ML models and aggregations on live data the moment it's generated. As data volume and velocity keep climbing, this architecture — built out of hard hardware-optimization constraints rather than abstraction — looks set to become the default standard for future database platforms.
