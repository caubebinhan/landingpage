---
seo_title: "Kiến trúc TOAST của PostgreSQL: Lưu trữ Dữ liệu Ngoại cỡ"
seo_description: "Giải thích kiến trúc TOAST của PostgreSQL: con trỏ varatt_external, nén PGLZ/LZ4, phân mảnh sang shadow table và cách TOAST triệt tiêu write amplification."
focus_keyword: "kiến trúc TOAST PostgreSQL"
---

# 08: Kiến trúc TOAST trong PostgreSQL: Quản lý Bộ nhớ và Lưu trữ Dữ liệu Ngoại cỡ

## Tóm tắt & Vấn đề

Một RDBMS lưu dữ liệu theo khối cố định luôn đụng phải một giới hạn vật lý khó chịu: làm sao nhét một trường TEXT, một tài liệu JSONB, hay một mảng BYTEA nặng vài chục megabyte vào một trang đĩa chỉ 8KB?

Nếu tăng kích thước trang lên, ví dụ 1MB, để chứa vừa dữ liệu lớn, hệ thống sẽ phá vỡ hiệu quả của các truy vấn OLTP nhỏ lẻ — lãng phí bộ nhớ và khuếch đại I/O một cách không cần thiết.

PostgreSQL không chọn cách phá vỡ mô hình quản lý bộ nhớ theo trang truyền thống. Thay vào đó nó dùng một cơ chế khá tinh tế: kiến trúc TOAST (The Oversized-Attribute Storage Technique).

TOAST cho phép PostgreSQL tự động phân mảnh, nén, và đẩy các trường dữ liệu khổng lồ ra lưu trữ ngoại tuyến, hoàn toàn trong suốt với lớp SQL phía trên. Hiểu rõ vi kiến trúc của TOAST không chỉ giải thích vì sao PostgreSQL xử lý JSON cỡ lớn tốt đến vậy, mà còn là một bài học hay về tối ưu cache CPU (TLB, L1/L2) và tránh làm ô nhiễm OS page cache.

---

## Giới hạn Trang và Ngưỡng TOAST

Để hiểu vì sao TOAST ra đời, cần nhìn vào cấu trúc vật lý của một trang đĩa trong PostgreSQL.

Mặc định, hằng số `BLCKSZ` (kích thước trang) của PostgreSQL là 8192 byte ($2^{13}$ byte). Trong không gian chật hẹp đó, dữ liệu không được đổ vào tùy tiện — nó phải theo cấu trúc slotted page:

1. **PageHeaderData:** chiếm 24 byte, chứa metadata như `pd_lsn` (log sequence number phục vụ phục hồi WAL), `pd_checksum`, và các con trỏ `pd_lower`, `pd_upper`.
2. **Mảng ItemIdData:** ở cuối trang, một mảng tĩnh các line pointer trỏ tới từng tuple thực tế.

PostgreSQL theo đuổi một nguyên tắc thiết kế rõ ràng: tránh phân mảnh trang. Mỗi tuple phải nằm trọn trong một lần đọc I/O nguyên tử của đúng một trang đĩa — không được phép tràn từ trang này sang trang khác.

Đồng thời, để giữ mật độ tuple hợp lý — một trang nên chứa được vài bản ghi, chứ không phải một bản ghi khổng lồ chiếm hết 8KB — PostgreSQL đặt ra một ngưỡng gọi là `TOAST_TUPLE_THRESHOLD`:

$$ TOAST\_TUPLE\_THRESHOLD = \lfloor \frac{BLCKSZ}{4} \rfloor - 1 $$

Với $BLCKSZ = 8192$, ngưỡng này là 2047 byte (trên thực tế làm tròn để căn chỉnh bộ nhớ, thường lấy khoảng 2040 byte).

Nếu một tuple được insert lớn hơn ngưỡng đó, engine lưu trữ buộc phải kích hoạt cơ chế TOAST: cắt bớt bản ghi xuống dưới giới hạn an toàn để vừa với heap page, rồi đẩy phần dữ liệu dôi ra sang một bảng "bóng" riêng.

---

## Vi kiến trúc Con trỏ TOAST (`varatt_external`)

Hành trình của một khối dữ liệu ngoại cỡ bắt đầu bằng việc thay thế nội dung gốc — ví dụ một file PDF 10MB lưu trong BYTEA — bằng một cấu trúc con trỏ chỉ 18 byte, gọi là `varatt_external`.

Cấu trúc `varlena` (mảng độ dài biến đổi) dùng bit-masking để mã hóa trạng thái. Đọc bit đầu tiên của byte header, parser biết ngay dữ liệu đang ở dạng nào:
- `PLAIN`: dữ liệu thô nằm inline, chưa nén.
- `COMPRESSED`: dữ liệu đã nén nhưng vẫn nằm inline.
- `EXTERNAL`: dữ liệu đã được đẩy ra ngoại tuyến (con trỏ TOAST).

Con trỏ 18 byte `varatt_external` chứa bốn trường:
1. `va_rawsize`: kích thước gốc chưa nén.
2. `va_extsize`: kích thước vật lý sau khi nén và phân mảnh trên đĩa.
3. `va_valueid`: OID định danh khối dữ liệu này.
4. `va_toastrelid`: OID của bảng TOAST đang giữ khối dữ liệu.

Với cơ chế con trỏ này, bản ghi trên bảng chính lúc này chỉ đóng vai trò như một chỉ mục dẫn hướng. Ví dụ bảng có cột `(id INT, created_at TIMESTAMP, payload JSONB)` với payload nặng 5MB. Trên heap chính, tuple chỉ còn chiếm: $4 \text{ byte (id)} + 8 \text{ byte (timestamp)} + 18 \text{ byte (con trỏ TOAST)} = 30 \text{ byte}$.

### Lợi thế với MVCC

Con trỏ 18 byte này không chỉ tiết kiệm dung lượng — nó còn giải quyết một vấn đề nghiêm trọng trong MVCC.
PostgreSQL không bao giờ ghi đè dữ liệu cũ. Khi chạy `UPDATE table SET status='done' WHERE id=1` (không đụng tới cột `payload`), hệ thống vẫn phải tạo một phiên bản tuple hoàn toàn mới.

Nếu không có TOAST pointer, PostgreSQL sẽ phải chép nguyên 5MB JSONB sang tuple mới mỗi lần update — một dạng write amplification rất tốn kém.
Nhờ TOAST pointer, PostgreSQL chỉ chép 18 byte con trỏ `va_valueid` sang tuple mới. Cả tuple cũ và mới cùng trỏ về một khối dữ liệu 5MB duy nhất trong shadow table. Cách chia sẻ con trỏ này loại bỏ gần như hoàn toàn write amplification, giúp SSD đỡ mài mòn hơn nhiều.

---

## Bảng Bóng TOAST và Thuật toán Phân mảnh

Khi dữ liệu bị đẩy ra ngoài, nó đi đâu? PostgreSQL tự động tạo một bảng "bóng" tên `pg_toast.pg_toast_XXX` (XXX là OID của bảng chính), ẩn khỏi các câu lệnh `\d` thông thường.

Bảng TOAST có ba cột cốt lõi:
1. `chunk_id` (OID): khớp với `va_valueid` của con trỏ 18 byte.
2. `chunk_seq` (INT): số thứ tự tăng dần từ 0, dùng để ghép các mảnh lại.
3. `chunk_data` (BYTEA): dữ liệu nhị phân thô của từng mảnh.

### Toán học của việc Phân mảnh

Các bản ghi trong shadow table cũng không được vượt quá `TOAST_TUPLE_THRESHOLD`, nên kích thước tối đa của một mảnh, `TOAST_MAX_CHUNK_SIZE`, được giới hạn mặc định ở 1996 byte.

Số lượng mảnh $N_{chunks}$ tính theo hàm trần:
$$ N_{chunks} = \lceil \frac{S_{compressed}}{1996} \rceil $$
Với một tệp 10MB (10,485,760 byte), PostgreSQL cắt nó thành khoảng $10,485,760 / 1996 \approx 5254$ dòng trong bảng `pg_toast_XXX`.

Để đọc lại nhanh, PostgreSQL tạo một chỉ mục B-Tree riêng `pg_toast_XXX_index` trên cặp cột `(chunk_id, chunk_seq)`. Khi truy vấn cần trường dữ liệu đó, executor duyệt B-Tree với độ phức tạp $\mathcal{O}(\log n + K)$ (K là số chunk), ghép 5254 mảnh này lại thành một khối liên tục trong RAM, giải nén, rồi trả về cho người dùng.

```mermaid
graph TD;
    subgraph Bảng Chính (Main Heap)
        A[Tuple Page 1] -->|Vượt ngưỡng 2040B| B(Header: TOAST_EXTERNAL);
        B --> C{Con trỏ 18-Byte \nva_valueid: 998877};
    end
    subgraph Bảng Bóng Tối TOAST [pg_toast_12345]
        C -.->|Index Scan O(log N)| D[(pg_toast_12345_index)];
        D -->|chunk_seq = 0| E[1996 Bytes Payload];
        D -->|chunk_seq = 1| F[1996 Bytes Payload];
        D -->|chunk_seq = 2| G[Partial Payload];
    end
    subgraph Bộ Nhớ Trình Thực Thi (Executor Context)
        E --> H((Memory Concatenation));
        F --> H;
        G --> H;
        H -->|PGLZ/LZ4 Algorithm| I[JSONB/Văn Bản Nguyên Bản];
    end
```

---

## Nén Dữ liệu và Entropy Shannon

Trước khi băm nhỏ thành các chunk 1996 byte, PostgreSQL thử nén dữ liệu để giảm tải I/O vật lý.
Hai thuật toán được hỗ trợ là PGLZ (PostgreSQL Lempel-Ziv) và LZ4 (nhanh hơn hẳn, có từ PG 14). Cả hai dựa trên từ điển vòng và tìm chuỗi lặp lại.

Điểm khôn ngoan của PostgreSQL nằm ở chỗ nó biết đánh giá rủi ro của việc nén, dựa trên lý thuyết thông tin Shannon.
Entropy của một chuỗi nhị phân $X$:
$$ H(X) = -\sum_{i=1}^{n} P(x_i) \log_2 P(x_i) $$

Nếu bạn chèn một tệp đã nén sẵn — JPEG, MP4, hay file GZIP — dữ liệu đã ở trạng thái gần với nhiễu trắng, entropy gần cực đại. Lempel-Ziv lúc này gần như vô dụng, thậm chí có thể làm tăng kích thước tệp và tốn thêm chu kỳ CPU vô ích.

Để tránh việc đó, PostgreSQL áp dụng một cơ chế heuristic đơn giản: nó chỉ thử nén trên một đoạn đầu của dữ liệu. Nếu tỷ lệ nén không tiết kiệm được ít nhất 25% kích thước (tức không giảm xuống dưới 75% kích thước gốc), quá trình nén bị hủy ngay lập tức. Khối dữ liệu được gắn nhãn uncompressed trong header và lưu thẳng vào TOAST ở dạng thô. Nhờ vậy hệ thống vẫn giữ được thông lượng ổn định ngay cả khi ứng dụng liên tục chèn dữ liệu không thể nén được.

```cpp
// Pseudocode: Vi kiến trúc Nén TOAST Heuristic
struct varlena* toast_insert_or_update(struct varlena* datum, StorageType strategy) {
    size_t raw_size = VARSIZE_ANY_EXHDR(datum);
    if (raw_size <= 2040) return datum; // Khối dữ liệu an toàn
    
    // Giai đoạn Nén Thử nghiệm (Heuristic Check)
    struct varlena* compressed_datum = perform_lz4_compression(datum);
    if (compressed_datum != nullptr && VARSIZE_ANY_EXHDR(compressed_datum) < raw_size * 0.75) {
        // Entropy thấp, Nén LZ4 thành công
        datum = compressed_datum;
    } else {
        // Entropy quá cao, Bỏ nén để cứu CPU Cycles
        free_memory_context(compressed_datum); 
    }
    
    // Tiến hành Binary Chunking ra Shadow Table...
    // Return 18-byte External Pointer
}
```

---

## Tương tác với Hệ điều hành, Phần cứng và VACUUM

Kiến trúc TOAST thể hiện rõ nhất giá trị của nó qua cách nó tương tác với hệ thống bộ đệm của CPU (TLB, L1/L2 cache) và page cache của Linux.

### Tránh Ô nhiễm OS Page Cache

Trong các ứng dụng dữ liệu lớn, một câu lệnh phân tích quét toàn bảng (`SELECT id, status FROM massive_table`) có thể chạm tới hàng triệu bản ghi.
Nếu dữ liệu JSONB 10MB nằm inline ngay trong bản ghi chính, lệnh quét này sẽ phải kéo hàng nghìn gigabyte dữ liệu JSONB không liên quan từ NVMe lên RAM, đẩy các dữ liệu quan trọng hơn — như root node của B-Tree index — ra khỏi page cache của Linux. Đây chính là hiện tượng OS cache pollution.

Với TOAST, bản ghi chính chỉ chứa con trỏ 18 byte. Hàng triệu bản ghi giờ nằm gọn trong vài megabyte RAM. Dữ liệu TOAST nằm yên trên đĩa và chỉ được nạp vào RAM khi người dùng thực sự chạy `SELECT payload FROM ...`. Tốc độ sequential scan trên dữ liệu lõi nhờ đó tăng đáng kể.

### Giảm TLB Misses

Khi mật độ tuple cao, một cache line 64 byte hay một memory page 4KB chứa được nhiều bản ghi hữu ích hơn hẳn. Điều này giảm đáng kể số lần TLB miss, tránh việc CPU phải dừng lại để thực hiện page table walk — một thao tác không hề rẻ.

### Dữ liệu Mồ côi và Autovacuum

Bảng TOAST không dùng khóa ngoại trỏ ngược về bảng chính, để tránh chi phí khóa. Vì vậy, khi bạn xóa một dòng ở bảng chính, các chunk 1996-byte tương ứng bên TOAST không bị xóa ngay — chúng trở thành dữ liệu mồ côi (dangling chunks) trong một khoảng thời gian.

Đây là lúc autovacuum vào việc. Khi vacuum quét bảng chính, nhận diện tuple đã chết, nó lấy OID 18 byte, nhảy sang bảng `pg_toast_XXX`, dùng B-Tree index để xóa vật lý các chunk liên quan, giải phóng không gian đĩa cho các lần chèn mới.

Sự phối hợp chặt chẽ này cho thấy TOAST không đơn thuần là một mẹo lưu trữ nhỏ — nó là một hệ thống con hoàn chỉnh, đủ tinh vi để giúp một RDBMS truyền thống như PostgreSQL xử lý được khối lượng JSON và document khổng lồ của thế giới hiện đại mà không phải trả giá quá đắt về hiệu năng.
