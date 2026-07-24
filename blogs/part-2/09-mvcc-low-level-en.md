---
seo_title: "MVCC Internals: Version Chains, EBR, and NUMA in Practice"
seo_description: "A low-level look at MVCC (multi-version concurrency control): version storage models, cache misses from pointer chasing, epoch-based GC, and NUMA memory placement."
focus_keyword: "MVCC (multi-version concurrency control)"
---

# Multi-Version Concurrency Control (MVCC): A Low-Level Architectural Analysis and the Multi-Threaded Memory Management Ecosystem

## Overview

MVCC — multi-version concurrency control — is the mechanism most modern databases use to let readers and writers run without blocking each other. That's the textbook description. What it actually costs at the hardware level is a different story, and it's the one most engineers never see until they're debugging a latency spike at 2am.

This piece works through MVCC from the micro-architecture up: how version chains interact with CPU caches, why they generate pointer chasing and false sharing, how systems reclaim memory without stopping the world, and what NUMA does to all of it. If you work on a storage engine, or just want to understand why your OLTP workload behaves the way it does under contention, the following should be useful.

**What's covered:**
- The three main multi-version storage models (append-only, time-travel, delta storage) and their trade-offs.
- Hardware-level friction: pointer chasing and false sharing, with the numbers behind them.
- Epoch-Based Reclamation (EBR) as a lock-free alternative to reference counting.
- NUMA-aware memory placement and why it matters for garbage collection.

## The Core Problem

In high-throughput OLTP systems, keeping data consistent while maximizing throughput is an old tension. Lock-based approaches solve consistency cleanly enough, but they force readers and writers to block each other, and that turns into a real bottleneck once you're running on more than a handful of cores.

MVCC sidesteps this by keeping multiple versions of each row, so reads don't block writes and writes don't block reads. That solves the concurrency problem, but it introduces a different set of problems at the micro-architecture layer:

1. **Memory fragmentation and access latency.** Keeping multiple versions around puts real pressure on the allocator. As version chains grow, the CPU has to chase pointers to find the version it needs, and the cache miss rate climbs accordingly.
2. **Multi-threaded synchronization overhead.** Concurrent reads and writes on shared structures need memory barriers and atomic operations, and those aren't free — they add traffic to the memory bus.
3. **Version lifecycle management (garbage collection).** Figuring out when it's safe to reclaim an old version, without breaking a transaction that's still reading it, is a genuinely hard problem. Get it wrong and you either leak memory or corrupt a read.

## Deep Technical Analysis

### Theoretical Foundations and Multi-Version Storage Architecture

At its core, MVCC is a mapping problem. If the logical state of the database at time $t$ is a set of tuples $D_t = \{R_1, R_2, \dots, R_n\}$, the MVCC layer maps each logical tuple $R_i$ to a set of physical versions $V_i = \{v_{i,1}, v_{i,2}, \dots, v_{i,m}\}$.

Each version $v_{i,j}$ carries a validity interval $[T_{begin}, T_{end})$. Depending on the isolation level in play — Read Committed, Serializable, whatever the engine supports — the transaction manager picks which timestamp a given read should be evaluated against, to preserve recoverability and serializability.

The version tuple itself typically carries a fair amount of metadata packed into its header to support visibility checks: the ID of the transaction that created the version ($TxnID_{creator}$), the ID of the transaction that deleted it ($TxnID_{deleter}$), and a pointer linking this version to the next or previous one in the chain. That header usually runs 16 to 32 bytes.

#### Storage Models

There are three broad approaches to organizing physical versions:
- **Append-Only Storage:** every new version lands in the same table space alongside all the old ones (this is what PostgreSQL does).
- **Time-Travel Storage:** the main table space holds the current version, and full copies of old versions get pushed into a separate store.
- **Delta Storage:** only the diff is stored, not a full copy (MySQL, Oracle).

### Hardware Obstacles: Pointer Chasing and Cache Misses

Under the append-only model, if the update rate is $\lambda$ updates/second, memory grows at $\frac{dM}{dt} = \lambda \times S_{tuple}$. This is where the model runs into trouble: as consecutive versions scatter across memory, the CPU cache stops being able to help you.

Walking a version chain means dereferencing pointer after pointer — the classic pointer chasing problem. Say $P_{miss}$ is the probability of an L3 cache miss on a given dereference, $T_{mem}$ is main memory access time (roughly 100ns), and $T_{cache}$ is L1 access time (roughly 1ns). If a read has to walk a chain of length $L$ to find its visible version, the expected resolution time is:

$$E[T_{resolve}] = L \times \left( P_{miss} \times T_{mem} + (1 - P_{miss}) \times T_{cache} \right)$$

With append-only storage, $P_{miss}$ tends toward 1.0 as fragmentation gets worse — each hop is effectively a guaranteed cache miss — and table scan latency degrades accordingly.

### Optimizing with Delta Storage and the False Sharing Problem

Delta storage was designed specifically to avoid this. Instead of appending a whole new copy, it updates the primary version in place and writes a delta record containing only the fields that changed. That delta gets pushed into a ring buffer known as the undo log. Memory growth here follows $\frac{dM_{undo}}{dt} = \lambda \times (S_{\Delta} + S_{metadata})$ — proportional to the size of the change, not the size of the row.

Reconstructing an old version now means walking the delta chain and applying each patch in reverse. To do this safely under concurrent access, the code needs explicit memory ordering — `std::memory_order_acquire` on the pointer loads, for instance.

At the source level, false sharing is a persistent hazard here. Under the MESI cache coherence protocol (Modified, Exclusive, Shared, Invalid), when one core writes to a variable, the entire cache line holding that variable gets invalidated on every other core — even if those cores were touching unrelated fields that just happen to live on the same line. The fix is to force separation with alignment directives like `alignas(64)` in C/C++, so hot fields don't share a cache line with something another thread is hammering.

```mermaid
graph TD
    subgraph CPU_Cache_Architecture
        L1_Core1[L1 Cache Core 0] -->|MESI Invalidate| L1_Core2[L1 Cache Core 1]
        L1_Core1 --> L2_Core1[L2 Cache]
        L1_Core2 --> L2_Core2[L2 Cache]
        L2_Core1 --> L3_Shared[L3 Shared Cache]
        L2_Core2 --> L3_Shared
    end
    subgraph Physical_Memory_Layout
        L3_Shared --> Main_Tuple[Main Version Tuple\nHeader | ID | Payload\nalignas 64 bytes]
        Main_Tuple -.->|Atomic Undo Pointer| Delta_1[Delta Record 1\nTxnID | Changed Columns]
        Delta_1 -.->|Atomic Undo Pointer| Delta_2[Delta Record 2\nTxnID | Changed Columns]
    end
    style Main_Tuple fill:#f9f,stroke:#333,stroke-width:2px
    style Delta_1 fill:#bbf,stroke:#333,stroke-width:1px
    style Delta_2 fill:#bbf,stroke:#333,stroke-width:1px
```

```cpp
// Illustration of the low-level data structure of Delta Storage in a multi-threaded environment
#include <atomic>
#include <cstdint>
#include <cstring>

// Aligned to 64 bytes to prevent False Sharing on the cache line
struct alignas(64) UndoRecord {
    std::atomic<UndoRecord*> next_delta;
    uint64_t transaction_id;
    uint32_t delta_size;
    uint8_t payload[]; // Flexible array member
};

struct alignas(64) TupleHeader {
    uint64_t xmin;
    std::atomic<uint64_t> xmax;
    std::atomic<UndoRecord*> undo_pointer;
    uint32_t tuple_length;
    uint16_t attributes_mask;
};

// Function to read and apply Deltas using a Wait-Free mechanism
void reconstruct_version(const TupleHeader* base_tuple, uint64_t read_ts, uint8_t* output_buffer) {
    std::memcpy(output_buffer, reinterpret_cast<const uint8_t*>(base_tuple) + sizeof(TupleHeader), base_tuple->tuple_length);
    UndoRecord* current_delta = base_tuple->undo_pointer.load(std::memory_order_acquire);

    while (current_delta != nullptr) {
        if (current_delta->transaction_id < read_ts) break;
        apply_binary_patch_logic(output_buffer, current_delta->payload, current_delta->delta_size);
        current_delta = current_delta->next_delta.load(std::memory_order_acquire);
    }
}
```

### Garbage Collection via Epoch-Based Reclamation (EBR)

Old versions pile up, and that pressure has to go somewhere. The database's event horizon, $TS_{min} = \min_{T \in ActiveTxns} (TS_{read}(T))$, tells you which versions are safe to discard: $\forall v_k \in Memory, \text{IsGarbage}(v_k) \iff v_k.T_{end} < TS_{min}$.

Rather than pay for reference counting on every access, in-memory systems like HyPer and Silo use Epoch-Based Reclamation. The system divides time into discrete epochs ($E_1, E_2, \dots$). When a thread retires an old version, it doesn't free it immediately — it drops the pointer into a garbage list tied to the current global epoch, $GarbageList[E_{global}]$. The actual `free()` call is deferred until every active thread has advanced at least two epochs past it:

$$\forall thread \in ActiveThreads, E_{local}(thread) > E_{safe} + 1$$

The weakness here is straightforward: if one thread stalls — blocked on I/O, descheduled, whatever — it holds back the epoch counter for everyone, and garbage accumulates system-wide until that thread moves again. A single stuck thread can quietly turn into an OOM.

### NUMA Micro-Architecture and TLB Shootdown

The OS gets involved here too. When memory is freed via `munmap`, the kernel triggers a TLB shootdown — an inter-processor interrupt that forces every core to flush parts of its pipeline — and that's expensive at scale.

The usual fix is to avoid the kernel path for hot allocations: user-space allocators like `jemalloc` or `tcmalloc`, with per-thread arenas, sidestep most of this. On NUMA (Non-Uniform Memory Access) hardware there's a second layer to this problem — RAM is physically attached to specific CPU sockets, and crossing sockets to fetch memory costs noticeably more than staying local. A transaction's rollback segment should be allocated on the same NUMA node as the thread that's using it, via APIs like `numa_alloc_onnode`.

## Lessons Learned & Best Practices

A few things fall out of digging into MVCC at this level:

1. **Know your hardware.** You can't design a high-throughput concurrent system while treating L1/L2/L3 caches, memory bandwidth, and NUMA interconnects as someone else's problem. Every optimization has to reckon with the 64-byte cache line.
2. **Don't let false sharing slide.** Lay out shared structures deliberately (`alignas(64)`) so independent threads aren't fighting over the same cache line and forcing needless invalidations.
3. **Defer reclamation off the hot path.** Locks and atomic reference counting on every access are expensive. EBR or hazard pointers move that cost off the critical path.
4. **Write your own allocator.** Relying on the OS's `malloc`/`free` for hot-path allocations means paying for syscalls, TLB shootdowns, and fragmentation you didn't need. Systems that care about this pool memory in user space instead.

## Conclusion

Building an MVCC system is not just a matter of managing version timestamps correctly. Underneath that logic, it's a constant negotiation with the physical realities of the machine — cache lines, memory buses, NUMA topology. How well a database performs under concurrent load comes down to how well it controls pointer-chasing latency, respects NUMA boundaries, and reclaims memory without stalling. None of this is optional if you're building a storage engine meant to hold up under real concurrent load — it's the difference between a system that scales and one that just looks like it does in a single-threaded benchmark.
