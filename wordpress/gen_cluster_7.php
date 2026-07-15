<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = 'C:\Users\linhlinh\.gemini\antigravity\brain\bfdcec0e-4227-4f2e-b197-e2d61d30b558\relational_model_codd_1784013598063.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'Relational Model Codd',
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

$cat_en = setup_term('Database Theory', 'category', 'en');
$cat_vi = setup_term('Lý Thuyết Cơ Sở Dữ Liệu', 'category', 'vi');
$cat_ja = setup_term('データベース理論', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Dark Ages of Data: Navigating the Network Model</h2>

<p>Before 1970, the concept of a "database" was a terrifying labyrinth. If you were a programmer working at IBM or a large bank, your data was likely stored using something called the <strong>Network Data Model</strong> (or the Hierarchical Model like IBM’s IMS). In these systems, data was physically chained together using hardcoded pointers on the disk.</p>

<p>Imagine writing a query to find an employee’s department. You couldn\'t just declare what you wanted. You had to write imperative code that physically navigated the disk: <em>"Start at the Company record, follow the pointer to the Department record, then traverse the linked list of Employee pointers until you find the right name."</em></p>

<p>This approach had a catastrophic flaw: <strong>Data Dependence</strong>. The application logic was inextricably glued to the physical layout of the data on the hard drive. If a database administrator decided to optimize performance by changing a linked list into a hash table, every single application that queried that data would instantly break. Programmers spent 80% of their time just rewriting navigation paths every time the disk structure changed.</p>

<h2>The Codd Rebellion: A Relational Model of Data</h2>

<p>In 1970, an IBM researcher named Edgar F. Codd published a paper that would completely alter the trajectory of computer science: <em>"A Relational Model of Data for Large Shared Data Banks"</em>.</p>

<p>Codd looked at the spaghetti of pointers and declared it mathematically absurd. He proposed a radical, entirely new paradigm based on <strong>Set Theory</strong> and <strong>First-Order Predicate Logic</strong>. Instead of navigating physical pointers, data should be presented to the user as simple, mathematical "Relations" (which we now call Tables), consisting of "Tuples" (Rows) and "Attributes" (Columns).</p>

<h3>The Concept of Data Independence</h3>
<p>The true genius of Codd’s paper was the concept of <strong>Data Independence</strong>. The user should only interact with a logical representation of the data. How the database physically stores that data on the disk—whether it uses B-Trees, Hash Maps, or sequential files—should be completely hidden from the user.</p>

<p>You don\'t tell the database <em>how</em> to find the data. You tell it <em>what</em> data you want. This birthed the concept of declarative query languages, eventually leading to the creation of SQL.</p>

<h2>Normal Forms and the Elimination of Anomalies</h2>

<p>Codd didn\'t just stop at tables. He introduced mathematical rigor to database design through <strong>Normalization</strong>. In the paper, he identified severe issues with early databases, which he called "Anomalies":</p>
<ul>
<li><strong>Update Anomalies:</strong> If a department\'s location changes, and that location is duplicated across 10,000 employee records, updating it is slow and prone to errors.</li>
<li><strong>Deletion Anomalies:</strong> If you delete the last employee in a department, you might accidentally delete the existence of the department itself.</li>
</ul>

<p>By enforcing strict mathematical rules (First, Second, and Third Normal Forms), Codd proved that data could be cleanly separated into distinct relations linked by Primary and Foreign Keys, guaranteeing absolute data integrity without physical pointers.</p>

<h2>The Irony of History: The Resistance from IBM</h2>

<p>Despite its mathematical perfection, Codd’s paper was initially met with intense hostility. IBM, Codd’s own employer, had invested millions of dollars in their flagship Hierarchical database (IMS). They viewed the Relational Model as a slow, academic toy that would threaten their cash cow.</p>

<p>They argued that a Relational Database would require the computer to perform complex "JOIN" operations on the fly, which would be catastrophically slow compared to following physical pointers. And in the 1970s, they were right. CPU and RAM were too slow. But Codd was a visionary; he knew that hardware would catch up, and that programmer productivity (Data Independence) would eventually become more valuable than raw machine cycles.</p>

<p>It took Larry Ellison, reading Codd\'s paper, to realize its commercial potential and found Oracle, forcing IBM to eventually pivot and release DB2. The Relational Model went on to dominate the world for the next 50 years.</p>

<h2>Lessons Learned: Mathematics Outlives Implementation</h2>

<p>Codd’s 1970 paper teaches us a profound lesson about software engineering. Implementations change. Hardware evolves. But sound mathematical principles endure.</p>

<p>Whenever we get bogged down in the specific implementations of modern tools—whether it\'s MongoDB, Redis, or Graph databases—we are often just re-learning the fundamental truths of data modeling that Codd formalized half a century ago. Abstraction and Data Independence are not just theoretical ideals; they are the bedrock of scalable software architecture.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The Fall of the Network Model and the Genius of E.F. Codd',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Relational Model', 'Edgar F. Codd', 'Data Independence', 'Database History']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Thời Kỳ Đồ Đá Của Dữ Liệu: Cơn Ác Mộng Của "Con Trỏ Vật Lý"</h2>

<p>Để thực sự trân trọng những gì chúng ta đang có hôm nay với PostgreSQL hay MySQL, bạn phải quay ngược thời gian về trước năm 1970. Nếu bạn là một kỹ sư phần mềm làm việc tại một ngân hàng lớn vào thời điểm đó, dữ liệu của bạn không nằm trong các "Bảng" (Tables). Nó bị trói buộc trong một thứ gọi là <strong>Mô hình Mạng (Network Model)</strong> hoặc Mô hình Phân cấp (Hierarchical Model).</p>

<p>Trong các hệ thống này, dữ liệu được liên kết với nhau bằng các con trỏ vật lý (physical pointers) ghi chết trên mặt đĩa cứng. Để viết một tính năng đơn giản như "Tìm phòng ban của nhân viên A", bạn không thể viết một câu lệnh khai báo (declarative) gọn gàng. Bạn phải viết các vòng lặp C/C++ (hoặc COBOL) hướng dẫn máy tính bò từng bước trên ổ cứng: <em>"Bắt đầu từ node Công ty, dò theo con trỏ xuống node Phòng ban, rồi lặp qua danh sách liên kết các con trỏ Nhân viên cho đến khi tìm thấy".</em></p>

<p>Kiến trúc này chứa đựng một lỗ hổng chí mạng về mặt rủi ro doanh nghiệp (Business Risk): <strong>Sự Phụ Thuộc Dữ Liệu (Data Dependence)</strong>. Logic của ứng dụng bị dán chặt bằng keo siêu dính vào cấu trúc vật lý của ổ đĩa. Nếu một DBA (Database Administrator) muốn tối ưu hóa hệ thống bằng cách đổi cấu trúc danh sách liên kết thành Hash Map, toàn bộ mã nguồn của hàng ngàn ứng dụng đang chạy sẽ lập tức bị hỏng (Break). Các kỹ sư phần mềm thời đó phải dành 80% thời lượng làm việc chỉ để... đập đi viết lại các đường dẫn điều hướng dữ liệu mỗi khi phần cứng thay đổi. Chi phí bảo trì phần mềm (Maintenance Cost) cao đến mức vô lý.</p>

<h2>Cuộc Nổi Loạn Của Edgar F. Codd: Lời Giải Từ Toán Học</h2>

<p>Vào năm 1970, một nhà nghiên cứu làm việc cho IBM tên là Edgar F. Codd đã xuất bản một bài báo khoa học định hình lại toàn bộ nền văn minh phần mềm: <em>"A Relational Model of Data for Large Shared Data Banks" (Mô hình Quan hệ cho các Ngân hàng Dữ liệu Chia sẻ Lớn)</em>.</p>

<p>Codd nhìn vào mớ hỗn độn của các con trỏ vật lý và tuyên bố rằng điều này thật nực cười về mặt toán học. Ông đề xuất một kiến trúc hoàn toàn mới dựa trên <strong>Lý thuyết Tập hợp (Set Theory)</strong> và <strong>Logic Vị từ Bậc 1 (First-Order Predicate Logic)</strong>. Thay vì ép lập trình viên phải lần mò theo con trỏ đĩa, dữ liệu nên được hiển thị dưới dạng các "Quan hệ" (Relations - ngày nay chúng ta gọi là Bảng/Tables) với cấu trúc Toán học chặt chẽ, bao gồm các "Bộ" (Tuples - Dòng) và "Thuộc tính" (Attributes - Cột).</p>

<h3>Chén Thánh Của Kỹ Thuật: Độc Lập Dữ Liệu (Data Independence)</h3>
<p>Sự vĩ đại cốt lõi trong bài báo của Codd không nằm ở việc vẽ ra cái Bảng. Nó nằm ở khái niệm <strong>Sự Độc lập Dữ liệu</strong>. Kỹ sư phần mềm chỉ nên tương tác với "Mô hình Logic" của dữ liệu. Còn việc hệ thống Database lưu trữ vật lý như thế nào dưới ổ cứng (dùng B-Tree, LSM-Tree, hay Hash Map) phải được giấu kín hoàn toàn (Abstracted away).</p>

<p>Bạn không cần chỉ thị cho Database <em>"Cách" (How)</em> để tìm dữ liệu. Bạn chỉ cần khai báo <em>"Cái Gì" (What)</em> bạn muốn. Tư duy này đã sinh ra khái niệm Ngôn ngữ truy vấn khai báo (Declarative Query Language), và nó chính là tiền thân của ngôn ngữ SQL huyền thoại.</p>

<h2>Chuẩn Hóa (Normalization) Và Việc Loại Bỏ Các Bất Thường</h2>

<p>Codd không chỉ dừng lại ở việc tạo ra bảng. Ông áp dụng sự chặt chẽ của Toán học vào việc thiết kế dữ liệu thông qua <strong>Các Dạng Chuẩn (Normal Forms)</strong>. Trong bài báo, ông vạch trần những sai lầm chết người của các hệ thống cũ, gọi là "Anomalies" (Sự bất thường):</p>
<ul>
<li><strong>Bất thường Cập nhật (Update Anomalies):</strong> Nếu địa chỉ của một phòng ban thay đổi, và địa chỉ đó đang bị ghi lặp lại ở 10.000 dòng dữ liệu nhân viên, việc cập nhật sẽ cực kỳ chậm và nguy cơ sai sót dữ liệu (Data Inconsistency) là rất cao.</li>
<li><strong>Bất thường Xóa (Deletion Anomalies):</strong> Nếu bạn xóa nhân viên cuối cùng của một phòng ban, bạn có thể vô tình xóa luôn sự tồn tại của phòng ban đó khỏi hệ thống.</li>
</ul>

<p>Bằng cách ép buộc các quy tắc Toán học (Dạng chuẩn 1, 2, 3), Codd chứng minh rằng dữ liệu có thể được chia tách sạch sẽ thành các Bảng độc lập, liên kết với nhau bằng Khóa Chính (Primary Key) và Khóa Ngoại (Foreign Key), đảm bảo tính toàn vẹn dữ liệu tuyệt đối mà không cần dùng đến con trỏ vật lý.</p>

<h2>Sự Trớ Trêu Của Lịch Sử: Sự Chống Đối Từ Chính IBM</h2>

<p>Dù hoàn hảo về mặt toán học, bài báo của Codd ban đầu bị ném đá dữ dội. Tập đoàn IBM (chủ lao động của Codd) lúc bấy giờ đã đổ hàng triệu đô la vào hệ thống Database phân cấp IMS của họ. Họ coi Mô hình Quan hệ của Codd là một "món đồ chơi học thuật" chậm chạp và đe dọa trực tiếp đến con gà đẻ trứng vàng của họ.</p>

<p>Các nhà lãnh đạo IBM lập luận rằng: Việc bắt máy tính phải thực hiện các phép "JOIN" (Nối bảng) trong lúc chạy thực tế (On the fly) sẽ tiêu tốn tài nguyên cực lớn và chậm chạp hơn rất nhiều so với việc chạy theo con trỏ vật lý. Và ở thập niên 70, họ đã... đúng. CPU và RAM thời đó quá yếu. Nhưng Codd là một người có tầm nhìn xa (Visionary). Ông tin rằng định luật Moore sẽ làm phần cứng rẻ đi và nhanh lên, trong khi <strong>năng suất của lập trình viên</strong> (nhờ Data Independence) mới là tài nguyên đắt đỏ nhất.</p>

<p>Phải chờ đến khi Larry Ellison (nhà sáng lập Oracle) đọc được bài báo của Codd và nhận ra mỏ vàng thương mại, Oracle ra đời và đánh sập thị trường. Lúc đó IBM mới cuống cuồng xoay trục và tung ra DB2. Mô hình Relational Database chính thức thống trị thế giới suốt nửa thế kỷ sau đó.</p>

<h2>Bài Học Quản Trị: Toán Học Luôn Sống Thọ Hơn Mã Nguồn</h2>

<p>Bài báo năm 1970 của Codd dạy cho chúng ta, những Engineering Manager và Architect, một bài học xương máu: Mã nguồn (Implementation) sẽ thay đổi. Phần cứng sẽ lỗi thời. Nhưng những nguyên lý Toán học vững chắc thì trường tồn.</p>

<p>Ngày nay, mỗi khi chúng ta bị sa lầy vào các cuộc chiến công nghệ (NoSQL vs SQL, MongoDB vs Postgres, GraphDB), chúng ta thực chất chỉ đang học lại những chân lý nền tảng về cấu trúc dữ liệu mà Codd đã quy chuẩn hóa từ nửa thế kỷ trước. Tính Trừu tượng (Abstraction) và Sự Độc lập Dữ liệu (Data Independence) không chỉ là lý thuyết viển vông; chúng là nền tảng sống còn để xây dựng các hệ thống phần mềm có khả năng Scale lên quy mô hàng triệu người dùng.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Sự Sụp Đổ Của Network Model Và Ánh Sáng Của Relational Model (E.F. Codd)',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Relational Model', 'Edgar F. Codd', 'Data Independence', 'Database History']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>暗黒時代のデータベース：物理ポインタの迷宮 🦇</h2>

<p>私たちが今日、PostgreSQLやMySQLといった便利なツールを当たり前のように使えることに感謝するためには、1970年以前の「暗黒時代」にタイムスリップする必要があります。当時の巨大企業や銀行のプログラマーにとって、データベースとは恐怖の迷宮でした。データは<strong>「ネットワーク・データモデル（Network Model）」</strong>や「階層型モデル（Hierarchical Model）」と呼ばれる仕組みで保存されていました。</p>

<p>これらの古いシステムでは、データ同士がディスク上の「物理的なポインタ（住所の矢印）」でガチガチに鎖で繋がれていました。</p>

<p>例えば、「ある社員が所属する部署を探す」という簡単なプログラムを書くとしましょう。今のように「このデータが欲しい！」と宣言することはできませんでした。プログラマーは、「会社のノードからスタートし、ポインタを辿って部署ノードに行き、そこから社員のポインタの鎖（連結リスト）をひたすら辿って目的の社員を見つける」という、迷路を歩き回るようなC言語やCOBOLのコードを書かなければならなかったのです。</p>

<p>この仕組みには、絶望的な欠陥がありました。それが<strong>「データ依存性（Data Dependence）」</strong>です。プログラムのロジックが、ハードディスク上の物理的なデータの並び方と「瞬間接着剤でくっついた状態」になっていたのです。もしデータベース管理者が「検索を速くするために、リスト構造からハッシュ構造に変えよう！」とディスクの構造をいじった瞬間、そこにつながっていた何千ものアプリケーションが即座にクラッシュ（崩壊）しました。当時のプログラマーは、仕事の80%の時間を「ディスクの構造が変わるたびに、ポインタを辿るコードを書き直す」という無駄な作業に費やしていたのです。</p>

<h2>エドガー・F・コッドの反逆：数学がもたらした光 🌟</h2>

<p>1970年、IBMの研究員であったエドガー・F・コッド（Edgar F. Codd）という天才が、コンピュータサイエンスの歴史を永遠に変える一本の論文を発表しました。それが<em>「A Relational Model of Data for Large Shared Data Banks（大規模共有データバンクのためのリレーショナル・データモデル）」</em>です。</p>

<p>コッドは、ポインタが絡み合ったスパゲッティ状態のデータベースを見て、「数学的に馬鹿げている！」と一刀両断しました。そして、<strong>「集合論（Set Theory）」</strong>と<strong>「一階述語論理（First-Order Predicate Logic）」</strong>という厳密な数学に基づいた、全く新しいパラダイムを提案したのです。</p>

<p>物理的なポインタを辿る代わりに、データは人間にとってわかりやすい数学的な「リレーション（Relation：現在のテーブル/表のこと）」、「タプル（Tuple：行）」、「アトリビュート（Attribute：列）」として表現されるべきだと主張しました。</p>

<h3>究極の魔法「データ独立性（Data Independence）」 🎩✨</h3>
<p>この論文の真の天才性は、テーブルを発明したことではありません。<strong>「データ独立性」</strong>という概念を確立したことです。プログラマーは、データの「論理的な形（見た目）」とだけ対話するべきであり、データベースが裏側でそれをハードディスクにどうやって保存しているか（B-Treeなのか、LSM-Treeなのか）は、完全に隠蔽（カプセル化）されるべきだと定義したのです。</p>

<p>あなたはデータベースに「どうやって（How）探すか」を命令する必要はありません。「何が（What）欲しいか」を伝えるだけでいいのです。この思想が、「宣言型クエリ言語」を生み出し、やがて私たちが愛する<strong>SQL</strong>という魔法の呪文へと進化していきました。</p>

<h2>正規化（Normalization）とデータの矛盾の排除 🧹</h2>

<p>コッドはテーブルを作っただけでなく、「データベースの設計」に数学的な厳密さを持ち込みました。それが<strong>「正規化（Normalization）」</strong>です。彼は古いシステムが抱えていた致命的なバグを「アノマリー（異常）」と呼びました：</p>
<ul>
<li><strong>更新の異常（Update Anomalies）：</strong> もし部署の住所が変更になったとき、その住所が1万人の社員データに重複して書き込まれていた場合、すべてを更新するのは極めて遅く、データが不一致になる（矛盾する）危険性が高くなります。</li>
<li><strong>削除の異常（Deletion Anomalies）：</strong> ある部署に所属する最後の1人の社員を削除したとき、システム上から「その部署自体の存在」までうっかり消え去ってしまう危険性です。</li>
</ul>

<p>コッドは、数学的なルール（第1、第2、第3正規形）を強制することで、データを「独立した綺麗なテーブル」に切り分け、それらを主キー（Primary Key）と外部キー（Foreign Key）で論理的に結びつける方法を証明しました。これにより、物理ポインタを使わなくてもデータの絶対的な整合性が保証されるようになったのです。</p>

<h2>歴史の皮肉：古巣IBMからの大反対 🛡️</h2>

<p>数学的に完璧であったにもかかわらず、コッドの論文は最初、大ブーイングで迎えられました。なんと彼を雇っていたIBM自身が最大の反対者だったのです。IBMは当時、「IMS」という階層型の古いデータベースに何百万ドルも投資して大儲けしていました。彼らは、コッドのリレーショナルモデルを「遅くて使い物にならない、学者のオモチャ」と見なし、自分たちのビジネスを脅かす存在として恐れたのです。</p>

<p>IBMの幹部たちはこう反論しました。「実行時に複数のテーブルを結合（JOIN）するなんて、物理ポインタを辿るより圧倒的に遅くて非現実的だ！」。そして1970年代の時点では、彼らは正しかったのです。当時のCPUとRAMは非力すぎました。</p>

<p>しかし、コッドは未来を見据えるビジョナリー（先見の明がある人）でした。彼は「ハードウェアはムーアの法則で安く・速くなるが、プログラマーの生産性（データ独立性）の価値はそれ以上に高くなる」と信じていました。結局、コッドの論文を読んでその商業的価値に気づいたラリー・エリソンが「Oracle（オラクル）」を設立し、大成功を収めました。焦ったIBMは慌てて方針を転換し、DB2をリリースすることになります。こうして、リレーショナルモデルはその後の50年間、世界を支配することになったのです。</p>

<h2>私たちが学んだこと：数学は実装よりも長生きする 📖🔍</h2>

<p>1970年のコッドの論文は、ソフトウェア・エンジニアリングにおける非常に深い教訓を教えてくれます。「実装のコードは変わる。ハードウェアは進化する。しかし、健全な数学的原則は永遠に生き続ける」ということです。</p>

<p>今日、私たちがMongoDBやRedis、グラフデータベースなどの最新ツールの実装に夢中になっているときでも、実は「コッドが半世紀前に定式化したデータモデリングの根本的な真理」を再学習しているに過ぎないことがよくあります。「抽象化（Abstraction）」と「データ独立性（Data Independence）」は、単なる机上の空論ではありません。それは、スケーラブルで壊れないソフトウェア・アーキテクチャを構築するための、最も強固な岩盤なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '物理ポインタの迷宮からの脱出：エドガー・F・コッドとリレーショナルモデルの誕生',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Relational Model', 'Edgar F. Codd', 'Data Independence', 'Database History']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 7 (Relational Model) with Categories, Tags, and Translation Links!\n";
