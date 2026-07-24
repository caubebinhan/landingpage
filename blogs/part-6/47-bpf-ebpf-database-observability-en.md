---
seo_title: "BPF and eBPF for Database Observability at the Kernel Level"
seo_description: "How eBPF gives near-zero-overhead database observability: uprobes, kprobes, eBPF maps, NUMA-aware per-CPU maps, and lessons from running eBPF at data-center scale."
focus_keyword: "eBPF database observability"
---

# 47: BPF and eBPF in Database Observability: Watching a Database from Inside the Kernel

## Summary & Core Problem Statement

In a world of distributed microservices and heavy transaction processing, the database is usually where the bottleneck actually lives — and yet figuring out what it's doing under load remains genuinely hard.

The core problem is that traditional databases behave like black boxes. To understand why a query is slow, engineers reach for the Slow Query Log, install an in-band profiling agent, or fall back to classic OS tools like `strace` or `tcpdump`.
- Turning on logging or an in-band agent typically adds 30-50% overhead, which can crash the very system you're trying to diagnose before you find the root cause.
- `strace`, built on `ptrace`, forces the OS to stop the database process on every single system call — latency can balloon by a hundred times or more.
- `tcpdump` captures a flood of raw packets and burns CPU just copying data from kernel space to user space.

**eBPF (Extended Berkeley Packet Filter)** changed the equation for database observability. It lets engineers run tiny, verified programs directly inside the kernel — tracking every packet crossing the NIC, every function call inside PostgreSQL, every block written to an SSD — with overhead that's practically zero, often under 1%.

This piece walks through the eBPF virtual machine's microarchitecture, how uprobes and kprobes let you hook into a running database without a restart, and what actually breaks when you run eBPF observability at data-center scale.

---

## The Microarchitecture Behind the eBPF Virtual Machine

eBPF started life filtering network packets (as cBPF, inside tools like tcpdump) and has since grown into a general-purpose virtual machine running at the heart of the Linux kernel.

### An Instruction Set Mapped Almost 1:1 to Hardware

The modern eBPF architecture maps closely onto 64-bit processors like x86_64 and ARM64. The eBPF "processor" works with 11 virtual 64-bit registers ($R0$ through $R10$):
- $R0$ holds the return value.
- $R1$-$R5$ hold arguments when calling a function.
- $R6$-$R9$ are callee-saved registers preserved across calls.
- $R10$ is a read-only frame pointer for accessing the stack.

Just-in-time (JIT) compilation turns eBPF bytecode into native machine code. Because the eBPF register model mirrors x86_64 so closely, this JIT step is fast, and the resulting code runs about as fast as directly compiled C.

### The Verifier: eBPF's Gatekeeper

The kernel is not a place for bugs. A divide-by-zero, a null pointer, or an infinite loop inside kernel code can bring down the whole machine. So before any eBPF program runs, it has to pass the **verifier**.

The verifier does static control-flow-graph analysis to guarantee:
1. No infinite loops — the program must provably terminate (eBPF is intentionally not Turing-complete).
2. No out-of-bounds memory access.
3. No access to forbidden kernel memory regions.
4. Stack usage capped at 512 bytes.

Verification runs in roughly $\mathcal{O}(N \times E)$ time, where $N$ is the number of states and $E$ the number of edges in the flow graph. Only once it's marked safe does the program get handed to the JIT.

---

## eBPF Maps: Sharing Data Without Copying It

An eBPF program running in the kernel might collect a query's latency — but how does that number reach user space (a Prometheus exporter, say) without the usual cost of copying data across the kernel boundary? That's what **eBPF maps** are for.

Maps are key-value structures allocated in the kernel's non-pageable physical RAM. Both the eBPF program and a user-space process can read and write the same map through a file descriptor, which sidesteps the data-copying syscall entirely.

### Hash Maps (RCU-Protected)

Hash maps hold state. A typical pattern: when a request packet arrives, eBPF writes `Time_Start` into a hash map keyed by the TCP tuple (IP, port). When the response leaves, eBPF looks up that key, computes the latency, and records it.

The underlying hashing uses Read-Copy-Update (RCU), which gives lock-free concurrent reads and access speeds in the tens of millions of IOPS without CPU contention.

### Ring Buffers

To stream events from kernel to user space — a feed of slow queries, say — eBPF uses a ring buffer: a lock-free, multi-producer single-consumer circular queue. CPU cores producing events write directly into it, and the user-space agent drains it via `epoll` polling. The kernel side is never blocked, even if the user-space consumer falls behind.

---

## Where to Attach: Probing Techniques

How does eBPF actually see what a database is doing? Through hooks placed at different points in the system.

### Network Hooks (TC / XDP / Socket Filter)

If you'd rather not touch the database process at all, you can observe at the network layer. eBPF can attach to Traffic Control (TC) or the eXpress Data Path (XDP), right at the NIC driver level. By parsing TCP traffic according to the PostgreSQL or MySQL wire protocol, an eBPF program can measure query latency, query counts, and retransmission rates without the database ever knowing it's being watched. Overhead here is extremely low, but reassembling queries that get fragmented across multiple TCP packets is genuinely difficult.

### User Probes (uprobes and uretprobes)

This is the sharpest tool in the box. Uprobes let eBPF attach directly to C/C++ functions inside a database's own binary. In PostgreSQL, for instance, you could place a uprobe at the entry of `exec_simple_query()` and a uretprobe at its return.

**How it actually works under the hood:** when eBPF attaches a uprobe to a function, it quietly overwrites the function's first machine instruction with an `int3` breakpoint trap. When the CPU reaches that instruction, it raises a hardware exception, the OS takes over, jumps into the kernel, and invokes the eBPF handler. Once that handler finishes, the CPU restores the original instruction and lets execution continue.

**Where uprobes bite you:** each jump through a uprobe — the exception plus the context switch — costs roughly 1.5 to 3.0 microseconds on modern hardware.
- Attach one to `exec_simple_query`, which runs maybe 10,000 times a second, and you're spending about 30ms total — call it 3% overhead, perfectly tolerable.
- Attach one to something like `btr_search_leaf`, which walks B-Tree leaf pages a million times a second, and you get an interrupt storm: the CPU needs 3 seconds of work for every 1 second that actually elapses. The database effectively hangs.

Always check a function's call frequency before wiring up a uprobe.

### Kernel Probes (kprobes)

Kprobes are how you watch a database's interaction with disk and RAM. Attaching kprobes to `vfs_read()` and `vfs_write()`, combined with some pattern recognition, lets eBPF track the page cache miss ratio directly — you can see exactly how many times a MySQL `SELECT` actually had to reach down to an NVMe drive, and how many nanoseconds it spent inside the block layer.

---

## Tuning for the Hardware: NUMA and Cache Behavior

Running an eBPF observability stack on a 128-core server handling billions of transactions means the map design has to respect the physical realities of the processor, not just the logical model.

### NUMA and Per-CPU Maps

On a NUMA (Non-Uniform Memory Access) machine, a CPU reaching across to another CPU's local RAM pays a steep latency penalty. If an eBPF program on core 0 and another on core 64 both write into the same global hash map, you get memory contention that saturates the interconnect (UPI or Infinity Fabric).

The fix is **per-CPU maps**. The kernel allocates a separate local copy of the map for each physical core, so a program running on a given core only ever writes to its own slice of RAM. Every write becomes lock-free and contention-free, with throughput bounded only by L1 cache bandwidth.

### Protecting the Instruction Cache

The L1 instruction cache (L1i) is tiny — typically 32KB — and the database needs most of that space for its own logic (query planner, execution engine). If an eBPF program compiles down to too much machine code, every invocation via kprobe or uprobe risks spilling into L1i and evicting the database's own instructions. When the database resumes, it eats a cache miss and has to reload from L2/L3 — a real and measurable stall.

The practical takeaway: write lean eBPF code. Merge checks where you can, unroll loops carefully, and try to keep the whole program under 4KB so it can share L1i peacefully with the database it's watching.

---

## Lessons for Systems Engineers

1. **Start with kprobes and network hooks; be careful with uprobes.** Never test a uprobe for the first time in production. Use a staging environment and tools like `bcc` or `bpftrace` to measure a function's call frequency (its `count()`) before you attach anything that collects latency.
2. **Flatten your data structures.** eBPF has no heap, no `malloc`. If you need to track a multi-step SQL transaction, build the finite state machine in user space — eBPF's job is just to push events up through the ring buffer, not to hold complex correlation logic inside the kernel.
3. **Watch your map capacity.** Every eBPF map has a `max_entries` cap. Once a hash table fills up, new transactions simply stop being observed. Your user-space agent needs to actively evict stale keys to make room.
4. **Lock down permissions.** eBPF can read the kernel's entire RAM, including passwords or secrets in flight. In production, restrict access to `CAP_BPF` or `CAP_SYS_ADMIN`, and use code signing to keep unknown eBPF programs out.
5. **Don't build your own observability stack from scratch.** Lean on existing tooling — Cilium, Tetragon, Pixie, or the `cilium/ebpf` library in Go — and push raw metrics into a Prometheus histogram so you get percentile views (P99, P99.9) in Grafana for free.

---
