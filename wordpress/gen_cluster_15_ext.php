<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'mvcc_memory_1784014388932.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'MVCC Memory',
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
<li><strong>What is it?</strong> Multi-Version Concurrency Control (MVCC) is a database architecture paradigm heavily popularized and optimized by researchers like Per-Åke Larson for Main-Memory Databases. It solves the biggest bottleneck in database history: "Readers blocking Writers."</li>
<li><strong>The Core Problem:</strong> In a traditional locking database (like early IBM System R), if an analyst runs a massive <code>SELECT</code> query that takes 10 minutes, they place a "Read Lock" on the table. If a customer tries to buy an item during those 10 minutes (a <code>UPDATE</code>), the customer is blocked and forced to wait. The business grinds to a halt.</li>
<li><strong>The Solution:</strong> MVCC eliminates Read Locks. When a row is updated, MVCC <em>does not overwrite</em> the old row. Instead, it creates a completely new "version" of the row and tags it with a timestamp. The analyst\'s 10-minute query simply reads the old version of the row from the past, while the customer freely writes the new version of the row in the present. They never block each other.</li>
<li><strong>Modern Reality:</strong> MVCC is the engine that powers the modern internet. It is the default concurrency model for PostgreSQL, MySQL (InnoDB), Oracle, and SQL Server. However, keeping multiple versions of rows creates massive data bloat, forcing databases to run aggressive "Garbage Collection" (like Postgres\'s infamous <code>VACUUM</code>) to delete old versions.</li>
</ul>

<h2>Historical Context & The Catalyst: The Analytics vs. Operations War</h2>
<p>In the 1980s and 90s, the "Locking" architecture invented by Jim Gray was the gold standard. It was mathematically perfect. But it had a fatal flaw in the real world: <strong>Readers block Writers, and Writers block Readers.</strong></p>

<p>Imagine Amazon.com in 1998. The website has two types of users:</p>
<ol>
<li><strong>Operations (OLTP):</strong> Millions of customers clicking "Buy Now", updating their cart, and changing inventory. These are fast, tiny Write transactions.</li>
<li><strong>Analytics (OLAP):</strong> Jeff Bezos running a query: "Sum the total sales for the last 24 hours." This is a massive Read transaction that takes 5 minutes to scan the database.</li>
</ol>

<p>Under a strict locking system, when Jeff Bezos starts his 5-minute Read query, he places a "Shared Lock" on the Sales table. Suddenly, a customer clicks "Buy Now" and tries to place an "Exclusive Lock" on the table to update it. The database says, "Sorry, Jeff Bezos is reading this table. Please wait 5 minutes." The customer leaves. Amazon loses the sale.</p>
<p>To prevent this, companies were forced to buy two completely separate servers: one for Operations, and one for Analytics, copying data between them overnight. This was expensive, complex, and slow.</p>

<h2>The Academic Breakthrough: Time Travel in the Database</h2>
<p>Computer scientists asked a radical question: What if we never overwrite data? What if every update just creates a new version?</p>

<p>This is the essence of <strong>Multi-Version Concurrency Control (MVCC)</strong>. It turns the database into a time machine.</p>

<p>Every transaction is assigned a unique, increasing Timestamp (or Transaction ID). Every row in the database has two hidden columns: <code>Created_By_Txn</code> and <code>Deleted_By_Txn</code>.</p>
<ul>
<li>When Transaction 100 inserts a row, it sets <code>Created=100</code>, <code>Deleted=infinity</code>.</li>
<li>When Transaction 105 updates that row, it <em>does not touch</em> the data. Instead, it marks the old row as <code>Deleted=105</code>, and inserts a brand new physical row with the new data, marked <code>Created=105</code>, <code>Deleted=infinity</code>.</li>
</ul>

<p>Now, let\'s replay the Amazon scenario. Jeff Bezos starts his query at Timestamp 101. The database takes a <strong>Snapshot</strong> of time at T=101. As his query scans the table, it looks at the hidden columns. It sees the old row (Created=100, Deleted=105). Because 101 is between 100 and 105, Bezos is allowed to read this old row. He completely ignores the new row (Created=105), because it was created in "the future" relative to his snapshot.</p>
<p>Meanwhile, the customer is freely creating new rows at T=105. <strong>Readers never block Writers. Writers never block Readers.</strong> Concurrency skyrockets.</p>

<h2>Deep Architectural Walkthrough: The Garbage Collection Tax</h2>
<p>MVCC sounds like magic, but it violates a fundamental rule of physics: Hard drives are not infinitely large.</p>

<p>If you update a row 10,000 times, MVCC creates 10,000 physical copies of that row on the disk. Only the newest one is "alive"; the other 9,999 are "dead tuples" (ghosts of the past). If you don\'t clean them up, your 10GB database will quickly bloat into a 1TB monster, and performance will collapse because the database has to scan past 9,999 dead rows just to find the live one.</p>

<p>This necessitates <strong>Garbage Collection (GC)</strong>. The database must run a background process to find and delete rows that are so old that no active query could possibly want to look at them anymore.</p>

<h3>The PostgreSQL vs. MySQL MVCC Split</h3>
<p>How databases implement this Garbage Collection is the biggest architectural dividing line in the industry.</p>

<ul>
<li><strong>PostgreSQL (Append-Only MVCC):</strong> Postgres stores the old versions and new versions <em>in the exact same data file</em>. This makes creating new versions incredibly fast. But it means the data file fills up with dead tuples. To fix this, Postgres runs an aggressive background process called <strong>VACUUM</strong>. If VACUUM falls behind, the database bloats catastrophically (a famous problem that companies like Uber have written extensively about).</li>
<li><strong>MySQL InnoDB (Undo-Log MVCC):</strong> MySQL stores only the <em>newest</em> version of the row in the main data file. The <em>old</em> versions are shoved into a separate, temporary file called the "Undo Log". This prevents the main table from bloating. However, if a long-running Read query needs to see an old version, it has to painstakingly reconstruct the old row by reading backwards through the Undo Log, which burns massive CPU cycles.</li>
</ul>

<h2>Modern Production Reality: Main-Memory MVCC</h2>
<p>Per-Åke Larson and other researchers at Microsoft (working on the Hekaton engine for SQL Server) took MVCC to its logical extreme for modern <strong>Main-Memory Databases</strong>.</p>

<p>When the entire database is in RAM, you don\'t use disk blocks. Larson\'s design linked the versions of a row together using standard in-memory pointers (a Linked List). The newest version points to the older version, which points to the older version. This lock-free, latch-free data structure allows 100-core CPUs to traverse versions at the speed of light, achieving millions of transactions per second.</p>

<h2>Expert Critique & Legacy</h2>
<p>MVCC is arguably the most important database architectural decision of the last 30 years. It shifted the burden of concurrency from "Blocking users" (which destroys user experience) to "Managing disk space" (which is cheap and can be handled by background bots).</p>

<p>However, MVCC is not a silver bullet. It introduces severe <strong>Write Amplification</strong>. In Postgres, updating a single boolean column forces the database to duplicate the entire 2KB row. Furthermore, configuring the Garbage Collector (Autovacuum) remains a dark art that requires senior DBAs.</p>

<p>Despite its flaws, the verdict of history is clear: In a world where read-heavy web traffic dominates, the ability to read the past without stopping the future makes MVCC the undisputed king of concurrency.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'MVCC: The "Time Travel" Architecture That Powers the Modern Internet',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['MVCC', 'Concurrency', 'PostgreSQL', 'Garbage Collection']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> Multi-Version Concurrency Control (MVCC - Kiểm soát đồng thời đa phiên bản) là kiến trúc lõi được tối ưu hóa bởi các nhà nghiên cứu như Per-Åke Larson. Nó sinh ra để giải quyết bài toán nhức nhối nhất lịch sử Database: "Người Đọc làm nghẽn Người Ghi".</li>
<li><strong>Vấn đề giải quyết:</strong> Trong các hệ thống cũ dùng Lock (Khóa), nếu sếp đang chạy một câu lệnh <code>SELECT</code> báo cáo mất 10 phút, hệ thống sẽ "Khóa Đọc" cái bảng đó lại. Nếu có khách hàng bấm nút "Mua hàng" (lệnh <code>UPDATE</code>), khách sẽ bị chặn lại bắt chờ 10 phút. Trải nghiệm người dùng nát bét, doanh thu bốc hơi.</li>
<li><strong>Giải pháp (Workflow):</strong> MVCC vứt bỏ hoàn toàn việc Khóa khi Đọc. Khi có người Ghi đè dữ liệu mới, MVCC <em>không xóa dữ liệu cũ</em>. Thay vào đó, nó tạo ra một "Phiên bản mới" và dán nhãn Thời gian (Timestamp) vào. Ông sếp chạy báo cáo 10 phút cứ việc thong thả Đọc cái "Phiên bản cũ" trong quá khứ, còn khách hàng cứ việc Ghi "Phiên bản mới" ở hiện tại. Nước sông không phạm nước giếng, không ai chặn ai.</li>
<li><strong>Thực tiễn Production:</strong> MVCC là cỗ máy vô hình đang gánh vác toàn bộ Internet hiện đại. Postgres, MySQL InnoDB, Oracle đều xài nó. Nhưng cái giá phải trả là rác thải bùng nổ. Lưu quá nhiều phiên bản cũ khiến Database bị phình to (Bloat), buộc hệ thống phải đẻ ra những con bot đi dọn rác (Garbage Collection) khét tiếng như <code>VACUUM</code> của Postgres.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cuộc Chiến Giữa Phân Tích (OLAP) và Vận Hành (OLTP)</h2>
<p>Vào thập niên 80-90, kiến trúc "Dùng Khóa" (Locking) của Jim Gray là chân lý tuyệt đối. Về mặt toán học, nó hoàn hảo. Nhưng khi đem ra đời thực, nó bộc lộ một tử huyệt chí mạng: <strong>Thằng Đọc chặn họng thằng Ghi, và Thằng Ghi khóa mõm thằng Đọc.</strong></p>

<p>Hãy tưởng tượng bạn đang vận hành trang Shopee. Shopee có 2 loại User:</p>
<ol>
<li><strong>Vận hành (OLTP):</strong> Hàng triệu khách hàng liên tục bấm "Thêm vào giỏ", "Thanh toán", làm trừ kho liên tục. Đây là những lệnh Ghi (Write) siêu nhỏ, siêu nhanh.</li>
<li><strong>Phân tích (OLAP):</strong> Sếp tổng muốn xem: "Tính tổng doanh thu toàn bộ gian hàng trong 24h qua". Đây là một lệnh Đọc (Read) khổng lồ, mất 5 phút để quét qua hàng tỷ dòng.</li>
</ol>

<p>Nếu dùng Lock truyền thống, khi Sếp bắt đầu chạy lệnh 5 phút, hệ thống sẽ ốp một cái "Khóa Đọc" (Shared Lock) lên toàn bộ bảng Sản phẩm. Bất thình lình, có khách hàng bấm Mua. Lệnh mua yêu cầu một "Khóa Ghi" (Exclusive Lock). Database lạnh lùng báo: "Xin lỗi anh khách, Sếp đang đọc báo cáo, anh vui lòng đứng chờ 5 phút nữa hãng mua". Khách hàng chửi thề và sang Lazada mua. Shopee mất tiền.</p>
<p>Để chữa cháy, các công ty ngày xưa phải tốn cả núi tiền mua 2 con Server vật lý tách biệt: Một con cho Khách mua, một con cho Sếp đọc báo cáo, đêm đến mới copy dữ liệu qua lại. Rất phèn và tốn kém.</p>

<h2>Đột Phá Học Thuật: Cỗ Máy Thời Gian Của Database</h2>
<p>Các nhà khoa học đặt ra một câu hỏi điên rồ: Chuyện gì xảy ra nếu ta KHÔNG BAO GIỜ GHI ĐÈ dữ liệu? Nếu mỗi lần Update, ta cứ đẻ ra một bản copy mới thì sao?</p>

<p>Đó chính là linh hồn của <strong>Kiến trúc MVCC</strong>. Nó biến Database thành một cỗ máy du hành thời gian.</p>

<p>Mỗi thao tác (Transaction) được phát một cái mã số thời gian (Timestamp) tăng dần. Và <em>mọi hàng dữ liệu</em> đều bị gắn ngầm 2 cái tem: <code>Tem_Sinh_Ra</code> và <code>Tem_Chết_Đi</code>.</p>
<ul>
<li>Giao dịch số 100 thêm một cái áo giá 50k. Nó dán tem: <code>Sinh_Ra=100</code>, <code>Chết_Đi=Vô Cực</code>.</li>
<li>Giao dịch số 105 muốn Update giá áo lên 60k. Nó <em>không đụng chạm</em> gì đến hàng 50k cũ. Nó lẳng lặng lấy bút sửa <code>Chết_Đi=105</code> ở hàng cũ. Sau đó, nó đẻ ra một hàng 60k mới tinh, dán tem <code>Sinh_Ra=105</code>, <code>Chết_Đi=Vô Cực</code>.</li>
</ul>

<p>Giờ hãy chạy lại kịch bản Shopee. Sếp tổng chạy báo cáo ở thời điểm số 101. Database chụp ngay một bức ảnh <strong>Snapshot</strong> (Không gian thời gian tại T=101). Khi câu lệnh của Sếp quét qua cái áo, nó nhìn vào tem. Nó thấy hàng áo 50k (Sinh ra=100, Chết đi=105). Vì con số 101 của Sếp nằm giữa 100 và 105, Sếp được quyền đọc hàng 50k này. Nó cũng thấy hàng áo 60k (Sinh ra=105), nhưng vì 105 là "tương lai" so với 101 của Sếp, hệ thống che mắt Sếp lại, coi như hàng đó chưa tồn tại.</p>
<p>Trong khi đó, ở thực tại, khách hàng vẫn đang thoải mái Update giá áo ở thời điểm 105. <strong>Người Đọc không bao giờ cản Người Ghi. Người Ghi không bao giờ chặn Người Đọc.</strong> Nút thắt cổ chai bị đập tan, tốc độ hệ thống tăng phi mã.</p>

<h2>Giải Phẫu Kiến Trúc: Bi Kịch Rác Thải (Garbage Collection Tax)</h2>
<p>MVCC nghe như một phép thuật, nhưng nó vi phạm định luật vật lý: Ổ cứng không thể to vô hạn.</p>

<p>Nếu bạn Update 1 dòng 10.000 lần, MVCC sẽ đẻ ra 10.000 bản copy của dòng đó trên ổ cứng. Chỉ có bản mới nhất là "Sống", 9.999 bản còn lại là "Hồn ma bóng quế" (Dead Tuples). Nếu không ai dọn dẹp, cái Database 10GB của bạn sẽ nhanh chóng phình to thành con quái vật 1 Terabyte. Tốc độ sẽ rớt thê thảm vì ổ cứng phải quét qua 9.999 cái xác chết thì mới tìm được dữ liệu sống.</p>

<p>Thế là ngành IT phải đẻ ra khái niệm <strong>Garbage Collection (GC - Thu gom rác)</strong>. Hệ thống phải nuôi một bầy bot chạy ngầm, rình xem cái xác nào quá cũ (không còn ông Sếp nào đang dùng Snapshot cũ để soi nữa), thì đem đi thiêu hủy để lấy lại chỗ trống ổ cứng.</p>

<h3>Cuộc Chiến Tôn Giáo: PostgreSQL vs MySQL</h3>
<p>Cách dọn rác chính là điểm phân định đẳng cấp và triết lý của các Database hiện đại:</p>

<ul>
<li><strong>PostgreSQL (Append-Only MVCC):</strong> Postgres nhét chung cả Bản cũ lẫn Bản mới vào <em>cùng một file dữ liệu</em>. Ghi thì siêu nhanh! Nhưng file sẽ ngập ngụa xác chết. Để dọn, Postgres xài con quái vật dọn rác tên là <strong>VACUUM</strong>. Nếu Server quá tải, VACUUM chạy không kịp, Database sẽ bị "Bloat" (Phình to đứt mạch máu) chết tươi. Uber từng viết bài chửi Postgres thậm tệ và chuyển sang MySQL chỉ vì cái VACUUM này.</li>
<li><strong>MySQL InnoDB (Undo-Log MVCC):</strong> Cáo già hơn, MySQL chỉ lưu Bản mới nhất ở file chính. Mấy cái Bản cũ nó vứt ra một cái bãi rác riêng gọi là "Undo Log". Nhờ vậy bảng chính luôn sạch sẽ, không bị phình to. Nhưng bù lại, nếu ông Sếp chạy báo cáo lâu, hệ thống phải lặn ngụp vào bãi rác Undo Log, lục lọi và lắp ráp lại các mảnh vỡ để dựng lại quá khứ, cực kỳ tốn CPU.</li>
</ul>

<h2>Thực Tiễn Production: Khi MVCC Lên RAM (Main-Memory MVCC)</h2>
<p>Tiến sĩ Per-Åke Larson và nhóm Microsoft (làm ra engine Hekaton cho SQL Server) đã đẩy MVCC lên cảnh giới tối thượng cho các hệ thống <strong>In-Memory Database</strong>.</p>

<p>Khi mọi thứ nằm trên RAM, ta không xài Block ổ cứng nữa. Larson thiết kế các Phiên bản của một Hàng kết nối với nhau bằng các Sợi dây con trỏ (Linked List) trên RAM. Bản mới nhất lấy dây thòng lọng buộc vào Bản cũ, Bản cũ buộc vào Bản cũ hơn. Cấu trúc không cần Lock (Lock-free) này giúp CPU 100-core nhảy múa xuyên thời gian qua các phiên bản với tốc độ ánh sáng, chạm mốc hàng triệu giao dịch mỗi giây.</p>

<h2>Bình Luận Chuyên Gia & Trái Đắng (Expert Critique & Trade-offs)</h2>
<p>MVCC có lẽ là quyết định kiến trúc vĩ đại nhất của ngành Database trong 30 năm qua. Nó chuyển giao gánh nặng từ việc "Bắt User phải chờ đợi" (Thứ giết chết doanh thu) sang việc "Tốn dung lượng ổ cứng" (Thứ cực kỳ rẻ và có thể dùng Bot dọn dẹp).</p>

<p>Dù vậy, MVCC không phải viên đạn bạc. Nó đẻ ra hội chứng <strong>Write Amplification (Khuếch đại Ghi)</strong>. Trong Postgres, chỉ cần bạn sửa 1 cột `true/false`, nó bắt bạn phải copy y nguyên nguyên một hàng dữ liệu dài dằng dặc 2KB. Và việc cấu hình con bot Autovacuum vẫn là một môn "Nghệ thuật Hắc ám" chỉ dành cho các bậc thầy DBA.</p>

<p>Nhưng vượt lên tất cả, bánh xe lịch sử đã phán quyết: Trong kỷ nguyên Internet nơi những cú Click xem hàng (Read-heavy) thống trị, khả năng "Soi lại quá khứ mà không cản bước tương lai" đã đưa MVCC lên ngôi vua tuyệt đối của thế giới Đồng thời.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'MVCC: Cỗ Máy Thời Gian Của Database Đang Gánh Vác Cả Thế Giới Internet',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['MVCC', 'Concurrency', 'PostgreSQL', 'Garbage Collection']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 「多版同時実行制御（Multi-Version Concurrency Control：MVCC）」は、データベースの歴史において最も凶悪なボトルネックであった「読み込みが書き込みをブロックしてしまう問題」を完全に解決した、現代のデータベースのコア・アーキテクチャです。Per-Åke Larsonなどの研究者によって、特に最新のインメモリDB向けに極限まで最適化されました。</li>
<li><strong>根本的な問題：</strong> 昔の「ロック（鍵）」を使うデータベースでは、社長が「今月の全売上を集計せよ」という10分かかる巨大な読み込み（SELECT）を実行すると、データベース全体に「読書ロック」がかかりました。その10分間、お客様がネットショップで「購入ボタン（UPDATE）」を押しても、ロックに弾かれて決済できず、ビジネスが完全に停止していました。</li>
<li><strong>解決策：</strong> MVCCは「読書ロック」という概念をこの世から消し去りました。データが上書き（UPDATE）されるとき、MVCCは古いデータを消しません。代わりに「新しいバージョンのデータ」を丸ごと新規作成し、タイムスタンプ（時間札）を貼ります。社長の10分かかる集計は「過去の古いバージョンのデータ」をのんびり読み続け、お客様は「現在の新しいデータ」を自由に書き込みます。お互いの世界が分離しているため、絶対に衝突しません。</li>
<li><strong>現代の真実：</strong> MVCCは現代インターネットの心臓です。PostgreSQL、MySQL（InnoDB）、Oracleなど、すべての主流データベースの標準機能です。ただし、「古いデータを残し続ける」ため、ハードディスクはゴミ（古いバージョン）でパンクしやすくなります。そのため、Postgresの「VACUUM」のような、巨大なゴミ収集ロボット（Garbage Collection）を裏で走らせ続ける必要があります。</li>
</ul>

<h2>歴史的背景：OLAP（集計）とOLTP（更新）の血みどろの戦い ⚔️</h2>
<p>1980年代から90年代にかけて、ジム・グレイが発明した「ロック（Locking）」アーキテクチャは完璧な数学的モデルとして称賛されていました。しかし現実のビジネスに投入すると、致命的な欠陥が露呈しました。<strong>「読む人が書く人をブロックし、書く人が読む人をブロックする」</strong>という事実です。</p>

<p>1998年のAmazon.comを想像してください。サイトには2種類のユーザーが混在しています。</p>
<ol>
<li><strong>オペレーション（OLTP）：</strong> 何百万人もの客が「カートに入れる」「決済する」をクリックし、在庫を秒速で減らしていく「超短距離の書き込み」。</li>
<li><strong>アナリティクス（OLAP）：</strong> ジェフ・ベゾスが「過去24時間の全商品の売上合計を出せ」と命じる「5分かかる巨大な読み込み」。</li>
</ol>

<p>ロックを使うシステムでは、ベゾスが5分の読み込みを始めた瞬間、商品テーブル全体に「共有ロック」がかかります。そこへ客が「決済ボタン（排他ロックの要求）」を押します。するとデータベースは冷酷に告げます。「申し訳ありません。現在ベゾス様が商品カタログを読書中です。5分間お待ちください」。客は怒ってサイトを去り、Amazonは莫大な売上を失います。</p>
<p>この惨劇を防ぐため、当時の企業は「客用サーバー」と「社長の集計用サーバー」を別々に数億円で買い、夜中にデータをバッチでコピーするという、高コストで泥臭い運用を強いられていました。</p>

<h2>学術的ブレイクスルー：データベースの中の「タイムマシン」 ⏱️</h2>
<p>コンピュータ科学者たちは狂った仮説を立てました。「もし、データを『上書き』するのを一切やめたらどうなる？ 更新されるたびに、コピーを増やしていけばいいじゃないか」。</p>

<p>これこそが<strong>MVCC（多版同時実行制御）</strong>の正体です。データベースを「タイムマシン」に変える魔法です。</p>

<p>すべてのトランザクションには、増加していく「タイムスタンプ（時間札）」が配られます。そしてデータベースのすべての行には、見えない2つのタグが縫い付けられています。<code>[生まれた時間]</code> と <code>[死んだ時間]</code> です。</p>
<ul>
<li>時間【100】の処理が、5000円のシャツを登録しました。タグは <code>[生=100, 死=無限]</code> となります。</li>
<li>時間【105】の処理が、シャツを6000円に値上げ（UPDATE）しようとしました。システムは古い5000円のデータには指一本触れません。代わりに古いタグを <code>[死=105]</code> に書き換え、全く新しい「6000円のシャツ」の行を追加して <code>[生=105, 死=無限]</code> とタグ付けします。</li>
</ul>

<p>さあ、Amazonの悲劇をやり直しましょう。ベゾスが時間【101】に集計クエリを開始しました。データベースはT=101の<strong>スナップショット（空間の静止画）</strong>をパシャリと撮ります。ベゾスのクエリがシャツのデータを見に来ると、タグをチェックします。そこには5000円のシャツ（生100、死105）があります。ベゾスの「101」はこの間にあるので、彼は5000円のシャツを読み込みます。一方、6000円のシャツ（生105）は、ベゾスの101より「未来」の存在なので、彼には完全に見えません（無視されます）。</p>
<p>その間、現実の時間【105】では、客が値上げされたデータをガンガン書き込んでいます。<strong>「読む人は書く人を絶対に邪魔しない。書く人も読む人を絶対に邪魔しない」</strong>。同時並行処理のボトルネックは木っ端微塵に粉砕されました。</p>

<h2>アーキテクチャの徹底解剖：ゴミ収集（Garbage Collection）という重税 🗑️</h2>
<p>MVCCは魔法のようですが、物理法則には逆らえません。「ハードディスクの容量は無限ではない」のです。</p>

<p>1つの行を1万回UPDATEすると、MVCCはハードディスクに「1万個の物理コピー」を作り出します。生きているのは最新の1個だけで、残り9999個は過去の亡霊（Dead Tuples）です。これを放置すれば、10GBだったデータベースはあっという間に1TBのゴミ山へと膨張し（Bloat）、生きているデータを探すために9999個の死体をかき分けなければならず、パフォーマンスは崩壊します。</p>

<p>ここで<strong>「ガベージコレクション（GC：ゴミ収集）」</strong>の概念が必要になります。データベースは裏でルンバのような掃除ロボットを走らせ、「過去のスナップショットから見ても、もう絶対に誰からも読まれることのない、古すぎる死体データ」を見つけ出し、物理的に削除してディスク容量を取り戻さなければなりません。</p>

<h3>宗教戦争：PostgreSQL vs MySQL</h3>
<p>この「ゴミの捨て方」こそが、現代データベースの思想を二分する最大の分水嶺です。</p>

<ul>
<li><strong>PostgreSQL（追記型MVCC）：</strong> Postgresは、古いバージョンも新しいバージョンも<em>「同じメインのデータファイル」</em>の中に無造作に放り込みます。書き込みは超高速ですが、ファイルはすぐに死体で溢れかえります。そのためPostgresは<strong>「VACUUM（バキューム）」</strong>という凶暴な掃除ロボットを裏で走らせます。もしサーバーの負荷が高すぎてVACUUMがサボると、データベースが爆発的に肥大化して死に至ります（UberがPostgresを捨ててMySQLに移行した有名な事件の原因です）。</li>
<li><strong>MySQL InnoDB（Undoログ型MVCC）：</strong> MySQLは賢く、メインのデータファイルには「常に最新バージョンだけ」を置きます。古いバージョンは「Undoログ」という隔離されたゴミ捨て場に押し込みます。おかげでメインテーブルは常に綺麗に保たれます。しかし、社長が長い集計クエリを走らせて「過去」を見たい場合、MySQLはゴミ捨て場（Undoログ）をガサゴソと漁り、パズルを組み立てるように過去のデータを再構築しなければならず、CPUを猛烈に消費します。</li>
</ul>

<h2>現代の真実：インメモリMVCCの極致 🚀</h2>
<p>Per-Åke Larson博士ら（SQL ServerのHekatonエンジンの開発者）は、このMVCCの概念を、現代の<strong>「インメモリ・データベース（すべてをRAMで動かすDB）」</strong>の極致へと押し上げました。</p>

<p>すべてがRAMの上にある場合、遅いディスクブロックのことは忘れて構いません。Larsonの設計では、ある行の「新しいバージョン」から「古いバージョン」へ、RAM上のダイレクトなポインタ（連結リスト）でヒモを繋ぎました。このロック・フリー（鍵を一切使わない）構造により、100コアの最新CPUが光の速さでバージョン間を飛び回り、1秒間に数百万回のトランザクションを処理できる怪物エンジンが誕生しました。</p>

<h2>専門家による批評と、受け継がれるレガシー 🏛️</h2>
<p>MVCCは、過去30年間のデータベース設計において「最も偉大で、最も影響力のある決断」と言えるでしょう。システム設計の苦労を「ユーザーをロックして待たせること（ユーザー体験の破壊）」から、「裏でディスクのゴミ掃除を頑張ること（安いストレージとCPUで解決可能）」へとシフトさせたのです。</p>

<p>もちろん、MVCCは銀の弾丸ではありません。<strong>「書き込みの増幅（Write Amplification）」</strong>という深刻な副作用をもたらします。Postgresでは、たった1ビットのフラグを `true` に変えるだけで、2キロバイトの行データを丸ごとコピーして書き直さなければなりません。そして、Autovacuum（自動ゴミ収集）のチューニングは、現在でも熟練DBAの「黒魔術」であり続けています。</p>

<p>しかし、それでも歴史の審判は下りました。情報の「読み込み（Read）」が圧倒的多数を占める現代のインターネット社会において、「未来の書き込みを止めることなく、過去を平和に読み続けられる」MVCCアーキテクチャは、同時実行制御における絶対的な王として君臨しているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'MVCC：現代インターネットを陰で支えるデータベースの「タイムマシン」構造',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['MVCC', 'Concurrency', 'PostgreSQL', 'Garbage Collection']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 15 (MVCC Memory)!\n";
