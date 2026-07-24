---
seo_title: "Locks, Latches, Spinlocks: Kiểm Soát Đồng Thời Trong DB"
seo_description: "Phân biệt locks, latches và spinlocks trong database engine: từ latch crabbing trên B+Tree đến giao thức MESI và hardware transactional memory."
focus_keyword: "locks, latches và spinlocks"
---

# 12: Locks, Latches, và Spinlocks: Kiểm Soát Đồng Thời Trong Database Engine

## Vì Sao Sự Phân Biệt Này Quan Trọng

Hỏi một lập trình viên ứng dụng thông thường "lock là gì", họ sẽ trả lời được. Nhưng hỏi họ phân biệt lock, latch và spinlock khác nhau ra sao, phần lớn sẽ lúng túng — trong khi chính sự phân biệt đó quyết định một database engine có chạy mượt trên hàng trăm lõi hay không, hay chỉ ì ạch dưới tải cao. Locks, latches và spinlocks là ba cơ chế đồng bộ hóa cốt lõi giữ cho một engine đa luồng vẫn đúng đắn, và mỗi cơ chế vận hành ở một tầng hoàn toàn khác nhau: tính nhất quán logic, tính nhất quán cấu trúc trong bộ nhớ, và hành vi cache thô của CPU.

Bài viết này đi từ bài toán hàng đợi ở tầm nhìn cao, xuống tận chi tiết vi kiến trúc của việc một lõi CPU tranh giành một cache line như thế nào. Trên đường đi, ta sẽ xem vì sao B+Tree cần kỹ thuật "latch crabbing" để giữ tính nhất quán khi bị sửa đổi đồng thời, vì sao một spinlock viết ẩu có thể âm thầm làm nghẽn bus bộ nhớ, và hardware transactional memory nằm ở đâu trong bức tranh thiết kế lock-free tương lai.

## Vấn Đề Cốt Lõi

Trong một database engine đa lõi xử lý lượng giao dịch lớn, bài toán khó nhất là tranh chấp tài nguyên (resource contention). Khi hàng nghìn luồng cùng muốn đọc hoặc ghi vào một cấu trúc dữ liệu chia sẻ — B+Tree, buffer pool, hash index — engine cần một cơ chế để chúng không giẫm chân lên nhau và làm hỏng dữ liệu.

Định luật Amdahl đặt ra một giới hạn cứng ở đây: dù ném bao nhiêu lõi vào bài toán, mức tăng tốc vẫn bị chặn bởi tỷ lệ công việc buộc phải chạy tuần tự. Một sơ đồ khóa thiết kế tồi sẽ đẩy ngày càng nhiều việc vào phần tuần tự đó, khiến các luồng dành phần lớn thời gian chờ đợi thay vì tính toán. Tệ hơn, dùng sai công cụ cho đúng việc — chẳng hạn spinlock quanh một lời gọi I/O chậm, hoặc một logical lock đầy đủ quanh một thao tác trong bộ nhớ chỉ mất hai chỉ thị — sẽ biến bus bộ nhớ thành nút thắt cổ chai, vì giao thức đồng nhất bộ đệm MESI cứ phải vô hiệu hóa cache line hết lần này đến lần khác.

Sai ở khâu này, một database chạy trên máy vài trăm lõi hoàn toàn có thể chậm hơn cùng khối lượng công việc đó chạy trên một lõi duy nhất. Dùng đúng locks, đúng latches, đúng spinlocks — mỗi loại ở đúng chỗ của nó — là điều ngăn chặn kịch bản đó xảy ra.

## Phân Tích Kỹ Thuật Chuyên Sâu

### Phân Định Ranh Giới: Locks vs. Latches vs. Spinlocks

Nhầm lẫn giữa lock và latch là một lỗi kinh điển trong kỹ thuật cơ sở dữ liệu, và rất dễ mắc phải vì cả hai đều "bảo vệ" một thứ gì đó. Nhưng chúng vận hành trên hai trục khác nhau — một bên là thời gian (giao dịch cần độc quyền truy cập bao lâu), bên kia là không gian (bạn có thể chạm vào một cấu trúc dữ liệu trong khoảng thời gian ngắn đến mức nào mà không làm hỏng nó).

**1. Locks (Khóa Logic):**
- **Mục đích:** bảo vệ tính nhất quán logic của cơ sở dữ liệu, theo mức độ cô lập giao dịch (isolation level).
- **Đối tượng bảo vệ:** tuples, pages, tables.
- **Thời gian giữ:** dài — giữ suốt vòng đời giao dịch.
- **Quản lý:** một Lock Manager, thường là một hash table lớn, có phát hiện deadlock qua wait-for graph.

**2. Latches (Khóa Vật Lý):**
- **Mục đích:** bảo vệ tính nhất quán vật lý của các cấu trúc dữ liệu trong bộ nhớ, như node của B+Tree, trong lúc đang đọc hoặc sửa.
- **Đối tượng bảo vệ:** cấu trúc dữ liệu bộ nhớ — mảng, danh sách liên kết, node cây.
- **Thời gian giữ:** rất ngắn, thường tính bằng micro giây hoặc ngắn hơn.
- **Quản lý:** tích hợp trực tiếp vào bản thân cấu trúc dữ liệu. Không có bộ phát hiện deadlock ở đây — lập trình viên phải tự ép buộc một thứ tự lấy latch nghiêm ngặt để tránh deadlock.

**3. Spinlocks (Khóa Vi Kiến Trúc):**
- **Mục đích:** dạng latch nguyên thủy nhất, nằm gần phần cứng nhất.
- **Cơ chế:** busy-wait — liên tục thăm dò một biến bộ nhớ thay vì yêu cầu hệ điều hành chuyển ngữ cảnh (context switch).

### Latch Crabbing Trên B+Tree

Latches là tuyến phòng thủ đầu tiên bất cứ khi nào bạn chạm vào dữ liệu trên đĩa hay trong bộ nhớ. Lấy ví dụ B+Tree kinh điển: đi từ nút gốc xuống nút lá thì đơn giản trên một luồng duy nhất, nhưng khi truy cập đồng thời thì rủi ro xuất hiện. Chuyện gì xảy ra nếu một luồng đang đi xuống trong khi một luồng khác chèn một khóa gây tách nút (node split), làm biến dạng chính con đường mà luồng đầu tiên đang đi?

Câu trả lời chuẩn là kỹ thuật **latch crabbing** (còn gọi là latch coupling):
1. Xin latch trên nút cha.
2. Xin latch trên nút con.
3. Kiểm tra xem nút con có "an toàn" không — không đầy, không sắp rỗng.
4. Nếu an toàn, giải phóng latch trên nút cha.
5. Đi tiếp xuống dưới và lặp lại.

Điểm mắc kẹt nằm ở nút gốc — mọi lượt duyệt đều phải xin latch ở đó trước, biến nó thành điểm tuần tự hóa bất kể cây rộng đến đâu. Các engine hiện đại né tránh điều này bằng **optimistic lock coupling**: thay vì xin latch để đọc, một luồng chỉ đọc một biến phiên bản (version counter) trên nút. Nếu biến đó thay đổi trước khi luồng đọc xong — nghĩa là có ai vừa ghi vào nút — nó bỏ kết quả đọc và thử lại. Đa số lượt đọc không gặp tranh chấp, nên đa số lượt đọc không hề phải trả giá cho một latch nào cả.

### Bên Trong Spinlock và Cái Giá Của MESI

Ở tầng thấp nhất, spinlock được xây trên các chỉ thị nguyên tử của CPU — Compare-And-Swap (CAS) là lựa chọn phổ biến nhất. CAS đảm bảo việc so sánh và cập nhật diễn ra nguyên tử, trong một chu kỳ xung nhịp phần cứng duy nhất.

Vấn đề nằm ở chỗ một vòng lặp CAS liên tục sẽ ảnh hưởng thế nào đến phần còn lại của máy. Giao thức MESI (Modified, Exclusive, Shared, Invalid) giữ cho L1/L2 cache nhất quán giữa các lõi, và nó không "thích" bị dồn dập:
- Khi lõi A thực hiện ghi CAS vào spinlock, cache line chứa biến đó chuyển sang trạng thái Modified ở lõi A.
- Mọi lõi khác đang quay vòng trên cùng biến đó — B, C, D — lập tức có bản sao cache line của mình bị vô hiệu hóa.
- Những lõi đó buộc phải fetch lại dòng dữ liệu từ L3 hoặc bộ nhớ chính, và nếu đủ nhiều lõi đang quay vòng, lưu lượng đó làm nghẽn cả bus kết nối (Intel QPI, AMD Infinity Fabric).

**Giải pháp: test-and-test-and-set (TTAS) kết hợp exponential backoff**

```cpp
class ExponentialBackoffSpinlock {
private:
    std::atomic<bool> lock_flag{false};

public:
    void lock() {
        int backoff_time = 1;
        while (true) {
            // Test: Quay vòng cục bộ trên L1 Cache, không làm phiền Bus bộ nhớ
            while (lock_flag.load(std::memory_order_relaxed)) {
                _mm_pause(); // Cứu cánh của siêu luồng (Hyper-threading)
            }
            // Test-And-Set: Chỉ thử ghi bằng lệnh đắt đỏ khi thấy khóa đã mở
            bool expected = false;
            if (lock_flag.compare_exchange_weak(expected, true, std::memory_order_acquire)) {
                return;
            }
            // Backoff: Ngủ một thời gian tăng dần theo cấp số nhân nếu tranh chấp
            for (int i = 0; i < backoff_time; ++i) _mm_pause();
            backoff_time = std::min(backoff_time * 2, 1024);
        }
    }
    void unlock() { lock_flag.store(false, std::memory_order_release); }
};
```

Lời gọi `_mm_pause()` làm nhiều việc hơn vẻ ngoài của nó: nó báo cho CPU biết đây là một vòng lặp bận (để CPU không áp dụng speculative execution theo cách vi phạm trật tự bộ nhớ), đồng thời nhường slot ALU cho một hardware thread anh em trên cùng lõi.

### False Sharing và Memory Alignment

Có một kiểu lỗi tinh vi hơn ở đây: false sharing. Nếu khóa A và khóa B bảo vệ hai vùng dữ liệu chẳng liên quan gì đến nhau nhưng lại vô tình nằm chung một cache line 64 byte, thì việc lõi 1 chạm vào khóa A và lõi 2 chạm vào khóa B, dưới góc nhìn phần cứng, trông y hệt như đang tranh chấp trên cùng một dòng. Kết quả là cùng hiện tượng cache line bouncing như tranh chấp thật, dù chưa từng có xung đột logic nào xảy ra.

Cách khắc phục trong C/C++ là căn chỉnh mỗi spinlock bằng `alignas(64)`, để mỗi khóa có cache line riêng và không khóa nào vô tình dùng chung với khóa khác.

### Futex và Hardware Transactional Memory

Khi số lượng luồng vượt quá khả năng chạy hữu ích của phần cứng, việc quay vòng không còn rẻ nữa — nó chỉ đốt chu kỳ CPU để chờ. Câu trả lời của Linux là futex (fast userspace mutex): trong trường hợp không tranh chấp, một luồng lấy khóa nguyên tử trong userspace mà không cần vào kernel. Chỉ khi tranh chấp kéo dài, nó mới gọi `futex_wait`, lúc đó kernel thực sự đưa luồng vào trạng thái ngủ và giao lõi CPU cho việc khác, rồi đánh thức lại sau bằng `futex_wake`. Bạn có được độ trễ thấp của spinlock trong trường hợp phổ biến, và hiệu quả CPU của một block thực sự trong trường hợp tranh chấp.

Xa hơn nữa, hardware transactional memory (HTM — Intel TSX là ví dụ tham chiếu) hướng tới việc loại bỏ hẳn cái khóa. Một vùng găng (critical section) chạy đầu cơ mà không giữ latch nào cả; phần cứng theo dõi L1 cache từng dòng một, và nếu không có gì khác chạm vào cùng dữ liệu đó, giao dịch được commit. Nếu có xung đột xuất hiện, phần cứng abort và khôi phục thanh ghi ngay lập tức, phần mềm quay về dùng lock thông thường. Về bản chất, HTM biến một pessimistic lock thành một optimistic lock triển khai bằng phần cứng, mà không tốn chi phí CAS.

## Bài Học Kinh Nghiệm & Thực Tiễn

1. **Hiểu rõ context switch thực sự tốn bao nhiêu.** Block trên một mutex truyền thống thay vì quay vòng tốn vài micro giây mỗi lần chuyển. Trong một database in-memory, vài micro giây đủ để CPU thực hiện hàng nghìn phép toán — đó chính xác là lý do spinlock tồn tại cho các đoạn găng ngắn, và cũng là lý do bạn không bao giờ nên dùng nó cho bất cứ thứ gì có thể block trên I/O.
2. **Tôn trọng cache line.** Đừng bao giờ cấp phát một mảng spinlock liền kề nhau mà không đệm (padding). `alignas(64)` không phải là trang trí — bỏ qua nó là mời gọi false sharing, và false sharing thì vô hình cho đến khi bạn profile ra nó.
3. **Ưu tiên optimistic hơn pessimistic khi có thể.** Khóa toàn bộ cây hay danh sách cho chắc ăn thường là lựa chọn sai. Optimistic lock coupling — kiểm tra version counter, thử lại nếu không khớp — chỉ tốn của bạn một lượt đọc lãng phí thỉnh thoảng, thay vì chặn đứng mọi luồng khác đang muốn vào.
4. **Không có bộ phát hiện deadlock ở tầng latch.** Thiết kế sai thứ tự lấy latch, sẽ không có gì cứu bạn như wait-for graph cứu một deadlock giao dịch — tiến trình chỉ đơn giản là treo. Hãy ép buộc một thứ tự nghiêm ngặt, có ghi rõ ràng, bất cứ khi nào code chạm vào nhiều hơn một latch cùng lúc.

## Kết Luận

Locks, latches và spinlocks không phải là kiến thức hàn lâm suông — chúng là cơ chế thực sự cho phép một hệ thống giao dịch phục vụ hàng nghìn client đồng thời mà không làm hỏng một dòng dữ liệu nào. Mọi database phân tán, mọi hạ tầng tài chính đang chạy hôm nay đều phụ thuộc vào việc làm đúng chuyện này. Điều phân biệt một kỹ sư có năng lực với người thực sự có thể giúp một hệ thống mở rộng quy mô là khả năng hiểu cách các cấu trúc dữ liệu ở tầng phần mềm tương tác với thực tế vật lý của bộ xử lý — cấu trúc NUMA, tính nhất quán cache, trật tự bộ nhớ. Thiết kế một chiến lược đồng bộ hóa không chỉ là viết code không bị crash; đó là làm việc thuận theo các ràng buộc vật lý của con silicon, thay vì chống lại chúng.
