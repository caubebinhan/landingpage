<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'what_goes_around.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'What Goes Around',
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

$cat_en = setup_term('NoSQL & Big Data', 'category', 'en');
$cat_vi = setup_term('NoSQL và Dữ Liệu Lớn', 'category', 'vi');
$cat_ja = setup_term('NoSQLとビッグデータ', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The NoSQL Illusion: Reinventing the Broken Wheel</h2>

<p>There is a powerful amnesia in the software engineering community. Every decade or so, a new generation of developers enters the industry, looks at the established technologies (like Relational Databases and SQL), and declares them "too slow," "too rigid," or "too complicated." They throw out fifty years of computer science research and invent something entirely "new."</p>

<p>In the late 2000s, this rebellion was branded as the <strong>NoSQL Movement</strong>. Startups everywhere were replacing PostgreSQL and MySQL with Document Stores (like MongoDB) and Key-Value Stores (like Redis or Cassandra). The pitch was seductive: "Schema-less! Web-scale! Just dump your JSON objects directly into the database and let the developers move fast and break things."</p>

<p>But if you read Michael Stonebraker and Joseph Hellerstein\'s seminal paper, <em>"What Goes Around Comes Around"</em>, you realize something deeply embarrassing about the NoSQL movement. The "innovations" of NoSQL—schemaless records, navigating data via pointers, application-side joins—were not new at all. They were the exact same architectural mistakes that the industry had made, suffered through, and abandoned in the 1960s with the IMS Hierarchical Model and the CODASYL Network Model.</p>

<h2>The Terrible Price of "Schema-Less"</h2>

<p>The primary marketing hook of NoSQL was the elimination of the Schema. In a relational database, you must define your columns (Name: String, Age: Integer) before you insert data. NoSQL advocates argued this was too restrictive for Agile development.</p>

<p>But Stonebraker’s paper reminds us of a fundamental law of physics in software engineering: <strong>Data always has a schema. The only question is whether the database enforces it, or your application code enforces it.</strong></p>

<p>When you use a schemaless document store, you are simply shifting the burden of data integrity from the C++ Database Engine to your JavaScript/Python application code. If a developer accidentally inserts a user document where "Age" is the string "twenty" instead of the integer 20, the database happily accepts it. Three months later, a different microservice tries to calculate the average age of users, crashes on the string "twenty", and brings down your entire production system.</p>

<p>In the 1960s, we learned that application-enforced schemas lead to unmaintainable spaghetti code. Codd invented the Relational Model precisely to solve this. By throwing away the schema, NoSQL doomed a new generation of developers to re-learn this painful lesson.</p>

<h2>The Return of Application-Level Joins</h2>

<p>Another "feature" of early NoSQL systems was the lack of JOINs. Because data was distributed across multiple nodes for scalability, performing a distributed JOIN was considered too computationally expensive. The advice was: "De-normalize your data (duplicate it), or perform the JOIN in your application code."</p>

<p>This is exactly how CODASYL Network databases worked in 1969. Programmers had to write manual <code>for</code> loops to fetch a User record, and then fetch all the Order records associated with that User ID.</p>

<p>Why is this bad? Because the Database Engine is infinitely better at optimizing data retrieval than your application code. A modern SQL optimizer can rewrite your query, choose between Hash Joins, Merge Joins, or Nested Loops, and execute it using low-level CPU vector instructions. When you write a <code>for</code> loop in Node.js to fetch data over the network, you are introducing massive network latency (the N+1 Query Problem) and completely bypassing decades of query optimization research.</p>

<h2>The Inevitable Conclusion: NewSQL</h2>

<p>Stonebraker’s paper predicted the exact trajectory of the NoSQL movement. He warned that once these startups grew up and had to deal with complex financial transactions, regulatory compliance, and data consistency, they would realize that abandoning ACID guarantees (Atomicity, Consistency, Isolation, Durability) was a catastrophic mistake.</p>

<p>And he was right. What happened to the NoSQL movement? MongoDB added multi-document ACID transactions and Schema Validation. Cassandra added SQL-like query languages (CQL). CockroachDB and Google Spanner emerged as "NewSQL" databases—offering the horizontal scalability of NoSQL, but refusing to compromise on strict SQL schemas and ACID transactions.</p>

<h2>Lessons Learned: Read the History Books</h2>

<p>The tech industry moves at breakneck speed, but the foundational principles of data management do not. <em>What Goes Around Comes Around</em> is not just a critique of NoSQL; it is a plea for historical literacy.</p>

<p>Before you adopt the shiny new database that promises to solve all your problems by abandoning the "old ways," take a moment to read the academic papers from the 1970s and 80s. Chances are, someone already tried your "new" idea forty years ago, realized it caused massive data corruption, and invented the Relational Model to fix it. Don\'t reinvent the broken wheel.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The NoSQL Illusion: Why History Always Repeats Itself',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['NoSQL', 'ACID', 'Michael Stonebraker', 'History']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Hội Chứng Mất Trí Nhớ Của Ngành Công Nghiệp Phần Mềm</h2>

<p>Là một Engineering Manager, tôi thường xuyên phải phỏng vấn các kỹ sư trẻ. Cứ khoảng 10 năm một lần, một thế hệ lập trình viên mới lại bước vào ngành, nhìn vào những công nghệ "đã được chứng minh" (như Hệ quản trị cơ sở dữ liệu quan hệ - RDBMS và SQL), và bĩu môi phán: "Thứ này quá chậm, quá cứng nhắc, và quá khó scale". Họ quyết định vứt bỏ 50 năm ròng rã nghiên cứu của giới Khoa học Máy tính vào sọt rác, và tự mình phát minh ra một thứ "hoàn toàn mới mẻ".</p>

<p>Vào cuối thập niên 2000, cuộc nổi loạn này mang một cái tên rất kêu: <strong>Phong trào NoSQL</strong>. Các Startup trên toàn thế giới đua nhau gỡ bỏ PostgreSQL/MySQL để thay thế bằng Document Stores (như MongoDB) hay Key-Value Stores (như Redis, Cassandra). Lời chào mời của giới tư bản (VCs) và các Evangelists nghe cực kỳ bùi tai: "Không cần Schema (Schema-less)! Scale vô hạn cỡ Web-scale! Cứ ném cục JSON của bạn thẳng vào Database, khỏi cần nghĩ nhiều, cứ Code nhanh và Đập phá đi (Move fast and break things)".</p>

<p>Nhưng nếu bạn là một kỹ sư có học thuật, và bạn đã đọc qua bài luận văn kinh điển <em>"What Goes Around Comes Around" (Điều gì đi rồi cũng sẽ quay lại)</em> của Michael Stonebraker (người đoạt giải Turing) và Joseph Hellerstein, bạn sẽ nhận ra một sự thật vô cùng nhục nhã về phong trào NoSQL. Những thứ được rêu rao là "đột phá" của NoSQL—như dữ liệu không cần Schema, duyệt dữ liệu bằng con trỏ, tự code tính năng Nối bảng (JOIN) ở tầng Application—hoàn toàn không có gì mới mẻ. Đó chính xác là những sai lầm kiến trúc tồi tệ nhất mà ngành công nghiệp phần mềm đã từng phạm phải, từng đau đớn gánh hậu quả, và cuối cùng phải khai tử vào thập niên 1960 với mô hình IMS (Hierarchical) và CODASYL (Network).</p>

<h2>Cái Giá Phải Trả Bằng Máu Của Việc "Không Cần Schema" (Schema-less)</h2>

<p>Miếng mồi câu khách (Marketing hook) lớn nhất của NoSQL là việc tiêu diệt Schema. Trong Database quan hệ (SQL), bạn phải định nghĩa rõ ràng cấu trúc cột (Tên là String, Tuổi là Integer) trước khi được phép ghi dữ liệu. Những người cuồng NoSQL cãi rằng điều này làm chậm quá trình phát triển Agile, rằng hệ thống nên linh hoạt chấp nhận mọi loại dữ liệu.</p>

<p>Nhưng bài báo của Stonebraker đã tát thẳng vào mặt lập luận đó bằng một định luật vật lý bất biến của Software Engineering: <strong>Dữ liệu lúc nào cũng CÓ Schema. Vấn đề duy nhất là: Database sẽ ép kiểu (enforce) cái Schema đó, hay là Code Application của bạn phải tự đi mà ép kiểu.</strong></p>

<p>Khi bạn dùng một cái Document Store không có Schema, bạn chỉ đơn giản là đang đá quả bóng trách nhiệm (về tính toàn vẹn dữ liệu) từ cái Engine C++ siêu việt của Database sang mớ code JavaScript/Python lỏng lẻo của bạn. Nếu một bạn Dev Junior vô tình chèn một document User mà trường "Age" (Tuổi) lại ghi là chữ "hai mươi" thay vì con số 20, Database NoSQL vẫn vui vẻ gật đầu lưu lại. Ba tháng sau, một Microservice khác chạy báo cáo tài chính, cố gắng tính trung bình độ tuổi khách hàng, nó đọc phải chữ "hai mươi", quăng ra lỗi <code>NaN</code> (Not a Number), và làm sập toàn bộ hệ thống Production của bạn ngay trong đêm giao thừa.</p>

<p>Vào thập niên 1960, các kỹ sư thời đó đã học được bài học máu xương này: Việc bắt Code Application phải đi rào đón, check type từng trường dữ liệu sẽ tạo ra một đống code rác (Spaghetti code) không thể bảo trì nổi. E.F. Codd đã phát minh ra Relational Model chính là để giải quyết tận gốc nỗi đau này. Bằng cách vứt bỏ Schema, phong trào NoSQL đã đẩy một thế hệ lập trình viên mới vào cảnh phải trả lại chính xác khoản học phí mà cha ông họ đã từng trả.</p>

<h2>Sự Hồi Sinh Của Thảm Họa "Application-Level JOINs"</h2>

<p>Một "tính năng" buồn cười khác của các hệ thống NoSQL đời đầu là sự vắng mặt của lệnh JOIN. Lý do biện minh là: Vì dữ liệu bị phân mảnh (Sharded) ra hàng trăm con server để scale, việc chạy lệnh JOIN phân tán là quá tốn CPU. Lời khuyên của các chuyên gia NoSQL lúc đó là: "Hãy phi chuẩn hóa (De-normalize) dữ liệu của bạn, copy nó ra nhiều bản, hoặc tự viết vòng lặp để JOIN dữ liệu ngay trong code Application (JavaScript/Java)".</p>

<p>Thật nực cười! Đây chính xác là cách mà các lập trình viên của hệ thống mạng CODASYL đã làm vào năm 1969. Lập trình viên phải viết thủ công một vòng lặp <code>for</code> để gọi API lấy thông tin User, sau đó dùng cái User_ID đó chạy thêm 10 cái request mạng nữa để lấy thông tin Đơn hàng (Orders).</p>

<p>Tại sao điều này lại tồi tệ đối với kiến trúc hệ thống? Bởi vì Database Engine thông minh hơn code của bạn gấp hàng vạn lần trong việc lấy dữ liệu. Một bộ tối ưu hóa (SQL Optimizer) hiện đại có thể tự động viết lại câu query của bạn, cân nhắc xem nên dùng thuật toán Hash Join, Merge Join, hay Nested Loop, và thực thi nó bằng các tập lệnh Vector cấp thấp của CPU. Khi bạn tự viết vòng lặp <code>for</code> trong Node.js để bốc dữ liệu qua mạng, bạn đang tự tạo ra thảm họa <strong>N+1 Query Problem</strong> (nghẽn cổ chai I/O mạng), và đồng thời phỉ báng 40 năm trời nghiên cứu tối ưu hóa truy vấn của giới học thuật.</p>

<h2>Sự Thừa Nhận Thất Bại Và Kỷ Nguyên NewSQL</h2>

<p>Bài báo của Stonebraker đã tiên đoán chính xác quỹ đạo sụp đổ của phong trào NoSQL. Ông cảnh báo rằng, khi mấy cái Startup công nghệ đó lớn lên, khi họ bắt đầu phải xử lý các giao dịch tài chính (Financial Transactions), tuân thủ kiểm toán (Compliance), và đòi hỏi tính nhất quán tuyệt đối của dữ liệu, họ sẽ chợt nhận ra rằng: Việc vứt bỏ các tiêu chuẩn ACID (Atomicity, Consistency, Isolation, Durability) là một sai lầm hủy diệt công ty.</p>

<p>Và lịch sử đã chứng minh ông đúng hoàn toàn. Chuyện gì đã xảy ra với phong trào NoSQL sau 10 năm? MongoDB phải lẳng lặng bổ sung tính năng Multi-document ACID Transactions và Schema Validation. Cassandra phải phát triển một ngôn ngữ truy vấn giống hệt SQL (gọi là CQL). Những gã khổng lồ mới như CockroachDB hay Google Spanner trỗi dậy với danh xưng "NewSQL"—những hệ thống có khả năng Scale ngang vô hạn như NoSQL, nhưng tuyệt đối không bao giờ thỏa hiệp với việc từ bỏ SQL Schema và tính ACID.</p>

<h2>Bài Học Quản Trị: Hãy Đọc Sách Lịch Sử Trước Khi Thiết Kế Hệ Thống</h2>

<p>Ngành công nghiệp công nghệ thay đổi với tốc độ ánh sáng (Framework JS mới ra mắt mỗi tuần), nhưng những nguyên lý nền tảng của Khoa học Dữ liệu (Data Management) thì không bao giờ suy xuyển. Bài báo <em>"What Goes Around Comes Around"</em> không chỉ là một gáo nước lạnh tát thẳng vào phong trào NoSQL; nó là một lời khẩn cầu về "Sự hiểu biết lịch sử".</p>

<p>Lần tới, trước khi bạn quyết định áp dụng một công nghệ Database mới toanh, bóng bẩy, hứa hẹn sẽ giải quyết mọi vấn đề của bạn bằng cách "vứt bỏ các tư duy cũ mèm", hãy dừng lại một nhịp. Hãy lên Google Scholar và đọc các bài báo học thuật từ thập niên 1970 và 80. Rất có khả năng, ai đó đã thử cái ý tưởng "mới mẻ" của bạn từ 40 năm trước, nhận ra rằng nó gây ra thảm họa mất mát dữ liệu, và đã phải phát minh ra Relational Model để sửa chữa sai lầm đó. Là một Engineering Manager, đừng để team của bạn phát minh lại một cái bánh xe... đã bị nổ lốp.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Sự Ảo Tưởng Của NoSQL: Tại Sao Lịch Sử Luôn Lặp Lại?',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['NoSQL', 'ACID', 'Michael Stonebraker', 'History']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>ソフトウェア業界の「健忘症（物忘れ）」問題 🩺</h2>

<p>ソフトウェア・エンジニアリングのコミュニティには、非常に強力な「記憶喪失」の病が存在します。10年ごとに新しい世代の若い開発者たちが業界に入ってきて、すでに確立された技術（リレーショナルデータベースやSQLなど）を見て、こう宣言するのです。「なんだこれ、遅すぎる！」「ルールが厳しすぎて窮屈だ！」「古臭い！」と。そして彼らは、過去50年にわたるコンピュータサイエンスの偉大な研究成果を窓から放り投げ、「自分たちが全く新しい革新的なものを発明した！」と大騒ぎするのです。</p>

<p>2000年代の終わり頃、この若き反逆者たちの運動は<strong>「NoSQL（ノー・エスキューエル）ムーブメント」</strong>というブランド名で呼ばれました。世界中のスタートアップ企業が、PostgreSQLやMySQLといった堅牢なデータベースを窓から投げ捨て、ドキュメントストア（MongoDBなど）やキーバリューストア（RedisやCassandraなど）に乗り換えました。</p>

<p>彼らのセールストークは、魔法のように魅力的でした。「スキーマ（テーブルの設計図）なんて要らない（Schema-less）！Webスケールで無限に拡張できる！JSONオブジェクトをそのままデータベースに投げ込むだけでいい！細かいことは気にせず、とにかくコードを書いて前に進め（Move fast and break things）！」</p>

<p>しかし、もしあなたが、チューリング賞（コンピュータ科学のノーベル賞）を受賞したマイケル・ストーンブレーカー（Michael Stonebraker）とジョセフ・ヘラースタインが書いた名論文<em>『What Goes Around Comes Around（巡り巡って元に戻る）』</em>を読めば、NoSQLムーブメントに関する非常に「恥ずかしい真実」に気づくはずです。NoSQLが主張した「革新（イノベーション）」——スキーマなし、ポインタによるデータ探索、アプリ側でのデータの結合（JOIN）——は、<strong>全く新しいものではなかった</strong>のです。それらは、1960年代にソフトウェア業界が「IMS（階層型）」や「CODASYL（ネットワーク型）」といった古いデータベースで犯し、苦しみ抜き、そして最終的に捨て去った「最悪のアーキテクチャの失敗」と完全に同じものだったのです。</p>

<h2>「スキーマなし（Schema-Less）」という甘い罠と、血みどろの代償 🩸</h2>

<p>NoSQLの最大のマーケティングの武器は、「スキーマ（設計図）の排除」でした。従来のリレーショナルデータベース（SQL）では、データを挿入する前に、「名前は文字列（String）」「年齢は数字（Integer）」と厳格にルールを定義しなければなりません。NoSQLの信奉者たちは、これが「アジャイル（俊敏な）開発の足かせになっている」と主張しました。</p>

<p>しかし、ストーンブレーカーの論文は、ソフトウェア工学における絶対不変の物理法則を私たちに突きつけます：<strong>「データには、必ずスキーマ（構造）が存在する。唯一の問いは、『データベースのエンジンがそれを強制（Enforce）するのか』、それとも『あなたのアプリのコードがそれを強制しなければならないのか』の違いだけである。」</strong></p>

<p>スキーマのないドキュメントデータベースを使うということは、データの整合性を守るという重い責任を、C++で書かれた堅牢なデータベースエンジンから、あなたの書いた不安定なJavaScriptやPythonのコードへと「丸投げ（シフト）」しているだけなのです。</p>

<p>もし、新人の開発者が間違えて「Age（年齢）」の項目に、数字の <code>20</code> ではなく、文字列で <code>"ハタチ"</code> と書いたユーザーデータを保存しようとしたとします。NoSQLデータベースは何も文句を言わず、笑顔でそれを受け入れます。しかし3ヶ月後、別のマイクロサービスが全ユーザーの「平均年齢」を計算しようとしたとき、その <code>"ハタチ"</code> という文字列を読み込んで <code>NaN（Not a Number：計算不能エラー）</code> を吐き出し、大晦日の深夜にあなたの本番システム全体を完全にダウンさせるのです。</p>

<p>1960年代、当時のエンジニアたちはこの痛ましい教訓を学びました。「アプリ側のコードでデータの型をチェックしようとすると、スパゲッティのように絡み合った保守不能なクソコードが生まれる」という事実です。エドガー・F・コッドが「リレーショナルモデル」を発明したのは、まさにこの苦痛を根絶するためでした。スキーマを窓から投げ捨てることで、NoSQLは、新しい世代の開発者たちに「先人たちがすでに払った高い授業料」をもう一度払わせるハメになったのです。</p>

<h2>悪夢の復活：「アプリ側でのJOIN（データ結合）」 🧟‍♂️</h2>

<p>初期のNoSQLシステムのもう一つの「機能」は、JOIN（複数のテーブルを結合する操作）が存在しないことでした。データを複数のサーバーに分散（シャーディング）して保存しているため、サーバーをまたいでJOINを実行するのはCPUコストが高すぎるとされたのです。当時のNoSQLエキスパートたちのアドバイスはこうでした。「データを非正規化（重複して保存）するか、<strong>あなたのJavaScriptやJavaのコードの中で <code>for</code> ループを回してJOINを書いてください。</strong>」</p>

<p>これは、呆れるほど馬鹿げた話です。なぜなら、1969年の古いネットワーク型データベース（CODASYL）のプログラマーたちがやらされていたことと全く同じだからです！</p>

<p>なぜアプリ側でJOINを書くのが悪いのでしょうか？ それは、データベースエンジンの方が、あなたの書くアプリのコードよりも、データを引っ張ってくる速度を「数万倍も賢く最適化できる」からです。現代のSQLオプティマイザ（最適化エンジン）は、あなたのクエリを書き換え、ハッシュ結合やマージ結合といった最適なアルゴリズムを選択し、CPUの低レベルな命令を使って爆速で処理します。あなたがNode.jsで <code>for</code> ループを書いてネットワーク越しにデータを取ってきているとき、あなたは<strong>「N+1クエリ問題（ネットワークの大渋滞）」</strong>という大災害を引き起こし、学術界が40年かけて築き上げたクエリ最適化の歴史を完全に侮辱しているのです。</p>

<h2>敗北の承認、そして「NewSQL」の時代へ 🌅</h2>

<p>ストーンブレーカーの論文は、NoSQLムーブメントの正確な末路を予言していました。彼はこう警告しました。「これらのNoSQLスタートアップが成長し、複雑な金融取引や、法的な監査、絶対に矛盾が許されないデータの一貫性（Consistency）に対処しなければならなくなったとき、彼らは『ACID保証（Atomicity, Consistency, Isolation, Durability）』を捨てたことが、企業を破壊するほどの致命的なミスだったと気づくことになるだろう。」</p>

<p>そして、彼の予言は100%的中しました。NoSQLムーブメントはどうなったでしょうか？ MongoDBはコソコソとマルチドキュメントACIDトランザクションとスキーマ検証機能を追加しました。CassandraはSQLとそっくりなクエリ言語（CQL）を開発しました。そして、CockroachDBやGoogle Spannerといった<strong>「NewSQL（ニュー・エスキューエル）」</strong>と呼ばれる巨人が登場しました。彼らは、NoSQLの「無限の拡張性」を持ちながら、SQLスキーマと厳密なACIDトランザクションを決して妥協しない（捨てない）データベースです。</p>

<h2>私たちが学んだこと：システムを作る前に、歴史の教科書を読もう 📖🔍</h2>

<p>テクノロジー業界の流行は光の速さで変化しますが（新しいJavaScriptフレームワークが毎週のように生まれます）、データ管理（Data Management）の基礎的な物理原則は決して変わりません。<em>『What Goes Around Comes Around』</em>は、単なるNoSQLへの批判ではありません。それは、ソフトウェアエンジニアに対する「歴史を学ぶことの重要性」を説く熱いメッセージなのです。</p>

<p>次にあなたが、「古い考え方を捨て去り、すべての問題を解決する！」と約束する、ピカピカの新しいデータベース・テクノロジーを採用しようとしたときは、少しだけ立ち止まってください。そして、1970年代や80年代のコンピュータサイエンスの論文（ペーパー）を読んでみてください。おそらく、あなたのその「新しい」アイデアは、40年前にすでに誰かが試して、大規模なデータ破壊を引き起こすことに気づき、それを修正するために「リレーショナルモデル（SQL）」を発明した……というオチが待っているはずです。壊れた車輪（Broken Wheel）を、もう一度発明するのはやめましょう。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'NoSQLの幻想：なぜ歴史は繰り返されるのか？（失われた10年）',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['NoSQL', 'ACID', 'Michael Stonebraker', 'History']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 8 (What Goes Around) with Categories, Tags, and Translation Links!\n";
