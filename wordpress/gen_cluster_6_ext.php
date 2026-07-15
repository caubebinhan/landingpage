<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'lsm_tree_merge_1783999314101.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'LSM-Tree',
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

$cat_en = setup_term('Database Architecture', 'category', 'en');
$cat_vi = setup_term('Kiến Trúc Database', 'category', 'vi');
$cat_ja = setup_term('データベースアーキテクチャ', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> The Log-Structured Merge-Tree (LSM-tree) is a modern database data structure optimized for environments with insanely high write volumes. It is the engine behind NoSQL giants like Cassandra, RocksDB, and LevelDB.</li>
<li><strong>The Core Problem:</strong> Traditional B-Trees are optimized for fast <em>reads</em>, but they struggle with heavy <em>writes</em>. Inserting data into a B-Tree requires "Random I/O"—jumping around the hard drive to find the right page to update. On mechanical hard drives (and even SSDs), random writes are devastatingly slow.</li>
<li><strong>The Solution:</strong> The LSM-Tree turns all writes into "Sequential I/O". It never updates data in place. Instead, it writes new data to an append-only log in memory (MemTable). When the MemTable is full, it flushes it to disk as an immutable file (SSTable).</li>
<li><strong>Modern Reality:</strong> Because data is scattered across multiple immutable files, reading becomes slower (Read Amplification). To fix this, LSM-Trees rely on Bloom Filters to quickly skip irrelevant files, and run background "Compaction" jobs to merge files together. It is a calculated trade-off: Sacrificing some read speed to achieve millions of writes per second.</li>
</ul>

<h2>Historical Context & The Catalyst: The Fall of the B-Tree</h2>
<p>For decades, the B-Tree was the undisputed king of database indexing. It was designed in 1970 and ruled the relational database world (Oracle, SQL Server, MySQL, Postgres). B-Trees are brilliant because they keep data sorted on disk, allowing for extremely fast search queries (O(log N)).</p>

<p>But around 2006, the world changed. The era of Big Data and Web 2.0 arrived. Companies like Facebook, LinkedIn, and Amazon were no longer just storing user profiles. They were tracking every single click, every "Like", every IoT sensor reading, and every server log. The workload shifted from "Read-Heavy" to "Write-Heavy".</p>

<p>The B-Tree began to choke. When you insert a new row into a B-Tree, the database has to physically find the correct 8KB block on the hard drive, read it into memory, modify it, and write it back. This is called an <strong>In-Place Update</strong>, and it forces the disk head to seek randomly across the platters (Random I/O). If a server receives 100,000 writes per second, the disk head physically cannot move fast enough. The database grinds to a halt. We needed a data structure that never looked backwards, only forwards.</p>

<h2>The Academic Breakthrough: Embrace the Append-Only Log</h2>
<p>In 1996, Patrick O\'Neil published a paper introducing the Log-Structured Merge-Tree. The fundamental philosophy was radical: <strong>Stop trying to sort data as it arrives. Just write it down as fast as possible.</strong></p>

<p>Hard drives (both HDDs and SSDs) have a quirky physical property: They are incredibly fast at writing data sequentially (appending to the end of a file), but terribly slow at writing data randomly. The LSM-Tree was designed specifically to exploit this hardware quirk. It treats the database not as a filing cabinet, but as a diary.</p>

<h2>Deep Architectural Walkthrough: The Anatomy of an LSM-Tree</h2>
<p>An LSM-Tree is not a single tree. It is a multi-stage pipeline consisting of several different data structures working in harmony.</p>

<h3>Stage 1: The MemTable (RAM)</h3>
<p>When a write request (e.g., <code>SET user_42_status = "Online"</code>) arrives, the database does not touch the disk. It writes the data directly into an in-memory tree called the <strong>MemTable</strong> (usually a Red-Black Tree or Skip List). Writing to RAM is instantaneous. To prevent data loss in case of a power outage, the database simultaneously appends the command to a raw text file on disk called the <strong>Write-Ahead Log (WAL)</strong>.</p>

<h3>Stage 2: The SSTable Flush (Disk)</h3>
<p>Eventually, the MemTable gets full (e.g., reaches 64MB). The database freezes the MemTable and creates a new one for incoming writes. The frozen MemTable is then "flushed" (written) to the hard drive in one massive, sequential swoop. Because the MemTable was a sorted tree in RAM, the resulting file on disk is also perfectly sorted. This file is called a <strong>Sorted String Table (SSTable)</strong>.</p>
<p><strong>Crucial Rule:</strong> SSTables are <em>Immutable</em>. Once written, they are never, ever modified. If User 42 changes their status to "Offline", the database simply writes a <em>new</em> entry into the MemTable, which will eventually be flushed into a <em>newer</em> SSTable. The old data remains on disk as a tombstone or an outdated record.</p>

<h3>Stage 3: The Read Path and Bloom Filters</h3>
<p>Because data is never updated in place, the read path becomes complicated. To find User 42\'s status, the database must ask:</p>
<ol>
<li>Is it in the MemTable? (Check RAM).</li>
<li>If not, check the newest SSTable on disk.</li>
<li>If not, check the next oldest SSTable on disk.</li>
<li>Keep checking until found.</li>
</ol>
<p>If you have 500 SSTables, reading a single key could require 500 disk reads. This is called <strong>Read Amplification</strong>. To prevent this, LSM-Trees use a probabilistic data structure called a <strong>Bloom Filter</strong>. Before checking an SSTable, the database asks the Bloom Filter: "Is User 42 in this file?" The Bloom Filter can definitively say "No", allowing the database to instantly skip the file without touching the disk.</p>

<h3>Stage 4: Compaction (The Garbage Collector)</h3>
<p>Over time, you will accumulate thousands of overlapping SSTables. To prevent the disk from filling up with outdated records (like the old "Online" status), a background thread constantly runs a process called <strong>Compaction</strong>. It takes several old SSTables, merges them together (like a zipper), deletes the outdated keys, and writes a single, clean, new SSTable. This keeps the read speed healthy.</p>

<h2>Modern Production Reality: The Great Trade-offs</h2>
<p>The LSM-Tree is an exercise in extreme trade-offs. It optimizes Write performance at the expense of Read performance and CPU usage.</p>

<ul>
<li><strong>Write Amplification:</strong> Although writes are sequential, Compaction means that a single byte of data will be rewritten to disk multiple times over its lifespan as files are merged. This can wear out SSDs faster.</li>
<li><strong>Space Amplification:</strong> Because old data is not immediately deleted, an LSM database often requires more disk space than the actual size of the dataset.</li>
</ul>

<p>Despite these flaws, LSM-Trees dominate the modern NoSQL landscape. <strong>Apache Cassandra</strong> uses them to ingest millions of metrics per second for Apple and Netflix. <strong>RocksDB</strong> (developed by Facebook) is an embedded LSM key-value store that is so fast, other databases (like CockroachDB and MySQL\'s MyRocks engine) simply use it as their underlying storage engine.</p>

<h2>Expert Critique & Legacy</h2>
<p>The LSM-Tree represents a paradigm shift in system design: moving away from mutable state towards immutable, event-sourced logs. It acknowledges that in a distributed, high-throughput world, locking a row on a disk to update it is a recipe for deadlock and latency.</p>

<p>However, operating an LSM-Tree in production is notoriously difficult. Tuning the Compaction algorithms (Size-Tiered vs. Leveled Compaction) is a dark art. If your write rate exceeds your compaction rate, your database will drown in unmerged SSTables, and your read latency will spike to catastrophic levels (a phenomenon known as an "LSM Stall").</p>

<p>Ultimately, the LSM-Tree proves that there is no "One Size Fits All" in database architecture. The B-Tree remains the king of Read-Heavy transaction processing, but the LSM-Tree is the undisputed emperor of the Write-Heavy, Big Data era.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'LSM-Tree: The Engine Behind Modern Big Data and NoSQL',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['LSM-Tree', 'Database Architecture', 'NoSQL', 'RocksDB', 'Cassandra']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Khái niệm cốt lõi:</strong> Log-Structured Merge-Tree (LSM-Tree) là một cấu trúc dữ liệu cơ sở dữ liệu hiện đại, được sinh ra để thống trị các hệ thống có lưu lượng Ghi (Write) khổng lồ. Nó là trái tim của các đế chế NoSQL như Cassandra, RocksDB, và LevelDB.</li>
<li><strong>Vấn đề giải quyết:</strong> Cây B-Tree truyền thống rất giỏi Đọc (Read), nhưng cực kỳ chật vật khi Ghi. Để cập nhật 1 dòng trong B-Tree, ổ cứng phải nhảy cóc (Random I/O) tìm đúng vị trí để ghi đè. Tốc độ ổ cứng cơ học (và cả SSD) bị sụt giảm thê thảm khi phải ghi Random liên tục.</li>
<li><strong>Giải pháp (Workflow):</strong> LSM-Tree biến mọi thao tác Ghi thành "Ghi Tuần Tự" (Sequential I/O). Nó KHÔNG BAO GIỜ ghi đè dữ liệu cũ. Thay vào đó, nó gom dữ liệu mới vào RAM (MemTable). Khi RAM đầy, nó xả toàn bộ cục dữ liệu đó xuống ổ cứng thành một file chỉ đọc (SSTable).</li>
<li><strong>Thực tiễn Production:</strong> Vì dữ liệu bị rải rác ở nhiều file, việc Đọc sẽ bị chậm (Read Amplification). Để khắc phục, LSM dùng màng lọc Bloom Filter để bỏ qua các file không chứa dữ liệu, và chạy một con bot dọn rác ngầm (Compaction) để gộp các file lại với nhau. Đây là một sự đánh đổi có chủ đích: Hy sinh tốc độ Đọc để đạt được hàng triệu request Ghi mỗi giây.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Sự Gục Ngã Của Đế Vương B-Tree</h2>
<p>Trong suốt 4 thập kỷ, B-Tree là vị vua không ngai của thế giới Database. Nó là bộ não đứng sau Oracle, SQL Server, MySQL, và PostgreSQL. B-Tree cực kỳ thông minh vì nó luôn giữ dữ liệu trên ổ cứng ở trạng thái được sắp xếp. Bạn muốn tìm kiếm một User? B-Tree tìm ra nó trong vòng vài mili-giây với thuật toán $O(\log N)$. Tuyệt vời!</p>

<p>Nhưng rồi thời đại Web 2.0 ập đến (khoảng năm 2006). Facebook, LinkedIn, Amazon xuất hiện. Họ không chỉ lưu hồ sơ User nữa. Họ lưu TỪNG CÁI CLICK CHUỘT, từng lượt Like, từng tọa độ GPS của thiết bị IoT, từng dòng log của máy chủ. Cán cân Database đảo chiều: Từ "Đọc nhiều hơn Ghi" (Read-Heavy) chuyển sang <strong>"Ghi tàn bạo hơn Đọc" (Write-Heavy)</strong>.</p>

<p>Lúc này, B-Tree bắt đầu hộc máu. Khi bạn chèn một dòng mới vào B-Tree, hệ thống phải tìm đúng cái Block 8KB trên ổ cứng, nạp lên RAM, sửa nó, rồi Ghi đè lại xuống đĩa cứng. Thao tác này gọi là <strong>Ghi Đè Tại Chỗ (In-Place Update)</strong>. Nó bắt cái đầu từ của ổ cứng HDD phải giật cục liên tục khắp mặt đĩa (Random I/O). Bất chấp ổ cứng xịn đến đâu, nếu có 100.000 lượt Ghi ập đến mỗi giây, giới hạn vật lý của cơ học sẽ làm Database sập toàn tập. Chúng ta cần một cấu trúc dữ liệu mới: Không bao giờ nhìn lại quá khứ, chỉ nhắm mắt và viết về phía trước.</p>

<h2>Đột Phá Học Thuật: Biến Database Thành Cuốn Nhật Ký (Append-Only Log)</h2>
<p>Năm 1996, Patrick O\'Neil xuất bản bài báo giới thiệu Log-Structured Merge-Tree. Triết lý của nó vô cùng ngạo mạn và đột phá: <strong>Đừng cố gắng sắp xếp dữ liệu ngay lúc nó vừa chạy vào. Cứ nhắm mắt mà ghi nó xuống ổ cứng càng nhanh càng tốt!</strong></p>

<p>Giới kỹ sư nhận ra một đặc tính vật lý tối quan trọng của ổ cứng (kể cả HDD cũ kỹ lẫn SSD NVMe đắt tiền): Ổ cứng Ghi Tuần Tự (Ghi nối đuôi vào cuối file) thì cực kỳ nhanh (có thể lên tới hàng Gigabyte/s), nhưng Ghi Ngẫu Nhiên (Tìm một dòng ở giữa file để sửa) thì chậm như rùa. LSM-Tree được thiết kế để bú trọn cái đặc tính "Ghi Tuần Tự" này. Nó không coi Database là một cái tủ hồ sơ được sắp xếp ngăn nắp nữa, nó coi Database là một Cuốn Nhật Ký (Append-Only Diary).</p>

<h2>Giải Phẫu Kiến Trúc: Động Cơ LSM-Tree Hoạt Động Ra Sao?</h2>
<p>LSM-Tree không phải là một cái Cây đơn lẻ. Nó là một dây chuyền sản xuất đa tầng, kết hợp nhiều cấu trúc dữ liệu khác nhau.</p>

<h3>Giai đoạn 1: MemTable (Nằm trên RAM)</h3>
<p>Khi có lệnh <code>UPDATE diem_so = 100 WHERE user = 42</code> bay vào, Database... lờ đi cái ổ cứng. Nó ghi dòng đó thẳng vào một cái Cây (thường là Red-Black Tree) nằm trong bộ nhớ RAM gọi là <strong>MemTable</strong>. Việc ghi vào RAM có tốc độ sấp sỉ vận tốc ánh sáng. (Để chống mất dữ liệu khi cúp điện, nó ghi thêm một dòng text vào cái file log thô trên đĩa cứng gọi là WAL - Write Ahead Log).</p>

<h3>Giai đoạn 2: Xả lũ SSTable (Nằm trên Đĩa cứng)</h3>
<p>Tất nhiên RAM thì có hạn. Khi MemTable phình to đến cỡ 64MB, Database ra lệnh "Đóng băng". Nó tạo một MemTable mới để đón dữ liệu mới. Còn cái MemTable cũ 64MB kia sẽ được "Xả" (Flush) thẳng xuống ổ cứng thành một file duy nhất. Vì MemTable vốn đã được sắp xếp sẵn trên RAM, nên cái file ghi xuống đĩa cứng cũng được sắp xếp hoàn hảo. File này được gọi là <strong>SSTable (Sorted String Table)</strong>.</p>
<p><strong>Luật thép của LSM:</strong> SSTable là <em>Bất Biến (Immutable)</em>. Một khi đã ghi xuống đĩa, không ai được phép mở nó ra để sửa. Nếu User 42 đổi điểm số thành 200, Database chỉ đơn giản là ghi một dòng <em>mới</em> vào MemTable, và sau này dòng mới đó sẽ rớt xuống một SSTable <em>mới hơn</em>. Dữ liệu cũ (điểm 100) vẫn nằm mốc meo ở file cũ trên đĩa cứng.</p>

<h3>Giai đoạn 3: Cuộc chiến Tìm kiếm (Read Path) & Bloom Filters</h3>
<p>Vì bạn không bao giờ ghi đè, dữ liệu bây giờ bị rải rác khắp nơi. Khi cần tìm điểm của User 42, Database phải đi hỏi cung từng thằng:</p>
<ol>
<li>Hỏi RAM (MemTable) trước xem có bản cập nhật mới nhất không?</li>
<li>Không có? Mở file SSTable mới nhất trên ổ cứng ra tìm.</li>
<li>Không có? Mở tiếp file SSTable cũ hơn... Cứ thế cho đến khi tìm ra.</li>
</ol>
<p>Nếu bạn có 500 file SSTable, việc đọc một User có thể khiến ổ cứng phải đọc 500 lần. Thảm họa này gọi là <strong>Read Amplification (Khuếch đại Đọc)</strong>. Để cứu vãn, LSM dùng một ma thuật xác suất gọi là <strong>Bloom Filter</strong>. Trước khi đọc file, hệ thống hỏi Bloom Filter: <em>"Trong file này có User 42 không?"</em>. Bloom Filter có khả năng phán <em>"Chắc chắn Không!"</em> với tốc độ $O(1)$. Nhờ thế, Database bỏ qua được 499 file không liên quan mà không hề tốn một giọt I/O ổ cứng nào.</p>

<h3>Giai đoạn 4: Dọn Rác (Compaction)</h3>
<p>Sau 1 tháng chạy, ổ cứng của bạn sẽ có hàng vạn file SSTable chứa đầy rác (những dòng dữ liệu cũ đã bị ghi đè bởi dòng mới). Để cứu dung lượng đĩa và tăng tốc độ đọc, một tiến trình ngầm gọi là <strong>Compaction (Nén/Gộp)</strong> sẽ chạy liên tục. Nó lấy vài file SSTable cũ, trộn chúng lại với nhau (giống như kéo khóa zíp), vứt bỏ các dòng dữ liệu cũ, và ghi ra một file SSTable mới toanh, sạch sẽ.</p>

<h2>Thực Tiễn Production: Sự Đánh Đổi Tàn Nhẫn</h2>
<p>LSM-Tree là đỉnh cao của nghệ thuật Đánh Đổi (Trade-offs). Nó bán đứng tốc độ Đọc và tài nguyên CPU để đổi lấy tốc độ Ghi vô cực.</p>

<ul>
<li><strong>Khuếch đại Ghi (Write Amplification):</strong> Dù Ghi tuần tự, nhưng quá trình Compaction gộp file sẽ khiến 1 byte dữ liệu của bạn bị copy đi copy lại, ghi xuống ổ cứng nhiều lần trong suốt vòng đời của nó. Điều này làm ổ SSD nhanh bị "chai" hơn bình thường.</li>
<li><strong>Khuếch đại Dung lượng (Space Amplification):</strong> Vì dữ liệu rác không bị xóa ngay lập tức, một Database LSM 100GB thực tế có thể ngốn tới 150GB đĩa cứng cho đến khi Compaction chạy dọn dẹp xong.</li>
</ul>

<p>Bất chấp khuyết điểm, LSM-Tree đang thống trị thế giới Big Data. <strong>Apache Cassandra</strong> dùng kiến trúc này để gánh hàng triệu truy vấn mỗi giây cho Netflix và Apple. <strong>RocksDB</strong> (siêu phẩm của Facebook) mạnh đến mức các hãng khác không thèm tự viết Engine nữa, mà lấy thẳng RocksDB về nhúng vào Database của họ (như CockroachDB hay MyRocks của MySQL).</p>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>LSM-Tree là một cú tát thẳng vào tư duy thiết kế hệ thống cũ: Chuyển từ "Cập nhật thay đổi trạng thái" (Mutable state) sang tư duy "Ghi nhật ký sự kiện bất biến" (Immutable, Event-sourced logs). Nó thừa nhận một chân lý: Trong môi trường phân tán (Distributed System) cần hiệu năng cao, việc Lock (Khóa) một dòng trên đĩa cứng để chờ Ghi đè là con đường ngắn nhất dẫn đến thắt cổ chai và Deadlock.</p>

<p>Tuy nhiên, vận hành một hệ thống LSM-Tree trên Production là một nghệ thuật hắc ám. Việc tinh chỉnh thuật toán Compaction (Size-Tiered hay Leveled) cực kỳ đau não. Nếu tốc độ Ghi của User vào Server diễn ra nhanh hơn tốc độ dọn rác (Compaction) của ổ cứng, Database của bạn sẽ chết chìm trong biển file SSTable, và tốc độ Đọc sẽ bị kéo lê lết thê thảm (hiện tượng này gọi là LSM Stall).</p>

<p>Tóm lại, LSM-Tree chứng minh rằng không có "Viên đạn bạc" trong thiết kế Database. B-Tree vẫn là Hoàng đế của các giao dịch Đọc nhiều (Ngân hàng, ERP). Nhưng LSM-Tree chính là Kẻ Thống Trị Tuyệt Đối của kỷ nguyên Dữ liệu khổng lồ (Big Data), nơi mà luồng dữ liệu đổ về như một cơn sóng thần không bao giờ dứt.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'LSM-Tree: Động Cơ Hủy Diệt Đứng Sau Kỷ Nguyên Big Data Và NoSQL',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['LSM-Tree', 'Database Architecture', 'NoSQL', 'RocksDB', 'Cassandra']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> Log-Structured Merge-Tree（LSM-Tree）は、膨大な量のデータ書き込み（Write）を処理することに特化した、現代のデータベース構造です。Cassandra、RocksDB、LevelDBなどの有名なNoSQLデータベースの「心臓部」として動いています。</li>
<li><strong>根本的な問題：</strong> 昔からあるB-Treeという仕組みは「検索（Read）」は得意ですが、「書き込み（Write）」には弱いです。データを更新するたびにハードディスク上のバラバラな場所に「上書き保存（ランダムI/O）」しに行くため、書き込み量が増えるとディスクが悲鳴を上げて止まってしまいます。</li>
<li><strong>解決策：</strong> LSM-Treeは「上書き保存」を完全にやめました。新しいデータが来たら、とりあえずメモリ（RAM）の中に整理して置いておき、いっぱいになったらディスクの「一番最後」にドバッと追加書き込み（シーケンシャルI/O）します。日記のようにただ追記していくだけなので、書き込み速度が爆発的に速くなります。</li>
<li><strong>現代の真実：</strong> 上書きしないため、ディスクの中に「古いデータ」と「新しいデータ」が散らばってしまい、検索（Read）が遅くなるという弱点があります。これをカバーするために、Bloom Filter（ブルームフィルタ）という魔法の辞書を使って無駄な検索を省いたり、裏側で「コンパクション（ゴミ出し・整理整頓）」という作業を延々と行ったりしています。書き込みスピードのために、複雑なトレードオフを受け入れた究極のアーキテクチャです。</li>
</ul>

<h2>歴史的背景：B-Tree王国の崩壊 🏰</h2>
<p>データベースの歴史において、過去40年間、<strong>「B-Tree」</strong>は絶対的な王様でした。Oracle、SQL Server、MySQL、PostgreSQL... 有名なリレーショナルデータベースはすべてB-Treeで作られています。B-Treeは、データを常に綺麗に並べ替えてハードディスクに保存してくれるため、「ユーザー検索」などを一瞬（$O(\log N)$）で行うことができます。完璧な発明でした。</p>

<p>しかし、2006年頃、Web 2.0とビッグデータの時代が到来し、世界は一変しました。Facebook、LinkedIn、Amazonなどの巨大企業は、もはや「ユーザーのプロフィール」だけを保存しているのではありません。彼らは、ユーザーの「すべてのクリック」「いいね！の履歴」「GPSの移動記録」「IoTセンサーのデータ」など、毎秒数百万件ものデータを記録し始めたのです。データベースの使われ方が、「検索メイン（Read-Heavy）」から<strong>「超・書き込みメイン（Write-Heavy）」</strong>へと逆転しました。</p>

<p>ここで、B-Treeは血を吐いて倒れてしまいました。B-Treeに新しい行を追加しようとすると、ハードディスクの円盤をカリカリと動かして「正しい場所」を探し当て、そこを読み込み、書き換えて、またディスクに戻すという<strong>「インプレース更新（上書き保存）」</strong>が発生します。この「ランダムI/O（あちこちに飛んで書き込む）」は、物理的なハードディスク（HDD）にとって最悪の罰ゲームです。SSDになっても限界があります。毎秒10万件の書き込みが押し寄せると、B-Treeのシステムは完全にフリーズしてしまいます。過去の常識を捨て、「絶対に振り返らず、ただ前へ前へと書き殴る」新しい仕組みが必要になったのです。</p>

<h2>学術的ブレイクスルー：すべてを「追記（Append）」せよ 📝</h2>
<p>1996年、Patrick O\'Neilという学者が「The Log-Structured Merge-Tree（LSM-Tree）」という論文を発表しました。その哲学は非常に過激でした。<strong>「データが来たら、その場で綺麗に並べ替えようとするな。とにかく一番後ろに追記（Append）して、あとで考えろ！」</strong></p>

<p>ハードディスク（HDDもSSDも）には、面白い物理的特性があります。あちこちにランダムに書き込むのは激遅ですが、「ファイルの最後尾にドバーッと連続して書き込む（シーケンシャルI/O）」のは信じられないほど爆速なのです。LSM-Treeは、このハードウェアの特性を限界までハックするために生まれました。データベースを「整理整頓された本棚」として扱うのではなく、「ただひたすら書き連ねる日記帳（Log）」として扱うことにしたのです。</p>

<h2>アーキテクチャの徹底解剖：LSM-Treeはどう動くのか？ ⚙️</h2>
<p>LSM-Treeは、一つの木ではありません。複数のデータ構造がリレーのようにつながった「工場」のような仕組みです。</p>

<h3>ステージ1：メモリ上のMemTable（RAM）</h3>
<p><code>ユーザー42のポイントを100に変更する</code> という更新リクエストが来たとき、LSM-Treeはハードディスクを一切見ません。代わりに、爆速のRAM（メモリ）の中に作られた<strong>MemTable（赤黒木などの構造）</strong>にデータを書き込みます。メモリへの書き込みなので、速度は光速です。（※停電でデータが消えないように、ディスクの隅っこにあるWALというテキストファイルに「今ポイント100にしたよ」というメモだけは残しておきます）。</p>

<h3>ステージ2：SSTableの書き出し（ディスクへFlush）</h3>
<p>メモリは無限ではありません。MemTableのサイズが数十メガバイトになると、データベースはそれを「冷凍保存」し、新しいリクエストを受け付けるための新しいMemTableを用意します。そして、冷凍された古いMemTableを、ハードディスクへ「一筆書き」で一気に書き出します（Flush）。MemTableはすでにメモリ上で綺麗に並べ替えられていたため、ディスクに書き出されたファイルも綺麗に並んでいます。このファイルを<strong>SSTable（Sorted String Table）</strong>と呼びます。</p>
<p><strong>LSMの鉄の掟：</strong> SSTableは「不変（Immutable）」です。一度ディスクに書き込んだら、二度と修正・上書きはしません。もしユーザー42のポイントが200に変わったら、どうするのでしょうか？ 答えは簡単です。新しいMemTableに「ポイント200」という新しい行を書くだけです。ディスクには「ポイント100（古い）」と「ポイント200（新しい）」の2つのファイルが同時に存在することになります。</p>

<h3>ステージ3：検索（Read）の苦難とBloom Filter 🔍</h3>
<p>上書き保存をしないため、データがいろんなファイルに散らかってしまいます。ユーザー42の最新ポイントを知りたいとき、データベースは大変な作業を強いられます。</p>
<ol>
<li>まず、メモリ（MemTable）に最新情報がないか探す。</li>
<li>なければ、ディスクにある一番新しいSSTableを探す。</li>
<li>なければ、その次に新しいSSTableを探す...。</li>
</ol>
<p>もしSSTableファイルが500個あったら、ディスクを500回読みに行かなければなりません（これを「Read Amplification：読み込みの増幅」と呼びます）。これを防ぐため、<strong>Bloom Filter（ブルームフィルタ）</strong>という確率論の魔法使いが登場します。データベースはファイルを開く前に、Bloom Filterに「このファイルにユーザー42はいる？」と聞きます。Bloom Filterは「絶対にいないよ！」と高速（$O(1)$）で答えてくれるため、データベースは無駄なディスク読み込みをスキップできるのです。</p>

<h3>ステージ4：大掃除（Compaction：コンパクション） 🧹</h3>
<p>そのまま何ヶ月も運用していると、ディスクの中に「古いポイント100」のようなゴミデータが含まれたSSTableファイルが何万個も溜まってしまいます。そこで、裏側で「コンパクション」というお掃除ロボットが常に走り続けます。いくつかの古いSSTableファイルをまとめて読み込み、ゴミデータを捨て、最新のデータだけを残した「綺麗で大きな1つのSSTable」に合体（マージ）させて保存し直すのです。</p>

<h2>現代の絶望的なトレードオフと運用現場のリアル ⚖️</h2>
<p>LSM-Treeは、「究極の書き込み速度」を手に入れるために、多くのものを犠牲（トレードオフ）にしたシステムです。</p>

<ul>
<li><strong>書き込みの増幅（Write Amplification）：</strong> コンパクション（ファイルの合体）が裏で延々と行われるため、1つのデータが一生の間に何度も何度もディスクに書き直されることになります。これにより、SSDの寿命（TBW）が通常より早く削られてしまいます。</li>
<li><strong>容量の増幅（Space Amplification）：</strong> ゴミデータがすぐに削除されず、コンパクションされるまでディスクに残るため、実際のデータサイズよりも多くのディスク容量を食いつぶします。</li>
</ul>

<p>これらの弱点があってもなお、ビッグデータ時代においてLSM-Treeの地位は揺るぎません。AppleやNetflixは<strong>Apache Cassandra</strong>（LSMベース）を使って毎秒数千万のデータを飲み込んでいます。Facebookが開発した<strong>RocksDB</strong>はあまりにも優秀なLSMエンジンであるため、他のデータベース（CockroachDBやMySQLのMyRocksエンジンなど）が自作を諦め、コアエンジンとしてそのままRocksDBを採用しているほどです。</p>

<h2>専門家による批評と、LSMが遺したレガシー 🏛️</h2>
<p>LSM-Treeは、システム設計のパラダイムシフトをもたらしました。それは「状態を上書き保存する（Mutable）」という古い考えを捨て、「起きた出来事を追記し続ける（Immutable, Event-sourced）」という新しい世界への移行です。毎秒膨大なリクエストが飛んでくる分散システムの世界において、「データを書き換えるためにハードディスクにロック（鍵）をかける」ことは、システムの死を意味することを悟ったのです。</p>

<p>しかし、本番環境（Production）でLSM-Treeを運用するのは黒魔術のような難しさがあります。もしユーザーからの「書き込みスピード」が、裏側のお掃除ロボット（コンパクション）の処理スピードを上回ってしまうと、ディスクが未整理のSSTableで溢れかえり、ある日突然データベースの応答速度が致命的に遅くなる「LSMストール（失速）」という恐怖の現象を引き起こします。</p>

<p>結局のところ、データベースの世界に「銀の弾丸（すべてを解決する魔法）」はありません。銀行のシステムのように、読み込みが多く確実な上書きが必要な場所では、今でも「B-Tree」が絶対王者です。しかし、クリックログやIoTデータが津波のように押し寄せるビッグデータの世界では、「LSM-Tree」こそが唯一無二の覇王として君臨し続けているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'LSM-Treeの深淵：NoSQLとビッグデータを支える「絶対に上書きしない」データベース',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['LSM-Tree', 'Database Architecture', 'NoSQL', 'RocksDB', 'Cassandra']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 6 (LSM-Tree)!\n";
