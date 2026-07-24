---
seo_title: "Sharding Algorithms Explained: Hash, Range & Directory"
seo_description: "A deep dive into sharding algorithms — hash-based, range-based, and directory-based — covering consistent hashing, hotspots, and lease fencing."
focus_keyword: "sharding algorithms"
---

# Sharding Algorithms at Hyperscale: Math, Data Structures, and Micro-Architecture

## Why Sharding Stops Being Optional

Once a dataset crosses into petabyte territory and traffic climbs past a few million transactions per second, a single-node database simply runs out of road. There's no clever indexing trick or bigger disk that fixes it — the physics of one disk and one CPU cap what you can do. At that point, **sharding** — splitting data across many independent nodes — stops being an optimization and becomes the only path forward.

It's tempting to think of sharding as "just splitting a table into chunks," but that undersells it badly. Getting it right touches discrete mathematics, graph theory, and some genuinely low-level hardware engineering. This article walks through the three sharding models that show up again and again in production systems — **hash-based sharding**, **range-based sharding**, and **directory-based sharding** — not just as abstract algorithms, but with an eye on CPU cache behavior, memory alignment, SIMD instructions, and the messier realities of OS-level I/O.

**The core problem:** routing a query to the one physical server holding the relevant data sounds simple, but each approach has a sharp edge. Hash-based sharding falls apart the moment a node dies — you get a rebalancing storm that saturates the network. Range-based sharding solves the range-scan problem but concentrates writes onto whichever shard owns "now," burning out its disk while neighboring shards sit idle. Directory-based sharding sidesteps both issues by adding a layer of indirection — and that layer becomes a bottleneck with its own latency problems. The question this article tries to answer: how do you route requests across a distributed system while still keeping the CPU-level cost of routing down in the tens of nanoseconds?

**What we'll cover:**
1. Consistent hashing bounds data movement to roughly $O(1/N)$, but on its own it produces lopsided load distribution — virtual nodes aren't optional, they're required.
2. Cache-line behavior dictates routing speed. A shard router built on linked lists will bleed performance to L1/L2 cache misses; contiguous, 64-byte-aligned arrays are the only sane choice.
3. Splitting a range shard under load is one of the trickier pieces of distributed systems engineering — it demands kernel bypass, zero-copy I/O, and isolated snapshots, or the service goes down mid-split.
4. Directory-based sharding solves its central bottleneck with client-side caching, but that cache needs a lease mechanism — and storage nodes need to know how to fence off clients whose lease has expired.

---

## Hash-Based Sharding: The Consistent Hashing Ring and CPU Micro-Dynamics

Hash-based sharding takes a record's key ($k$), runs it through a hash function, and maps the result onto a server. The naive version of this — $S(k) = h(k) \pmod N$ — is simple to implement and a genuine liability in production. Add a single server (changing $N$ to $N+1$), and roughly 99% of keys land on a different node than before. That triggers a rebalancing storm: a flood of data movement across the network that can knock out east-west bandwidth for hours.

### 1 Consistent Hashing and Virtual Nodes

David Karger's consistent hashing scheme fixed this by rethinking the mapping entirely. Instead of a modulo operation, the identifier space is bent into a ring — say, from $0$ to $2^{160}-1$.

- Each server node is hashed onto a coordinate on that ring.
- Each key is hashed too, and walked clockwise to the first node it meets.

When a node fails, only the data it alone owned needs to move — to its neighbor on the ring. That brings data movement down to close to the theoretical minimum of $\approx \frac{1}{N}$.

The catch is that hash placement is random, so nothing stops one server from ending up with, say, half the ring's data purely by chance — a real load imbalance problem. The fix is **virtual nodes**: instead of hashing a physical node once, you hash it thousands of times with different suffixes, $V(node_i) = \{h(node_i \parallel j) \mid j \in [1, v]\}$. Spread thin enough across the ring, these virtual replicas flatten the distribution curve to something close to uniform.

### 2 Making the Ring Fast: Contiguous Arrays and SIMD

Here's where hardware reality intrudes on the clean theory. If you implement the ring as a circular linked list — which is the natural data structure to reach for — you'll pay for it in cache misses. In C++ or Rust, pointers scattered across heap memory make the CPU's instruction pipeline stall constantly as it chases each dereference.

The fix is to flatten the ring into a static, sorted, contiguous array instead:

- Each element is sized and aligned to match the CPU's 64-byte L1 cache line exactly.
- Each entry takes 16 bytes — 8 for the hash, 8 for a pointer to the node identifier.
- That means a single cache line pulled from RAM carries four virtual nodes at once.

Binary search over this array gives you $O(\log(N \times v))$ lookup time. Pair that with a fast non-cryptographic hash — MurmurHash3 or CityHash are common choices — and let the compiler exploit SIMD (AVX-512) where it can, and routing even a heavy stream of keys costs only a few nanoseconds per key.

```rust
// Low-level Rust code illustrating a memory-packed static hash-ring structure
use std::hash::{Hash, Hasher};
use fasthash::murmur3::Murmur3Hasher_x64_128;

pub struct ConsistentHashRing {
    // Contiguously allocated vector array (Contiguous Memory Allocation)
    ring: Vec<(u64, String)>,
    virtual_nodes: usize,
}

impl ConsistentHashRing {
    pub fn new(virtual_nodes: usize) -> Self {
        ConsistentHashRing {
            ring: Vec::with_capacity(10_000), // Prevents heap fragmentation
            virtual_nodes,
        }
    }

    pub fn add_node(&mut self, node_id: &str) {
        for i in 0..self.virtual_nodes {
            let virtual_key = format!("{}#{}", node_id, i);
            let mut hasher = Murmur3Hasher_x64_128::default();
            virtual_key.hash(&mut hasher);
            self.ring.push((hasher.finish(), node_id.to_string()));
        }
        // Static sort to prepare for micro-level binary search
        self.ring.sort_unstable_by(|a, b| a.0.cmp(&b.0));
    }

    pub fn get_node(&self, key: &str) -> Option<String> {
        let mut hasher = Murmur3Hasher_x64_128::default();
        key.hash(&mut hasher);
        let hash_val = hasher.finish();

        // The hardware branch predictor will forecast this binary search loop with extreme accuracy
        match self.ring.binary_search_by(|probe| probe.0.cmp(&hash_val)) {
            Ok(idx) => Some(self.ring[idx].1.clone()),
            Err(idx) => {
                let wrapped_idx = if idx == self.ring.len() { 0 } else { idx };
                Some(self.ring[wrapped_idx].1.clone())
            }
        }
    }
}
```

---

## Range-Based Sharding: Dynamic Rebalancing and I/O Hotspots

Hash sharding's biggest weakness is that it throws away data locality entirely. A query like `SELECT * WHERE id BETWEEN 10 AND 50` has to fan out to every shard (scatter-gather), because there's no relationship between key proximity and physical placement.

**Range-based sharding** takes the opposite approach: it splits the key space $\mathcal{K}$ into contiguous, non-overlapping ranges $R_i = [K_{i, min}, K_{i, max})$. A range query gets routed to one or two adjacent shards instead of the whole cluster, which is a huge win for network cost.

### 1 The Data Hotspot Problem

That range-scan efficiency comes at a price: hotspots. If the shard key is a timestamp, every new INSERT in the system lands on whichever single shard owns the current time window — its disk takes 100% of the write load while shards holding yesterday's data sit nearly idle. The only real fix is splitting shards dynamically, at runtime, before they become a bottleneck.

### 2 How Shard Splitting Actually Works

When a shard $R_0 = [K_{min}, K_{max})$ grows past its size ceiling, it gets split at some point $K_{split}$:

- A new shard $R_{new} = [K_{split}, K_{max})$ is created on a fresh server.
- The old shard shrinks to $R_{old} = [K_{min}, K_{split})$.

The tricky part is doing this without blocking writes — copying hundreds of gigabytes can't come at the cost of downtime. In practice, engineers lean on MVCC combined with the filesystem's own copy-on-write machinery:

- At the kernel level, Linux takes an instant, byte-free snapshot of the SSTable files via copy-on-write (ZFS or Btrfs handle this natively).
- A background compaction process then streams that data over the network to the new server, at whatever pace doesn't starve foreground I/O.
- Meanwhile, a catch-up log buffer records every write that lands on $R_{new}$'s key range while the copy is still in flight.
- Once the bulk copy finishes, the router flips traffic over in roughly a microsecond, replays the catch-up log against the new server, and the cluster confirms the split via Paxos or Raft.

```cpp
// Ultra-fast range-routing structure in C++
struct alignas(64) RangeShard {
    std::string min_key;
    std::string max_key;
    std::string node_endpoint;

    // Branchless logic technique for the CPU
    inline bool contains(const std::string& key) const noexcept {
        return key >= min_key && key < max_key;
    }
};

class ZeroCopyRangeRouter {
    std::vector<RangeShard> shards;
public:
    std::string route_point_query(const std::string& key) const {
        // lower_bound optimized to be preloaded into cache via SIMD
        auto it = std::lower_bound(shards.begin(), shards.end(), key, 
            [](const RangeShard& s, const std::string& k) { return s.max_key <= k; });

        if (it != shards.end() && it->contains(key)) [[likely]] {
            return it->node_endpoint;
        }
        throw std::runtime_error("Consistency check failed.");
    }
};
```

---

## Directory-Based Sharding: A Central Lookup and the Cost of Latency

Directory-based sharding takes abstraction to its logical extreme. There's no formula mapping keys to servers at all — just a lookup table: $S(k) = DirectoryLookup(k)$.

- The upside is real flexibility: any piece of data, for any tenant, can move to any node at any time, unconstrained by a fixed hash or range formula. Google Spanner and FoundationDB both lean on variations of this model.
- The downside is equally real: that lookup table becomes a single point of contention. Every query in the system has to ask the directory service (typically backed by something like ZooKeeper or etcd) where to go.

### 1 Caching the Directory Without Breaking Consistency

A round-trip RPC to the directory service costs tens of milliseconds — fine occasionally, unworkable per-query at scale. The obvious fix is to cache the routing table in the client's own memory.

That introduces a genuinely hard consistency problem, though: if a shard moves and the client's cache doesn't know yet, it might send a write to the wrong server — and that write could silently corrupt data that's since moved elsewhere.

**The fix is a lease-bound cache:**

1. The directory server hands the client a routing table along with a lease token, valid for a bounded window $\Delta t$ (5 seconds is a typical value).
2. The client is free to talk directly to shard servers as long as the lease holds, under the constraint $T_{current} + \epsilon < T_{issued} + \Delta t$, where $\epsilon$ accounts for NTP clock skew between machines.
3. The real safety net sits on the storage node itself, which holds its own lease from the directory center. If that lease expires — a severed link, a network partition — the storage node fences itself: it stops serving NVMe I/O, rejects every incoming request, and returns a `409 Conflict`.
4. That self-fencing behavior is what actually protects data integrity. A client operating on stale routing information (effectively a zombie with a desynchronized clock) can send all the writes it wants — the storage node simply refuses them once its own lease has lapsed, which is what keeps ACID guarantees intact even under misrouted, "dirty" writes.

---

## Choosing Between the Three

There's no single sharding algorithm that wins across the board.

- Hash-based sharding gets the most out of CPU and memory — a well-tuned hash ring resists load imbalance almost entirely — but it gives up range-scan performance completely.
- Range-based sharding keeps data physically ordered, which is what makes large analytical scans efficient, but it commits you to an ongoing, sometimes painful campaign of shard splitting at the storage layer.
- Directory-based sharding buys you the freedom to move data anywhere, for any reason, at the cost of a genuinely complex support system: client-side caching, directory leases, and fencing logic on every storage node.

Understanding these algorithms down to cache-line and register-level detail isn't academic overkill — it's what separates a sharding layer that degrades gracefully under load from one that falls over the first time a node dies at 3 a.m.
