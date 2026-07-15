<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'ubiquitous_btree_1784013925402.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Ubiquitous B-Tree',
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
<li><strong>What is it?</strong> Published in 1979 by Douglas Comer, "The Ubiquitous B-Tree" is the definitive paper explaining the internal workings of the B-Tree (and B+ Tree), the fundamental data structure used by almost every relational database (Postgres, MySQL, Oracle) for the last 40 years.</li>
<li><strong>The Core Problem:</strong> Searching through millions of records on a mechanical hard drive is slow. A standard Binary Search Tree works great in RAM, but on a hard drive, traversing a deep binary tree causes too many random disk seeks. Since disk I/O is 100,000 times slower than RAM, minimizing disk reads is the absolute priority.</li>
<li><strong>The Solution:</strong> The B-Tree is a "fat and shallow" search tree. Instead of a node having only 2 children (like a Binary Tree), a B-Tree node can have hundreds of children. Crucially, the size of a single node is perfectly aligned with the hardware "Block Size" (usually 4KB or 8KB) of the operating system and hard drive.</li>
<li><strong>Modern Reality:</strong> Nearly all modern databases use a variant called the B+ Tree, where data only lives in the leaf nodes, and the leaves are linked together as a Linked List. This allows for incredibly fast range queries (e.g., <code>WHERE price BETWEEN 10 AND 50</code>).</li>
</ul>

<h2>Historical Context & The Catalyst: The Physics of the Hard Drive</h2>
<p>To understand why the B-Tree was invented in 1970 by Rudolf Bayer and Edward McCreight (and later popularized by Comer\'s paper), you must understand the brutal physics of mechanical hard drives (HDDs). A hard drive is literally a spinning magnetic platter with a mechanical arm that physically moves across it.</p>

<p>When software asks for data, the disk arm must physically move to the correct track (Seek Time), wait for the platter to spin to the correct sector (Rotational Latency), and then read the data. This mechanical movement takes roughly 5 to 10 milliseconds. In computer time, 10 milliseconds is an eternity. During that time, a modern CPU could have executed 10 million instructions.</p>

<p>If you store 1 million records in a standard <strong>Binary Search Tree</strong>, the tree will be roughly 20 levels deep. To find a specific record, the computer must traverse from the root to the leaf, making 20 "hops." If the tree is stored on a hard drive, those 20 hops could trigger 20 random disk seeks. $20 \times 10\text{ms} = 200\text{ms}$ just to find one record. If 1,000 users search at once, the system dies.</p>

<p>Computer scientists realized a fundamental rule of database engineering: <strong>Algorithms are irrelevant; the only thing that matters is minimizing Disk I/O.</strong> We needed a tree that was incredibly shallow, requiring at most 3 or 4 disk seeks to find any record out of billions.</p>

<h2>The Academic Breakthrough: High Fan-out and Block Alignment</h2>
<p>The genius of the B-Tree lies in its synergy with the Operating System\'s hardware architecture.</p>

<p>When you ask a hard drive for 1 byte of data, it doesn\'t give you 1 byte. The mechanical overhead is too high. Instead, it reads a whole "Block" or "Page" of data (traditionally 4KB or 8KB) and copies that entire block into RAM. This is called a Page Fault.</p>

<p>The B-Tree exploits this behavior. It dictates that the size of a single Node in the tree must be <em>exactly</em> the size of a Disk Block (e.g., 8KB). Because 8KB is quite large, a single node doesn\'t just hold 1 key and 2 pointers (like a Binary Tree). It can hold hundreds of keys and hundreds of pointers.</p>

<p>This is called the <strong>Fan-out ratio</strong> (or Branching Factor). If a B-Tree has a fan-out of 100, the root node has 100 children. Those children have 10,000 children. Those children have 1,000,000 children. Thus, a B-Tree can store 1 million records while being only 3 levels deep! A search through 1 million records guarantees a maximum of 3 disk seeks. This is the magic of the "fat and shallow" tree.</p>

<h2>Deep Architectural Walkthrough: The B+ Tree Variant</h2>
<p>While the original B-Tree stored actual user data inside the internal routing nodes, almost all modern databases (PostgreSQL, MySQL InnoDB) use a highly optimized variant called the <strong>B+ Tree</strong>.</p>

<p>The B+ Tree enforces two strict rules:</p>
<ol>
<li><strong>Data is only in the Leaves:</strong> The internal (upper) nodes of the tree contain <em>only</em> routing keys and pointers, absolutely no user data. This makes the internal nodes extremely small, meaning you can fit thousands of routing pointers into a single 8KB block. The Fan-out ratio explodes, making the tree even shallower. In production, the root node and first level are usually cached entirely in RAM, meaning a search requires only 1 actual disk read!</li>
<li><strong>Linked Leaves:</strong> Every leaf node at the bottom of the tree has a pointer to its next sibling, forming a Doubly Linked List.</li>
</ol>

<h3>The Power of the Linked List</h3>
<p>Why link the leaves? Because of <strong>Range Queries</strong>. Suppose you run: <code>SELECT * FROM users WHERE age BETWEEN 20 AND 30</code>. </p>
<p>In a standard B-Tree, the database would have to repeatedly traverse the tree from the root down to the leaves for age 20, then age 21, then age 22, causing massive CPU overhead. In a B+ Tree, the database traverses from the root exactly <em>once</em> to find the node containing age 20. Then, it simply walks horizontally across the Linked List at the bottom until it hits age 30. This horizontal scan reads contiguous blocks from the hard drive, triggering <strong>Sequential I/O</strong>, which is incredibly fast.</p>

<h2>Modern Production Reality: Page Splits and Fragmentation</h2>
<p>The B-Tree is an elegant reader, but a violent writer. Because the tree must remain perfectly balanced at all times, inserting new data can trigger catastrophic chain reactions.</p>

<p>When you insert a record and an 8KB leaf node becomes 100% full, the database must perform a <strong>Page Split</strong>. It creates a new 8KB block, moves half the data to the new block, and updates the parent node. If the parent node is also full, <em>it</em> splits. This cascade can reach all the way to the root.</p>

<p>Page splits cause two severe problems in production:</p>
<ol>
<li><strong>Write Amplification:</strong> Inserting 1 byte of data might force the database to rewrite three entire 8KB blocks to disk.</li>
<li><strong>Fragmentation:</strong> When a page splits, the new 8KB block is often allocated at the physical end of the hard drive file. Even though the leaf nodes are logically linked together, they are no longer physically contiguous on the disk. Over time, horizontal Range Queries degenerate from fast Sequential I/O back into slow Random I/O. DBAs must periodically run expensive <code>VACUUM</code> or <code>REBUILD INDEX</code> commands to defragment the tree.</li>
</ol>

<h2>Expert Critique & Legacy</h2>
<p>There is a famous joke in computer science: "What does the \'B\' in B-Tree stand for?" Bayer (the inventor)? Boeing (where he worked)? Balanced? Broad? The creators never officially said. Comer\'s paper joked it stands for "Bayer," but today, we might as well say it stands for "Backbone," because it is the backbone of the global economy.</p>

<p>The B-Tree is a triumph of hardware-software co-design. It proves that you cannot design efficient data structures in a vacuum; you must understand the physical realities of the machine executing them.</p>

<p>While the rise of Write-Heavy workloads and SSDs has shifted some momentum toward LSM-Trees (like Cassandra and RocksDB), the B-Tree remains the undisputed champion for Read-Heavy workloads. Every time you query a relational database, you are riding the fat, shallow branches of a 50-year-old algorithmic masterpiece.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The Ubiquitous B-Tree: The Algorithm That Runs the Global Economy',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['B-Tree', 'B+ Tree', 'Database Architecture', 'Indexing']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> Xuất bản năm 1979 bởi Douglas Comer, "The Ubiquitous B-Tree" là bài báo kinh điển giải phẫu cấu trúc bên trong của B-Tree (và B+ Tree). Đây là cấu trúc dữ liệu nền tảng làm nên sức mạnh của 99% các Relational Database hiện đại (MySQL, Postgres, Oracle) trong suốt 40 năm qua.</li>
<li><strong>Vấn đề giải quyết:</strong> Việc tìm kiếm dữ liệu trên đĩa cứng cơ học (HDD) là cực kỳ chậm do đầu từ phải di chuyển vật lý (Random I/O). Cây nhị phân (Binary Tree) thông thường rất sâu, bắt ổ cứng phải nhảy cóc hàng chục lần để tìm 1 bản ghi. Vì Đĩa cứng chậm hơn RAM 100.000 lần, mục tiêu tối thượng là: Đọc đĩa cứng càng ít lần càng tốt.</li>
<li><strong>Giải pháp (Workflow):</strong> B-Tree là một cái cây "Lùn và Mập". Thay vì chỉ có 2 nhánh như cây nhị phân, 1 Node của B-Tree có thể chứa hàng trăm nhánh con. Đặc biệt, kích thước của 1 Node được ép bằng đúng kích thước Block vật lý của ổ cứng (thường là 4KB hoặc 8KB) để tối ưu hóa I/O.</li>
<li><strong>Thực tiễn Production:</strong> Ngày nay, tất cả Database đều dùng phiên bản nâng cấp là <strong>B+ Tree</strong>. Trong B+ Tree, dữ liệu thật chỉ nằm ở tầng dưới cùng (Leaf nodes), và các lá này được móc nối với nhau thành một danh sách liên kết (Linked List). Điều này giúp các câu truy vấn quét diện rộng (Range Query như <code>BETWEEN 10 AND 50</code>) chạy nhanh như chớp bằng Sequential I/O.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Trận Chiến Vật Lý Của Đĩa Cứng (HDD)</h2>
<p>Để hiểu tại sao Rudolf Bayer và Edward McCreight lại phát minh ra B-Tree vào năm 1970, bạn phải hiểu sự tàn khốc của vật lý đĩa cứng cơ học. Một ổ HDD thực chất là một cái đĩa từ tính xoay tròn, với một cánh tay cơ học đâm thò ra thụt vào để đọc dữ liệu (giống hệt đĩa than máy hát).</p>

<p>Khi phần mềm cần đọc dữ liệu, cánh tay cơ học phải vật lộn chạy đến đúng vòng từ tính (Seek Time), rồi chờ cái đĩa quay cái "cạch" tới đúng vị trí (Rotational Latency). Toàn bộ quá trình vật lý này mất khoảng 5 đến 10 mili-giây. Trong thế giới máy tính, 10 mili-giây là khoảng thời gian bằng cả 1 thế kỷ. Trong 10ms đó, CPU đã có thể chạy xong hàng chục triệu dòng lệnh.</p>

<p>Hãy tưởng tượng bạn có 1 triệu bản ghi lưu trong một <strong>Cây Nhị Phân (Binary Search Tree)</strong>. Cây này sẽ sâu khoảng 20 tầng. Để tìm 1 người, máy tính phải nhảy 20 bước từ đỉnh xuống đáy cây. Nếu cây này nằm trên ổ cứng, 20 bước nhảy = 20 lần cánh tay cơ học phải giật cục (Random Seek). $20 \times 10\text{ms} = 200\text{ms}$ chỉ để tìm đúng 1 dòng! Nếu có 1.000 user cùng tìm kiếm, Database sẽ bốc khói và sập nguồn.</p>

<p>Giới kỹ sư nhận ra một chân lý tuyệt đối của kiến trúc Database: <strong>Thuật toán phức tạp đến đâu không quan trọng, cái duy nhất quan trọng là Phải giảm thiểu tối đa số lần đọc ổ cứng (Disk I/O).</strong> Ngành IT cần một cái cây cực kỳ "Lùn", sao cho chỉ cần tối đa 3-4 lần đọc ổ cứng là tìm được bất kỳ dữ liệu nào trong hàng tỷ dòng.</p>

<h2>Đột Phá Học Thuật: Tỷ Lệ Fan-out Và Căn Chỉnh Block Size</h2>
<p>Sự thiên tài của B-Tree nằm ở chỗ: Nó bắt tay làm hòa với hệ điều hành (OS) và phần cứng.</p>

<p>Khi bạn xin ổ cứng 1 Byte dữ liệu, ổ cứng không bao giờ trả cho bạn 1 Byte. Vì chi phí khởi động cánh tay cơ học quá đắt đỏ, ổ cứng sẽ múc luôn một cục to (gọi là Block hoặc Page - thường là 8KB) và quăng toàn bộ cục 8KB đó lên RAM. Đằng nào cũng tốn công đọc, đọc 1 cục to cho bõ.</p>

<p>B-Tree bám chặt vào đặc tính này. Nó ra luật: Kích thước của 1 Node trong B-Tree phải <em>to bằng đúng 1 Block của ổ cứng (8KB)</em>. Vì 8KB là một không gian rất rộng, 1 Node của B-Tree không chỉ chứa 1 con số và 2 nhánh như Cây nhị phân. 1 Node của B-Tree có thể nhét được hàng trăm con số và hàng trăm nhánh con.</p>

<p>Đây gọi là <strong>Tỷ lệ Fan-out (Hệ số phân nhánh)</strong>. Nếu một B-Tree có Fan-out là 100, thì Node gốc có 100 con. Tầng 2 có 10.000 con. Tầng 3 có 1.000.000 con. Thấy sự vi diệu chưa? B-Tree có thể chứa 1 triệu bản ghi mà chiều cao của cây chỉ là 3 tầng! Nghĩa là để tìm 1 dữ liệu trong 1 triệu dữ liệu, bạn chỉ cần đọc ổ cứng tối đa 3 lần. Đó là ma thuật của cái cây "Lùn và Mập".</p>

<h2>Giải Phẫu Kiến Trúc: Đỉnh Cao Của B+ Tree</h2>
<p>B-Tree nguyên thủy vẫn có một nhược điểm: Nó nhét lẫn lộn cả "Dữ liệu thật" (Data) và "Biển báo rẽ" (Routing Keys) vào các Node bên trên. Do đó, các Database hiện đại (như InnoDB của MySQL hay Postgres) đều dùng phiên bản tiến hóa: <strong>B+ Tree</strong>.</p>

<p>B+ Tree áp đặt 2 luật thép:</p>
<ol>
<li><strong>Dữ liệu thật chỉ nằm ở đáy (Leaf Nodes):</strong> Các Node bên trên (Internal Nodes) bị cấm chứa dữ liệu. Chúng chỉ đóng vai trò là "Biển báo giao thông" (Chứa các ID để rẽ trái/phải). Vì không chứa data, các Node bên trên siêu nhẹ và có thể nhét được hàng ngàn cái biển báo vào 1 Block 8KB. Fan-out tăng bạo chúa, cây càng lùn hơn. Trên thực tế Production, Node gốc và tầng 1 của B+ Tree luôn được Cache sẵn trên RAM. Suy ra: Để tìm 1 dòng dữ liệu, bạn chỉ tốn ĐÚNG 1 LẦN I/O Ổ CỨNG.</li>
<li><strong>Các lá được xâu kim (Linked Leaves):</strong> Ở tầng đáy cùng (tầng Lá chứa dữ liệu thật), mỗi Node sẽ có một sợi dây (Pointer) trỏ sang Node bên cạnh, tạo thành một Doubly Linked List (Danh sách liên kết kép).</li>
</ol>

<h3>Quyền Năng Của Linked List Dưới Đáy</h3>
<p>Tại sao phải xâu các lá lại với nhau? Để giải quyết bài toán <strong>Range Query (Truy vấn theo khoảng)</strong>. Ví dụ: <code>SELECT * FROM users WHERE age BETWEEN 20 AND 30</code>.</p>
<p>Với B-Tree thường, Database phải đi từ đỉnh cây xuống đáy để tìm tuổi 20, xong lại phải leo lên đỉnh đi xuống đáy để tìm tuổi 21... cực kỳ tốn CPU. Nhưng với B+ Tree, Database chỉ đi từ đỉnh xuống đáy ĐÚNG 1 LẦN để tìm Node chứa tuổi 20. Sau đó, nó bỏ qua cái cây, và chỉ việc đi bộ ngang qua sợi dây Linked List ở tầng đáy để gom dữ liệu cho đến khi gặp tuổi 30 thì dừng. Quá trình "đi bộ ngang" này ép ổ cứng đọc các Block nằm cạnh nhau liên tiếp (Sequential I/O), mang lại tốc độ khủng khiếp.</p>

<h2>Thực Tiễn Production: Cơn Đau Của "Page Split" Và Phân Mảnh (Fragmentation)</h2>
<p>B+ Tree là một thiên thần khi Đọc (Read), nhưng lại là một ác quỷ khi Ghi (Write). Đặc tính của nó là phải giữ cho cây luôn Cân Bằng (Balanced). Việc nhét dữ liệu mới vào có thể gây ra phản ứng dây chuyền tàn khốc.</p>

<p>Khi bạn <code>INSERT</code> 1 dòng mới, và cái Block 8KB ở tầng lá bị đầy 100%, Database phải làm một thao tác gọi là <strong>Page Split (Chẻ Node)</strong>. Nó phải xin OS cấp cho một Block 8KB mới toanh, cắt một nửa dữ liệu từ Block cũ ném sang Block mới, rồi leo lên tầng trên sửa lại cái Biển báo rẽ. Nếu tầng trên cũng đầy? Chẻ tiếp tầng trên! Sự kiện chẻ Node này có thể dội ngược lên tận gốc của cây.</p>

<p>Page Split đẻ ra 2 thảm họa trên Production:</p>
<ol>
<li><strong>Khuếch đại Ghi (Write Amplification):</strong> Bạn chỉ ghi thêm 1 Byte dữ liệu, nhưng Database phải bốc tới 3 Block (24KB) ra chẻ đôi và ghi lại xuống ổ đĩa. Nó vắt kiệt I/O của server.</li>
<li><strong>Phân Mảnh Đĩa (Fragmentation):</strong> Khi chẻ Node, cái Block mới 8KB thường bị ghi vào vị trí ngẫu nhiên ở tít cuối ổ cứng. Mặc dù về mặt Logic (dây Linked List) chúng nằm cạnh nhau, nhưng về mặt Vật Lý chúng nằm cách xa nhau vạn dặm trên đĩa cứng. Chạy một thời gian, các Range Query bị phân mảnh sẽ mất đi khả năng Sequential I/O và thoái hóa thành Random I/O chậm chạp. Đó là lý do các ông DBA thỉnh thoảng phải chặn server lúc nửa đêm để chạy lệnh <code>REBUILD INDEX</code> (Xây lại cây từ đầu) để gom các Block về nằm cạnh nhau.</li>
</ol>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Có một câu đố vui kinh điển trong giới khoa học máy tính: "Chữ B trong B-Tree là viết tắt của cái gì?". Là Bayer (Tên tác giả)? Là Boeing (Nơi ông ấy làm việc)? Là Balanced (Cân bằng)? Broad (Rộng)? Tác giả chưa bao giờ chính thức giải thích. Comer đùa rằng nó là "Bayer", nhưng ngày nay, giới kỹ sư gọi nó là "Backbone" (Xương sống), vì nó chính xác là xương sống của nền kinh tế toàn cầu.</p>

<p>B-Tree là một kiệt tác của việc "Thiết kế phần mềm phải dựa trên giới hạn của Phần cứng" (Hardware-Software Co-design). Nó chứng minh một chân lý phũ phàng: Bạn không thể viết ra một thuật toán nhanh nếu bạn nhốt mình trong tháp ngà lý thuyết, bạn phải cọ xát với sự bẩn thỉu và chậm chạp của cơ khí học.</p>

<p>Mặc dù sự lên ngôi của Big Data, ổ cứng SSD và các hệ thống Write-Heavy đã giúp cho kiến trúc đối thủ LSM-Tree (Cassandra, RocksDB) giành được hào quang, nhưng B+ Tree vẫn là vị Hoàng đế không thể bị lật đổ trong thế giới Read-Heavy (Đọc nhiều hơn Ghi). Mỗi lần bạn gõ 1 câu lệnh SQL trên ngân hàng hay mua hàng Shopee, bạn đang trượt trên những cành cây "Lùn và Mập" của một kiệt tác thuật toán đã 50 năm tuổi.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'B-Tree: Kiệt Tác Thuật Toán 50 Năm Tuổi Đang Vận Hành Cả Thế Giới',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['B-Tree', 'B+ Tree', 'Database Architecture', 'Indexing']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 1979年にDouglas Comerが発表した「The Ubiquitous B-Tree（至る所にあるB-Tree）」は、世界中のリレーショナルデータベース（MySQL、PostgreSQL、Oracleなど）の心臓部として過去40年間使われ続けている「B-Tree（およびB+ Tree）」というインデックス構造の仕組みを世界に知らしめた名著です。</li>
<li><strong>根本的な問題：</strong> ハードディスク（HDD）の中から、数千万件のデータを探し出すのは途方もなく時間がかかります。普通の「二分探索木（Binary Tree）」を使うと、木が深くなりすぎてハードディスクの「物理的な読み込み（ランダムアクセス）」が何十回も発生し、システムがフリーズしてしまいます。HDDの読み込みはメモリ（RAM）の10万倍も遅いため、「いかにディスクを読む回数を減らすか」がデータベースの至上命題でした。</li>
<li><strong>解決策：</strong> B-Treeは「極端に背が低く、横に異常に太い」木構造です。一つのノード（節点）が2つではなく「数百個」の枝を持ちます。そして、ノードのサイズをハードディスクが一度に読み込む「ブロックサイズ（通常8KB）」に完璧に一致させることで、驚異的なディスク読み込み効率を実現しました。</li>
<li><strong>現代の真実：</strong> 現代のデータベースはすべて、改良版の<strong>「B+ Tree」</strong>を使用しています。データ本体は一番下（葉っぱ：リーフノード）にしか置かず、しかもその葉っぱ同士を横にヒモで繋ぐ（連結リストにする）ことで、「IDが10から50までのユーザーを取ってくる」といった範囲検索（Range Query）を爆速で処理できるようにしています。</li>
</ul>

<h2>歴史的背景：物理ハードディスク（HDD）との残酷な戦い ⚔️</h2>
<p>B-Treeが1970年にRudolf BayerとEdward McCreightによって発明された理由を理解するには、物理的なハードディスク（HDD）の「残酷な遅さ」を理解しなければなりません。HDDは、高速で回転する磁気ディスクの上を、レコード針のような「アーム（腕）」が物理的に動いてデータを読み書きする機械です。</p>

<p>ソフトウェアがデータを要求すると、アームが「ジジジ...」と指定の場所に移動し（シーク時間）、ディスクが目的の場所まで回転してくるのを待ちます（回転待ち時間）。この「物理的な機械の動き」には、約5〜10ミリ秒（ms）かかります。人間にとっては一瞬ですが、CPUから見れば永遠です。その10ミリ秒の間に、CPUは1000万個の命令を実行できるのです。</p>

<p>もし、100万件のデータを普通の<strong>「二分探索木（Binary Search Tree）」</strong>で保存したとしましょう。木は深さ20階層ほどになります。データを探すために、根元から葉っぱまで「20回のホップ」が必要です。これがハードディスク上に置かれていると、アームが20回ランダムに動くことになります。 $20回 \times 10\text{ms} = 200\text{ms}$。たった1件のデータを検索するのに0.2秒もかかってしまいます。1000人のユーザーが同時に検索したら、サーバーは沈没します。</p>

<p>コンピュータ科学者たちは気づきました。<strong>「計算アルゴリズムの美しさなどどうでもいい。とにかく、ハードディスクへのランダムアクセス（Disk I/O）の回数を減らすことだけが正義だ」</strong>と。数億件のデータであっても、「最大でも3〜4回のディスク読み込み」で発見できる「極端に背の低い木」が必要だったのです。</p>

<h2>学術的ブレイクスルー：異常な分岐数（Fan-out）とブロック・アライメント 🧩</h2>
<p>B-Treeの真の天才性は、OSとハードウェア（ディスク）の物理的な仕組みに「完璧に寄り添った」ことです。</p>

<p>プログラムがハードディスクに「1バイトだけデータちょうだい」とお願いしても、ディスクは1バイトだけを渡してはくれません。機械を動かすコストが高すぎるため、「ついでだからこの周辺の8KB（ブロック）を全部持っていけ！」と、8KBの塊を丸ごとメモリ（RAM）に放り投げてきます（これをページフォールトと呼びます）。</p>

<p>B-Treeはこの性質を悪用（ハック）しました。「どうせ一度に8KB読まれるなら、木のノード（節点）のサイズを、ピッタリ8KBにしてしまおう！」と考えたのです。8KBというのはかなりの大容量です。二分探索木のように「値が1つ、枝が2つ」しか持たないのはもったいない。B-Treeの1つのノードには、数百個の「値」と、数百個の「枝（ポインタ）」をぎっしり詰め込むことができます。</p>

<p>これを<strong>「分岐数（Fan-out ratio）」</strong>と呼びます。もし分岐数が「100」のB-Treeを作ったとしましょう。根っこ（ルート）のノードは100個の子供を持ちます。階層2は「1万個」の子供を持ちます。階層3は「100万個」です。お分かりでしょうか？ 100万件のデータを保存しても、木は「たったの3階層」にしかならないのです！ つまり、100万件の中から1つのデータを検索するのに、ハードディスクを読む回数は「最大でも3回」で済みます。これが、「背が低く横に太い木」の圧倒的な魔法です。</p>

<h2>アーキテクチャの徹底解剖：「B+ Tree」という究極の進化 🧬</h2>
<p>オリジナルのB-Treeは、木の途中のノードにも「実際のデータ」を保存していました。しかし現在、PostgreSQLやMySQLのInnoDBなど、ほぼすべてのデータベースは、これを究極まで進化させた<strong>「B+ Tree（Bプラス・ツリー）」</strong>を使っています。</p>

<p>B+ Treeには、絶対に破ってはならない2つの厳しいルールがあります。</p>
<ol>
<li><strong>データ本体は一番下（葉：Leaf Node）にしか置かない：</strong> 木の上層階（Internal Node）には「右に行け」「左に行け」という道標（ルーティングキー）だけを置き、実データは一切置きません。実データが無いので上層階のノードはスカスカになり、8KBの中に「数千個」の道標を詰め込めるようになります。分岐数はさらに爆発し、木は限界まで低くなります。本番環境（Production）では、木の上層階は常にメモリ（RAM）に乗ったままキャッシュされるため、実際のディスク読み込みは「一番下の葉っぱに触れる時の1回だけ」になることが多いのです。</li>
<li><strong>葉っぱ同士を鎖で繋ぐ（Linked Leaves）：</strong> 一番下の葉っぱたちを、横一直線に「双方向連結リスト（Doubly Linked List）」で繋ぎ合わせます。</li>
</ol>

<h3>連結リストの絶大な力（範囲検索）</h3>
<p>なぜ葉っぱを横に繋ぐのでしょうか？ それは<strong>範囲検索（Range Query）</strong>のためです。たとえば <code>SELECT * FROM users WHERE age BETWEEN 20 AND 30</code> というクエリを打ったとします。</p>
<p>もし葉っぱが繋がっていなければ、データベースは「20歳を探すために上から下へ」「次に21歳を探すために上から下へ」と何度も木を上り下りしなければなりません。しかしB+ Treeなら、上から下へ降りるのは「最初の20歳のノードを見つけるための1回」だけです。そこを見つけたら、あとは木を無視して、一番下の連結リストの鎖を辿りながら横へ横へと歩いていくだけで、30歳までのデータを一網打尽に回収できます。この「横歩き」は、ディスク上の連続した場所を読み込む「シーケンシャルI/O」となるため、信じられないほどのスピードが出ます。</p>

<h2>現代の運用における恐怖：「ページ分割（Page Split）」とフラグメンテーション 💥</h2>
<p>B+ Treeは「読み込み（Read）」に関しては天使ですが、「書き込み（Write）」に関しては時に悪魔になります。木は常にバランス（左右対称）を保たなければならないため、新しいデータを追加するときに大惨事が起きることがあります。</p>

<p>新しくデータを <code>INSERT</code> して、8KBの葉っぱノードが「100%満杯」になってしまったらどうなるでしょうか？ データベースは<strong>「ページ分割（Page Split）」</strong>という大手術を行います。新しく8KBの空箱を用意し、満杯の箱からデータを半分移し替え、さらに親ノードの道標を書き換えます。もし親ノードも満杯だったら？ さらに親も分割します。この分割の連鎖は、最悪の場合、根元（ルート）まで波及します。</p>

<p>この「ページ分割」は、本番環境で2つの重大な問題を引き起こします。</p>
<ol>
<li><strong>書き込みの増幅（Write Amplification）：</strong> たった1バイトのデータを追加しただけなのに、木を分割するために「8KBのブロックを3つ書き換える（24KBのディスク書き込み）」という無駄なI/Oが発生し、サーバーの負荷が跳ね上がります。</li>
<li><strong>断片化（Fragmentation）：</strong> 新しく作られた8KBの箱は、ディスクの空き容量の「一番後ろ」にポツンと配置されます。論理的には鎖で繋がっていても、物理的なハードディスク上では「あちこちに飛び散った状態」になります。長く運用していると、爆速だったはずの範囲検索（横歩き）が、あちこちにディスクアームを動かす「遅いランダムI/O」に劣化してしまいます。そのため、DBA（データベース管理者）は定期的に深夜に <code>REBUILD INDEX</code> などの呪文を唱え、木を1から綺麗に並べ直す大掃除をしなければならないのです。</li>
</ol>

<h2>専門家による批評と、不朽のレガシー 🏛️</h2>
<p>コンピュータサイエンスの界隈には、有名なジョークがあります。「B-Treeの "B" とは何の略なのか？」<br>
Bayer（開発者の名前）でしょうか？ Boeing（彼が働いていたボーイング社）でしょうか？ Balanced（バランス）？ Broad（広い）？ 実は、開発者自身が公式に語ったことは一度もありません。Comerの論文では「BayerのBだろう」と冗談めかして書かれていますが、現代のエンジニアたちは<strong>「Backbone（背骨）のBだ」</strong>と呼んでいます。なぜなら、B-Treeは文字通り、世界経済を支える背骨だからです。</p>

<p>B-Treeは、「ソフトウェアのアルゴリズムは、ハードウェアの物理的な限界と寄り添って初めて真価を発揮する」という（Hardware-Software Co-designの）究極の成功例です。</p>

<p>近年、ビッグデータの波とSSDの普及により、「書き込み特化」のライバルであるLSM-Tree（CassandraやRocksDBなど）に脚光が当たることも増えました。しかし、依然として「読み込みメイン（Read-Heavy）」のトランザクション処理においては、B+ Treeは絶対に倒されることのない無敵の皇帝です。あなたがネットショッピングで商品を検索するたびに、裏側ではこの「50年前の天才が作った、背が低くて太い木」が、静かに世界を支えているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '世界経済を支える背骨「B-Tree」：ハードディスクの物理的限界をハックした50年前の魔法',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['B-Tree', 'B+ Tree', 'Database Architecture', 'Indexing']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 10 (Ubiquitous B-Tree)!\n";
