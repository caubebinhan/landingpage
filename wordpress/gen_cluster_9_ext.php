<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'architectural_era_1784013832028.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Architectural Era',
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

$cat_en = setup_term('Database History & Theory', 'category', 'en');
$cat_vi = setup_term('Lịch Sử & Lý Thuyết Database', 'category', 'vi');
$cat_ja = setup_term('データベースの歴史と理論', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> "The End of an Architectural Era" is a provocative 2007 paper by Michael Stonebraker. It declares the death of the "One Size Fits All" monolithic Relational Database Management System (RDBMS) like traditional Oracle or SQL Server installations.</li>
<li><strong>The Core Problem:</strong> For 30 years, companies used a single monolithic database engine to do everything: process user transactions (OLTP), run heavy analytical reports (OLAP), and handle text searches. Because the database engine had to be "okay" at everything, it was mathematically impossible for it to be "excellent" at anything. The overhead of making a database general-purpose made it 50x slower than specialized engines.</li>
<li><strong>The Solution:</strong> Stonebraker argued for a complete rewrite of database architecture into specialized, purpose-built engines. An In-Memory row-store for transactions (OLTP), a Column-Store for analytics (OLAP), and Stream Processing engines for real-time data.</li>
<li><strong>Modern Reality:</strong> This paper accurately predicted the modern cloud architecture known as <strong>Polyglot Persistence</strong>. Today, a single application might use Redis for caching, PostgreSQL for transactions, Snowflake for analytics, and Elasticsearch for text search. The monolith is dead; the specialized engine won.</li>
</ul>

<h2>Historical Context & The Catalyst: The Monolithic Monopolies</h2>
<p>If you walked into a Fortune 500 company in the year 2000, their entire IT infrastructure was likely running on a single, massive Oracle or IBM DB2 database server. This was the era of the "Elephants." These databases were incredible feats of engineering. They were designed to be a "One Size Fits All" solution. You could run a banking transaction on them, and later that night, the CEO could run a massive aggregation query to calculate quarterly revenue on the exact same database.</p>

<p>But as the internet scaled, cracks began to show in the monolith. Amazon and Google were generating data at a scale that these general-purpose elephants simply could not digest. Why were they choking? Because general-purpose RDBMSs carry an enormous amount of "baggage."</p>

<p>To be safe, reliable, and capable of handling any query imaginable, a traditional RDBMS spends 90% of its CPU time doing things other than actually reading or writing data. It spends CPU cycles on:</p>
<ol>
<li><strong>Buffer Pool Management:</strong> Constantly moving 8KB pages between slow disk and fast RAM.</li>
<li><strong>Locking:</strong> Ensuring two users don\'t overwrite each other\'s data.</li>
<li><strong>Latching:</strong> Ensuring the internal data structures of the database don\'t get corrupted by multi-threading.</li>
<li><strong>Recovery (WAL):</strong> Writing everything to a Write-Ahead Log in case the server loses power.</li>
</ol>

<p>Stonebraker looked at this and realized a terrifying truth: <strong>In a traditional RDBMS, only 10% of the CPU is actually doing useful work.</strong> The rest is pure overhead to maintain the illusion of a general-purpose machine.</p>

<h2>The Academic Breakthrough: The Death of "One Size Fits All"</h2>
<p>Stonebraker\'s 2007 paper was a declaration of war against the database monopolies. He argued that the era of a single database doing everything was over. The future belonged to highly specialized, stripped-down engines that did exactly one thing perfectly.</p>

<p>He divided the database world into three distinct workloads, proving mathematically that a single engine could not satisfy them all:</p>

<h3>1. OLTP (Online Transaction Processing)</h3>
<p>These are your e-commerce checkouts and bank transfers. High volume, tiny reads/writes, absolute consistency required. Stonebraker argued that because modern servers have so much RAM, the entire OLTP database should live <em>In-Memory</em>. By removing the hard drive entirely, you eliminate the Buffer Pool overhead. Furthermore, by running transactions sequentially on a single thread (like Redis or modern VoltDB), you eliminate Locking and Latching overhead. An in-memory, single-threaded engine can be 50x faster at OLTP than a traditional Oracle database.</p>

<h3>2. OLAP (Online Analytical Processing)</h3>
<p>These are your business intelligence reports. "Give me the average sales of all red shoes in Europe over the last 5 years." You don\'t need real-time locks for this. You need to scan billions of rows. Stonebraker argued that OLAP requires a <strong>Column-Store</strong> architecture (like C-Store or modern Snowflake/Redshift). By storing data by column instead of by row, you can compress the data massively and skip reading irrelevant columns. A Column-Store is 100x faster at analytics than a traditional Row-Store RDBMS.</p>

<h3>3. Stream Processing</h3>
<p>This is IoT sensor data or stock market ticks. Data arrives constantly and you need to trigger alerts in real-time. A traditional RDBMS requires you to write the data to disk first, then query it. Stonebraker argued this is backwards. Stream processing engines (like modern Apache Kafka or Flink) flip the database inside out: the query is stationary, and the data flows through it.</p>

<h2>Deep Architectural Walkthrough: Why You Can\'t Have Both</h2>
<p>Why can\'t we just build one magical database that has an In-Memory engine for OLTP and a Column-Store for OLAP? This is the holy grail known as <strong>HTAP (Hybrid Transactional/Analytical Processing)</strong>.</p>

<p>The architectural conflict is fundamental:</p>
<ul>
<li><strong>OLTP</strong> wants data stored in <strong>Rows</strong> so that inserting a new user (Name, Age, Address) can be written as a single, contiguous block of memory.</li>
<li><strong>OLAP</strong> wants data stored in <strong>Columns</strong> so that summing the `Age` column of a billion users requires reading a single, contiguous block of memory without reading the `Name` or `Address`.</li>
</ul>

<p>If you try to do OLTP on a Column-Store, inserting one user requires writing to 50 different files (one for each column), destroying write performance. If you try to do OLAP on a Row-Store, summing the `Age` column requires reading the entire `Name` and `Address` data into memory just to throw it away, destroying read performance.</p>

<p>Stonebraker\'s conclusion: You must separate them. You run your fast In-Memory Row-Store for the live app, and every night, you use an ETL (Extract, Transform, Load) pipeline to copy the data over to your massive Column-Store for the analysts.</p>

<h2>Modern Production Reality: Polyglot Persistence</h2>
<p>Look at the architecture of any modern tech giant today (Netflix, Uber, Airbnb). They do not use a single database. They use an architecture known as <strong>Polyglot Persistence</strong>. This is exactly what Stonebraker predicted.</p>

<p>A modern microservices architecture looks like this:</p>
<ul>
<li><strong>Redis:</strong> In-memory key-value store for session caching.</li>
<li><strong>PostgreSQL/Aurora:</strong> Row-store for the core OLTP transactional billing engine.</li>
<li><strong>MongoDB:</strong> Document store for flexible user profiles.</li>
<li><strong>Elasticsearch:</strong> Specialized inverted-index engine for the search bar.</li>
<li><strong>Snowflake/BigQuery:</strong> Cloud-native Column-Store for the data science team.</li>
<li><strong>Kafka:</strong> Stream processing for real-time event logging.</li>
</ul>

<p>We have completely dismantled the monolithic RDBMS into specialized components.</p>

<h2>Expert Critique & Legacy</h2>
<p>Stonebraker\'s paper is one of the most accurate technological prophecies of the 21st century. It broke the mental monopoly of the "General Purpose Database" and gave engineers permission to use the right tool for the exact job.</p>

<p>However, Polyglot Persistence has introduced a new nightmare: <strong>Operational Complexity and Data Synchronization</strong>. If your user data is scattered across Postgres, Elasticsearch, and Snowflake, how do you keep them all in sync? When a user deletes their account, how do you ensure the deletion propagates across all five specialized databases? The industry spent the last decade building complex ETL pipelines, Debezium Change Data Capture (CDC) streams, and Kafka queues just to glue these specialized engines back together.</p>

<p>Today, we are seeing a slight pendulum swing back. Systems like SingleStore or Google HTAP are trying to merge OLTP and OLAP back together using complex memory-tiering and background column-conversion. The "One Size Fits All" monolith may be dead, but the quest to tame the chaos of specialized engines is the defining challenge of modern data engineering.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The End of an Era: Why the "One Size Fits All" Database Died',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Michael Stonebraker', 'OLTP', 'OLAP', 'Polyglot Persistence']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> "The End of an Architectural Era" (Sự Sụp Đổ Của Một Kỷ Nguyên Kiến Trúc) là bài báo mang tính khiêu khích năm 2007 của Michael Stonebraker. Nó tuyên bố bản án tử hình cho mô hình "Cái gì cũng làm được" (One Size Fits All) của các Database nguyên khối khổng lồ (như Oracle, SQL Server).</li>
<li><strong>Vấn đề giải quyết:</strong> Suốt 30 năm, các công ty dùng một con Server Database duy nhất để làm MỌI THỨ: Xử lý giao dịch mua hàng (OLTP), chạy báo cáo phân tích khổng lồ (OLAP), và cả tìm kiếm văn bản. Vì phải "ôm đồm" mọi thứ, Database bị phình to (Bloat). Stonebraker chứng minh rằng 90% sức mạnh CPU của Database bị lãng phí cho các tác vụ quản lý overhead (Khóa, Latch, Buffer), khiến nó chậm hơn 50 lần so với các Database chuyên dụng.</li>
<li><strong>Giải pháp (Workflow):</strong> Đập nát con quái vật nguyên khối ra. Tương lai thuộc về các Database chuyên biệt: In-Memory Row-Store cho Giao dịch siêu tốc (OLTP), Column-Store cho Phân tích dữ liệu lớn (OLAP), và Stream Engine cho dữ liệu thời gian thực.</li>
<li><strong>Thực tiễn Production:</strong> Lời tiên tri này đã tạo ra bức tranh kiến trúc Microservices ngày nay: <strong>Polyglot Persistence (Đa ngôn ngữ lưu trữ)</strong>. Một App hiện đại sẽ xài Redis để Cache, Postgres để tính tiền, ElasticSearch để tìm kiếm, và Snowflake để làm báo cáo. Monolith đã chết, chuyên môn hóa đã lên ngôi.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Đế Chế Của Những Con Voi Nguyên Khối</h2>
<p>Nếu bạn bước chân vào phòng Server của một tập đoàn tài chính năm 2000, bạn sẽ thấy một cỗ máy duy nhất, trị giá hàng triệu đô, chạy cơ sở dữ liệu Oracle hoặc IBM DB2. Giới IT gọi chúng là "Những con voi" (Elephants). Khẩu hiệu của chúng là: "One Size Fits All" (Một cỡ vừa cho tất cả). Bạn có thể dùng cỗ máy đó để thực hiện một giao dịch chuyển tiền (siêu nhanh, siêu an toàn), và đến tối, ông Giám đốc có thể chạy một câu lệnh SQL scan hàng tỷ dòng để tính doanh thu quý trên chính cái Database đó.</p>

<p>Nhưng khi thời đại Internet bùng nổ, Google và Amazon xuất hiện. Dữ liệu đổ về như sóng thần. Và những "Con voi" bắt đầu hụt hơi. Tại sao một cỗ máy đắt tiền như Oracle lại bị sập? Nguyên nhân nằm ở "Chi phí vận hành ngầm" (Overhead).</p>

<p>Để đảm bảo một cỗ máy có thể làm được MỌI TRÒ mà không bị lỗi, một RDBMS (Database quan hệ truyền thống) phải tự gánh trên lưng một bộ giáp cực nặng. Khi bạn chạy một câu SQL, CPU không thực sự dành thời gian để đọc dữ liệu. Nó dành 90% chu kỳ CPU để làm 4 việc vớ vẩn sau:</p>
<ol>
<li><strong>Buffer Pool:</strong> Hì hục bốc dữ liệu từ ổ cứng chậm chạp nhét lên RAM, rồi lại xả từ RAM xuống ổ cứng.</li>
<li><strong>Locking (Khóa):</strong> Đảm bảo 2 user không vô tình ghi đè dữ liệu của nhau. CPU phải liên tục check xem dòng này có ai đang khóa không.</li>
<li><strong>Latching:</strong> Khóa các cấu trúc dữ liệu nội bộ (như B-Tree) để chống đụng độ giữa các luồng (Multi-threading).</li>
<li><strong>Recovery (Ghi Log):</strong> Phải chép mọi hành động ra một cuốn sổ (WAL) để lỡ cúp điện còn khôi phục được.</li>
</ol>

<p>Stonebraker nhìn vào biểu đồ Profiling và kết luận một sự thật tàn nhẫn: <strong>Trong một Database truyền thống, chỉ có 10% sức mạnh CPU là thực sự làm việc có ích (lấy dữ liệu). 90% còn lại là chi phí quản lý bộ máy.</strong> Nó quá cồng kềnh.</p>

<h2>Đột Phá Học Thuật: Lệnh Tử Hình Cho "One Size Fits All"</h2>
<p>Bài báo năm 2007 của Stonebraker như một quả bom ném thẳng vào mặt các ông lớn Oracle và Microsoft. Ông dõng dạc tuyên bố: Kỷ nguyên của một Database làm mọi việc đã kết thúc. Tương lai thuộc về các "Cỗ máy chuyên biệt" (Specialized Engines) được gọt giũa trần trụi để làm ĐÚNG MỘT VIỆC với tốc độ bàn thờ.</p>

<p>Ông chia thế giới Database làm 3 chiến trường, và chứng minh bằng toán học rằng không một cỗ máy nào có thể vô địch cả ba:</p>

<h3>1. Chiến trường OLTP (Giao dịch Online)</h3>
<p>Đây là lúc User bấm nút "Thanh Toán". Lượng truy cập khổng lồ, mỗi lần chỉ sửa 1-2 dòng dữ liệu, yêu cầu độ trễ tính bằng mili-giây. Stonebraker lập luận: Máy chủ hiện đại có hàng trăm GB RAM, tại sao cứ phải dùng ổ cứng? Hãy nhét TOÀN BỘ Database lên RAM (In-Memory). Loại bỏ ổ cứng, ta triệt tiêu được Overhead của Buffer Pool. Hơn nữa, thay vì dùng đa luồng (Multi-threading) rối rắm, hãy cho hệ thống chạy Đơn luồng (Single-thread) như Redis. Chạy đơn luồng thì không bao giờ sợ đụng độ, thế là ta triệt tiêu luôn Overhead của Locking và Latching. Kết quả? Một In-Memory Database chuyên biệt có thể xử lý giao dịch <strong>nhanh gấp 50 lần</strong> Oracle truyền thống.</p>

<h3>2. Chiến trường OLAP (Phân tích Dữ liệu)</h3>
<p>Đây là lúc team Data Science cần thống kê: "Lấy trung bình lương của toàn bộ nhân viên trong 10 năm qua". Bạn không cần Locking ở đây vì chẳng ai sửa dữ liệu lúc đang thống kê cả. Cái bạn cần là Scan 1 tỷ dòng siêu nhanh. Stonebraker chỉ ra rằng: Nếu dùng Row-Store (Lưu theo Hàng) truyền thống, để lấy cột "Lương", ổ cứng phải đọc luôn cả cột "Tên", "Tuổi", "Địa chỉ" rồi vứt đi, cực kỳ lãng phí I/O. Giải pháp là <strong>Column-Store (Lưu theo Cột)</strong>. Dữ liệu cột Lương được lưu sát nhau, nén lại cực nhỏ. Một Column-Store chuyên biệt (như ClickHouse hay Snowflake) có thể chạy báo cáo <strong>nhanh gấp 100 lần</strong> Database thường.</p>

<h3>3. Chiến trường Stream Processing (Xử lý dòng chảy)</h3>
<p>Dữ liệu chứng khoán hay IoT đổ về hàng triệu event mỗi giây. Bạn cần cảnh báo ngay lập tức nếu nhiệt độ quá cao. Database truyền thống bắt bạn phải GHI dữ liệu xuống đĩa xong rồi mới được QUERRY. Quá chậm! Stonebraker bảo: Hãy lật ngược vấn đề lại. Hãy đặt câu Query đứng yên một chỗ, và cho Dòng dữ liệu (Stream) chảy xuyên qua nó. Đó là khởi thủy của các hệ thống như Apache Kafka hay Flink ngày nay.</p>

<h2>Giải Phẫu Kiến Trúc: Tại Sao Không Thể Nhồi Nhét?</h2>
<p>Nhiều người sẽ hỏi: <em>"Tại sao không gộp cái In-Memory (OLTP) và cái Column-Store (OLAP) vào chung một phần mềm cho tiện?"</em> Khái niệm này gọi là <strong>HTAP (Hybrid Transactional/Analytical Processing)</strong>.</p>

<p>Câu trả lời nằm ở sự xung đột vật lý tận cùng:</p>
<ul>
<li><strong>OLTP</strong> thèm khát dữ liệu lưu theo <strong>Hàng (Row)</strong>. Khi tạo một User mới (Tên, Tuổi, Địa chỉ), nó muốn viết toàn bộ cụm đó vào một khối RAM liền kề nhau bằng 1 nhát ghi duy nhất.</li>
<li><strong>OLAP</strong> thèm khát dữ liệu lưu theo <strong>Cột (Column)</strong>. Khi tính tổng Tuổi, nó muốn đọc một dãy bộ nhớ chỉ chứa toàn số Tuổi nằm sát nhau.</li>
</ul>

<p>Nếu bạn cố tình làm OLTP trên Column-Store, việc tạo 1 User mới sẽ bắt ổ cứng phải chọc vào 50 file khác nhau (tương ứng với 50 cột) để ghi, tốc độ Ghi sẽ sụp đổ. Nếu bạn làm OLAP trên Row-Store, ổ cứng sẽ phải đọc hàng tấn rác (Tên, Địa chỉ) chỉ để lấy ra cái Tuổi, tốc độ Đọc sẽ sụp đổ.</p>

<p>Kết luận của Stonebraker: Chia để trị. Bạn dùng Row-Store siêu tốc cho App chạy Live. Và cứ đến 12h đêm, bạn dùng một hệ thống luân chuyển dữ liệu (ETL Pipeline) để copy toàn bộ dữ liệu ngày hôm đó, xào nấu lại, và ném sang cái Column-Store siêu to khổng lồ cho đội Phân tích dữ liệu làm việc vào sáng hôm sau.</p>

<h2>Thực Tiễn Production: Sự Hỗn Loạn Của Polyglot Persistence</h2>
<p>Hãy nhìn vào bản vẽ hệ thống (Architecture) của Uber, Netflix hay Shopee hiện tại. Bạn sẽ không thấy một con Oracle Database nào ôm sô từ A-Z. Bạn sẽ thấy một mớ hỗn độn (nhưng có tổ chức) được gọi là <strong>Polyglot Persistence (Đa ngôn ngữ lưu trữ)</strong>. Lời tiên tri của Stonebraker đã thành hiện thực 100%.</p>

<ul>
<li><strong>Redis:</strong> Đóng vai trò In-Memory để Cache Session.</li>
<li><strong>PostgreSQL/MySQL:</strong> Row-Store chuẩn ACID để trừ tiền ví điện tử.</li>
<li><strong>MongoDB:</strong> Lưu thông tin User Profile với cấu trúc JSON thiên biến vạn hóa.</li>
<li><strong>Elasticsearch:</strong> Lưu Inverted-Index để phục vụ cái thanh Search Bar gõ tìm kiếm sản phẩm.</li>
<li><strong>Snowflake / ClickHouse:</strong> Cái kho khổng lồ (Data Warehouse) lưu lịch sử 10 năm để chạy AI và báo cáo.</li>
<li><strong>Kafka:</strong> Cái ống nước khổng lồ luân chuyển dữ liệu giữa các hệ thống trên.</li>
</ul>

<p>Chúng ta đã thành công trong việc xé xác con khủng long Monolith ra thành những linh kiện xe đua F1 chuyên biệt.</p>

<h2>Bình Luận Chuyên Gia & Trái Đắng (Expert Critique & Trade-offs)</h2>
<p>Bài báo của Stonebraker đã giải phóng tư duy của giới kỹ sư. Nó cho phép chúng ta quyền được chọn "Đúng công cụ cho đúng bài toán", thay vì bị trói buộc vào một hệ thống của Oracle.</p>

<p>Tuy nhiên, kiến trúc Polyglot Persistence đã đẻ ra một con quái vật mới tàn độc không kém: <strong>Sự phức tạp trong vận hành và Đồng bộ dữ liệu (Data Synchronization)</strong>. Giả sử dữ liệu User của bạn đang nằm rải rác ở Postgres, ElasticSearch và Snowflake. Khi User ấn nút "Xóa tài khoản", làm sao bạn đảm bảo lệnh xóa đó được thực thi thành công ở cả 3 Database? Nếu hệ thống mạng chập chờn, Postgres xóa xong nhưng ElasticSearch không nhận được lệnh, dữ liệu của bạn sẽ bị "Vênh" (Inconsistency).</p>

<p>Ngành IT đã phải dành trọn 10 năm qua chỉ để đi dọn bãi rác này. Chúng ta đẻ ra hệ thống Change Data Capture (Debezium), dùng Kafka Event-Sourcing để cố gắng dán những chiếc Database chuyên biệt này lại với nhau thành một khối thống nhất.</p>

<p>Hôm nay, con lắc lịch sử lại đang đảo chiều. Các ông lớn đang cố gắng chế tạo ra các HTAP Database mới (như TiDB, SingleStore, Google Spanner) cố gắng nhét cả Row-Store và Column-Store vào chung một phần mềm bằng các thuật toán ảo hóa bộ nhớ cực kỳ phức tạp. Con quái vật Monolith có thể đã chết, nhưng khát vọng về một chiếc "Chén thánh" có thể giải quyết mọi bài toán Data mà không cần bảo trì 5 hệ thống khác nhau vẫn luôn là nỗi ám ảnh vĩnh cửu của giới Software Engineering.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Sự Sụp Đổ Của Kỷ Nguyên Nguyên Khối: Tại Sao Bạn Phải Dùng 5 Loại Database Cùng Lúc?',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Michael Stonebraker', 'OLTP', 'OLAP', 'Polyglot Persistence']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 「アーキテクチャの時代の終焉（The End of an Architectural Era）」は、データベース界の巨匠マイケル・ストーンブレーカーが2007年に発表した過激な論文です。OracleやSQL Serverのような、「どんな処理でも一つでこなす（One Size Fits All）」巨大な万能データベースの「死」を宣告しました。</li>
<li><strong>根本的な問題：</strong> 過去30年間、企業は1つの巨大なデータベースで「ユーザーの決済（OLTP）」「巨大な売上集計（OLAP）」「テキスト検索」などをすべてこなそうとしてきました。しかし、何でもできる「万能マシン」は、裏で膨大な管理作業（ロック、バッファ管理など）を行っており、CPUの90%を無駄にしています。結果として、専用のデータベースに比べて「50倍」も遅くなっていました。</li>
<li><strong>解決策：</strong> 巨大な万能マシンを解体し、「一つのことだけを完璧にこなす専用エンジン」に分割すべきだと主張しました。決済には超高速な「インメモリ型（OLTP）」、データ分析には集計に特化した「カラムストア型（OLAP）」、そしてリアルタイム処理には「ストリーム型」を使い分けるべきだという提案です。</li>
<li><strong>現代の真実：</strong> この論文の予言は完全に的中し、現在のモダンな開発現場では<strong>「ポリグロット・パーシステンス（適材適所の複数データベース利用）」</strong>が当たり前になりました。1つのアプリの裏側で、Redis（キャッシュ）、PostgreSQL（決済）、Elasticsearch（検索）、Snowflake（分析）が同時に連携して動く時代になったのです。</li>
</ul>

<h2>歴史的背景：巨大な「万能の象（エレファント）」の時代 🐘</h2>
<p>2000年頃の大企業のサーバールームに入ると、そこには数億円もする超巨大なサーバーが鎮座し、OracleやIBM DB2といった巨大なデータベースが動いていました。IT業界では、これらを「巨大な象（Elephants）」と呼んでいました。彼らのスローガンは<strong>「One Size Fits All（フリーサイズ：これ一つで何でもできる）」</strong>でした。昼間は全国のATMの振り込み処理を超高速でこなし、夜中には社長が見るための「過去10年分の売上集計レポート」を同じシステム上で計算できたのです。</p>

<p>しかし、GoogleやAmazonといったインターネットの巨人たちが登場し、扱うデータの量が「億」から「兆」へと桁違いに膨れ上がると、この「万能の象」は息切れし始めました。なぜ、数億円のシステムがクラッシュしたのでしょうか？ それは「万能であるための代償（オーバーヘッド）」が重すぎたからです。</p>

<p>伝統的なリレーショナルデータベース（RDBMS）は、どんなクエリが来ても絶対に壊れないように、鉄壁の防御システムを持っています。しかしストーンブレーカーは、データベースのCPUが「実際にデータを読み書きする作業」に使っている時間は、たったの<strong>10%</strong>しかないことを暴きました。残りの90%のCPUパワーは、以下のような「裏方の管理作業」に浪費されていたのです。</p>
<ol>
<li><strong>バッファプール管理：</strong> 遅いハードディスクと速いメモリ（RAM）の間で、データをひたすら出し入れする作業。</li>
<li><strong>ロッキング（Locking）：</strong> 2人のユーザーが同時に同じデータを書き換えて壊さないように、データに「鍵」をかける作業。</li>
<li><strong>ラッチング（Latching）：</strong> データベース内部のツリー構造がマルチスレッド処理で壊れないようにする微細な排他制御。</li>
<li><strong>リカバリ（WAL）：</strong> 突然の停電に備えて、すべての行動を逐一日記（ログ）に書き留める作業。</li>
</ol>
<p>「万能」であるために、システムは重い鎧を着込んで身動きが取れなくなっていたのです。</p>

<h2>学術的ブレイクスルー：「万能データベース」への死刑宣告 ⚔️</h2>
<p>ストーンブレーカーの2007年の論文は、データベース業界への宣戦布告でした。彼は「一つのデータベースですべてをまかなう時代は終わった」と宣言し、今後の未来は「無駄な鎧を脱ぎ捨て、たった一つのタスクだけを極限の速度でこなす専用エンジン（Specialized Engines）」の時代になると予言しました。</p>

<p>彼はデータベースの戦場を3つに分け、それぞれに最適な「専用の武器」を定義しました。</p>

<h3>1. OLTP（オンライン・トランザクション処理：決済など）の戦場</h3>
<p>「ユーザーが購入ボタンを押す」「残高を更新する」など、細かいデータを一瞬で書き換える処理です。ストーンブレーカーは主張しました。「今のサーバーには大量のメモリ（RAM）がある。なら、ハードディスクを捨てるべきだ！ データをすべてメモリに乗せれば（インメモリ型）、バッファ管理の無駄が消える。さらに、複雑なマルチスレッドをやめてシングルスレッドで順番に処理すれば、データに鍵をかける（ロックする）無駄な作業も消える」。この設計で作られた専用エンジンは、従来のOracleより<strong>50倍</strong>も速くトランザクションを処理できました。</p>

<h3>2. OLAP（オンライン分析処理：ビッグデータ集計）の戦場</h3>
<p>「過去5年間の、赤い靴の平均売上を出して」といった、何十億行ものデータをスキャンする処理です。ここでは、データを「行（Row）」ではなく<strong>「列（Column）」</strong>ごとにまとめて保存する<strong>カラムストア型（Column-Store）</strong>が必要です。「売上」の列だけが物理的に固まって保存されているため、他の余計なデータ（名前や住所など）を読み込む無駄を省けます。これにより、データ分析のスピードは従来比で<strong>100倍</strong>に跳ね上がりました。</p>

<h3>3. ストリーム処理（リアルタイムデータ）の戦場</h3>
<p>株価の変動やIoTセンサーなど、毎秒数百万件のデータが流れ込んでくる処理です。従来のデータベースは「まずディスクに保存してから、あとで検索（クエリ）する」という順番でした。しかしストーンブレーカーは、「検索条件（クエリ）を固定して待ち構え、そこにデータ（ストリーム）を通過させるべきだ」と主張しました。これが現在のApache Kafkaなどのストリーム処理の原型です。</p>

<h2>アーキテクチャの徹底解剖：なぜ「合体」できないのか？ 🧩</h2>
<p>多くの人がこう思います。「インメモリ型（OLTP）とカラムストア型（OLAP）のいいとこ取りをして、一つのソフトに合体させればいいじゃないか！」（これをHTAPと呼びます）。</p>

<p>しかし、物理学的にそれは不可能です。</p>
<ul>
<li><strong>OLTP（決済）</strong>は、ユーザーを新規登録するとき、「名前、年齢、住所」という一塊のデータを、ハードディスクの「連続した一つの場所」に一気に書き込みたいのです（<strong>行指向：Row-Store</strong>）。</li>
<li><strong>OLAP（分析）</strong>は、全員の「年齢」を足し算するとき、「年齢だけ」が連続して並んでいる場所を一気に読み込みたいのです（<strong>列指向：Column-Store</strong>）。</li>
</ul>

<p>もし、列指向のデータベースで「ユーザーの新規登録」を行おうとすると、「名前のファイル」「年齢のファイル」「住所のファイル」など、バラバラのファイルに少しずつ書き込みに行かなければならず、書き込み速度が致命的に遅くなります。逆に、行指向のデータベースで「年齢の平均」を計算しようとすると、無関係な名前や住所のデータまで大量にディスクから読み込まなければならず、読み込み速度が崩壊します。</p>

<p>結論：これらは絶対に分離しなければなりません。普段のWebアプリは超高速なOLTP（行指向）で動かし、夜中になると「ETL（データ抽出・変換・ロード）」というバッチ処理を使って、1日分のデータをOLAP（列指向の巨大なデータウェアハウス）にコピーして、翌日の分析に備えるのです。</p>

<h2>現代の真実：ポリグロット・パーシステンス（適材適所のカオス） 🌪️</h2>
<p>現代のUber、Netflix、メルカリのような巨大IT企業のシステム構成図を見てみましょう。そこには巨大な万能データベースは存在しません。代わりに、<strong>「ポリグロット・パーシステンス（Polyglot Persistence：複数の異なるデータストアの使い分け）」</strong>と呼ばれる、見事な（そして複雑な）マイクロサービス・アーキテクチャが広がっています。</p>

<ul>
<li><strong>Redis：</strong> セッション情報を爆速で読み書きするインメモリ・キャッシュ。</li>
<li><strong>PostgreSQL：</strong> 課金や決済を絶対に間違えないための堅牢なリレーショナル（OLTP）。</li>
<li><strong>MongoDB：</strong> ユーザーの柔軟なプロフィールデータを保存するドキュメントストア。</li>
<li><strong>Elasticsearch：</strong> 商品検索の入力窓のための、テキスト検索専用エンジン。</li>
<li><strong>Snowflake / BigQuery：</strong> データサイエンティストがAIを回すための巨大なカラムストア（OLAP）。</li>
<li><strong>Kafka：</strong> これらすべてのデータベース間で、変更履歴をリアルタイムに受け渡す巨大な土管。</li>
</ul>

<p>ストーンブレーカーの予言通り、万能の象は解体され、私たちはF1マシンのような専用エンジンの集合体を手に入れました。</p>

<h2>専門家による批評と、私たちが払う代償 ⚖️</h2>
<p>この論文は、エンジニアたちを「データベースはOracle一つでなければならない」という呪縛から解放し、「適材適所で最高のツールを使う自由」を与えました。21世紀で最も正確に未来を言い当てた技術的予言の一つです。</p>

<p>しかし、自由には代償が伴います。ポリグロット・パーシステンスは<strong>「運用管理の地獄」と「データ同期の悪夢」</strong>を生み出しました。ユーザーのデータがPostgres、Elasticsearch、Snowflakeの3カ所に散らばっている場合、ユーザーが「退会ボタン」を押したとき、どうやって3つのデータベースから同時に、矛盾なくデータを消去するのでしょうか？ ネットワークが瞬断してElasticsearchだけデータが消え残ったらどうなるのでしょうか？ この10年間、IT業界は「Change Data Capture（CDC）」などの複雑な分散システム技術を使って、バラバラになったデータベースを「接着剤でくっつける作業」に膨大な労力を費やしてきました。</p>

<p>そして今、歴史の振り子は少しだけ戻ろうとしています。Google SpannerやTiDBのような最新のデータベースは、複雑なメモリ階層と分散技術を駆使して、「OLTPとOLAPをもう一度、1つのシステムに統合しよう（HTAP）」という無謀とも思える夢に再び挑戦し始めています。「万能の象」は死にましたが、「すべてのデータを一つで管理したい」というエンジニアの究極の欲望は、決して消えることはないのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '万能データベースの死：なぜ現代のWebアプリは5種類のDBを同時に使うのか',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Michael Stonebraker', 'OLTP', 'OLAP', 'Polyglot Persistence']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 9 (Architectural Era)!\n";
