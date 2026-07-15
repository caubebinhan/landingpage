<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'ubiquitous_btree.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
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

$cat_en = setup_term('Data Structures', 'category', 'en');
$cat_vi = setup_term('Cấu Trúc Dữ Liệu', 'category', 'vi');
$cat_ja = setup_term('データ構造', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Problem with Binary Trees on Spinning Rust</h2>

<p>Every computer science student learns the Binary Search Tree (BST). It is an elegant, recursive data structure that guarantees $O(\log n)$ search time. If you have 1 million records, a BST can find the exact record you are looking for in just 20 comparisons. For data stored entirely in RAM, the Binary Search Tree is practically perfect.</p>

<p>But databases do not live in RAM. They live on Hard Disk Drives (and later, SSDs). And this is where the mathematical purity of the Binary Tree crashes violently into the physical reality of hardware.</p>

<p>To understand why, you have to look at how a hard drive actually works. A magnetic hard drive consists of spinning platters and a mechanical arm. To read data, the arm must physically swing across the platter to find the correct track (Seek Time), and then wait for the platter to spin to the correct position (Rotational Latency). This mechanical movement takes about 10 milliseconds. This means a disk can only perform about 100 Random Reads per second.</p>

<p>If you store a Binary Tree on a disk, navigating from the root node down to a leaf node requires following pointers scattered randomly across the disk. For a tree with 1 million records, those 20 comparisons translate to 20 Random Disk Seeks. 20 seeks * 10ms = 200 milliseconds just to find a single row. If 1,000 users query the database at the same time, the disk queue explodes, and the database grinds to a halt.</p>

<h2>The Genius of the B-Tree: Optimizing for the Block</h2>

<p>In 1979, Douglas Comer published a paper titled <em>"The Ubiquitous B-Tree"</em>, summarizing a data structure invented by Bayer and McCreight a decade earlier. The B-Tree was designed specifically to solve the Disk Seek problem.</p>

<p>The core insight of the B-Tree is an understanding of <strong>Block I/O</strong>. When a hard drive reads data, it doesn\'t read a single byte. Because moving the mechanical arm is so expensive, once the arm is in position, the drive reads an entire "Block" (typically 4KB or 8KB) of contiguous data at once.</p>

<p>If the disk is going to give us 8KB of data anyway, why are we only storing 1 key in a Binary Tree node? The B-Tree radically changes the shape of the tree. Instead of a tall, skinny tree where each node has 2 children, the B-Tree is a short, incredibly fat tree where each node is exactly the size of a disk block (8KB) and contains hundreds of keys and children pointers.</p>

<h3>The Fanout Factor</h3>
<p>Because a single B-Tree node can hold, say, 200 keys, the tree has a massive "Fanout". The root node has 200 children. The second level has $200 \times 200 = 40,000$ children. The third level has $40,000 \times 200 = 8,000,000$ children.</p>

<p>This means a B-Tree can store 8 million records in a tree that is only 3 levels deep! To find any record among 8 million, the database only has to make 3 Disk Seeks (and usually, the first two levels are cached in RAM, so it actually only takes 1 Disk Seek). The B-Tree transformed a 200ms lookup into a 10ms lookup. It literally saved the database industry.</p>

<h2>The Pain of Page Splits and Rebalancing</h2>

<p>The B-Tree achieves its incredible read performance by ensuring the tree remains perfectly balanced. But this balance comes at a high cost during write operations.</p>

<p>When you insert a new row, the B-Tree must find the correct 8KB leaf node (Page) and insert the data there. But what if that 8KB page is already completely full? The B-Tree must perform a <strong>Page Split</strong>. It creates a brand new 8KB page, moves half the data from the full page to the new page, and then updates the parent node to point to both pages. If the parent node is also full, the split cascades upwards, potentially all the way to the root.</p>

<p>Page splits are extremely expensive. They cause significant Write Amplification and Random I/O on the disk. They also require heavy locking mechanisms (Latches) to prevent other threads from reading the tree while it is physically being ripped in half and glued back together.</p>

<h2>The Legacy of the Ubiquitous Index</h2>

<p>For nearly 40 years, the B-Tree (and its variant, the B+Tree, which stores all data in the leaf nodes) has been the undisputed king of database indexing. Every major RDBMS—PostgreSQL, MySQL, Oracle, SQL Server—is built on the back of the B-Tree.</p>

<p>It is the perfect example of hardware-aware software design. By abandoning the mathematical purity of the Binary Tree and embracing the physical realities of Block I/O and Disk Seeks, the B-Tree created an architecture that has scaled from Megabytes in the 1970s to Terabytes today. It is, as Comer titled his paper, truly ubiquitous.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The Ubiquitous B-Tree: How Hardware Physics Shaped Software',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['B-Tree', 'Indexing', 'Douglas Comer', 'Disk Optimization']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Khi Lý Thuyết Bị Nghiền Nát Bởi Bánh Răng Vật Lý</h2>

<p>Bất kỳ sinh viên IT nào cũng từng phải học qua Cây nhị phân tìm kiếm (Binary Search Tree - BST). Về mặt toán học, nó là một cấu trúc dữ liệu tuyệt mỹ. Nó đảm bảo tốc độ tìm kiếm $O(\log n)$. Nếu bạn có 1 triệu user, một cái Binary Tree chỉ mất đúng 20 bước so sánh là tìm ra user bạn cần. Nếu toàn bộ Database của bạn nằm gọn trong RAM, Binary Tree là một vị thần vô đối.</p>

<p>Nhưng trên thực tế của các hệ thống Enterprise, Database hiếm khi nằm gọn trong RAM. Nó bị quăng xuống Ổ đĩa cứng (HDD, và sau này là SSD). Và chính tại cái ổ đĩa cơ học quay rền rĩ đó, sự thuần khiết toán học của Cây nhị phân đã bị nghiền nát hoàn toàn bởi các định luật vật lý khắc nghiệt.</p>

<p>Hãy nhìn vào cách một cái ổ cứng (HDD) hoạt động. Nó bao gồm các đĩa từ tính xoay điên cuồng và một cánh tay cơ khí gắn đầu đọc. Để đọc 1 byte dữ liệu, cánh tay cơ khí phải vung vẩy quét qua mặt đĩa (Seek Time) để tìm đúng rãnh, và chờ mâm đĩa quay (Rotational Latency) tới đúng cung từ (sector). Động tác vật lý này mất khoảng 10 mili-giây. Tức là, một cái HDD xịn nhất cũng chỉ có thể quét ngẫu nhiên (Random Read) khoảng 100 lần trong 1 giây.</p>

<p>Nếu bạn lưu một cái Binary Tree xuống đĩa, việc đi từ Node gốc (Root) xuống Node lá (Leaf) đòi hỏi bạn phải nhảy múa loạn xạ trên bề mặt đĩa theo các con trỏ. Để tìm 1 user trong 1 triệu user, 20 phép so sánh toán học kia biến thành 20 lần vung tay cơ khí của ổ đĩa. Thời gian chờ lên tới: 20 * 10ms = 200ms cho MỘT LẦN TÌM KIẾM. Nếu công ty bạn có 1.000 user truy cập cùng lúc? Ổ đĩa sẽ bốc khói, hàng đợi (queue) tràn ra, và hệ thống của bạn chính thức "sập tiệm".</p>

<h2>Sự Vĩ Đại Của B-Tree: Tối Ưu Hóa Theo Kích Thước Block</h2>

<p>Năm 1979, Douglas Comer xuất bản bài báo <em>"The Ubiquitous B-Tree" (Cây B-Tree Có Mặt Ở Khắp Mọi Nơi)</em>, tổng hợp lại một phát minh thiên tài của Bayer và McCreight. B-Tree ra đời với một mục đích duy nhất: Cứu rỗi tốc độ I/O của đĩa cứng.</p>

<p>Insight (sự thấu hiểu) đắt giá nhất của B-Tree là sự am hiểu về <strong>Block I/O</strong>. Khi ổ cứng đọc dữ liệu, nó không bao giờ rảnh rỗi đi đọc đúng 1 byte. Vì công vung cánh tay cơ khí quá lớn, nên một khi đã vung tay tới nơi, nó sẽ đọc trọn luôn một khối dữ liệu lớn (Block, thường là 4KB hoặc 8KB) mang về.</p>

<p>Các kỹ sư thiết kế B-Tree lập luận: Nếu đĩa cứng đằng nào cũng ném cho ta 8KB dữ liệu, tại sao ta lại chỉ lưu đúng 1 giá trị vào trong cái Node của Binary Tree? Thật lãng phí! B-Tree đã thay đổi hình dáng của cái cây. Thay vì một cái cây cao lêu nghêu, gầy nhom (mỗi Node đẻ 2 nhánh), B-Tree là một cái cây siêu lùn và siêu béo. Mỗi Node của B-Tree được ép kích thước to đúng bằng 1 Block của đĩa cứng (8KB), và bên trong nó nhồi nhét hàng trăm giá trị (Keys) và hàng trăm con trỏ nhánh (Pointers).</p>

<h3>Hệ Số Fanout Khổng Lồ</h3>
<p>Bởi vì một Node của B-Tree có thể chứa tới 200 con trỏ nhánh, cái cây này phình ra (Fanout) với tốc độ khủng khiếp. Node gốc (Level 1) có 200 nhánh. Xống Level 2, ta có $200 \times 200 = 40.000$ nhánh. Xuống Level 3, ta có $40.000 \times 200 = 8.000.000$ nhánh.</p>

<p>Bạn thấy sự kỳ diệu chưa? B-Tree có thể lưu trữ 8 triệu dòng dữ liệu trong một cái cây chỉ cao đúng 3 tầng! Để tìm bất kỳ dòng nào trong 8 triệu dòng đó, Database chỉ cần thực hiện đúng 3 lần Disk Seeks. Hơn thế nữa, 2 tầng đầu tiên (Root và Level 2) thường rất nhỏ, nên luôn được Cache sẵn trên RAM. Cuối cùng, để tìm 1 user trong 8 triệu user, Database chỉ cần xuống ổ cứng vung tay đúng 1 lần duy nhất (10ms). B-Tree đã biến thời gian chờ 200ms thành 10ms. Nó đã cứu rỗi cả ngành công nghiệp Database.</p>

<h2>Cái Giá Phải Trả Bằng Máu: Page Split Và Rebalancing</h2>

<p>Không có bữa trưa nào miễn phí. B-Tree đạt được tốc độ ĐỌC vô tiền khoáng hậu là nhờ việc nó luôn giữ cho cái cây được cân bằng tuyệt đối (Perfectly balanced). Nhưng cái giá phải trả cho việc duy trì sự cân bằng này khi GHI dữ liệu là cực kỳ tàn khốc.</p>

<p>Khi bạn <code>INSERT</code> một dòng dữ liệu mới, B-Tree phải chui xuống đúng cái Node lá 8KB đó và nhét dữ liệu vào. Nhưng nếu cái Node 8KB đó đã ĐẦY ứ ự thì sao? B-Tree buộc phải thực hiện một cuộc đại phẫu thuật gọi là <strong>Page Split (Chia tách trang)</strong>. Nó phải xin hệ điều hành cấp cho một Node 8KB mới cứng, cưa đôi số lượng dữ liệu ở Node cũ, chuyển một nửa sang Node mới, và cập nhật lại con trỏ ở Node cha. Nếu Node cha cũng đầy, cuộc phẫu thuật này sẽ lây lan (cascade) ngược lên tận Root.</p>

<p>Page Split là cơn ác mộng của hiệu năng. Nó tạo ra vô số thao tác Random I/O trên đĩa. Kinh khủng hơn, trong lúc cái cây đang bị "cưa đôi", hệ thống buộc phải dùng "Latches" (Khóa Mutex cấp thấp) để khóa chặt cái cây lại, cấm không cho các luồng (Threads) khác đọc dữ liệu kẻo đọc nhầm dữ liệu đang rách nát. Dưới tải trọng ghi khổng lồ (Write-heavy), hệ thống sẽ bị treo cứng vì Page Split.</p>

<h2>Di Sản Bất Tử Của Indexing</h2>

<p>Suốt 40 năm qua, B-Tree (và biến thể mạnh mẽ nhất của nó là B+Tree - nơi toàn bộ data được đẩy hết xuống Node lá để duyệt tuần tự cho nhanh) là vị vua tuyệt đối không thể lật đổ của Database Indexing. Mọi đế chế khổng lồ nhất—PostgreSQL, MySQL, Oracle, SQL Server—đều được xây dựng trên bộ khung xương bằng thép của B-Tree.</p>

<p>Nó là một kiệt tác của ngành Kỹ thuật Phần mềm (Software Engineering). Nó dạy cho chúng ta một bài học vĩ đại: Đừng mù quáng tin vào sự hoàn hảo của Toán học trên giấy. Chỉ khi bạn dám bẻ cong Toán học để ôm trọn lấy những sự thật trần trụi và xấu xí của Phần cứng (như Block I/O và Seek Time), bạn mới có thể tạo ra những hệ thống trường tồn với thời gian.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'B-Tree Đã Cứu Ngành Database Như Thế Nào: Sự Thỏa Hiệp Của Toán Học Và Vật Lý',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['B-Tree', 'Indexing', 'Douglas Comer', 'Disk Optimization']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>回る円盤（HDD）と、二分木の絶望的な相性の悪さ 💿💔</h2>

<p>コンピュータサイエンスを学ぶ学生は皆、最初に「二分探索木（Binary Search Tree - BST）」という美しいデータ構造を教わります。それは、データが常に左右に綺麗に枝分かれしていく構造で、検索速度 $O(\log n)$ という素晴らしい数学的な保証を与えてくれます。もし100万件のデータがあったとしても、二分木ならたった「20回」の比較（右か左かを選ぶだけ）で目的のデータを見つけ出すことができます。もしデータがすべてRAM（メモリ）の中にあるなら、二分木は完璧な魔法の杖です。</p>

<p>しかし、現実のデータベースはRAMの中には住んでいません。彼らは、暗くて冷たい「ハードディスクドライブ（HDD）」やSSDの中に住んでいます。そしてこの「ハードディスクという物理的な機械」の上では、二分木の美しい数学は暴力的に粉砕されてしまうのです。</p>

<p>なぜか？ ハードディスクがどうやって動いているかを想像してください。それは、猛スピードで回転する金属の円盤と、レコード針のような「物理的な金属の腕（アーム）」でできています。データを読むためには、アームが円盤の上を物理的にスイングして目的の場所を探し（シーク時間）、円盤が回転して目的のデータが針の下に来るのを待たなければなりません。この「物理的な動き」には約10ミリ秒（1秒の100分の1）という、コンピュータの世界では「永遠」とも思える時間がかかります。</p>

<p>二分木をハードディスクに保存すると、親から子へポインタ（矢印）を辿るたびに、ディスク上のランダムな場所に飛ばされることになります。100万件の中からデータを探すための「20回の比較」は、「ディスクのアームが20回ランダムに動く（Random Seek）」ことに翻訳されます。20回 × 10ミリ秒 = 200ミリ秒。たった1件のデータを探すのに0.2秒もかかってしまうのです！ もし1000人のユーザーが同時にアクセスしたら、ディスクの順番待ちは爆発し、データベースは完全にフリーズしてしまいます。</p>

<h2>B-Treeの天才的なひらめき：「ブロック」に合わせる 🧱</h2>

<p>1979年、ダグラス・カマー（Douglas Comer）は<em>『The Ubiquitous B-Tree（至る所にあるB-Tree）』</em>という論文を発表し、その10年前にバイエルとマクレイトが発明したこのデータ構造の素晴らしさを世に広めました。B-Treeは、まさにこの「ディスクのシーク問題」を解決するためだけに設計された究極の兵器だったのです。</p>

<p>B-Treeの核心にあるひらめきは、ハードディスクの<strong>「ブロックI/O」</strong>という特性への深い理解でした。ハードディスクは、データを「1バイト」ずつ読むことはしません。アームを動かすのに多大なコストがかかるため、一度アームを動かしたら、ついでにまとまった塊（ブロック：通常は4KBや8KB）のデータを一気に読み込んでくるのです。</p>

<p>B-Treeの設計者たちはこう考えました。「どうせディスクが8KBのデータをまとめて持ってくるなら、ノードの中にデータ（キー）を1つしか入れない二分木はアホらしい！」。そこで彼らは木の形を根本から変えました。ひょろ長くて枝が2本しかない二分木の代わりに、B-Treeは「背が極端に低く、横に異常なほど太った木」になったのです。B-Treeの各ノードのサイズは、ディスクのブロックサイズ（8KB）と完全に一致するように作られ、その1つのノードの中に、数百個のキーと数百本の枝（ポインタ）がぎっしりと詰め込まれました。</p>

<h3>驚異の「ファナウト（枝分かれの数）」 🌳</h3>
<p>1つのノードが例えば「200本」の枝を持っているとします（これをファナウトと呼びます）。するとどうなるでしょう？<br>
ルート（1階）には200の枝があります。<br>
2階には $200 \times 200 = 40,000$ の枝があります。<br>
3階には $40,000 \times 200 = 8,000,000$（800万）の枝があります。</p>

<p>なんと、B-Treeを使えば、たった「3階建て」の木の中に、800万件ものデータを収納できるのです！ 800万件の中から目的のデータを探すのに、データベースはディスクをたった「3回」読み込むだけで済みます（しかも1階と2階は通常RAMにキャッシュされているため、実際にディスクを動かすのは1回だけです）。B-Treeは、200ミリ秒の検索時間を10ミリ秒へと魔法のように短縮し、データベース業界を崩壊から救ったのです。</p>

<h2>代償：ページ分割（Page Split）という外科手術 🔪</h2>

<p>検索においては無敵のB-Treeですが、この驚異的なパフォーマンスは「木が常に完璧なバランスを保っていること」によって成り立っています。そして、データを「書き込む（INSERT）」とき、このバランスを維持するための痛ましい代償を支払うことになります。</p>

<p>新しいデータを追加するとき、B-Treeは該当する8KBの葉っぱ（ページ）を見つけてデータを押し込みます。しかし、もしその8KBのページがすでに「満杯」だったらどうなるでしょうか？ B-Treeは<strong>「ページ分割（Page Split）」</strong>という大掛かりな外科手術を行わなければなりません。</p>

<p>システムに新しい8KBの空のページを要求し、満杯になったページの中身を半分にノコギリで切り裂き、半分を新しいページに移動させ、親ノードのポインタを繋ぎ直すのです。もし親ノードも満杯だったら、この分割手術はルート（頂上）に向かって連鎖的に発生します。</p>

<p>ページ分割はパフォーマンスの悪夢です。ディスク上に大量のランダムな書き込みを発生させるだけでなく、手術の最中に他のプログラムが木を読もうとして壊れたデータを見ないように、木全体に「ラッチ（Latch：強力な鍵）」をかけてシステムを一時停止させなければならないからです。</p>

<h2>世界を支配する「至る所にあるインデックス」 👑</h2>

<p>40年以上にわたり、B-Tree（そしてデータをすべて一番下の葉っぱに集めた改良版のB+Tree）は、データベースのインデックス技術における絶対的な王様として君臨し続けてきました。PostgreSQL、MySQL、Oracle、SQL Server……あなたが名前を知っている巨大なデータベースはすべて、このB-Treeの頑丈な背中の上に構築されています。</p>

<p>これは「ハードウェアを意識したソフトウェア設計」の最も完璧な成功例です。机上の美しい数学（二分木）を捨て去り、ディスクアームの動きやブロックI/Oといった「醜くも避けられない物理の現実」を抱きしめたことで、B-Treeは1970年代の数メガバイトの時代から、現代の数十テラバイトの時代に至るまで、信じられないほどスケーリングし続けることができたのです。まさに論文のタイトルの通り、それは「至る所（Ubiquitous）」にあるのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'B-Treeの奇跡：数学の美しさを捨ててハードウェアの物理学に従った理由',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['B-Tree', 'Indexing', 'Douglas Comer', 'Disk Optimization']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 10 (B-Tree) with Categories, Tags, and Translation Links!\n";
