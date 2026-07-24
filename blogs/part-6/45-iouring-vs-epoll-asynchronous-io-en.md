---
seo_title: "io_uring vs epoll: Async I/O for Database Engines"
seo_description: "Why io_uring outperforms epoll and Linux AIO for database storage engines, and how ring buffers, SQPOLL, and fixed buffers cut syscall overhead."
focus_keyword: "io_uring vs epoll"
---

# `io_uring` vs `epoll`: The New Era of Asynchronous I/O in Database Architecture

## Executive Summary & Core Problem Statement

For more than two decades, `epoll` (introduced in kernel 2.5) was the answer to the C10K problem — how to handle 10,000 concurrent connections without falling over. That answer held up fine as long as storage was slow. But once storage moved from millisecond-scale spinning disks to microsecond-scale PCIe NVMe Gen 4/5 drives, `epoll` and the I/O model built around it started showing cracks that no amount of tuning could paper over.

**The core problem:** engines like ScyllaDB, Aerospike, and PostgreSQL aren't bottlenecked by the physical limits of the drive anymore — they're bottlenecked by the software overhead the Linux kernel imposes on every I/O call. Doing asynchronous I/O through `epoll` or the old POSIX AIO interface means the CPU keeps crossing the user space/kernel space boundary, and every syscall — `read()`, `write()`, `epoll_wait()` — costs thousands of cycles in context switching, KPTI page table protection, and data copying. On fast NVMe hardware, the CPU spends more time doing paperwork with the OS than moving actual bytes.

`io_uring`, landing in Linux 5.1, rebuilds this picture from the ground up. Two ring buffers, memory-mapped and shared directly between user space and the kernel, let an application submit millions of I/O requests and collect their results without making a single syscall in the common case.

This piece works through the mechanics of `io_uring`'s micro-architecture, sets it against the real limitations of `epoll` and `linux-aio`, and looks at how database engines are restructuring around it — which is really the substance behind any `io_uring` vs `epoll` comparison worth taking seriously.

---

## The Crisis of `epoll` and the Origins of Asynchronous I/O

### The Nature of `epoll` (Event-Driven Readiness)

`epoll` was built for network sockets, and its whole model rests on **readiness notification**, not completion notification.

The workflow looks like this:
1. You call `epoll_wait()` and block, waiting.
2. The network card gets a packet, and the kernel wakes you: "socket 5 has data, go read it."
3. You then make a separate `read(5)` call to actually copy bytes from the kernel buffer into your own.

Total latency $T_{epoll}$ works out to:
$$T_{epoll} = t_{syscall(epoll\_wait)} + t_{ctx\_switch} + t_{syscall(read)} + t_{vfs\_lookup} + t_{hardware\_io} + t_{interrupt}$$

### The `epoll` Disaster for Disk Storage I/O

`epoll` does its job well for networking. It falls apart for local disk storage. On Linux, regular files on ext4 or xfs always report "ready" to `epoll` — call `epoll_wait()` on a file descriptor and it returns instantly, every time. But then when you call `read()`, if the data isn't already sitting in the page cache, the kernel quietly blocks your thread until it comes back from disk.

That silent blocking violates the whole premise an event loop is built on. One stalled `read()` can freeze thousands of unrelated client connections sharing that same NodeJS or NGINX thread.

### The Collapse of Linux AIO (`io_submit`)

Linux did offer an asynchronous disk I/O interface before `io_uring` — `linux-aio`, via `io_submit` and `io_getevents`. Linus Torvalds was famously blunt about its shortcomings, and they were real:
1. **`O_DIRECT` only.** You manage your own buffering and skip the page cache entirely; buffered I/O just doesn't work through this path.
2. **Still blocks on metadata.** Even with `O_DIRECT`, `io_submit` can stall if the filesystem needs to allocate blocks or grab an inode lock.
3. **Syscall overhead persists.** Submitting even a batch of requests still costs at least one `io_submit` call.

Faced with those limits, PostgreSQL, MySQL, and MongoDB all ended up building large thread pools instead — dumping blocking `read`/`write` calls onto background threads to keep the main thread free. The tradeoff is that constant context-switching across hundreds of I/O threads thrashes the CPU's L1/L2 caches.

---

## The Micro-architecture of `io_uring`: The Shared Memory Marvel

Jens Axboe, who maintains the Linux block layer, designed `io_uring` specifically to remove these weaknesses. The model flips from "readiness" to **"completion"**: you hand the kernel a description of the work, it runs it start to finish, and it writes the result back into shared memory for you to pick up.

### Lock-Free Ring Buffer Data Structures

`io_uring` sets up two one-directional ring buffers:
1. **Submission Queue (SQ)** — tasks flowing user to kernel, made up of SQE (Submission Queue Entry) slots. Each SQE describes one command: "read 4KB from file X into buffer Y."
2. **Completion Queue (CQ)** — results flowing kernel to user, made up of CQE slots. Each CQE reports the outcome: "that read finished, error code 0."

Both of these arrays are memory-mapped directly into the application's address space. The application and the kernel are quite literally looking at the same RAM.

### Synchronization via Memory Barriers

Notice there's no mutex or spinlock anywhere in this design. Synchronization is handled entirely with atomic `Head`/`Tail` pointers and hardware-level memory barriers.

```mermaid
graph TD
    subgraph User_Space_Architecture
        DB[Database Engine Thread]
        SQ_Tail[SQ Tail Pointer (Atomic)]
        CQ_Head[CQ Head Pointer (Atomic)]
        SQ_Ring[Submission Queue Array - SQEs mmap]
        CQ_Ring[Completion Queue Array - CQEs mmap]
    end
    
    subgraph Kernel_Space_Architecture
        SQ_Head[SQ Head Pointer]
        CQ_Tail[CQ Tail Pointer]
        Kernel_Worker[io_wq Asynchronous Workers]
        Block_Layer[Linux Block Layer & NVMe Driver]
    end

    DB -->|1. Write I/O configuration (Opcodes, Buffers)| SQ_Ring
    DB -->|2. Atomic update via smp_store_release()| SQ_Tail
    SQ_Tail -.->|Memory Barrier| SQ_Head
    Kernel_Worker -->|3. Read I/O Command| SQ_Ring
    Kernel_Worker -->|4. Dispatch Command to Disk| Block_Layer
    Block_Layer -->|5. Completion Signal (DMA Done)| Kernel_Worker
    Kernel_Worker -->|6. Write Result Code (Status 0)| CQ_Ring
    Kernel_Worker -->|7. Atomic update via smp_store_release()| CQ_Tail
    CQ_Tail -.->|Memory Barrier| CQ_Head
    DB -->|8. Read CQE (smp_load_acquire) with zero syscalls| CQ_Ring
```

Say a database wants to submit 100 write tasks. It writes them into 100 SQE slots in memory, bumps the `SQ_Tail` pointer, then makes one `io_uring_enter()` call to wake the kernel and let it get to work. What used to be 100 `write()` syscalls is now one.

### The Ultimate Frontier: Eliminating Syscalls Entirely with SQPOLL

For applications chasing the lowest possible latency, `io_uring` offers `IORING_SETUP_SQPOLL`. Enable this flag and the kernel spawns a dedicated thread pinned to a CPU core, continuously polling the application's `SQ_Tail` pointer.

The moment the application pushes a new request into the SQ, that kernel thread sees it through shared memory and picks it up immediately — no syscall involved at all. Submission and completion both happen with zero context switches. At that point, I/O latency ($T_{iouring\_sqpoll}$) is basically bounded by the physical transfer time:
$$T_{iouring\_sqpoll} = t_{mem\_barrier} + t_{pcie\_dma\_transfer} + t_{nvme\_flash\_prog}$$

---

## Advanced Weapons for Database Engines

`io_uring` doesn't stop at basic I/O submission — it hands database engines a fuller toolkit.

### Registered Fixed Buffers

Normally, a `read()` or `write()` call forces the kernel to build an `iovec`, map the caller's RAM pages into the IOMMU so hardware can DMA directly, then tear that mapping down afterward. That's expensive, every single time.

With `io_uring`, a database can pre-register a large chunk of RAM once. The kernel pins it and sets up the IOMMU mapping ahead of time. From then on, `IORING_OP_WRITE_FIXED` operations move data straight between the disk controller and that memory region — no repeated address translation.

### Chained Requests (Linked SQEs)

Ordering matters a lot in a database. A typical sequence: write data (command 1), flush it with `fsync` (command 2), then update metadata (command 3).

With Linux AIO, that meant waiting for each step to complete before submitting the next. With `io_uring`'s `IOSQE_IO_LINK` flag, you submit all three at once, and the kernel guarantees command 2 only runs if command 1 succeeded. Fewer round trips between user and kernel space for the same guarantee.

### Unifying Network and Disk I/O

Maybe the biggest structural win is that `io_uring` handles every operation type — network, file, timeout, `fsync`, `fallocate` — through the same interface. A database no longer needs two separate machines running side by side: `epoll` for sockets and a thread pool for disk.

One event loop on one CPU core can accept a TCP connection (`IORING_OP_ACCEPT`), read an HTTP request (`IORING_OP_RECV`), and write to disk (`IORING_OP_WRITEV`) — all through a single ring.

---

## A Non-Blocking C++ Implementation

Here's a simplified C++ sketch of a storage engine built on `liburing`. Note how a C++ context object gets attached to `user_data` so the application can match completions back to the request that triggered them.

```cpp
#include <liburing.h>
#include <memory>
#include <cstdint>
#include <iostream>
#include <stdexcept>

// Custom request structure carrying internal application context
struct IOTransactionContext {
    int file_descriptor;
    uint64_t disk_offset;
    std::unique_ptr<char[]> memory_buffer;
    size_t length;
    uint32_t transaction_id;
};

class UltraFastStorageEngine {
private:
    struct io_uring ring;
    const unsigned int RING_DEPTH = 4096;

public:
    UltraFastStorageEngine() {
        struct io_uring_params params = {};
        // Maximum optimization: use SQPOLL to eliminate syscalls entirely
        params.flags |= IORING_SETUP_SQPOLL;
        params.sq_thread_idle = 2000; // Kernel thread sleeps after 2ms of no work
        
        if (io_uring_queue_init_params(RING_DEPTH, &ring, &params) < 0) {
            throw std::runtime_error("Kernel does not support this or ulimit restricts it!");
        }
    }

    void submit_async_write(IOTransactionContext* ctx) {
        // Fetch an empty SQE slot from the shared Ring Buffer
        struct io_uring_sqe *sqe = io_uring_get_sqe(&ring);
        if (!sqe) {
            // SQ is full, proactively submit so the Kernel can consume some entries
            io_uring_submit(&ring);
            sqe = io_uring_get_sqe(&ring);
        }
        
        // Configure the low-level asynchronous write opcode
        io_uring_prep_write(sqe, ctx->file_descriptor, 
                            ctx->memory_buffer.get(), ctx->length, ctx->disk_offset);
                           
        // CRITICAL: attach the C++ object pointer to the SQE's metadata (64-bit integer)
        io_uring_sqe_set_data(sqe, ctx);
    }

    void reap_completions_lockfree() {
        struct io_uring_cqe *cqe;
        unsigned head;
        unsigned count = 0;

        // Scan the CQ Ring Buffer entirely in local RAM (No Syscalls)
        io_uring_for_each_cqe(&ring, head, cqe) {
            // Cast the user_data pointer back to restore the context
            IOTransactionContext* ctx = static_cast<IOTransactionContext*>(io_uring_cqe_get_data(cqe));
            
            if (cqe->res < 0) {
                std::cerr << "I/O Error on Transaction " << ctx->transaction_id 
                          << " (Error code: " << cqe->res << ")\n";
            } else {
                // Handle successful business logic
                finalize_transaction(ctx, cqe->res);
            }
            count++;
        }
        
        if (count > 0) {
            // Update the atomic CQ Head to tell the Kernel we've harvested these entries
            io_uring_cq_advance(&ring, count);
        }
    }

private:
    void finalize_transaction(IOTransactionContext* ctx, int bytes_written) {
        // Send an ACK back to the client over the network, or mark the WAL
        delete ctx; // Clean up memory
    }
    
    ~UltraFastStorageEngine() {
        io_uring_queue_exit(&ring);
    }
};
```

---

## Lessons Learned & Best Practices for Systems Architects

The move from `epoll` to `io_uring` is already happening — Redis 7.0, PostgreSQL 15, and NodeJS 20+ have all started experimenting with it. But it's not a drop-in swap; a few things matter before you commit to it.

1. **Know the limits of the OS page cache.** If `io_uring` reads a file whose data is already sitting in the page cache, the kernel's background `io_wq` worker still has to get involved, which eats into the speedup. `io_uring` shows its real advantage when paired with **`O_DIRECT`** — which means the database needs its own buffer pool and a willingness to bypass the OS cache entirely.
2. **Watch for security exposure.** Mapping kernel structures directly into user space via mmap has a track record of privilege-escalation CVEs. Docker, Kubernetes, and SELinux frequently disable `io_uring` by default through seccomp filters — expect to explicitly whitelist those syscalls before the database will even start.
3. **Manage buffer lifetime carefully.** The buffer you hand to an SQE has to stay valid until its CQE comes back. Free that buffer early — from a careless thread, say — and the kernel will DMA data into memory that's already been reused for something else. That's memory corruption, and it's the kind of bug that can take down the whole process.
4. **Consider polling I/O for the fastest NVMe drives.** On drives with sub-10-microsecond latency, the interrupt-based completion signal becomes the bottleneck itself — a context switch alone costs 3-4 microseconds. `IORING_SETUP_IOPOLL` makes the kernel busy-wait on the SSD's status instead of sleeping. That burns a full CPU core, but it gets latency close to the physical floor.
5. **Don't rush to rip out `epoll` for ordinary web servers.** `io_uring` can handle networking too, but for typical short-lived HTTP/TCP connections the benchmarks don't show enough of a gap over `epoll` to justify a full rewrite. `io_uring` clearly wins in storage; in networking, `epoll` is still a solid, battle-tested choice.

---
