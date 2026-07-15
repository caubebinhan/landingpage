<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'mvcc_memory.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'MVCC In-Memory',
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

$cat_en = setup_term('Transactions & Concurrency', 'category', 'en');
$cat_vi = setup_term('Giao Dịch & Đồng Thời', 'category', 'vi');
$cat_ja = setup_term('トランザクションと並行処理', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Problem with Locking in RAM</h2>

<p>We already know that locks are bad for concurrency. If Transaction A is writing to a row, Transaction B cannot read it. B must wait. This is a necessary evil when data is stored on a slow hard drive. But when you move the entire database into RAM (In-Memory Database), the rules of physics change.</p>

<p>In an In-Memory DB, reading and writing take nanoseconds. But acquiring a lock (using hardware mutexes or latches) also takes nanoseconds, and sometimes hundreds of nanoseconds due to CPU cache invalidation. Suddenly, the overhead of <em>managing the locks</em> becomes more expensive than actually <em>processing the data</em>. If you use traditional locking in a Main-Memory Database, you are throwing away 90% of your CPU power just waiting for locks to clear.</p>

<h2>The MVCC Illusion: Traveling Through Time</h2>

<p>To eliminate locks, modern databases (like PostgreSQL, MySQL InnoDB, and in-memory titans like HyPer) use a brilliant concept called <strong>Multi-Version Concurrency Control (MVCC)</strong>.</p>

<p>The core philosophy of MVCC is simple: <strong>Readers never block Writers, and Writers never block Readers.</strong></p>

<p>How is this possible? By treating the database like a Git repository. When Transaction A wants to update a user\'s balance from $100 to $50, it does <em>not</em> overwrite the $100. Instead, it creates a brand new "Version" of the row with $50. Both the $100 version and the $50 version exist in RAM simultaneously.</p>

<p>Every transaction is assigned a unique "Timestamp" when it starts. If Transaction B started <em>before</em> Transaction A committed, Transaction B is essentially living in the past. When B queries the database, the MVCC engine looks at the timestamps and routes B to the older $100 version. B sees a perfectly consistent snapshot of the past, completely unaware that A is busy writing new data in the present.</p>

<h2>The Garbage Collection Nightmare</h2>

<p>MVCC is a magical illusion for the application developer, but it creates a massive headache for the database engine: <strong>Garbage Collection</strong>. If every <code>UPDATE</code> creates a new version of a row, the RAM will quickly fill up with millions of obsolete, dead versions. PostgreSQL users are painfully familiar with this; it is called "Table Bloat", and it is why the <code>VACUUM</code> process exists.</p>

<p>In 2015, Thomas Neumann published a paper titled <em>"Fast Serializable Multi-Version Concurrency Control for Main-Memory Database Systems"</em>, outlining the state-of-the-art MVCC implementation used in the HyPer database. Neumann solved the garbage collection problem with a brilliant optimization.</p>

<h3>The Undo-Buffer Chain</h3>
<p>Instead of polluting the main data table with multiple versions, HyPer stores only the <em>latest, newest</em> version in the main table. Whenever a transaction updates a row, it takes the <em>old</em> data and pushes it into a small, thread-local "Undo Buffer". These old versions are linked together in a linked list (a version chain).</p>

<p>If an older transaction needs to read the past, it goes to the main table, sees the data is too new, and follows the pointer down the Undo Buffer chain until it finds the version that matches its timestamp. Because the Undo Buffers are thread-local and ephemeral, the moment the old transaction finishes, the database can instantly and cheaply throw away the old versions. No massive <code>VACUUM</code> process is required. No table bloat. Just pure, lock-free performance.</p>

<h2>Conclusion</h2>

<p>MVCC is the reason modern databases can handle massive read-heavy workloads (like serving a website) while simultaneously handling complex background updates. By embracing the concept of "Time" and treating data as immutable snapshots, MVCC elegantly sidesteps the deadlocks and bottlenecks that plagued early database systems.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Time Travel in the Database: The Magic of MVCC and Lock-Free Reads',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['MVCC', 'HyPer', 'In-Memory', 'Lock-Free']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Khi Ổ Khóa Chậm Hơn Cả Tốc Độ Đọc Dữ Liệu</h2>

<p>Chúng ta đều biết rằng Khóa (Locks) là kẻ thù của hiệu năng. Nếu Transaction A đang khóa một dòng dữ liệu để ghi (Write), thì Transaction B muốn đọc (Read) dòng đó bắt buộc phải đứng ngoài cửa chờ. Sự chờ đợi này là một "sự ác cần thiết" đối với các Database truyền thống chạy trên ổ đĩa cứng chậm chạp. Nhưng khi bạn bê nguyên cái Database đặt vào trong RAM (In-Memory Database), các định luật vật lý bị đảo lộn hoàn toàn.</p>

<p>Trong RAM, việc đọc và ghi một dòng dữ liệu chỉ tốn vài nanosecond. Nhưng ác thay, việc thò tay đi xin hệ điều hành cấp cho một cái Khóa (Mutex / Latch) cũng tốn từng đó thời gian, thậm chí tốn hàng trăm nanosecond vì nó làm CPU Cache bị vô hiệu hóa (Cache Invalidation). Đột nhiên, chi phí để <em>quản lý cái khóa</em> lại đắt đỏ hơn cả chi phí <em>xử lý dữ liệu</em>! Nếu bạn bê nguyên cơ chế Lock truyền thống lên In-Memory DB, CPU của bạn sẽ lãng phí 90% sức mạnh chỉ để làm công việc "đứng đợi khóa mở".</p>

<h2>Ảo Thuật MVCC: Du Hành Thời Gian Trong Database</h2>

<p>Để tiêu diệt triệt để sự chờ đợi này, các Database hiện đại (từ PostgreSQL, MySQL InnoDB cho đến các quái vật In-Memory như HyPer) đều sử dụng một triết lý thiết kế thiên tài mang tên <strong>MVCC (Multi-Version Concurrency Control - Kiểm soát đồng thời đa phiên bản)</strong>.</p>

<p>Câu thần chú vĩ đại nhất của MVCC là: <strong>Người Đọc không bao giờ chặn Người Ghi, và Người Ghi không bao giờ chặn Người Đọc (Readers don\'t block Writers, Writers don\'t block Readers).</strong></p>

<p>Làm sao điều này có thể xảy ra? Rất đơn giản: MVCC đối xử với Database giống hệt như cách Git quản lý Source Code. Khi Transaction A muốn <code>UPDATE</code> số dư của user từ 100$ xuống 50$, nó <em>KHÔNG</em> ghi đè (overwrite) xóa mất số 100$. Thay vào đó, nó tạo ra một "Phiên bản" (Version) mới tinh chứa số 50$. Lúc này, trong RAM tồn tại song song cả 2 phiên bản: bản cũ 100$ và bản mới 50$.</p>

<p>Mỗi Transaction khi bắt đầu sẽ được cấp một cái "Đồng hồ cát" (Timestamp). Nếu Transaction B bắt đầu <em>trước</em> khi Transaction A Commit, thì Transaction B về cơ bản đang sống trong "Quá khứ". Khi B gửi lệnh <code>SELECT</code>, engine MVCC sẽ nhìn vào cái Timestamp của B, và lặng lẽ dẫn đường cho B đi đến cái phiên bản cũ 100$. B nhìn thấy một bức tranh quá khứ hoàn hảo, nhất quán, và hoàn toàn không hay biết rằng ở thì Hiện tại, A đang hì hục sửa dữ liệu thành 50$. Không ai phải đợi ai. Zero Lock!</p>

<h2>Cơn Ác Mộng Rác Thải (Garbage Collection)</h2>

<p>MVCC là một phép thuật tuyệt đẹp đối với lập trình viên Application, nhưng nó lại ném một quả bom tàn khốc sang cho các kỹ sư làm Database Engine: <strong>Rác (Garbage)</strong>. Nếu mỗi câu <code>UPDATE</code> đều đẻ ra một phiên bản mới, thì chẳng mấy chốc RAM sẽ bị đổ đầy bởi hàng triệu phiên bản cũ rích, vô dụng (Dead Tuples). Những ai xài PostgreSQL chắc chắn từng nếm mùi đau khổ này: Nó gọi là hiện tượng phình to bảng (Table Bloat), và đó là lý do Postgres phải có một con bot dọn rác cực nhọc tên là <code>VACUUM</code>.</p>

<p>Năm 2015, Thomas Neumann đã xuất bản bài báo <em>"Fast Serializable Multi-Version Concurrency Control for Main-Memory Database Systems"</em>, trình bày kiến trúc MVCC đỉnh cao được dùng trong HyPer Database, giải quyết triệt để bài toán dọn rác này.</p>

<h3>Chuỗi Bộ Đệm Undo (Undo-Buffer Chain)</h3>
<p>Thay vì xả rác bừa bãi các phiên bản cũ vào chung một bảng chính (như Postgres), HyPer đưa ra một quy tắc khắt khe: Bảng chính (Main Table) CHỈ ĐƯỢC PHÉP chứa phiên bản mới nhất, xịn nhất hiện tại. Khi một Transaction muốn sửa dữ liệu, nó sẽ bốc cái dữ liệu <em>cũ</em> ra, và ném tạm vào một cái thùng rác nhỏ xíu nằm trong Thread của riêng nó (gọi là Undo Buffer). Các phiên bản cũ này được nối với nhau bằng các con trỏ (Pointer) tạo thành một sợi dây xích thời gian.</p>

<p>Nếu một Transaction "đến từ quá khứ" muốn đọc dữ liệu, nó sẽ mò vào Bảng chính, thấy dữ liệu này "quá mới", nó liền bám theo sợi dây xích Pointer lùi về các thùng rác Undo Buffer để tìm ra đúng phiên bản thuộc về thời đại của nó. Sự thiên tài ở đây là gì? Vì cái Undo Buffer này là bộ nhớ cục bộ, tạm thời, nên ngay khi cái Transaction quá khứ kia chạy xong, Database có thể vứt cái thùng rác đó đi một cách tức khắc và nhẹ nhàng, tốn đúng 0.1 nanosecond. Không cần con bot <code>VACUUM</code> nặng nề nào cả. Không có Table Bloat. Chỉ có tốc độ tối đa của RAM.</p>

<h2>Lời Kết</h2>

<p>MVCC chính là lý do giúp các hệ thống Web hiện đại có thể phục vụ hàng triệu lượt truy cập <code>SELECT</code> mỗi giây mà không bị đơ cứng khi có một lệnh <code>UPDATE</code> lớn chạy ngầm. Bằng cách thao túng khái niệm "Thời gian" (Time) và coi dữ liệu là những bức ảnh chụp bất biến (Immutable Snapshots), MVCC đã thanh lịch vượt qua những cái bẫy Deadlock chết chóc của các thế hệ Database thời kỳ đồ đá.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Cỗ Máy Thời Gian Của Database: Phép Thuật MVCC Và Cơ Chế Không Khóa (Lock-Free)',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['MVCC', 'HyPer', 'In-Memory', 'Lock-Free']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>RAMの中では「鍵（Lock）」をかけることすら遅すぎる ⏱️</h2>

<p>私たちはすでに、データベースにおいて「鍵（Lock）」が並行処理の最大の敵であることを知っています。処理Aがデータを書き込んでいる間、処理Bはそのデータを読むことができず、じっと待たなければなりません。データが遅いハードディスクにある時代には、これは「必要な悪」でした。しかし、データベース全体をRAM（メモリ）の上に移動させた「インメモリ・データベース」の世界では、物理法則が根本から変わってしまいます。</p>

<p>RAMの中では、データを読んだり書いたりする処理はほんの「数ナノ秒」で終わります。しかし困ったことに、OSにお願いして「鍵（MutexやLatch）」をかけてもらう処理にも、同じく数ナノ秒から数十ナノ秒の時間がかかってしまうのです（CPUキャッシュが飛んでしまうためです）。つまり、<strong>「データを処理する時間」よりも「鍵を管理して順番待ちをする時間」の方が長くなってしまう</strong>という逆転現象が起きます。インメモリDBで伝統的なロック機構を使うと、CPUの能力の90%が「ただ鍵が開くのを待つだけ」に使われて無駄になってしまいます。</p>

<h2>MVCCの魔法：データベース内のタイムトラベル ⏳</h2>

<p>この「待ち時間」を完全に消し去るため、現代のデータベース（PostgreSQL、MySQL InnoDB、そしてHyPerなどのインメモリの巨人たち）は、<strong>「MVCC（Multi-Version Concurrency Control：多版同時実行制御）」</strong>という天才的なアーキテクチャを採用しています。</p>

<p>MVCCの最大の哲学はこれです：<strong>「読む人は書く人を邪魔しない。書く人は読む人を邪魔しない」</strong>。</p>

<p>どうやってそんな魔法を実現するのでしょうか？ それは、データベースを「Gitのバージョン管理」のように扱うことです。処理Aがユーザーの残高を100ドルから50ドルに減らそうとしたとき、データベースは決して100ドルの数字を「上書き（消去）」しません。代わりに、50ドルという「新しいバージョン」の行を新しく作成します。この瞬間、RAMの中には100ドル（古い版）と50ドル（新しい版）が同時に存在しています。</p>

<p>データベースにアクセスするすべての処理には、開始時に「タイムスタンプ（時計の時刻）」が渡されます。もし処理Bが、処理Aが完了する「前」の時刻にスタートしていた場合、処理Bは事実上「過去の世界」を生きていることになります。処理Bがデータベースを検索したとき、MVCCエンジンは時計を見比べ、処理Bをこっそりと「古い100ドルのバージョン」へと案内します。処理Bは、現在の世界でAが必死にデータを書き換えていることに全く気付かず、完全に一貫した「過去のスナップショット」を読み取ることができるのです。誰も鍵を待つ必要はありません（Zero Lock）！</p>

<h2>ゴミ収集（Garbage Collection）の悪夢 🗑️</h2>

<p>アプリ開発者にとってMVCCは夢のような魔法ですが、データベース・エンジンを作るエンジニアにとっては巨大な頭痛の種を生み出します。それが<strong>「ゴミ（不要な古いデータ）」</strong>です。もし <code>UPDATE</code> を実行するたびに新しいバージョンが作られ、古いデータが残っていくなら、RAMはあっという間に「誰も読まない古いバージョンのゴミ（Dead Tuples）」で埋め尽くされてしまいます。PostgreSQLを使っている人なら、これが「テーブルの肥大化（Table Bloat）」という現象であり、それを掃除するために <code>VACUUM</code> という重い処理が必要であることを痛いほど知っているでしょう。</p>

<p>2015年、トーマス・ノイマン（Thomas Neumann）は<em>『Fast Serializable Multi-Version Concurrency Control for Main-Memory Database Systems』</em>という論文を発表し、インメモリDB（HyPer）におけるMVCCの最先端の最適化手法を提案しました。彼はこのゴミ問題を見事に解決しました。</p>

<h3>Undoバッファ・チェーンの閃き 🔗</h3>
<p>HyPerは、Postgresのように「メインのテーブルの中に新旧のバージョンをごちゃ混ぜに保管する」ことを禁止しました。メインのテーブルには常に<strong>「最新のピカピカのデータだけ」</strong>が存在することをルールにしたのです。では、古いデータはどこに行くのか？ 処理がデータを更新するとき、古いデータを取り出して、その処理専用の小さなゴミ箱（Undo Buffer：元に戻すためのバッファ）に放り込みます。そして、このゴミ箱同士をポインタ（矢印）で鎖のようにつなぎ合わせます。</p>

<p>「過去の世界」を生きている処理がデータを読みに来たとき、メインテーブルを見て「あ、このデータは未来のデータだ」と気づくと、ポインタの鎖を辿ってゴミ箱（Undo Buffer）の中を探しに行き、自分の時代に合ったデータを見つけ出します。この設計が天才的なのは、<strong>「過去の処理が終わった瞬間、その小さなゴミ箱をメモリごとパッと一瞬で捨ててしまえる」</strong>という点です。Postgresのように巨大なテーブル全体を掃除（VACUUM）して回る必要はありません。テーブルの肥大化は一切起きず、ただ純粋な「ロックのない超高速な読み書き」だけが残るのです。</p>

<h2>結論</h2>

<p>私たちがWebサイトで、裏側で巨大なデータ更新が行われている最中でもサクサクとページ（SELECT）を見ることができるのは、すべてこのMVCCのおかげです。「時間（Time）」という概念を取り入れ、データを「変化しない写真（スナップショット）」として扱うことで、MVCCは昔のデータベースを苦しめていた「デッドロック」や「待ち時間のボトルネック」を、この上なくエレガントに回避したのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'データベース内のタイムトラベル：MVCCと「鍵のない」世界の魔法',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['MVCC', 'HyPer', 'In-Memory', 'Lock-Free']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 15 (MVCC) with Categories, Tags, and Translation Links!\n";
