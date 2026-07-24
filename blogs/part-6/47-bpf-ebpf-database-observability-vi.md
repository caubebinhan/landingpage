---
seo_title: "eBPF trong Database Observability: Giám Sát Ở Mức Kernel"
seo_description: "Tìm hiểu eBPF trong database observability: kiến trúc máy ảo eBPF, eBPF Maps, uprobes/kprobes, và cách giám sát PostgreSQL/MySQL với overhead gần như bằng không."
focus_keyword: "eBPF trong database observability"
---

# 47: BPF và eBPF Trong Database Observability: Siêu Âm Cơ Sở Dữ Liệu Ở Mức Hạt Nhân (Kernel)

## Tóm Tắt & Vấn Đề Cốt Lõi (Core Problem Statement)

Khi hệ thống chuyển sang kiến trúc vi dịch vụ phân tán và xử lý giao dịch cường độ cao, cơ sở dữ liệu gần như luôn là nơi đầu tiên bị nghi ngờ mỗi khi có sự cố. Vấn đề là chẩn đoán chính xác lại không dễ chút nào — đây chính là lúc eBPF trong database observability trở thành công cụ đáng để đầu tư thời gian tìm hiểu.

**Vấn đề cốt lõi:** Cơ sở dữ liệu truyền thống hoạt động như một **"hộp đen"**.
Để biết một truy vấn SQL chậm vì đâu, kỹ sư thường phải bật Slow Query Log, cài thêm agent đo đạc nội bộ (in-band profiling), hoặc dùng các công cụ hệ điều hành quen thuộc như `strace`, `tcpdump`. Mỗi lựa chọn đều có cái giá riêng:
- Bật log hoặc chạy agent in-band có thể kéo overhead lên 30-50%, tức là bạn làm chậm hệ thống trước khi kịp tìm ra nguyên nhân chậm.
- `strace` dựa trên `ptrace`, buộc kernel phải dừng tiến trình database mỗi lần có system call — độ trễ tăng vọt hàng trăm lần.
- `tcpdump` bắt gói tin sinh ra khối lượng dữ liệu khổng lồ và ngốn CPU chỉ để copy dữ liệu từ kernel space lên user space.

Sự xuất hiện của **eBPF (Extended Berkeley Packet Filter)** thay đổi hẳn cách tiếp cận vấn đề này. eBPF cho phép kỹ sư chạy những đoạn mã cực nhẹ ngay bên trong kernel space, theo dõi từng gói tin qua card mạng, từng lời gọi hàm bên trong PostgreSQL, từng khối dữ liệu ghi xuống SSD — với overhead thường dưới 1%.

Bài viết này đi sâu vào kiến trúc máy ảo eBPF, cách thiết lập uprobes/kprobes để "móc" vào mã nguồn database đang chạy mà không cần restart, cùng những bài học thực tế khi vận hành hạ tầng giám sát eBPF ở quy mô trung tâm dữ liệu.

---

## Kiến Trúc Vi Mô Máy Ảo eBPF (eBPF Virtual Machine)

Ban đầu chỉ là công cụ lọc gói tin mạng (cBPF, dùng trong tcpdump), eBPF ngày nay đã trở thành một máy ảo đa năng chạy ngay trong nhân Linux.

### Kiến Trúc Tập Lệnh (ISA) Ánh Xạ Trực Tiếp Phần Cứng
Tập lệnh eBPF được thiết kế để ánh xạ gần như 1-1 với cấu trúc phần cứng của các CPU 64-bit như x86_64 và ARM64.
Bộ xử lý eBPF dùng mô hình 11 thanh ghi ảo 64-bit ($R0$ đến $R10$):
- $R0$: lưu giá trị trả về của hàm.
- $R1$-$R5$: chứa đối số đầu vào khi gọi hàm.
- $R6$-$R9$: các thanh ghi được bảo toàn qua lời gọi hàm (callee-saved).
- $R10$: con trỏ khung xếp (frame pointer), chỉ đọc, dùng để truy cập stack.

Quá trình biên dịch tức thời (JIT) chuyển bytecode eBPF thành mã máy nguyên bản của CPU. Vì kiến trúc thanh ghi của eBPF gần như sao chép nguyên xi x86_64, việc JIT diễn ra rất nhanh và mã sinh ra chạy nhanh không kém gì mã C được biên dịch trực tiếp.

### Kẻ Gác Đền: eBPF Verifier
Kernel là vùng cấm địa. Một lỗi chia cho 0, một con trỏ NULL, hay một vòng lặp vô hạn trong mã eBPF đều có thể khiến cả hệ điều hành sập (kernel panic).
Vì vậy mọi đoạn mã eBPF trước khi được nạp phải đi qua **Verifier**.
Verifier phân tích đồ thị luồng điều khiển (control flow graph) tĩnh để đảm bảo:
1. Không có vòng lặp vô hạn — mã eBPF phải chứng minh được là sẽ kết thúc.
2. Không truy cập bộ nhớ ngoài mảng (out-of-bound access).
3. Không đụng vào các vùng nhớ kernel bị cấm.
4. Stack không vượt quá 512 byte.

Độ phức tạp của thuật toán kiểm tra tiệm cận $\mathcal{O}(N \times E)$, với $N$ là số trạng thái và $E$ là số cạnh trong đồ thị luồng. Chỉ khi Verifier xác nhận an toàn, mã mới được đưa vào JIT.

---

## eBPF Maps: Chia Sẻ Dữ Liệu Tốc Độ Cao (Zero-Copy)

Chương trình eBPF chạy trong kernel thu thập được độ trễ của query — vậy làm sao đẩy con số đó lên user space (Prometheus, Datadog Agent...) mà không tốn chi phí copy? Câu trả lời là **eBPF Maps**.

Đây là các cấu trúc key-value được cấp phát tĩnh trên vùng RAM không phân trang (non-pageable) của kernel. Cả chương trình eBPF lẫn ứng dụng user space đều đọc/ghi được vào map này thông qua file descriptor, bỏ qua hoàn toàn system call sao chép dữ liệu.

### eBPF Hash Map (RCU-Protected)
Dùng để lưu trạng thái. Ví dụ: khi gói tin request đi vào, eBPF ghi `Time_Start` vào hash map với key là `TCP_Tuple (IP, Port)`. Khi gói tin response đi ra, eBPF đọc lại `Time_Start`, tính ra latency rồi lưu kết quả.
Thuật toán hash bên trong dùng cơ chế **Read-Copy-Update (RCU)**, cho phép đọc đồng thời hoàn toàn không khóa (lock-free), đạt tốc độ hàng chục triệu IOPS mà không nghẽn CPU.

### eBPF Ring Buffer
Để đẩy luồng sự kiện (event streaming) từ kernel lên user space — chẳng hạn danh sách các query chậm — eBPF dùng ring buffer.
Đây là hàng đợi vòng MPSC (multi-producer single-consumer) không khóa. Các lõi CPU sinh sự kiện ghi thẳng vào ring buffer, còn user space agent dùng `epoll` để liên tục thu hoạch dữ liệu.
Cơ chế này đảm bảo kernel không bao giờ bị chặn dù user space có quá tải đến đâu.

---

## Các Phương Pháp Đặt Trạm Giám Sát (Probing)

eBPF cần biết database đang làm gì, và nó làm điều đó qua các "hook" gắn vào những điểm mấu chốt của hệ điều hành.

### Network Hook (TC / XDP / Socket Filter)
Nếu không muốn (hoặc không thể) can thiệp vào tiến trình database, bạn có thể giám sát ở cấp mạng.
eBPF gắn được vào tầng Traffic Control (TC) hoặc eXpress Data Path (XDP) — tức là ngay tại driver của card mạng. Bằng cách parse gói tin TCP theo wire protocol của PostgreSQL/MySQL, chương trình eBPF đo được thời gian truy vấn, số lượng truy vấn, tỷ lệ retransmission — mà bản thân database không hề hay biết. Overhead ở đây rất thấp, nhưng lại khó phân tích những câu truy vấn bị phân mảnh trên nhiều gói TCP.

### User Probes (uprobes & uretprobes)
Đây mới là công cụ mạnh nhất. Uprobes cho phép eBPF gắn thẳng vào các hàm C/C++ bên trong mã nguồn database ở user space.
Ví dụ trong PostgreSQL, ta có thể gắn uprobe vào đầu hàm `exec_simple_query()` và uretprobe vào điểm return của hàm đó.

**Nguyên lý ngắt phần cứng (`int3`):**
Khi eBPF gắn uprobe vào `exec_simple_query()`, nó âm thầm thay opcode đầu tiên của hàm bằng lệnh `int3` (breakpoint trap).
Khi CPU chạy đến đây, `int3` ném ra một hardware exception. Hệ điều hành giành quyền điều khiển, nhảy vào kernel, gọi handler eBPF của chúng ta. Sau khi ghi log xong, CPU khôi phục opcode cũ và để database chạy tiếp như chưa có gì xảy ra.

**Điểm cần cẩn trọng với uprobes:**
Mỗi lần nhảy qua uprobe (tạo exception + context switch) tốn khoảng **1.5 đến 3.0 micro-giây** trên CPU hiện đại.
- Gắn vào hàm `exec_simple_query` (chạy khoảng 10.000 lần/giây) chỉ tốn 30ms CPU mỗi giây — tương đương 3% overhead, khá an toàn.
- Gắn vào hàm `btr_search_leaf` (duyệt lá B-Tree, chạy 1.000.000 lần/giây) thì khác hẳn: bạn tạo ra một "cơn bão ngắt", CPU tốn 3 giây xử lý cho mỗi giây thực tế — database gần như treo cứng.
Luôn đo tần suất gọi hàm trước khi quyết định gắn uprobe vào đâu.

### Kernel Probes (kprobes)
Dùng để giám sát tương tác của database với ổ cứng và RAM.
Gắn kprobe vào `vfs_read()` và `vfs_write()`, kết hợp thêm logic nhận dạng, eBPF có thể theo dõi tỷ lệ page cache miss. Nhờ đó kỹ sư nhìn xuyên được xem một câu `SELECT` của MySQL thực chất đã phải đọc xuống ổ NVMe bao nhiêu lần, và mất bao nhiêu nano-giây ở tầng block layer của kernel.

---

## Tối Ưu Hóa Vi Kiến Trúc Hệ Thống (NUMA & Cache)

Khi triển khai eBPF observability trên cụm server 128 core xử lý hàng tỷ giao dịch, thiết kế eBPF Maps buộc phải tôn trọng những giới hạn vật lý của vi xử lý.

### Bài Toán NUMA và Per-CPU Maps
Trong kiến trúc NUMA (Non-Uniform Memory Access), một CPU truy cập RAM thuộc node khác sẽ chịu độ trễ lớn hơn hẳn. Nếu chương trình eBPF trên core 0 và core 64 cùng ghi vào một hash map toàn cục duy nhất, chúng sẽ tranh chấp bộ nhớ và làm bão hòa bus liên kết (UPI/Infinity Fabric).

**Giải pháp** là dùng **Per-CPU Maps**: hệ điều hành cấp cho mỗi lõi CPU một bản sao riêng của map. Chương trình eBPF chạy trên lõi nào chỉ ghi vào phần RAM cục bộ của lõi đó. Kết quả là mọi thao tác ghi trở nên lockless, không tranh chấp, và throughput tiệm cận giới hạn băng thông của L1 cache.

### Bảo Vệ Instruction Cache (I-Cache Thrashing)
L1 instruction cache (L1i) của CPU rất nhỏ, thường chỉ 32KB, và database cần dùng phần lớn không gian đó cho query planner, execution engine.
Nếu chương trình eBPF biên dịch ra quá nhiều mã máy, mỗi lần nó được gọi qua kprobe/uprobe sẽ tràn vào L1i, đẩy mã của database ra ngoài. Khi database chạy tiếp, nó gặp cache miss, phải nạp lại lệnh từ L2/L3 — hiệu năng suy giảm rõ rệt vì stall cycles tăng.

Kỹ sư viết BPF nên giữ mã thật gọn: hợp nhất các điều kiện kiểm tra, cẩn trọng khi unroll loop, và cố giữ toàn bộ chương trình dưới 4KB để nó "sống chung hòa bình" với database trong L1i cache.

---

## Bài Học Rút Ra & Best Practices Cho Kỹ Sư Hệ Thống

1. **Bắt đầu từ kprobes và network, thận trọng với uprobes:** đừng bao giờ thử uprobe lần đầu trên production. Dùng staging cùng `bcc` hoặc `bpftrace` để đo số lần gọi hàm/giây bằng `count()` trước khi bật thu thập latency.
2. **Giữ cấu trúc dữ liệu phẳng (flattening FSM):** eBPF không có heap, không có `malloc`. Nếu cần theo dõi một transaction SQL đi qua nhiều bước, hãy xây máy trạng thái hữu hạn (FSM) ở user space — eBPF chỉ có nhiệm vụ đẩy từng sự kiện lên qua ring buffer, đừng nhồi logic ghép nối phức tạp vào kernel.
3. **Quản lý dung lượng map (bounded capacity):** mọi eBPF map đều có `max_entries`. Nếu hash table đầy, các giao dịch mới sẽ không được giám sát nữa. Agent user-space cần đủ nhanh để dọn các key cũ, nhường chỗ cho dữ liệu mới.
4. **Quyền hạn và bảo mật:** eBPF có thể đọc toàn bộ RAM của kernel, kể cả password hay secret key đang lưu chuyển. Trên production, chỉ cấp quyền cho user thuộc `CAP_BPF` hoặc `CAP_SYS_ADMIN`, và dùng code signing để chặn mọi chương trình eBPF không rõ nguồn gốc.
5. **Tận dụng hệ sinh thái sẵn có:** đừng viết BPF agent từ đầu bằng C. `Cilium`, `Tetragon`, `Pixie`, hoặc Golang với thư viện `cilium/ebpf` đều là lựa chọn tốt hơn. Đẩy dữ liệu ra Prometheus histogram để khai thác các biểu đồ phân vị P99, P99.9 trên Grafana.
