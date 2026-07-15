<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'architectural_era.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
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
$cat_vi = setup_term('Kiến Trúc Cơ Sở Dữ Liệu', 'category', 'vi');
$cat_ja = setup_term('データベース・アーキテクチャ', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Hidden 90% Tax of Traditional Databases</h2>

<p>If you write an SQL query in a modern application and it takes 10 milliseconds to run, what is the database actually doing during those 10 milliseconds? You might assume the CPU is furiously multiplying numbers, searching through strings, and returning your data. But you would be completely wrong.</p>

<p>In 2007, Turing Award winner Michael Stonebraker published a devastating critique of the databases that run the world (like Oracle, DB2, and SQL Server) titled <em>"The End of an Architectural Era: It\'s Time for a Complete Rewrite"</em>. Through rigorous profiling of the actual C++ code executing inside these databases, his team made a shocking discovery: <strong>Less than 10% of the CPU time is actually spent executing your query.</strong></p>

<p>The other 90% is completely wasted on "overhead". It is a massive, invisible tax you pay just to keep the old architecture running.</p>

<h2>The Four Horsemen of Database Overhead</h2>

<p>Traditional RDBMS architectures were designed in the late 1970s (the System R era). Back then, RAM was incredibly expensive, and disks were incredibly slow. The entire architecture was optimized to minimize disk reads. But today, you can buy a server with 1 Terabyte of RAM for a few thousand dollars. Many entire databases can fit comfortably in memory. Yet, the 1970s architecture remains, dragging down performance.</p>

<p>Stonebraker identified four massive bottlenecks consuming the 90% of your CPU:</p>

<ol>
<li><strong>Buffer Pool Management:</strong> Because the system assumes data is on a slow disk, it maps chunks of the disk into a "Buffer Pool" in RAM. Every single time a record is read, the CPU must look up its address in a hash table, check if it\'s in RAM, "pin" the memory page so it doesn\'t get evicted, and unpin it later. This tracking alone consumes 30% of CPU time, even if the entire database is already in RAM!</li>
<li><strong>Locking:</strong> To prevent two users from overwriting each other\'s data, the database uses 2-Phase Locking. Before reading or writing, the thread must acquire a lock on the record. Acquiring a lock means writing to a shared lock table, which requires acquiring a "latch" (a low-level CPU mutex) to protect the lock table itself. In high-concurrency environments, threads spend huge amounts of time just waiting for these latches.</li>
<li><strong>Latching (B-Tree traversal):</strong> Even finding the record requires walking down a B-Tree. To prevent the B-Tree from physically shifting while you are reading it, you must acquire short-term latches on every node you traverse. It\'s like having to stop and ask a security guard for permission at every step of a staircase.</li>
<li><strong>Write-Ahead Logging (WAL):</strong> To ensure data survives a power outage, every change must be serialized and appended to a log file before the transaction commits. The CPU wastes immense cycles formatting log records and waiting for the disk to acknowledge the write.</li>
</ol>

<h2>The Monolith Must Die: The Rise of Specialized Engines</h2>

<p>Stonebraker\'s conclusion was brutal and absolute: <strong>"One size does not fit all."</strong> You cannot take a 1970s, disk-based, row-oriented monolith and simply "patch" it to be fast for modern workloads.</p>

<p>If you want to build a high-frequency trading platform (OLTP), you need a database that completely removes the Buffer Pool and Locking. You need an <strong>In-Memory Database</strong> (like H-Store/VoltDB) that executes transactions single-threaded per partition, achieving millions of transactions per second because there is zero locking overhead.</p>

<p>If you want to build a data warehouse to analyze petabytes of analytics (OLAP), you need a database that abandons row-based storage and B-Trees entirely. You need a <strong>Columnar Database</strong> (like C-Store/Vertica or ClickHouse) that compresses columns heavily and uses vectorized CPU instructions.</p>

<h2>Lessons Learned: Stop Tuning the Un-tunable</h2>

<p>As software engineers, we often spend weeks trying to optimize our slow SQL queries. We add indexes, we tweak Buffer Pool sizes, we rewrite our ORM queries. But Stonebraker’s paper reminds us that we are fighting a losing battle against the architecture itself.</p>

<p>When you hit a performance wall, the solution is not always to tune the monolith. Sometimes, you have to realize that the architectural era of the universal, general-purpose database is over. The future belongs to specialized engines designed explicitly for the hardware of today, not the hardware of 1978.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The 90% Overhead Tax: Why Traditional Databases Are Architecturally Obsolete',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['RDBMS', 'Stonebraker', 'Architecture', 'Performance Overhead']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Cú Lừa 90% CPU Của Các "Ông Lớn" Database</h2>

<p>Bạn là một Engineering Manager. Bạn vừa ký nháy duyệt chi 50.000 USD để mua một dàn máy chủ khủng với 128 cores CPU và 1TB RAM để cứu vãn cái hệ thống MySQL/Oracle đang chạy rùa bò của công ty. Bạn kỳ vọng với cấu hình "quái vật" này, CPU sẽ tập trung toàn lực vào việc xử lý các câu SQL JOIN phức tạp của bạn. Nhưng bạn đã nhầm to.</p>

<p>Năm 2007, một quả bom thực sự đã được ném vào ngành công nghiệp phần mềm. Michael Stonebraker (người đoạt giải Turing, cha đẻ của PostgreSQL) cùng nhóm nghiên cứu MIT đã mổ xẻ mã nguồn C++ của các hệ quản trị CSDL truyền thống (RDBMS). Trong bài báo <em>"The End of an Architectural Era: It\'s Time for a Complete Rewrite" (Dấu Chấm Hết Của Một Kỷ Nguyên Kiến Trúc)</em>, ông công bố một sự thật chấn động: <strong>Hệ thống Database của bạn chỉ dành vỏn vẹn 10% năng lực CPU để thực sự xử lý dữ liệu.</strong></p>

<p>Vậy 90% CPU còn lại đang làm cái quái gì? Chúng đang bị đốt cháy vào một thứ gọi là "Overhead" (Chi phí kiến trúc rác) - một khoản thuế tàng hình khổng lồ mà bạn phải trả chỉ để duy trì một kiến trúc phần mềm được thiết kế từ tận... thập niên 1970.</p>

<h2>Tứ Đại Ác Nhân Bòn Rút Tài Nguyên Hệ Thống</h2>

<p>Các kiến trúc RDBMS như Oracle, DB2, hay SQL Server được sinh ra vào thời kỳ System R (cuối những năm 70). Thời đó, RAM đắt như vàng ròng và cực kỳ nhỏ, còn ổ đĩa cơ học thì quay chậm như rùa. Toàn bộ kiến trúc CSDL được thiết kế với một nỗi ám ảnh duy nhất: Giảm thiểu số lần đọc đĩa cứng. Nhưng ngày nay, khi bạn có thể nhét vừa toàn bộ Data của một công ty cỡ trung vào 1TB RAM, cái kiến trúc cổ lỗ sĩ đó lại trở thành nút thắt cổ chai bóp nghẹt hệ thống.</p>

<p>Stonebraker chỉ mặt đặt tên "4 kẻ ăn bám" đang đốt 90% CPU của bạn:</p>

<ol>
<li><strong>Buffer Pool (Bể đệm):</strong> Vì hệ thống luôn "hoang tưởng" rằng dữ liệu nằm trên đĩa, nó phải duy trì một Hash Table khổng lồ trong RAM để map (ánh xạ) giữa đĩa và bộ nhớ. Mỗi lần bạn đọc 1 dòng dữ liệu, CPU phải tra cứu Hash Table này, thực hiện thao tác "Pin" (Khóa chặt trang nhớ để tránh bị xóa đi), đọc xong lại phải "Unpin". Thao tác kế toán vô bổ này ngốn sạch 30% CPU, ngay cả khi 100% dữ liệu của bạn đã nằm sẵn trong RAM!</li>
<li><strong>Locking (Khóa đồng thời):</strong> Để đảm bảo tính ACID, khi 2 user cùng ghi dữ liệu, CSDL dùng kỹ thuật 2-Phase Locking. Việc xin cấp quyền Lock yêu cầu CPU phải ghi vào một Bảng Khóa chung (Lock Table). Để bảo vệ cái Bảng Khóa này không bị crash, CPU lại phải dùng đến "Latch" (Mutex cấp thấp). Hệ quả: Các Thread dẫm chân lên nhau, phải xếp hàng chờ đợi (Wait time) chỉ để xin được cái... giấy phép cấp quyền Lock.</li>
<li><strong>Latching (Bảo vệ B-Tree):</strong> Thậm chí việc đi tìm dữ liệu cũng đầy đau khổ. Để chui xuống được cái "Nút lá" của cấu trúc B-Tree, CPU phải xin một cái Latch bảo vệ ở mỗi tầng nhánh mà nó đi qua (để tránh việc có thằng khác đang bẻ nhánh cây lúc mình đang trèo). Tưởng tượng bạn leo thang bộ 10 tầng, và mỗi bậc thang đều có một ông bảo vệ đứng bắt bạn trình căn cước.</li>
<li><strong>Logging (Ghi nhật ký WAL):</strong> Để đảm bảo cúp điện không mất dữ liệu, mọi thao tác sửa đổi phải được chuyển thành binary log và ép ghi tuần tự xuống đĩa cứng (Write-Ahead Log) trước khi Transaction được phép báo thành công (Commit). Việc "chờ đợi đĩa cứng" này là kẻ thù số 1 của hiệu năng.</li>
</ol>

<h2>Đập Đi Xây Lại: Không Có Chuyện "Một Cỡ Vừa Cho Mọi Người"</h2>

<p>Kết luận của Stonebraker phũ phàng nhưng chính xác: <strong>"One size does not fit all"</strong> (Một bộ áo không thể mặc vừa cho mọi người). Bạn không thể lấy một cái hệ thống Monolithic (nguyên khối) cũ kỹ từ thập niên 70, đắp thêm vài miếng vá (patch), và hy vọng nó chạy nhanh trong kỷ nguyên Big Data.</p>

<p>Nếu bạn xây hệ thống giao dịch chứng khoán (OLTP) cần tốc độ 1 triệu Transaction/giây, bạn phải vứt bỏ hoàn toàn Buffer Pool và Locking. Bạn cần một <strong>In-Memory Database</strong> (như VoltDB). Vì dữ liệu nằm hoàn toàn trên RAM và xử lý đơn luồng (Single-threaded) theo từng Partition, nó không cần Lock, không cần Latch, tốc độ bay lên tận mây xanh.</p>

<p>Nếu bạn xây hệ thống phân tích báo cáo (OLAP) vài Petabyte, bạn phải vứt bỏ hoàn toàn B-Tree và cách lưu trữ theo hàng (Row-based). Bạn cần một <strong>Columnar Database</strong> (như ClickHouse hay Vertica) nén dữ liệu cực sâu và chạy bằng lệnh Vector của CPU.</p>

<h2>Bài Học Dành Cho Kỹ Sư: Ngừng Cố Gắng "Tuning" Những Thứ Không Thể</h2>

<p>Rất nhiều kỹ sư dành cả thanh xuân để "Tuning" (tối ưu hóa) các câu lệnh SQL: thêm index, chỉnh lại tham số `innodb_buffer_pool_size`, hay sửa lại thư viện ORM. Nhưng bài báo của Stonebraker đánh thức chúng ta: Đôi khi, bạn đang đánh một trận chiến nắm chắc phần thua chống lại chính giới hạn kiến trúc của hệ thống.</p>

<p>Khi ứng dụng của bạn chạm vào giới hạn vật lý của hiệu năng, giải pháp không phải là "Tuning" cái khối Monolith đó nữa. Giải pháp là phải dũng cảm nhìn nhận: Kỷ nguyên của những "Database Đa Năng" đã kết thúc. Tương lai thuộc về những Động cơ Đặc nhiệm (Specialized Engines), được thiết kế đo ni đóng giày cho phần cứng của ngày hôm nay, chứ không phải cho những chiếc đĩa từ tính của năm 1978.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Cú Lừa 90% CPU: Tại Sao Cấu Trúc Database Truyền Thống Đã Bị Đào Thải',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['RDBMS', 'Stonebraker', 'Architecture', 'Performance Overhead']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>伝統的データベースの「隠れた90%の税金」 💸</h2>

<p>現代のWebアプリケーションでSQLクエリを書き、それが「10ミリ秒」で返ってきたとします。その10ミリ秒の間、データベースの内部でCPUは一体何をしているのでしょうか？ あなたは「CPUが猛烈な勢いで数字を掛け合わせたり、文字列を検索したりして、一生懸命データを集めているんだろうな」と想像するかもしれません。しかし、それは完全に間違っています。</p>

<p>2007年、チューリング賞を受賞したデータベース界の巨匠、マイケル・ストーンブレーカー（Michael Stonebraker）は、Oracle、DB2、SQL Serverといった世界を支配している伝統的データベースに対して、壊滅的な批判論文を発表しました。タイトルは<em>『The End of an Architectural Era: It\'s Time for a Complete Rewrite（アーキテクチャの時代の終焉：今こそ完全に書き直す時だ）』</em>です。</p>

<p>彼の研究チームが、これらのデータベース内部で実行されているC++のコードを徹底的にプロファイリング（分析）した結果、信じられないほど衝撃的な事実が判明しました。<strong>「CPUが実際にあなたのクエリ（データの検索や計算）を処理している時間は、全体のわずか10%未満である」</strong>という事実です。</p>

<p>では、残りの90%のCPUパワーはどこに消えてしまったのでしょうか？ それはすべて「オーバーヘッド（無駄な管理コスト）」として燃やし尽くされているのです。これは、古いアーキテクチャを動かし続けるためだけに、あなたが毎秒払い続けている「目に見えない巨大な税金」なのです。</p>

<h2>CPUを食い潰す「4つの大罪」 👿</h2>

<p>リレーショナルデータベース（RDBMS）の基礎となるアーキテクチャは、1970年代後半（System Rの時代）に設計されました。当時は「RAM（メモリ）は宝石のように高価で、ハードディスクは亀のように遅い」という時代でした。そのため、システム全体が「いかにディスクの読み込み回数を減らすか」という目的のためだけに最適化されていました。</p>

<p>しかし現代では、1TBのRAMを積んだサーバーが数十万円で買えます。企業のデータベース全体が、余裕でメモリ内に収まってしまう時代です。それにもかかわらず、1970年代の「ディスクが遅いことを前提とした設計」が未だに残っており、それがシステムの足を強烈に引っ張っているのです。</p>

<p>ストーンブレーカーは、CPUの90%を浪費している4つの巨大なボトルネックを特定しました：</p>

<ol>
<li><strong>バッファプール管理（Buffer Pool Management）：</strong> システムは常に「データは遅いディスク上にある」と疑っているため、メモリ上に「バッファプール」という一時置き場を作ります。データを1行読むたびに、CPUはハッシュテーブルを検索し、データがメモリにあるか確認し、そのページが消されないように「ピン留め（Pin）」し、読み終わったら「ピンを外す（Unpin）」という無駄な事務作業を行います。驚くべきことに、<strong>すべてのデータが既にメモリ上にある場合でも</strong>、この確認作業だけでCPUの30%が消費されます。</li>
<li><strong>ロッキング（Locking）：</strong> 2人のユーザーが同時に同じデータを書き換えて壊してしまうのを防ぐため、データベースは「ロック（鍵）」をかけます。しかし、ロックを取得するということは、共有の「ロックテーブル」に書き込みを行うということです。そしてそのロックテーブル自体を守るために、CPUの低レベルな「ラッチ（Latch / Mutex）」を奪い合わなければなりません。アクセスが集中すると、スレッド（処理）たちは「鍵をもらうための整理券」をもらうために大行列を作り、膨大な待ち時間を浪費します。</li>
<li><strong>ラッチング（Latching / B-Treeの保護）：</strong> データを見つけるためにB-Treeの階段を降りる時でさえ、苦痛が伴います。自分が読んでいる最中に他の誰かがツリーの枝を物理的に変えてしまわないように、通り抜けるすべてのノード（結び目）で一瞬だけラッチ（鍵）をかけなければなりません。これは例えるなら、「階段を1段降りるごとに、警備員に身分証を見せて許可をもらわなければならない」ようなものです。</li>
<li><strong>ログの書き込み（Write-Ahead Logging / WAL）：</strong> 突然の停電でもデータが消えないようにするため、変更内容はすべてシリアライズされ、ディスク上のログファイルに追記（Append）されます。そして、ディスクが「書き込み完了」と返事をするまで、トランザクションはコミット（確定）できません。CPUはこの「ディスクの返事待ち」と「ログのフォーマット作業」に多大なサイクルを無駄にします。</li>
</ol>

<h2>モノリス（巨大な一枚岩）の死と、特化型エンジンの台頭 🚀</h2>

<p>ストーンブレーカーの結論は、残酷なまでに絶対的でした。<strong>「One size does not fit all（一つの服を全員に着せることはできない）」。</strong>1970年代に作られた、ディスクベースで、行指向（Row-oriented）の巨大なデータベースに、いくら最新の「パッチ（ツギハギ）」を当てても、現代の過酷なワークロードを速く処理することは原理的に不可能なのです。</p>

<p>もしあなたが、毎秒何百万回もの取引を行う超高速な証券取引システム（OLTP）を作りたいなら、バッファプールとロックを「完全に根絶」したデータベースが必要です。すべてをRAM上で動かし、データごとにシングルスレッドで処理を行うことでロックのオーバーヘッドをゼロにした<strong>「インメモリ・データベース（In-Memory DB）」</strong>（VoltDBなど）こそが正解です。</p>

<p>もしあなたが、ペタバイト級の売上データを分析する巨大なデータウェアハウス（OLAP）を作りたいなら、B-Treeと「行ごとの保存」を完全に捨て去る必要があります。同じ種類のデータを縦に圧縮して保存し、CPUのベクトル命令で爆速処理する<strong>「カラムナー（列指向）データベース」</strong>（ClickHouseやVerticaなど）が必要です。</p>

<h2>私たちが学んだこと：限界を超えたチューニングをやめる勇気 🔧</h2>

<p>私たちソフトウェアエンジニアは、遅いSQLクエリを速くするために、何週間も徹夜して最適化（チューニング）を行うことがよくあります。インデックスを追加し、バッファプールの設定値をいじり、ORMのコードを書き直します。</p>

<p>しかし、ストーンブレーカーの論文は私たちに冷水を浴びせます。「あなたは、アーキテクチャそのものの限界という、絶対に勝てない相手と戦っているのだ」と。</p>

<p>パフォーマンスの巨大な壁にぶつかったとき、解決策は常に「巨大な一枚岩（モノリス）の設定をこねくり回す」ことではありません。時には、「何でもできる汎用的なデータベースの時代は終わった」と現実を受け入れる勇気が必要です。未来は、1978年のハードディスクのためではなく、「今日の最新ハードウェア」のためだけに極限まで研ぎ澄まされた、専門的な特化型エンジン（Specialized Engines）の手に委ねられているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'モノリスの崩壊：伝統的データベースがCPUの90%を無駄にする理由',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['RDBMS', 'Stonebraker', 'Architecture', 'Performance Overhead']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 9 (Architectural Era) with Categories, Tags, and Translation Links!\n";
