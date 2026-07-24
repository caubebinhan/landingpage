---
seo_title: "Cơ chế Thực thi SQL Window Functions: Từ Sort đến SIMD"
seo_description: "Giải thích cách SQL Window Functions thực sự chạy bên dưới: partitioning, sorting, Monotonic Deque, Segment Tree, SIMD và NUMA — vì sao truy vấn trung bình động lại chậm."
focus_keyword: "SQL Window Functions"
---

# Cơ chế Thực thi Vi kiến trúc đằng sau SQL Window Functions

## Tóm tắt điều hành

SQL Window Functions đã thay đổi hẳn cách các kỹ sư dữ liệu và nhà phân tích xử lý các phép tính phân tích phức tạp. Khác với hàm tập hợp (Aggregate Functions) vốn gộp nhiều dòng thành một kết quả duy nhất, Window Functions giữ nguyên từng bản ghi đầu vào, đồng thời vẫn tính được giá trị liên quan đến "khung dữ liệu" (Window Frame) xung quanh nó.

Nhưng đằng sau cú pháp SQL gọn gàng như `ROW_NUMBER() OVER(PARTITION BY... ORDER BY...)` là một trong những cỗ máy thực thi phức tạp và tốn kém bậc nhất trong các hệ quản trị cơ sở dữ liệu (RDBMS) lẫn hệ thống phân tán như Spark hay Trino.

Bài viết này mổ xẻ cơ chế thực thi của SQL Window Functions, từ những khái niệm nền tảng như partitioning và sorting, đến các cấu trúc dữ liệu như Monotonic Deque hay Segment Tree, cho tới tận tầng vi kiến trúc phần cứng: SIMD, cache line, NUMA, JIT compilation. Đọc xong, bạn sẽ hiểu rõ Window Functions vận hành ra sao, biết cách nhận diện điểm nghẽn hiệu năng trong hệ thống lớn, và rút ra được vài nguyên tắc thiết kế đáng áp dụng.

---

## Vấn đề Cốt lõi của Window Functions

**Vấn đề là gì?**
Hãy hình dung một câu truy vấn tính trung bình động 7 ngày cho hàng triệu khách hàng:

`AVG(sales) OVER(PARTITION BY customer_id ORDER BY date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW)`

Để trả kết quả cho một dòng, hệ thống không chỉ đọc mỗi dòng đó — nó phải:
1. Gom đúng hàng triệu khách hàng vào các phân vùng (partitioning).
2. Sắp xếp dữ liệu theo ngày trong từng khách hàng (sorting).
3. Duy trì một khung cửa sổ trượt gồm 7 dòng, vứt bỏ dòng cũ nhất và thêm dòng mới nhất mỗi khi cửa sổ tịnh tiến.

Khác hẳn một phép quét tuyến tính thông thường, việc này đòi hỏi lưu trạng thái cục bộ khá tinh vi. Nếu dữ liệu lớn hơn RAM, hệ thống hoặc sập, hoặc phải liên tục hoán đổi trang đĩa (paging/swapping), khiến độ trễ truy vấn nhảy từ vài giây lên vài giờ. Cái khó của một RDBMS hiện đại là giải quyết bài toán này mà vẫn giữ được thông lượng CPU ở mức cao, tận dụng tối đa cache L1/L2/L3.

---

## Giai đoạn Một: Phân mảnh và Định tuyến Dữ liệu

Bước đầu tiên là chia dữ liệu thành các phân vùng logic dựa trên `PARTITION BY`.

### Sức mạnh của Hashing kết hợp SIMD
Việc gom nhóm không dùng phép so sánh chuỗi đơn giản, mà dựa vào các thuật toán băm tốc độ cao như MurmurHash3 hay xxHash, chạy trên tập lệnh **SIMD (AVX-512)**. Bằng cách nạp cùng lúc 8 đến 16 khóa băm vào thanh ghi vector, CPU tính được việc phân vùng cho 16 bản ghi chỉ trong một chu kỳ xung nhịp.

$$h(x) = \text{xxHash}(x_k) \pmod P$$

### Radix Buffer và hiện tượng Cache Thrashing
Để tránh cache miss, luồng dữ liệu được định tuyến qua **Radix Buffer** — kích thước được căn chỉnh cẩn thận để vừa khít với L2/L3 cache (thường từ 256KB đến vài MB). Nếu đổ thẳng dữ liệu vào hàng nghìn phân vùng cùng lúc, CPU sẽ phải liên tục thay cache line, kéo băng thông bộ nhớ xuống thấp thảm hại.

### External Partitioning và mmap của hệ điều hành
Khi phân vùng phình to hơn RAM (out-of-core), RDBMS chuyển sang External Partitioning: ánh xạ các file tạm lên đĩa qua `mmap()` kèm cờ `madvise(MADV_SEQUENTIAL)`. Chỉ thị cấp kernel này báo cho hệ điều hành bật chế độ đọc trước tích cực, liên tục đẩy dữ liệu từ SSD vào RAM trước cả khi thuật toán Window cần đến, gần như loại bỏ độ trễ I/O.

---

## Giai đoạn Hai: Sắp xếp Dữ liệu

Sau khi phân vùng xong, hệ thống phải đối mặt với bài toán tốn kém: `ORDER BY` nội bộ. Về mặt lý thuyết, độ phức tạp không thể thấp hơn $\Omega(N \log N)$.

### IntroSort và cây đấu (Loser Tree)
Khi dữ liệu vừa RAM, **IntroSort** (lai giữa Quicksort và Heapsort) chiếm ưu thế. Khi RAM không đủ, **External Merge Sort** phải vào cuộc: dữ liệu bị chia nhỏ, sắp xếp sơ bộ, rồi ghi xuống NVMe thành các đoạn đã sắp (sorted runs).

Ở giai đoạn trộn (merge), hệ thống dùng **Loser Tree (Tournament Tree)** — cấu trúc này rất hợp với vi kiến trúc bộ nhớ vì mỗi khi thêm một phần tử mới, chỉ cần $\log_2(K)$ phép so sánh, và các thao tác cập nhật nút cây diễn ra gần như trên cùng một cache line vật lý.

---

## Giai đoạn Ba: Máy Trạng thái Đánh giá Khung Cửa sổ

Đây là phần thú vị nhất. Khung cửa sổ của bản ghi $i$ được biểu diễn:

$$ W_i = \{ r_j \in P \mid \max(1, i - L) \le j \le \min(N, i + U) \} $$

### Các hàm phân tích đơn giản (ROW_NUMBER, RANK)
Với nhóm hàm này, hệ thống chỉ cần giữ một thanh ghi theo dõi chỉ mục hiện tại. Độ phức tạp $\mathcal{O}(N)$, gần như không tốn gì thêm.

### Bài toán cửa sổ trượt (Sliding Window Maximum/Minimum)
Tính `MAX()` hay `MIN()` trên một khung trượt — chẳng hạn 30 ngày gần nhất — không đơn giản như trông có vẻ. Cách tính ngây thơ cho độ phức tạp $\mathcal{O}(N \times W)$ ($W$ là kích thước cửa sổ); với $W = 10.000$, truy vấn gần như đứng hình.

Giải pháp ở đây là **hàng đợi đơn điệu (Monotonic Deque)**: nó chỉ giữ lại các ứng viên có khả năng trở thành MAX, và loại ngay những giá trị quá nhỏ, không bao giờ có cơ hội. Nhờ vậy chi phí giảm về đúng $\mathcal{O}(N)$ — tuyến tính thuần túy.

```rust
// Mô phỏng thuật toán Monotonic Deque cho Window Maximum
pub fn evaluate_max(&self) -> Vec<T> {
    let mut deque: VecDeque<usize> = VecDeque::new();
    for i in 0..n {
        // 1. Vứt bỏ dòng bị trượt khỏi cửa sổ
        if let Some(&front_idx) = deque.front() {
            if i >= self.window_size && front_idx <= i - self.window_size { deque.pop_front(); }
        }
        // 2. Vứt bỏ ứng viên kém cỏi
        while let Some(&back_idx) = deque.back() {
            if self.data[back_idx] <= self.data[i] { deque.pop_back(); } 
            else { break; }
        }
        deque.push_back(i); // 3. Ghi danh ứng viên mới
    }
}
```

### Xử lý sai số tích lũy (Catastrophic Cancellation)
Khi tính `SUM()` trên dữ liệu dấu phẩy động, việc cộng một số lớn rồi trừ đi một số nhỏ liên tục — điều xảy ra thường xuyên với cửa sổ trượt — làm mất dần độ chính xác nhị phân. Các RDBMS kỹ lưỡng dùng **thuật toán Kahan Summation** để giữ một bộ đệm sai số bù, giúp độ lệch tích lũy luôn sát gần 0.

### Segment Tree cho các hàm khó
Với `COUNT(DISTINCT)` hoặc khung cửa sổ dựa trên `RANGE` không đều, Deque bó tay. Lúc này database dựng ngay một **Segment Tree** hoặc **Fenwick Tree** trong RAM. Chi phí tăng lên $\mathcal{O}(N \log N)$, nhưng đổi lại xử lý được các truy vấn phức tạp hơn nhiều.

---

## Ràng buộc ở Tầng Phân tán và Hệ điều hành

### Prefix Sum cho hệ thống MPP
Trong các data warehouse quy mô lớn như Snowflake hay BigQuery, việc tính Window Function không thể dồn hết vào một node. Nếu một phân vùng khách hàng có quá nhiều dữ liệu (data skew), node đó dễ dàng hết bộ nhớ (OOM).

Cách giải quyết là dùng thuật toán **Prefix Sum (Scan)**: dữ liệu của một khách hàng được chia thành nhiều đoạn, giao cho nhiều node. Node A tính tổng 10 ngày đầu, Node B tính tổng 10 ngày sau, rồi chúng broadcast phần "dư" cho nhau. Nhờ tính kết hợp (associativity) của phép toán, độ trễ phân tán giảm xuống còn khoảng $\mathcal{O}(N / P + \log P)$.

### Huge Pages và Direct I/O
Để chứa các cấu trúc hàng đợi và cây khổng lồ, database không thể dùng trang RAM 4KB thông thường — việc trượt bảng trang ảo (TLB miss) sẽ ảnh hưởng nặng đến CPU. Vì vậy hầu như bắt buộc phải cấu hình **Transparent Huge Pages (2MB/1GB)**.

Thêm nữa, việc quét dữ liệu cho Window Function về bản chất là "đọc một lần rồi bỏ". Nếu dùng page cache của hệ điều hành, nó sẽ đẩy văng những dữ liệu hữu ích khác ra khỏi RAM. Vì thế database dùng cờ `O_DIRECT` kết hợp `io_uring` (I/O bất đồng bộ của Linux) để chuyển dữ liệu thẳng từ bộ điều khiển SSD NVMe vào vùng nhớ nội bộ, bỏ qua hoàn toàn kernel.

### Kiến trúc NUMA và hiện tượng False Sharing
Trên máy chủ hai CPU, RAM bị chia thành hai NUMA node (0 và 1). RDBMS phải dùng **CPU pinning** để đảm bảo thread tính toán trên CPU 0 chỉ đọc RAM gắn với CPU 0. Ngoài ra, cần padding các đối tượng dữ liệu trong C++ (`alignas(64)`) để tránh hai thread ghi đè lên cùng một cache line vật lý 64-byte — hiện tượng false sharing có thể kéo băng thông bộ nhớ xuống thấp bất ngờ.

---

## Bài học Rút ra

Qua việc mổ xẻ kiến trúc phức tạp của Window Function, kỹ sư hệ thống và DBA có thể rút ra vài bài học đáng nhớ:

1. **Đừng xem nhẹ chi phí sắp xếp:** Nếu chỉ cần gom nhóm, đừng lạm dụng `ORDER BY` bên trong Window Function. Bất kỳ `ORDER BY` nào cũng kích hoạt cả một cỗ máy sorting với độ phức tạp $\mathcal{O}(N \log N)$, kèm nguy cơ phải ghi xuống SSD.
2. **Kích thước khung cửa sổ quyết định thuật toán:** `ROWS BETWEEN` với giới hạn nhỏ luôn nhanh hơn `RANGE BETWEEN` — cái sau buộc hệ thống xử lý logic biên phức tạp hơn, thậm chí phải dựng Segment Tree.
3. **Cẩn thận với dữ liệu lệch (data skew):** Cách bạn chọn `PARTITION BY` quyết định cách dữ liệu được phân tán. Partition theo `quốc_gia` mà 99% khách hàng ở Việt Nam thì gần như toàn bộ dữ liệu dồn vào một luồng CPU (hay một node), khiến tính song song của cụm trở nên vô nghĩa. Nên chọn khóa phân vùng có độ phân tán đều.
4. **Giới hạn RAM và cấu hình hệ thống:** Cần thiết lập hợp lý các tham số bộ nhớ dành cho sort/hash (như `work_mem` trong PostgreSQL). Cấp quá ít sẽ buộc hệ thống chạy External Merge Sort chậm trên đĩa; cấp quá nhiều thì OOM Killer của Linux có thể kết liễu tiến trình database.

## Kết luận

Cơ chế thực thi của Window Function là sự kết hợp giữa cấu trúc đồ thị tổ hợp, các thuật toán tối ưu tinh vi, và những kỹ thuật tinh chỉnh sát phần cứng. Hiểu được cơ chế này không chỉ giúp viết SQL thông minh hơn, mà còn là nền tảng để các kiến trúc sư dữ liệu thiết kế những hệ thống phân tích dữ liệu lớn vượt ra ngoài giới hạn của các công cụ phổ thông.
