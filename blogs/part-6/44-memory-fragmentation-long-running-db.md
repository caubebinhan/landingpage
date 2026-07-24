---
seo_title: "Memory Fragmentation in Long-Running Databases"
seo_description: "How memory fragmentation degrades long-running database processes, why glibc struggles, and how jemalloc, slab allocators, and huge pages fix it."
focus_keyword: "memory fragmentation database"
---

# 44: Memory Fragmentation in Long-Running Database Processes: Micro-architectural Implications and Algorithmic Mitigations

## Executive Summary & Core Problem Statement

Memory fragmentation in long-running database processes is one of those problems that never shows up in a benchmark but eventually shows up in production. It creeps in slowly: throughput drifts down, tail latency drifts up, and months later you're staring at an OOM kill with no obvious culprit.

**The core problem:** a database engine handling OLTP and OLAP workloads is allocating and freeing memory millions of times a second. Run that allocator for weeks or months straight and the heap accumulates what's best described as structural entropy — the operating system's memory allocator, working alongside the virtual memory subsystem, ends up with a heap that looks nothing like it did on day one.

That entropy breaks contiguity. Data structures that should be logically contiguous — B+ tree nodes, columnar arrays — get physically scattered across pages that have no relationship to each other. Once that happens, the spatial-locality assumptions that CPU cache design depends on stop holding: TLB misses climb, and the MMU is stuck doing multi-level page table walks it wouldn't otherwise need. The CPU that should be running your queries spends its cycles waiting on RAM instead.

This piece walks through the micro-architectural fallout of fragmentation, why the standard glibc allocator falls short for this workload, and the mitigations — slab allocators, buffer pools, arena allocation, huge pages — that database engineers reach for instead.

---

## Theoretical Foundations of Memory Fragmentation

Fragmentation in a long-running process comes in two flavors, and it's worth being precise about which one you're dealing with.

### Internal vs. External Fragmentation

1. **Internal fragmentation** happens when the allocator hands back a block bigger than what was asked for — usually to satisfy alignment rules (8-byte or 16-byte boundaries) or because the allocator only deals in fixed size classes. That extra space is allocated but unused; it's dead weight sitting inside memory you already own.
2. **External fragmentation** is the opposite problem: lots of small free segments scattered between live allocations. The total free space might be plenty for a large request, but none of the individual gaps are big enough, so the allocator has no choice but to ask the OS for more via `mmap` or `sbrk` — bloating the process's RSS even though it's technically sitting on "free" bytes already.

### The Mathematics of Fragmentation

To put a number on this, define a fragmentation ratio $\Phi$. Let $M_{total}$ be the total memory requested from the OS, and $M_{used}$ the memory actually holding live data. Then:
$$\Phi = 1 - \frac{M_{used}}{M_{total}}$$

As $\Phi$ climbs over time $t$, the system edges toward a point where an allocation of size $s$ can fail — $P_{fail}(s)$ becomes non-negligible — even though $M_{total} - M_{used} \gg s$ on paper.

We can model that failure probability using a Poisson-style approximation over the distribution of free block sizes $F=\{f_1, f_2, \dots, f_n\}$:
$$P_{fail}(s) = \prod_{i=1}^{n} (1 - H(f_i - s))$$
where $H(x)$ is the Heaviside step function. Once $P_{fail}(s)$ hits 1, the allocator has to fall back to compaction — or, if memory is genuinely exhausted, the Linux OOM killer steps in.

---

## Micro-architectural Implications: TLB and Cache Hierarchies

The hardware cost of fragmentation is real and measurable. Modern CPUs lean heavily on the **Translation Lookaside Buffer (TLB)** to cache virtual-to-physical address translations, and fragmentation is exactly what breaks that cache.

### TLB Thrashing and Page Table Walks

Once external fragmentation sets in, arrays that are logically one contiguous block end up mapped across pages with no physical relationship to each other. Spatial locality is gone, and TLB miss rates climb accordingly.

Let $T_{hit}$ be TLB hit latency and $T_{miss}$ the cost of a miss — which means walking 4 or 5 levels of the page table radix tree. Effective Memory Access Time is:
$$EMAT = P_{hit} \cdot T_{hit} + (1 - P_{hit}) \cdot T_{miss}$$

As fragmentation worsens, $P_{hit}$ drops, the MMU walks page tables far more often, and EMAT can balloon by two orders of magnitude — from roughly 1ns for an L1 TLB hit to something like 100ns for a page walk that has to touch main memory.

### L1, L2, and L3 Cache Degradation

The damage isn't confined to the TLB. In a badly fragmented heap, data that ought to sit together inside one 64-byte cache line gets spread across several, which shrinks the effective capacity of L1/L2/L3 and invites cache thrashing.

Hardware prefetchers make this worse in practice: they're built to recognize sequential or strided access patterns, and a fragmented linked structure or a page-scattered array gives them nothing predictable to latch onto.

```mermaid
graph TD
    A[Application Memory Request] --> B{Memory Allocator glibc/jemalloc}
    B -->|Fast Path| C[Thread Local Cache (TCACHE)]
    B -->|Slow Path| D[Central Free List / Arena]
    D --> E{Sufficient Contiguous Space?}
    E -->|Yes| F[Allocate Block]
    E -->|No| G[System Call mmap/sbrk]
    G --> H[Kernel Virtual Memory Manager]
    H --> I[Page Frame Reclaiming]
    I --> J[Physical Memory Allocation]
    J --> K[Page Table Update]
    K --> F
    F --> L[Return Pointer to DB Process]
```

---

## The Allocator Wars: `glibc` vs. `jemalloc` vs. `tcmalloc`

The default C library allocator, `glibc malloc` (built on ptmalloc), is fine for a desktop app. It's a poor fit for a long-running, heavily multi-threaded database server: global locks and shared arenas mean thread contention and fragmentation both creep up over time.

That's why high-performance databases — Redis, PostgreSQL, CockroachDB — link against something purpose-built instead: **`jemalloc`** (originally FreeBSD, later maintained at Facebook) or Google's **`tcmalloc`**.

### The `jemalloc` Architecture

`jemalloc` was designed from the start for high allocation-rate, multi-threaded workloads, and it leans on a few specific techniques:
1. **Size classes.** Memory is segregated into discrete size classes (8 bytes, 16, 32, and on up to large-page sizes). This bounds internal fragmentation to roughly 20% in the worst case — a known, predictable ceiling rather than an open-ended risk.
2. **Thread-local caching (tcache).** Each thread keeps its own lock-free cache of blocks. Small allocations are served straight from the tcache with no lock acquisition, giving an $O(1)$ fast path and no false sharing between cores.
3. **Active purging.** A background thread coalesces fragmented free blocks and hands them back to the OS, so RSS tracks actual usage instead of drifting upward forever.

---

## Algorithmic Mitigations: Custom Database Memory Architectures

Even with jemalloc doing its job, there's still a gap between how the application thinks about memory and how the OS actually manages it. That's why high-performance databases often sidestep general-purpose allocators entirely on hot paths, in favor of memory architectures built for the job.

### Slab Allocators

One approach is the slab allocator. Memory is pre-allocated from the OS in large, contiguous chunks — slabs — and each slab is carved into uniform slots dedicated to one specific object type: transaction contexts, lock descriptors, whatever the database needs repeatedly. Because every slot in a slab is the same size and the same type, external fragmentation inside the pool simply can't happen — there's nothing irregular for gaps to form around.

When a slot is freed, it just gets pushed onto a lock-free free list. Internal fragmentation per slab is bounded by $S_{slab} \mod S_{obj}$, which is negligible once $S_{slab} \gg S_{obj}$.

### The Buffer Pool (Fixed-Size Pages)

For the primary data cache, databases lean on a fixed-size **buffer pool**. It's allocated as one large, contiguous virtual memory region up front, then divided into identical frames — 8KB in PostgreSQL, 16KB in InnoDB.

Because every frame is the same size, the buffer pool sidesteps both internal and external fragmentation entirely for data caching. When the pool fills up and a new page needs loading, LRU or CLOCK-Pro evicts an old page and the new one drops into that exact same frame. The cache stays fragmentation-free no matter how long the process runs.

### Arena Allocators (Region-Based Memory Management)

Query execution is a different story — hash tables and sort buffers for a `JOIN` or `ORDER BY` need variable-sized, transient allocations. To keep those from chewing holes in the global heap, engines use **arena allocators** (PostgreSQL calls its version `MemoryContext`).

An arena grabs one large contiguous block when a query starts. From there, every allocation is just a pointer bump — true $O(1)$, no per-object metadata overhead.

The key trick is on the deallocation side: nothing inside the arena is ever freed individually. The whole arena gets torn down in one shot when the query finishes. That means transient query memory structurally cannot contribute to long-term external fragmentation — there's no bookkeeping left behind to fragment.

```cpp
template <typename T, size_t BlockSize = 4096>
class FragmentFreeArena {
private:
    struct Block {
        char data[BlockSize];
        size_t current_offset;
        Block* next;
        Block() : current_offset(0), next(nullptr) {}
    };
    Block* head_block;
    Block* current_block;

public:
    FragmentFreeArena() {
        head_block = new Block();
        current_block = head_block;
    }
    
    ~FragmentFreeArena() {
        Block* curr = head_block;
        while (curr != nullptr) {
            Block* next = curr->next;
            delete curr;
            curr = next;
        }
    }

    void* allocate(size_t size, size_t alignment = alignof(std::max_align_t)) {
        size_t current_ptr = reinterpret_cast<size_t>(current_block->data) + current_block->current_offset;
        size_t offset = (alignment - (current_ptr % alignment)) % alignment;
        
        if (current_block->current_offset + offset + size <= BlockSize) {
            void* ptr = current_block->data + current_block->current_offset + offset;
            current_block->current_offset += offset + size;
            return ptr;
        } else {
            Block* new_block = new Block();
            current_block->next = new_block;
            current_block = new_block;
            return allocate(size, alignment); 
        }
    }
    
    // O(1) instantaneous deallocation of entire region
    void reset() {
        Block* curr = head_block->next;
        while (curr != nullptr) {
            Block* next = curr->next;
            delete curr;
            curr = next;
        }
        head_block->next = nullptr;
        head_block->current_offset = 0;
        current_block = head_block;
    }
};
```

---

## OS Interactions: Huge Pages and `madvise`

None of this happens in a vacuum — the kernel has its own opinions about memory, and they interact with database-level fragmentation in ways worth understanding.

### Transparent Huge Pages (THP) vs. Explicit Huge Pages

Standard x86-64 uses a 4KB page size. A database holding 128GB of RAM needs 33.5 million page table entries at that granularity. Switch to **huge pages** (2MB or 1GB) and that entry count drops dramatically, which in turn multiplies effective TLB coverage.

The catch is Linux's **Transparent Huge Pages (THP)**, which tries to collapse 4KB pages into 2MB huge pages automatically via a kernel thread (`khugepaged`). Good intentions, bad outcome in practice: when the kernel wants to form a huge page but physical memory is fragmented, it triggers **direct compaction** — a synchronous, blocking operation that pauses the database process while pages get physically migrated. That stall can run into the hundreds of milliseconds, which shows up as a nasty, unpredictable latency spike.

The standard advice is to **disable THP entirely** (`echo never > /sys/kernel/mm/transparent_hugepage/enabled`) and use statically allocated explicit huge pages instead (`hugepages=N` in GRUB). Reserving the contiguous memory up front removes the runtime compaction risk altogether.

### The `madvise` System Call

When the database frees memory, the user-space allocator marks it free, but the OS still holds the physical page mapping unless told otherwise. To avoid RSS quietly ballooning, allocators like jemalloc periodically call `madvise(MADV_DONTNEED)` or `MADV_FREE` on unused ranges.

That call tells the kernel to tear down the page table entries for that virtual range and reclaim the physical pages behind it. If the process touches that address again later, the kernel just maps in a fresh, zeroed page. It's how the database gives memory back to the OS instead of hoarding it indefinitely.

---

## Lessons Learned & Best Practices

1. **Never ship default `glibc` for a database.** If you're building anything data-intensive in C, C++, or Rust, link against `jemalloc` or `tcmalloc` instead. The gap under real concurrency is not subtle.
2. **Disable Transparent Huge Pages.** For MongoDB, PostgreSQL, Redis — basically every major engine — THP's synchronous compaction causes latency spikes that are hard to diagnose after the fact. Turn it off at the OS level.
3. **Use explicit huge pages for the buffer pool.** Pinning your primary data cache to pre-allocated huge pages removes TLB thrashing and OS-level swapping from the equation entirely.
4. **Use arena allocators for request-scoped work.** In web servers or query engines, allocate per-request from an arena and reset the pointer when the request ends. No fragmentation, no garbage collector needed.
5. **Watch RSS against actual data size.** If your database reports 10GB of live data but `htop` shows 30GB of RSS, that gap is memory fragmentation or a purging misconfiguration — tune the allocator's background purge thread before it gets worse.

---
