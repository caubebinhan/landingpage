---
seo_title: "Memory-Mapped Files (mmap) và cái chết của MongoDB MMAPv1"
seo_description: "Phân tích kỹ thuật cơ chế memory-mapped files (mmap), lý do MongoDB MMAPv1 sụp đổ dưới tải cao vì Database Lock và Page Fault Thrashing, và vì sao WiredTiger thay thế nó."
focus_keyword: "Memory-Mapped Files mmap"
---

# 05: Memory-Mapped Files (mmap) và Vì Sao MongoDB Khai Tử MMAPv1

## Tóm tắt & Vấn đề

Cách một hệ quản trị cơ sở dữ liệu quản lý bộ nhớ đệm và lưu trữ vật lý gần như quyết định số phận lâu dài của nó. Thế hệ NoSQL đầu tiên, mà đại diện tiêu biểu nhất là MongoDB với engine MMAPv1, đã chọn một lối tắt hấp dẫn: giao toàn bộ việc quản lý I/O và bộ nhớ cho nhân hệ điều hành thông qua memory-mapped files (`mmap`).

Lối tắt này giúp MongoDB ra đời nhanh, code engine gọn, đội ngũ kỹ sư ban đầu không phải tự viết buffer pool. Nhưng khi dữ liệu vượt qua kích thước RAM và số lượng ghi đồng thời tăng lên hàng trăm nghìn, cái mô hình dựa hoàn toàn vào `mmap` bắt đầu lộ ra những vết nứt không thể vá: khóa cấp cơ sở dữ liệu (database-level lock), hiện tượng page fault thrashing khiến luồng đứng hình, và tình trạng phân mảnh đĩa ngày càng nặng.

Vấn đề cốt lõi nằm ở chỗ: kernel là một trình quản lý bộ nhớ đa dụng, nó không biết gì về cấu trúc B-Tree hay quan hệ giữa các tệp dữ liệu bên trong. Khi thuật toán LRU của kernel quyết định đẩy một trang ra khỏi RAM, nó hoàn toàn có thể chọn nhầm root node của một index quan trọng — và thế là một truy vấn tưởng chừng đơn giản lại kéo theo một major page fault làm tê liệt cả luồng xử lý.

Bài viết này đi sâu vào cơ chế vi kiến trúc của `mmap`, dùng định luật Amdahl để chứng minh vì sao hiệu năng đa luồng sụp đổ, mổ xẻ nguyên nhân MMAPv1 bị khai tử, và giải thích tại sao MongoDB buộc phải chuyển sang một engine tự quản lý bộ nhớ như WiredTiger.

---

## Cơ chế Vi kiến trúc của Memory-Mapped Files (mmap)

Memory-mapped files, tức system call `mmap` trong POSIX, là kỹ thuật ánh xạ trực tiếp nội dung một tệp trên đĩa vào không gian địa chỉ ảo của tiến trình. Nó bỏ qua các lời gọi I/O truyền thống như `read()` hay `write()`. Một khi tệp đã được ánh xạ, ứng dụng đọc ghi tệp đó y hệt như đang thao tác trên một mảng byte nằm trong RAM, thông qua con trỏ thông thường.

### Thiết lập Ánh xạ Ảo (Virtual Mapping)

Khi tiến trình gọi `mmap()`, kernel **không** đọc đĩa ngay. Nó chỉ dựng lên một vùng nhớ ảo mới (Virtual Memory Area - VMA), ghi nhận rằng khoảng địa chỉ ảo từ $V_{start}$ đến $V_{end}$ tương ứng với tệp nào trên đĩa.

Việc ánh xạ này gắn chặt với bảng trang đa mức (multi-level page table) và bộ đệm dịch địa chỉ TLB (Translation Lookaside Buffer) bên trong MMU của CPU.

Về mặt toán học, đây là phép biến đổi từ không gian ảo $\mathcal{V}$ sang không gian vật lý $\mathcal{P}$, thông qua thiết bị khối $\mathcal{D}$. Với một trang kích thước $S_{page}$ (thường 4KB) nằm ở địa chỉ ảo $V_{addr}$, địa chỉ vật lý $P_{addr}$ chỉ thực sự được xác định khi xảy ra page fault. Gọi $\mathcal{F}$ là hàm phân giải địa chỉ:

$$ P_{addr} = \mathcal{F}(V_{addr}) = P_{base} + (V_{addr} \pmod{S_{page}}) $$

### Page Fault và Cái giá về Độ trễ

Trái tim của `mmap` là demand paging — phân trang theo yêu cầu — được kích hoạt bởi major page fault.

Khi ứng dụng truy cập qua con trỏ vào một vùng $V_{addr}$ mà trang vật lý tương ứng chưa nằm trong RAM, MMU không tìm thấy ánh xạ hợp lệ trong page table. Nó lập tức phát sinh một hardware exception, đẩy quyền điều khiển về cho kernel.

Quy trình xử lý một major page fault diễn ra như sau:
1. **Context switch:** tiến trình ứng dụng bị chặn lại.
2. **Kernel VMA lookup:** kernel xác định tệp vật lý và offset tương ứng với địa chỉ ảo bị lỗi.
3. **I/O fetch:** kernel cấp một page frame trống, rồi ra lệnh DMA kéo dữ liệu từ đĩa (HDD/NVMe) lên trang RAM đó.
4. **Cập nhật PTE:** page table entry được cập nhật để nối $V_{addr}$ với trang RAM mới.
5. **Resume:** trả lại quyền điều khiển, ứng dụng thực thi lại lệnh truy cập bộ nhớ vừa bị lỗi.

```mermaid
flowchart TD
    A[Tiến trình User Space] -->|Truy cập biến đổi V_addr| B(Memory Management Unit - MMU)
    B -->|Tra cứu bộ đệm| C{Translation Lookaside Buffer - TLB}
    C -- TLB Hit --> D[Địa chỉ vật lý P_addr]
    C -- TLB Miss --> E{Page Table Walk (Duyệt bảng trang)}
    E -- PTE Hợp lệ --> F[Cập nhật TLB và Trả về P_addr]
    E -- PTE Trống (Invalid) --> G((Major Page Fault))
    G --> H[Chuyển đổi ngữ cảnh sang Kernel Mode]
    H --> I[Xác định khối trên Đĩa thông qua VMA]
    I --> J[Điều phối I/O Đọc trang vào RAM vật lý]
    J --> K[Cập nhật Page Table Entry - PTE]
    K --> L[Phục hồi tiến trình User Space]
    F --> D
    L --> B
    D --> M[Đọc/Ghi Dữ liệu RAM]
```

Chi phí ở đây lớn hơn nhiều so với cảm giác trực quan. Gọi $T_{L1}$ là thời gian truy cập cache L1 (khoảng 1ns), $T_{RAM}$ là truy cập RAM (khoảng 100ns), $T_{disk}$ là độ trễ đĩa (100µs với NVMe, 5ms với HDD), và $P_{fault}$ là xác suất xảy ra page fault.

Thời gian truy cập kỳ vọng $E[T_{access}]$ được tính:
$$ E[T_{access}] = (1 - P_{fault}) \cdot T_{RAM} + P_{fault} \cdot T_{disk} $$

Vì $T_{disk} \gg T_{RAM}$ — chênh lệch từ $10^3$ đến $10^5$ lần — chỉ cần $P_{fault} = 0.01$ (1% số lần truy cập bị fault) là hiệu suất đã tụt hàng nghìn lần. Một truy vấn quét dữ liệu lớn dễ dàng biến tiến trình database thành nạn nhân của context switch liên tục.

### Ảo mộng "Zero-Copy" của mmap

Bất chấp cái giá của page fault, `mmap` từng mang lại cho các hệ thống đời đầu một lời hứa rất hấp dẫn: không cần sao chép bộ nhớ hai lần (zero-copy read/write).
Với `read()` truyền thống, dữ liệu đi qua ba chặng: đĩa → kernel page cache → user buffer.
Với `mmap`, kernel page cache chính là user buffer luôn — ứng dụng đọc ghi thẳng vào đó.

Điều này cho phép MongoDB viết engine gọn và nhanh chóng. Kỹ sư không cần tự xây buffer pool, không cần tự cài đặt LRU eviction, không cần thuật toán flush nền. Nhân Linux lo hết. Với một startup đang chạy đua ra thị trường, đây là lợi thế cạnh tranh thực sự.

Nhưng khoản nợ kỹ thuật này rồi cũng phải trả.

```c
// Mã giả minh họa ảo mộng mmap của MongoDB MMAPv1
#include <sys/mman.h>
#include <sys/stat.h>
#include <fcntl.h>

void* init_database_mmap(const char* db_file_path, size_t* out_size) {
    int fd = open(db_file_path, O_RDWR);
    struct stat sb;
    fstat(fd, &sb);
    
    // Yêu cầu Kernel ánh xạ toàn bộ CSDL vào không gian ảo
    void* db_memory = mmap(NULL, sb.st_size, PROT_READ | PROT_WRITE, MAP_SHARED, fd, 0);
    
    *out_size = sb.st_size;
    close(fd); // OS vẫn duy trì ánh xạ ngay cả khi fd bị đóng
    
    return db_memory;
    // Từ đây, MongoDB truy cập B-Tree và Documents bằng con trỏ C++ thẳng vào db_memory
}
```

---

## MongoDB MMAPv1: Kiến trúc, Giới hạn và Điểm mù

Trong MMAPv1 — engine mặc định của MongoDB cho tới bản 3.0 — mọi tệp vật lý được ánh xạ thẳng vào không gian ảo theo đúng cơ chế vừa mô tả.

### Phân bổ Tệp theo Hàm mũ

MMAPv1 tạo các tệp dữ liệu phân đoạn được đánh số (`database.0`, `database.1`, v.v.), mỗi tệp mới có dung lượng gấp đôi tệp trước, theo hàm mũ cơ số 2 cho tới trần 2GB:
$$ S_i = \min(2^i \cdot 64 \text{ MB}, 2048 \text{ MB}) $$

Mục đích là giữ cho các document nằm trên những vùng đĩa liền kề (contiguous extents), giảm seek time trên ổ HDD cơ học. Bên trong mỗi tệp, các extent chứa danh sách liên kết đôi của các document BSON.

### Bài toán Cấp phát Động và Di dời Tài liệu

Trong NoSQL, schema linh hoạt là con dao hai lưỡi. Một document 1KB hôm nay hoàn toàn có thể phình thành 5KB vào ngày mai, chỉ vì ứng dụng đẩy thêm phần tử vào một mảng lồng bên trong nó.

Nếu vùng đĩa ngay sát document đã bị chiếm, thao tác update sẽ kích hoạt document relocation — di dời tài liệu:
1. MongoDB phải xin cấp một vùng trống mới, đủ 5KB.
2. Dùng `memcpy` chép toàn bộ dữ liệu sang đó.
3. Giải phóng vùng 1KB cũ, để lại một lỗ hổng gây phân mảnh.
4. Cập nhật lại mọi leaf node trong tất cả các index để trỏ đến địa chỉ vật lý mới.

MongoDB có thêm padding động để chừa chỗ trống phòng hờ, nhưng cách này ngốn dung lượng đáng kể và không giải quyết tận gốc hiệu ứng I/O gợn sóng do relocation gây ra.

### Nút thắt cổ chai: Khóa Cấp Cơ sở Dữ liệu

Vì RAM được truy cập trực tiếp qua con trỏ C++ trần, việc đảm bảo an toàn luồng trở nên đắt đỏ. Ban đầu MMAPv1 dùng một khóa đọc-ghi toàn cục cho cả tiến trình, sau đó được hạ xuống mức database-level lock.

Nghĩa là nếu một luồng đang ghi vào collection `Users`, cả database — kể cả `Orders`, `Products` không liên quan gì — cũng bị khóa cứng. Không ai đọc được, không ai ghi được.

Định luật Amdahl cho ta một cách nhìn định lượng về mức độ tệ của việc này. Gọi $P$ là tỷ lệ thời gian hệ thống chạy song song được, $S = 1 - P$ là phần buộc phải chạy tuần tự (nằm trong critical section của lock). Gia tốc tối đa khi mở rộng ra $N$ lõi CPU:
$$ \text{Speedup}(N) = \frac{1}{(1 - P) + \frac{P}{N}} $$

Trong MMAPv1, vì một khóa duy nhất bao trùm cả database, $1-P$ rất lớn. Khi số kết nối $N \to \infty$, hiệu suất tiệm cận về:
$$ \lim_{N \to \infty} \text{Speedup}(N) = \frac{1}{1 - P} $$
Mua một máy chủ 128 lõi cho MMAPv1 gần như là phí tiền, vì 127 lõi còn lại chỉ ngồi spin-wait chờ khóa được nhả.

### Page Fault Thrashing và Cơ chế Yield

Điều tệ nhất xảy ra khi database lock gặp major page fault cùng lúc:
1. Luồng $T_1$ giữ write lock của cả database.
2. $T_1$ cố ghi vào một document nằm ở địa chỉ ảo $V_x$.
3. Không may, trang vật lý ứng với $V_x$ vừa bị hệ điều hành âm thầm đẩy xuống đĩa vì thuật toán LRU của kernel thấy nó ít được dùng gần đây.
4. $T_1$ bị kernel chặn lại vì page fault, phải chờ có khi tới 10 mili-giây để dữ liệu được kéo lên từ đĩa.
5. Suốt 10 mili-giây đó, $T_1$ vẫn đang giữ write lock.
6. Hàng nghìn kết nối khác đứng hình hoàn toàn — hệ thống trông như bị đóng băng, không có lỗi rõ ràng nào để nhìn vào.

Để giảm nhẹ vấn đề, MongoDB thêm cơ chế yield: trước khi truy cập một trang, luồng gọi `mincore()` của Linux để hỏi xem trang đó có đang nằm trong RAM không. Nếu không, nó tự nhả khóa, kích hoạt một tiến trình nền đẩy yêu cầu I/O (background page-in), rồi chờ. Đây là một miếng vá hợp lý về mặt thực dụng, nhưng nó khiến state machine của concurrency trở nên rối rắm và khó dự đoán hơn nhiều.

---

## Sự Khai tử của MMAPv1 và Bước chuyển sang WiredTiger

Những hạn chế của MMAPv1 trở thành rào cản không thể vượt qua khi dữ liệu lớn trở thành chuyện thường ngày. Phụ thuộc hoàn toàn vào kernel để quản lý bộ nhớ đồng nghĩa với việc DBMS gần như mù trước chính dữ liệu của mình — hệ điều hành không phân biệt được đâu là root node của B-Tree (cần ghim chặt trong RAM) và đâu là một document lịch sử ít khi được đụng tới (có thể đẩy xuống đĩa thoải mái).

### MVCC và Bước chuyển sang User-Space

Năm 2014, MongoDB mua lại WiredTiger, đánh dấu một thay đổi triết lý kỹ thuật rõ rệt. WiredTiger từ bỏ cơ chế tự động của `mmap`, tự xây một buffer pool nằm hoàn toàn trong không gian ứng dụng (user-space).

Điều đó cho WiredTiger toàn quyền kiểm soát: nó dùng thuật toán thay thế kết hợp LFU/LRU cùng với nhận thức về phân cấp ngữ nghĩa (semantic hierarchy awareness), đảm bảo cấu trúc B-Tree luôn được giữ trong RAM thay vì phó mặc cho may rủi.

Thay vì dùng khóa cấp database thô bạo, WiredTiger áp dụng Multi-Version Concurrency Control (MVCC). Mỗi thao tác ghi không sửa trực tiếp lên dòng dữ liệu cũ, mà tạo một phiên bản mới trong RAM, nối với các phiên bản trước qua chuỗi con trỏ. Nhờ vậy:
- Đọc không bao giờ chặn ghi.
- Khóa diễn ra ở cấp document, không phải cấp database.
- Tỷ lệ song song hóa $P \approx 1$. Định luật Amdahl giờ cho phép WiredTiger tận dụng gần như trọn vẹn sức mạnh của một máy chủ 128 lõi.

### Nén Dữ liệu Khối

Từ bỏ `mmap` còn mở ra một khả năng khác: nén dữ liệu. Vì `mmap` ánh xạ đĩa và RAM theo tỷ lệ 1:1, dữ liệu trên đĩa không thể nén được — nén vào là offset byte ảo sẽ sai lệch ngay.

Với buffer pool độc lập, dữ liệu trong RAM được giải nén để xử lý, còn khi luồng flush ghi xuống đĩa, WiredTiger nén khối dữ liệu bằng zstd hoặc Snappy.
Hệ số nén $\mathcal{C}_{ratio} = \frac{S_{uncompressed}}{S_{compressed}}$ thường đạt từ 3 đến 5 lần. Lợi ích không chỉ là giảm chi phí lưu trữ trên cloud, mà còn tăng đáng kể thông lượng I/O, vì mỗi lần đọc đĩa giờ mang về nhiều thông tin hơn trên cùng một lượng byte.

```rust
// Mã giả trừu tượng cho thấy sự vượt trội của User-Space Buffer Pool (như WiredTiger)
struct BufferPoolManager {
    page_table: HashMap<LogicalPageId, FrameId>, // Ánh xạ Ảo riêng của DBMS
    frames: Vec<PageFrame>,
    eviction_policy: Box<dyn EvictionAlgorithm>,
    wal_manager: WriteAheadLog,
}

impl BufferPoolManager {
    fn fetch_page(&mut self, page_id: LogicalPageId) -> Result<&mut PageData, DiskError> {
        if let Some(&frame_id) = self.page_table.get(&page_id) {
            // Cache Hit: Cập nhật Semantic LFU
            self.eviction_policy.record_access(frame_id);
            Ok(self.frames[frame_id].get_data_mut())
        } else {
            // Cache Miss: DBMS tự điều phối I/O, không gây Kernel Interrupt / Page Fault treo luồng!
            let frame_id = self.evict_oldest_document_page()?; 
            
            // Sử dụng O_DIRECT + Async I/O (io_uring) để kéo dữ liệu lên
            let compressed_data = disk_manager.async_read_page(page_id)?;
            let raw_data = snappy::decompress(compressed_data);
            
            self.frames[frame_id].fill(raw_data);
            self.page_table.insert(page_id, frame_id);
            Ok(self.frames[frame_id].get_data_mut())
        }
    }
}
```

### Kết luận

MMAPv1 không phải là một thiết kế tồi. Nó hoàn thành đúng vai trò lịch sử của mình: giúp MongoDB ra mắt nhanh, thu hút lập trình viên, và chiếm được thị phần lớn trong kỷ nguyên Web 2.0. Câu chuyện memory-mapped files ở đây là một ví dụ kinh điển về việc một quyết định kỹ thuật đúng ở giai đoạn này lại trở thành gánh nặng ở giai đoạn khác.

Nhưng khi bước vào thế giới dữ liệu lớn, nhiều luồng và kiến trúc microservices, giới hạn toán học về băng thông và tranh chấp khóa là thứ không thể né tránh bằng cách thêm phần cứng. Bằng cách lấy lại quyền kiểm soát ở user-space với WiredTiger — MVCC, buffer pool độc lập, nén dữ liệu khối — MongoDB đã tự tái định hình từ một giải pháp NoSQL sơ khai thành một nền tảng lưu trữ giao dịch đủ trưởng thành cho doanh nghiệp.

Bài học để lại cho kỹ sư hệ thống rất rõ ràng: đừng bao giờ giao phó tri thức ngữ nghĩa cốt lõi của ứng dụng cho một kernel đa dụng — nó không biết, và không cần biết, điều gì thực sự quan trọng với bạn.
