---
seo_title: "HTAP Database Là Gì: Kiến Trúc Delta-Main Giải Thích Chi Tiết"
seo_description: "Tìm hiểu HTAP database vận hành thế nào: kiến trúc Delta-Main, MVCC xuyên hai định dạng lưu trữ, và các kỹ thuật SIMD/JIT giúp hợp nhất OLTP và OLAP."
focus_keyword: "HTAP database"
---

# Sách Trắng Kỹ Thuật #51: Cơ Sở Dữ Liệu HTAP - Kiến Trúc Của Xử Lý Giao Dịch/Phân Tích Hỗn Hợp

## Tóm Tắt Dành Cho Quản Lý
Bài viết này đi sâu vào kiến trúc vi mô của HTAP database — hệ thống xử lý giao dịch/phân tích hỗn hợp (Hybrid Transactional/Analytical Processing). Trong nhiều thập kỷ, ngành cơ sở dữ liệu chấp nhận một ranh giới khá cứng nhắc giữa OLTP và OLAP. HTAP là nỗ lực phá bỏ sự đánh đổi đó. Chúng ta sẽ đi qua phần toán học của cấu trúc bộ nhớ hai định dạng (kiến trúc Delta-Main), sự phức tạp khi MVCC phải hoạt động xuyên suốt hai layout dữ liệu hoàn toàn khác nhau, và những kỹ thuật phần cứng — vector hóa SIMD, biên dịch JIT bằng LLVM — giúp mọi thứ đủ nhanh để có ý nghĩa thực tế.

---

## Mở Đầu: Vì Sao Xử Lý Dữ Liệu Từng Tách Làm Hai Nhánh
Suốt hơn bốn thập kỷ, cơ sở dữ liệu luôn phải chọn một trong hai hướng: hoặc được thiết kế để ghi thật nhanh, hoặc để đọc thật nhanh, hiếm khi làm tốt cả hai cùng lúc.
- **OLTP (Online Transaction Processing):** các hệ thống như PostgreSQL hay MySQL được tối ưu cho truy vấn điểm và cập nhật với tần suất cao, độ trễ thấp. Chúng dùng bố cục hướng hàng (N-ary storage model), nhờ đó toàn bộ một bản ghi — chẳng hạn hồ sơ người dùng — có thể được lấy ra hoặc thay đổi chỉ trong một lần I/O đĩa hoặc một lần đọc cache line.
- **OLAP (Online Analytical Processing):** các hệ thống như Snowflake, ClickHouse, hay BigQuery được tối ưu cho các truy vấn phức tạp, nặng về đọc, chạy lâu để tổng hợp khối lượng dữ liệu khổng lồ. Chúng dựa vào bố cục hướng cột (decomposition storage model), tối đa hóa băng thông bộ nhớ tuần tự và cho phép nén dữ liệu ở mức rất cao.

Vấn đề là doanh nghiệp ngày nay muốn có phân tích thời gian thực ngay trên dữ liệu giao dịch đang chạy. Cách tiếp cận truyền thống để lấp khoảng trống này — trích xuất dữ liệu từ OLTP, biến đổi, rồi nạp (ETL) vào kho OLAP — tạo ra một độ trễ khó chấp nhận, biến "phân tích thời gian thực" thành "phân tích của ngày hôm qua".

HTAP là bước chuyển kiến trúc nhằm hợp nhất cả hai loại workload vào một engine duy nhất, loại bỏ pipeline ETL mà vẫn giữ được sự tách biệt về hiệu năng giữa hai bên. Câu hỏi khó là: làm sao một engine duy nhất có thể phục vụ hai mục tiêu về cơ bản đối lập nhau như vậy?

---

## Bài Toán Cốt Lõi: Sự Không Tương Thích Giữa Hai Mô Hình (Impedance Mismatch)

### Cái Giá Row Store Phải Trả Cho Truy Vấn Phân Tích
Khi một truy vấn OLAP kiểu `SELECT SUM(salary) FROM employees` chạy trên row store, CPU buộc phải nạp toàn bộ hàng vào cache L1/L2 chỉ để đọc mỗi trường `salary`. Điều đó có nghĩa là kéo theo cả những trường không cần thiết như `address` hay `biography`, làm bẩn cache và lãng phí băng thông bộ nhớ. CPU rơi vào trạng thái "đói" dữ liệu hữu ích, và cả hệ thống bị nghẽn tại đó.

### Cái Giá Column Store Phải Trả Cho Giao Dịch
Ngược lại, thử `INSERT` hay `UPDATE` trên column store lại là một cơn ác mộng khác. Một thay đổi logic ở một hàng duy nhất đòi hỏi phải seek và ghi vào hàng chục, thậm chí hàng trăm tệp cột nằm rải rác trên đĩa. I/O ngẫu nhiên rải rác kiểu này phá hỏng hoàn toàn thông lượng giao dịch — một thao tác vốn chỉ mất micro giây biến thành mili giây.

### Chi Phí Của Việc Di Chuyển Dữ Liệu (ETL)
Duy trì hai cơ sở dữ liệu tách biệt đồng nghĩa với việc phải chạy một pipeline ETL, và ETL thì nổi tiếng dễ vỡ, tốn kém tính toán, và tạo ra một khoảng trễ dữ liệu ($\Delta T$) thường dao động từ vài phút đến vài giờ. Trong giao dịch thuật toán, phát hiện gian lận, hay định giá động, một giờ dữ liệu cũ có thể đồng nghĩa với hàng triệu đô la doanh thu bị mất.

---

## Giải Pháp: Kiến Trúc Delta-Main

Các HTAP database như TiDB, SingleStore, hay SAP HANA giải quyết sự không tương thích này bằng một kiến trúc đa định dạng, lưu trữ kép. Dữ liệu về mặt logic nằm trong một schema thống nhất, nhưng về mặt vật lý được lưu đồng thời ở cả dạng hàng lẫn dạng cột, trong cùng một hệ thống.

### Cấu Trúc Bộ Nhớ Delta-Main
Vì việc giữ đồng bộ hai bản sao vật lý cho từng thay đổi ở cấp độ micro giây là quá tốn kém, các engine HTAP dựa vào **kiến trúc delta-main trong bộ nhớ**:
- **Delta Store (hướng hàng):** mọi thao tác ghi đến — `INSERT`, `UPDATE`, `DELETE` — đều đổ vào một buffer hướng hàng trong RAM, không khóa và chịu được mức độ đồng thời cao. Nó hấp thụ các thay đổi giao dịch tốc độ cao, đảm bảo commit dưới một mili giây.
- **Main Store (hướng cột):** phần lớn dữ liệu lịch sử nằm ở đây, được nén mạnh và tối ưu cho đọc.

### Nén Bất Đồng Bộ và Tuple Mover
Để Delta Store hướng hàng không "ăn" hết RAM khả dụng và làm chậm phân tích, một pipeline nền chạy liên tục — thường gọi là Tuple Mover hoặc Compactor. Nó chụp snapshot các hàng bất biến trong Delta Store, chuyển vị chúng sang định dạng cột, áp dụng các phép nén mạnh (run-length encoding, dictionary encoding, bit-packing), rồi đẩy kết quả vào Main Store.

Chi phí của một truy vấn phân tích $C_{olap}$ trên thuộc tính $A$ là tổng của cả hai lượt quét:
$$C_{olap} = C_{column\_scan}(N - \Delta) + C_{row\_scan}(\Delta)$$
Vì $C_{row\_scan}$ đắt hơn đáng kể so với $C_{column\_scan}$ tính trên mỗi tuple, thuật toán của Tuple Mover phải đủ tích cực để giữ $\Delta$ nhỏ, nhưng cũng phải đủ thận trọng để không cướp mất chu kỳ CPU và băng thông bộ nhớ của các luồng OLTP.

```mermaid
graph TD
    subgraph Transactional Workload (OLTP)
        A[Client App] -->|High-Frequency Writes| B(Transaction Manager)
        B -->|Row Mutations| C{Write-Optimized Delta Store (RAM)}
    end
    
    subgraph Analytical Workload (OLAP)
        E[Analytics App] -->|Complex Aggregation| F(Vectorized Execution Engine)
        F -->|Scan & Filter| C
        F -->|High-Speed Sequential Scan| D[(Compressed Columnar Main Store)]
    end

    subgraph The Background Pipeline
        C -.->|Asynchronous Tuple Mover| G(Transposition & Compression)
        G -.->|Immutable Column Chunks| D
    end
```

### Hệ Quả Về Phần Cứng: NUMA, TLB, và Huge Pages
Xây dựng một hệ thống HTAP chạy trong bộ nhớ đòi hỏi phải nắm rất rõ giới hạn của hệ điều hành và phần cứng. Quét hàng terabyte dữ liệu cột với các trang bộ nhớ 4KB tiêu chuẩn sẽ nhanh chóng gây ra hàng loạt TLB miss. Vì vậy các engine HTAP hầu như luôn cấu hình kernel Linux dùng huge page (2MB hoặc 1GB) thay thế, giữ cho TLB luôn thường trú và page walker gần như rảnh rỗi.

Kiến trúc NUMA còn thêm một lớp phức tạp nữa: nếu một luồng trên CPU socket 0 cố quét dữ liệu cột nằm trong RAM của socket 1, dữ liệu đó buộc phải đi qua liên kết QPI/UPI, cộng thêm độ trễ và giới hạn thông lượng. Các bộ cấp phát của HTAP thường ghim các khối cột cụ thể vào đúng NUMA node nơi luồng phân tích đang chạy, để giữ truy cập bộ nhớ càng cục bộ càng tốt.

---

## MVCC Trong Bối Cảnh HTAP

Phục vụ đồng thời ghi thông lượng cao và đọc chạy dài trên cùng một engine đòi hỏi một cơ chế cô lập thực sự vững chắc. Khóa hai giai đoạn (2PL) truyền thống sẽ sụp đổ ở đây: một truy vấn đọc OLAP kéo dài 5 phút sẽ giữ shared lock trên hàng triệu dòng, chặn đứng mọi thao tác ghi OLTP suốt 5 phút đó.

### Cô Lập Snapshot Qua MVCC
HTAP dựa nhất quán vào MVCC. Bên ghi không bao giờ ghi đè dữ liệu tại chỗ — nó thêm một phiên bản mới của tuple. Bên đọc không bao giờ lấy khóa — nó đọc từ một snapshot bất biến, nhất quán về mặt thời gian, tương ứng với timestamp đọc logic riêng của nó ($T_{read}$).

### Giải Quyết Tính Hiển Thị Giữa Hai Định Dạng
Phần phức tạp nằm ở chỗ phải đối chiếu tính hiển thị (visibility) giữa hai định dạng lưu trữ.
1. Truy vấn phân tích quét Columnar Main Store đã được nén mạnh — nhưng một số tuple trong đó có thể đã bị cập nhật hoặc xóa ở Delta Store. Engine dùng Roaring Bitmap hoặc một danh sách vô hiệu hóa để loại các tuple cũ này ra.
2. Đồng thời, truy vấn cũng quét Delta Store, đánh giá một predicate hiển thị cho từng dòng:
   $$Visible(V) = (BeginTS \le T_{read}) \land (EndTS > T_{read})$$
Chỉ phiên bản tuple thỏa điều kiện này mới được gộp vào kết quả cuối cùng.

### Thu Gom Rác và Watermark
Một engine xử lý 10.000 lượt cập nhật mỗi giây cũng đồng thời sinh ra 10.000 phiên bản tuple lỗi thời mỗi giây. Hệ thống duy trì một watermark toàn cục, $T_{watermark}$, đại diện cho giao dịch cũ nhất còn hoạt động. Một tiến trình vacuum chạy nền liên tục quét Delta Store, xóa vật lý bất kỳ phiên bản tuple nào có $EndTS < T_{watermark}$, thu hồi bộ nhớ và giữ mật độ cache ở mức tốt.

---

## Tăng Tốc Thực Thi Truy Vấn Bằng Phần Cứng

Để bù lại chi phí phát sinh từ MVCC và việc đối chiếu hai định dạng, các engine phân tích của HTAP bỏ qua hẳn mô hình xử lý từng tuple một (kiểu Volcano) truyền thống, và tận dụng tối đa vi kiến trúc CPU hiện đại.

### Thực Thi Vector Hóa và SIMD
Thực thi theo vector xử lý dữ liệu theo lô — thường 1024 hoặc 4096 giá trị một lần — giúp khấu hao chi phí gọi hàm ảo và giữ cho các vòng lặp lõi nằm gọn trong L1 instruction cache.
Quan trọng hơn, vector hóa mở khóa các lệnh SIMD như Intel AVX-512. CPU có thể nạp 16 số nguyên 32-bit vào một thanh ghi 512-bit duy nhất và thực hiện một phép toán số học hoặc kiểm tra điều kiện (`salary > 50000`) trên cả 16 giá trị chỉ trong một chu kỳ xung nhịp — về lý thuyết nhanh gấp 16 lần so với thực thi vô hướng (scalar).

### Biên Dịch JIT Bằng LLVM
Một kỹ thuật bổ trợ khác là biên dịch JIT dựa trên LLVM. Thay vì chạy các hàm C++ tổng quát đã biên dịch sẵn, database viết và biên dịch mã máy dành riêng cho từng câu SQL cụ thể ngay tại thời điểm chạy. Biên dịch JIT hợp nhất nhiều toán tử lại với nhau, giữ dữ liệu nằm hoàn toàn trong thanh ghi CPU, và gần như loại bỏ hẳn việc phải quay lại L1 cache.

### Đánh Đổi Giữa Độ Mới Dữ Liệu và Hiệu Năng
Độ mới của dữ liệu trong HTAP không phải chuyện có-hoặc-không — nó là một dải giá trị có thể điều chỉnh. Nếu một nhà phân tích chấp nhận dữ liệu cũ 5 phút ($\Delta T = 5\text{ phút}$), query optimizer hoàn toàn có thể bỏ qua Delta Store hướng hàng và chỉ quét Columnar Main Store bất biến. Tránh được các kiểm tra tính hiển thị tốn CPU và việc gộp hai định dạng, truy vấn chạy nhanh hơn nhiều bậc. Đây là một sự đánh đổi khá rõ ràng: hy sinh độ mới ở mức micro giây, đổi lại thông lượng quét tăng thêm hàng gigabyte mỗi giây.

---

## Bài Học Rút Ra

Nhìn lại quá trình các hệ thống HTAP tiến hóa, có vài điều đáng chú ý.

1. **Hệ thống hợp nhất thực chất là chuyện đánh đổi thông minh, không phải phép màu.** HTAP không xóa bỏ các định luật vật lý — nó đánh đổi dung lượng RAM (lưu hai định dạng) và chu kỳ CPU nền (Tuple Mover) để lấy sự đơn giản trong vận hành (không cần ETL) và độ mới dữ liệu theo thời gian thực.
2. **Độ mới dữ liệu là một dải giá trị, không phải một biến boolean.** Các hệ thống hiện đại nên cho phép ứng dụng tự thương lượng yêu cầu về độ mới của mình. Việc phơi bày giới hạn trễ $\Delta T$ cho query optimizer mở ra nhiều cơ hội tối ưu lớn, và cho thấy tính khả tuần tự nghiêm ngặt thường là một nút thắt không cần thiết đối với các nhu cầu phân tích.
3. **Đồng thiết kế phần cứng-phần mềm là điều bắt buộc, không phải tùy chọn.** Không thể xây một engine xử lý một tỷ dòng mỗi giây chỉ bằng các lớp trừu tượng POSIX chung chung. Để đạt hiệu năng HTAP thực sự, lập trình viên phải viết mã tôn trọng rõ ràng cache line, ranh giới NUMA, kích thước trang TLB, và thanh ghi SIMD. Phần mềm buộc phải uốn theo thực tế vật lý của con chip nó chạy trên đó.

---

## Kết Luận
HTAP database là một bước tiến thực sự trong kỹ thuật dữ liệu hiện đại — phá bỏ bức tường tồn tại hàng thập kỷ giữa hệ thống vận hành và hệ thống phân tích. Bằng cách phối hợp delta store trong bộ nhớ, nén cột chạy nền, MVCC không khóa, và vector hóa SIMD, các engine HTAP cho phép doanh nghiệp chạy mô hình machine learning phức tạp và các phép tổng hợp ngay trên dữ liệu sống, đúng vào thời điểm nó được tạo ra. Khi khối lượng và tốc độ dữ liệu tiếp tục tăng, kiến trúc này — sinh ra từ chính những giới hạn khắc nghiệt của việc tối ưu phần cứng — nhiều khả năng sẽ trở thành chuẩn mực mặc định cho các nền tảng cơ sở dữ liệu trong tương lai.
