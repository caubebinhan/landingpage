---
seo_title: "Skip List trong Redis ZSET: Cấu trúc bên trong ra sao?"
seo_description: "Phân tích cách Redis dùng skip list để cài đặt ZSET: metadata span, cấp phát bằng jemalloc, cơ chế listpack, và cách nó sống sót qua fork/copy-on-write."
focus_keyword: "skip list"
---

# Redis xây dựng ZSET trên nền skip list như thế nào

## Executive Summary (Tóm tắt / Overview)

Redis được đánh giá cao không chỉ vì tốc độ thuần túy, mà còn vì cấu trúc dữ liệu của nó khớp rất tốt với nhu cầu thực tế. **Sorted Set (ZSET)** là một ví dụ điển hình: nó giữ một tập hợp các phần tử không trùng lặp, mỗi phần tử gắn với một điểm số kiểu số thực, và luôn tự động sắp xếp theo điểm số đó, để các thao tác tra thứ hạng, truy vấn theo khoảng điểm số, hay lấy phần tử theo vị trí đều nhanh ngay cả khi tập hợp lớn tới hàng triệu phần tử.

Bài viết này đi vào cách Redis thực sự cài đặt ZSET bên dưới. Nói ngắn gọn: một bảng băm kết hợp với một biến thể **skip list** đã được tinh chỉnh. Chúng ta sẽ xem vì sao Redis không chọn cây cân bằng nhị phân, phần toán học đứng sau việc skip list tự quyết định hình dạng của nó, chiến lược cấp phát dựa trên `jemalloc`, cách cấu trúc này tương tác với cache CPU và hệ điều hành, và nó xuất hiện ở đâu trong các hệ thống thực tế.

## Core Problem Statement (Vấn đề cốt lõi)

Duy trì một tập hợp động, có thứ tự, hỗ trợ tìm kiếm, chèn, xóa và truy vấn phạm vi với độ phức tạp $\mathcal{O}(\log N)$ là một bài toán quen thuộc trong khoa học máy tính, và các kỹ sư Redis cũng đã cân nhắc những ứng viên tiêu chuẩn khi thiết kế ZSET:

1. **Cây AVL và cây Đỏ Đen** đảm bảo $\mathcal{O}(\log N)$ ở trường hợp xấu nhất, nhưng giữ cây cân bằng không hề miễn phí. Mỗi lần chèn hoặc xóa đều kéo theo xoay cây và đổi màu. Redis đơn luồng nên tranh chấp khóa không phải vấn đề, nhưng những lần xoay cây đó vẫn ngốn chu kỳ CPU và làm tăng độ trễ dao động. Truy vấn phạm vi (`ZRANGE`) cần hoặc là một lượt duyệt trung thứ tự tốn kém, hoặc thêm con trỏ cha khiến mỗi node phình to hơn.
2. **B-Tree và B+-Tree** là lựa chọn hiển nhiên cho lưu trữ trên đĩa, vì được tinh chỉnh sẵn theo cache line và kích thước trang hệ điều hành. Nhưng với một cấu trúc nằm hoàn toàn trong bộ nhớ, việc tách và gộp node liên tục kéo theo sao chép bộ nhớ, và độ phức tạp khi cài đặt cũng đi ngược lại triết lý giữ mã nguồn đơn giản mà Redis theo đuổi.

Đó là lý do **skip list**, được William Pugh giới thiệu năm 1990, trở thành lựa chọn hấp dẫn hơn. Thay vì ép buộc cân bằng cấu trúc một cách nghiêm ngặt, nó chấp nhận **cân bằng xác suất** dựa trên việc tung đồng xu mỗi khi chèn. Nó dễ cài đặt, nhanh trong thực tế, hỗ trợ tự nhiên các truy vấn phạm vi thông qua danh sách liên kết ở tầng đáy, và dễ tinh chỉnh - đó chính xác là lý do Redis xây ZSET xoay quanh nó.

## Deep Technical Knowledge / Internals (Kiến thức kỹ thuật chuyên sâu)

### Hai cấu trúc phối hợp: dict + skip list

Một ZSET của Redis không đơn thuần là một skip list - đó là một cặp cấu trúc đồng bộ với nhau:
- **`dict`** (bảng băm) trả lời câu hỏi "điểm số của phần tử này là bao nhiêu" trong $\mathcal{O}(1)$.
- **`zskiplist`** giữ thứ tự tuyến tính, để các truy vấn thứ hạng và quét phạm vi chạy trong $\mathcal{O}(\log N)$.

Đây là một sự đánh đổi không gian - thời gian khá rõ ràng: Redis chấp nhận tốn thêm bộ nhớ để duy trì bảng băm, đổi lại `ZSCORE` không cần phải duyệt qua bất cứ thứ gì.

### Bên trong zskiplistNode: trường span

Thiết kế skip list nguyên bản của Pugh chưa bao giờ giải quyết bài toán đếm thứ hạng - không có cách nào rẻ để biết phần tử $X$ đang đứng ở vị trí thứ 1.000.042 trong số vài triệu phần tử. Cách Redis khắc phục là thêm trường `span` vào mỗi `zskiplistLevel`.

Đây là cấu trúc C thực tế:

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

`span` ghi lại có bao nhiêu node ở tầng đáy nằm giữa node hiện tại và node mà con trỏ `forward` của nó trỏ tới. Khi `ZRANK` chạy, nó chỉ đơn giản cộng dồn các giá trị `span` dọc theo đường đi từ `header` đến node đích - biến một phép duyệt vốn dĩ $\mathcal{O}(N)$ thành một phép cộng dồn chạy ở $\mathcal{O}(\log N)$.

```mermaid
graph LR
    subgraph Tang_3_Duong_Cao_Toc
        H3[Header] -- "span: 3" --> N3[Node C (Rank 3)]
        N3 -- "span: 2" --> T3[Tail]
    end
    subgraph Tang_2_Trung_Chuyen
        H2[Header] -- "span: 1" --> N1[Node A (Rank 1)]
        N1 -- "span: 2" --> N3
        N3 -- "span: 2" --> T2[Tail]
    end
    subgraph Tang_1_Co_So
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

*Biểu đồ Mermaid: cấu trúc phân tầng của skip list. Mũi tên liền là con trỏ forward kèm giá trị span. Mũi tên đứt nét là con trỏ backward.*

### Toán học đằng sau việc sinh tầng ngẫu nhiên

Mỗi khi ZSET chèn một phần tử mới, nó phải quyết định node mới sẽ cao bao nhiêu tầng, và Redis làm điều này bằng cách mô phỏng một phép thử Bernoulli:
- Mọi node đều có ít nhất tầng 1.
- Xác suất leo thêm một tầng nữa là $p = 0.25$.
- Việc tung đồng xu này lặp lại cho tới khi thất bại hoặc chạm mức `ZSKIPLIST_MAXLEVEL` (32).

Xác suất một node có đúng $k$ tầng tuân theo phân phối hình học:
$$ P(L=k) = p^{k-1}(1-p) $$

Và số tầng kỳ vọng $\mathbb{E}[L]$ được tính như sau:
$$ \mathbb{E}[L] = \sum_{k=1}^{32} k \cdot p^{k-1}(1-p) \approx \frac{1}{1-p} $$

Thay $p=0.25$ vào, ta được $\mathbb{E}[L] = 1.33$ - trung bình mỗi node chỉ mang 1.33 tầng chi phí con trỏ. So với việc cây nhị phân luôn cố định 2 con trỏ mỗi node, thiết kế bất đối xứng của skip list vượt trội hẳn về mặt bộ nhớ.

### Cấp phát: jemalloc và Flexible Array Member

Redis giao việc cấp phát heap cho `jemalloc` (hoặc `tcmalloc`), được chọn chính vì khả năng xử lý tốt các workload gây phân mảnh.

`zskiplistNode` tận dụng thủ thuật **Flexible Array Member** của C99 - trường `level[]` ở cuối struct - để toàn bộ một node, gồm metadata và tất cả các tầng, được cấp phát chỉ trong một lần gọi:
$$ \text{Size} = \text{sizeof}(zskiplistNode) + (k) \times \text{sizeof}(zskiplistLevel) $$

Nhờ đó bộ nhớ của mỗi node hoàn toàn liên tục và phân mảnh nội bộ (internal fragmentation) bị loại bỏ hoàn toàn. Cái giá phải trả là việc thay đổi kích thước liên tục của cả ZSET theo thời gian sẽ làm tăng phân mảnh ngoại vi (external fragmentation). `jemalloc` xử lý khá tốt vấn đề này, nhưng trên một instance tải cao, vẫn đáng để theo dõi chỉ số `mem_fragmentation_ratio` qua lệnh `INFO MEMORY`.

### Nơi cache CPU phản đòn

Dù thanh lịch về mặt thuật toán, skip list có một điểm yếu thực sự trên phần cứng hiện đại: nó phụ thuộc nặng vào việc rượt đuổi con trỏ (pointer chasing), và đó chính xác là thứ mà các CPU thân thiện với cache lại kém nhất.

Các đối tượng `zskiplistNode` nằm rải rác trên heap, nên việc duyệt qua danh sách nghĩa là nhảy tới những địa chỉ gần như ngẫu nhiên. Mỗi lần nhảy lãng phí phần lớn một cache line 64 byte, và địa chỉ của node tiếp theo không thể đoán trước. Kết quả là một chuỗi liên tục cache miss ở L1/L2/L3 và TLB miss, với pipeline phải chờ hàng trăm chu kỳ để lấy dữ liệu từ DRAM.

Câu trả lời của Redis cho vấn đề này là một cấu trúc riêng gọi là **listpack**.

### Listpack: đánh đổi Big-O lấy sự thân thiện với cache

Khi còn dưới hai ngưỡng cấu hình - `zset-max-listpack-entries` (mặc định 128) và `zset-max-listpack-value` (mặc định 64 byte) - Redis hoàn toàn không tạo bảng băm lẫn skip list, mà lưu cả ZSET như một chuỗi byte liên tục duy nhất, gọi là **listpack** (kế thừa từ `ziplist` trước đó):

`[Header] [Phần_tử_1] [Điểm_1] [Phần_tử_2] [Điểm_2] ... [End]`

Tra cứu trên listpack thoái hóa thành một lượt quét phẳng $\mathcal{O}(N)$ thay vì $\mathcal{O}(\log N)$, nhưng trong thực tế lại thường nhanh hơn hàng chục lần đối với các tập nhỏ. Vài trăm byte liên tục vừa gọn trong L1/L2 cache, việc quét tuần tự đúng là sở trường của bộ prefetch phần cứng, và việc resize được thực hiện qua `realloc`/`memmove`, thứ mà CPU xử lý rất tốt bằng các lệnh vector hóa. Chỉ khi tập hợp vượt quá ngưỡng cấu hình, Redis mới chuyển nó - một cách không thể đảo ngược - thành một skip list đầy đủ.

### Đối đầu với hệ điều hành: fork() và Copy-on-Write

Cơ chế bền vững của Redis (RDB snapshot, AOF rewrite) dựa vào `fork()` để tạo một tiến trình nền, và Linux xử lý việc chia sẻ bộ nhớ phát sinh bằng copy-on-write ở cấp độ trang.

Điều này ổn cho tới khi các thao tác ghi bắt đầu chạm vào một ZSET dùng skip list. Một lần chèn duy nhất đụng tới các trường `span` và `forward` nằm rải rác trên hàng chục node không liên quan, và vì các node này phân tán về mặt vật lý, một lần chèn đó có thể kích hoạt page fault trên hàng chục trang 4KB riêng biệt - mỗi trang giờ cần một bản sao thật sự. Nếu việc này lặp lại đủ nhiều, nó có thể tạo ra một đợt tăng vọt sử dụng bộ nhớ đủ lớn để mời gọi oom killer của Linux. Đó là lý do vì sao `overcommit_memory = 1` và một khoảng RAM dự phòng hợp lý không phải là tùy chọn trong môi trường production.

## Practical Applications & Case Studies (Ứng dụng thực tế)

### Bảng xếp hạng thời gian thực

Các nền tảng game và thương mại điện tử dựa vào ZSET cho bảng xếp hạng liên tục cập nhật trên hàng chục triệu người dùng. `ZINCRBY` để cập nhật điểm, `ZREVRANGE` để lấy top K, và `ZRANK` để lấy vị trí hiện tại của người dùng - tất cả đều chạy dưới mili giây, và tốc độ của `ZREVRANGE` gần như hoàn toàn đến từ việc metadata `span` giải quyết bài toán tính thứ hạng với chi phí rẻ.

### Rate limiter kiểu sliding window

Một cách làm phổ biến cho rate limiting ở tầng L7 là lưu timestamp theo micro giây làm điểm số và UUID làm member. `ZREMRANGEBYSCORE` dọn sạch mọi thứ nằm ngoài cửa sổ hiện tại (ví dụ, cũ hơn 60 giây), và `ZCARD` cho ngay số lượng còn lại - một cách rẻ và hiệu quả để xây dựng rate limiter kiểu sliding window.

### Đánh chỉ mục dữ liệu chuỗi thời gian

Các nền tảng IoT thu thập dữ liệu đo lường từ hàng triệu cảm biến có thể dùng timestamp làm điểm số. Lấy dữ liệu của một thiết bị từ $T_1$ đến $T_2$ trở thành một truy vấn `ZRANGEBYSCORE` với độ phức tạp $\mathcal{O}(\log N + M)$ - skip list nhảy thẳng tới $T_1$, rồi quét tuyến tính qua $M$ bản ghi khớp điều kiện.

### Hàng đợi tác vụ trì hoãn

Dùng điểm số làm timestamp thực thi trong tương lai, các worker liên tục poll bằng `ZRANGEBYSCORE -inf <current_timestamp> LIMIT 0 1`. Skip list giữ độ trễ của lần tra cứu này ở $\Theta(\log N)$ bất kể kích thước hàng đợi, giúp toàn bộ nhóm worker luôn phản hồi nhanh.

## Lessons Learned (Bài học rút ra)

1. **Cân bằng xác suất thường là đủ tốt, và dễ sống chung hơn.** Đánh đổi việc cân bằng toán học nghiêm ngặt lấy một phân phối hình học ngẫu nhiên giúp Redis có được một cấu trúc dễ bảo trì hơn, độ trễ ổn định hơn, và tránh được chi phí điều phối mà một cây cân bằng sẽ cần tới.
2. **Tính cục bộ của cache thường thắng độ phức tạp tiệm cận thông minh hơn người ta tưởng.** Listpack là bằng chứng rõ ràng nhất: khi tập dữ liệu còn đủ nhỏ, một lượt quét tuần tự $\mathcal{O}(N)$ đơn giản trên bộ nhớ liên tục vẫn thường xuyên vượt qua một phép tìm kiếm $\mathcal{O}(\log N)$ "thông minh hơn" nhưng rải rác trên heap - cho tới khi dữ liệu đủ lớn để phần toán học giành lại lợi thế.
3. **Cấu trúc dày đặc con trỏ mang theo chi phí ẩn ở cấp hệ điều hành.** Dù thuật toán in-memory có tốt đến đâu, page fault và copy-on-write trong lúc fork mới là giới hạn thực sự - đáng để hiểu rõ trước khi cấp phát RAM cho một cụm Redis lớn.
4. **Kết hợp hai cấu trúc đôi khi rất đáng giá.** Ghép `dict` với `zskiplist` tốn thêm bộ nhớ, nhưng đổi lại có được tra cứu điểm số ở thời gian hằng số trong khi vẫn giữ nguyên khả năng truy cập theo thứ tự - một ví dụ điển hình cho việc lưu trùng trạng thái lại là lựa chọn đúng đắn.

---
