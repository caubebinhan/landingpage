---
seo_title: "Physical Replication và Logical Replication ở tầng kernel"
seo_description: "Physical replication và logical replication khác nhau ra sao ở tầng kernel: zero-copy DMA, chi phí giải mã WAL, reorder buffer, và lý do lag theo hàng đợi M/M/1."
focus_keyword: "physical replication logical replication"
---

# Logical vs. Physical Replication - Giải Phẫu Luồng Dữ Liệu Ở Cấp Độ Hạt Nhân

## Tóm tắt Điều hành (Executive Summary)

Muốn giữ tính nhất quán và độ sẵn sàng cao trong một hệ quản trị cơ sở dữ liệu phân tán hay một kiến trúc lưu trữ quy mô lớn, gần như bắt buộc phải có một cơ chế đồng bộ (replication) đủ tinh vi giữa các node. Trong lĩnh vực này, hai trường phái kiến trúc đã tồn tại song song từ rất lâu: **physical replication** (sao chép vật lý) và **logical replication** (sao chép logic).

Bài viết này không chỉ nói về "cái gì" ở bề mặt ứng dụng, mà đi thẳng xuống những tầng thấp nhất của hệ thống — bộ nhớ kernel, cấu trúc đĩa NVMe, vi kiến trúc CPU, giao thức TCP/IP — để xem dữ liệu thực sự di chuyển ra sao từ node nguồn (primary) sang các node đích (replica), và vì sao hai cách tiếp cận này lại có hiệu năng khác biệt lớn đến vậy khi chịu tải thực tế.

**Vấn đề cốt lõi (Problem Statement):**
Làm sao đồng bộ hàng gigabyte dữ liệu mỗi giây giữa các máy chủ mà không làm sập mạng hoặc vắt kiệt CPU? Sao chép vật lý cho tốc độ gần chạm giới hạn phần cứng (nhờ zero-copy DMA), nhưng lại ràng buộc chặt vào cấu trúc nhị phân của một hệ điều hành và một phiên bản cơ sở dữ liệu cụ thể. Sao chép logic cho sự linh hoạt — lọc dữ liệu, sao chép xuyên nền tảng — đổi lại là một khoản thuế tính toán thật sự cho việc giải mã, cùng rủi ro tràn bộ nhớ. Chọn sai kiến trúc này khi hệ thống đang chịu tải cao, hậu quả không dừng ở việc chậm đi mà có thể là sụp đổ.

**Bài học và Kiến thức rút ra (Lessons Learned):**
1. **Sao chép vật lý vô địch về thông lượng thô.** Gần như bỏ qua hoàn toàn CPU (kernel bypass, `sendfile()`/`splice()`), nó đẩy byte từ RAM ra card mạng với chi phí gần như $O(1)$ mỗi byte.
2. **Sao chép logic è cổ trả thuế tính toán.** Giải mã lại luồng WAL thành các hàng dữ liệu buộc CPU phải phân tích cú pháp byte thô, kéo tỷ lệ cache hit ở L1/L2 xuống thấp và đòi hỏi cấp phát những reorder buffer khá lớn trong bộ nhớ.
3. **Độ trễ đồng bộ đi theo quy luật hàng đợi.** Vì bước áp dụng thay đổi ở phía logic vốn chạy đơn luồng, độ trễ hành xử giống hệt một hàng đợi M/M/1 — và có thể phình to rất nhanh nếu primary ghi nhanh hơn tốc độ replica xử lý kịp.

---

## Physical Replication: Đồng Bộ Ở Cấp Độ Khối

Ý tưởng nền tảng của sao chép vật lý khá thẳng: chép nguyên xi nội dung nhị phân của các trang bộ nhớ (8KB) hoặc file WAL từ nguồn sang đích, từng byte một, không diễn giải gì ở giữa.

### Khái Niệm LSN (Log Sequence Number)

Sao chép vật lý dựa vào một định danh tăng đơn điệu gọi là **LSN** — tọa độ vật lý tuyệt đối của một bản ghi trong file log, được định nghĩa đệ quy như sau:
$$LSN_{i+1} = LSN_{i} + \Delta_{size}(Record_{i}) + \sigma(alignment)$$
$\sigma(alignment)$ ở đây là một số hạng làm tròn nhỏ, đệm mỗi bản ghi khớp vào ranh giới 8 hay 16 byte để bus dữ liệu của CPU hoạt động thuận tiện hơn. Chuỗi LSN tạo ra một trật tự happens-before không thể phá vỡ: replica chỉ cần áp byte vào đúng offset tương ứng trên đĩa của nó, không cần hiểu ý nghĩa của dữ liệu.

### Truyền Zero-Copy và Đường Ống Ở Tầng Kernel

Lợi thế cốt lõi của sao chép vật lý là khả năng lách qua gần như toàn bộ user space. Trong một đường I/O truyền thống, dữ liệu phải đi qua bốn vùng đệm khác nhau: đĩa, page cache của kernel, buffer người dùng, buffer socket — mỗi bước đều tốn một lần sao chép.

Sao chép vật lý tránh phần lớn chi phí đó bằng **zero-copy DMA** thông qua `sendfile()` hay `splice()` trên Linux:
- Luồng WAL được DMA nạp thẳng từ NVMe SSD vào page cache (không gian kernel).
- `sendfile()` ra lệnh cho NIC lấy dữ liệu trực tiếp từ page cache rồi đẩy thẳng lên đường truyền.
- CPU gần như đứng ngoài cuộc — không tốn một chu kỳ nào để đọc nội dung byte thực tế.

Độ trễ của một lần commit đồng bộ rút gọn thành tổng các độ trễ vật lý đơn thuần:
$$T_{sync\_commit} = T_{local\_flush} + T_{network\_RTT} + T_{remote\_flush} + T_{ack}$$
Để ý là thời gian CPU không xuất hiện ở đâu trong công thức này — nó chỉ bị chi phối bởi độ trễ đĩa và thời gian khứ hồi mạng.

### Giới Hạn Băng Thông và TCP (BBR/CUBIC)

Khi thông lượng ghi vượt khoảng 1000 MB/s, điểm nghẽn dịch chuyển từ SSD sang chính ngăn xếp TCP/IP. Gói tin bắt đầu rớt sẽ kéo theo **head-of-line blocking**, khiến thông lượng tụt mạnh dù phần cứng bên dưới vẫn hoàn toàn bình thường.

Để chống chịu những trục trặc này, hệ thống giữ một ring buffer chứa các đoạn WAL chưa được xác nhận — tham số `wal_keep_size` trong PostgreSQL chính là để chỉnh việc này. Kích thước an toàn tối thiểu được quyết định bởi tích số băng thông và độ trễ:
$$BDP = C \times RTT$$
Nếu ring buffer tràn trước khi replica bắt kịp, slot đồng bộ coi như hỏng vĩnh viễn, buộc replica phải đồng bộ lại từ đầu.

```mermaid
graph TD
    subgraph Primary_Node
        A[Transaction Manager] -->|Write Process| B(WAL Buffer - User Space)
        B -->|fdatasync System Call| C[VFS Page Cache - Kernel Space]
        C -->|DMA Flush| D[(NVMe SSD Array)]
        C -->|sendfile Zero-copy| E[TCP Socket Buffer]
    end
    subgraph Network_Routing
        E -->|TCP BBR/CUBIC Sliding Window| F{Optical Network Interface Card}
        F -->|Dark Fiber / 100G Switch| G{Replica Network Interface Card}
    end
    subgraph Replica_Node
        G -->|DMA Ring Rx| H[TCP Socket Buffer]
        H -->|recv syscall| I(WAL Receiver Process)
        I -->|write syscall| J[VFS Page Cache]
        J -->|fdatasync syscall| K[(Replica NVMe SSD)]
        I -->|Apply Micro-instruction| L[Shared Buffer Cache / Page Blocks]
    end
```

---

## Logical Replication: Bên Trong Pipeline Giải Mã Logic

Trong khi sao chép vật lý chỉ chuyển byte một cách mù quáng, sao chép logic phải đóng vai một người phiên dịch: đọc lại các khối byte vật lý thô, tách chúng ra, rồi dựng lại thành những câu lệnh SQL thuần túy — INSERT, UPDATE, DELETE.

Chính đặc tính này khiến nó hữu dụng ở những chỗ sao chép vật lý bất lực: sao chép từ PostgreSQL sang MySQL, chuyển dữ liệu từ máy chủ x86_64 sang ARM, hay chỉ sao chép mỗi bảng `users` trong khi bỏ qua hoàn toàn bảng `logs`.

### Chi Phí Tính Toán Ở Cấp Vi Kiến Trúc

Sự linh hoạt này không hề miễn phí — nó ngốn những chu kỳ CPU thật sự ở tầng vi kiến trúc. Giải mã hàng gigabyte WAL thô đòi hỏi bộ giải mã phải tra cứu metadata MVCC (Multi-Version Concurrency Control) chỉ để xác định hình dạng của một tuple.

Nghĩa là hàng tỷ phép phân tích cú pháp nhỏ lẻ, trên dữ liệu liên tục đổi hình dạng. Tập dữ liệu đang xử lý không thể gói gọn trong cache L1/L2, dẫn đến một chuỗi cache miss liên tục cả về lệnh lẫn dữ liệu — và thông lượng CPU trên đường đi này tụt rõ rệt so với sao chép vật lý.

### Reorder Buffer

Thách thức thuật toán khó nhằn nhất trong giải mã logic là **reorder buffer**. Trong một luồng WAL, các bản ghi của nhiều giao dịch đan xen lẫn nhau, kiểu như `Tx1_Start`, `Tx2_Start`, `Tx1_Insert`, `Tx2_Update`, `Tx1_Commit`.

Nhưng sao chép logic vẫn phải giữ nguyên tính nguyên tử. Nó không được phát ra bất kỳ phần nào của `Tx1` cho tới khi thực sự thấy `Commit` của `Tx1`. Vì thế bộ giải mã phải dựng một hash map trong bộ nhớ, ôm giữ toàn bộ dữ liệu của mọi giao dịch đang mở — `Tx1`, `Tx2`, và bất kỳ giao dịch nào khác — cho tới khi từng cái commit xong.

Khi tổng kích thước các giao dịch đang xử lý vượt ngân sách bộ nhớ cho phép (ví dụ một giao dịch đơn lẻ cập nhật 10 triệu hàng), hệ thống buộc phải chuyển sang **tràn ra đĩa (spill-to-disk)**.

```cpp
template <typename DataType>
class HighlyConcurrentReorderBuffer {
private:
    std::unordered_map<TransactionId, std::vector<LogicalTupleChange>> active_inflight_txns;
    std::atomic<size_t> current_memory_footprint{0};
    const size_t HARD_MEMORY_LIMIT = 1024 * 1024 * 512; // 512 MB Threshold

    // Cứu vớt hệ thống khỏi thảm họa Out-Of-Memory bằng Spill-to-Disk
    void evict_to_disk_spill(TransactionId victim_xid) {
        int fd = create_anonymous_temp_file(victim_xid);
        size_t stream_size = active_inflight_txns[victim_xid].size() * sizeof(LogicalTupleChange);
        
        // Cấp phát Zero-copy Mapped I/O
        void* virtual_mapped_mem = mmap(nullptr, stream_size, PROT_WRITE, MAP_SHARED, fd, 0);
        memcpy(virtual_mapped_mem, active_inflight_txns[victim_xid].data(), stream_size);
        
        // Ép xả xuống đĩa
        msync(virtual_mapped_mem, stream_size, MS_ASYNC);
        active_inflight_txns[victim_xid].clear();
        munmap(virtual_mapped_mem, stream_size);
        current_memory_footprint.fetch_sub(stream_size, std::memory_order_release);
    }
};
```

Phần tốn kém nhất nằm ở khoảnh khắc `COMMIT` tới: hệ thống phải chạy **external merge sort** giữa dữ liệu vẫn còn trong RAM và phần đã bị đẩy ra đĩa. Đó là một đợt I/O đọc/ghi ngẫu nhiên dồn dập, đúng lúc ta muốn đĩa yên tĩnh nhất.

---

## Lý Thuyết Hàng Đợi và Độ Trễ Đồng Bộ

Vấn đề cấu trúc lớn nhất ở phía replica trong sao chép logic là bước áp dụng thay đổi chạy đơn luồng. Primary có thể đang ghi song song trên 64 lõi CPU, nhưng replica chỉ dùng **một worker áp dụng duy nhất** để thực thi lại các câu lệnh SQL logic — đó là cách duy nhất để giữ đúng thứ tự ràng buộc khóa ngoại một cách an toàn.

Điều này biến độ trễ đồng bộ thành một bài toán hàng đợi M/M/1 kinh điển. Áp dụng công thức Pollaczek-Khinchine, độ dài hàng đợi kỳ vọng $L_q$ — chính là độ trễ đồng bộ — được tính:
$$L_q = \frac{\rho^2 + \rho^2 C_s^2}{2(1 - \rho)} \quad \text{với hệ số tải} \quad \rho = \frac{\lambda_{total}}{\mu_{apply}}$$

Khi $\lambda_{total}$ (tốc độ primary sinh thay đổi) tiến gần $\mu_{apply}$ (tốc độ replica áp dụng kịp), $\rho \to 1$, và công thức cho đúng kết quả ta lo ngại: $L_q$ tiến tới vô cực. Đây không phải là vấn đề có thể vá bằng cách thêm RAM — đó là giới hạn vật lý cứng của cơ chế áp dụng đơn luồng. Thiếu một cơ chế backpressure làm chậm primary lại, replica có thể trôi lệch hàng giờ đồng hồ.

Sao chép vật lý né hẳn vấn đề này. Vì không cần quan tâm ngữ nghĩa SQL hay khóa ngoại, tiến trình áp dụng WAL có thể giao việc sửa trang cho hàng chục worker thread chạy song song, mỗi thread độc lập vá khối 8KB riêng của mình trong bộ nhớ. Miễn là memory barrier được tôn trọng đúng cách (và tránh được false sharing trên NUMA), sao chép vật lý mở rộng ở phía replica gần như tuyến tính.

---

## Tổng Kết

Sao chép logic và sao chép vật lý không đơn thuần là hai cách triển khai cùng một ý tưởng — chúng đại diện cho hai lựa chọn đánh đổi khác nhau về điều gì thực sự quan trọng.

- **Sao chép logic** mang lại tự do: tách dữ liệu khỏi phần cứng và định dạng bên dưới, mở đường cho các mô hình hybrid-cloud, CDC (Change Data Capture), và pipeline streaming. Giá phải trả là chi phí CPU, áp lực bộ nhớ từ reorder buffer, và một trần thông lượng cứng do cơ chế áp dụng đơn luồng áp đặt.
- **Sao chép vật lý** chỉ trung thành với cách bố trí byte của primary, không hơn không kém. Đổi lại là thông lượng gần chạm giới hạn phần cứng, chi phí CPU tối thiểu, và khả năng khôi phục sau thảm họa tỷ lệ thuận với băng thông đĩa thay vì độ phức tạp của SQL.

Hiểu được mỗi mô hình sụp đổ ở đâu, và vì sao, mới là điều giúp vận hành thực sự một cụm cơ sở dữ liệu xử lý hàng triệu giao dịch mỗi giây, chứ không chỉ dừng ở việc theo dõi dashboard.
