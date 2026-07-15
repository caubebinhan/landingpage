<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'what_goes_around_1784013726661.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
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
<li><strong>What is it?</strong> "What Goes Around Comes Around" is a seminal 2005 paper by Turing Award winner Michael Stonebraker. It is a critical, historical review of 35 years of database data models, proving that the software industry constantly reinvents old ideas, forgets why they failed, and ultimately returns to the Relational Model.</li>
<li><strong>The Core Problem:</strong> Every 10 years, a new generation of developers gets frustrated with SQL and the strict Relational Model. They invent "new" data models (Object-Oriented databases in the 90s, XML databases in the 2000s, NoSQL Document stores in the 2010s) claiming they are faster, more flexible, and "schemaless."</li>
<li><strong>The Solution/Observation:</strong> Stonebraker mathematically maps these "new" models back to the 1960s Hierarchical and Network models. He demonstrates that without a strict schema and declarative query language, these models always collapse under the weight of complex queries and data corruption, forcing them to slowly re-implement SQL features until they essentially become Relational databases again.</li>
<li><strong>Modern Reality:</strong> We saw this exactly with MongoDB (which started as schemaless NoSQL and slowly added schemas, JOINs, and ACID transactions) and GraphQL (which is essentially reinventing the Network Model traversal). Those who do not study database history are doomed to repeat it.</li>
</ul>

<h2>Historical Context & The Catalyst: The Amnesia of the IT Industry</h2>
<p>If you have worked in software engineering for more than five years, you have probably noticed a frustrating pattern. A new technology emerges, everyone claims it will kill the old technology, we spend millions of dollars rewriting our systems, and five years later, the "new" technology starts looking suspiciously like the thing it replaced.</p>

<p>Nowhere is this cyclical amnesia more prevalent than in databases. In 2005, Michael Stonebraker (the original creator of PostgreSQL) looked at the rising hype around "XML Databases." Startups were claiming that storing data as nested XML documents was the "future," and that flat, relational SQL tables were dead.</p>

<p>Stonebraker sighed. He had seen this exact movie before. He had seen it in the 1990s with Object-Oriented Databases (OODBMS). And he had seen the exact same architectural flaws in the 1960s with IBM\'s IMS hierarchical database. He wrote this paper to beg the industry: <em>Please stop reinventing the flat tire.</em></p>

<h2>The Academic Breakthrough: The 9 Eras of Data Models</h2>
<p>The paper is a masterclass in architectural taxonomy. Stonebraker divides database history into distinct eras, showing how each era was a reaction to the previous one, and how almost all non-relational models share the same fatal flaws.</p>

<h3>1. The IMS Era (Hierarchical - 1960s)</h3>
<p>Data was stored as a Tree. A "Customer" node had child "Order" nodes. It was extremely fast if your query followed the tree (e.g., "Get orders for Customer X"). It was computationally impossible if your query went against the tree (e.g., "Find the Customer who placed Order Y").</p>

<h3>2. The CODASYL Era (Network - 1970s)</h3>
<p>To fix the Tree problem, engineers allowed records to have multiple parents, creating a Network (Graph). This solved the query problem but created a "Navigational Nightmare." Programmers had to write complex C code to traverse raw memory pointers. Data independence was zero.</p>

<h3>3. The Relational Era (SQL - 1980s)</h3>
<p>Codd introduces the Relational Model. Pointers are destroyed. Data is stored in flat tables linked by values (Foreign Keys). You declare <em>what</em> you want (SQL), not <em>how</em> to get it. Data independence is achieved.</p>

<h3>4. The Entity-Relationship (ER) Era (Late 1980s)</h3>
<p>People tried to use ER diagrams as the actual database engine. Stonebraker notes this failed because ER is a great <em>design</em> tool, but a terrible <em>execution</em> engine.</p>

<h3>5. The Object-Oriented Era (OODBMS - 1990s)</h3>
<p>The rise of C++ and Java made developers hate SQL (The Object-Relational Impedance Mismatch). They built databases that directly stored C++ Objects on disk. Stonebraker points out this was just a regression to the 1970s CODASYL Network model. It brought back physical pointers and destroyed the declarative query language. It failed.</p>

<h3>6. The Object-Relational Era (Late 1990s)</h3>
<p>PostgreSQL is born. It added object-oriented features (custom types, inheritance) directly into the Relational SQL engine. A compromise that survived.</p>

<h3>7. The XML Era (2000s)</h3>
<p>The internet arrives. People want to store data as semi-structured XML documents. Stonebraker points out that XML is literally just the 1960s IMS Hierarchical Tree model wrapped in angle brackets. It suffered the exact same query limitations and died.</p>

<h2>Deep Architectural Walkthrough: The "Schemaless" Illusion</h2>
<p>The core architectural debate in the paper centers around <strong>Schema-on-Write</strong> vs <strong>Schema-on-Read</strong>.</p>

<p>Non-relational databases (XML, JSON Document stores) always market themselves as "Schemaless". You don\'t have to run <code>ALTER TABLE</code>; you just dump JSON into the database. Developers love this because it makes building the MVP (Minimum Viable Product) incredibly fast.</p>

<p>Stonebraker mathematically destroys this illusion. He argues that <em>data always has a schema</em>. If you don\'t enforce the schema in the database (Schema-on-Write), you are forcing the application code to handle it (Schema-on-Read). </p>

<p>If your <code>Users</code> collection has 10,000 documents where the age is an Integer, and 500 documents where the age is a String ("twenty-two"), your Database is happy. But your Application code must now contain hundreds of <code>if/else</code> statements to check data types. When a new developer joins the team and writes a Python script to calculate the average age, the script crashes. <strong>Schemaless databases do not remove complexity; they simply push the complexity into the application layer, where it is much harder to manage.</strong></p>

<h2>Modern Production Reality: The NoSQL Cycle (2010s)</h2>
<p>The most prophetic aspect of Stonebraker\'s paper is that it was written in 2005, five years <em>before</em> the NoSQL movement exploded. Yet, it perfectly predicted exactly what would happen to MongoDB, CouchDB, and Cassandra.</p>

<p>In 2010, developers frustrated with SQL scaling issues invented NoSQL Document Stores (JSON). They claimed Relational was dead. But a JSON document is mathematically identical to the XML Document, which is mathematically identical to the 1960s IMS Hierarchical model.</p>

<p>Exactly as Stonebraker predicted, as NoSQL databases matured and faced enterprise workloads, they realized they couldn\'t survive without SQL features. Over the next decade, MongoDB added Schemas (JSON Schema validation), they added JOINs (<code>$lookup</code>), and they added multi-document ACID transactions. They slowly, painfully rebuilt the Relational Database on top of a Document store.</p>

<p>Even GraphQL, the modern darling of frontend data fetching, is architecturally a reincarnation of the 1970s CODASYL Network model—navigating graphs via edges.</p>

<h2>Expert Critique & Legacy</h2>
<p>"What Goes Around Comes Around" is required reading for every Software Architect. It is a humbling reminder that Computer Science is not a linear march of progress; it is a pendulum.</p>

<p>The paper proves that the Relational Model did not win because of corporate backing by Oracle or IBM. It won because it relies on the strict, unyielding foundation of mathematical set theory. Navigational, Hierarchical, and Object models are optimized for the programmer\'s convenience at the exact moment of writing code. The Relational Model is optimized for the data\'s integrity over the 50-year lifespan of the company.</p>

<p>The ultimate lesson is clear: <strong>Data Outlives Code.</strong> Your beautifully crafted Python/Node.js application will be rewritten, deprecated, and deleted in 4 years. But the Data will remain. If you couple your database architecture to your application\'s temporary object model, you will eventually rewrite your database. If you use the Relational Model, your data will outlive us all.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'What Goes Around Comes Around: Why We Keep Re-inventing SQL',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Michael Stonebraker', 'Database History', 'NoSQL vs SQL', 'Architecture']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> "What Goes Around Comes Around" (Tạm dịch: Vòng Lặp Lịch Sử) là một bài báo huyền thoại năm 2005 của Michael Stonebraker (người tạo ra PostgreSQL và đạt giải Turing). Nó tóm tắt 35 năm lịch sử kiến trúc Database, vạch trần một sự thật cay đắng: Ngành IT liên tục phát minh lại cái bánh xe, quên mất tại sao các công nghệ cũ thất bại, để rồi cuối cùng luôn phải quay về quỳ lạy mô hình Relational (SQL).</li>
<li><strong>Vấn đề giải quyết:</strong> Cứ mỗi 10 năm, một thế hệ Dev trẻ lại cảm thấy chán ghét sự gò bó của SQL. Họ sáng chế ra các Database "kiểu mới" (Object-Oriented vào thập niên 90, XML vào thập niên 2000, NoSQL Document vào thập niên 2010), quảng cáo rầm rộ là "Nhanh hơn, linh hoạt hơn, không cần Schema (Schemaless)".</li>
<li><strong>Giải pháp (Workflow):</strong> Stonebraker dùng toán học để chứng minh: Bất kể cái vỏ bọc bên ngoài là XML hay JSON, bản chất bên trong của các Database "mới" này y hệt cái mô hình Phân cấp (Hierarchical) và Mạng lưới (Network) rác rưởi của thập niên 1960. Khi đụng phải bài toán dữ liệu lớn, chúng luôn sụp đổ và buộc phải lén lút copy các tính năng của SQL (JOIN, ACID, Schema) để sinh tồn.</li>
<li><strong>Thực tiễn Production:</strong> Lời tiên tri này đã ứng nghiệm 100% với MongoDB (ban đầu khinh bỉ SQL, sau phải thêm Schema, thêm hàm JOIN, thêm Transaction). Ai không học lịch sử Database, kẻ đó sẽ phải trả giá bằng mồ hôi và nước mắt.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Căn Bệnh Mất Trí Nhớ Của Ngành IT</h2>
<p>Nếu bạn làm Dev được trên 5 năm, chắc chắn bạn sẽ nhận ra một vòng lặp nực cười. Một công nghệ "Mới Hàng Hiệu" ra mắt, các sếp hô hào nó sẽ giết chết công nghệ cũ, công ty đốt hàng triệu đô để đập đi xây lại. Và 5 năm sau, cái công nghệ "Mới" đó phình to ra, vá chằng vá đụp, và trông giống y hệt cái công nghệ cũ mà nó vừa giết.</p>

<p>Không ở đâu căn bệnh mất trí nhớ này nặng nề như trong thế giới Database. Năm 2005, Michael Stonebraker nhìn vào cái trend "XML Database" đang làm mưa làm gió. Các Startup thi nhau gào thét rằng: <em>"Lưu dữ liệu dạng bảng SQL lỗi thời rồi, phải lưu dạng Document XML lồng nhau mới là tương lai!"</em></p>

<p>Stonebraker chỉ biết thở dài. Ông đã xem bộ phim này quá nhiều lần. Ông từng thấy nó vào thập niên 90 với trào lưu Object-Oriented Database (Lưu thẳng Object C++ xuống ổ cứng). Và ông từng thấy tận mắt lỗ hổng kiến trúc đó từ tận thập niên 1960 với con quái vật IMS của IBM. Cực chẳng đã, ông viết bài báo này như một tiếng thét tuyệt vọng: <em>Lạy các cụ, xin đừng phát minh lại cái bánh xe hình vuông nữa!</em></p>

<h2>Đột Phá Học Thuật: Mổ Xẻ 9 Kỷ Nguyên Của Data Model</h2>
<p>Bài báo này là một cuốn sách giáo khoa tàn nhẫn về phân loại Kiến trúc. Stonebraker chia lịch sử Database thành các kỷ nguyên, chỉ ra cách kỷ nguyên sau cố gắng sửa sai cho kỷ nguyên trước, và tại sao mọi mô hình phi quan hệ (Non-Relational) đều mang chung một căn bệnh nan y.</p>

<h3>1. Kỷ Nguyên IMS (Mô hình Phân cấp - 1960s)</h3>
<p>Dữ liệu lưu dạng Cây (Tree). Một "Khách hàng" sẽ ôm các nhánh con là "Đơn hàng". Truy vấn đi xuôi chiều cây (Tìm đơn hàng của ông A) thì cực nhanh. Nhưng truy vấn ngược chiều cây (Tìm xem món hàng này do ông nào mua) thì máy tính treo cứng. Quá ngu ngốc.</p>

<h3>2. Kỷ Nguyên CODASYL (Mô hình Mạng lưới - 1970s)</h3>
<p>Để sửa lỗi Cây, họ cho phép các nhánh đan chéo nhau thành Mạng lưới (Graph). Sửa được lỗi truy vấn, nhưng đẻ ra "Ác mộng Điều hướng" (Navigational Nightmare). Lập trình viên phải code C để tự tay dò dẫm từng con trỏ bộ nhớ (Pointers) trên ổ cứng. Nếu ông DBA đổi ổ cứng, code sập toàn tập.</p>

<h3>3. Kỷ Nguyên SQL (Mô hình Quan hệ - 1980s)</h3>
<p>Codd giáng thế. Chém đứt mọi con trỏ vật lý. Dữ liệu lưu thành Bảng phẳng, móc nối bằng Khóa ngoại (Foreign Key). Sinh ra ngôn ngữ SQL: Lập trình viên chỉ cần ra lệnh "Tao muốn lấy gì", còn việc "Lấy thế nào" để Database tự lo. Giải thoát hoàn toàn Dev khỏi cái ổ cứng.</p>

<h3>4. Kỷ Nguyên Object-Oriented Database (OODBMS - 1990s)</h3>
<p>Ngôn ngữ C++ và Java lên ngôi. Lập trình viên ghét cay ghét đắng việc phải map Object trong code thành Bảng trong SQL. Họ làm ra OODBMS để lưu thẳng Object xuống đĩa. Stonebraker vạch mặt: Trò này thực chất chỉ là lôi cái mô hình Mạng lưới rác rưởi của thập niên 70 quay lại. Nó đem con trỏ bộ nhớ quay về, và giết chết ngôn ngữ khai báo SQL. Dự án chết yểu.</p>

<h3>5. Kỷ Nguyên XML Database (2000s)</h3>
<p>Internet bùng nổ. Người ta rộ lên lưu dữ liệu lồng nhau bằng XML (giống JSON bây giờ). Stonebraker cười khẩy: XML thực chất chính là cái mô hình Cây IMS của thập niên 60 được bọc thêm mấy cái dấu ngoặc nhọn <code>&lt; &gt;</code>. Nó dính nguyên xi cái giới hạn truy vấn ngược chiều cây của năm 1960. Cuối cùng, XML Database cũng xuống mồ.</p>

<h2>Giải Phẫu Kiến Trúc: Cú Lừa Mang Tên "Schemaless"</h2>
<p>Cốt lõi của cuộc chiến nằm ở khái niệm: <strong>Schema-on-Write (Kiểm tra cấu trúc lúc Ghi)</strong> vs <strong>Schema-on-Read (Kiểm tra cấu trúc lúc Đọc)</strong>.</p>

<p>Các Database Non-relational (như XML hay JSON MongoDB) luôn dùng chung một bài Marketing: <em>"Chúng tôi là Schemaless (Không cần cấu trúc). Bạn không cần chạy <code>ALTER TABLE</code> đau đầu, có cục JSON nào cứ quăng bừa vào, chúng tôi nhận hết!"</em>. Các bạn Dev trẻ làm Startup cực kỳ mê trò này vì nó giúp làm ra sản phẩm (MVP) siêu nhanh.</p>

<p>Stonebraker dùng búa tạ toán học đập nát ảo tưởng này. Ông chỉ ra một chân lý: <strong>Dữ liệu luôn luôn có cấu trúc (Schema). Nó không tự mất đi.</strong> Nếu bạn lười biếng, không ép cấu trúc ngay dưới Database (Schema-on-Write), bạn đang đùn đẩy trách nhiệm đó lên tầng Application Code của bạn (Schema-on-Read).</p>

<p>Giả sử Collection <code>Users</code> của bạn có 10.000 user lưu Tuổi là số Nguyên (<code>25</code>), nhưng có 500 user bị lưu nhầm thành String (<code>"hai mươi lăm"</code>). MongoDB vẫn vui vẻ mỉm cười. Nhưng trong code Node.js của bạn bây giờ phải mọc ra hàng trăm cái hàm <code>if/else</code> để check kiểu dữ liệu. Hôm sau, một Data Analyst mới vào công ty, viết một cái script Python nhỏ để tính tuổi trung bình. Kịch bản tồi tệ xảy ra: Script crash tung tóe vì không cộng được Số với Chữ. <strong>Schemaless không hề triệt tiêu sự phức tạp, nó chỉ giấu rác dưới tấm thảm, và đẩy sự khổ nhục lên đầu các Lập trình viên đứng sau.</strong></p>

<h2>Thực Tiễn Production: Lời Tiên Tri Về Thế Hệ NoSQL (2010s)</h2>
<p>Điều đáng sợ nhất của bài báo này là nó được viết vào năm 2005, tức là 5 năm TRƯỚC KHI phong trào NoSQL bùng nổ. Vậy mà nó đã tiên tri chính xác 100% cái chết và sự tái sinh của MongoDB, CouchDB, và Cassandra.</p>

<p>Năm 2010, giới Dev bực mình vì MySQL không scale được, liền đẻ ra NoSQL Document Stores (lưu JSON). Họ gào lên: <em>"SQL đã chết!"</em>. Nhưng cấu trúc JSON lồng nhau thực chất chính là bản copy của XML, mà XML lại là bản copy của mô hình IMS năm 1960.</p>

<p>Đúng như Stonebraker dự đoán, khi NoSQL trưởng thành và phải đối mặt với các bài toán tài chính doanh nghiệp khắt khe, họ nhận ra mình không thể sống thiếu các tính năng của SQL. Suốt 10 năm qua, MongoDB phải lén lút vá víu: Họ phải ép Schema (JSON Schema Validation), họ phải đẻ ra hàm JOIN (<code>$lookup</code>), và họ phải hỗ trợ ACID Transaction nhiều bảng. Họ đã cắn răng xây dựng lại một cái Relational Database yếu kém trên nền tảng của một cái Document Store.</p>

<p>Ngay cả GraphQL, công nghệ Frontend thời thượng nhất hiện nay, về mặt kiến trúc cũng chỉ là sự tái sinh của mô hình Mạng lưới (Network) thập niên 70: Dò dẫm dữ liệu qua các Cạnh (Edges).</p>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>"What Goes Around Comes Around" là kinh thánh bắt buộc phải đọc đối với bất kỳ System Architect nào. Nó là một cái tát làm thức tỉnh sự ngạo mạn của ngành phần mềm: Khoa học máy tính không phải là một mũi tên tiến thẳng về phía trước; nó là một con lắc đồng hồ đu đưa qua lại.</p>

<p>Bài báo chứng minh rằng Mô hình Quan hệ (Relational Model) không chiến thắng vì được các tập đoàn Oracle hay Microsoft chống lưng. Nó thắng vì nó đứng trên nền tảng vững như bàn thạch của Toán học (Lý thuyết Tập hợp). Các mô hình Mạng lưới, Phân nhánh, hay Object-Oriented chỉ là những viên thuốc giảm đau tạm thời, được tối ưu hóa cho <em>sự lười biếng của Lập trình viên tại thời điểm viết code</em>. Trong khi đó, Mô hình Quan hệ được thiết kế để <em>bảo vệ sự toàn vẹn của Dữ liệu trong suốt vòng đời 50 năm của một công ty</em>.</p>

<p>Bài học tối thượng vô cùng tàn nhẫn: <strong>Data Outlives Code (Dữ liệu sống thọ hơn Code)</strong>. Cái cục code Python hay Node.js tuyệt đẹp mà bạn đang viết hôm nay, 4 năm nữa sẽ bị xóa bỏ và đập đi xây lại bằng Golang hay Rust. Nhưng Dữ liệu của khách hàng thì vẫn nằm đó. Nếu bạn thiết kế Database dựa trên cấu trúc Object tạm bợ của đống Code, bạn sẽ phải đập luôn cả Database. Nếu bạn dùng Mô hình SQL Quan hệ, dữ liệu của bạn sẽ trường tồn vĩnh cửu.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Vòng Lặp Lịch Sử: Tại Sao Ngành IT Cứ Ghét SQL Rồi Lại Phải Van Xin SQL?',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Michael Stonebraker', 'Database History', 'NoSQL vs SQL', 'Architecture']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 「What Goes Around Comes Around（歴史は繰り返す）」は、データベース界の巨匠でありチューリング賞受賞者のMichael Stonebraker（マイケル・ストーンブレーカー）が2005年に書いた伝説的な論文です。過去35年間のデータベースの歴史を振り返り、「IT業界は昔失敗したアイデアを名前を変えて何度も発明し、結局はリレーショナル（SQL）の凄さに気づいて戻ってくる」という残酷な真実を証明しました。</li>
<li><strong>根本的な問題：</strong> 約10年ごとに、若きエンジニアたちは「SQLは古くてダサくて、制約が厳しすぎる」とイライラし始めます。そして、「スキーマレスで自由だ！オブジェクト指向だ！」と叫んで「新しい」データベース（90年代のOODBMS、00年代のXML、10年代のNoSQL）を発明します。</li>
<li><strong>解決策（論文の指摘）：</strong> ストーンブレーカーは数学的・構造的に分析し、これらの「新しい」データベースの中身が、実は1960年代に大失敗した「階層型・ネットワーク型データベース」の単なる焼き直し（コピー）であることを暴きました。厳しいルール（スキーマ）がないデータベースは、システムが巨大化すると必ず崩壊し、結局はSQLの機能（JOINやトランザクション）を必死に追加して生き延びようとすることを予言しました。</li>
<li><strong>現代の真実：</strong> この論文の予言は、MongoDBなどのNoSQLデータベースで「100%的中」しました。「SQLなんていらない」と始まったMongoDBは、長年の苦労の末、スキーマチェック機能やJOIN（<code>$lookup</code>）、トランザクションを追加し、今や「SQLデータベースの劣化版」のような構造に近づいています。歴史を学ばない者は、同じ過ちを繰り返すのです。</li>
</ul>

<h2>歴史的背景：IT業界の「恐るべき健忘症」 🌀</h2>
<p>ソフトウェア開発の世界で5年以上働いていると、奇妙なサイクルに気づくはずです。ピカピカの「新しい技術」が登場し、誰もが「これで古い技術は死んだ！」と熱狂します。会社は数億円をかけてシステムを書き直します。しかし5年後、その「新しい技術」はどんどん複雑になり、気づけば「かつて自分たちが捨てた古い技術」とそっくりな姿になっているのです。</p>

<p>この「IT業界の健忘症（記憶喪失）」が最もひどいのが、データベースの世界です。2005年、PostgreSQLの生みの親であるマイケル・ストーンブレーカーは、世間で大流行していた「XMLデータベース」の熱狂を冷めた目で見ていました。スタートアップ企業は「データをフラットな表（SQL）に保存するのは時代遅れだ。これからはXMLのような入れ子（ツリー）構造で保存するのが未来だ！」と叫んでいました。</p>

<p>ストーンブレーカーは深い溜息をつきました。彼はこの光景を何度も見てきたからです。1990年代の「オブジェクト指向データベース（OODBMS）」ブームのときも同じでした。さらに遡れば、1960年代のIBMのIMS（階層型データベース）が抱えていた致命的な欠陥と「まったく同じ」アーキテクチャだったのです。彼はIT業界に「お願いだから、四角い車輪を再発明するのはやめてくれ」と訴えるために、この論文を書きました。</p>

<h2>学術的ブレイクスルー：データモデルの「9つの時代」を解剖する 🔬</h2>
<p>この論文は、アーキテクチャの分類学における最高傑作です。ストーンブレーカーはデータベースの歴史を時代ごとに切り分け、それぞれの時代がいかに前の時代への「反発」として生まれ、そして「非リレーショナル」なモデルがすべて同じ理由で自滅していったかを示しました。</p>

<h3>1. IMSの時代（階層型モデル - 1960年代）</h3>
<p>データを「親と子」のツリー（木）構造で保存しました。木を上から下へ辿る検索は爆速ですが、下から上（子から親）を検索するクエリは計算量が爆発して不可能になるという致命的な欠陥がありました。</p>

<h3>2. CODASYLの時代（ネットワーク型モデル - 1970年代）</h3>
<p>ツリーの欠陥を直すため、データ同士を網の目（グラフ）のようにポインタで繋ぎました。しかし、プログラマーがポインタのアドレスを自力で辿るC言語のプログラムを書かなければならず、地獄のような難しさ（ナビゲーショナル地獄）になりました。</p>

<h3>3. リレーショナル時代（SQLの誕生 - 1980年代）</h3>
<p>コッド博士が数学的理論（SQL）を持ち込みました。物理的なポインタの鎖を断ち切り、データを「フラットな表」にしました。プログラマーは「欲しいもの（WHAT）」を宣言するだけでよくなり、革命が起きました。</p>

<h3>4. オブジェクト指向時代（OODBMS - 1990年代）</h3>
<p>C++やJavaの流行により、プログラマーは「コード上のオブジェクトを、そのままディスクに保存したい（SQLの表に変換するのは面倒くさい）」と考えました。ストーンブレーカーはこれを「1970年代のネットワーク型モデルの最悪な復活」と切り捨てました。物理ポインタが復活し、SQLの便利さが失われ、このブームはあっけなく死にました。</p>

<h3>5. XML時代（2000年代）</h3>
<p>インターネットの普及で、HTMLのようなツリー構造（XML）でデータを保存するブームが来ました。ストーンブレーカーは笑いました。「これは1960年代のIMS階層型モデルに、<>というカッコを付けただけのただのパクリだ。当時の欠陥をそのまま引き継いでいる」。案の定、これも死にました。</p>

<h2>アーキテクチャの徹底解剖：「スキーマレス」という甘い罠 🍯</h2>
<p>この論文の最大のハイライトは、<strong>「Schema-on-Write（書き込むときにルールを守らせる）」</strong>と<strong>「Schema-on-Read（読むときにルールを解釈する）」</strong>の戦いです。</p>

<p>非リレーショナルなデータベース（XMLや現代のMongoDBなどのJSONストア）は、常に<strong>「スキーマレス（ルール無用）」</strong>を最大のウリにします。「めんどくさい <code>ALTER TABLE</code> なんて不要です！ JSONを投げ込めば何でも保存できますよ！」と。スタートアップの若手エンジニアは、爆速でプロトタイプが作れるためこれに飛びつきます。</p>

<p>しかし、ストーンブレーカーはこの幻想を数学のハンマーで粉砕します。<strong>「データには必ずルール（スキーマ）が存在する。データベースからルールを無くせば、そのツケはすべてアプリケーション側のコードが払うことになる」</strong>と。</p>

<p>たとえば <code>Users</code> テーブルに、年齢が「数字（25）」で保存された人が1万人、間違って「文字列（"二十五"）」で保存された人が500人いたとします。MongoDBは文句を言わず保存します。しかし、あなたが書くNode.jsのコードの中には、「もし年齢が文字列だったら数字に変換して...」という <code>if/else</code> のゴミコードが数百個も発生します。翌日、新人データサイエンティストがPythonで「平均年齢を出すスクリプト」を書いた瞬間、文字と数字が足せずにシステムがクラッシュします。<strong>スキーマレスとは「複雑さを消す」魔法ではなく、「データベースの複雑さを、アプリのコードという見えない場所に隠す」だけの最悪の先送りなのです。</strong></p>

<h2>現代の真実：NoSQLの壮大な遠回り（2010年代） 🎢</h2>
<p>この論文が本当に恐ろしいのは、2005年（「NoSQLブーム」が起きる5年も前）に書かれたにもかかわらず、MongoDB、CouchDB、Cassandraの運命を「完璧に予言」していたことです。</p>

<p>2010年、SQLのスケール（拡張性）にキレたエンジニアたちが「NoSQL（JSONドキュメントストア）」を発明し、「SQLは死んだ！」と宣言しました。しかし、JSONの入れ子構造はXMLのパクリであり、XMLは1960年代のIMSのパクリです。</p>

<p>ストーンブレーカーの予言通り、エンタープライズの複雑な業務に直面したNoSQLは、SQLの機能なしでは生き残れないことを悟りました。この10年間、MongoDBは裏でコソコソと、スキーマ検証機能（JSON Schema）、JOIN機能（<code>$lookup</code>）、そしてマルチドキュメント・トランザクション（ACID）を追加し続けました。彼らは「ドキュメントストアという最悪の土台の上に、不完全なリレーショナルデータベースを再構築する」という壮大な無駄骨を折ったのです。</p>

<h2>専門家による批評と、永遠の教訓 🏛️</h2>
<p>「What Goes Around Comes Around」は、すべてのシステムアーキテクトが絶対に読まなければならないバイブル（聖書）です。コンピュータサイエンスが常に前に進んでいるわけではなく、実はただの「振り子（行ったり来たり）」であることを教えてくれます。</p>

<p>リレーショナル（SQL）モデルが覇権を握ったのは、Oracleが強かったからではありません。「集合論」という数学の絶対的な基礎の上に構築されているからです。ネットワーク型やオブジェクト型データベースは、<strong>「プログラムを書く瞬間のエンジニアの怠慢（楽をしたい）」</strong>のために最適化されています。しかしリレーショナルモデルは、<strong>「50年間生き残る会社のデータの完全性」</strong>のために最適化されているのです。</p>

<p>この論文が教える究極の真理は残酷です。<strong>「Data Outlives Code（データはコードより長生きする）」</strong>。あなたが徹夜で書いた美しいPythonやTypeScriptのコードは、4年後にはすべて捨てられ、RustやGoに書き直されるでしょう。しかし、顧客のデータだけは一生残り続けます。もしあなたが「今のアプリのコードが書きやすいから」という理由でデータベースを選べば、数年後にデータベースごと捨てるハメになります。しかしSQLを選べば、あなたのデータはあなた自身よりも長生きするのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '歴史は繰り返す：なぜIT業界は「SQL」を嫌い、そして結局「SQL」に土下座するのか',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Michael Stonebraker', 'Database History', 'NoSQL vs SQL', 'Architecture']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 8 (What Goes Around)!\n";
