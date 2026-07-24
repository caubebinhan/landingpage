---
seo_title: "io_uring vs epoll: I/O bất đồng bộ cho database"
seo_description: "So sánh io_uring và epoll trong kiến trúc cơ sở dữ liệu: vì sao ring buffer, SQPOLL và fixed buffer giúp io_uring vượt qua epoll và Linux AIO."
focus_keyword: "io_uring vs epoll"
---

# 45: `io_uring` vs `epoll`: Kỷ Nguyên Mới Của I/O Bất Đồng Bộ Trong Kiến Trúc Cơ Sở Dữ Liệu

## Tóm Tắt & Vấn Đề Cốt Lõi (Core Problem Statement)

Suốt hơn hai thập kỷ, hệ sinh thái máy chủ Linux dựa vào `epoll` (ra đời từ kernel 2.5) để giải bài toán C10K — phục vụ 10.000 kết nối đồng thời. Cách tiếp cận đó ổn miễn là ổ đĩa còn chậm. Nhưng khi lưu trữ chuyển từ HDD trễ mili-giây sang PCIe NVMe Gen 4/5 trễ micro-giây, `epoll` và mô hình I/O truyền thống bắt đầu lộ ra những điểm yếu mà không cách tinh chỉnh nào bù đắp nổi.

**Vấn đề cốt lõi:** các engine như ScyllaDB, Aerospike, PostgreSQL không còn bị giới hạn bởi tốc độ vật lý của ổ đĩa nữa — cái kìm hãm chúng là **chi phí phần mềm của chính hệ điều hành Linux**. Khi làm I/O bất đồng bộ qua `epoll` hay chuẩn POSIX AIO cũ, CPU cứ phải nhảy qua nhảy lại giữa User Space và Kernel Space, và mỗi syscall — `read()`, `write()`, `epoll_wait()` — tốn hàng nghìn chu kỳ xung nhịp cho việc hoán đổi ngữ cảnh, bảo vệ bảng phân trang (KPTI), và sao chép dữ liệu. Trên phần cứng NVMe tốc độ cao, CPU dành nhiều thời gian "làm thủ tục" với OS hơn là thực sự di chuyển byte dữ liệu.

`io_uring`, xuất hiện từ Linux kernel 5.1, viết lại toàn bộ bức tranh này từ gốc. Hai ring buffer được ánh xạ bộ nhớ (mmap) và chia sẻ trực tiếp giữa User Space và Kernel Space cho phép ứng dụng gửi hàng triệu yêu cầu I/O và nhận kết quả mà không cần một syscall nào trong trường hợp phổ biến.

Bài viết này đi qua cơ chế vi kiến trúc của `io_uring`, đặt nó cạnh những giới hạn thực sự của `epoll` và `linux-aio`, rồi xem các engine cơ sở dữ liệu đang tái cấu trúc thế nào xung quanh công nghệ này — và đó cũng chính là nội dung cốt lõi khi so sánh `io_uring` với `epoll`.

---

## Sự Khủng Hoảng Của `epoll` và Cội Nguồn I/O Bất Đồng Bộ

### Bản Chất của `epoll` (Event-Driven Readiness)

`epoll` sinh ra cho network sockets, và cả mô hình của nó xoay quanh **thông báo sẵn sàng**, không phải thông báo hoàn thành.

Quy trình diễn ra như sau:
1. Gọi `epoll_wait()` và chờ, luồng bị chặn.
2. Card mạng nhận gói tin, kernel đánh thức: "socket 5 có dữ liệu, đọc đi".
3. Ứng dụng gọi tiếp `read(5)` để thực sự sao chép byte từ buffer của kernel sang buffer của mình.

Tổng độ trễ $T_{epoll}$ có thể viết lại thành:
$$T_{epoll} = t_{syscall(epoll\_wait)} + t_{ctx\_switch} + t_{syscall(read)} + t_{vfs\_lookup} + t_{hardware\_io} + t_{interrupt}$$

### Thảm Họa `epoll` Đối Với Lưu Trữ Ổ Đĩa (Disk I/O)

`epoll` làm tốt việc của mình với mạng. Nhưng với ổ đĩa cục bộ thì nó gần như bó tay. Trên Linux, các file thông thường trên ext4 hay xfs luôn báo "sẵn sàng" cho `epoll` — gọi `epoll_wait()` trên một file descriptor thì nó trả về ngay lập tức, lần nào cũng vậy. Nhưng đến khi gọi `read()`, nếu dữ liệu chưa nằm sẵn trong page cache, kernel lặng lẽ chặn luồng của bạn lại cho đến khi dữ liệu về từ đĩa.

Việc chặn âm thầm đó phá vỡ chính tiền đề mà một event loop được xây dựng dựa trên. Chỉ một lệnh `read()` bị nghẽn cũng đủ làm đóng băng hàng nghìn kết nối client khác đang dùng chung luồng NodeJS hay NGINX đó.

### Sự Đổ Vỡ Của Linux AIO (`io_submit`)

Trước `io_uring`, Linux từng có một giao diện I/O ổ đĩa bất đồng bộ là `linux-aio`, thông qua `io_submit` và `io_getevents`. Linus Torvalds từng thẳng thắn chê bai kiến trúc này, và những điểm ông chỉ ra đều có cơ sở:
1. **Chỉ hỗ trợ `O_DIRECT`.** Bạn phải tự quản lý buffer, bỏ qua page cache hoàn toàn; buffered I/O đơn giản là không chạy được qua đường này.
2. **Vẫn bị chặn bởi thao tác metadata.** Ngay cả dùng `O_DIRECT`, `io_submit` vẫn có thể treo nếu filesystem cần cấp phát block hay khóa inode.
3. **Chi phí syscall vẫn còn đó.** Gửi một batch yêu cầu vẫn tốn ít nhất một lệnh gọi `io_submit`.

Đối mặt với những giới hạn đó, PostgreSQL, MySQL, MongoDB đều phải xây thread pool lớn — đẩy các lệnh `read`/`write` chặn sang luồng nền để giữ luồng chính rảnh tay. Cái giá phải trả là việc hoán đổi ngữ cảnh liên tục giữa hàng trăm luồng I/O làm tan nát cache L1/L2 của CPU.

---

## Kiến Trúc Vi Mô Của `io_uring`: Kỳ Quan Shared Memory

Jens Axboe, người duy trì block layer của Linux kernel, thiết kế `io_uring` chính là để loại bỏ những điểm yếu này. Mô hình chuyển từ "sẵn sàng" sang **"hoàn thành"**: bạn giao cho kernel mô tả công việc, kernel chạy trọn vẹn từ đầu đến cuối, rồi ghi kết quả vào vùng nhớ chia sẻ để bạn lấy về.

### Cấu Trúc Dữ Liệu Vòng (Ring Buffers) Phi Khóa

`io_uring` thiết lập hai ring buffer một chiều:
1. **Submission Queue (SQ)** — tác vụ chạy từ user sang kernel, gồm các ô SQE (Submission Queue Entry). Mỗi SQE mô tả một lệnh: "đọc 4KB từ file X vào buffer Y".
2. **Completion Queue (CQ)** — kết quả chạy từ kernel về user, gồm các ô CQE. Mỗi CQE báo kết quả: "lệnh đọc đó xong rồi, mã lỗi 0".

Cả hai mảng này được ánh xạ trực tiếp vào không gian địa chỉ của ứng dụng. Ứng dụng và kernel thực sự đang nhìn vào cùng một vùng RAM.

### Đồng Bộ Hóa Bằng Hàng Rào Bộ Nhớ (Memory Barriers)

Chú ý là không hề có mutex hay spinlock nào ở đây. Đồng bộ hóa hoàn toàn dựa vào con trỏ `Head`/`Tail` nguyên tử cùng các hàng rào bộ nhớ ở cấp phần cứng.

```mermaid
graph TD
    subgraph Kiến Trúc Không Gian Người Dùng (User Space)
        DB[Database Engine Thread]
        SQ_Tail[SQ Tail Pointer (Atomic)]
        CQ_Head[CQ Head Pointer (Atomic)]
        SQ_Ring[Mảng Hàng Đợi Gửi - SQEs mmap]
        CQ_Ring[Mảng Hàng Đợi Hoàn Thành - CQEs mmap]
    end
    
    subgraph Kiến Trúc Không Gian Hạt Nhân (Kernel Space)
        SQ_Head[SQ Head Pointer]
        CQ_Tail[CQ Tail Pointer]
        Kernel_Worker[io_wq Asynchronous Workers]
        Block_Layer[Linux Block Layer & NVMe Driver]
    end

    DB -->|1. Ghi cấu hình I/O (Opcodes, Buffers)| SQ_Ring
    DB -->|2. Cập Nhật 원 tử Smp_store_release()| SQ_Tail
    SQ_Tail -.->|Memory Barrier| SQ_Head
    Kernel_Worker -->|3. Đọc Lệnh I/O| SQ_Ring
    Kernel_Worker -->|4. Điều hướng Lệnh xuống Đĩa| Block_Layer
    Block_Layer -->|5. Tín Hiệu Hoàn Tất (DMA Xong)| Kernel_Worker
    Kernel_Worker -->|6. Ghi Mã Kết Quả (Status 0)| CQ_Ring
    Kernel_Worker -->|7. Cập Nhật 원 tử Smp_store_release()| CQ_Tail
    CQ_Tail -.->|Memory Barrier| CQ_Head
    DB -->|8. Đọc CQE (Smp_load_acquire) phi Syscall| CQ_Ring
```

Giả sử một database muốn gửi 100 tác vụ ghi. Nó chỉ cần ghi dữ liệu vào 100 ô SQE trong bộ nhớ, tăng con trỏ `SQ_Tail`, rồi gọi một lệnh `io_uring_enter()` duy nhất để đánh thức kernel bắt tay vào việc. Thứ từng cần 100 lệnh gọi `write()` giờ chỉ còn một.

### Cảnh Giới Tối Thượng: Triệt Tiêu Syscall Với SQPOLL

Với những ứng dụng đòi hỏi độ trễ thấp nhất có thể, `io_uring` cung cấp cờ `IORING_SETUP_SQPOLL`. Bật cờ này, kernel sẽ sinh ra một luồng riêng, ghim vào một lõi CPU, liên tục thăm dò con trỏ `SQ_Tail` của ứng dụng.

Ngay khi ứng dụng đẩy một yêu cầu mới vào SQ, luồng kernel đó nhìn thấy qua vùng nhớ chia sẻ và lập tức xử lý — không hề có syscall nào tham gia. Cả gửi lẫn nhận kết quả đều không tốn context switch. Lúc này độ trễ I/O ($T_{iouring\_sqpoll}$) gần như chỉ còn bị giới hạn bởi thời gian truyền vật lý:
$$T_{iouring\_sqpoll} = t_{mem\_barrier} + t_{pcie\_dma\_transfer} + t_{nvme\_flash\_prog}$$

---

## Các Vũ Khí Nâng Cao Dành Cho Động Cơ Cơ Sở Dữ Liệu

`io_uring` không dừng lại ở việc gửi I/O cơ bản — nó còn trao cho engine cơ sở dữ liệu một bộ công cụ đầy đủ hơn.

### Đăng Ký Bộ Đệm Cố Định (Fixed Buffers)

Bình thường, mỗi lệnh `read()` hay `write()` buộc kernel phải dựng cấu trúc `iovec`, ánh xạ các trang RAM của caller vào IOMMU để phần cứng có thể DMA trực tiếp, rồi gỡ ánh xạ đó sau khi xong. Việc này tốn kém, và tốn kém mỗi lần thực hiện.

Với `io_uring`, database có thể đăng ký trước một khối RAM lớn, một lần duy nhất. Kernel ghim khối đó và thiết lập sẵn ánh xạ IOMMU. Từ đó về sau, các thao tác `IORING_OP_WRITE_FIXED` chuyển dữ liệu thẳng giữa bộ điều khiển ổ đĩa và vùng nhớ đó — không cần dịch địa chỉ lặp lại.

### Chuỗi Liên Kết Yêu Cầu (Linked SQEs)

Thứ tự thực thi rất quan trọng trong một database. Ví dụ một chuỗi điển hình: ghi dữ liệu (lệnh 1), flush bằng `fsync` (lệnh 2), rồi mới cập nhật metadata (lệnh 3).

Với Linux AIO, phải chờ từng bước hoàn tất mới gửi bước tiếp theo. Với cờ `IOSQE_IO_LINK` của `io_uring`, cả ba lệnh được gửi cùng lúc, và kernel đảm bảo lệnh 2 chỉ chạy nếu lệnh 1 thành công. Cùng một đảm bảo về thứ tự nhưng ít lượt qua lại giữa user và kernel hơn hẳn.

### Hợp Nhất Mạng (Network) và Ổ Đĩa (Storage)

Có lẽ bước đột phá lớn nhất về mặt cấu trúc là `io_uring` xử lý mọi loại thao tác — mạng, file, timeout, `fsync`, `fallocate` — qua cùng một giao diện. Database không còn cần vận hành song song hai cỗ máy: `epoll` cho socket và thread pool cho ổ đĩa.

Một event loop duy nhất trên một lõi CPU có thể vừa nhận kết nối TCP (`IORING_OP_ACCEPT`), vừa đọc HTTP request (`IORING_OP_RECV`), vừa ghi xuống đĩa (`IORING_OP_WRITEV`) — tất cả qua một ring duy nhất.

---

## Hiện Thực Hóa Bằng Mã Nguồn C++ Phi Chặn (Non-Blocking)

Dưới đây là bản phác thảo C++ đơn giản của một storage engine dựng trên `liburing`. Chú ý cách một đối tượng context C++ được gắn vào `user_data` để khớp kết quả trả về với đúng yêu cầu đã gửi.

```cpp
#include <liburing.h>
#include <memory>
#include <cstdint>
#include <iostream>
#include <stdexcept>

// Cấu trúc yêu cầu tùy chỉnh mang theo ngữ cảnh ứng dụng nội bộ
struct IOTransactionContext {
    int file_descriptor;
    uint64_t disk_offset;
    std::unique_ptr<char[]> memory_buffer;
    size_t length;
    uint32_t transaction_id;
};

class UltraFastStorageEngine {
private:
    struct io_uring ring;
    const unsigned int RING_DEPTH = 4096;

public:
    UltraFastStorageEngine() {
        struct io_uring_params params = {};
        // Tối đa hóa tối ưu: Sử dụng SQPOLL loại trừ hoàn toàn syscall
        params.flags |= IORING_SETUP_SQPOLL;
        params.sq_thread_idle = 2000; // Kernel thread ngủ sau 2ms nếu ko có việc
        
        if (io_uring_queue_init_params(RING_DEPTH, &ring, &params) < 0) {
            throw std::runtime_error("Kernel không hỗ trợ hoặc giới hạn ulimit!");
        }
    }

    void submit_async_write(IOTransactionContext* ctx) {
        // Truy xuất khối SQE rỗng từ Ring Buffer chia sẻ 
        struct io_uring_sqe *sqe = io_uring_get_sqe(&ring);
        if (!sqe) {
            // SQ đã đầy, chủ động đệ trình để Kernel tiêu thụ bớt
            io_uring_submit(&ring);
            sqe = io_uring_get_sqe(&ring);
        }
        
        // Cấu hình mã lệnh ghi bất đồng bộ cấp thấp
        io_uring_prep_write(sqe, ctx->file_descriptor, 
                            ctx->memory_buffer.get(), ctx->length, ctx->disk_offset);
                           
        // CRITICAL: Gắn con trỏ đối tượng C++ vào metadata của SQE (64-bit integer)
        io_uring_sqe_set_data(sqe, ctx);
    }

    void reap_completions_lockfree() {
        struct io_uring_cqe *cqe;
        unsigned head;
        unsigned count = 0;

        // Quét qua CQ Ring Buffer hoàn toàn trên không gian RAM cục bộ (No Syscall)
        io_uring_for_each_cqe(&ring, head, cqe) {
            // Ép kiểu ngược lại con trỏ User Data để khôi phục ngữ cảnh
            IOTransactionContext* ctx = static_cast<IOTransactionContext*>(io_uring_cqe_get_data(cqe));
            
            if (cqe->res < 0) {
                std::cerr << "I/O Lỗi tại Transaction " << ctx->transaction_id 
                          << " (Mã lỗi: " << cqe->res << ")\n";
            } else {
                // Xử lý logic nghiệp vụ thành công
                finalize_transaction(ctx, cqe->res);
            }
            count++;
        }
        
        if (count > 0) {
            // Cập nhật Atomic CQ Head báo cho Kernel biết ta đã thu hoạch xong
            io_uring_cq_advance(&ring, count);
        }
    }

private:
    void finalize_transaction(IOTransactionContext* ctx, int bytes_written) {
        // Gửi ACK lại cho client qua mạng, hoặc đánh dấu WAL
        delete ctx; // Dọn dẹp memory
    }
    
    ~UltraFastStorageEngine() {
        io_uring_queue_exit(&ring);
    }
};
```

---

## Bài Học Rút Ra & Best Practices Cho Kiến Trúc Sư Hệ Thống

Sự chuyển giao từ `epoll` sang `io_uring` đang diễn ra thật — Redis 7.0, PostgreSQL 15, NodeJS 20+ đều đã bắt đầu thử nghiệm. Nhưng đây không phải kiểu thay thế cắm vào là chạy; có vài điều cần nắm rõ trước khi cam kết theo hướng này.

1. **Hiểu rõ giới hạn của OS page cache.** Nếu `io_uring` đọc một file mà dữ liệu đã nằm sẵn trong page cache, worker nền `io_wq` của kernel vẫn phải can thiệp, ăn bớt phần lợi thế tốc độ. `io_uring` chỉ thể hiện rõ ưu thế khi kết hợp với **`O_DIRECT`** — nghĩa là database cần tự có buffer pool riêng và sẵn sàng bỏ qua page cache của OS hoàn toàn.
2. **Cẩn trọng với rủi ro bảo mật.** Việc ánh xạ trực tiếp cấu trúc kernel vào user space qua mmap từng gắn liền với nhiều CVE về leo thang đặc quyền. Docker, Kubernetes, SELinux thường vô hiệu hóa `io_uring` mặc định qua seccomp filter — cần whitelist tường minh các syscall đó thì database mới khởi động được.
3. **Quản lý vòng đời buffer cẩn thận.** Buffer bạn đưa cho một SQE phải còn hợp lệ cho đến khi CQE tương ứng trả về. Giải phóng buffer đó quá sớm — chẳng hạn từ một luồng bất cẩn — và kernel sẽ DMA dữ liệu vào vùng nhớ đã bị dùng cho việc khác. Đó là lỗi memory corruption, loại bug có thể kéo sập cả tiến trình.
4. **Cân nhắc polling I/O cho ổ NVMe siêu nhanh.** Trên các ổ có độ trễ dưới 10 micro-giây, chính cơ chế ngắt báo hoàn thành lại trở thành nút thắt — một context switch đã tốn 3-4 micro-giây rồi. `IORING_SETUP_IOPOLL` khiến kernel busy-wait để thăm dò trạng thái SSD thay vì ngủ. Cách này đốt trọn một lõi CPU, nhưng đưa độ trễ xuống gần sát giới hạn vật lý.
5. **Đừng vội bỏ `epoll` cho các web server thông thường.** `io_uring` cũng xử lý được mạng, nhưng với các kết nối HTTP/TCP ngắn hạn điển hình, benchmark không cho thấy khoảng cách đủ lớn so với `epoll` để biện minh cho việc viết lại toàn bộ kiến trúc. `io_uring` rõ ràng thắng thế ở mảng lưu trữ; ở mảng mạng, `epoll` vẫn là lựa chọn vững vàng, đã qua thử thách thời gian.

---
