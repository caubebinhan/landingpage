---
seo_title: "Mô Hình Percolator: Google Spanner và TiDB xử lý Distributed Transactions"
seo_description: "Phân tích mô hình Percolator - cách Google Spanner và TiDB dùng MVCC, 2PC, TrueTime API và Async Commit để xử lý distributed transactions trên quy mô lớn."
focus_keyword: "mô hình Percolator"
---

# 14: Mô hình Percolator: Cách Google Spanner và TiDB xử lý Distributed Transactions

## Tóm Tắt Điều Hành

Bài viết này đi sâu vào **mô hình Percolator**, kiến trúc đứng sau cách nhiều hệ thống cơ sở dữ liệu phân tán hiện đại xử lý giao dịch. Google giới thiệu Percolator năm 2010 để giải một bài toán khó nhằn: làm sao có được giao dịch ACID đầy đủ trên một kho lưu trữ NoSQL cỡ lớn như Bigtable, vốn không hề được thiết kế cho việc đó.

**Trong bài này bạn sẽ thấy:**
- Percolator kết hợp MVCC (Điều khiển Đồng thời Đa Phiên bản) với một biến thể 2PC không cần coordinator lưu trạng thái riêng.
- Cách dữ liệu được xếp thành ba cột ($A_{data}$, $A_{lock}$, $A_{write}$) trên nền LSM-Tree để tránh ghi ngẫu nhiên xuống đĩa.
- Vì sao Timestamp Oracle (TSO) dễ trở thành nút thắt cổ chai, và TiDB giải quyết bằng Async Commit ra sao.
- Google Spanner đi xa hơn thế nào với TrueTime API - dùng đồng hồ nguyên tử và GPS để giới hạn độ bất định của thời gian.

## Vấn Đề Cốt Lõi

Trong các kiến trúc đám mây và microservices hiện nay, dữ liệu bị băm và phân mảnh trên hàng nghìn máy chủ, đặt rải khắp nhiều trung tâm dữ liệu ở các vùng địa lý khác nhau. Giữ cho một giao dịch cập nhật nhiều bản ghi cùng lúc vẫn nhất quán trong bối cảnh đó không phải chuyện đơn giản.

- Các RDBMS truyền thống dựa vào một hệ quản lý khóa tập trung. Đưa mô hình đó vào môi trường phân tán thì tạo ra điểm lỗi đơn (SPOF) và độ trễ mạng kéo hiệu năng xuống rất nhanh.
- 2PC kiểu cổ điển qua X/Open XA cần một coordinator ghi trạng thái giao dịch xuống đĩa. Nếu coordinator chết giữa chừng, mọi bên tham gia bị treo tài nguyên vô thời hạn - đây chính là "blocking problem" kinh điển của 2PC.

Google cần một cơ chế không có điểm thắt cổ chai trung tâm, cho phép các giao dịch đọc chạy nhanh mà không bao giờ bị chặn bởi giao dịch ghi. Percolator ra đời từ yêu cầu đó.

## Phân Tích Kỹ Thuật Chuyên Sâu

### Nền Tảng Lý Thuyết: MVCC và Tọa Độ Thời Gian

Percolator xây trên sự kết hợp giữa MVCC (Multi-Version Concurrency Control) và một biến thể phân tán của 2PC dựa trên khóa lạc quan (Optimistic Concurrency Control - OCC).

Mỗi phiên bản dữ liệu không chỉ là một giá trị tĩnh - nó gắn với một tọa độ trong không gian thời gian hai chiều. Khi giao dịch $T_i$ bắt đầu, hệ thống gán cho nó một mốc thời gian bắt đầu $T_{s,i}$. Mốc này hoạt động như một bộ lọc: $T_i$ chỉ được phép nhìn thấy các phiên bản dữ liệu do giao dịch $T_j$ tạo ra và đã commit với $T_{c,j} < T_{s,i}$. Cơ chế này cho mức cô lập Snapshot Isolation (SI), loại bỏ Dirty Read và Phantom Read, chỉ còn để lại một rủi ro nhỏ về Write Skew.

### Cấu Trúc Vi Mô Dữ Liệu: Kỹ Thuật Ba Cột

Để hiện thực hóa các khái niệm trên, Percolator tách một thuộc tính logic $A$ thành ba cột vật lý riêng biệt bên trong lớp lưu trữ Bigtable/LSM-Tree:

1. **Cột $A_{data}$ (Payload):** lưu dữ liệu thô. Khi giao dịch cập nhật, giá trị mới $V_{new}$ được ghi vào đây với khóa là $T_s$ - ngay cả khi giao dịch chưa hoàn tất.
2. **Cột $A_{lock}$ (Semaphore):** quản lý khóa độc quyền phân tán. Khi ghi, giao dịch phải đặt cờ vào cột này. Giao dịch song song nào thấy cờ đó sẽ tự backoff theo nguyên lý OCC để tránh deadlock.
3. **Cột $A_{write}$ (Source of Truth):** chỉ được cập nhật sau khi giao dịch commit thành công. Bản ghi ở đây dùng khóa $T_c$ và giá trị là một con trỏ trỏ ngược về tọa độ $T_s$ trong cột $A_{data}$.

Cách dùng con trỏ gián tiếp này giúp giảm write amplification đáng kể - dữ liệu nặng (large binaries) chỉ cần ghi xuống đĩa một lần.

### Cơ Chế 2PC Không Trạng Thái Riêng và Khóa Chính (Primary Lock)

Điểm khác biệt của Percolator là coordinator (thường chỉ là một thư viện client) không giữ trạng thái riêng nào cả - tiến trình của giao dịch được khắc thẳng vào lớp dữ liệu.

Quy trình gồm ba bước:
1. **Prewrite:** client xác định tập bản ghi cần sửa ($K$), chọn ngẫu nhiên một trong số đó làm Primary Lock $k_p$, các khóa còn lại là Secondary Locks. Client gửi lệnh Prewrite tới mọi node lưu trữ liên quan. Mỗi node kiểm tra xung đột (có giao dịch tương lai nào đã ghi đè hay đang giữ khóa không); nếu an toàn, giá trị mới được ghi vào $A_{data}$ và cờ ghi vào $A_{lock}$ (khóa phụ chứa con trỏ trỏ về $k_p$).
2. **Commit:** client lấy mốc thời gian commit $T_c$, rồi chỉ gửi lệnh Commit đến node giữ Primary Lock $k_p$. Nếu cờ khóa của $k_p$ vẫn còn nguyên, node ghi con trỏ vào $A_{write}$ tại $T_c$ và xóa cờ $A_{lock}$.
3. **Async cleanup:** ngay khi $k_p$ được commit, toàn bộ giao dịch coi như đã thành công trên toàn hệ thống. Việc commit hàng nghìn Secondary Locks còn lại chạy nền, không cần client chờ.

### TSO Và Giải Pháp Async Commit Của TiDB

Mọi giao dịch Percolator đều cần Timestamp Oracle (TSO) cấp mốc thời gian. TSO dễ trở thành nút thắt về độ trễ mạng vì phải phục vụ số lượng lớn RPC. Batching RPC giúp giảm tải phần nào, nhưng độ trễ round-trip (RTT) vẫn còn đó.

TiDB - dự án mã nguồn mở kế thừa mô hình Percolator - đã tổ chức lại tầng này. Dựa trên nền Raft Consensus, TiDB xây dựng cơ chế **Async Commit / 1PC**: thay vì hỏi TSO, các node lưu trữ (TiKV) tự nội suy một mốc commit dự kiến dựa trên đồng hồ cục bộ của chúng. Coordinator gom các con số này lại, lấy $T_c$ lớn nhất làm mốc toàn cục, và bước Commit thứ hai được đẩy hẳn xuống chạy nền. RTT giảm từ 2 vòng xuống còn 1.

```mermaid
sequenceDiagram
    participant Client as TiDB (SQL Layer/Coordinator)
    participant TSO as Placement Driver (TSO)
    participant TiKV_P as TiKV (Primary Lock Region)
    participant TiKV_S as TiKV (Secondary Lock Region)
    
    Client->>TSO: 1. RPC Request start_ts
    TSO-->>Client: 2. Return start_ts
    
    par Async Prewrite Broadcast
        Client->>TiKV_P: 3. Prewrite(start_ts, primary, Calc_Tc_p)
        Client->>TiKV_S: 3. Prewrite(start_ts, secondary, Calc_Tc_s)
    end
    
    note over TiKV_P,TiKV_S: Raft Consensus replication for Prewrite log
    TiKV_P-->>Client: 4. Prewrite OK (MaxLocal_Tc_p)
    TiKV_S-->>Client: 4. Prewrite OK (MaxLocal_Tc_s)
    
    note over Client: Calculate Global Min Commit Ts = max(MaxLocal_Tc_p, MaxLocal_Tc_s) + 1
    
    Client-->>Client: 5. Return success instantly (Async Commit Engine)
    
    par Background Commit Push
        Client->>TiKV_P: 6. Commit(start_ts, Global_Tc)
    end
```

### Google Spanner Đi Xa Hơn: TrueTime API

Spanner tấn công trực diện vào chính khái niệm thời gian. Thay vì dựa vào một TSO đơn lẻ vốn dễ nghẽn, Google trang bị cho các trung tâm dữ liệu bộ thu GPS và đồng hồ nguyên tử Rubidium, tạo thành **TrueTime API**.

Hàm $TT.now()$ không trả về một thời điểm chính xác mà là một khoảng bất định $[t_{earliest}, t_{latest}]$. Độ rộng $\epsilon$ của khoảng này luôn được giữ dưới 7 mili-giây.

Spanner dùng tính chất này trong **Commit Wait Rule**: ở bước chốt của 2PC, Spanner đặt $T_c = t_{latest}$, rồi không báo thành công ngay mà bắt tiến trình "ngủ" cho tới khi $TT.now().earliest > T_c$. Khoảng chờ vài mili-giây này loại bỏ rủi ro đảo lộn nhân quả giữa các sự kiện, cho phép Spanner đạt Strict Global External Consistency mà không cần một TSO trung tâm nào cả.

## Bài Học Kinh Nghiệm & Thực Tiễn

1. **2PC không nhất thiết phải chậm và dễ kẹt.** Bằng cách bỏ coordinator có trạng thái riêng (khắc metadata thẳng vào dữ liệu) và dùng Primary Lock làm điểm neo, Percolator giải quyết được vấn đề SPOF vốn ám ảnh 2PC truyền thống. Đây là một mẫu thiết kế đáng học khi xây hệ thống phân tán.
2. **LSM-Tree phù hợp với mẫu ghi này hơn B+Tree.** Cấu trúc ba cột của Percolator sẽ hành hạ một ổ HDD chạy B+Tree vì tạo ra quá nhiều truy cập ngẫu nhiên. LSM-Tree biến các thao tác đó thành ghi tuần tự - lựa chọn cấu trúc dữ liệu ở đây gắn chặt với hiệu năng thực tế.
3. **`fsync()` từng thao tác một là kẻ thù của throughput.** Các engine như TiKV dùng group commit kết hợp `io_uring` và Direct I/O để đưa độ phức tạp của việc xả đĩa từ $\mathcal{O}(M)$ xuống gần $\mathcal{O}(1)$. Đừng gọi fsync cho từng ghi riêng lẻ.
4. **Đôi khi giới hạn phần mềm chỉ giải được bằng phần cứng.** Khi TSO chạm ngưỡng bão hòa, câu trả lời của Google là lắp đồng hồ nguyên tử Rubidium vào trung tâm dữ liệu - một quyết định phần cứng đã định hình lại cách Spanner vận hành.

## Kết Luận

Từ Percolator gốc của Google với 2PC/OCC, qua các cải tiến Raft và Async Commit của TiKV/TiDB, đến TrueTime API trên Spanner - đây là một chuỗi giải pháp cho cùng một bài toán: làm sao giữ tính nhất quán khi dữ liệu trải khắp hàng nghìn máy chủ. Mỗi bước tiến kết hợp lý thuyết serializability, kỹ thuật I/O bất đồng bộ và, ở trường hợp Spanner, cả phần cứng đo thời gian chuyên dụng. Hiểu được mô hình Percolator là hiểu được vì sao các hệ thống phân tán hiện đại có thể vừa nhất quán vừa mở rộng được.
