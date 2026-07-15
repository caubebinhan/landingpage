<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'zero_bloat_minimal.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Zero Bloat Architecture',
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

$cat_en = setup_term('Performance Optimization', 'category', 'en');
$cat_vi = setup_term('Tối Ưu Hiệu Năng', 'category', 'vi');
$cat_ja = setup_term('パフォーマンス最適化', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> Zero Bloat Architecture is a frontend engineering philosophy focused on shipping the absolute minimum amount of JavaScript to the browser. It heavily relies on build-time optimizations like Tree Shaking and Dead Code Elimination.</li>
<li><strong>The Core Problem:</strong> Modern web development relies on massive dependency trees (the <code>node_modules</code> black hole). If a developer imports a single utility function from a 5MB library like Lodash, the bundler historically shipped the entire 5MB library to the user\'s browser, causing devastatingly slow load times.</li>
<li><strong>The Solution:</strong> Using ES6 Modules (which are statically analyzable), modern bundlers trace the exact execution path of the code. They construct an AST, find which functions are actually imported and used, and physically "shake" the dependency tree, dropping all unused code before shipping it to production.</li>
<li><strong>Modern Reality:</strong> Tools like Rollup, ESBuild, and Webpack 5 use advanced Tree Shaking algorithms. Frameworks like Svelte and SolidJS take this further by compiling away the framework itself, leaving zero runtime bloat.</li>
</ul>

<h2>Historical Context & The Catalyst: The Dependency Black Hole</h2>
<p>To understand the obsession with "Zero Bloat", we have to look at the dark ages of frontend development—around 2012 to 2015. The invention of Node.js and NPM (Node Package Manager) revolutionized how developers wrote code. Instead of writing everything from scratch, you could just <code>npm install lodash</code> or <code>npm install moment</code>.</p>

<p>This led to a culture of aggressive dependency consumption. However, the module system of the time—CommonJS (used by Node.js)—was completely dynamic. You could write <code>const utils = require(getModuleName());</code>. Because the module name was determined at <em>runtime</em>, build tools like early Browserify or Webpack could not predict which parts of a library you were actually going to use.</p>

<p>The result was the <strong>Dependency Black Hole</strong>. You wanted one simple function: <code>isEqual()</code>. But because you imported it from Lodash using CommonJS, Webpack panicked and bundled the entirety of Lodash into your <code>main.js</code> file. Your application grew to 5 Megabytes. When a user on a 3G mobile connection visited your site, they had to download, parse, and execute 5MB of JavaScript before seeing a single button. The web became bloated, slow, and hostile to users.</p>

<h2>The Academic Breakthrough: Static Analysis via ES6 Modules</h2>
<p>The crisis of bloat forced the ECMAScript committee to make a profound architectural decision when designing the new ES6 Module system (<code>import</code> and <code>export</code>). They designed it to be strictly <strong>Static</strong>.</p>

<p>In ES6, you cannot conditionally import a module inside an <code>if</code> statement using the standard syntax. You must declare all imports at the very top level of the file. This seemingly restrictive rule was actually a stroke of genius. Because the imports are static, a build tool doesn\'t need to run the code to understand the dependency graph. It just needs to <em>read</em> the text.</p>

<p>This unlocked a compiler optimization technique originally developed in the 1990s for languages like Lisp: <strong>Tree Shaking (Dead Code Elimination)</strong>. Rich Harris, the creator of the Rollup bundler (and later Svelte), popularized this concept in the JavaScript ecosystem.</p>

<h2>Deep Architectural Walkthrough: How Tree Shaking Works</h2>
<p>Tree Shaking is not magic; it is rigorous graph traversal. Here is the exact pipeline a modern bundler uses to eliminate bloat:</p>

<h3>1. Building the Dependency Graph</h3>
<p>The bundler starts at your entry point (e.g., <code>index.js</code>). It parses the code into an Abstract Syntax Tree (AST) and looks for <code>import</code> statements. It follows every import, parsing those files into ASTs, until it has built a massive, interconnected Graph of every file in your project and in your <code>node_modules</code>.</p>

<h3>2. The Mark and Sweep Algorithm</h3>
<p>Once the graph is built, the bundler performs a "Mark and Sweep" algorithm, identical to how a Garbage Collector reclaims memory:</p>
<ul>
<li><strong>Marking (The inclusion phase):</strong> The bundler starts at the entry point. It looks at the functions you explicitly call. If you call <code>isEqual()</code>, it traces the pointer back to the exact node in the Lodash AST and "Marks" that specific function node as <em>Used</em>. Crucially, it only marks <code>isEqual</code>. It ignores the other 300 functions in Lodash.</li>
<li><strong>Sweeping (The elimination phase):</strong> After tracing all possible execution paths, the bundler looks at the massive AST. Any node (variable, function, or class) that does <em>not</em> have a "Used" mark is considered Dead Code. The bundler violently prunes these nodes from the tree.</li>
</ul>

<h3>3. Code Generation</h3>
<p>Finally, the bundler prints the surviving AST nodes back into a JavaScript file. You are left with a <code>bundle.js</code> that contains your code and <em>only</em> the <code>isEqual()</code> function. A 5MB payload is reduced to 10KB.</p>

<h2>Modern Production Reality: Side Effects and Optimization Barriers</h2>
<p>In theory, Tree Shaking is perfect. In production reality, it is a minefield. The biggest enemy of Tree Shaking is the <strong>Side Effect</strong>.</p>

<p>JavaScript is a highly dynamic language. What if a file in <code>node_modules</code> contains this code:</p>
<pre><code>export function unusedMath() { return 1 + 1; }
window.globalConfig = { init: true }; // SIDE EFFECT!</code></pre>

<p>Even if you never import <code>unusedMath</code>, the bundler cannot safely delete this file. Why? Because the mere act of evaluating this file mutates the global <code>window</code> object. If the bundler drops this file, it might break the application in unexpected ways. Because bundlers are conservative, they will "bail out" of Tree Shaking and include the entire file just to be safe.</p>

<p>To fix this, the community introduced the <code>"sideEffects": false</code> flag in <code>package.json</code>. This acts as a legal contract. It allows the library author to promise the bundler: <em>"My code is pure. If a function is not imported, you can safely delete the entire file without breaking anything."</em> This flag is the secret to achieving true Zero Bloat.</p>

<h2>Expert Critique & Legacy</h2>
<p>The pursuit of Zero Bloat has redefined the JavaScript ecosystem. It shifted the burden of performance from the user\'s browser to the developer\'s build step. We now spend seconds compiling our code so that millions of users can save milliseconds of load time.</p>

<p>It also paved the way for "Compiler-as-a-Framework" architectures like Svelte and SolidJS. Instead of shipping a massive 100KB React runtime to the browser to calculate DOM diffs, Svelte statically analyzes your components at build time and compiles them down to pure, surgical DOM manipulation statements. The framework itself is completely "shaken" away, leaving Zero Bloat.</p>

<p>However, the complexity of this build step is immense. Configuring Webpack or dealing with CommonJS vs ESModule interop is notoriously painful. The future of Zero Bloat lies in tools like Rust-based ESBuild or SWC, which perform these massive AST traversals natively, reducing build times from minutes to milliseconds, and finally delivering the promise of a fast, lightweight web.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Zero Bloat Architecture: The Brutal Art of Tree Shaking',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Tree Shaking', 'Bundlers', 'Performance', 'ESModules']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Khái niệm cốt lõi:</strong> Zero Bloat Architecture (Kiến trúc phi phình to) là triết lý Engineering tập trung vào việc đẩy một lượng JavaScript nhỏ nhất, tinh gọn nhất có thể xuống trình duyệt của người dùng. Kỹ thuật cốt lõi là Tree Shaking và Dead Code Elimination (Loại bỏ mã chết).</li>
<li><strong>Vấn đề giải quyết:</strong> Các dự án Web hiện đại thường bị nghiện thư viện (Hố đen <code>node_modules</code>). Bạn chỉ import một hàm <code>isEmpty()</code> từ Lodash, nhưng trình đóng gói (Bundler) thời xưa lại ngốc nghếch ném toàn bộ thư viện Lodash nặng 5MB xuống trình duyệt, làm Web tải chậm như rùa bò.</li>
<li><strong>Giải pháp (Workflow):</strong> Dựa vào đặc tính "Tĩnh" (Static) của ES6 Modules, các Bundler hiện đại dựng lên cây AST, dùng thuật toán dò tìm xem chính xác hàm nào được gọi. Sau đó chúng sẽ "Rung cây" (Tree Shaking), vứt bỏ toàn bộ những hàm không được dùng đến trước khi đóng gói file cuối cùng.</li>
<li><strong>Thực tiễn Production:</strong> Các công cụ như Rollup, ESBuild hay Webpack 5 đều mặc định bật Tree Shaking. Thậm chí các framework như Svelte còn đi xa hơn: Xóa sổ luôn cả bản thân cái Framework, chỉ để lại code Vanilla JS thuần túy (Zero Runtime Bloat).</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Hố Đen Lỗ Hổng <code>node_modules</code></h2>
<p>Để hiểu tại sao giới Frontend lại bị ám ảnh bởi từ khóa "Zero Bloat" (Không phình to), chúng ta phải nhìn lại thời kỳ tăm tối của Web Development vào khoảng 2012 - 2015. Sự ra đời của Node.js và NPM (Node Package Manager) đã thay đổi hoàn toàn cách chúng ta viết code. Thay vì tự code lại từ đầu, phương châm sống của Dev là: <em>"Cái gì khó, có NPM lo"</em>. Cần tính toán ngày tháng? <code>npm install moment</code>. Cần xử lý mảng? <code>npm install lodash</code>.</p>

<p>Nhưng sự tiện lợi này mang theo một liều thuốc độc. Chuẩn module thời đó là <strong>CommonJS</strong> (cú pháp <code>require()</code>). Đặc điểm của CommonJS là nó mang tính <strong>Động (Dynamic)</strong>. Bạn hoàn toàn có thể viết code thế này: <code>const x = require( condition ? "lodash" : "moment" )</code>. Vì tên thư viện chỉ được xác định lúc phần mềm ĐANG CHẠY (Runtime), các công cụ Build lúc bấy giờ (như Webpack 1.0) hoàn toàn bị mù. Chúng không thể đoán trước được bạn sẽ dùng hàm nào trong thư viện.</p>

<p>Hệ quả? Bạn chỉ muốn xài đúng 1 cái hàm <code>cloneDeep()</code> nhỏ xíu của Lodash, nhưng Webpack hoảng sợ và quyết định: <em>"Thà giết lầm hơn bỏ sót, tôi sẽ gói TOÀN BỘ thư viện Lodash 5MB vào file main.js của anh"</em>. Kết quả là ứng dụng Web của bạn phình to thành một con quái vật 10 Megabyte. Một user dùng mạng 3G trên điện thoại sẽ phải tải xuống, parse, và chạy 10MB JavaScript chỉ để bấm được một cái nút. Trải nghiệm người dùng (UX) bị hủy diệt hoàn toàn.</p>

<h2>Đột Phá Học Thuật: Phân Tích Tĩnh (Static Analysis) Qua ES6 Modules</h2>
<p>Cuộc khủng hoảng rác thải JavaScript này buộc hội đồng ECMAScript (những người thiết kế ra ngôn ngữ JS) phải đưa ra một quyết định mang tính lịch sử khi thiết kế chuẩn ES6 Module (cú pháp <code>import / export</code>). Họ quyết định ép nó phải mang tính <strong>Tĩnh (Static)</strong>.</p>

<p>Trong ES6, bạn KHÔNG THỂ viết <code>import</code> bên trong câu lệnh <code>if / else</code> (nếu dùng cú pháp import tĩnh). Bạn bắt buộc phải khai báo toàn bộ các dòng <code>import</code> ở sát mép trên cùng của file code. Quy định này nghe có vẻ độc đoán, nhưng nó là một nước cờ thiên tài của giới Compiler Engineering.</p>

<p>Bởi vì mọi import đều nằm cứng ở đầu file, các công cụ Build (như Rollup hay Webpack) không cần phải chạy thử code của bạn nữa. Chúng chỉ cần <strong>ĐỌC</strong> file text là có thể vẽ ra được một sơ đồ chính xác tuyệt đối xem file nào phụ thuộc vào file nào. Điều này chính thức mở khóa một tuyệt kỹ tối ưu hóa từ thập niên 90 (vốn dùng cho ngôn ngữ Lisp): <strong>Tree Shaking (Rung Cây)</strong> - hay tên khoa học là Dead Code Elimination (Loại bỏ mã chết).</p>

<h2>Giải Phẫu Kiến Trúc: Cơ Chế Hoạt Động Của Thuật Toán "Rung Cây"</h2>
<p>Tree Shaking không phải là phép thuật, nó là một thuật toán duyệt Đồ thị (Graph Traversal) cực kỳ tàn nhẫn. Dưới đây là cách mà một Bundler hiện đại (như Rollup) vắt kiệt từng byte rác trong code của bạn:</p>

<h3>1. Xây Dựng Đồ Thị Phụ Thuộc (Dependency Graph)</h3>
<p>Bundler bắt đầu từ file gốc của bạn (ví dụ <code>index.js</code>). Nó dùng Parser để biến code thành cây AST, quét tìm các lệnh <code>import</code>. Gặp file nào, nó chui vào file đó, tiếp tục tìm <code>import</code>. Quá trình này cứ tiếp diễn đệ quy cho đến khi nó vẽ ra được một cái Mạng Nhện (Graph) khổng lồ nối liền toàn bộ code của bạn và đống <code>node_modules</code>.</p>

<h3>2. Thuật toán Đánh Dấu và Quét (Mark and Sweep)</h3>
<p>Sau khi có mạng nhện, Bundler bắt đầu đóng vai một gã dọn rác:</p>
<ul>
<li><strong>Giai đoạn Đánh dấu (Mark):</strong> Bundler đứng ở hàm <code>main()</code> của bạn, nhìn xem bạn gọi hàm gì. Nếu bạn gọi <code>cloneDeep()</code>, nó lần theo sợi tơ nhện chạy thẳng vào file Lodash, tìm đúng cái Node chứa hàm <code>cloneDeep</code>, và đóng dấu "CÓ XÀI" (Used) lên cái Node đó. Mấu chốt ở đây là: 300 cái hàm còn lại trong Lodash (như <code>isEmpty</code>, <code>isEqual</code>) sẽ KHÔNG bị đóng dấu.</li>
<li><strong>Giai đoạn Cắt tỉa (Sweep):</strong> Sau khi duyệt xong mọi đường đi, Bundler lấy cây cưa máy ra. Bất kỳ một Node nào (biến, hàm, class) nằm trong cây AST mà KHÔNG có dấu "CÓ XÀI", nó sẽ bị phán quyết là <strong>Dead Code (Mã chết)</strong>. Bundler tàn nhẫn chặt đứt các Node đó và vứt thẳng vào sọt rác.</li>
</ul>

<h3>3. Đóng Gói (Code Generation)</h3>
<p>Cuối cùng, Bundler in những Node sống sót ra thành một file <code>bundle.js</code> duy nhất. Kết quả? File 5MB của Lodash giờ đây bị thu nhỏ lại chỉ còn đúng 5KB chứa mỗi thuật toán clone. Kiến trúc Zero Bloat được xác lập.</p>

<h2>Thực Tiễn Production: Quái Vật "Side Effects"</h2>
<p>Trên lý thuyết, Tree Shaking là vũ khí hoàn hảo. Nhưng trong thực tế Production, nó là một bãi mìn. Kẻ thù lớn nhất của thuật toán Rung cây là hiện tượng <strong>Side Effect (Hiệu ứng phụ)</strong>.</p>

<p>JavaScript là một ngôn ngữ quá linh hoạt. Giả sử trong thư viện <code>node_modules</code> có một file chứa đoạn code sau:</p>
<pre><code>export function hamKhongXaiToi() { return 1 + 1; }
window.cauHinhHeThong = { init: true }; // WARNING: SIDE EFFECT!</code></pre>

<p>Ngay cả khi bạn KHÔNG BAO GIỜ import hàm <code>hamKhongXaiToi</code>, Bundler vẫn KHÔNG DÁM xóa cái file này đi. Tại sao? Bởi vì chỉ riêng việc JavaScript engine đọc qua file này đã làm thay đổi một biến toàn cục (<code>window.cauHinhHeThong</code>). Nếu Bundler tự ý xóa file này, ứng dụng của bạn có thể bị sập một cách bí ẩn. Vì các Bundler được thiết kế theo tư tưởng "Thà chạy chậm còn hơn chạy lỗi", nó sẽ nhắm mắt gói toàn bộ file này vào. Tree Shaking bị vô hiệu hóa!</p>

<p>Để cứu vãn, giới Frontend đẻ ra một cái cờ (flag) cực kỳ quan trọng trong file <code>package.json</code>: <code>"sideEffects": false</code>. Đây là một "Bản cam kết pháp lý". Tác giả của thư viện dùng cái cờ này để thề độc với Bundler rằng: <em>"Code của tôi rất sạch (Pure). Nếu Dev không gọi hàm của tôi, anh cứ việc mạnh tay xóa sạch cả file đi, tôi đảm bảo không có lỗi xảy ra đâu"</em>. Việc config đúng cờ này chính là chìa khóa tối thượng để đạt được Zero Bloat.</p>

<h2>Bình Luận Chuyên Gia & Tương Lai (Expert Critique & Legacy)</h2>
<p>Cuộc chiến theo đuổi Zero Bloat đã định hình lại toàn bộ hệ sinh thái JavaScript. Nó tạo ra một triết lý thiết kế cực đoan: Đẩy toàn bộ gánh nặng tính toán từ Trình duyệt của User sang Công đoạn Build của Dev. Chúng ta thà chấp nhận ngồi chờ máy tính compile code mất 1 phút, để đổi lấy việc 1 triệu User truy cập web nhanh hơn 500 mili-giây.</p>

<p>Nó cũng là tiền đề đẻ ra các thế hệ Framework "Không có Runtime" như Svelte hay SolidJS. Thay vì bắt user tải về cục React.js nặng 100KB chỉ để chạy thuật toán Virtual DOM ngầm trong trình duyệt, Svelte đóng vai trò như một Compiler. Nó phân tích code của bạn lúc Build, và "dịch" thẳng nó ra thành các câu lệnh Vanilla JS tương tác với DOM nguyên thủy cực kỳ nhỏ gọn. Bản thân cái Framework Svelte bị "Rung cây" vứt đi mất tiêu, chỉ để lại Zero Bloat trên máy User.</p>

<p>Dù vậy, cái giá phải trả cho Zero Bloat là sự rườm rà của Tooling. Việc cấu hình Webpack, xử lý xung đột giữa CommonJS và ESModule vẫn là nỗi ác mộng của dân Frontend. Tương lai của kiến trúc này đang nằm trong tay những công cụ Build được viết bằng các ngôn ngữ hệ thống tốc độ cao (Rust, Go) như ESBuild hay SWC. Bằng sức mạnh xử lý AST gốc đa luồng, chúng giảm thời gian Rung Cây từ vài phút xuống còn vài mili-giây, mang lại một trải nghiệm Web hoàn hảo từ lúc Build cho tới lúc chạy trên trình duyệt.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Kiến Trúc Zero Bloat: Nghệ Thuật Rung Cây (Tree Shaking) Chặt Bỏ Mã Chết',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Tree Shaking', 'Bundlers', 'Performance', 'ESModules']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> Zero Bloat Architecture（無駄ゼロ・アーキテクチャ）は、ユーザーのブラウザに送るJavaScriptの量を「極限まで削ぎ落とす」ことに執念を燃やすフロントエンドの設計哲学です。「Tree Shaking（木揺らし）」と呼ばれる強力なビルド時最適化に依存しています。</li>
<li><strong>根本的な問題：</strong> 現代のWeb開発では、便利なライブラリを何でもかんでも <code>node_modules</code> に詰め込みます。昔のビルドツール（Webpackの初期など）は非常に愚かで、巨大なライブラリの中からたった1つの小さな関数を使っただけでも、5MBもあるライブラリ「丸ごと」をユーザーにダウンロードさせてしまい、Webサイトの表示速度を壊滅的に遅くしていました。</li>
<li><strong>解決策：</strong> ES6モジュール（<code>import/export</code>）の「静的な（Static）」性質を利用して、現代のバンドラーはコードのAST（抽象構文木）を解析し、「本当に使われている関数」だけを特定します。そして、使われていない余分なコードを物理的に木から「振り落とす（Tree Shaking）」のです。</li>
<li><strong>現代の真実：</strong> Rollup、ESBuild、Webpack 5といったツールは、このTree Shakingを標準で搭載しています。さらに、SvelteやSolidJSなどの最新フレームワークは、フレームワーク自体のコードすら完全に振り落とし、「純粋なJavaScript」だけをブラウザに届けるという究極のZero Bloatを実現しています。</li>
</ul>

<h2>歴史的背景：依存関係のブラックホール 🕳️</h2>
<p>フロントエンドエンジニアがなぜこれほどまでに「Bloat（肥大化・無駄な贅肉）」を憎むのかを理解するには、2012年から2015年頃の「Web開発の暗黒時代」を振り返る必要があります。Node.jsとNPM（Node Package Manager）の発明は、開発者の書き方を劇的に変えました。車輪の再発明をやめ、誰もが <code>npm install lodash</code>（便利関数パック）や <code>npm install moment</code>（日付計算）を打ち込むようになったのです。</p>

<p>しかし、この便利さには猛毒が含まれていました。当時のモジュールシステムであった<strong>CommonJS</strong>（<code>require()</code>構文）は、非常に<strong>動的（Dynamic）</strong>な性質を持っていました。<code>const lib = require( isMobile ? "lodash" : "jquery" );</code> のような書き方が許されていたのです。モジュールの名前が「プログラムが実行される瞬間（ランタイム）」まで確定しないため、ビルドツール（コードをまとめるソフト）は、「開発者がライブラリのどの部分を使うのか」を事前に予測することができませんでした。</p>

<p>その結果、何が起きたか？ <strong>「依存関係のブラックホール」</strong>です。あなたは単に <code>cloneDeep()</code> という1つの小さな関数を使いたいだけなのに、Webpackはパニックになり、「念のため、Lodashライブラリ全体（5MB）をメインのファイルにパックしておこう！」と判断してしまうのです。結果として、あなたのWebアプリは10MBを超える巨大なモンスターに膨れ上がりました。スマートフォンの3G回線で見ているユーザーは、たった1つのボタンを押すために、10MBのJavaScriptをダウンロードし、パースし、実行するのを何十秒も待たされるハメになったのです。Webは肥大化し、重く、ユーザーにとって敵対的な場所になってしまいました。</p>

<h2>学術的ブレイクスルー：ES6モジュールによる「静的解析」 ⚡</h2>
<p>この「JavaScriptのゴミ屋敷問題」に終止符を打つため、ECMAScript委員会（言語の仕様を決める賢い人たち）は、新しいES6モジュール（<code>import / export</code>）を設計する際、歴史的かつ断固たる決断を下しました。それは、モジュールシステムを厳格に<strong>「静的（Static）」</strong>なものにするということです。</p>

<p>ES6では、標準の <code>import</code> を <code>if</code> 文の中に入れることは許されません。すべての <code>import</code> は、必ずファイルの「一番上」に書かなければならないのです。この窮屈にも思えるルールこそが、コンパイラ工学における天才的な一手でした。</p>

<p>インポートが静的である（実行前から固定されている）ということは、ビルドツールがわざわざコードを「実行」しなくても、テキストを「読む（パースする）」だけで、どのファイルがどのファイルに依存しているかの正確な地図（依存関係グラフ）を作成できることを意味します。この仕様変更によって、1990年代にLispなどの言語で使われていた究極の最適化技術が、ついにJavaScriptの世界に降臨しました。それが<strong>「Tree Shaking（木揺らし：デッドコード削除）」</strong>です。（この概念をJS界に広めたのは、RollupやSvelteの開発者であるRich Harrisです）。</p>

<h2>アーキテクチャの徹底解剖：「木揺らし」はどのように動くのか 🌳</h2>
<p>Tree Shakingは魔法ではありません。それはAST（抽象構文木）を使った、無慈悲なグラフ探索アルゴリズムです。現代のバンドラー（Rollupなど）がゴミを削ぎ落とす正確な手順を見てみましょう。</p>

<h3>1. 依存関係グラフ（ネットワーク）の構築</h3>
<p>バンドラーは、あなたの起点となるファイル（<code>index.js</code>）からスタートします。コードをパースしてASTを作り、<code>import</code> 文を探します。見つけたらそのファイルに飛び込み、さらに <code>import</code> を探します。これを繰り返し、あなたの書いたコードと <code>node_modules</code> を網羅する、巨大なクモの巣（依存関係グラフ）を描き出します。</p>

<h3>2. マーク・アンド・スウィープ（印をつけて、掃き捨てる）アルゴリズム</h3>
<p>グラフが完成すると、バンドラーは「ゴミ収集車（Garbage Collector）」に変身します。</p>
<ul>
<li><strong>マーキング（印つけフェーズ）：</strong> バンドラーは <code>index.js</code> から出発し、あなたが実際に呼び出している関数を辿ります。もしあなたが <code>cloneDeep()</code> を呼んでいれば、LodashのASTの中にある <code>cloneDeep</code> のノード（枝）にまでたどり着き、そこに「使用中（Used）」というスタンプを力強く押します。重要なのは、Lodashにある残りの300個の関数には「見向きもしない」ということです。</li>
<li><strong>スウィーピング（切り落としフェーズ）：</strong> すべてのルートを辿り終えた後、バンドラーは巨大なツリー全体を見渡します。そして、「使用中」のスタンプが押されていないノード（変数、関数、クラス）をすべて<strong>「デッドコード（死んだコード）」</strong>と宣告し、チェーンソーでツリーから容赦なく切り落とし、焼却炉に放り込みます。</li>
</ul>

<h3>3. コードの再生成</h3>
<p>最後に、バンドラーは生き残ったASTノードだけを、再びJavaScriptの文字列として印刷します。出来上がった <code>bundle.js</code> には、あなたのコードと、Lodashの <code>cloneDeep()</code> だけが含まれています。5MBあった脂肪は、わずか5KBの筋肉だけに絞り込まれました。これがZero Bloatです。</p>

<h2>現代の絶望的な罠：「副作用（Side Effects）」という地雷 💣</h2>
<p>理論上は完璧なTree Shakingですが、本番環境の現実では地雷原を歩くようなものです。Tree Shakingの最大の敵、それが<strong>「副作用（Side Effect）」</strong>です。</p>

<p>JavaScriptは自由すぎる言語です。もし <code>node_modules</code> の中のあるファイルに、次のようなコードが書かれていたらどうなるでしょうか？</p>
<pre><code>export function unusedMath() { return 1 + 1; }
window.globalConfig = { init: true }; // 警告：副作用！グローバル変数を書き換えている！</code></pre>

<p>たとえあなたが <code>unusedMath</code> を一度もインポートしていなくても、バンドラーはこのファイルを安全に削除することができません。なぜなら、このファイルを削除してしまうと、裏でこっそり行われていた <code>window.globalConfig</code> の初期化が実行されなくなり、アプリ全体が謎のクラッシュを起こす可能性があるからです。バンドラーの至上命題は「アプリを壊さないこと」なので、疑わしいファイルは「安全策」としてすべて残してしまいます。結果として、ゴミがバンドルに混入します。</p>

<p>この地雷を撤去するため、コミュニティは <code>package.json</code> に <code>"sideEffects": false</code> という強力なフラグを導入しました。これはライブラリ作者からバンドラーへの「法的誓約書」です。<em>「私のコードは純粋（Pure）です。グローバル変数をこっそり書き換えたりしません。だから、もし関数がインポートされていなければ、このファイル全体を安全に消し去って構いません」</em>という宣言なのです。このフラグを正しく設定できるかどうかが、真のZero Bloatを達成するための鍵となります。</p>

<h2>専門家による批評と、コンパイラ化するフロントエンド 🏛️</h2>
<p>Zero Bloatの追求は、JavaScriptのエコシステムを再定義しました。それは、「Webの重さをユーザーのブラウザに負担させる」時代から、「パフォーマンスの負担を開発者のビルド環境が引き受ける」時代へのパラダイムシフトでした。私たちは今、数百万人のユーザーのロード時間を数ミリ秒削るために、開発環境で数秒かけてコードをコンパイルしているのです。</p>

<p>また、この進化はSvelteやSolidJSのような「コンパイラ・アズ・ア・フレームワーク」への道を切り開きました。Reactのように「仮想DOMを計算する100KBのエンジン」をブラウザに送るのではなく、Svelteはビルド時にASTを解析し、コンポーネントを「最小限のピュアなJavaScript（DOM操作命令）」にコンパイルします。不要なフレームワークのコード自体が完全にTree Shakingされ、ブラウザには文字通りZero Bloatなコードだけが届くのです。</p>

<p>ただし、この代償としてビルド環境は恐ろしく複雑になりました。Webpackの設定や、CommonJSとESModulesの混在によるトラブルは、フロントエンドエンジニアの終わらない苦痛です。この問題の救世主となるのが、RustやGoといった高速なシステム言語で書かれたESBuildやSWCなどの次世代ツールです。これらはマルチスレッドでASTを高速に処理し、何分もかかっていた木揺らしの作業を数ミリ秒で完了させます。Zero Bloatの未来は、より軽く、より速いWebの約束を果たすために、今も進化し続けているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'Zero Bloatアーキテクチャ：不要なコードを切り落とす「Tree Shaking」の残酷な芸術',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Tree Shaking', 'Bundlers', 'Performance', 'ESModules']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 4 (Zero Bloat)!\n";
