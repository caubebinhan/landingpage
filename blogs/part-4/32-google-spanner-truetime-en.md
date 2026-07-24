---
seo_title: "Google Spanner TrueTime: How Atomic Clocks Enable Global Consistency"
seo_description: "How Google Spanner's TrueTime API bounds clock uncertainty with GPS and atomic clocks, and how Commit Wait uses that bound to guarantee external consistency."
focus_keyword: "Google Spanner TrueTime"
---

# Google Spanner & TrueTime: Bending the Axis of Time in Distributed Systems

## Executive Summary

The CAP theorem forces a choice between Consistency and Availability the moment a network partition hits. Most modern databases — Cassandra, DynamoDB — pick Availability and settle for Eventual Consistency. The reason isn't that anyone wrote a worse algorithm; it's that no two servers on Earth run perfectly synchronized clocks. Network latency and quartz-crystal drift make sure of that.

Google decided not to accept that trade-off. **Google Spanner** is, as far as public systems go, the only globally distributed relational database that achieves true **External Consistency**. The mechanism behind it is the **TrueTime API** — a purpose-built time infrastructure combining GPS with atomic clocks to turn "what time is it" from a fuzzy question into a value with a mathematically guaranteed error bound.

This piece walks through TrueTime's architecture: the error function $\epsilon(t)$, how Paxos routes writes, and — the part that matters most — the **Commit Wait** mechanism, where Spanner deliberately stalls a transaction just long enough to absorb clock uncertainty.

**Problem Statement:**
In a global system, if transaction $T_1$ (New York) finishes before transaction $T_2$ (Tokyo) even starts, causality demands that timestamp $S_1$ be less than $S_2$. But if Tokyo's clock happens to run 5ms behind New York's, the system could easily assign $S_2 < S_1$ — a paradox that breaks any attempt at global transaction ordering. The obvious fix, a central timestamp server, just moves the bottleneck: cross-continental round trips of hundreds of milliseconds make that a non-starter at Google's scale.

**Lessons Learned:**
1. **Accept that time is never exact.** Instead of returning a single number, TrueTime returns an interval $[t_{earliest}, t_{latest}]$ — the truth is guaranteed to lie somewhere in there.
2. **Commit Wait trades latency for consistency.** Before acknowledging a write, the coordinator waits out the full uncertainty window $2\epsilon$ to make sure causality holds.
3. **Diverse failure modes reinforce each other.** GPS can be jammed but doesn't drift. Atomic clocks drift but can't be jammed. Together they cover each other's blind spots.

---

## The Time Problem at the Heart of Distributed Computing

Every server keeps time using a quartz crystal oscillator, and no two oscillate at exactly the same rate — chip temperature, power supply variance, and hardware aging all nudge the frequency slightly. This is **clock drift**, and it's unavoidable in commodity hardware.

The traditional fix, NTP, asks a reference server for the time over LAN/WAN. But router queue buffers fluctuate, so round-trip time is rarely symmetric, and NTP has no reliable way to compensate for that asymmetry. Across a wide-area network, clock error between two NTP-synced servers routinely reaches 100–200ms.

That's a big window. Tens of thousands of transactions could get reordered inside it, which is exactly what breaks external consistency and linearizability.

---

## TrueTime Architecture: A Network of Atomic Clocks and Satellites

To get past what NTP can offer, Google built dedicated hardware instead of relying on software alone.

### A Dual Master Network

TrueTime's hardware layer consists of Time Masters installed in every Google datacenter, of two kinds:

1. **GPS Masters** — rooftop antennas reading satellite signals. Very precise, but vulnerable to solar interference, radio noise, or atmospheric errors that can knock out signal entirely.
2. **Atomic Masters** (rubidium/cesium clocks) — housed deep in the server vault, measuring electron transitions. They never lose signal, but drift slightly over time (on the order of a few microseconds per day).

The combination works because the two failure modes are largely orthogonal: if GPS drops out, the atomic clock keeps things steady; if the atomic clock drifts too far, GPS pulls it back. Some top-tier machines run both and are informally called Armageddon Masters.

### Marzullo's Algorithm and the TrueTime Daemon

Every Spanner machine runs a background TrueTime daemon that periodically polls a set of Masters — GPS and atomic, local and remote. Once it collects the responses, it runs a **modified Marzullo's Algorithm**, which intersects the reported intervals and automatically discards any Master whose answer looks inconsistent with the rest — the "liars" caused by hardware faults or a delayed fiber link.

---

## The Math Behind TrueTime: Bounding Uncertainty

TrueTime's real contribution isn't returning a precise number — it's returning a provably safe **uncertainty interval**.

The API exposes `TT.now()`, which returns:
$$ [t_{earliest}, t_{latest}] $$
The system guarantees that the true absolute time $t_{abs}$ at the moment of the call satisfies:
$$ t_{earliest} \le t_{abs} \le t_{latest} $$

### The Error Function $\epsilon(t)$

Call the uncertainty $\epsilon$; the interval width is always $2\epsilon$. Right at the moment a server successfully syncs with a Master ($t_{sync}$), error is at its minimum ($\epsilon_{sync} \approx 0$). From there it grows as the local quartz crystal drifts, at a worst-case rate $\rho$ (Google generally assumes $\rho \approx 200\mu s/\text{second}$):

$$ \epsilon = \epsilon_{sync} + \rho \cdot (t - t_{sync}) $$

When `TT.now()` is called, the server checks its local hardware clock $C(t)$:
- $t_{earliest} = C(t) - \epsilon$
- $t_{latest} = C(t) + \epsilon$

Thanks to the dedicated GPS/atomic hardware, average $\epsilon$ sits around **1–7ms** — compared to roughly 150ms on public NTP infrastructure.

---

## Commit Wait: Stalling Time to Protect Causality

Every tablet in Spanner is replicated via **Paxos**. On a write, the Paxos leader assigns a timestamp to the transaction before it's recorded in the write-ahead log.

### The Monotonic Timestamp Rule

When transaction $T_1$ requests a write, the leader calls `TT.now()` and assigns $s = TT.now().latest$ — the largest plausible value in the interval. Taking the upper bound guarantees the timestamp never collides with something that already happened.

### The Commit Wait Rule

Commit Wait says: **the system cannot acknowledge the transaction as complete until real time $t_{abs}$ has definitely passed $s$.**

How does it know that's happened? The coordinator keeps calling `TT.now()` until it sees $t_{earliest} > s$. At that point, since $t_{abs} \ge t_{earliest}$ by definition, it can conclude with certainty that $t_{abs} > s$.

The wait is usually on the order of $2\epsilon$ — up to roughly 7ms in the worst case.

**Why bother waiting?** Because it absorbs the relativistic slop in Earth-scale clocks. Say $T_1$ gets $s = 100$. The leader waits until $t_{abs} > 100$ before telling the client it succeeded. Client A tells Client B, and Client B starts $T_2$. Since $T_2$ can only start after $t_{abs} > 100$, when it calls `TT.now()`, its interval is guaranteed to have $t_{earliest} > 100$ — so $T_2$'s timestamp $s_2$ is necessarily greater than 100. The causal order $s_1 < s_2$ holds globally, by construction.

```cpp
// Pseudocode modeling the micro-architecture of Commit Wait in C++
struct TrueTimeInterval {
    int64_t earliest_us;
    int64_t latest_us;
};

class TrueTimeAPI {
public:
    TrueTimeInterval now();
};

class PaxosLeaderEngine {
private:
    TrueTimeAPI tt_api;
    int64_t last_assigned_timestamp = 0;
    
public:
    int64_t PrepareTransaction() {
        // Take the maximal future timestamp
        TrueTimeInterval current_time = tt_api.now();
        int64_t s = current_time.latest_us;
        
        // Force strict monotonic increase
        if (s <= last_assigned_timestamp) {
            s = last_assigned_timestamp + 1;
        }
        last_assigned_timestamp = s;
        return s;
    }

    void CommitTransaction(Transaction tx, int64_t s) {
        // Replicate data out to the quorum over fiber
        // This network transmission window (~2-5ms) will ABSORB part of the wait time.
        ReplicateToPaxosQuorum(tx, s);
        
        // Lock the thread to protect causality - begin Commit Wait
        while (true) {
            TrueTimeInterval wait_time = tt_api.now();
            if (wait_time.earliest_us > s) {
                // The uncertainty has passed. Current absolute time is definitely > s
                break; 
            }
            // Save CPU cycles, ask the OS kernel to suspend the thread for the remaining microseconds
            HardwareInterruptNanosleep(wait_time.earliest_us - s);
        }
        
        // Safe. Send 200 OK back over the wide-area network
        RespondToClient(tx.client_id, SUCCESS);
    }
};
```

---

## Lock-Free Read-Only Transactions Across Continents

Commit Wait costs roughly 7ms of latency on writes. What it buys back is a genuine win on reads: **lock-free reads across continents**.

If an application in Vietnam wants to read the User table, it doesn't need to call all the way to a US datacenter for a distributed read lock. It just calls `TT.now().latest` to get a timestamp $s_{read}$, then hands that to a local replica in the Vietnam datacenter. That replica — stored under MVCC — simply looks up the version of the row whose timestamp is closest to $s_{read}$ and returns it. Writes happening anywhere else in the world can never collide with or block that read.

Read throughput scales essentially without limit, and latency approaches zero. This is the core mechanism behind the read-heavy infrastructure powering Google Ads.

---

## A Micro-Architecture and System-Calls View

To hit microsecond precision, the TrueTime daemon can't afford ordinary network syscalls like `recvfrom()` — an OS context switch alone would corrupt $\epsilon$.

- **Zero-context-switch design:** TrueTime daemon data lives in a shared-memory segment mapped directly into user space.
- **Reading CPU registers directly:** a call to `TT.now()` reads the crystal oscillation signal straight from a hardware register (e.g., the `RDTSC` instruction on x86_64), paired with memory barriers (`MFENCE`/`LFENCE`) to keep speculative execution from introducing noise.
- **Watching crystal temperature:** the daemon polls the CPU's temperature sensors continuously. As temperature rises, the crystal drifts faster, so the algorithm widens $\rho$ toward its most conservative bound automatically, rather than risk an undetected sync failure.

Put together, these choices are what let Google Spanner and its TrueTime API offer external consistency at global scale without paying for a central bottleneck — the physical limits of clock synchronization get turned into a number the system can reason about, instead of a source of silent corruption.
