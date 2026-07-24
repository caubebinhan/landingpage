---
seo_title: "Thuật Toán Sharding: Hash, Range và Directory ở Quy Mô Lớn"
seo_description: "So sánh ba thuật toán sharding — Hash-based, Range-based, Directory-based — và cách mỗi mô hình đánh đổi giữa cân bằng tải, range scan và độ trễ routing."
focus_keyword: "thuật toán sharding"
---

# Thuật Toán Sharding Ở Quy Mô Hyperscale: Toán Học, Cấu Trúc Dữ Liệu Và Vi Kiến Trúc

## Tóm tắt Điều hành

Khi dữ liệu vượt ngưỡng petabyte và lượng truy cập chạm mốc hàng triệu giao dịch mỗi giây, kiến trúc cơ sở dữ liệu nguyên khối bắt đầu chạm trần vật lý của đĩa cứng và CPU đơn lẻ. Lối thoát gần như duy nhất là **sharding** — phân tán tải trọng ra hàng ngàn máy chủ.

Nhưng sharding không đơn thuần là "chia nhỏ dữ liệu ra nhiều máy". Đây là một trong những bài toán khó nhất của hệ thống phân tán, nằm ở giao điểm giữa toán rời rạc, lý thuyết đồ thị và kỹ thuật phần cứng cấp thấp. Bài viết này đi qua ba mô hình thuật toán sharding phổ biến nhất trong ngành: **Hash-based Sharding**, **Range-based Sharding**, và **Directory-based Sharding** — không chỉ ở tầng thuật toán, mà cả ở tầng vi kiến trúc CPU: cache line, SIMD, và hành vi I/O của hệ điều hành.

**Vấn đề cốt lõi:**
Định tuyến một truy vấn đến đúng máy chủ vật lý chứa dữ liệu tưởng đơn giản nhưng lại kéo theo chi phí đáng kể. Chọn Hash-based, hệ thống dễ gặp "rebalancing storm" khi một node chết đi. Chọn Range-based, một shard duy nhất có thể biến thành hotspot, gánh hết tải trong khi các shard khác rảnh rỗi. Chọn Directory-based, node định tuyến trung tâm dễ trở thành bottleneck với độ trễ tăng vọt. Vậy làm sao giữ được độ trễ truy cập ở mức vài chục nano-giây trong khi vẫn giải quyết được bài toán phân phối này?

**Bài học rút ra:**
1. **Consistent Hashing không hoàn hảo:** nó giới hạn lượng dữ liệu cần di chuyển ở mức $O(1/N)$, nhưng lại gây mất cân bằng tải nếu không có Virtual Nodes đi kèm.
2. **CPU cache line quyết định tốc độ:** thuật toán định tuyến shard không nên dùng linked list — tỷ lệ L1/L2 cache miss sẽ kéo hiệu năng xuống đáy. Mảng bộ nhớ liền kề và alignment 64-byte là điều bắt buộc.
3. **Range shard splitting là việc khó:** khi một shard theo khoảng bị đầy, việc tách đôi nó đòi hỏi zero-copy I/O và snapshot cách ly, nếu không muốn gây downtime.
4. **Lease và fencing trong Directory Sharding:** giải quyết điểm nghẽn directory cần cache cục bộ ở client, được bảo vệ bằng lease có thời hạn. Nếu client bị treo, storage node phải đủ khôn để từ chối các request đã hết hạn.

---

## Hash-based Sharding: Consistent Hashing và Vi Kiến Trúc CPU

Sharding theo hàm băm dùng một hàm băm (mật mã hoặc không) để biến định danh bản ghi ($k$) thành một điểm trong không gian ảo, rồi ánh xạ điểm đó vào một máy chủ cụ thể.

Cách làm đơn giản nhất — chia dư $S(k) = h(k) \pmod N$ — lại là một cái bẫy. Khi $N$ đổi thành $N+1$ (thêm một node), gần như toàn bộ khóa sẽ đổi kết quả modulo, kéo theo một **rebalancing storm** làm ngập băng thông mạng nội bộ.

### Consistent Hashing và Virtual Nodes

David Karger giải quyết vấn đề này bằng **consistent hashing**. Không gian định danh được cuộn thành một vòng tròn (ví dụ từ $0$ đến $2^{160}-1$):

- Mỗi node $Node_i$ được băm để có một tọa độ trên vòng.
- Khóa dữ liệu $k$ cũng được băm, rồi di chuyển theo chiều kim đồng hồ để "đáp" xuống node đầu tiên gặp được.

Khi một node sập, chỉ dữ liệu thuộc riêng node đó cần di chuyển sang node kế tiếp — khối lượng di chuyển giảm về tỷ lệ tối ưu $\approx \frac{1}{N}$.

Vấn đề còn lại là độ lệch tải: vì vị trí băm là ngẫu nhiên, một node có thể vô tình gánh tới 50% vòng. **Virtual Nodes** giải quyết việc này bằng cách chia một node vật lý thành hàng ngàn "bản sao ảo" $V(node_i) = \{h(node_i \parallel j) \mid j \in [1, v]\}$, giúp phân phối dữ liệu đều hơn nhiều.

### Tối Ưu Hóa Ở Tầng Vi Kiến Trúc: Mảng Tuyến Tính Và SIMD

Ở tầng thực thi cấp thấp, vòng băm không nên cài đặt bằng linked list dạng vòng. Trong C++/Rust, con trỏ nhảy lung tung trên heap sẽ phá nát instruction pipeline vì tỷ lệ cache miss cao.

Cách hiệu quả hơn là "làm phẳng" vòng băm thành một **mảng tuyến tính đã sắp xếp**:

- Kích thước mỗi phần tử được canh chỉnh khớp với độ dài L1 cache line (64 byte).
- Mỗi phần tử chiếm 16 byte (8 byte hash, 8 byte con trỏ định danh node).
- Một cache line nạp từ RAM lên CPU vừa đủ chứa 4 virtual node.

Tìm kiếm nhị phân trên mảng liền kề này đạt độ phức tạp $O(\log(N \times v))$. Kết hợp với các hàm băm nhanh như **MurmurHash3** hay **CityHash**, cùng tập lệnh SIMD (AVX-512), việc định tuyến một luồng khóa lớn chỉ mất vài nano-giây.

```rust
// Mã nguồn Rust cấp thấp minh họa cấu trúc Vòng băm tĩnh nén chặt bộ nhớ
use std::hash::{Hash, Hasher};
use fasthash::murmur3::Murmur3Hasher_x64_128;

pub struct ConsistentHashRing {
    // Mảng vector được cấp phát liền kề (Contiguous Memory Allocation)
    ring: Vec<(u64, String)>,
    virtual_nodes: usize,
}

impl ConsistentHashRing {
    pub fn new(virtual_nodes: usize) -> Self {
        ConsistentHashRing {
            ring: Vec::with_capacity(10_000), // Ngăn chặn phân mảnh Heap
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
        // Sắp xếp tĩnh để chuẩn bị cho Binary Search cấp vi mô
        self.ring.sort_unstable_by(|a, b| a.0.cmp(&b.0));
    }

    pub fn get_node(&self, key: &str) -> Option<String> {
        let mut hasher = Murmur3Hasher_x64_128::default();
        key.hash(&mut hasher);
        let hash_val = hasher.finish();

        // Hardware Branch Predictor sẽ dự đoán siêu chính xác vòng lặp Binary Search này
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

## Range-based Sharding: Tái Cân Bằng Và Điểm Nóng I/O

Điểm yếu lớn nhất của hash sharding là nó phá vỡ tính cục bộ (locality) của dữ liệu. Một truy vấn kiểu `SELECT * WHERE id BETWEEN 10 AND 50` buộc phải "gõ cửa" mọi máy chủ (scatter-gather), làm hiệu năng tụt thê thảm.

**Range-based Sharding** giải quyết việc này bằng cách chia không gian khóa $\mathcal{K}$ thành các khoảng liền kề, không giao nhau $R_i = [K_{i, min}, K_{i, max})$. Truy vấn quét dải được định tuyến thẳng đến một hoặc hai shard liên tiếp, tiết kiệm đáng kể tài nguyên mạng.

### Hiệu ứng Data Hotspot

Đổi lại cho khả năng quét dải tốt, range sharding dễ gặp **data hotspot**. Nếu shard key là timestamp, gần như toàn bộ lệnh INSERT mới nhất sẽ dồn vào đúng một shard đang quản lý khoảng thời gian "hiện tại" — máy chủ đó quá tải trong khi các máy chủ quản lý dữ liệu cũ lại nhàn rỗi. Cách xử lý thường gặp là **dynamic shard splitting** ngay tại runtime.

### Cơ Chế Tách Shard Động

Khi một shard $R_0 = [K_{min}, K_{max})$ chạm ngưỡng dung lượng, nó cần tách đôi tại một điểm $K_{split}$:

- Shard mới $R_{new} = [K_{split}, K_{max})$ sinh ra trên một máy chủ mới.
- Shard cũ tự thu hẹp còn $R_{old} = [K_{min}, K_{split})$.

**Cái khó nằm ở I/O:** copy hàng trăm GB dữ liệu không được phép chặn ghi của người dùng (zero-downtime). Cách làm phổ biến kết hợp MVCC với các cơ chế copy-on-write của hệ điều hành:

- Ở tầng kernel, Linux tạo snapshot tức thời cho các tệp SSTable qua copy-on-write (ZFS/Btrfs) — không byte vật lý nào bị sao chép ngay lúc đó.
- Một tiến trình nền (background compaction) từ từ chuyển dữ liệu sang máy chủ mới.
- Song song đó, một catch-up log buffer ghi lại mọi thay đổi đang diễn ra trên phần dữ liệu $R_{new}$ ở máy chủ cũ.
- Khi tiến trình copy hoàn tất, router chuyển lưu lượng trong khoảng $\sim 1\mu s$, áp catch-up log vào máy chủ mới, và xác nhận việc tách shard thành công qua Paxos/Raft.

```cpp
// Cấu trúc định tuyến khoảng siêu cấp bằng C++ 
struct alignas(64) RangeShard {
    std::string min_key;
    std::string max_key;
    std::string node_endpoint;

    // Kỹ thuật Branchless logic cho CPU
    inline bool contains(const std::string& key) const noexcept {
        return key >= min_key && key < max_key;
    }
};

class ZeroCopyRangeRouter {
    std::vector<RangeShard> shards;
public:
    std::string route_point_query(const std::string& key) const {
        // lower_bound được tối ưu SIMD nạp sẵn vào cache
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

## Directory-based Sharding: Điểm Trung Tâm Và Độ Trễ Cache

**Directory-based Sharding** là mô hình trừu tượng nhất trong ba mô hình. Nó bỏ hoàn toàn logic tính toán nội bộ, thay bằng một bảng ánh xạ khổng lồ: $S(k) = DirectoryLookup(k)$.

- Ưu điểm: dữ liệu có thể di chuyển tự do bất kỳ đâu, cho bất kỳ tenant nào, không bị ràng buộc bởi công thức toán học cố định (Google Spanner, FoundationDB đều dùng cách tiếp cận này ở một mức độ nào đó).
- Nhược điểm: bảng tra cứu directory dễ trở thành bottleneck trung tâm, vì mọi truy vấn toàn cầu đều phải xin định tuyến từ node trung tâm (kiểu ZooKeeper/etcd).

### Local Edge Caching và Cơ Chế Lease

Tra cứu bảng directory qua mạng tốn hàng chục mili-giây — một mức phí không thể chấp nhận nếu phải trả cho mọi request. Giải pháp là cache bảng tra cứu ngay trên RAM của client.

Nhưng điều đó kéo theo một vấn đề kiểu CAP: làm sao đảm bảo cache của client vẫn khớp với directory center khi một shard bị di dời? Nếu client dùng địa chỉ cũ để ghi, dữ liệu có thể bị ghi sai chỗ.

**Giải pháp dựa trên Lease:**

1. Directory Server cấp bảng định tuyến cho client kèm một **lease token** có hiệu lực trong một khoảng thời gian $\Delta t$ (ví dụ 5000ms).
2. Client giao tiếp trực tiếp với các shard server, tin tưởng bảng định tuyến này là đúng, miễn là $T_{current} + \epsilon < T_{issued} + \Delta t$ ($\epsilon$ là dung sai clock skew qua NTP).
3. Điểm mấu chốt nằm ở shard server: bản thân nó cũng nhận một lease từ directory center. Nếu lease của storage node hết hạn (do mất kết nối với trung tâm), storage node sẽ tự chặn ghi — đóng I/O, từ chối mọi request từ client, trả về lỗi `409 Conflict`.
4. Cơ chế fencing này ở tầng storage chính là lớp bảo vệ cuối cùng chống lại các client bị mất đồng bộ thời gian (tương tự zombie client trong split-brain). Nó giữ cho tính toàn vẹn ACID không bị phá vỡ bởi các ghi sai địa chỉ.

---

## Tổng Kết

Không có thuật toán sharding nào là viên đạn bạc.

- Hash-based tối ưu CPU và RAM qua cấu trúc vòng băm tĩnh, chống mất cân bằng tải tốt nhưng đánh đổi khả năng range scan.
- Range-based giữ được tính liên tục của dữ liệu, mở đường cho các truy vấn phân tích quét dải, nhưng đặt gánh nặng lên hệ thống I/O mỗi khi phải tách shard.
- Directory-based cho phép di chuyển dữ liệu linh hoạt nhất, đổi lại một hệ thống phức tạp hơn giữa client cache, directory lease và storage fencing.

Muốn chọn đúng thuật toán sharding cho một hệ thống cụ thể, cần hiểu rõ những đánh đổi này đến tận từng cache line và thanh ghi phần cứng — không chỉ ở tầng khái niệm.
