<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'cstore_columnar.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'C-Store Columnar',
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

$cat_en = setup_term('OLAP & Analytics', 'category', 'en');
$cat_vi = setup_term('Phân Tích Dữ Liệu (OLAP)', 'category', 'vi');
$cat_ja = setup_term('OLAPとデータ分析', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Curse of the Row Store in Big Data</h2>

<p>For fifty years, databases have been obsessed with "Rows". If you insert a user into PostgreSQL, the database takes the User ID, Name, Email, and Registration Date, bundles them together, and writes them sequentially onto the hard drive as a single continuous block of bytes. This architecture is called a <strong>Row-Store</strong>.</p>

<p>Row-Stores are absolutely fantastic for OLTP (Online Transaction Processing). If a user logs in and wants to see their profile, the database finds the row, reads the single block from disk, and returns all the data instantly. It is fast, efficient, and atomic.</p>

<p>But what happens when the business scales, and the CEO asks: <em>"What was the average age of all users who registered last month?"</em></p>

<p>To answer this question, a Row-Store has to scan millions of users. But because the data is stored row by row, the database is forced to read the User ID, the Name, the Email, and the Age from the disk—even though the query only needs the Age and the Date. This means 90% of the data pulled from the disk into the CPU cache is immediately thrown away as garbage. This massive I/O waste brings analytical queries to a grinding halt.</p>

<h2>The C-Store Revolution: Rotating the Data 90 Degrees</h2>

<p>In 2005, a group of researchers led by Michael Stonebraker published a paper titled <em>"C-Store: A Column-oriented DBMS"</em>. Their proposal was breathtakingly simple yet profoundly disruptive: What if we took the database and rotated it 90 degrees?</p>

<p>Instead of storing data row by row, C-Store (and its commercial successor, Vertica, as well as modern engines like ClickHouse) stores data <strong>column by column</strong>. All the Ages are stored together in one file. All the Names are stored together in another file.</p>

<p>Now, when the CEO asks for the average age, the database only opens the "Age" file and the "Date" file. It completely ignores the "Name" and "Email" files. By only reading the exact columns needed, disk I/O drops by 90% instantly.</p>

<h2>The Hidden Superpowers: Compression and Vectorization</h2>

<p>Reducing disk I/O is great, but Column-Stores hide two incredible superpowers that make them 100x to 1000x faster than Row-Stores for analytics.</p>

<h3>1. Extreme Data Compression</h3>
<p>If you look at a column of "Countries", you will see "USA", "USA", "UK", "USA", "Japan" repeating millions of times. In a Row-Store, compressing this is difficult because the data is mixed with Names and Ages. But in a Column-Store, because all the data in a file is of the exact same type and often highly repetitive, you can use specialized compression algorithms like <strong>Run-Length Encoding (RLE)</strong>. The database can compress a million "USA" strings into a single tuple: `("USA", 1000000)`. A 100GB table can easily shrink to 5GB, meaning it can fit entirely in RAM.</p>

<h3>2. Vectorized CPU Execution (SIMD)</h3>
<p>When computing an average, a Row-Store processes data one row at a time. It uses a standard CPU instruction for every single addition. A Column-Store, however, can leverage modern CPU capabilities called <strong>SIMD (Single Instruction, Multiple Data)</strong>. Because the Ages are packed tightly together in memory without any other junk in between, the CPU can load a "vector" of 16 or 32 ages simultaneously into its registers and add them all together in a single clock cycle. This Vectorized Execution acts as a turbocharger for analytical queries.</p>

<h2>The Trade-Off: Why You Still Need Row-Stores</h2>

<p>If Column-Stores are so fast, why don\'t we use them for everything? Because inserting a single row into a Column-Store is a nightmare. To insert one user, the database has to open 10 different files (one for each column) and append the data to each one. This causes catastrophic Random I/O and heavy locking.</p>

<p>This is why the industry split in two. You use PostgreSQL (Row-Store) to power your live web application where users are constantly writing data. And you use ClickHouse or Snowflake (Column-Store) to power your analytics dashboards, copying data over in massive batches. C-Store didn\'t kill the Row-Store; it simply proved that for analytical math, the column reigns supreme.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Rotating the Database 90 Degrees: The Magic of Columnar Stores',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Columnar Database', 'C-Store', 'Big Data', 'Vectorization']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Căn Bệnh Mãn Tính Của Row-Store Trong Kỷ Nguyên Big Data</h2>

<p>Trong suốt nửa thế kỷ qua, các hệ quản trị cơ sở dữ liệu đã bị ám ảnh bởi một khái niệm duy nhất: "Hàng" (Row). Nếu bạn chạy một lệnh <code>INSERT</code> để tạo một user mới vào MySQL, hệ thống sẽ lấy ID, Tên, Email, và Ngày sinh của người đó, gói gém chúng lại với nhau, và ghi tuần tự thành một khối byte liên tục xuống ổ đĩa cứng. Kiến trúc này được gọi là <strong>Row-Store (Lưu trữ theo hàng)</strong>.</p>

<p>Row-Store là một thiết kế tuyệt hảo dành cho hệ thống OLTP (Xử lý giao dịch trực tuyến - ví dụ: hệ thống bán hàng, thanh toán). Nếu một user đăng nhập và muốn xem profile của họ, Database chỉ cần dò tìm đúng cái Hàng đó, bốc 1 cục duy nhất từ ổ cứng lên, và trả về trọn vẹn mọi thông tin. Nó cực kỳ nhanh, an toàn và đảm bảo tính nguyên tử (Atomic).</p>

<p>Nhưng bi kịch bắt đầu khi công ty của bạn Scale lên. Vị CEO bước vào phòng Engineering và yêu cầu: <em>"Hãy tính cho tôi độ tuổi trung bình của toàn bộ 100 triệu User đã đăng ký trong tháng trước"</em>.</p>

<p>Để trả lời câu hỏi này, một cái Row-Store sẽ phải gồng mình quét (Scan) qua 100 triệu hàng. Bởi vì dữ liệu được dính chặt vào nhau theo từng hàng, Database bị ép buộc phải đọc TẤT CẢ mọi thứ từ đĩa cứng lên RAM: đọc cả Email, đọc cả Tên, đọc cả Mật khẩu mã hóa... mặc dù câu truy vấn của bạn CHỈ CẦN cột Tuổi (Age). Điều này có nghĩa là, 90% dữ liệu được I/O khó nhọc kéo từ ổ đĩa lên CPU Cache bị vứt thẳng vào sọt rác ngay lập tức vì không dùng đến. Sự lãng phí I/O (I/O waste) khổng lồ này chính là lý do khiến các báo cáo phân tích (Analytics) trên MySQL chạy mất hàng giờ đồng hồ.</p>

<h2>Cuộc Cách Mạng C-Store: Xoay Ngang Database 90 Độ</h2>

<p>Vào năm 2005, nhóm nghiên cứu của Michael Stonebraker đã xuất bản bài luận văn kinh điển <em>"C-Store: A Column-oriented DBMS"</em>. Giải pháp mà họ đưa ra cực kỳ đơn giản đến mức táo bạo: Chuyện gì sẽ xảy ra nếu chúng ta cầm cái Database và... xoay nó 90 độ?</p>

<p>Thay vì lưu dữ liệu theo từng hàng, C-Store (và các hậu duệ thương mại của nó như Vertica, hay các quái vật mã nguồn mở hiện đại như ClickHouse) lưu dữ liệu <strong>theo từng Cột (Column-by-Column)</strong>. Toàn bộ Tuổi của 100 triệu user được lưu sát nhau trong 1 file vật lý. Toàn bộ Tên được lưu trong 1 file khác.</p>

<p>Giờ đây, khi vị CEO yêu cầu tính "Tuổi trung bình", Database chỉ mở duy nhất cái file "Age" ra và đọc. Nó hoàn toàn phớt lờ file "Name" hay "Email". Bằng cách chỉ đọc chính xác những cột cần thiết, chi phí I/O đĩa cứng giảm ngay lập tức 90%, tốc độ truy vấn tăng lên theo cấp số nhân.</p>

<h2>Sức Mạnh Tàng Hình: Nén Dữ Liệu (Compression) Và Xử Lý Vector (Vectorization)</h2>

<p>Giảm I/O là một chuyện, nhưng Column-Store còn giấu trong tay áo 2 siêu năng lực khủng khiếp khiến nó nhanh gấp 100 đến 1.000 lần so với Row-Store trong các bài toán Data Warehouse.</p>

<h3>1. Ép Nén Dữ Liệu Cực Đại (Extreme Data Compression)</h3>
<p>Hãy nhìn vào một cột chứa "Quốc gia" (Country). Bạn sẽ thấy các chữ "Vietnam", "Vietnam", "USA", "USA" lặp lại hàng triệu lần. Trong Row-Store, bạn rất khó nén cái này vì chữ "Vietnam" bị chen ngang bởi cột Tên và cột Ngày sinh. Nhưng trong Column-Store, vì toàn bộ dữ liệu trong file có chung một kiểu dữ liệu (Data Type) và lặp lại liên tục, Database có thể sử dụng các thuật toán nén chuyên dụng cực kỳ mạnh như <strong>Run-Length Encoding (RLE)</strong>. Hệ thống có thể nén 1 triệu chữ "Vietnam" thành một dòng ký hiệu duy nhất: `("Vietnam", 1.000.000)`. Một bảng dữ liệu nặng 100GB trên MySQL có thể bị ép nhỏ lại chỉ còn 5GB trên ClickHouse, nghĩa là nó có thể nằm lọt thỏm trong RAM mà không cần chạm tới ổ đĩa!</p>

<h3>2. Kích Hoạt Lệnh CPU Vector (SIMD)</h3>
<p>Khi tính trung bình cộng, Row-Store xử lý dữ liệu theo kiểu "chạy bằng cơm": lấy từng hàng ra, cộng vào biến tổng, rồi lặp lại. Nhưng Column-Store thì khác. Vì cột "Tuổi" được xếp thành một mảng liên tục (contiguous array) hoàn hảo trong RAM không chứa rác, Column-Store có thể đánh thức một tính năng cấp thấp của CPU gọi là <strong>SIMD (Single Instruction, Multiple Data)</strong>. CPU có thể bốc một "Vector" gồm 16 hoặc 32 con số Tuổi nhét vào thanh ghi (Registers) và cộng tất cả chúng lại với nhau chỉ trong 1 chu kỳ xung nhịp (Clock cycle) duy nhất. Đây chính là công nghệ Vectorized Execution biến Column-Store thành một cỗ xe đua F1 thực thụ.</p>

<h2>Tại Sao Chúng Ta Vẫn Phải Dùng MySQL/Postgres?</h2>

<p>Đọc đến đây, bạn sẽ tự hỏi: Nếu Column-Store vĩ đại như vậy, tại sao ta không vứt hết MySQL đi mà xài ClickHouse cho mọi dự án? Câu trả lời nằm ở "Điểm yếu chí mạng" của Column-Store: Việc ghi dữ liệu (Write).</p>

<p>Việc <code>INSERT</code> một dòng dữ liệu (1 user mới) vào Column-Store là một cơn ác mộng kiến trúc. Vì dữ liệu bị băm ra thành 10 file khác nhau cho 10 cột, để ghi 1 user, Database phải mở 10 file ra, xả nén, nối thêm dữ liệu vào từng file, và nén lại. Thao tác này tạo ra Random I/O khủng khiếp và khóa (Lock) toàn bộ hệ thống.</p>

<p>Đó là lý do ngành công nghiệp phần mềm bị chia làm hai nửa rõ rệt. Bạn BẮT BUỘC phải dùng PostgreSQL/MySQL (Row-Store) làm Database chính cho ứng dụng Web của bạn (nơi user liên tục đăng nhập, mua hàng). Sau đó, vào ban đêm, bạn dùng các job ETL để hốt toàn bộ dữ liệu đó, gom thành từng Batch khổng lồ, và đổ vào ClickHouse/Snowflake (Column-Store) để chạy Dashboard báo cáo cho sếp. C-Store không giết chết Row-Store; nó chỉ chứng minh rằng trong thế giới của Toán học Thống kê, Cột mới là vị vua đích thực.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Xoay Database Ngang 90 Độ: Ma Thuật Của Kiến Trúc Column-Store',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Columnar Database', 'C-Store', 'Big Data', 'Vectorization']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>行（Row）にこだわるがゆえの悲劇 📉</h2>

<p>過去50年間、データベースの世界は「行（Row：ロウ）」という概念に完全に取り憑かれていました。もしあなたがMySQLに新しいユーザーを登録（INSERT）すると、データベースは「ユーザーID」「名前」「メールアドレス」「登録日」といった情報をひとまとめのセット（行）にし、それをハードディスク上に「連続した1つの塊」として書き込みます。このアーキテクチャを<strong>「ロウ・ストア（Row-Store：行指向データベース）」</strong>と呼びます。</p>

<p>ロウ・ストアは、OLTP（オンライントランザクション処理：日常的なアプリの操作）においては信じられないほど素晴らしい性能を発揮します。ユーザーがログインして自分のプロフィールを見たいとき、データベースはその「行」を見つけ、ディスクから1つの塊を読み込むだけで、必要なすべての情報を瞬時に返すことができます。それは高速で、安全で、完璧です。</p>

<p>しかし、ビジネスが成長し、データが膨大になったとき、CEOが会議室でこう質問したらどうなるでしょうか？<br>
<em>「先月登録した全ユーザー（1億人）の、平均年齢を教えてくれ」</em></p>

<p>このたった一つの質問に答えるため、ロウ・ストアは地獄の苦しみを味わうことになります。データが「行」ごとにまとめられているため、データベースは「年齢」の数字だけを取り出すことができません。ディスクからメモリ（RAM）へとデータを読み込む際、「ID」「名前」「メールアドレス」「暗号化されたパスワード」まで、<strong>今回は全く必要のないデータまで全部まとめて読み込まなければならない</strong>のです。そして、読み込まれたデータの90%は「ゴミ」として即座に捨てられます。この巨大なI/Oの無駄遣い（ディスクの読み込みの浪費）こそが、分析用の重いクエリ（Analytics）が何時間もかかってしまう最大の原因なのです。</p>

<h2>C-Storeの革命：データベースを90度回転させる 🔄</h2>

<p>2005年、マイケル・ストーンブレーカー率いる研究チームは、<em>『C-Store: A Column-oriented DBMS（列指向DBMS）』</em>という論文を発表しました。彼らの提案は、息を呑むほどシンプルでありながら、業界の常識を覆す破壊的なものでした。「もし、データベースの表を『90度回転』させたらどうなるだろうか？」</p>

<p>データを「行ごと」に保存するのではなく、C-Store（およびその商業的な後継であるVertica、あるいは現代のClickHouseなど）は、データを<strong>「列（Column：カラム）ごと」</strong>に保存します。1億人分の「年齢」の数字だけが、1つのファイルにまとめて保存されます。「名前」はすべて別のファイルに保存されます。</p>

<p>さて、もう一度CEOが「平均年齢を教えてくれ」と言ったとしましょう。今度は、データベースは「年齢」のファイルだけを開いて読み込みます。「名前」や「メールアドレス」のファイルは完全に無視（スキップ）されます。クエリに必要な列だけをピンポイントで読み込むことで、ディスクの読み込み量（I/O）は即座に90%削減され、処理速度は劇的に向上するのです。</p>

<h2>隠された2つの超能力：圧縮（Compression）とベクトル化（Vectorization） 🦸‍♀️</h2>

<p>I/Oを減らすだけでも素晴らしいことですが、カラム・ストア（列指向データベース）には、データ分析においてロウ・ストアを100倍から1000倍も凌駕する「2つの隠された超能力」があります。</p>

<h3>1. 極限のデータ圧縮（Extreme Compression）</h3>
<p>例えば「国（Country）」という列を見てみましょう。そこには「USA」「USA」「Japan」「UK」「USA」という文字が何百万回も繰り返されています。ロウ・ストアでは、これらの文字の間に「名前」や「年齢」が混ざっているため、データを圧縮するのは非常に困難です。しかしカラム・ストアでは、ファイルの中身がすべて「同じデータ型」であり、高度に繰り返されるため、<strong>ランレングス圧縮（RLE）</strong>のような強力な専用アルゴリズムを使うことができます。データベースは、100万個の「USA」という文字列を `("USA", 1000000)` というたった一行の記号に圧縮してしまうのです！ MySQLで100GBあったテーブルが、ClickHouseでは5GBにまで縮小され、ハードディスクに触れることなく、すべてがRAMの中にスッポリと収まってしまうことすらあります。</p>

<h3>2. CPUのベクトル化実行（SIMD命令）</h3>
<p>平均を計算するとき、ロウ・ストアはデータを「1行ずつ」取り出して、CPUで1回ずつ足し算をします。しかしカラム・ストアは違います。「年齢」の数字だけが、メモリ上に他のゴミデータが混ざることなく「綺麗な配列」として密集しているため、<strong>「SIMD（Single Instruction, Multiple Data：単一命令・複数データ）」</strong>と呼ばれる現代のCPUの特殊な機能（魔法）を起動できるのです。CPUは、16個や32個の「年齢の数字」をまとめてレジスタにロードし、たった「1回のクロックサイクル（1回の命令）」で一気に足し算をしてしまいます。この「ベクトル化実行（Vectorized Execution）」が、分析クエリの速度を爆発的に加速させるターボチャージャーの役割を果たします。</p>

<h2>トレードオフ：なぜそれでもPostgreSQLが必要なのか？ ⚖️</h2>

<p>ここまで読んで、「カラム・ストアがそんなに凄いなら、全部それを使えばいいじゃないか！」と思うかもしれません。しかし、それをしてはいけません。カラム・ストアには致命的な弱点があります。それは「1行のデータを書き込む（INSERTする）」のが悪夢のように遅いということです。</p>

<p>新しいユーザーを1人登録するためだけに、データベースは10個の列のための「10個の異なるファイル」をすべて開き、解凍し、データを追加し、また圧縮して閉じなければなりません。これは強烈なディスクへのランダム書き込みを発生させ、システム全体をロック（停止）させてしまいます。</p>

<p>これが、現代のソフトウェア業界が「2つの世界」に分かれている理由です。ユーザーが頻繁にデータを書き込むWebアプリケーションの裏側には、これまで通りPostgreSQLやMySQL（ロウ・ストア）を使わなければなりません。そして夜中に、そのデータを巨大なバッチ（塊）として抽出し、分析用ダッシュボードの裏側にいるClickHouseやSnowflake（カラム・ストア）に一気に流し込むのです。C-Storeはロウ・ストアを殺したわけではありません。「集計と分析の数学においては、列（Column）こそが絶対的な王である」ということを証明しただけなのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'データベースを90度回転させる：カラムナー型（列指向）データベースの魔法',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Columnar Database', 'C-Store', 'Big Data', 'Vectorization']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 11 (C-Store Columnar) with Categories, Tags, and Translation Links!\n";
