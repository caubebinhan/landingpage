---
seo_title: "2PL vs OCC: So Sánh Two-Phase Locking Và Optimistic Concurrency"
seo_description: "So sánh 2PL vs OCC ở cấp độ vi kiến trúc: cache coherence, lock manager, contention theo Zipfian, và lý do các thiết kế MVCC hybrid chiếm ưu thế."
focus_keyword: "2PL vs OCC"
---

# Two-Phase Locking (2PL) vs. Optimistic Concurrency Control (OCC): Phân Tích Vi Kiến Trúc Và Thuật Toán

Bất kỳ cơ sở dữ liệu nào cho phép nhiều transaction chạy đồng thời cũng phải trả lời một câu hỏi giống nhau: điều gì xảy ra khi hai transaction cùng chạm vào một dòng dữ liệu? Cuộc tranh luận 2PL vs OCC chính là điểm khởi đầu cho phần lớn cách tư duy của ngành về vấn đề này, và đến giờ nó vẫn là lăng kính đúng đắn để suy luận về hành vi của một database engine dưới áp lực contention. Bài viết này đi xa hơn các định nghĩa sách giáo khoa, xem xét 2PL và OCC thực sự làm gì với CPU — cache line, memory barrier, bộ lập lịch của hệ điều hành — và tại sao điều đó quan trọng hơn nhiều so với những gì pseudocode của hai thuật toán này thể hiện.

## Vấn Đề Cốt Lõi

Serializability là sự đảm bảo rằng việc thực thi đồng thời tạo ra kết quả tương đương với một thứ tự tuần tự nào đó của cùng các transaction đó. Đây là tính chất mà mọi cơ chế concurrency control đều cố gắng cung cấp, và về cơ bản có hai cách để đạt được nó.

Nếu giả định conflict xảy ra thường xuyên, bạn dùng **Pessimistic Locking (2PL)**. Lock giúp việc suy luận về tính đúng đắn trở nên đơn giản, nhưng đồng thời cũng buộc các thread phải chờ đợi — và chờ đợi nghĩa là CPU bị lãng phí, chi phí context-switch tăng lên, và deadlock cần được phát hiện hoặc ngăn chặn.

Nếu giả định conflict hiếm khi xảy ra, bạn dùng **Optimistic Concurrency Control (OCC)**. Phần lớn thời gian cách này hoạt động tốt — không lock, không chờ đợi, transaction cứ thế chạy. Nhưng nếu conflict lại xảy ra thường xuyên (một đợt flash sale, một sản phẩm viral cháy hàng), các transaction của OCC sẽ liên tục validate, thất bại, rồi retry hết lần này đến lần khác. CPU utilization leo lên gần 100% trong khi throughput thực tế lại tiến về 0. Hiện tượng này có tên riêng: optimistic thrashing.

Vậy vấn đề kỹ thuật thực sự không phải là "thuật toán nào tốt hơn" — mà là thiết kế một cơ chế đồng bộ hóa giữ số lượng atomic instruction ở mức thấp, tránh làm bão hòa memory bus, và không sụp đổ khi độ lệch (skew) của workload thay đổi bất ngờ.

## Kiến Thức Chuyên Sâu

### Nền Tảng Lý Thuyết Của Transactional Concurrency Control

Serializability dựa trên một conflict graph $G = (V, E)$, trong đó đỉnh $V$ là các transaction đã commit và cạnh $E$ là các conflict — read-write, write-read, hoặc write-write. Một schedule là conflict-serializable khi và chỉ khi $G$ không có chu trình (acyclic).

#### Two-Phase Locking (2PL)

2PL đảm bảo tính acyclic thông qua một quy tắc đơn giản chia làm hai giai đoạn:

- **Growing Phase:** transaction có thể nhận lock nhưng không được nhả bất kỳ lock nào.
- **Shrinking Phase:** transaction có thể nhả lock nhưng không được nhận thêm lock mới.

2PL thuần vẫn có thể xảy ra cascading abort — $T_j$ đọc dữ liệu chưa commit từ $T_i$, rồi $T_i$ abort, và giờ $T_j$ cũng phải abort theo. **Strict Two-Phase Locking (S2PL)** bịt lỗ hổng này bằng cách giữ tất cả exclusive lock cho đến khi commit hoặc abort. Cái giá phải trả là 2PL cần cơ chế xử lý deadlock: hoặc phát hiện (thuật toán thành phần liên thông mạnh của Tarjan, $\mathcal{O}(V+E)$), hoặc phòng ngừa bằng các sơ đồ như Wait-Die và Wound-Wait.

#### Optimistic Concurrency Control (OCC)

OCC chia một transaction thành ba giai đoạn:

1. **Read Phase:** các thao tác chạy trên một bản sao local của dữ liệu. Read set $RS(T_i)$ và write set $WS(T_i)$ được ghi lại trong quá trình đó.
2. **Validation Phase:** hệ thống kiểm tra xem tập hợp của $T_i$ có giao nhau với tập hợp của các transaction đã commit đồng thời hay không.
3. **Write Phase:** nếu validation thành công, các thay đổi local được flush ra global state.

Nếu validation thất bại, workspace bị hủy bỏ và transaction phải restart. Xác suất validation thất bại, $P_{abort}$, tăng theo cấp số nhân khi contention tăng lên — đây chính là con số quyết định OCC có phù hợp với một workload cụ thể hay không.

### Vi Kiến Trúc Và Đồng Bộ Hóa Cấp Độ Phần Cứng

Khoảng cách giữa 2PL và OCC không chỉ nằm ở thuật toán — nó thể hiện trực tiếp trong cách mỗi bên tương tác với **CPU cache coherence protocol (MOESI)** qua kết nối QPI/UPI.

#### Cái Giá Của 2PL Trên Phần Cứng

Lock manager thực chất là một hash table lớn chứa các mutex hoặc spinlock. Việc acquire một lock đòi hỏi thực thi một atomic instruction — `LOCK CMPXCHG` trên x86_64 — và những lệnh này tốn kém: chúng bỏ qua store buffer, hoạt động như một full memory barrier, và buộc lõi CPU phải từ bỏ out-of-order execution xung quanh chúng.

Còn một cái giá khác dễ bị bỏ qua: nếu hai lock không liên quan tình cờ nằm trên cùng một cache line 64-byte, bạn sẽ gặp **False Sharing**. MESI sẽ invalidate cache line đó trên mọi core khác ngay khi một thread chạm vào lock của nó, tạo ra lưu lượng memory bus chẳng liên quan gì đến contention thực sự trên dữ liệu. Các lock manager nghiêm túc thường đệm (pad) cấu trúc của mình bằng `alignas(64)` chính là để tránh vấn đề này trên phần cứng NUMA.

$$
T_{throughput\_2PL} = \frac{N_{cores}}{T_{exec} + N_{locks} \times \left(T_{atomic} + P_{contention} \times T_{wait}\right) + T_{deadlock\_detection}}
$$

#### Cái Giá Của OCC Trên Phần Cứng

OCC tránh hoàn toàn atomic operation trong suốt read phase — transaction thay đổi thread-local storage (TLS), thứ không bao giờ chạm đến lưu lượng cache coherence.

Cái giá xuất hiện sau đó, ở **Validation Phase**, đây chính là điểm nghẽn kiểu Amdahl của OCC. Validation thường đòi hỏi phải bước vào một critical section, thường được bảo vệ bởi một global seqlock. Còn có một cái giá âm thầm hơn: việc cấp phát rồi hủy bỏ các workspace tạm thời với kích thước lớn gây áp lực thực sự lên memory allocator của hệ điều hành (`jemalloc` và tương tự). Đẩy tốc độ cấp phát lên đủ cao sẽ làm bão hòa hệ thống virtual memory của kernel, kích hoạt TLB shootdown trên khắp các core.

$$
T_{throughput\_OCC} = \frac{N_{cores} \times (1 - P_{abort})}{T_{read\_phase} + T_{validation\_phase} + T_{write\_phase} + P_{abort} \times T_{retry\_penalty}}
$$

### Hành Vi Thuật Toán Dưới Zipfian Workload

Những so sánh thú vị nhất xuất hiện dưới các mẫu truy cập dữ liệu bị lệch — kiểu được mô hình hóa bằng phân phối Zipfian với $\alpha > 0.9$, nơi một số ít key hấp thụ phần lớn lưu lượng.

Dưới **OCC**, một workload bị lệch mạnh đẩy $P_{abort}$ tăng vọt. Mỗi transaction bị abort sẽ restart ngay lập tức, làm phồng lên tốc độ đến (arrival rate) $\lambda$ vượt xa những gì workload thực sự tạo ra. Qua một ngưỡng nhất định — khi tỷ lệ retry vượt qua tỷ lệ commit — hệ thống rơi vào **optimistic thrashing**: CPU giữ ở mức 100%, trong khi throughput hữu ích tiến về 0. Bản thân việc validation cũng không hề miễn phí; đối chiếu read set và write set một cách ngây thơ tốn $\mathcal{O}(K \times R_{size} \times W_{size})$, trừ khi được tối ưu bằng Bloom filter hoặc lock-free hash set.

```rust
// Simplified Rust OCC Validation Logic
pub fn validate_and_commit(&self, mut txn: Transaction) -> Result<(), &'static str> {
    let commit_timestamp = self.global_timestamp.fetch_add(1, Ordering::SeqCst);
    let history = self.committed_transactions.read().unwrap();
    
    // Critical Validation Phase: Check for overlapping read/write sets
    for past_txn in history.iter() {
        if past_txn.start_timestamp > txn.start_timestamp {
            // Validation fails if past transaction modified memory we read
            if !txn.read_set.is_disjoint(&past_txn.write_set) {
                return Err("Validation Failed: Read-Write Conflict");
            }
        }
    }
    // Proceed to Write Phase...
    Ok(())
}
```

**2PL** xử lý cùng loại skew đó theo một cách khác. Chiều dài lock queue $L$ tăng lên, nên độ trễ tăng theo, nhưng hệ thống không xoáy vào vòng lặp sụp đổ. Locking tự nó là một cơ chế điều tiết tự nhiên: bộ lập lịch của hệ điều hành đưa các thread bị block vào trạng thái sleep thay vì đốt CPU cycle vào việc retry, nên vòng lặp phản hồi thrashing vốn gây hại cho OCC không bao giờ được kích hoạt. Throughput đi vào bình nguyên (plateau) dưới skew nặng thay vì sụp đổ.

```cpp
// Advanced C++ 2PL Lock Manager snippet handling wait queues
bool acquire_lock(uint64_t txn_id, uint64_t data_id, LockMode mode) {
    // Hash table lookup...
    std::unique_lock<std::mutex> lock(state->bucket_mutex);
    
    if (mode == LockMode::EXCLUSIVE && state->shared_count == 0 && !state->exclusive_held) {
        state->exclusive_held = true;
        return true;
    } else {
        // Conflict: Append to wait queue, OS suspends thread (conserving CPU)
        state->wait_queue.push_back({txn_id, mode, false});
        state->cv.wait(lock, [&]{ return check_grant_condition(state, txn_id, mode); });
        return true; 
    }
}
```

### Góc Nhìn Về Hardware Transactional Memory (HTM)

Một số bộ vi xử lý — Intel TSX là ví dụ quen thuộc nhất — đẩy ý tưởng của OCC xuống tận silicon. **Hardware Transactional Memory (HTM)** dùng L1 cache như một speculative buffer, theo dõi read/write set thông qua các bit metadata của cache line. Nếu một conflict bị snoop trên bus, phần cứng abort transaction gần như tức thì — rẻ hơn nhiều so với một lượt validation bằng phần mềm. Vấn đề nằm ở dung lượng: L1 rất nhỏ, và nếu working set của một transaction không vừa, bạn sẽ gặp capacity abort và phải rơi về đường phần mềm, thường là 2PL thuần túy.

## Bài Học Rút Ra Và Thực Hành Tốt

1. **Khớp giao thức với hồ sơ contention của bạn.** OCC thuần chạy trên workload có contention nặng — hệ thống đặt vé, giảm tồn kho của một mặt hàng đang hot — sẽ thrash. OCC làm tốt trên các phân tích read-heavy và trên các workload được phân vùng tự nhiên để conflict luôn hiếm.
2. **Đệm (pad) các cấu trúc lock của bạn.** Một 2PL lock manager thiếu `alignas(64)` (hoặc 128, tùy nền tảng) trên các lock bucket sẽ gặp false sharing, và hiệu ứng này không hề nhẹ — một máy 64-core có thể chạy chậm hơn cả laptop hai nhân khi bị contention nặng.
3. **Đừng coi validation phase của OCC là miễn phí.** Đó là một critical section, và nếu bạn chưa tối ưu phép giao tập hợp (Bloom filter giúp ích ở đây) và kết hợp với epoch-based memory reclamation, nó sẽ trở thành chính điểm nghẽn mà thiết kế của bạn đang cố tránh.
4. **Hệ thống production phần lớn đi theo hướng hybrid.** Rất ít engine chạy thuần 2PL hoặc thuần OCC từ đầu đến cuối. Mẫu hình phổ biến là MVCC cho các thao tác đọc kết hợp với Strict 2PL cho ghi, hoặc một sơ đồ thích ứng chuyển đổi giao thức dựa trên độ sâu hàng đợi hoặc tín hiệu contention quan sát được.

## Kết Luận

Câu hỏi 2PL vs OCC không phải là chuyện học thuật — đó là một quyết định định hình cách một database engine hoạt động dưới các ràng buộc phần cứng thực tế. 2PL đánh đổi một phần hiệu quả CPU để lấy hành vi dự đoán được dưới contention; OCC đánh đổi chi phí quản lý bộ nhớ và độ phức tạp của validation để lấy throughput cao hơn khi contention thấp. Hiểu cả hai đến tận cấp độ cache-coherence và lập lịch chính là điều phân biệt một mô hình tư duy thực sự hoạt động với một mô hình sụp đổ ngay khi traffic production trở nên lệch.
