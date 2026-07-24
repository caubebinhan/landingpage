---
seo_title: "Window Function trong SQL chạy như thế nào bên dưới?"
seo_description: "Phân tích cơ chế thực thi Window Function trong SQL: partitioning, sorting, Monotonic Deque, Segment Tree, SIMD, NUMA — và vì sao một số truy vấn window lại chậm bất thường."
focus_keyword: "Window Function trong SQL"
---

# Cơ chế Thực thi Vi kiến trúc đằng sau SQL Window Functions

## Tóm tắt điều hành

Window Function trong SQL đã đổi hẳn cách các kỹ sư dữ liệu viết các phép tính phân tích phức tạp. Khác với hàm tập hợp thông thường vốn gộp nhiều dòng lại thành một kết quả, Window Function giữ nguyên từng dòng dữ liệu gốc trong khi vẫn tính được giá trị phụ thuộc vào một "khung" (window frame) các dòng xung quanh nó.

Cú pháp thì gọn — `ROW_NUMBER() OVER(PARTITION BY... ORDER BY...)` — nhưng phía sau là một trong những cỗ máy thực thi phức tạp nhất trong các hệ quản trị cơ sở dữ liệu quan hệ (RDBMS) cũng như trong các hệ thống xử lý phân tán như Spark hay Trino.

Bài viết này bóc tách cơ chế đó theo từng lớp: từ các bước nền tảng như phân vùng (partitioning) và sắp xếp (sorting), qua các cấu trúc dữ liệu như Monotonic Deque hay Segment Tree, cho đến những chi tiết ở tầng phần cứng — SIMD, cache line, NUMA, JIT compilation. Sau khi đọc xong, bạn sẽ nắm được Window Function vận hành ra sao trong thực tế, biết soi ra điểm nghẽn hiệu năng khi hệ thống scale lớn, và có thêm vài nguyên tắc để thiết kế truy vấn tốt hơn.

---

## Vấn đề Cốt lõi

**Vấn đề nằm ở đâu?**
Thử hình dung một truy vấn tính trung bình động 7 ngày cho hàng triệu khách hàng:

`AVG(sales) OVER(PARTITION BY customer_id ORDER BY date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW)`

Để trả về kết quả cho đúng một dòng, hệ thống không chỉ đọc dòng đó — nó còn phải:
1. Gom hàng triệu khách hàng vào đúng phân vùng của họ (partitioning).
2. Sắp xếp dữ liệu theo ngày trong nội bộ từng khách hàng (sorting).
3. Giữ một khung cửa sổ trượt 7 dòng, loại dòng cũ nhất và thêm dòng mới nhất mỗi khi khung dịch chuyển.

So với một phép quét tuyến tính đơn thuần, việc này đòi hỏi quản lý trạng thái cục bộ khá phức tạp. Khi dữ liệu vượt quá dung lượng RAM, hệ thống hoặc là sập, hoặc phải liên tục hoán trang (paging/swapping), kéo độ trễ truy vấn từ vài giây lên tới hàng giờ. Thách thức với một RDBMS hiện đại là xử lý được bài toán này mà vẫn giữ thông lượng CPU cao, tận dụng hết cache L1/L2/L3 thay vì để chúng bị bỏ phí.

---

## Bước Một: Phân vùng và Định tuyến Dữ liệu

Chia dữ liệu thành các phân vùng logic theo `PARTITION BY` là bước khởi đầu.

### Hashing kết hợp SIMD
Việc gom nhóm không dựa vào so sánh chuỗi trực tiếp mà dùng các thuật toán băm nhanh như MurmurHash3 hay xxHash, chạy trên tập lệnh **SIMD (AVX-512)**. Nạp cùng lúc 8 đến 16 khóa băm vào thanh ghi vector cho phép CPU tính phân vùng cho 16 bản ghi chỉ trong một chu kỳ xung nhịp duy nhất.

$$h(x) = \text{xxHash}(x_k) \pmod P$$

### Radix Buffer và tình trạng Cache Thrashing
Luồng dữ liệu được định tuyến qua **Radix Buffer** để tránh cache miss — kích thước buffer được tính toán sao cho vừa với L2/L3 cache (thường 256KB đến vài MB). Nếu đổ dữ liệu thẳng vào hàng nghìn phân vùng cùng lúc, CPU buộc phải liên tục hoán đổi cache line, khiến băng thông bộ nhớ giảm mạnh.

### External Partitioning và mmap
Khi một phân vùng phình to hơn RAM (out-of-core), RDBMS chuyển sang External Partitioning, ánh xạ file tạm ra đĩa qua `mmap()` cùng cờ `madvise(MADV_SEQUENTIAL)`. Chỉ thị này báo cho hệ điều hành bật chế độ đọc trước tích cực, đẩy dữ liệu từ SSD lên RAM trước khi thuật toán window thực sự cần đến, giúp giảm gần hết độ trễ I/O.

---

## Bước Hai: Sắp xếp Dữ liệu

Sau khi phân vùng xong, hệ thống phải xử lý phần tốn kém nhất: `ORDER BY` nội bộ. Về lý thuyết, độ phức tạp không thể thấp hơn $\Omega(N \log N)$.

### IntroSort và Loser Tree
Khi dữ liệu vừa RAM, **IntroSort** (kết hợp Quicksort và Heapsort) là lựa chọn mặc định. Khi RAM không đủ, **External Merge Sort** vào cuộc: dữ liệu bị chia nhỏ, sắp sơ bộ, rồi ghi xuống NVMe dưới dạng các đoạn đã sắp xếp (sorted runs).

Ở bước trộn, hệ thống dùng **Loser Tree (Tournament Tree)** — cấu trúc phù hợp với vi kiến trúc bộ nhớ vì mỗi lần thêm phần tử mới chỉ tốn $\log_2(K)$ phép so sánh, và việc cập nhật nút cây gần như luôn nằm gọn trong cùng một cache line.

---

## Bước Ba: Máy Trạng thái Đánh giá Khung Cửa sổ

Đây là phần cốt lõi. Khung cửa sổ của bản ghi $i$ được biểu diễn bằng công thức:

$$ W_i = \{ r_j \in P \mid \max(1, i - L) \le j \le \min(N, i + U) \} $$

### Các hàm phân tích đơn giản (ROW_NUMBER, RANK)
Với nhóm hàm này, hệ thống chỉ cần một thanh ghi theo dõi chỉ số hiện tại — độ phức tạp $\mathcal{O}(N)$, gần như miễn phí.

### Bài toán Sliding Window Maximum/Minimum
Tính `MAX()` hay `MIN()` trên một cửa sổ trượt — ví dụ 30 ngày gần nhất — không đơn giản như vẻ ngoài của nó. Làm ngây thơ sẽ cho độ phức tạp $\mathcal{O}(N \times W)$ ($W$ là kích thước cửa sổ); với $W = 10.000$, truy vấn gần như đứng yên tại chỗ.

Cách xử lý hiệu quả là dùng **hàng đợi đơn điệu (Monotonic Deque)**: chỉ giữ lại các ứng viên có khả năng trở thành giá trị MAX, loại ngay những phần tử quá nhỏ không bao giờ có cơ hội. Nhờ vậy chi phí tụt về đúng $\mathcal{O}(N)$.

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

### Sai số tích lũy khi cộng số thực
Tính `SUM()` trên dữ liệu dấu phẩy động, việc cộng số lớn rồi trừ số nhỏ liên tục — điều thường xảy ra với cửa sổ trượt — làm mòn dần độ chính xác nhị phân. Các RDBMS cẩn thận sẽ dùng **thuật toán Kahan Summation** để bù sai số, giữ độ lệch tích lũy sát ngưỡng 0.

### Segment Tree cho các trường hợp khó
Với `COUNT(DISTINCT)` hoặc khung `RANGE` không đều, Deque không giải quyết được. Lúc này database dựng ngay một **Segment Tree** hay **Fenwick Tree** trong RAM, chấp nhận chi phí tăng lên $\mathcal{O}(N \log N)$ để đổi lấy khả năng xử lý những truy vấn phức tạp hơn.

---

## Ràng buộc ở Tầng Phân tán và Hệ điều hành

### Prefix Sum cho hệ thống MPP
Với các data warehouse cỡ lớn như Snowflake hay BigQuery, việc tính Window Function không thể gói gọn trong một node. Nếu dữ liệu của một phân vùng khách hàng bị lệch quá nhiều (data skew), node xử lý nó dễ hết bộ nhớ.

Giải pháp là thuật toán **Prefix Sum (Scan)**: chia dữ liệu của một khách hàng thành nhiều đoạn, giao cho nhiều node xử lý song song. Node A tính tổng 10 ngày đầu, Node B tính 10 ngày tiếp theo, rồi chúng trao đổi phần "dư" cho nhau. Nhờ tính kết hợp của phép cộng, độ trễ xử lý phân tán giảm còn khoảng $\mathcal{O}(N / P + \log P)$.

### Huge Pages và Direct I/O
Chứa các cấu trúc hàng đợi và cây lớn đòi hỏi nhiều hơn trang RAM 4KB tiêu chuẩn — trượt bảng trang ảo (TLB miss) sẽ ảnh hưởng đáng kể đến hiệu năng CPU. Vì vậy cấu hình **Transparent Huge Pages (2MB/1GB)** gần như là bắt buộc.

Thêm nữa, việc quét dữ liệu cho Window Function về bản chất là thao tác đọc một lần rồi bỏ. Dùng page cache mặc định của hệ điều hành sẽ đẩy văng dữ liệu hữu ích khác khỏi RAM, nên database thường dùng `O_DIRECT` kết hợp `io_uring` để đưa dữ liệu thẳng từ SSD NVMe vào vùng nhớ riêng, bỏ qua kernel hoàn toàn.

### NUMA và hiện tượng False Sharing
Trên máy chủ hai CPU, RAM chia thành hai NUMA node. RDBMS dùng **CPU pinning** để đảm bảo thread chạy trên CPU 0 chỉ đọc RAM gắn với CPU 0. Ngoài ra, các đối tượng dữ liệu trong C++ cần được padding (`alignas(64)`) để tránh hai thread ghi đè lên cùng một cache line vật lý 64-byte — nếu không, hiện tượng false sharing sẽ kéo băng thông bộ nhớ xuống thấp một cách khó hiểu.

---

## Bài học Rút ra

Từ việc mổ xẻ Window Function ở tầng thực thi, có vài điều đáng ghi nhớ cho kỹ sư hệ thống và DBA:

1. **Chi phí sắp xếp không hề rẻ:** Nếu chỉ cần gom nhóm, đừng thêm `ORDER BY` một cách tùy tiện trong Window Function — nó sẽ kích hoạt cả một cỗ máy sorting với độ phức tạp $\mathcal{O}(N \log N)$ và có thể phải ghi dữ liệu xuống SSD.
2. **Kích thước khung quyết định thuật toán chạy bên dưới:** `ROWS BETWEEN` với giới hạn nhỏ luôn nhanh hơn `RANGE BETWEEN`, vốn buộc hệ thống xử lý logic biên phức tạp hơn, đôi khi phải dựng cả Segment Tree.
3. **Dữ liệu lệch là rủi ro thực sự:** Cách chọn `PARTITION BY` quyết định dữ liệu phân tán ra sao. Partition theo quốc gia mà 99% khách hàng ở Việt Nam thì gần như toàn bộ tải sẽ dồn vào một luồng CPU hay một node, khiến việc chạy song song trên cụm trở nên vô ích. Nên chọn khóa phân vùng có độ phân tán đồng đều hơn.
4. **Cấu hình RAM hợp lý:** Các tham số bộ nhớ dành riêng cho sort/hash (như `work_mem` trong PostgreSQL) cần được thiết lập cẩn thận — cấp thiếu thì truy vấn rơi vào External Merge Sort chậm chạp trên đĩa, cấp thừa thì có nguy cơ bị OOM Killer của Linux kết liễu tiến trình.

## Kết luận

Cơ chế thực thi của Window Function là sự kết hợp giữa cấu trúc đồ thị tổ hợp, các thuật toán tối ưu được viết rất kỹ, và những tinh chỉnh sát tận phần cứng. Hiểu được nó không chỉ giúp viết SQL tốt hơn, mà còn là nền tảng để thiết kế những hệ thống phân tích dữ liệu lớn vượt xa giới hạn của các công cụ thông thường.
