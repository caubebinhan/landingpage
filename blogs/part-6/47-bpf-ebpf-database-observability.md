---
seo_title: "eBPF Database Observability: Giám Sát Database Ở Tầng Kernel"
seo_description: "Phân tích cách dùng eBPF database observability để theo dõi PostgreSQL/MySQL ở mức kernel: eBPF Maps, uprobes, kprobes và tối ưu NUMA với overhead gần bằng 0."
focus_keyword: "eBPF database observability"
---

# 47: BPF và eBPF Trong Database Observability: Siêu Âm Cơ Sở Dữ Liệu Ở Mức Hạt Nhân (Kernel)

## Tóm Tắt & Vấn Đề Cốt Lõi (Core Problem Statement)

Bất kỳ ai từng trực đêm vì một query đột nhiên chậm gấp mười lần bình thường đều hiểu cảm giác này: database không tự nói cho bạn biết nó đang làm gì bên trong. Đó là lý do eBPF database observability đang được nhắc đến nhiều — nó cho phép nhìn vào bên trong mà gần như không phải trả giá về hiệu năng.

**Vấn đề cốt lõi:** database truyền thống vận hành như một **hộp đen**.
Muốn biết một câu SQL chậm vì đâu, kỹ sư thường phải bật Slow Query Log, gắn thêm agent profiling chạy song song trong tiến trình (in-band), hoặc dùng những công cụ hệ điều hành đã cũ như `strace`, `tcpdump`. Cả ba cách đều có cái giá của nó:
- Bật log hoặc chạy in-band agent có thể ngốn 30-50% hiệu năng — hệ thống sập trước khi bạn tìm ra vấn đề.
- `strace` chạy trên nền `ptrace`, buộc kernel dừng hẳn tiến trình database ở mỗi system call, khiến độ trễ tăng lên hàng trăm lần.
- `tcpdump` bắt gói tin tạo ra lượng dữ liệu khổng lồ và tốn nhiều CPU chỉ để copy dữ liệu từ kernel space sang user space.

**eBPF (Extended Berkeley Packet Filter)** giải quyết bài toán này theo một cách khác hẳn. Nó cho phép chạy các đoạn mã cực nhẹ ngay trong kernel space, quan sát từng gói tin qua NIC, từng lời gọi hàm bên trong PostgreSQL, từng khối dữ liệu ghi xuống SSD, với overhead thường dưới 1%.

Bài viết này sẽ đi vào kiến trúc máy ảo eBPF, cách gắn uprobes/kprobes để quan sát mã nguồn database đang chạy mà không cần restart, và những bài học thực chiến khi vận hành hạ tầng eBPF ở quy mô data center.

---

## Kiến Trúc Vi Mô Máy Ảo eBPF (eBPF Virtual Machine)

Xuất phát điểm của eBPF chỉ là bộ lọc gói tin mạng (cBPF, dùng trong tcpdump thời kỳ đầu), nhưng ngày nay nó đã trở thành một máy ảo đa năng nằm ngay trong nhân Linux.

### Kiến Trúc Tập Lệnh (ISA) Ánh Xạ Trực Tiếp Phần Cứng
Tập lệnh eBPF hiện đại được thiết kế ánh xạ gần như trực tiếp với phần cứng CPU 64-bit (x86_64, ARM64).
Bộ xử lý eBPF dùng mô hình 11 thanh ghi ảo 64-bit, từ $R0$ đến $R10$:
- $R0$: giá trị trả về của hàm.
- $R1$-$R5$: đối số truyền vào khi gọi hàm.
- $R6$-$R9$: thanh ghi callee-saved, được bảo toàn qua các lời gọi hàm.
- $R10$: frame pointer chỉ đọc, dùng truy cập stack.

Bộ biên dịch JIT (Just-In-Time) chuyển bytecode eBPF thành mã máy gốc của CPU. Vì kiến trúc thanh ghi eBPF gần như trùng khớp với x86_64, quá trình JIT diễn ra cực nhanh và mã sinh ra chạy nhanh tương đương mã C biên dịch trực tiếp.

### Verifier: Người Gác Cổng Của Kernel
Kernel là vùng đất không được phép sai sót. Một lỗi chia cho 0, một con trỏ NULL, hay một vòng lặp vô hạn trong mã eBPF đều có thể gây kernel panic — sập cả hệ điều hành.
Vì thế mọi đoạn mã eBPF phải qua cửa **Verifier** trước khi được nạp.
Verifier phân tích tĩnh đồ thị luồng điều khiển (control flow graph) để đảm bảo:
1. Không có vòng lặp vô hạn — mã phải chứng minh được sẽ kết thúc.
2. Không truy cập bộ nhớ ngoài phạm vi mảng.
3. Không đụng đến các vùng nhớ kernel bị cấm truy cập.
4. Stack không vượt quá 512 byte.

Độ phức tạp thuật toán của bước kiểm tra này tiệm cận $\mathcal{O}(N \times E)$, với $N$ là số trạng thái và $E$ là số cạnh trong đồ thị luồng. Chỉ khi Verifier xác nhận "an toàn", mã mới được chuyển sang JIT.

---

## eBPF Maps: Chia Sẻ Dữ Liệu Tốc Độ Cao (Zero-Copy)

Một chương trình eBPF chạy trong kernel đo được độ trễ của query — nhưng làm sao đưa con số đó lên user space (Prometheus, Datadog Agent...) mà không phải trả giá copy dữ liệu? Câu trả lời nằm ở **eBPF Maps**.

Đây là các cấu trúc key-value được cấp phát tĩnh trong vùng RAM không phân trang của kernel. Cả chương trình eBPF và ứng dụng user space đều đọc/ghi trực tiếp qua file descriptor, loại bỏ hoàn toàn system call sao chép dữ liệu.

### eBPF Hash Map (RCU-Protected)
Dùng để giữ trạng thái. Chẳng hạn, khi gói request đi vào, eBPF ghi `Time_Start` vào hash map với key là `TCP_Tuple (IP, Port)`; khi gói response đi ra, nó đọc lại `Time_Start`, tính latency rồi lưu kết quả.
Cơ chế hash bên trong dùng **Read-Copy-Update (RCU)**, cho phép đọc đồng thời hoàn toàn lock-free, đạt tốc độ hàng chục triệu IOPS mà không làm nghẽn CPU.

### eBPF Ring Buffer
Để đẩy dòng sự kiện (event streaming) từ kernel lên user space — ví dụ danh sách các query chậm — eBPF dùng ring buffer.
Đây là hàng đợi vòng MPSC (multi-producer single-consumer) không khóa: các lõi CPU sinh ra sự kiện ghi thẳng vào ring buffer, còn agent ở user space dùng `epoll` để liên tục thu hoạch. Nhờ vậy kernel không bao giờ bị chặn dù user space có bị quá tải.

---

## Các Phương Pháp Đặt Trạm Giám Sát (Probing)

Muốn biết database đang làm gì, eBPF cần các "hook" gắn vào những điểm then chốt của hệ điều hành.

### Network Hook (TC / XDP / Socket Filter)
Nếu không muốn hoặc không thể can thiệp vào chính tiến trình database, bạn vẫn giám sát được ở tầng mạng.
eBPF gắn được vào Traffic Control (TC) hoặc eXpress Data Path (XDP) — ngay tại driver của card mạng. Bằng cách parse gói TCP theo wire protocol của PostgreSQL/MySQL, chương trình eBPF đo được thời gian truy vấn, số lượng truy vấn, tỷ lệ retransmission, mà bản thân database hoàn toàn không hay biết. Overhead ở cách này rất thấp, nhưng khó phân tích các truy vấn bị phân mảnh trên nhiều gói TCP.

### User Probes (uprobes & uretprobes)
Đây là công cụ mạnh nhất trong bộ công cụ. Uprobes cho phép eBPF gắn thẳng vào các hàm C/C++ bên trong mã nguồn database ở user space.
Ví dụ trong PostgreSQL, ta gắn uprobe vào đầu hàm `exec_simple_query()` và uretprobe vào điểm return của nó.

**Cơ chế ngắt phần cứng (`int3`):**
Khi eBPF gắn uprobe vào `exec_simple_query()`, nó thay thế opcode đầu tiên của hàm bằng lệnh `int3` (breakpoint trap).
Khi CPU chạy tới đó, `int3` ném ra hardware exception. Hệ điều hành giành quyền điều khiển, nhảy vào kernel, gọi handler của eBPF. Sau khi ghi log xong, CPU khôi phục opcode gốc và database chạy tiếp bình thường.

**Điểm cần lưu ý khi dùng uprobes:**
Mỗi lần đi qua uprobe (tạo exception cộng context switch) tốn khoảng **1.5-3.0 micro-giây** trên CPU hiện đại.
- Gắn vào `exec_simple_query` (chạy khoảng 10.000 lần/giây) chỉ tốn 30ms CPU mỗi giây, tương đương 3% overhead — chấp nhận được.
- Gắn vào `btr_search_leaf` (duyệt lá B-Tree, chạy tới 1 triệu lần/giây) thì khác hẳn: CPU tốn tới 3 giây xử lý cho mỗi giây thực tế, database gần như đứng hình.
Luôn đo tần suất gọi hàm (invocation frequency) trước khi quyết định gắn uprobe ở đâu.

### Kernel Probes (kprobes)
Dùng để quan sát tương tác của database với ổ đĩa và RAM.
Gắn kprobe vào `vfs_read()` và `vfs_write()`, kết hợp thêm logic nhận dạng ngữ cảnh, eBPF theo dõi được tỷ lệ page cache miss. Từ đó kỹ sư nhìn thấy được một câu `SELECT` của MySQL thực chất đã phải đọc xuống ổ NVMe bao nhiêu lần, và mất bao nhiêu nano-giây ở tầng block layer.

---

## Tối Ưu Hóa Vi Kiến Trúc Hệ Thống (NUMA & Cache)

Khi triển khai eBPF observability trên cụm server 128 core xử lý hàng tỷ giao dịch mỗi ngày, thiết kế eBPF Maps buộc phải tôn trọng những giới hạn vật lý của vi xử lý — nếu không, chính công cụ giám sát lại trở thành nguồn gây nghẽn.

### Bài Toán NUMA và Per-CPU Maps
Trong kiến trúc NUMA (Non-Uniform Memory Access), một CPU truy cập RAM thuộc node khác chịu độ trễ cao hơn hẳn truy cập cục bộ. Nếu chương trình eBPF chạy trên core 0 và core 64 cùng ghi vào một hash map toàn cục duy nhất, chúng sẽ tranh chấp bộ nhớ và làm bão hòa bus liên kết (UPI/Infinity Fabric).

**Giải pháp** là **Per-CPU Maps**: hệ điều hành cấp cho mỗi lõi CPU vật lý một bản sao riêng của map. Chương trình eBPF chạy trên lõi nào chỉ ghi vào phần RAM cục bộ của lõi đó. Kết quả là các thao tác ghi trở nên lockless và không tranh chấp, throughput tiệm cận giới hạn băng thông L1 cache.

### Bảo Vệ Instruction Cache (I-Cache Thrashing)
L1 instruction cache (L1i) của CPU khá nhỏ — thường chỉ 32KB — và phần lớn không gian đó cần dành cho query planner, execution engine của database.
Nếu chương trình eBPF biên dịch ra quá nhiều mã máy, mỗi lần gọi qua kprobe/uprobe nó sẽ tràn vào L1i, đẩy mã của database ra ngoài. Khi database chạy tiếp, nó gặp cache miss, phải nạp lại lệnh từ L2/L3 — hiệu năng giảm rõ rệt vì số stall cycles tăng lên.

Kỹ sư viết BPF nên giữ mã thật gọn gàng: hợp nhất các phép kiểm tra, cẩn trọng khi unroll loop, và cố giữ toàn bộ chương trình dưới 4KB để nó tồn tại yên ổn cùng database trong L1i cache.

---

## Bài Học Rút Ra & Best Practices Cho Kỹ Sư Hệ Thống

1. **Bắt đầu từ kprobes và network, thận trọng với uprobes:** đừng bao giờ thử nghiệm uprobe lần đầu trên production. Dùng staging cùng `bcc` hoặc `bpftrace` để đo số lần gọi hàm mỗi giây bằng `count()` trước khi bật thu thập latency.
2. **Giữ cấu trúc dữ liệu phẳng (flattening FSM):** eBPF không có heap, không `malloc`. Nếu cần theo dõi một transaction SQL đi qua nhiều bước, xây máy trạng thái hữu hạn ở user space — eBPF chỉ đẩy từng sự kiện lên qua ring buffer, đừng nhồi logic ghép nối phức tạp vào kernel.
3. **Quản lý dung lượng map (bounded capacity):** mọi eBPF map đều có `max_entries`. Nếu hash table đầy, các giao dịch mới sẽ không được ghi nhận. Agent user-space cần đủ nhanh để dọn dẹp các key cũ, nhường chỗ cho dữ liệu mới.
4. **Quyền hạn và bảo mật:** eBPF có thể đọc toàn bộ RAM của kernel, kể cả password hay secret key đang lưu chuyển trong bộ nhớ. Trên production chỉ nên cấp quyền cho user thuộc `CAP_BPF` hoặc `CAP_SYS_ADMIN`, và dùng code signing để chặn chương trình eBPF không rõ nguồn gốc.
5. **Tận dụng hệ sinh thái sẵn có:** đừng tự viết BPF agent từ đầu bằng C. `Cilium`, `Tetragon`, `Pixie`, hoặc Golang với thư viện `cilium/ebpf` là những lựa chọn thực tế hơn nhiều. Đẩy dữ liệu ra Prometheus histogram để khai thác biểu đồ phân vị P99, P99.9 trên Grafana.
