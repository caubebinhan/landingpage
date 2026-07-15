<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'lock_granularity_1784014214746.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Lock Granularity',
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];
    $attach_id = wp_insert_attachment($attachment, $destination);
    $attach_data = wp_generate_attachment_metadata($attach_id, $destination);
    wp_update_attachment_metadata($attach_id, $attach_data);
} else {
    $attach_id = 0;
}

// 2. Setup Categories and Tags
function setup_term($name, $taxonomy, $lang) {
    $term = get_term_by('name', $name, $taxonomy);
    if (!$term) {
        $term_info = wp_insert_term($name, $taxonomy);
        $term_id = $term_info['term_id'];
    } else {
        $term_id = $term->term_id;
    }
    pll_set_term_language($term_id, $lang);
    return $term_id;
}

$cat_en = setup_term('Database Concurrency', 'category', 'en');
$cat_vi = setup_term('Đồng Thời Database', 'category', 'vi');
$cat_ja = setup_term('データベースの同時実行', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> Jim Gray\'s 1975 paper on "Granularity of Locks" is the foundational text for database concurrency control. It invented the concept of <em>Intent Locks</em>, which allow databases to manage simultaneous reads and writes without grinding to a halt. Jim Gray later won the Turing Award for this and related work on transaction processing.</li>
<li><strong>The Core Problem:</strong> If 1,000 users are trying to buy tickets on a website simultaneously, the database must "lock" data to prevent double-booking. If it locks the entire database (Coarse Granularity), it is 100% safe but extremely slow (only 1 transaction per second). If it locks only a single row (Fine Granularity), it is fast, but causes massive overhead as the system tries to track millions of tiny locks, leading to CPU exhaustion and deadlocks.</li>
<li><strong>The Solution:</strong> Gray invented a Hierarchical Locking System. He introduced the "Intent Lock" (e.g., Intent to Share (IS) or Intent to eXclusive (IX)). Before a transaction can lock a row, it must place an "Intent Lock" on the parent Table. This acts as a warning sign, allowing the database to quickly check for conflicts without scanning millions of individual row locks.</li>
<li><strong>Modern Reality:</strong> Every modern relational database (PostgreSQL, MySQL InnoDB, SQL Server) uses Gray\'s multi-granularity locking protocols. Features like "Lock Escalation" (automatically upgrading a million row locks into one table lock to save memory) are direct implementations of this paper.</li>
</ul>

<h2>Historical Context & The Catalyst: The Concurrency Nightmare</h2>
<p>In the early 1970s, as IBM was building System R (the first SQL database), they faced a catastrophic problem: Concurrency. A database is not just a filing cabinet; it is a highly trafficked intersection where thousands of transactions are trying to read and write the exact same data simultaneously.</p>

<p>Consider a banking system. Transaction A wants to transfer $100 from Account 1 to Account 2. At the exact same microsecond, Transaction B wants to read the total balance of all accounts in the bank. If Transaction B reads the data while Transaction A is halfway done (money deducted from Account 1, but not yet added to Account 2), the total balance will be incorrect. The bank will lose $100 into thin air.</p>

<p>To fix this, we use <strong>Locks</strong>. A lock guarantees isolation.</p>
<ul>
<li><strong>Table Lock (Coarse Granularity):</strong> Transaction A locks the entire <code>Accounts</code> table, does the transfer, and unlocks it. This guarantees mathematical perfection. However, it means Transaction C, which just wants to update Account 999 (completely unrelated to A and B), must wait in line. The database becomes a single-lane road. Concurrency drops to zero.</li>
<li><strong>Row Lock (Fine Granularity):</strong> Transaction A locks <em>only</em> Row 1 and Row 2. Transaction C can instantly update Row 999. Concurrency is incredible! But what about Transaction B, which wants to read the <em>entire table</em>? To ensure safety, Transaction B must ask the database: "Is <em>any</em> row in this table currently locked by anyone else?" The database must now scan all 10 million row locks to answer that question, causing massive CPU overhead.</li>
</ul>

<p>Jim Gray realized that both extremes were fatal. The database needed a way to support both coarse and fine locks simultaneously, without causing a performance collapse.</p>

<h2>The Academic Breakthrough: The Intent Lock</h2>
<p>Gray\'s solution was to arrange the database into a hierarchy: Database -> Table -> Page -> Row. He then invented a completely new type of lock: The <strong>Intent Lock</strong>.</p>

<p>An Intent Lock is not an actual lock on the data. It is a "warning sign" placed higher up in the hierarchy to announce your intentions lower down. Gray defined three new locks:</p>
<ol>
<li><strong>IS (Intent Share):</strong> "I intend to place a Shared (Read) lock on a specific row inside this table."</li>
<li><strong>IX (Intent Exclusive):</strong> "I intend to place an Exclusive (Write) lock on a specific row inside this table."</li>
<li><strong>SIX (Shared with Intent Exclusive):</strong> "I am reading this entire table (Shared lock), but I also intend to write to a few specific rows inside it (IX)."</li>
</ol>

<h3>How Intent Locks Solve the Concurrency Problem</h3>
<p>Let\'s replay the banking scenario using Gray\'s protocol:</p>
<p>Transaction A wants to update Row 1. It cannot just lock Row 1. The protocol forces it to start at the top. Transaction A places an <strong>IX (Intent Exclusive)</strong> lock on the <code>Accounts</code> Table, and then places an <strong>X (Exclusive)</strong> lock on Row 1.</p>

<p>Now, Transaction B comes along and wants to read the entire table. It asks the database for an <strong>S (Shared)</strong> lock on the <code>Accounts</code> Table. The database looks at the table, sees Transaction A\'s "IX" warning sign, and instantly knows: "Ah! Someone is writing inside this table. The S and IX locks are incompatible. Transaction B, you must wait."</p>

<p>The magic is what the database <em>didn\'t</em> do. It didn\'t have to scan 10 million row locks to find a conflict. The conflict was caught instantly at the table level in $O(1)$ time.</p>
<p>Meanwhile, Transaction C comes along and wants to update Row 999. It asks for an <strong>IX</strong> lock on the table. IX is compatible with another IX! The database grants it. Transaction C then places an <strong>X</strong> lock on Row 999. Massive concurrency is achieved, safely.</p>

<h2>Deep Architectural Walkthrough: Lock Escalation and Deadlocks</h2>
<p>Gray\'s hierarchy solved the discovery problem, but it introduced a new resource problem: <strong>Lock Memory Overhead</strong>.</p>

<p>A lock is a data structure in RAM. If a transaction decides to update 5 million rows, it must request 5 million individual Row Locks. This could consume Gigabytes of RAM just for the lock manager, crashing the database.</p>

<p>To solve this, Gray formalized <strong>Lock Escalation</strong>. When a transaction acquires too many fine-grained locks (e.g., more than 5,000 row locks on a single table), the database\'s Lock Manager dynamically intervenes. It converts those 5,000 Row Locks into a single Table Lock (escalation). This frees up massive amounts of RAM, at the temporary cost of blocking other users from accessing the table.</p>

<h3>The Deadlock Problem</h3>
<p>Multi-granularity locking drastically increases the surface area for <strong>Deadlocks</strong>. Transaction A locks Row 1 and needs Row 2. Transaction B locks Row 2 and needs Row 1. They wait for each other forever.</p>
<p>Because of Gray\'s work, modern databases must run a background "Deadlock Detector" thread. It constantly builds a "Waits-For Graph" (a directed graph of which transaction is waiting for which lock). If it detects a cycle in the graph, it ruthlessly murders (aborts) the youngest transaction, rolls back its changes, and allows the older transaction to proceed.</p>

<h2>Modern Production Reality: Postgres vs MySQL</h2>
<p>The implementation of Gray\'s protocols defines the personality of modern databases.</p>

<ul>
<li><strong>MySQL (InnoDB):</strong> Uses classic row-level locking with Intent Locks exactly as Gray described. However, to prevent readers from blocking writers (a major issue in the 1970s), InnoDB combines Gray\'s locks with <strong>MVCC (Multi-Version Concurrency Control)</strong>, meaning reads rarely require S-locks anymore.</li>
<li><strong>PostgreSQL:</strong> Uses a heavily modified approach. Instead of keeping row locks in a massive RAM table (which causes the lock escalation problem), Postgres writes the lock information <em>directly onto the disk row itself</em> (in the tuple header). Because the lock is on disk, Postgres has zero memory overhead for row locks. Therefore, <strong>Postgres never performs Lock Escalation</strong>. You can lock 1 billion rows in Postgres, and it won\'t run out of lock memory or escalate to a table lock.</li>
</ul>

<h2>Expert Critique & Legacy</h2>
<p>Jim Gray\'s 1975 paper is a masterpiece of pragmatic engineering. It proved that in system design, you cannot solve a problem with a single extreme solution (all Table locks vs all Row locks). You must build layered hierarchies that dynamically adapt to the workload.</p>

<p>His invention of the Intent Lock is one of the most elegant "O(1) optimization" tricks in computer science history. It remains the absolute bedrock of ACID transactions. Without multi-granularity locking, e-commerce, banking, and modern SaaS applications would be physically impossible to scale.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Lock Granularity: How Jim Gray Solved the Database Concurrency Nightmare',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Jim Gray', 'Concurrency', 'Locking', 'Database Architecture']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> Viết năm 1975 bởi huyền thoại Jim Gray (người sau này đạt giải Turing), "Granularity of Locks" (Độ chi tiết của Khóa) là cuốn kinh thánh khai sinh ra hệ thống Kiểm soát Đồng thời (Concurrency Control) cho Database. Nó phát minh ra khái niệm <em>Intent Lock (Khóa Ý Định)</em> để giải quyết bài toán hóc búa: Làm sao để 1000 người cùng đọc/ghi dữ liệu mà không dẫm chân lên nhau.</li>
<li><strong>Vấn đề giải quyết:</strong> Nếu 1000 người cùng mua vé xem phim, DB phải "Khóa" (Lock) dữ liệu lại để chống bán trùng vé. Nếu Khóa nguyên cả cái Bảng (Table Lock), dữ liệu an toàn 100% nhưng cực kỳ chậm (xếp hàng từng người một). Nếu chỉ Khóa đúng 1 Hàng (Row Lock), tốc độ cực nhanh, nhưng DB sẽ sập nguồn vì cạn kiệt RAM do phải quản lý hàng triệu cái khóa nhỏ li ti.</li>
<li><strong>Giải pháp (Workflow):</strong> Jim Gray tạo ra một hệ thống Khóa phân cấp. Ông đẻ ra "Khóa Ý Định" (Intent Lock như IS, IX). Trước khi một Transaction muốn Khóa 1 Hàng, nó phải treo một cái "Biển báo Ý định" lên cái Bảng chứa hàng đó. Biển báo này giúp DB check xem có ai đang xung đột không chỉ trong $O(1)$ thời gian, thay vì phải càn quét kiểm tra hàng triệu cái khóa nhỏ ở dưới đáy.</li>
<li><strong>Thực tiễn Production:</strong> Mọi Relational Database hiện đại nhất (Postgres, MySQL InnoDB, SQL Server) đều đang dùng giao thức Khóa phân cấp của Jim Gray. Những tính năng như "Lock Escalation" (tự động gộp 5000 cái khóa hàng thành 1 cái khóa bảng để cứu RAM) là bản copy 100% từ bài báo này.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cơn Ác Mộng Của Sự Đồng Thời (Concurrency Nightmare)</h2>
<p>Đầu những năm 1970, khi IBM đang rục rịch chế tạo System R (ông tổ của SQL Database), họ đâm sầm vào một bức tường vật lý mang tên: Sự Đồng Thời (Concurrency). Database không phải là một cái tủ hồ sơ tĩnh lặng; nó là một ngã tư đường khủng khiếp, nơi hàng ngàn Transaction lao vào nhau với tốc độ bàn thờ, tranh giành Đọc và Ghi cùng một dữ liệu.</p>

<p>Thử tưởng tượng hệ thống Ngân hàng. Giao dịch A muốn chuyển 100$ từ Tài khoản 1 sang Tài khoản 2. Cùng lúc đó, Giao dịch B của ông sếp muốn tính Tổng số tiền của toàn bộ ngân hàng. Nếu Giao dịch B xông vào đọc đúng lúc Giao dịch A mới trừ xong 100$ ở TK1, nhưng chưa kịp cộng vào TK2, thì Tổng số tiền của ngân hàng sẽ bị hụt mất 100$ bốc hơi vào hư vô.</p>

<p>Để ngăn chặn thảm họa này, người ta dùng <strong>Lock (Khóa)</strong>. Khóa giúp cô lập dữ liệu. Nhưng khóa to hay khóa nhỏ?</p>
<ul>
<li><strong>Khóa Cấp Bảng (Table Lock - Khóa to):</strong> Giao dịch A khóa mẹ nó toàn bộ bảng <code>Tài Khoản</code> lại, chuyển tiền xong mới mở khóa. An toàn tuyệt đối! Nhưng... Giao dịch C (chỉ muốn nạp tiền cho TK 999, chả liên quan gì đến A) sẽ bị chặn đứng, đứng ngoài cửa khóc lóc chờ A làm xong. Database biến thành cái đường 1 chiều. Chậm như rùa.</li>
<li><strong>Khóa Cấp Hàng (Row Lock - Khóa nhỏ):</strong> Giao dịch A chỉ khóa đúng Hàng 1 và Hàng 2. Giao dịch C lập tức được phi vào khóa Hàng 999. Tốc độ bàn thờ! Mọi người đều vui. Nhưng khoan... Giao dịch B (muốn đọc TOÀN BỘ BẢNG) thì sao? Để đảm bảo an toàn, B phải hỏi Database: "Có ai đang khóa MỘT HÀNG BẤT KỲ trong cái bảng này không?". Database vã mồ hôi hột, lôi cuốn sổ quản lý Lock ra, và phải dò quét qua 10 triệu cái Row Lock đang có trên RAM để trả lời B. CPU của server bốc cháy.</li>
</ul>

<p>Jim Gray nhìn thấy bế tắc: Khóa to thì giết chết Tốc độ, Khóa nhỏ thì giết chết CPU. Cần một phép màu để dung hòa cả hai.</p>

<h2>Đột Phá Học Thuật: Phát Minh Ra Khóa Ý Định (Intent Lock)</h2>
<p>Jim Gray quyết định áp dụng tư duy quân đội: Quản lý theo cấp bậc. Ông chia Database thành cây phân cấp: Database -> Bảng (Table) -> Trang (Page) -> Hàng (Row). Và ông phát minh ra một loại Khóa kỳ lạ chưa từng có trong lịch sử: <strong>Intent Lock (Khóa Ý Định)</strong>.</p>

<p>Khóa Ý Định không thực sự "Khóa" dữ liệu. Nó đóng vai trò là một "Cái Biển Báo Cảnh Báo" được treo ở tầng trên cao (ví dụ: tầng Bảng), nhằm thông báo cho thiên hạ biết bạn sắp làm gì ở tầng thấp (tầng Hàng). Ông định nghĩa 3 loại khóa mới:</p>
<ol>
<li><strong>IS (Intent Share):</strong> "Tôi có ý định Đọc (Share) một vài hàng nằm sâu bên trong cái Bảng này."</li>
<li><strong>IX (Intent Exclusive):</strong> "Tôi có ý định Ghi đè (Exclusive) một vài hàng nằm sâu bên trong cái Bảng này."</li>
<li><strong>SIX (Share with Intent Exclusive):</strong> "Tôi muốn Đọc toàn bộ cái Bảng này, nhưng tôi cũng sẽ sửa một vài hàng bên trong nó."</li>
</ol>

<h3>Ma Thuật Chống Xung Đột Bằng Khóa Ý Định</h3>
<p>Hãy chạy lại kịch bản Ngân hàng bằng thuật toán của Jim Gray:</p>
<p>Giao dịch A muốn sửa Hàng 1. Nó không được phép lao vào khóa Hàng 1 ngay. Luật bắt buộc nó phải làm thủ tục từ trên đỉnh. Giao dịch A phải treo một biển <strong>IX (Intent Exclusive)</strong> lên cửa của Bảng <code>Tài Khoản</code>. Sau đó nó mới đi xuống dưới và áp lệnh khóa <strong>X (Exclusive)</strong> lên Hàng 1.</p>

<p>Lúc này, Giao dịch B xông tới đòi Đọc TOÀN BỘ BẢNG. Nó xin Database cấp cho một khóa <strong>S (Share)</strong> lên Bảng <code>Tài Khoản</code>. Database nhìn lên cửa Bảng, thấy cái biển <strong>IX</strong> đỏ chót của A đang treo ở đó. Database lập tức phán: "Á à! Có thằng đang Ghi dữ liệu ở bên trong. Lệnh S (Đọc tất) và lệnh IX (Đang ghi) là kẻ thù không đội trời chung. Thằng B, mày phải đứng chờ!".</p>

<p>Sự thiên tài nằm ở chỗ: <em>Database không hề phải rà soát hàng triệu cái khóa Hàng ở dưới đáy.</em> Nó tóm được sự xung đột ngay tại cửa ra vào của cái Bảng trong thời gian $O(1)$.</p>

<p>Trong khi đó, Giao dịch C đến và muốn sửa Hàng 999. Nó xin treo biển <strong>IX</strong> lên Bảng. Luật quy định: Biển IX không xung đột với biển IX khác (2 thằng cùng Ghi ở 2 góc khác nhau thì không sao). Database cho phép! Giao dịch C vui vẻ đi xuống dưới khóa Hàng 999. Hệ thống chạy với tốc độ đồng thời tối đa mà vẫn an toàn tuyệt đối.</p>

<h2>Giải Phẫu Kiến Trúc: Nâng Cấp Khóa (Lock Escalation) Và Khóa Chết (Deadlocks)</h2>
<p>Mô hình của Gray giải quyết được tốc độ, nhưng lại đẻ ra bài toán Rác Bộ Nhớ (Memory Overhead).</p>

<p>Mỗi cái Lock thực chất là một Object nằm trên RAM. Nếu một câu lệnh <code>UPDATE</code> vô tình quét qua 5 triệu Hàng, hệ thống sẽ phải cấp phát 5 triệu cái Object Row Lock trên RAM. Nó ngốn vài Gigabyte RAM chỉ để lưu thông tin Khóa, làm Server Out-of-Memory và sập.</p>

<p>Để cứu vãn, Gray chuẩn hóa khái niệm <strong>Lock Escalation (Leo thang Khóa)</strong>. Hệ thống sẽ cử một con bot theo dõi. Nếu một Giao dịch xin quá nhiều Khóa Hàng (ví dụ: vượt ngưỡng 5000 khóa trong 1 Bảng), con bot sẽ tước toàn bộ 5000 cái Khóa Hàng đó đi, và thay bằng MỘT Khóa Bảng duy nhất (Table Lock). Quá trình này giải phóng lượng RAM khổng lồ, bù lại, nó chấp nhận hy sinh tính đồng thời (chặn người khác truy cập Bảng) trong một thời gian ngắn.</p>

<h3>Nỗi Đau Deadlock</h3>
<p>Khóa nhỏ li ti thì dễ dẫn đến đụng xe (Deadlock). Giao dịch A khóa Hàng 1, đang cần xin Hàng 2. Giao dịch B khóa Hàng 2, đang cần xin Hàng 1. Hai thằng đứng trừng mắt nhìn nhau đến tận thế.</p>
<p>Nhờ nền tảng của Gray, các Database hiện đại phải đẻ ra một cái máy quét ngầm gọi là "Deadlock Detector". Máy quét này vẽ một Đồ thị (Graph) các Giao dịch đang chờ nhau. Nếu nó phát hiện có một vòng tròn (Cycle) đụng xe, nó sẽ lạnh lùng làm "Sát thủ": Chọn cái Giao dịch trẻ tuổi nhất, giết nó (Abort), Rollback lại mọi thay đổi, để nhường đường cho Giao dịch già hơn đi qua.</p>

<h2>Thực Tiễn Production: Sự Khác Biệt Của Postgres Và MySQL</h2>
<p>Bài báo của Gray là luật chung, nhưng cách các ông lớn implement nó lại tạo ra sự khác biệt vĩ đại:</p>

<ul>
<li><strong>MySQL (InnoDB):</strong> Làm chuẩn 100% theo bài của Gray, lưu Lock trên RAM. Nhưng để tránh tình trạng "Thằng Đọc chặn thằng Ghi", InnoDB kết hợp Khóa của Gray với công nghệ <strong>MVCC (Đa phiên bản)</strong>, giúp cho việc Đọc dữ liệu hầu như không bao giờ cần phải xin Khóa S nữa.</li>
<li><strong>PostgreSQL:</strong> Đi một nước cờ cực đoan và dị biệt. Nhận thấy việc lưu hàng triệu Row Lock trên RAM (như MySQL) rất dễ dẫn đến sập RAM và phải chạy Lock Escalation, Postgres quyết định: <em>Ghi thông tin Khóa thẳng xuống ổ cứng, ngay trên cái Header của từng Hàng dữ liệu</em>. Vì thông tin Khóa nằm trên ổ cứng, Postgres tiêu tốn 0 Byte RAM cho Row Lock. Kết quả cực sốc: <strong>Postgres KHÔNG BAO GIỜ CÓ LOCK ESCALATION</strong>. Bạn có thể <code>UPDATE</code> 1 tỷ dòng trong Postgres, nó sẽ lầm lì chạy mà không bao giờ bị tràn RAM hay biến thành Khóa Bảng, một điều mà MySQL hay SQL Server phải ghen tị.</li>
</ul>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Bài báo năm 1975 của Jim Gray là một kiệt tác của Kỹ thuật thực dụng (Pragmatic Engineering). Nó đập tan tư duy "Trắng - Đen" (Hoặc Khóa Bảng, hoặc Khóa Hàng). Nó chứng minh rằng trong thiết kế hệ thống, muốn tối ưu hóa, bạn phải xây dựng một Hệ thống Phân cấp có khả năng co giãn linh hoạt (Dynamic Hierarchy).</p>

<p>Việc phát minh ra Khóa Ý Định (Intent Lock) là một trong những cú trick "Tối ưu hóa $O(1)$" thanh lịch nhất trong lịch sử Khoa học Máy tính. Nó là viên đá tảng tạo nên chuẩn ACID bất diệt. Nếu không có lý thuyết Khóa phân cấp của Jim Gray, mọi nền tảng Thương mại điện tử (Shopee, Amazon), Ngân hàng lõi, và các hệ thống SaaS khổng lồ ngày nay đều sẽ vỡ nát dưới sức ép của hàng triệu cú click chuột diễn ra trong cùng một tích tắc.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Nghệ Thuật Khóa Dữ Liệu: Cách Jim Gray Cứu Database Khỏi Cơn Ác Mộng Deadlock',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Jim Gray', 'Concurrency', 'Locking', 'Database Architecture']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> のちにチューリング賞を受賞するデータベース界の伝説、ジム・グレイ（Jim Gray）が1975年に発表した「Granularity of Locks（ロックの粒度）」という論文です。「どうすれば、何千人ものユーザーが同時にデータベースに書き込んでも、データが壊れたりシステムがフリーズしたりしないのか？」という「同時実行制御（Concurrency Control）」の基礎を築いたバイブルです。</li>
<li><strong>根本的な問題：</strong> 人気チケットの予約サイトで、2人が同時に同じ席を買おうとしたらどうなるでしょう？ システムはデータを「ロック（施錠）」して他の人を締め出す必要があります。もし「データベース全体」をロックすれば絶対安全ですが、1秒間に1人しか買えません（遅すぎる）。逆に「たった1つの座席（行）」だけを細かくロックすれば超高速ですが、数百万個の細かいロックを管理するためにサーバーのメモリ（RAM）がパンクし、複雑に絡み合って「デッドロック（お互いに待ち続けて永久停止）」を起こします。</li>
<li><strong>解決策：</strong> ジム・グレイは、「ロックの階層構造」と<strong>「インテント・ロック（意図ロック：Intent Lock）」</strong>という天才的な概念を発明しました。「行」をロックする前に、その親である「テーブル」に「これから下のほうで書き込みをするよ」という警告看板（インテント・ロック）を立てるのです。これにより、システムは数百万の行をスキャンすることなく、一瞬でロックの衝突を検知できるようになりました。</li>
<li><strong>現代の真実：</strong> PostgreSQL、MySQL InnoDB、SQL Serverなど、現代のすべてのリレーショナル・データベースは、このグレイの「階層的ロックモデル」をそのまま採用しています。メモリを節約するために大量の行ロックを自動的にテーブルロックに変換する「ロック・エスカレーション」などの技術も、この論文が完全な起源です。</li>
</ul>

<h2>歴史的背景：同時実行の悪夢（Concurrency Nightmare） ⚔️</h2>
<p>1970年代初頭、IBMが世界初のSQLデータベース「System R」を開発していたとき、彼らは分厚い物理の壁に激突しました。それが「同時実行性（Concurrency）」です。データベースは静かな書類棚ではなく、何千人ものプログラマーが同時に猛スピードで交差する「狂った交差点」です。</p>

<p>銀行のシステムを想像してください。トランザクションA（送金処理）が、口座1から100ドルを引いて、口座2に足そうとしています。口座1から引いた「直後」のタイミングで、トランザクションB（集計処理）が、銀行の「すべての口座の合計金額」を計算しようとしました。すると、空中に浮いている100ドルが計算から漏れてしまい、銀行の合計資産が100ドル消滅してしまいます。大惨事です。</p>

<p>これを防ぐために<strong>「ロック（Lock：鍵）」</strong>を使います。問題は「鍵の大きさ」です。</p>
<ul>
<li><strong>テーブルロック（巨大な鍵）：</strong> Aが「口座テーブル全体」に鍵をかけ、送金が終わるまで誰も触れなくします。完璧に安全です。しかし、口座999を更新したいだけのCさんも、関係ないのに鍵が空くまで待たされます。データベースは「1車線の道路」になり、大渋滞を起こします。</li>
<li><strong>行ロック（極小の鍵）：</strong> Aが「口座1と口座2（行）」だけに鍵をかけます。これならCさんは待たずに口座999を更新できます。超高速です！ しかし、Bさん（テーブル全体を集計したい）が来たらどうなるでしょう？ Bさんは安全を確認するため、データベースに「今、このテーブルの数百万行のうち、誰かが鍵をかけている行は一つでもあるか？」と尋ねます。データベースは白目を剥きながら、メモリ上にある数百万個の行ロックをすべてスキャンして確認しなければなりません。CPUが燃え尽きます。</li>
</ul>

<p>ジム・グレイは悟りました。「巨大な鍵」は速度を殺し、「極小の鍵」はCPUを殺す。この両方を同時に、かつ安全に共存させる魔法が必要だったのです。</p>

<h2>学術的ブレイクスルー：天才的な「意図ロック（Intent Lock）」 💡</h2>
<p>グレイはデータベースを「階層（データベース → テーブル → ページ → 行）」として定義し、歴史上誰も見たことがない全く新しい種類の鍵を発明しました。それが<strong>「インテント・ロック（Intent Lock：意図ロック）」</strong>です。</p>

<p>意図ロックは、データそのものをロックするわけではありません。上の階層（テーブルなど）に立てておく<strong>「警告の看板」</strong>です。彼は3つの新しい看板を定義しました。</p>
<ol>
<li><strong>IS（Intent Share）：</strong> 「今から、このテーブルのずっと下のほうの『行』を、読書（Share）目的でロックする予定です」</li>
<li><strong>IX（Intent Exclusive）：</strong> 「今から、下のほうの『行』を、書き込み・上書き（Exclusive）目的でロックする予定です」</li>
<li><strong>SIX（Share with Intent Exclusive）：</strong> 「このテーブル全体を読み込みますが、同時にいくつか特定の行だけ書き込みます」</li>
</ol>

<h3>意図ロックがもたらした「O(1)」の魔法</h3>
<p>この看板がどのように世界を救うのか、銀行の例で見てみましょう。</p>
<p>トランザクションAが口座1（行）を上書きしたいとします。いきなり口座1をロックしてはいけません。ルールにより、まず親である「口座テーブル」の入り口に<strong>「IX（書き込み意図）」</strong>という赤い看板を立てます。その上で、下の階層に降りて口座1に<strong>「X（排他ロック）」</strong>をかけます。</p>

<p>そこへ、トランザクションBが「テーブル全体の集計」にやってきました。Bはテーブル全体を読書ロック<strong>（Sロック）</strong>しようとします。するとデータベースは、テーブルの入り口にぶら下がっている「IX」の赤い看板を見て、即座に叫びます。「ダメだ！ 誰かが中で書き込みをしている最中だ！ S（全体読み込み）とIX（部分書き込み）は衝突する。Bよ、ここで待機せよ！」</p>

<p>このシステムの真の恐ろしさは<strong>「データベースがやらなかったこと」</strong>にあります。データベースは「数百万個の行ロックをスキャンして衝突を探す」という地獄の作業を一切行いませんでした。テーブルの入り口の看板を1つ見ただけで、一瞬（$O(1)$の計算量）で衝突を検知したのです。</p>
<p>一方、口座999を更新したいだけのCさんが来たらどうでしょう？ Cさんもテーブルに<strong>「IX」</strong>看板を立てようとします。ルール上、「IXとIXの看板は並べて立ててもOK（違う行を更新するなら問題ない）」です。なのでCさんはスッと中に入り、口座999を超高速で更新できます。安全性と超・同時実行性が、ここで完璧に両立しました。</p>

<h2>アーキテクチャの徹底解剖：ロックエスカレーションとデッドロック 💥</h2>
<p>グレイの階層モデルは検索速度を解決しましたが、今度は「メモリの枯渇」という新たな問題を生みました。</p>

<p>ロックというのは、RAM上に作られるデータ構造（オブジェクト）です。もしバッチ処理が「500万件のユーザー情報を一気に更新する」というSQLを走らせたら、システムは500万個の行ロックオブジェクトをRAM上に作らなければならず、数ギガバイトのメモリを消費してサーバーがクラッシュ（OOM）してしまいます。</p>

<p>これを防ぐため、グレイは<strong>「ロック・エスカレーション（Lock Escalation）」</strong>という緊急回避システムを組み込みました。システムは裏で監視ボットを走らせています。もし1つのトランザクションが「5000個以上の行ロック」を抱え込んだら、ボットが強権を発動し、その5000個の細かい鍵を没収して、代わりに「巨大なテーブルロック1つ」に変換（エスカレーション）します。これにより、他人の処理をしばらく止めるという犠牲を払う代わりに、サーバーのメモリ枯渇による完全停止を未然に防ぐのです。</p>

<h3>デッドロック（死の抱擁）</h3>
<p>細かい行ロックを許可すると、避けられない事故が起きます。Aさんが行1をロックして行2を待っている。Bさんが行2をロックして行1を待っている。お互いに一生待ち続ける「デッドロック（Deadlock）」です。</p>
<p>グレイの理論に基づき、現代のデータベースには「デッドロック監視システム（Deadlock Detector）」が組み込まれています。これは常に「誰が誰を待っているか」というグラフを描き続け、グラフの中にループ（円）を発見した瞬間、冷酷な判断を下します。一番新しく始まった（若い）トランザクションを「強制キル（Abort）」し、データを巻き戻して、古いトランザクションに道を譲らせるのです。</p>

<h2>現代の真実：PostgreSQLの狂気 vs MySQL 🐘🐬</h2>
<p>グレイの論文は業界標準ですが、その「実装方法」にデータベースの思想が表れます。</p>

<ul>
<li><strong>MySQL (InnoDB)：</strong> グレイの教えに100%従い、ロック情報をメモリ（RAM）上で管理します。しかし「読む人が書く人をブロックする」のを防ぐため、MVCC（多版同時実行制御）という技術と組み合わせ、通常の読み込みではそもそもSロックを取らないように進化しました。ただし、大量の更新をするとロックエスカレーション（テーブルロックへの格上げ）が発生します。</li>
<li><strong>PostgreSQL：</strong> かなり狂気じみた（天才的な）アプローチを取りました。「数百万の行ロックをRAMに置くとエスカレーションが発生してウザい」と考えたPostgresは、なんと<strong>「行ロックの情報を、ハードディスク上のデータそのもの（Tupleのヘッダ）に直接書き込む」</strong>という設計にしました。ロック情報がディスク上にあるため、Postgresは行ロックでRAMを1バイトも消費しません。その結果、Postgresには<strong>「ロック・エスカレーションという概念が存在しない」</strong>のです。10億行を更新（UPDATE）しても、決してテーブルロックに格上げされず、静かに沈黙のまま処理を続けることができます。</li>
</ul>

<h2>専門家による批評と、不朽のレガシー 🏛️</h2>
<p>ジム・グレイの1975年の論文は、プラグマティック（実用的）なシステム設計の最高傑作です。彼は「巨大なロックか、極小のロックか」という二元論の罠を破壊し、「動的に変化する階層構造を作ればよい」という解答を導き出しました。</p>

<p>彼が発明した「インテント・ロック（意図ロック）」は、コンピュータサイエンスの歴史において、最も優雅でパワフルな「$O(1)$最適化のトリック」の一つです。これはデータベースの「ACIDトランザクション」を支える絶対的な岩盤です。もしグレイのこの論文が存在しなければ、Amazonでの同時セールも、世界中の銀行の送金ネットワークも、現代のすべての巨大クラウドシステムも、処理が複雑に絡み合って自壊していたことでしょう。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'ロックの粒度（Lock Granularity）：Jim GrayはいかにしてDBの「デッドロック地獄」を救ったか',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Jim Gray', 'Concurrency', 'Locking', 'Database Architecture']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 13 (Lock Granularity)!\n";
