---
seo_title: "Locks vs Latches vs Spinlocks in Database Engines"
seo_description: "How database engines use locks, latches, and spinlocks for concurrency control, from B+Tree latch crabbing to the MESI protocol and hardware transactional memory."
focus_keyword: "locks, latches, and spinlocks"
---

# Locks, Latches, and Spinlocks: Concurrency Control in the Database Engine

## Why This Distinction Matters

Ask most application developers what a "lock" is and they'll give you a reasonable answer. Ask them to explain the difference between a lock, a latch, and a spinlock, and most will draw a blank — yet that distinction is exactly what separates a database engine that scales across a hundred cores from one that grinds to a halt under load. Locks, latches, and spinlocks are the three synchronization primitives that keep a multi-threaded database engine correct, and each one operates at a completely different level: logical consistency, in-memory structural consistency, and raw CPU cache behavior, respectively.

This piece walks through all three, starting from the high-level queueing problem and working down to the micro-architectural details of how a CPU core actually contends for a cache line. Along the way we'll cover why B+Trees need "latch crabbing" to stay consistent under concurrent modification, why a naive spinlock can quietly saturate your memory bus, and where hardware transactional memory fits into the future of lock-free design.

## The Core Problem

In a multi-core database engine handling heavy transaction volume, the hardest problem is resource contention. When thousands of threads want to read or write the same shared structure — a B+Tree, a buffer pool, a hash index — the engine needs some mechanism to keep them from stepping on each other and corrupting data.

Amdahl's Law sets a hard ceiling here: however many cores you throw at the problem, the speedup is bounded by the fraction of work that has to run serially. A poorly designed locking scheme pushes more and more of that work into the serial bucket, and threads spend their time waiting instead of computing. Worse, using the wrong tool for the job — a spinlock around a slow I/O call, or a full logical lock around a two-instruction in-memory update — turns the memory bus into a bottleneck, because the MESI cache-coherence protocol ends up invalidating cache lines over and over.

Get this wrong and a database running on a few-hundred-core machine can end up slower than the same workload on a single core. Getting locks, latches, and spinlocks right — using each where it belongs — is what keeps that from happening.

## Deep Technical Analysis

### Drawing the Boundaries: Locks vs. Latches vs. Spinlocks

Conflating locks and latches is a classic mistake in database engineering, and it's an easy one to make since both "protect" something. But they operate on different axes — one is about time (how long a transaction needs exclusive access), the other about space (how briefly you can touch a data structure without breaking it).

**1. Locks (logical locks):**
- **Purpose:** protect the logical consistency of the database, per the transaction's isolation level.
- **What they protect:** tuples, pages, tables.
- **Duration:** long — held for the life of the transaction.
- **Management:** a Lock Manager, typically a large hash table, with deadlock detection via a wait-for graph.

**2. Latches (physical locks):**
- **Purpose:** protect the physical consistency of in-memory structures, like B+Tree nodes, while they're being read or mutated.
- **What they protect:** in-memory structures — arrays, linked lists, tree nodes.
- **Duration:** very short, typically microseconds or less.
- **Management:** embedded directly in the data structure itself. There's no deadlock detector here — the programmer has to enforce a strict latch-acquisition order to avoid one.

**3. Spinlocks (micro-architectural locks):**
- **Purpose:** the most primitive form of latch, sitting closest to the hardware.
- **Mechanism:** busy-wait — repeatedly poll a memory variable instead of asking the OS for a context switch.

### Latch Crabbing on B+Trees

Latches are the first line of defense whenever you're touching data on disk or in memory. Take the classic B+Tree: walking from the root down to a leaf is straightforward on a single thread, but with concurrent access it gets dangerous. What happens if one thread is descending the tree while another is inserting a key that triggers a node split, restructuring the very path the first thread is walking?

The standard answer is **latch crabbing** (also called latch coupling):
1. Acquire a latch on the parent node.
2. Acquire a latch on the child node.
3. Check whether the child is "safe" — not full, not about to underflow.
4. If it's safe, release the latch on the parent.
5. Move down and repeat.

The catch is the root node — every single traversal has to latch it first, which makes it a serialization point no matter how wide the tree gets. Modern engines get around this with **optimistic lock coupling**: instead of taking a latch to read, a thread just reads a version counter on the node. If the counter has changed by the time it finishes reading — meaning someone wrote to the node in the meantime — it discards the read and retries. Most reads never see contention, so most reads never pay for a latch at all.

### Spinlock Internals and the MESI Cost

At the lowest level, spinlocks are built on CPU atomic instructions — Compare-And-Swap (CAS) being the usual choice. CAS guarantees the compare-and-update happens atomically, within a single hardware cycle.

The problem is what a tight CAS loop does to the rest of the machine. The MESI protocol (Modified, Exclusive, Shared, Invalid) is what keeps L1/L2 caches consistent across cores, and it doesn't like being hammered:
- When core A does a CAS write to the spinlock, the cache line holding that variable flips to Modified on core A.
- Every other core spinning on that same variable — B, C, D — has its copy of that cache line invalidated immediately.
- Those cores now have to re-fetch the line from L3 or main memory, and if enough cores are spinning, that traffic saturates the interconnect (Intel QPI, AMD Infinity Fabric).

**The fix: test-and-test-and-set (TTAS) with exponential backoff**

```cpp
class ExponentialBackoffSpinlock {
private:
    std::atomic<bool> lock_flag{false};

public:
    void lock() {
        int backoff_time = 1;
        while (true) {
            // Test: spin locally on the L1 cache, without disturbing the memory bus
            while (lock_flag.load(std::memory_order_relaxed)) {
                _mm_pause(); // The saving grace of hyper-threading
            }
            // Test-And-Set: only attempt the expensive write instruction once the lock looks open
            bool expected = false;
            if (lock_flag.compare_exchange_weak(expected, true, std::memory_order_acquire)) {
                return;
            }
            // Backoff: sleep for an exponentially increasing duration under contention
            for (int i = 0; i < backoff_time; ++i) _mm_pause();
            backoff_time = std::min(backoff_time * 2, 1024);
        }
    }
    void unlock() { lock_flag.store(false, std::memory_order_release); }
};
```

The `_mm_pause()` call is doing more than it looks like: it tells the CPU this is a spin loop (so it doesn't misapply speculative execution in ways that violate memory ordering) and frees up ALU slots for a sibling hardware thread on the same core.

### False Sharing and Memory Alignment

There's a subtler failure mode here: false sharing. If lock A and lock B protect two unrelated pieces of data but happen to land on the same 64-byte cache line, then core 1 touching lock A and core 2 touching lock B look — to the hardware — like contention on the same line. The result is the same cache-line bouncing you'd get from real contention, except there was never any logical conflict at all.

The fix in C/C++ is to align each spinlock with `alignas(64)`, so every lock gets its own cache line and none of them accidentally share one.

### Futex and Hardware Transactional Memory

Once thread count outpaces what the hardware can usefully run, spinning stops being cheap — it just burns cycles waiting. Linux's answer is the futex (fast userspace mutex): in the uncontended case, a thread grabs the lock atomically in userspace without ever entering the kernel. Only when contention persists does it call `futex_wait`, at which point the kernel actually parks the thread and hands the core to something else, waking it later via `futex_wake`. You get the low latency of a spinlock in the common case and the CPU efficiency of a real block in the contended case.

Further out, hardware transactional memory (HTM — Intel TSX is the reference example) points toward doing away with the lock entirely. A critical section runs speculatively with no latch held at all; the hardware tracks the L1 cache line by line, and if nothing else touches the same data, the transaction commits. If a conflict shows up, the hardware aborts and rolls back the registers instantly, and software falls back to a regular lock. Effectively, HTM turns a pessimistic lock into a hardware-implemented optimistic one, without the CAS overhead.

## Lessons Learned & Best Practices

1. **Know what a context switch actually costs.** Blocking on a traditional mutex instead of spinning costs a few microseconds per switch. In an in-memory database, a few microseconds is enough time for the CPU to do thousands of operations — which is exactly why spinlocks exist for short critical sections, and why you should never use one for anything that might block on I/O.
2. **Respect the cache line.** Never allocate an array of spinlocks back-to-back without padding. `alignas(64)` isn't cosmetic — skipping it invites false sharing, and false sharing is invisible until you profile for it.
3. **Prefer optimistic over pessimistic where you can.** Locking an entire tree or list to be safe is usually the wrong trade. Optimistic lock coupling — check a version counter, retry on mismatch — costs you an occasional wasted read instead of blocking every other thread that wants in.
4. **There's no deadlock detector at the latch level.** Get your latch-acquisition order wrong and nothing rescues you the way a wait-for graph rescues a transaction deadlock — the process just hangs. Enforce a strict, documented ordering whenever code touches more than one latch at a time.

## Conclusion

Locks, latches, and spinlocks aren't academic trivia — they're the actual mechanism that lets a transactional system serve thousands of concurrent clients without corrupting a single row. Every distributed database and every piece of financial infrastructure running today depends on getting this right. What separates a competent engineer from someone who can really make a system scale is understanding how software-level data structures interact with the physical reality of the processor — NUMA topology, cache coherence, memory ordering. Designing a synchronization strategy isn't just about writing code that doesn't crash; it's about working with the physical constraints of the silicon instead of against them.
