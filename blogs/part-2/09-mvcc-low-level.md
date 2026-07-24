---
seo_title: "MVCC Là Gì? Phân Tích Kiến Trúc Mức Thấp, EBR và NUMA"
seo_description: "Tìm hiểu MVCC (multi-version concurrency control) ở tầng vi kiến trúc: mô hình lưu trữ đa phiên bản, pointer chasing, false sharing, EBR và tối ưu NUMA."
focus_keyword: "MVCC (Multi-Version Concurrency Control)"
---

# Multi-Version Concurrency Control (MVCC): Phân Tích Kiến Trúc Mức Thấp và Hệ Sinh Thái Quản Lý Bộ Nhớ Đa Luồng

## Tổng Quan

MVCC — multi-version concurrency control — là cơ chế mà hầu hết các cơ sở dữ liệu hiện đại dùng để cho phép đọc và ghi diễn ra song song mà không chặn lẫn nhau. Đó là định nghĩa sách giáo khoa. Còn cái giá thực sự phải trả ở tầng phần cứng thì lại là một câu chuyện khác, và phần lớn kỹ sư chỉ chạm vào nó khi đang debug một đợt tăng latency lúc nửa đêm.

Bài viết này đi từ dưới lên: chuỗi phiên bản tương tác với cache CPU ra sao, vì sao chúng sinh ra pointer chasing và false sharing, hệ thống thu hồi bộ nhớ mà không cần dừng toàn bộ tiến trình bằng cách nào, và kiến trúc NUMA ảnh hưởng đến tất cả những điều đó ra sao. Nếu bạn làm việc với storage engine, hoặc đơn giản là muốn hiểu vì sao workload OLTP của mình lại phản ứng như vậy khi có tranh chấp (contention), những gì dưới đây sẽ hữu ích.

**Nội dung chính:**
- Ba mô hình lưu trữ đa phiên bản chủ đạo (Append-Only, Time-Travel, Delta Storage) và đánh đổi giữa chúng.
- Ma sát ở tầng phần cứng: pointer chasing và false sharing, kèm những con số cụ thể.
- Epoch-Based Reclamation (EBR) như một giải pháp thay thế lock-free cho reference counting.
- Bố trí bộ nhớ theo NUMA và vì sao điều này quan trọng với garbage collection.

## Vấn Đề Cốt Lõi

Trong các hệ thống OLTP xử lý khối lượng giao dịch lớn, việc giữ dữ liệu nhất quán trong khi vẫn tối đa hóa throughput là một bài toán đã cũ. Cơ chế khóa (lock-based) giải quyết được vấn đề nhất quán khá gọn, nhưng lại buộc giao dịch đọc và ghi phải chờ nhau, và điều đó trở thành nút thắt cổ chai thật sự ngay khi hệ thống chạy trên nhiều hơn vài lõi CPU.

MVCC né tránh vấn đề này bằng cách giữ nhiều phiên bản của cùng một dòng dữ liệu, để đọc không chặn ghi và ghi không chặn đọc. Điều đó giải quyết được bài toán tranh chấp, nhưng lại kéo theo một loạt vấn đề khác ở tầng vi kiến trúc:

1. **Phân mảnh bộ nhớ và độ trễ truy cập:** Giữ nhiều phiên bản tạo áp lực thực sự lên bộ cấp phát bộ nhớ. Khi chuỗi phiên bản dài ra, CPU phải đuổi theo con trỏ (pointer chasing) để tìm đúng phiên bản cần đọc, và tỷ lệ cache miss tăng theo.
2. **Chi phí đồng bộ hóa đa luồng:** Đọc/ghi đồng thời trên cùng cấu trúc dữ liệu chia sẻ cần đến memory barrier và các lệnh nguyên tử (atomic operations) — những thứ này không miễn phí, chúng làm tăng lưu lượng trên bus bộ nhớ.
3. **Quản lý vòng đời phiên bản (Garbage Collection):** Xác định thời điểm an toàn để thu hồi một phiên bản cũ, mà không làm hỏng một giao dịch vẫn đang đọc nó, là một bài toán thực sự khó. Làm sai thì hoặc rò rỉ bộ nhớ, hoặc làm hỏng kết quả đọc.

## Phân Tích Kỹ Thuật Chuyên Sâu

### Nền Tảng Lý Thuyết và Kiến Trúc Lưu Trữ Đa Phiên Bản

Về bản chất, MVCC là một bài toán ánh xạ. Nếu trạng thái logic của cơ sở dữ liệu tại thời điểm $t$ là một tập hợp tuple $D_t = \{R_1, R_2, \dots, R_n\}$, tầng MVCC ánh xạ mỗi tuple logic $R_i$ thành một tập hợp các phiên bản vật lý $V_i = \{v_{i,1}, v_{i,2}, \dots, v_{i,m}\}$.

Mỗi phiên bản $v_{i,j}$ mang theo một khoảng hiệu lực $[T_{begin}, T_{end})$. Tùy vào cấp độ cô lập (isolation level) đang áp dụng — Read Committed, Serializable, hay bất cứ mức nào engine hỗ trợ — transaction manager sẽ quyết định một lượt đọc cụ thể nên được đánh giá theo timestamp nào, để đảm bảo khả năng phục hồi (recoverability) và tính tuần tự (serializability).

Bản thân version tuple thường mang khá nhiều metadata nhúng ngay trong header để phục vụ việc kiểm tra tính khả kiến (visibility): định danh giao dịch tạo ra phiên bản ($TxnID_{creator}$), định danh giao dịch xóa nó ($TxnID_{deleter}$), và một con trỏ nối phiên bản này với phiên bản trước hoặc sau trong chuỗi. Header này thường nặng khoảng 16 đến 32 byte.

#### Các Mô Hình Lưu Trữ

Có ba cách tiếp cận chính để tổ chức các phiên bản vật lý:
- **Append-Only Storage:** mọi phiên bản mới được ghi vào cùng một table space với tất cả các phiên bản cũ (đây là cách PostgreSQL làm).
- **Time-Travel Storage:** table space chính giữ phiên bản hiện hành, còn các bản sao đầy đủ của phiên bản cũ được đẩy sang một vùng lưu trữ riêng.
- **Delta Storage:** chỉ lưu phần chênh lệch (delta), không lưu bản sao đầy đủ (MySQL, Oracle).

### Rào Cản Phần Cứng: Pointer Chasing và Cache Miss

Với mô hình Append-Only, nếu tỷ lệ cập nhật là $\lambda$ update/giây, bộ nhớ phình ra theo tốc độ $\frac{dM}{dt} = \lambda \times S_{tuple}$. Đây chính là chỗ mô hình này gặp rắc rối: khi các phiên bản liên tiếp nằm rải rác trong bộ nhớ, cache CPU gần như hết tác dụng.

Duyệt qua một chuỗi phiên bản nghĩa là giải tham chiếu con trỏ hết lần này đến lần khác — đây chính là hiện tượng pointer chasing kinh điển. Giả sử $P_{miss}$ là xác suất trượt cache L3 mỗi lần giải tham chiếu, $T_{mem}$ là thời gian truy cập bộ nhớ chính (khoảng 100ns), và $T_{cache}$ là thời gian truy cập cache L1 (khoảng 1ns). Nếu một lượt đọc phải duyệt qua chuỗi dài $L$ để tìm ra phiên bản khả kiến, thời gian kỳ vọng để hoàn tất là:

$$E[T_{resolve}] = L \times \left( P_{miss} \times T_{mem} + (1 - P_{miss}) \times T_{cache} \right)$$

Với Append-Only Storage, $P_{miss}$ có xu hướng tiến sát về 1.0 khi mức độ phân mảnh tăng lên — gần như mỗi bước nhảy đều là một lần cache miss chắc chắn — và độ trễ của table scan cũng suy giảm theo đúng tỷ lệ đó.

### Tối Ưu Với Delta Storage Và Bài Toán False Sharing

Delta Storage được thiết kế riêng để tránh vấn đề trên. Thay vì thêm hẳn một bản sao mới, nó cập nhật trực tiếp (in-place) phiên bản chính và ghi một bản ghi vi phân (delta record) chỉ chứa những trường đã thay đổi. Delta này được đẩy vào một vùng ring buffer gọi là Undo Log. Tốc độ tăng bộ nhớ ở đây tuân theo $\frac{dM_{undo}}{dt} = \lambda \times (S_{\Delta} + S_{metadata})$ — tỷ lệ thuận với kích thước phần thay đổi, chứ không phải kích thước cả dòng dữ liệu.

Tái tạo lại một phiên bản cũ giờ đây nghĩa là duyệt chuỗi delta và áp dụng ngược từng bản vá một. Để làm điều này an toàn dưới truy cập đồng thời, mã nguồn cần có memory ordering tường minh — chẳng hạn `std::memory_order_acquire` khi load con trỏ.

Ở cấp độ mã nguồn, false sharing là một mối nguy thường trực. Theo giao thức nhất quán cache MESI (Modified, Exclusive, Shared, Invalid), khi một lõi CPU ghi vào một biến, toàn bộ cache line chứa biến đó sẽ bị đánh dấu invalid trên mọi lõi khác — kể cả khi các lõi đó đang thao tác trên những trường hoàn toàn không liên quan, chỉ vì chúng tình cờ nằm chung một cache line. Cách khắc phục là buộc tách biệt bằng các chỉ thị căn chỉnh như `alignas(64)` trong C/C++, để các trường "nóng" không chia sẻ cache line với thứ mà một luồng khác đang liên tục ghi vào.

```mermaid
graph TD
    subgraph CPU_Cache_Architecture
        L1_Core1[L1 Cache Core 0] -->|MESI Invalidate| L1_Core2[L1 Cache Core 1]
        L1_Core1 --> L2_Core1[L2 Cache]
        L1_Core2 --> L2_Core2[L2 Cache]
        L2_Core1 --> L3_Shared[L3 Shared Cache]
        L2_Core2 --> L3_Shared
    end
    subgraph Physical_Memory_Layout
        L3_Shared --> Main_Tuple[Main Version Tuple\nHeader | ID | Payload\nalignas 64 bytes]
        Main_Tuple -.->|Atomic Undo Pointer| Delta_1[Delta Record 1\nTxnID | Changed Columns]
        Delta_1 -.->|Atomic Undo Pointer| Delta_2[Delta Record 2\nTxnID | Changed Columns]
    end
    style Main_Tuple fill:#f9f,stroke:#333,stroke-width:2px
    style Delta_1 fill:#bbf,stroke:#333,stroke-width:1px
    style Delta_2 fill:#bbf,stroke:#333,stroke-width:1px
```

```cpp
// Minh họa cấu trúc dữ liệu Low-Level của Delta Storage trong môi trường Đa luồng
#include <atomic>
#include <cstdint>
#include <cstring>

// Căn chỉnh 64 byte để ngăn chặn False Sharing trên cache line
struct alignas(64) UndoRecord {
    std::atomic<UndoRecord*> next_delta;
    uint64_t transaction_id;
    uint32_t delta_size;
    uint8_t payload[]; // Flexible array member
};

struct alignas(64) TupleHeader {
    uint64_t xmin; 
    std::atomic<uint64_t> xmax; 
    std::atomic<UndoRecord*> undo_pointer; 
    uint32_t tuple_length;
    uint16_t attributes_mask;
};

// Hàm đọc và áp dụng Delta sử dụng cơ chế Wait-Free
void reconstruct_version(const TupleHeader* base_tuple, uint64_t read_ts, uint8_t* output_buffer) {
    std::memcpy(output_buffer, reinterpret_cast<const uint8_t*>(base_tuple) + sizeof(TupleHeader), base_tuple->tuple_length);
    UndoRecord* current_delta = base_tuple->undo_pointer.load(std::memory_order_acquire);
    
    while (current_delta != nullptr) {
        if (current_delta->transaction_id < read_ts) break; 
        apply_binary_patch_logic(output_buffer, current_delta->payload, current_delta->delta_size);
        current_delta = current_delta->next_delta.load(std::memory_order_acquire);
    }
}
```

### Thuật Toán Thu Gom Rác Bằng Epoch-Based Reclamation (EBR)

Phiên bản cũ tích tụ liên tục, và áp lực đó phải được giải quyết ở đâu đó. Đường chân trời sự kiện của cơ sở dữ liệu, $TS_{min} = \min_{T \in ActiveTxns} (TS_{read}(T))$, cho biết phiên bản nào có thể an toàn để loại bỏ: $\forall v_k \in Memory, \text{IsGarbage}(v_k) \iff v_k.T_{end} < TS_{min}$.

Thay vì trả giá cho reference counting ở mỗi lần truy cập, các hệ thống in-memory như HyPer hay Silo dùng Epoch-Based Reclamation. Hệ thống chia trục thời gian thành các kỷ nguyên rời rạc ($E_1, E_2, \dots$). Khi một luồng thu hồi một phiên bản cũ, nó không giải phóng ngay — nó đưa con trỏ vào một danh sách rác gắn với kỷ nguyên toàn cục hiện tại, $GarbageList[E_{global}]$. Lệnh `free()` thực sự chỉ được gọi khi mọi luồng đang hoạt động đã tiến ít nhất hai kỷ nguyên qua mốc đó:

$$\forall thread \in ActiveThreads, E_{local}(thread) > E_{safe} + 1$$

Điểm yếu ở đây khá rõ ràng: nếu một luồng bị treo — chờ I/O, bị hệ điều hành tạm dừng, hay bất cứ lý do gì — nó sẽ giữ bộ đếm kỷ nguyên đứng yên cho tất cả các luồng khác, và rác tích tụ trên toàn hệ thống cho đến khi luồng đó chạy lại. Một luồng bị kẹt âm thầm có thể biến thành một sự cố OOM.

### Vi Kiến Trúc NUMA và TLB Shootdown

Hệ điều hành cũng can thiệp vào đây. Khi bộ nhớ được giải phóng qua `munmap`, nhân hệ điều hành kích hoạt TLB shootdown — một ngắt liên vi xử lý (IPI) buộc mọi lõi phải xả một phần pipeline của mình — và điều này tốn kém đáng kể ở quy mô lớn.

Cách xử lý phổ biến là tránh đi qua kernel cho các lần cấp phát nóng: các bộ cấp phát ở user-space như `jemalloc` hay `tcmalloc`, với arena riêng cho từng luồng, né được phần lớn chi phí này. Trên phần cứng NUMA (Non-Uniform Memory Access) còn có thêm một lớp vấn đề nữa — RAM được gắn vật lý vào từng CPU socket cụ thể, và việc truy cập chéo socket để lấy dữ liệu tốn kém hơn hẳn so với truy cập cục bộ. Rollback segment của một giao dịch nên được cấp phát trên cùng node NUMA với luồng đang sử dụng nó, thông qua các API như `numa_alloc_onnode`.

## Bài Học Kinh Nghiệm & Thực Tiễn

Một vài điều rút ra được sau khi đào sâu vào MVCC ở tầng này:

1. **Hiểu rõ phần cứng của mình.** Không thể thiết kế một hệ thống đồng thời có throughput cao mà coi cache L1/L2/L3, băng thông bộ nhớ, và kết nối NUMA là chuyện của người khác. Mọi tối ưu hóa đều phải tính đến kích thước cache line 64 byte.
2. **Đừng để false sharing trôi qua.** Bố trí các cấu trúc dữ liệu chia sẻ một cách có chủ đích (`alignas(64)`) để các luồng độc lập không tranh nhau cùng một cache line và gây ra những lần invalidate không cần thiết.
3. **Đẩy việc thu hồi ra khỏi hot path.** Lock và atomic reference counting ở mỗi lần truy cập đều tốn kém. EBR hay hazard pointer giúp đưa chi phí đó ra khỏi đường thực thi then chốt.
4. **Tự viết bộ cấp phát bộ nhớ riêng.** Dựa vào `malloc`/`free` của hệ điều hành cho các lần cấp phát nóng nghĩa là phải trả giá cho syscall, TLB shootdown, và phân mảnh mà đáng lẽ không cần thiết. Những hệ thống thực sự quan tâm đến hiệu năng đều tự pool bộ nhớ ở user-space.

## Kết Luận

Xây dựng một hệ thống MVCC không chỉ đơn thuần là quản lý đúng các timestamp phiên bản. Bên dưới lớp logic đó là một cuộc thương lượng liên tục với thực tế vật lý của máy tính — cache line, bus bộ nhớ, cấu trúc NUMA. Hiệu năng của một cơ sở dữ liệu dưới tải đồng thời phụ thuộc vào việc nó kiểm soát độ trễ pointer chasing tốt đến đâu, tôn trọng ranh giới NUMA ra sao, và thu hồi bộ nhớ mà không gây đình trệ như thế nào. Không có điều nào trong số này là tùy chọn nếu bạn đang xây một storage engine cần đứng vững dưới tải đồng thời thực tế — đó chính là khác biệt giữa một hệ thống thực sự scale được và một hệ thống chỉ trông có vẻ như vậy trong benchmark đơn luồng.
