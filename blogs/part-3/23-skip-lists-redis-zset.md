---
seo_title: "Skip List trong Redis ZSET: Kiến trúc và Tối ưu hóa"
seo_description: "Phân tích chuyên sâu cách Redis ZSET dùng skip list kết hợp hash table, cơ chế span tính rank, listpack và ảnh hưởng tới CPU cache, COW khi fork."
focus_keyword: "skip list Redis ZSET"
---

# Kiến trúc Vi mô và Kỹ thuật Tối ưu hóa Skip List trong Redis ZSET

## Executive Summary (Tóm tắt / Overview)

Redis giữ vị trí dẫn đầu trong nhóm cơ sở dữ liệu in-memory không chỉ nhờ tốc độ mà còn nhờ tập cấu trúc dữ liệu đa dạng của nó. Trong số đó, **Sorted Set (ZSET)** là một trong những kiểu dữ liệu thú vị nhất để mổ xẻ. ZSET giữ một tập phần tử không trùng lặp, mỗi phần tử gắn với một điểm số dạng số thực (floating-point), và toàn bộ tập hợp luôn được sắp theo điểm số đó — cho phép truy vấn vị trí, truy vấn theo khoảng điểm số, và tính hạng (rank) gần như tức thời.

Bài viết này đào sâu vào cách Redis cài đặt skip list cho ZSET: sự kết hợp giữa **bảng băm (hash table)** và một biến thể của **skip list** cải tiến. Chúng ta sẽ xem tại sao Redis không chọn cây tìm kiếm cân bằng truyền thống, đi qua phần toán học đứng sau thuật toán skip list, cách cấp phát bộ nhớ vi mô bằng `jemalloc`, cách cấu trúc này cọ xát với CPU cache và hệ điều hành, và cuối cùng là những nơi nó thực sự tỏa sáng trong sản xuất. Mục tiêu là giúp kỹ sư hệ thống hiểu rõ những đánh đổi mà đội ngũ core Redis đã chấp nhận khi thiết kế cấu trúc này.

---

## Core Problem Statement (Vấn đề cốt lõi)

Duy trì một tập dữ liệu động luôn có thứ tự, đồng thời hỗ trợ tìm kiếm, chèn, xóa và truy vấn khoảng với độ phức tạp $\mathcal{O}(\log N)$, là một bài toán quen thuộc trong khoa học máy tính nhưng không hề dễ giải quyết gọn gàng.

Khi thiết kế ZSET, các kỹ sư Redis đã cân nhắc những lựa chọn tiêu chuẩn:
1. **Cây AVL / Cây Đỏ Đen**: đảm bảo $\mathcal{O}(\log N)$ cho mọi thao tác, nhưng cái giá phải trả là việc duy trì cân bằng hình thái cây. Mỗi lần chèn hay xóa kéo theo các phép xoay (rotation) và đổi màu nút. Redis là hệ thống đơn luồng nên không lo tranh chấp khóa, nhưng các phép xoay liên tục vẫn ngốn không ít chu kỳ CPU và gây jitter độ trễ. Thêm vào đó, `ZRANGE` cần duyệt trung thứ tự hoặc phải giữ thêm con trỏ cha, làm phình kích thước mỗi nút.
2. **B-Tree / B+ Tree**: là lựa chọn số một cho lưu trữ trên đĩa vì tận dụng tốt cache line và kích thước trang OS. Nhưng trong môi trường thuần RAM của Redis, việc tách/gộp node B-Tree tạo ra chi phí sao chép bộ nhớ đáng kể, và độ phức tạp cài đặt đi ngược triết lý giữ mã nguồn gọn nhẹ của Redis.

Trước những giới hạn của các mô hình cân bằng tất định, **skip list** — do William Pugh giới thiệu năm 1990 — là lời giải phù hợp. Thay vì cân bằng hình thái nghiêm ngặt, skip list dựa vào **cân bằng xác suất** thông qua một thuật toán ngẫu nhiên. Mã nguồn đơn giản, thao tác nhanh, hỗ trợ tự nhiên truy vấn khoảng nhờ danh sách liên kết ở tầng đáy, và dễ tinh chỉnh — đó là lý do Redis chọn skip list làm hạt nhân cho ZSET.

---

## Deep Technical Knowledge / Internals (Kiến thức kỹ thuật chuyên sâu)

### Cấu trúc lai (Dual Composite Structure): Dict + Skip List

ZSET của Redis không đơn thuần là một skip list. Nó là sự kết hợp đồng bộ của hai cấu trúc:
- **`dict` (Hash Table)**: tra điểm số của bất kỳ phần tử nào trong $\mathcal{O}(1)$.
- **`zskiplist`**: giữ thứ tự tuyến tính của dữ liệu, phục vụ truy vấn rank và truy xuất theo khoảng với $\mathcal{O}(\log N)$.

Đây là ví dụ điển hình của nguyên lý **đánh đổi không gian lấy thời gian**. Redis chấp nhận tốn thêm bộ nhớ cho bảng băm để đổi lấy tốc độ trả lời gần như tức thì cho `ZSCORE`.

### Cấu trúc vi mô: `zskiplistNode` và siêu dữ liệu `span`

Thiết kế skip list gốc của Pugh không giải quyết bài toán đếm rank. Làm sao biết phần tử X đứng thứ mấy trong danh sách hàng triệu phần tử mà chỉ mất $\mathcal{O}(\log N)$? Redis giải bài toán này bằng cách thêm trường `span` vào `zskiplistLevel`.

Cấu trúc nút trong C:

```c
/* Cấu trúc định nghĩa một nút của Skip List */
typedef struct zskiplistNode {
    sds ele;                              // Chuỗi động SDS chứa nội dung phần tử
    double score;                         // Điểm số phân loại (độ chính xác kép IEEE 754)
    struct zskiplistNode *backward;       // Con trỏ lùi (level 0) hỗ trợ ZREVRANGE
    struct zskiplistLevel {
        struct zskiplistNode *forward;    // Con trỏ tiến (chỉ định nút kế tiếp ở cùng cấp độ)
        unsigned long span;               // Khoảng cách (số lượng nút vật lý bị bỏ qua)
    } level[];                            // Flexible Array Member chứa các tầng của nút
} zskiplistNode;

/* Khối điều khiển danh sách */
typedef struct zskiplist {
    struct zskiplistNode *header, *tail;
    unsigned long length;                 // Tổng số lượng phần tử
    int level;                            // Cấp độ cao nhất hiện tại của danh sách
} zskiplist;
```

**`span` hoạt động ra sao?**
`span` ghi lại khoảng cách logic giữa nút hiện tại và nút mà `forward` trỏ tới. Khi thực thi `ZRANK`, thuật toán chỉ cần cộng dồn `span` trên mọi cạnh đi qua từ `header` đến nút đích. Nhờ vậy, một phép đếm vốn cần $\mathcal{O}(N)$ nếu duyệt tuần tự lại được rút gọn thành một phép cộng dồn chạy trong $\mathcal{O}(\log N)$.

```mermaid
graph LR
    subgraph Tầng_3_Đường_Cao_Tốc
        H3[Header] -- "span: 3" --> N3[Node C (Rank 3)]
        N3 -- "span: 2" --> T3[Tail]
    end
    subgraph Tầng_2_Trung_Chuyển
        H2[Header] -- "span: 1" --> N1[Node A (Rank 1)]
        N1 -- "span: 2" --> N3
        N3 -- "span: 2" --> T2[Tail]
    end
    subgraph Tầng_1_Cơ_Sở
        H1[Header] -- "span: 1" --> N1
        N1 -- "span: 1" --> N2[Node B (Rank 2)]
        N2 -- "span: 1" --> N3
        N3 -- "span: 1" --> N4[Node D (Rank 4)]
        N4 -- "span: 1" --> T1[Tail]
    end
    N1 -.->|backward| H1
    N2 -.->|backward| N1
    N3 -.->|backward| N2
    N4 -.->|backward| N3
```

*Biểu đồ Mermaid: cấu trúc nhiều tầng của skip list. Mũi tên liền là con trỏ forward kèm giá trị span. Mũi tên nét đứt là con trỏ backward.*

### Toán học đằng sau quá trình sinh cấp độ (Randomized Level Generation)

Mỗi khi một phần tử mới được thêm vào ZSET, thuật toán phải quyết định nút đó cao bao nhiêu tầng. Redis dùng một quá trình giống thử Bernoulli:
- Mọi nút luôn có ít nhất 1 tầng (Level 1).
- Xác suất leo thêm một tầng là $p = 0.25$.
- Phép "tung đồng xu" này lặp lại đến khi thất bại hoặc chạm trần `ZSKIPLIST_MAXLEVEL` (hiện là 32).

Xác suất một nút có đúng $k$ tầng tuân theo phân phối hình học:
$$ P(L=k) = p^{k-1}(1-p) $$

Kỳ vọng số tầng $\mathbb{E}[L]$ là:
$$ \mathbb{E}[L] = \sum_{k=1}^{32} k \cdot p^{k-1}(1-p) \approx \frac{1}{1-p} $$
Với $p = 0.25$, ta có $\mathbb{E}[L] = 1.33$ — trung bình mỗi nút chỉ có 1.33 tầng. So với chi phí cố định 2 con trỏ của cây nhị phân, thiết kế bất đối xứng này chỉ tốn khoảng $1.33 \times \text{sizeof(pointer)}$ cho mảng `forward` của mỗi nút — một tỷ lệ nén khá ấn tượng cho một cấu trúc chỉ dựa vào xác suất.

### Quản lý Bộ nhớ (Jemalloc) và Flexible Array Member

Redis giao việc cấp phát vùng nhớ heap cho `jemalloc` (hoặc `tcmalloc`) — bộ cấp phát được tối ưu cho khối lượng công việc phân mảnh cao.
Nhìn vào `zskiplistNode`, Redis dùng thủ thuật **C99 Flexible Array Member (FAM)** (`level[]`) đặt ở cuối struct.
Nhờ đó, mỗi nút chỉ cần một lần gọi cấp phát duy nhất:
$$ \text{Size} = \text{sizeof}(zskiplistNode) + (k) \times \text{sizeof}(zskiplistLevel) $$
Khối bộ nhớ trả về liên tục cho toàn bộ phần siêu dữ liệu của nút, gần như loại bỏ **phân mảnh nội vi (internal fragmentation)** trong từng đối tượng.

Mặt trái là cấp phát nút động độc lập làm tăng nguy cơ **phân mảnh ngoại vi (external fragmentation)** khi ZSET liên tục thay đổi kích thước. `jemalloc` xử lý khá tốt vấn đề này, nhưng team vận hành vẫn nên theo dõi `mem_fragmentation_ratio` qua `INFO MEMORY` để phát hiện sớm.

### Sự xung đột với CPU Cache và Branch Predictor

Skip list mạnh về mặt giải thuật nhưng lộ điểm yếu rõ rệt trên phần cứng hiện đại. CPU ngày nay dựa nhiều vào tính địa phương không gian (spatial locality) và cơ chế prefetch phần cứng.

Vì các nút `zskiplistNode` nằm rải rác trên heap, việc duyệt skip list gần như là kiểu "rượt đuổi con trỏ" (pointer chasing) tệ nhất có thể xảy ra. Khi CPU nạp một nút, phần lớn dữ liệu trong cache line 64-byte chứa nút đó bị lãng phí vì địa chỉ nút kế tiếp là ngẫu nhiên. Kết quả là hàng loạt **cache miss** (L1, L2, L3) và **TLB miss**, khiến pipeline CPU phải chờ hàng trăm chu kỳ để bộ điều khiển bộ nhớ lấy dữ liệu từ DRAM.

Đây chính là vấn đề mà Redis giải quyết bằng một cấu trúc trung gian gọi là **listpack**.

### Định dạng thay thế `Listpack`

Để né điểm yếu về cache của skip list khi tập hợp còn nhỏ, Redis áp dụng một cơ chế thích ứng. Dưới các ngưỡng cấu hình:
- `zset-max-listpack-entries` (mặc định 128)
- `zset-max-listpack-value` (mặc định 64 bytes)

Redis không tạo `dict` hay `zskiplist` nào cả. Toàn bộ dữ liệu ZSET được nén vào một chuỗi byte liên tục duy nhất: **listpack** (kế thừa ý tưởng của `ziplist` trước đây).

Trong listpack, cặp phần tử và điểm số nằm sát nhau tuần tự:
`[Header] [Phần_tử_1] [Điểm_1] [Phần_tử_2] [Điểm_2] ... [End]`

Dù thuật toán tìm kiếm rơi từ $\mathcal{O}(\log N)$ xuống $\mathcal{O}(N)$ tuyến tính, tốc độ thực tế của listpack lại nhanh hơn skip list nhiều lần khi tập hợp nhỏ. Lý do khá đơn giản: một listpack vài trăm byte nằm gọn trong một vài dòng L1/L2 cache. Việc duyệt mảng tuần tự tận dụng tối đa hardware prefetcher, khiến việc đọc bộ nhớ gần như nhanh bằng đọc thanh ghi. Các thao tác cấp phát lại dùng `realloc` và `memmove`, vốn khai thác tốt các tập lệnh SIMD của CPU. Chỉ khi dữ liệu vượt ngưỡng, listpack mới chuyển đổi (không thể quay lại) sang skip list.

### Giao tranh với Hệ điều hành: COW (Copy-on-Write) và System Forks

Trong các cơ chế bền vững dữ liệu như snapshot RDB hay AOF rewrite, tiến trình cha của Redis gọi `fork()` để sinh ra một tiến trình con chạy nền. Linux dùng **Copy-on-Write (COW)** trên nền phân trang bộ nhớ ảo cho việc này.

Mọi thứ vẫn ổn cho đến khi có ghi đè vào ZSET đang dùng skip list. Một lần chèn phần tử mới có thể thay đổi `span` và `forward` ở nhiều nút khác nhau. Vì các nút này nằm rải rác, một phép chèn nhỏ cũng có thể gây ra page fault, buộc kernel phải sao chép lại hàng chục trang bộ nhớ 4KB không liên quan trực tiếp đến dữ liệu thay đổi.
Điều này tạo ra những đợt tăng đột biến RAM, có thể khiến node bị OOM Killer của Linux tắt tiến trình. Vì vậy, cấu hình `overcommit_memory = 1` và giữ đủ khoảng trống RAM dự phòng gần như là bắt buộc khi vận hành Redis ở quy mô lớn.

---

## Practical Applications & Case Studies (Ứng dụng thực tế)

Nhờ độ phức tạp $\mathcal{O}(\log N)$ linh hoạt cùng các tối ưu vi mô kể trên, ZSET trở thành lựa chọn mặc định cho nhiều bài toán kiến trúc hệ thống hiện đại.

### Hệ thống Bảng Xếp Hạng Thời Gian Thực (Real-time Streaming Leaderboards)
Trong eSports, gaming hay thương mại điện tử, việc giữ một bảng xếp hạng cập nhật liên tục cho hàng chục triệu người dùng đòi hỏi thông lượng I/O rất cao. Các lệnh như `ZINCRBY` (cập nhật điểm), `ZREVRANGE` (lấy top K), và `ZRANK` (lấy vị trí hiện tại) đều chạy ở độ trễ dưới mili-giây. `ZREVRANGE` hưởng lợi trực tiếp từ `span` — đây chính là chỗ skip list Redis ZSET tỏa sáng nhất trong thực tế.

### Hệ thống Giới Hạn Tần Suất Bằng Cửa Sổ Trượt (Sliding Window Rate Limiter)
Một cách hiệu quả để chống các đợt tấn công ở tầng L7 là dùng sliding window. Lưu timestamp (micro giây) làm `score` và UUID làm giá trị phần tử, hệ thống dùng `ZREMRANGEBYSCORE` để dọn các request cũ ngoài khung thời gian (ví dụ xóa mọi thứ trước 60 giây), rồi dùng `ZCARD` để đếm số request hợp lệ còn lại gần như tức thì.

### Chỉ Mục Chuỗi Dữ Liệu Thời Gian (Time-Series Indexing)
Trong hạ tầng IoT, dữ liệu từ hàng triệu cảm biến đổ về liên tục. Dùng ZSET với nhãn thời gian làm điểm số, việc lấy dữ liệu của một thiết bị từ $T_1$ đến $T_2$ trở thành một câu `ZRANGEBYSCORE` với chi phí $\mathcal{O}(\log N + M)$: skip list nhảy thẳng đến vị trí $T_1$ rồi duyệt tuần tự $\mathcal{O}(M)$ phần tử còn lại.

### Hàng Đợi Nhiệm Vụ Trì Hoãn (Delayed Task Queues)
Dùng `score` làm timestamp thực thi trong tương lai. Worker liên tục poll bằng `ZRANGEBYSCORE -inf <current_timestamp> LIMIT 0 1`. Skip list đảm bảo độ trễ tìm task tiếp theo luôn nằm trong $\Theta(\log N)$, giữ cho vòng lặp worker chạy ổn định.

---

## Lessons Learned (Bài học rút ra)

Từ việc mổ xẻ ZSET, có vài bài học đáng mang sang các hệ thống khác:

1. **Chấp nhận xác suất thay vì cân bằng tuyệt đối**: bằng cách dùng phân phối hình học ngẫu nhiên thay cho các quy tắc cân bằng cây chặt chẽ, Redis có được một cấu trúc dễ bảo trì hơn, ít lỗi hơn, và tránh được các nút thắt do xoay cây liên tục.
2. **Locality thắng độ phức tạp tiệm cận**: không thuật toán nào tiệm cận đẹp đến đâu cũng thắng được vật lý của cache. Sự chuyển đổi sang listpack là minh chứng rõ ràng — khi tập dữ liệu đủ nhỏ, quét tuần tự $\mathcal{O}(N)$ trên vùng nhớ liên tục thường nhanh hơn tìm kiếm $\mathcal{O}(\log N)$ trên các nút rải rác.
3. **Chi phí ẩn của cấu trúc con trỏ**: dù thuật toán in-memory có tốt đến đâu, OS memory paging và COW vẫn là giới hạn cuối cùng. Hiểu rõ điều này giúp đội vận hành kiểm soát rủi ro OOM khi cấu hình tài nguyên cho cụm dữ liệu phân tán.
4. **Kết hợp cấu trúc dữ liệu là một kỹ năng**: đánh đổi một chút bộ nhớ để duy trì song song hai cấu trúc tham chiếu — như `dict` cùng `zskiplist` — có thể phá vỡ giới hạn vốn có của một cấu trúc đơn lẻ, và đó là lý do skip list Redis ZSET vẫn là lựa chọn vững sau nhiều năm.

---
