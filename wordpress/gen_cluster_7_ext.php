<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'relational_model_codd_1784013598063.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
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
<li><strong>What is it?</strong> Published in 1970 by Edgar F. Codd, "A Relational Model of Data for Large Shared Data Banks" is arguably the most important computer science paper ever written. It invented the Relational Database (the foundation of SQL, MySQL, Postgres, Oracle).</li>
<li><strong>The Core Problem:</strong> Before 1970, databases used "Navigational" models (Hierarchical or Network). Data was linked using hardcoded physical memory pointers. If you wanted to ask a new question (e.g., "Find all employees in the NY office"), you had to write a complex C program to physically traverse the pointers. If the IT department moved the data on the hard drive, every application broke.</li>
<li><strong>The Solution:</strong> Codd proposed separating the <em>logical representation</em> of data from its <em>physical storage</em>. He used mathematical Set Theory and First-Order Predicate Logic to define data as "Relations" (Tables) consisting of "Tuples" (Rows) and "Attributes" (Columns). Relationships are formed by matching values (Foreign Keys), not by physical memory addresses.</li>
<li><strong>Modern Reality:</strong> Codd\'s model birthed SQL (Structured Query Language), allowing users to declare <em>what</em> data they want, leaving the database engine to figure out <em>how</em> to get it. It remains a multi-billion dollar industry standard 50 years later.</li>
</ul>

<h2>Historical Context & The Catalyst: The Navigational Nightmare</h2>
<p>To appreciate the sheer magnitude of Edgar F. Codd\'s 1970 paper, you must understand the dark ages of data storage in the 1960s. IBM ruled the world with a database system called IMS (Information Management System), which used a <strong>Hierarchical Model</strong>. Shortly after, the CODASYL committee introduced the <strong>Network Model</strong>.</p>

<p>Both of these were "Navigational Databases." What does that mean? It means data was connected using raw, physical memory pointers. A "Department" record on the hard drive literally contained the exact byte address of the first "Employee" record. The first Employee contained a byte address pointing to the second Employee, forming a linked list on the bare metal.</p>

<p>This architecture caused two catastrophic problems:</p>
<ol>
<li><strong>Data Dependency:</strong> The application code was tightly coupled to the physical layout of the disk. If a junior DBA decided to reorganize the hard drive to save space, the memory addresses changed. Consequently, every single application program written by the company immediately broke and had to be recompiled.</li>
<li><strong>The Query Problem:</strong> There was no such thing as a "query language." If the CEO asked a novel business question: "Which departments have employees hired after 1965 who earn more than $10,000?", a programmer had to spend three weeks writing a custom procedural program to manually traverse the hardcoded pointers step-by-step.</li>
</ol>
<p>Codd, an Oxford-educated mathematician working at IBM, looked at this mess and was horrified by the lack of mathematical rigor. He realized the industry was building skyscrapers on quicksand.</p>

<h2>The Academic Breakthrough: First-Order Predicate Logic</h2>
<p>Codd\'s paper did not invent a new programming language; it invented a new mathematical framework for data. He proposed that we must absolutely sever the tie between the logical view of the data and its physical storage mechanics.</p>

<p>He turned to <strong>Set Theory</strong> and <strong>First-Order Predicate Logic</strong>. In mathematics, a "Relation" is a set of tuples. Codd translated this into database terms:</p>
<ul>
<li><strong>Relation:</strong> A Table.</li>
<li><strong>Tuple:</strong> A Row (a single record).</li>
<li><strong>Attribute:</strong> A Column (a property of the record).</li>
<li><strong>Domain:</strong> The set of permitted values for a column (e.g., Integers, Strings).</li>
</ul>

<p>The stroke of absolute genius was how Codd handled relationships. Instead of using physical byte pointers to link an Employee to a Department, he used <strong>Data Values</strong>. If the Department table has a row with ID `42`, the Employee table simply has a column called `DepartmentID` containing the number `42`. The link is logical, not physical. This invention of the <strong>Foreign Key</strong> changed software engineering forever.</p>

<h2>Deep Architectural Walkthrough: Relational Algebra</h2>
<p>Because the data was now represented as mathematical sets, Codd could apply mathematical operations to them. He defined a set of operations called <strong>Relational Algebra</strong>, which forms the theoretical basis of what we now know as SQL.</p>

<p>Instead of writing a procedural program instructing the computer <em>how</em> to navigate pointers, a user could write a declarative statement defining <em>what</em> they wanted. Codd defined 8 core algebraic operators:</p>

<ol>
<li><strong>Select (Restrict):</strong> Filter rows based on a condition (SQL: <code>WHERE</code>).</li>
<li><strong>Project:</strong> Select specific columns, discarding the rest (SQL: <code>SELECT col1, col2</code>).</li>
<li><strong>Cartesian Product (Cross Join):</strong> Combine every row of Table A with every row of Table B.</li>
<li><strong>Join:</strong> A Cartesian Product followed by a Selection based on matching keys (SQL: <code>INNER JOIN</code>). This is where the magic of Foreign Keys is executed.</li>
<li><strong>Union, Intersection, Difference:</strong> Standard set operations.</li>
</ol>

<p>When you write a SQL query today, the database\'s Query Optimizer parses your text, translates it into a tree of these Relational Algebra operators, and mathematically calculates the most efficient way to execute them on the physical disk. Codd\'s abstraction allowed the database engine to become smart.</p>

<h2>Modern Production Reality: The Birth of SQL and Oracle</h2>
<p>Ironically, IBM initially ignored Codd\'s paper. They were making millions selling their hierarchical IMS database and didn\'t want to cannibalize their own product. It took a few years for a group of researchers at IBM San Jose to build "System R" to prove Codd\'s theories worked in practice. During this project, Don Chamberlin and Ray Boyce invented <strong>SEQUEL</strong> (later renamed SQL) as a human-readable way to write Codd\'s relational algebra.</p>

<p>Meanwhile, a young entrepreneur named Larry Ellison read Codd\'s paper and the System R whitepapers. Realizing IBM was dragging its feet, Ellison beat them to the market, founding Software Development Laboratories, which later became <strong>Oracle Corporation</strong>. The rest is multi-billion dollar history.</p>

<h2>Expert Critique & Legacy</h2>
<p>Codd\'s Relational Model is the most enduring architecture in computer science. Web frameworks die every 3 years. Programming languages rise and fall. But the Relational Database has remained the undisputed foundation of global commerce, banking, and software for half a century.</p>

<p>However, the model is not without its critics. The strict, normalized schema required by Codd\'s rules creates friction with modern Object-Oriented programming languages—a problem famously known as the <strong>Object-Relational Impedance Mismatch</strong>. Furthermore, the necessity of ACID transactions and heavy `JOIN` operations makes relational databases difficult to scale horizontally across thousands of servers, which led to the NoSQL movement in the 2010s.</p>

<p>Yet, the NoSQL movement ultimately validated Codd. After years of trying to build "schemaless" and "non-relational" document stores, the industry realized that data <em>is</em> inherently relational. Today, modern distributed databases like Google Spanner and CockroachDB spend massive engineering effort to provide horizontal scalability while strictly adhering to Codd\'s glorious, 1970 mathematical Relational Model.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The Genesis of SQL: E.F. Codd and the Relational Model (1970)',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['E.F. Codd', 'Relational Model', 'SQL', 'Database History']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> Xuất bản năm 1970 bởi tiến sĩ Edgar F. Codd, "A Relational Model of Data for Large Shared Data Banks" được coi là bài báo khoa học máy tính quan trọng nhất mọi thời đại. Nó chính là bản thiết kế gốc khai sinh ra Cơ sở dữ liệu Quan hệ (Relational Database - nền tảng của MySQL, Postgres, Oracle, SQL Server).</li>
<li><strong>Vấn đề giải quyết:</strong> Trước năm 1970, Database dùng mô hình "Điều hướng" (Navigational). Dữ liệu được liên kết với nhau bằng các con trỏ địa chỉ bộ nhớ vật lý (Pointers) code cứng vào đĩa. Nếu muốn truy vấn một câu hỏi mới, Lập trình viên phải viết nguyên một phần mềm bằng C để mò mẫm dò theo từng con trỏ. Nếu thay đổi ổ cứng, toàn bộ phần mềm của công ty sẽ vứt đi.</li>
<li><strong>Giải pháp (Workflow):</strong> Codd dùng Lý thuyết Tập hợp (Set Theory) của Toán học để tách biệt hoàn toàn "Cấu trúc Logic" ra khỏi "Lưu trữ Vật lý". Dữ liệu được tổ chức thành các Bảng (Relations), Hàng (Tuples) và Cột (Attributes). Các bảng liên kết với nhau bằng các Giá trị trùng khớp (Foreign Key) thay vì địa chỉ ổ cứng.</li>
<li><strong>Thực tiễn Production:</strong> Bài báo này đã đẻ ra ngôn ngữ SQL. Nhờ Codd, Lập trình viên chỉ cần khai báo "Tôi muốn lấy dữ liệu gì" (Declarative), còn việc "Lấy như thế nào cho nhanh" (Tối ưu hóa I/O đĩa cứng) do Database tự lo. Hơn 50 năm sau, đây vẫn là tiêu chuẩn công nghiệp trị giá hàng trăm tỷ đô.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cơn Ác Mộng Của Con Trỏ Vật Lý (Navigational Nightmare)</h2>
<p>Để thấy được sự vĩ đại của Edgar F. Codd, bạn phải thấu hiểu sự nguyên thủy tột cùng của ngành IT vào thập niên 1960. Khi đó, IBM làm bá chủ thế giới với hệ thống Database mang tên IMS (Information Management System). Hệ thống này lưu dữ liệu theo mô hình Phân cấp (Hierarchical) và mô hình Mạng lưới (Network).</p>

<p>Giới kỹ sư gọi chúng là "Navigational Database" (Database điều hướng). Nghĩa là sao? Nghĩa là dữ liệu được móc nối với nhau bằng địa chỉ vật lý của ổ đĩa. Một dòng dữ liệu "Phòng Ban" lưu trên đĩa cứng sẽ chứa chính xác một dãy số Hexadecimal (ví dụ: `0x8F3A`) trỏ thẳng tới Sector chứa dòng dữ liệu "Nhân Viên" đầu tiên. Dòng Nhân Viên 1 lại chứa địa chỉ trỏ tới Nhân Viên 2, tạo thành một cái Linked List (Danh sách liên kết) nằm trần trụi trên phần cứng cơ học.</p>

<p>Kiến trúc này đẻ ra 2 thảm họa tàn khốc:</p>
<ol>
<li><strong>Sự phụ thuộc Dữ liệu (Data Dependency):</strong> Code của phần mềm bị trói chặt vào cấu trúc vật lý của ổ cứng. Giả sử công ty mua ổ cứng mới, hoặc ông DBA (Quản trị DB) quyết định gom mảnh ổ đĩa (Defrag) để chạy nhanh hơn. Các địa chỉ `0x8F3A` bị thay đổi. BÙM! Toàn bộ hàng trăm phần mềm quản lý của công ty lập tức crash, báo lỗi Memory Segfault, và đội Dev phải thức trắng đêm để viết lại code.</li>
<li><strong>Bi kịch Truy vấn (The Query Problem):</strong> Thời đó không có khái niệm "Ngôn ngữ truy vấn" (Query Language). Nếu Giám đốc hỏi: <em>"Lấy cho tôi danh sách nhân viên ở Hà Nội có lương trên 20 củ"</em>, đội IT sẽ khóc thét. Họ phải cử một ông Dev già dặn viết một chương trình bằng C hoặc COBOL, cấp phát bộ nhớ, tự viết vòng lặp `while` đi theo từng con trỏ ổ cứng để nhặt dữ liệu. Mất 3 tuần chỉ để trả lời 1 câu hỏi kinh doanh.</li>
</ol>
<p>E.F. Codd - một nhà toán học tốt nghiệp đại học Oxford đang làm việc tại IBM - nhìn vào mớ hỗn độn đó và cảm thấy kinh tởm sự thiếu chặt chẽ của nó. Ngành IT đang xây những tòa nhà chọc trời trên một đầm lầy vật lý.</p>

<h2>Đột Phá Học Thuật: Toán Học Hóa Dữ Liệu (First-Order Predicate Logic)</h2>
<p>Bài báo năm 1970 của Codd không phát minh ra một ngôn ngữ lập trình. Ông phát minh ra một <strong>Khung lý thuyết Toán học</strong>. Ông tuyên bố một định lý đanh thép: "Chúng ta bắt buộc phải cắt đứt hoàn toàn mối liên hệ giữa cách con người nhìn dữ liệu (Logical View) và cách ổ cứng lưu trữ dữ liệu (Physical Storage)".</p>

<p>Codd đem <strong>Lý thuyết Tập hợp (Set Theory)</strong> và <strong>Logic Vị từ Bậc 1 (First-Order Predicate Logic)</strong> vào khoa học máy tính. Trong Toán học, "Relation" (Quan hệ) là một tập hợp các Tuple. Codd ép ngành IT phải gọi theo Toán học:</p>
<ul>
<li><strong>Relation (Quan hệ):</strong> Ứng với khái niệm Bảng (Table).</li>
<li><strong>Tuple (Bộ giá trị):</strong> Ứng với khái niệm Hàng/Bản ghi (Row/Record).</li>
<li><strong>Attribute (Thuộc tính):</strong> Ứng với khái niệm Cột (Column).</li>
</ul>

<p>Nhưng cú đấm thiên tài nhất của Codd nằm ở cách ông xử lý sự liên kết. Thay vì dùng địa chỉ RAM/Ổ cứng để nối Nhân viên với Phòng ban, Codd dùng <strong>Giá Trị Dữ Liệu (Data Values)</strong>. Nếu Phòng IT có `ID = 42`, thì ở bảng Nhân Viên, ta chỉ việc thêm một cột `PhongBan_ID` và điền số `42` vào đó. Sự liên kết này hoàn toàn là Logic trên giấy, không dính dáng gì đến ổ cứng. Khái niệm <strong>Foreign Key (Khóa ngoại)</strong> chính thức ra đời, thay đổi lịch sử phần mềm mãi mãi.</p>

<h2>Giải Phẫu Kiến Trúc: Đại Số Quan Hệ (Relational Algebra)</h2>
<p>Khi dữ liệu đã bị ép vào khuôn khổ Toán học (Tập hợp), Codd bắt đầu tung ra các phép tính Toán học để nhào nặn chúng. Ông định nghĩa một bộ môn gọi là <strong>Đại số Quan hệ (Relational Algebra)</strong>. Đây chính là linh hồn của câu lệnh SQL mà bạn đang gõ hàng ngày.</p>

<p>Thay vì viết code C lặp `for/while` để chỉ thị cho máy tính <em>"Làm thế nào để lấy dữ liệu" (Imperative)</em>, Codd cho phép người dùng chỉ cần khai báo <em>"Tôi muốn lấy cái gì" (Declarative)</em>. Ông định nghĩa 8 phép toán cốt lõi, trong đó nổi bật nhất là:</p>

<ol>
<li><strong>Select (Chọn lọc):</strong> Lọc ra các Hàng thỏa mãn điều kiện (Chính là mệnh đề <code>WHERE</code> trong SQL).</li>
<li><strong>Project (Chiếu):</strong> Chỉ lấy một vài Cột, vứt bỏ các Cột khác (Chính là <code>SELECT cot_A, cot_B</code>).</li>
<li><strong>Cartesian Product (Tích Đề-các):</strong> Nhân chéo mọi dòng của Bảng A với mọi dòng của Bảng B.</li>
<li><strong>Join (Kết nối):</strong> Là sự kết hợp của Tích Đề-các và phép Lọc. Nó tìm các dòng có Foreign Key khớp nhau để ghép lại (Chính là <code>INNER JOIN</code>). Đây là cỗ máy phép thuật của Relational Database.</li>
</ol>

<p>Ngày nay, khi bạn gõ một câu lệnh SQL, bộ phận Query Optimizer (Trình tối ưu hóa truy vấn) của Database sẽ Parse câu SQL đó thành một cái Cây AST chứa các phép toán Đại số Quan hệ của Codd. Nhờ tính chất Toán học, Optimizer có thể tự động đổi chỗ các phép tính (ví dụ: Lọc dữ liệu trước rồi mới Join sau) để tìm ra con đường lấy dữ liệu nhanh nhất, tốn ít I/O ổ cứng nhất. Codd đã truyền trí thông minh cho Database.</p>

<h2>Thực Tiễn Production: Sự Phản Bội Của IBM Và Sự Trỗi Dậy Của Oracle</h2>
<p>Có một sự thật cay đắng: Khi Codd trình bày bài báo này, ban lãnh đạo IBM đã... nhét nó vào ngăn kéo. IBM đang kiếm bộn tiền (hàng trăm triệu đô la) từ việc bán hệ thống Database phân cấp IMS cũ kỹ. Họ không điên gì tự đập nồi cơm của mình để làm theo một ông Tiến sĩ Toán học.</p>

<p>Phải mất vài năm sau, một nhóm kỹ sư trẻ tại lab IBM San Jose mới lén lút lập ra dự án "System R" để chứng minh lý thuyết của Codd là khả thi. Trong dự án này, Don Chamberlin và Ray Boyce đã chế ra ngôn ngữ <strong>SEQUEL</strong> (sau đổi tên thành SQL) để con người gõ chữ tiếng Anh thay vì gõ ký hiệu Toán học của Codd.</p>

<p>Trong lúc IBM vẫn chần chừ không dám thương mại hóa, một chàng thanh niên tham vọng tên là <strong>Larry Ellison</strong> đã đọc được bài báo của Codd và tài liệu của System R. Nhận thấy IBM quá bảo thủ, Ellison quyết định khởi nghiệp, copy toàn bộ kiến trúc SQL của IBM và tung ra thị trường trước. Công ty của Ellison mang tên Software Development Laboratories, sau này đổi tên thành <strong>Oracle Corporation</strong>. Oracle trở thành gã khổng lồ nghìn tỷ đô, còn IBM thì ôm hận.</p>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Mô hình Quan hệ của Codd là kiến trúc có sức sống dai dẳng nhất trong lịch sử Khoa học Máy tính. Các Framework JS (React, Vue) ra đời và chết đi mỗi 3 năm. Các ngôn ngữ lập trình lên ngôi rồi lụi tàn. Nhưng Relational Database vẫn là trái tim bơm máu cho toàn bộ hệ thống Tài chính, Ngân hàng, và Thương mại điện tử toàn cầu suốt hơn nửa thế kỷ qua.</p>

<p>Tất nhiên, nó không hoàn hảo. Việc thiết kế Database chuẩn hóa (Normalized) khắt khe theo luật của Codd tạo ra sự xung đột thảm liệt với tư duy Lập trình Hướng đối tượng (OOP). Nỗi đau này được giới kỹ sư gọi là <strong>Object-Relational Impedance Mismatch (Sự lệch pha Trở kháng Khách thể-Quan hệ)</strong> - thứ đẻ ra những công cụ ORM cồng kềnh như Hibernate hay Prisma.</p>

<p>Đến những năm 2010, phong trào NoSQL bùng nổ, tuyên bố "Khóa ngoại và JOIN đã chết", chuyển sang dùng Document Store (như MongoDB) để dễ dàng scale server. Nhưng thời gian đã chứng minh Codd luôn đúng. Sau 10 năm vật lộn với NoSQL, ngành IT nhận ra dữ liệu sinh ra bản chất của nó ĐÃ LÀ CÓ QUAN HỆ VỚI NHAU. Ngày nay, các siêu Database phân tán hiện đại nhất thế giới như Google Spanner hay CockroachDB đều phải quay về quỳ gối trước chuẩn SQL và Mô hình Quan hệ Toán học tuyệt đẹp mà Edgar F. Codd đã vẽ ra trên mặt giấy từ năm 1970.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Bản Thiết Kế Vĩ Đại Năm 1970: E.F. Codd Và Sự Khai Sinh Của Thế Giới SQL',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['E.F. Codd', 'Relational Model', 'SQL', 'Database History']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 1970年にEdgar F. Codd（エドガー・F・コッド）博士が発表した「大規模共有データバンクのためのデータの関係モデル」という論文です。コンピュータサイエンスの歴史上、最も偉大で重要な論文と言われており、現在私たちが使っている「リレーショナル・データベース（MySQL、PostgreSQL、Oracleなど）」の完全な原点です。</li>
<li><strong>根本的な問題：</strong> 1970年以前のデータベースは、データを「ハードディスクの物理的なメモリアドレス（ポインタ）」で直接繋いでいました。「部署データ」の中に「社員データが保存されているディスクの番地」が書き込まれていたのです。そのため、ハードディスクを交換したりデータを整理したりすると番地が変わり、会社のすべてのシステムが壊れてしまうという致命的な欠陥（ナビゲーショナル地獄）を抱えていました。</li>
<li><strong>解決策：</strong> コッド博士は数学の「集合論」を使い、「論理的なデータ」と「物理的なハードディスク」を完全に切り離しました。データを「表（テーブル）」「行（タプル）」「列（属性）」として扱い、データ同士の繋がりは物理アドレスではなく「同じ値（外部キー）」を使うようにしたのです。</li>
<li><strong>現代の真実：</strong> この論文から「SQL」という言語が生まれました。プログラマーは「どうやってディスクからデータを取ってくるか」を書く必要がなくなり、「何が欲しいか（SELECT）」を宣言するだけでよくなりました。50年経った今でも、世界の金融・ITシステムを支える絶対的な王者です。</li>
</ul>

<h2>歴史的背景：物理ポインタの悪夢（ナビゲーショナル地獄） 😱</h2>
<p>コッド博士の1970年の論文がどれほど世界をひっくり返したかを理解するには、1960年代の「ITの暗黒時代」を知る必要があります。当時、世界を支配していたのはIBMの「IMS」という階層型データベースでした。当時のシステムは<strong>「ナビゲーショナル（案内型）データベース」</strong>と呼ばれていました。</p>

<p>ナビゲーショナルとはどういう意味でしょうか？ それは、データ同士が「ハードディスクの物理的な番地（ポインタ）」で鎖のようにつながれていたということです。ディスク上にある「IT部署」のデータを開くと、そこに <code>0x8F3A</code> というような16進数の暗号が書かれています。これが「最初の社員データの物理アドレス」です。その社員データを開くと、次の社員のアドレスが書かれています。プログラマーはこの鎖を一つずつ辿っていくしかなかったのです。</p>

<p>この仕組みは、2つの壊滅的な悲劇を生み出しました。</p>
<ol>
<li><strong>データと物理の癒着（Data Dependency）：</strong> 会社のプログラムが、ハードディスクの物理的な構造に完全に依存していました。もしIT管理者が「ディスクの空き容量を整理（デフラグ）しよう」と思ってデータを動かすと、ポインタのアドレスが変わってしまい、会社のすべてのプログラムがエラーで即死します。</li>
<li><strong>クエリ（質問）ができない：</strong> 社長が「1965年以降に入社した、給料1万ドル以上の社員リストを出してくれ」と頼んだとします。現代ならSQLを1行書くだけで1秒で終わりますが、当時は「SQL」が存在しません。C言語やCOBOLのベテランプログラマーが数週間かけて、ハードディスクのポインタを辿る複雑な探索プログラムをゼロから手書きしなければならなかったのです。</li>
</ol>
<p>オックスフォード大学出身の数学者であり、IBMの研究員であったコッド博士は、この泥沼のような状況を見て絶望しました。「IT業界は、数学的な厳密さのない、沈みゆく泥舟の上に摩天楼を建てようとしている」と。</p>

<h2>学術的ブレイクスルー：数学の「集合論」をデータベースへ 📐</h2>
<p>コッド博士の論文は、新しいプログラミング言語を発明したわけではありません。彼は<strong>「データのための新しい数学の枠組み」</strong>を発明しました。彼は、「人間が考える論理的なデータ構造」と「機械が扱う物理的なディスク構造」を完全に切り離すべきだと強く主張しました。</p>

<p>彼が持ち出した武器は、数学の<strong>「集合論（Set Theory）」</strong>と<strong>「一階述語論理」</strong>でした。数学の世界では、データの集まりを「リレーション（関係）」と呼びます。コッドはこれをデータベースの言葉に翻訳しました。</p>
<ul>
<li><strong>リレーション（Relation）：</strong> 表（テーブル）のこと。</li>
<li><strong>タプル（Tuple）：</strong> 表の1行（レコード）のこと。</li>
<li><strong>アトリビュート（Attribute）：</strong> 表の列（カラム）のこと。</li>
</ul>

<p>そして、最も天才的だったのは「データ同士の繋がり」の解決方法です。ディスクの物理アドレス（ポインタ）で社員と部署を繋ぐのをやめ、<strong>「データの値（Value）」</strong>で繋ぐことにしたのです。IT部署のIDが `42` なら、社員のテーブルに `部署ID` という列を作り、そこに `42` という数字を書き込むだけです。これが<strong>「外部キー（Foreign Key）」</strong>の誕生です。物理的な鎖は断ち切られ、データは論理的な繋がりだけで表現されるようになりました。</p>

<h2>アーキテクチャの徹底解剖：「関係代数」という名のSQLの魂 🧮</h2>
<p>データを「数学の集合」として定義できたことで、コッド博士はそこに「数学の計算（代数）」を適用することができました。彼は<strong>「関係代数（Relational Algebra）」</strong>と呼ばれる8つの基本操作を定義しました。これこそが、私たちが毎日書いている「SQL」の正体です。</p>

<p>プログラマーは「ディスクをどう動かすか」という手続き（Imperative）を書く必要がなくなり、「どんなデータが欲しいか」という宣言（Declarative）をするだけでよくなりました。コッドが定義した主要な数学操作は以下の通りです。</p>

<ol>
<li><strong>Select（制限）：</strong> 条件に合う「行」だけを抽出する（SQLの <code>WHERE</code> 句）。</li>
<li><strong>Project（射影）：</strong> 必要な「列」だけを残し、他を捨てる（SQLの <code>SELECT col1, col2</code>）。</li>
<li><strong>Cartesian Product（直積）：</strong> テーブルAのすべての行と、テーブルBのすべての行を掛け合わせる。</li>
<li><strong>Join（結合）：</strong> 直積の中から、外部キー（同じ値）が一致する行だけを残す（SQLの <code>INNER JOIN</code>）。これがリレーショナルデータベース最大の魔法です。</li>
</ol>

<p>現在、あなたがSQLを打ち込むと、データベースの中にある「クエリオプティマイザ（Query Optimizer）」という天才AIが、あなたのSQLをこの「コッドの関係代数」のツリーに変換します。そして数学的な計算を駆使して、「一番ディスクI/Oが少なくて済む、最速の検索ルート」を自動的に導き出してくれるのです。コッド博士のおかげで、データベースは「ただの箱」から「考える頭脳」へと進化しました。</p>

<h2>現代の真実：IBMの裏切りと、Oracle帝国の誕生 👑</h2>
<p>歴史の皮肉なところは、コッド博士が所属していたIBMの経営陣が、この歴史的論文を「無視」したことです。IBMは当時、古い階層型データベース（IMS）の販売で何百億円も稼いでおり、自社の主力製品を数学者の理論で潰す気など毛頭ありませんでした。</p>

<p>数年後、IBMサンノゼ研究所の若手エンジニアたちが、コッドの理論が実際に動くことを証明するために、内緒で「System R」というプロジェクトを立ち上げました。この中で、人間が英語のように関係代数を打ち込める言語<strong>「SEQUEL（のちのSQL）」</strong>が発明されました。</p>

<p>しかし、IBMがウダウダと製品化を渋っている間に、ある野心的な若き起業家がコッドの論文とSystem Rの資料を読み漁っていました。彼の名は<strong>ラリー・エリソン（Larry Ellison）</strong>。彼はIBMより先にこの理論を製品化し、Software Development Laboratoriesという会社を立ち上げました。これが現在の超巨大企業<strong>「Oracle（オラクル）」</strong>です。IBMは最大のチャンスを逃し、Oracleがデータベースの世界を制覇することになりました。</p>

<h2>専門家による批評と、不滅のレガシー 🏛️</h2>
<p>コッド博士のリレーショナル・モデルは、コンピュータサイエンスの歴史において最も長寿で、最も成功したアーキテクチャです。Webのフレームワーク（Reactなど）は3年で流行り廃りが変わりますが、リレーショナル・データベースは50年もの間、世界の銀行、航空会社、Amazonの買い物を裏で支え続けている絶対的な基盤です。</p>

<p>もちろん、弱点もあります。コッドが定めた厳格な「正規化（Normalization）」のルールは、現代の「オブジェクト指向プログラミング」とは相性が悪く、データ変換に苦労する「O/Rインピーダンス・ミスマッチ」という永遠の課題を生みました。また、複雑なJOIN計算は複数のサーバーに分散させることが難しく、2010年代には「SQLを捨てよう」というNoSQL（MongoDBなど）のブームが起きました。</p>

<p>しかし、10年間のNoSQLの実験を経て、IT業界は「やっぱりデータには関係性（リレーション）が必要だ」という結論に戻ってきました。現在、Google SpannerやCockroachDBのような最新鋭の分散データベースは、何百億円もの開発費を投じて、「無限にスケールしながらも、コッド博士が1970年に定義したリレーショナル・モデル（SQL）を完璧に再現する」ことに心血を注いでいます。エドガー・F・コッド博士の数学的ビジョンは、永遠に不滅なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'SQLの創造神 E.F. コッド：1970年の論文がいかにして世界を支配したか',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['E.F. Codd', 'Relational Model', 'SQL', 'Database History']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 7 (Relational Model)!\n";
