<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'radix_tree.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'Adaptive Radix Tree',
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

$cat_en = setup_term('In-Memory DB', 'category', 'en');
$cat_vi = setup_term('Database Bộ Nhớ Chính', 'category', 'vi');
$cat_ja = setup_term('インメモリDB', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The B-Tree is Too Slow for Modern CPUs</h2>

<p>For decades, the B-Tree was the ultimate data structure for databases. It was designed to minimize Disk I/O by packing hundreds of keys into large 8KB blocks. But what happens when you build an <strong>In-Memory Database</strong> (like Redis, MemSQL, or HyPer)? When the entire database lives in RAM, there is no disk. There is no Disk I/O. Suddenly, the B-Tree becomes a liability.</p>

<p>When you eliminate the disk, the new bottleneck becomes the <strong>CPU Cache</strong>. Reading from main memory (RAM) takes about 100 nanoseconds. But reading from the CPU\'s L1 Cache takes less than 1 nanosecond. To a modern CPU, fetching data from RAM is like taking a 100-mile road trip. If your data structure forces the CPU to constantly fetch from RAM, you suffer from <strong>Cache Misses</strong>, and your performance plummets.</p>

<p>The B-Tree is notoriously unfriendly to CPU Caches. A B-Tree node contains an array of sorted keys. To find the correct child pointer, the CPU usually performs a Binary Search within the node. Binary Search jumps randomly around the array, defeating the CPU\'s hardware prefetcher and causing massive Cache Misses.</p>

<h2>Enter the Radix Tree (Trie)</h2>

<p>If we want to avoid comparisons and binary searches, we need a different data structure. The <strong>Radix Tree</strong> (also known as a Trie) is an elegant alternative. Instead of comparing entire strings or numbers, a Radix Tree routes the search based on the individual bytes of the key.</p>

<p>If you are searching for the string "APPLE", the root node has an array of 256 pointers (one for each possible byte). You look at the first letter \'A\' (ASCII 65), jump directly to the 65th pointer, and move to the next node. You do this for \'P\', \'P\', \'L\', \'E\'. The search time is exactly equal to the length of the string, regardless of whether the database has 10 records or 10 billion records. It requires zero comparisons, and the memory access pattern is perfectly predictable for the CPU.</p>

<p>But traditional Radix Trees have a fatal flaw: <strong>Memory Waste</strong>. Every node must allocate an array of 256 pointers (occupying 2KB of RAM on a 64-bit machine). If a node only has one child (e.g., the \'P\' after the \'A\'), you are wasting 255 empty pointers. A sparse Radix Tree will devour your server\'s RAM instantly.</p>

<h2>The Masterpiece: The Adaptive Radix Tree (ART)</h2>

<p>In 2013, Viktor Leis published a paper that solved this problem permanently: <em>"The Adaptive Radix Tree: ARTful Indexing for Main-Memory Databases"</em>. ART is currently the fastest indexing structure in the world for in-memory databases, powering systems like DuckDB and HyPer.</p>

<p>The genius of ART is the word <strong>"Adaptive"</strong>. Instead of forcing every node to be a giant 256-pointer array, ART defines four different sizes of nodes: Node4, Node16, Node48, and Node256. When a node is created, it starts as a tiny Node4 (holding up to 4 children). If you insert a 5th child, the tree seamlessly allocates a Node16, copies the data over, and deletes the Node4.</p>

<h3>SIMD to the Rescue</h3>
<p>But how do you quickly search inside a Node16 without doing a binary search? This is where ART flexes its hardware-awareness. It uses <strong>SIMD (Single Instruction, Multiple Data)</strong> instructions available on all modern Intel/AMD processors. The CPU can load all 16 keys into a single 128-bit SIMD register and compare them against your target byte in a single clock cycle.</p>

<h2>The Ultimate Index</h2>

<p>The Adaptive Radix Tree is a triumph of engineering. It combines the $O(k)$ search complexity of a Trie with the memory efficiency of a B-Tree, while exploiting low-level CPU vector instructions to eliminate Cache Misses. It is the perfect example of what happens when software developers stop treating the CPU as a magical black box, and start designing data structures that align perfectly with the silicon.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Why B-Trees Fail in RAM: The Brilliance of the Adaptive Radix Tree',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Adaptive Radix Tree', 'CPU Cache', 'In-Memory', 'Indexing']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Khi B-Tree Trở Thành Gánh Nặng Trong Kỷ Nguyên RAM Khổng Lồ</h2>

<p>Trong suốt 40 năm, B-Tree là vị vua không ngai của giới Database. Nó được thiết kế với một mục đích duy nhất: Giảm số lần đọc ổ cứng (Disk I/O) bằng cách nhét hàng trăm Key vào một Block khổng lồ 8KB. Nhưng chuyện gì sẽ xảy ra nếu công ty của bạn có đủ tiền để mua một Server với 2TB RAM, và bạn quyết định chạy toàn bộ Database trên bộ nhớ chính (<strong>In-Memory Database</strong> như Redis, MemSQL, hay HyPer)?</p>

<p>Khi toàn bộ dữ liệu đã nằm trong RAM, ổ đĩa không còn là nút thắt cổ chai nữa. Lúc này, "kẻ ngáng đường" mới lộ diện: <strong>CPU Cache (Bộ nhớ đệm của CPU)</strong>.</p>

<p>Hãy nhìn vào các con số vật lý: Tốc độ để CPU chui xuống RAM lấy dữ liệu tốn khoảng 100 nanoseconds. Nhưng nếu dữ liệu đã nằm sẵn trong L1 Cache của CPU, nó chỉ tốn chưa tới 1 nanosecond. Đối với một con chip hiện đại chạy ở xung nhịp 4GHz, việc phải xuống RAM lấy dữ liệu giống như việc bạn đang code mà bị bắt đi bộ 100 km để lấy một cốc nước. Hiện tượng này gọi là <strong>Cache Miss (Trượt bộ nhớ đệm)</strong>. Nếu cấu trúc dữ liệu của bạn gây ra quá nhiều Cache Miss, hiệu năng CPU sẽ lao dốc thảm hại.</p>

<p>Và thật trớ trêu, B-Tree lại là kẻ thù không đội trời chung của CPU Cache. Để tìm một phần tử bên trong cái Node 8KB của B-Tree, CPU thường phải dùng thuật toán Tìm kiếm nhị phân (Binary Search). Binary Search nhảy cóc liên tục qua lại giữa các mảng bộ nhớ, làm vô hiệu hóa hoàn toàn bộ phận "Đoán trước dữ liệu" (Hardware Prefetcher) của CPU. Kết quả: CPU liên tục bị Cache Miss và ngồi chơi xơi nước chờ RAM phản hồi.</p>

<h2>Giải Pháp Thay Thế: Radix Tree (Cây Tiền Tố)</h2>

<p>Để giải quyết bài toán này, giới học thuật quay sang một cấu trúc dữ liệu khác: <strong>Radix Tree</strong> (hay còn gọi là Trie). Thay vì phải "so sánh" các Key với nhau như B-Tree, Radix Tree tìm đường đi dựa trên từng Byte của dữ liệu.</p>

<p>Ví dụ, để tìm chữ "APPLE", Node gốc của Radix Tree sẽ có một mảng 256 con trỏ (tương ứng với 256 ký tự ASCII). Bạn chỉ việc lấy chữ \'A\' (mã ASCII 65), nhảy thẳng tắp đến con trỏ thứ 65 để xuống tầng tiếp theo. Tiếp tục như vậy cho \'P\', \'P\', \'L\', \'E\'. Bạn tốn chính xác 5 bước nhảy. Không cần bất kỳ một phép so sánh lớn hơn/nhỏ hơn nào. Thời gian tìm kiếm chỉ phụ thuộc vào độ dài của chuỗi, hoàn toàn không phụ thuộc vào việc Database của bạn có 10 dòng hay 10 tỷ dòng dữ liệu!</p>

<p>Nhưng Radix Tree truyền thống có một điểm yếu chí mạng khiến nó không thể dùng trong Production: <strong>Sự Lãng phí Bộ nhớ (Memory Waste)</strong>. Mỗi Node bắt buộc phải cấp phát sẵn một mảng 256 con trỏ (tốn khoảng 2KB RAM). Nếu Node đó chỉ có đúng 1 nhánh con (ví dụ chữ \'P\' đi sau chữ \'A\'), bạn đang lãng phí 255 con trỏ trống rỗng. Một cái Radix Tree thưa thớt (Sparse) sẽ ngốn sạch 2TB RAM của bạn chỉ trong chớp mắt.</p>

<h2>Kiệt Tác Kỹ Thuật: Adaptive Radix Tree (ART)</h2>

<p>Năm 2013, Viktor Leis đã xuất bản bài luận văn làm chấn động giới In-Memory DB: <em>"The Adaptive Radix Tree: ARTful Indexing for Main-Memory Databases"</em>. ART đã giải quyết triệt để bài toán lãng phí RAM, và hiện đang là cấu trúc Indexing nhanh nhất thế giới, làm trái tim cho các hệ thống như DuckDB và HyPer.</p>

<p>Sự thiên tài của ART nằm ở chữ <strong>"Adaptive" (Thích ứng)</strong>. Thay vì ép mọi Node phải to đùng 256 con trỏ, ART định nghĩa ra 4 loại Node với kích thước tăng dần: Node4, Node16, Node48, và Node256. Khi mới tạo, một nhánh chỉ là một Node4 siêu nhỏ bé (chứa tối đa 4 con trỏ). Khi bạn chèn thêm phần tử thứ 5, ART sẽ mượt mà "Nâng cấp" (Grow) cái Node đó lên thành Node16, copy dữ liệu sang, và xóa cái Node4 cũ đi. Kỹ thuật này giúp ART tiết kiệm RAM không thua kém gì B-Tree.</p>

<h3>Đánh Thức Sức Mạnh Cấp Thấp Của CPU (SIMD)</h3>
<p>Nhưng đợi đã, nếu một Node16 chứa 16 ký tự, làm sao để CPU tìm ra đúng ký tự cần thiết mà không phải lặp qua từng cái? Đây là lúc ART phô diễn sức mạnh kiến trúc phần cứng. ART tận dụng các tập lệnh <strong>SIMD (Single Instruction, Multiple Data)</strong> được tích hợp sẵn trên mọi con chip Intel/AMD hiện đại. CPU có thể nạp cùng lúc cả 16 ký tự vào một thanh ghi Vector 128-bit, và so sánh chúng với ký tự mục tiêu của bạn chỉ trong ĐÚNG 1 CHU KỲ XUNG NHỊP (1 Clock Cycle). Đây là một đẳng cấp tối ưu hóa vượt ra ngoài phạm vi của ngôn ngữ lập trình thông thường.</p>

<h2>Sự Hoàn Hảo Của Phần Mềm Khi Thuận Theo Phần Cứng</h2>

<p>Adaptive Radix Tree là một bản giao hưởng hoàn hảo của Kỹ thuật Phần mềm. Nó kết hợp được tốc độ tìm kiếm $O(k)$ thần tốc của Radix Tree, khả năng nén bộ nhớ tuyệt đỉnh của B-Tree, và khai thác triệt để các tập lệnh Vector cấp thấp của CPU để triệt tiêu hoàn toàn độ trễ Cache Miss. Nó là minh chứng hùng hồn cho chân lý: Code của bạn chỉ có thể chạm đến đỉnh cao của hiệu năng khi bạn ngừng coi CPU là một hộp đen ma thuật, và bắt đầu thiết kế cấu trúc dữ liệu ôm trọn lấy từng đường nét kiến trúc của Silicon.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Khi B-Tree Phản Chủ: Sức Mạnh Tuyệt Đối Của Adaptive Radix Tree',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Adaptive Radix Tree', 'CPU Cache', 'In-Memory', 'Indexing']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>巨大なRAMの時代：B-Treeが「遅すぎる」理由 🐢</h2>

<p>過去40年間、データベースのインデックス（索引）といえば「B-Tree」の独壇場でした。B-Treeは、遅いハードディスクへの読み書き（Disk I/O）を極限まで減らすために、8KBの巨大なブロックの中に何百個ものデータ（キー）を詰め込むように設計されています。しかし、もしあなたの会社がお金持ちで、2TBもの巨大なRAMを搭載したサーバーを購入し、すべてのデータをメモリ上に置く「インメモリ・データベース（Redis、MemSQL、HyPerなど）」を構築したとしたら、何が起きるでしょうか？</p>

<p>データがすべてRAMの上にあるとき、ハードディスクの遅さはもはや問題ではありません。新しい最大の敵は<strong>「CPUキャッシュ（CPU Cache）」</strong>になります。</p>

<p>物理的な数字を見てみましょう。現代のCPUが「RAM」までデータを片道で取りに行くと、約100ナノ秒かかります。しかし、データがすでにCPUのすぐ隣にある「L1キャッシュ」に乗っていれば、1ナノ秒未満で読み取れます。4GHzで猛烈に動いているCPUにとって、RAMまでデータを取りに行くのは「徒歩で100キロ先のコンビニまでお茶を買いに行く」ようなものです。もしあなたのデータ構造が、CPUに何度もRAMまで走らせるような作りになっていたら、<strong>「キャッシュミス（Cache Miss）」</strong>が連発し、パフォーマンスは地に落ちます。</p>

<p>そして皮肉なことに、B-TreeはCPUキャッシュと非常に相性が悪いのです。B-Treeの巨大なノードの中から目的のデータを探すとき、CPUは通常「二分探索（Binary Search）」を行います。二分探索は、配列の中を右へ左へとランダムにジャンプするため、CPUに搭載されている「次に使われるデータを予測してキャッシュに乗せる機能（ハードウェア・プリフェッチャ）」を完全に混乱させます。結果として、CPUはキャッシュミスを起こし、RAMからのデータ到着をボーッと待つ羽目になるのです。</p>

<h2>救世主「Radix Tree（トライ木）」の登場 🌿</h2>

<p>比較（二分探索）をせずにデータを見つけるためには、全く異なるデータ構造が必要です。それが<strong>「Radix Tree（ラディックス・ツリー / トライ木）」</strong>です。Radix Treeは、文字や数字を丸ごと「大小比較」するのではなく、データを「1バイトずつ」のパス（道筋）として辿っていきます。</p>

<p>例えば "APPLE" という文字を探す場合、Radix Treeのルート（根）には256個の矢印（ポインタ）の配列があります。あなたは最初の文字 \'A\'（ASCIIコードの65）を見て、無条件で65番目の矢印へジャンプします。次は \'P\'、その次も \'P\' と辿るだけです。「比較」は一切必要ありません。検索にかかる時間は、データベースに10件のデータがあろうが、100億件のデータがあろうが関係なく、「探したい文字列の長さ（この場合は5ステップ）」と完全に一致します。そして何より、メモリのアクセスパターンが規則正しいので、CPUはキャッシュミスを起こしにくいのです。</p>

<p>しかし、伝統的なRadix Treeには、本番環境で使えない致命的な欠陥がありました。それが<strong>「メモリの浪費（Memory Waste）」</strong>です。すべてのノードが常に「256個の矢印の配列」を確保しなければならないため、もし \'A\' の次に \'P\' しかデータが存在しなくても、残り255個の空っぽの矢印のために無駄なメモリ（約2KB）を消費してしまいます。データがスカスカ（Sparse）な状態のRadix Treeを作ると、数テラバイトのRAMが一瞬で食い尽くされてしまうのです。</p>

<h2>最高傑作：「Adaptive Radix Tree (ART)」の適応力 🦎</h2>

<p>2013年、ヴィクトル・ライス（Viktor Leis）が発表した論文<em>『The Adaptive Radix Tree: ARTful Indexing for Main-Memory Databases』</em>は、このメモリ浪費問題を完全に、そして美しく解決しました。ARTは現在、DuckDBやHyPerなどの最先端インメモリDBの心臓部として動いている、世界最速のインデックス構造です。</p>

<p>ARTの天才的な点は、名前に含まれる<strong>「Adaptive（適応型）」</strong>という言葉にあります。ARTは、すべてのノードを巨大な256サイズにするのではなく、「Node4（4個用）」「Node16」「Node48」「Node256」という4つの異なるサイズのノードを用意しました。新しいノードが作られるときは、最も小さい「Node4」として生まれます。もしそこに5つ目のデータが挿入されたら、ARTは魔法のようにノードを「Node16」へとスムーズに成長（アップグレード）させ、古いNode4を削除するのです。この「適応力」により、ARTはB-Treeに匹敵するほどメモリを節約できるようになりました。</p>

<h3>SIMD命令によるハードウェアの極限利用 ⚡</h3>
<p>しかし、ここで疑問が湧きます。「Node16（16個のデータが入ったノード）」の中から目的の文字を探すとき、結局 <code>for</code> ループで比較するなら遅くなるのでは？<br>
ここでARTは、ハードウェアの深淵なる力を解放します。ARTは、現代のIntelやAMDのCPUに必ず搭載されている<strong>「SIMD（Single Instruction, Multiple Data：単一命令・複数データ）」</strong>という強力なベクトル命令を利用するのです。CPUは、ノード内の16個の文字を「128ビットの巨大なレジスタ」に一発で詰め込み、あなたが探している文字と「たった1回のクロックサイクル」で同時に比較（一括マッチング）してしまいます。これは、普通のプログラミング言語のループ処理を完全に置き去りにする、次元の違う速さです。</p>

<h2>シリコンと完璧に調和したデータ構造 🧬</h2>

<p>Adaptive Radix Tree（ART）は、ソフトウェア・エンジニアリングの金字塔です。Radix Treeの「比較不要な $O(k)$ の検索スピード」と、B-Treeの「メモリ効率の良さ」を融合させ、さらにCPUの低レベルなベクトル命令（SIMD）を悪用してキャッシュミスを完全にゼロにする。これは、「開発者がCPUを魔法のブラックボックスとして扱うのをやめ、シリコンチップの物理的な回路（ハードウェア・アーキテクチャ）と完璧に調和するデータ構造をデザインしたとき、何が起きるのか」を示す、最も美しい例なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'RAMの中でB-Treeは遅すぎる：Adaptive Radix Treeの天才的な設計',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Adaptive Radix Tree', 'CPU Cache', 'In-Memory', 'Indexing']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 12 (Radix Tree) with Categories, Tags, and Translation Links!\n";
