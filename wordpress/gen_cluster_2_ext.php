<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'virtual_grid_dom.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Virtual Grid DOM',
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

$cat_en = setup_term('Desktop UI Architecture', 'category', 'en');
$cat_vi = setup_term('Kiến Trúc Giao Diện', 'category', 'vi');
$cat_ja = setup_term('デスクトップUI設計', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> Virtual Grid DOM (or Windowing/Virtualization) is a frontend UI rendering architecture designed to display massive datasets (like a million-row spreadsheet or infinite scrolling feeds) without crashing the browser.</li>
<li><strong>The Core Problem:</strong> The Document Object Model (DOM) is a tree structure in the browser. Rendering 100,000 HTML nodes requires massive memory allocation, styling calculations, and layout reflows. The browser will freeze and crash.</li>
<li><strong>The Solution:</strong> Instead of rendering all rows, a Virtual Grid only renders the exact rows currently visible within the browser\'s "Viewport" (e.g., 20 rows). As the user scrolls, the grid constantly recycles those 20 HTML elements, swapping out the underlying data.</li>
<li><strong>Modern Reality:</strong> Libraries like <code>react-window</code> or <code>ag-Grid</code> use this exclusively. It transforms rendering performance from $O(N)$ (where N is total data) to $O(V)$ (where V is the visible items), guaranteeing 60 FPS scrolling regardless of dataset size.</li>
</ul>

<h2>Historical Context & The Catalyst: The 10,000 Row Freeze</h2>
<p>In the early days of Web 2.0, the internet transitioned from static documents to dynamic applications. Developers started building web-based email clients, data tables, and infinite social feeds. The naive approach to rendering a large list of data was simply to use a <code>for</code> loop to generate 10,000 <code>&lt;div&gt;</code> or <code>&lt;tr&gt;</code> elements and inject them straight into the browser.</p>

<p>The result was a catastrophic UX failure. The browser\'s main thread is single-threaded. When you inject 10,000 DOM nodes, the browser must stop everything to perform three devastatingly expensive operations:</p>
<ol>
<li><strong>Style Calculation:</strong> The browser must compute the CSS rules for all 10,000 elements.</li>
<li><strong>Layout / Reflow:</strong> The browser must calculate the exact X and Y coordinates, width, and height of every single box on the screen. Changing one box at the top might push down the remaining 9,999 boxes.</li>
<li><strong>Paint:</strong> The browser rasterizes the pixels to the screen.</li>
</ol>

<p>This process could take upwards of 5 to 10 seconds, during which the page would completely freeze (the infamous "Page Unresponsive" popup). The fundamental realization was: <em>The DOM is not a database. It cannot handle bulk data.</em></p>

<h2>The Academic Breakthrough: The Illusion of Infinite Scrolling</h2>
<p>To solve the DOM bottleneck, UI engineers looked back to an older technique used in 1980s video games and desktop operating systems: <strong>Viewport Rendering (or Windowing)</strong>. When Super Mario runs to the right, the game console doesn\'t render the entire 10-mile level in memory. It only renders the exact 256x224 pixel "window" that the television screen can show.</p>

<p>Translating this to the browser birthed the <strong>Virtual Grid DOM</strong>. If the user\'s screen is 800 pixels high, and each row is 40 pixels high, the screen can physically only fit 20 rows at a time. Therefore, no matter if your dataset has 100 rows or 1,000,000 rows, the browser should only ever possess 20 DOM nodes. Period.</p>

<p>The magic trick is creating the <em>illusion</em> of 1,000,000 rows. You do this by creating a giant, invisible "Ghost Container" <code>&lt;div&gt;</code> that forces the browser\'s scrollbar to shrink to the correct tiny size. As the user pulls the scrollbar down, JavaScript intercepts the scroll event, calculates which data slice should be visible, and updates the data inside the 20 existing DOM nodes.</p>

<h2>Deep Architectural Walkthrough: Mathematics of the Grid</h2>
<p>Building a Virtual Grid requires precise mathematics. Let\'s walk through the exact algorithm of a perfectly virtualized list.</p>

<h3>1. The Ghost Container Setup</h3>
<p>Suppose we have 1,000,000 items. Each row is fixed at 30px height.</p>
<code>Total Height = 1,000,000 * 30px = 30,000,000px</code>
<p>We render an empty container <code>&lt;div style="height: 30000000px; position: relative;"&gt;</code>. This tricks the browser into creating an enormous scrollbar.</p>

<h3>2. The Scroll Event & Offset Calculation</h3>
<p>The user scrolls down by 6,000 pixels. The <code>onScroll</code> event fires.</p>
<code>ScrollTop = 6000px</code>
<p>Now, we calculate the <strong>Start Index</strong> (which item is at the top of the viewport?)</p>
<code>StartIndex = Math.floor(ScrollTop / RowHeight) = Math.floor(6000 / 30) = 200</code>
<p>We calculate the <strong>End Index</strong> (assuming a viewport height of 600px).</p>
<code>VisibleItemsCount = Math.ceil(ViewportHeight / RowHeight) = Math.ceil(600 / 30) = 20</code>
<code>EndIndex = StartIndex + VisibleItemsCount = 200 + 20 = 220</code>

<h3>3. The Absolute Positioning Trick</h3>
<p>We extract items 200 to 220 from our JavaScript array. We feed this data into our 20 recycled DOM nodes. But how do we position them correctly within the 30-million-pixel ghost container?</p>
<p>We use <strong>Absolute Positioning</strong>. We set <code>position: absolute; top: Xpx;</code> for each rendered row.</p>
<code>Row 200 Top = 200 * 30px = 6000px</code>
<code>Row 201 Top = 201 * 30px = 6030px</code>

<p>Because the rows are absolutely positioned, changing their top coordinate does not trigger a global Reflow of the surrounding elements. The browser simply repaints them at their new coordinates. We achieve a silky smooth 60 Frames Per Second (FPS).</p>

<h2>Modern Production Reality: Dynamic Heights & Over-scanning</h2>
<p>The math above works perfectly when every row is exactly 30px high. But what if you are building Twitter, where a tweet with a video is 400px high, and a text tweet is 50px high? This is the nightmare of <strong>Dynamic Height Virtualization</strong>.</p>

<p>When heights are dynamic, you cannot simply multiply <code>Index * 30px</code> to find the Y-coordinate. The grid must measure the height of each item <em>after</em> it renders, and store that height in a cached Hash Map. Calculating the total height of the ghost container requires summing up the cached heights and estimating the heights of unmeasured items. It is an algorithmic tightrope walk.</p>

<p>Furthermore, rendering exactly 20 items will result in a blank white flash if the user scrolls quickly. Production libraries (like <code>react-window</code>) use a technique called <strong>Over-scanning</strong>. They render the 20 visible items, plus 10 items above the viewport, and 10 items below the viewport. This 40-item buffer ensures that fast scrolls feel seamless.</p>

<h2>Expert Critique & Legacy</h2>
<p>Virtualization represents a profound shift in frontend architecture: <strong>Separating the Data Model from the View Model</strong>. It proves that the DOM should strictly be a dumb rendering target for the current viewport, not a storage mechanism for application state.</p>

<p>However, Virtualization introduces severe accessibility (a11y) and SEO challenges. Because 99% of the data does not exist in the DOM, native browser features like <code>Ctrl+F</code> (Find on page) simply stop working. Screen readers for visually impaired users cannot announce the true size of the list. Solving these edge cases has consumed thousands of engineering hours.</p>

<p>Despite the flaws, the Virtual Grid is the foundational rendering engine of the modern web. Every time you scroll seamlessly through millions of rows in Notion, Airtable, or a Google Cloud dashboard, you are witnessing the elegant illusion of DOM Virtualization in action.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Virtual Grid DOM: The Mathematics of Rendering a Million Rows',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Virtualization', 'React Window', 'DOM Performance', 'Frontend Architecture']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Khái niệm:</strong> Virtual Grid DOM (Ảo hóa giao diện) là kỹ thuật tối ưu hóa Frontend dùng để render các tập dữ liệu khổng lồ (ví dụ: bảng tính 1 triệu dòng) mà không làm trình duyệt bị đơ (Crash).</li>
<li><strong>Vấn đề:</strong> DOM (Document Object Model) của trình duyệt hoạt động cực kỳ nặng nề. Việc bắt trình duyệt vẽ 100.000 thẻ <code>&lt;div&gt;</code> sẽ làm nghẽn cổ chai luồng chính (Main thread), khiến việc tính toán CSS và Layout tiêu tốn hàng chục giây.</li>
<li><strong>Giải pháp:</strong> Thay vì vẽ toàn bộ 100.000 dòng, Virtual Grid chỉ vẽ ĐÚNG số lượng dòng đang hiển thị trên màn hình của User (ví dụ: 20 dòng). Khi user cuộn chuột, hệ thống sẽ liên tục tái chế (recycle) 20 thẻ HTML này và thay thế nội dung (data) bên trong.</li>
<li><strong>Thực tiễn:</strong> Kỹ thuật này đổi độ phức tạp render từ $O(N)$ (N là tổng dữ liệu) thành $O(V)$ (V là số lượng phần tử nhìn thấy), giúp duy trì tốc độ khung hình 60 FPS bất chấp kích thước dữ liệu. Các thư viện lớn như <code>react-window</code> hay <code>ag-Grid</code> đều dùng cốt lõi này.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cơn Ác Mộng Trình Duyệt Bị Đơ (Page Unresponsive)</h2>
<p>Vào đầu kỷ nguyên Web 2.0, các ứng dụng web bắt đầu phình to từ những trang báo tĩnh (Static Document) thành những phần mềm phức tạp (Web Apps). Các kỹ sư Frontend bắt đầu nhét hàng ngàn dòng dữ liệu vào các bảng (Table) hoặc các feed mạng xã hội vô tận. Lúc bấy giờ, cách làm ngây thơ nhất là: Có 10.000 dòng dữ liệu trong mảng Array? Dùng vòng lặp <code>.map()</code> đẻ ra 10.000 thẻ <code>&lt;tr&gt;</code> và tống thẳng hết vào DOM.</p>

<p>Kết quả là một thảm họa về UX. Trình duyệt web (Chrome, Firefox) chạy JavaScript và vẽ giao diện trên cùng một luồng duy nhất (Single-threaded). Khi bạn ném 10.000 node vào DOM, trình duyệt phải đứng hình để làm 3 tác vụ tàn khốc sau:</p>
<ol>
<li><strong>Style Calculation (Tính toán CSS):</strong> Phân tích xem CSS nào được áp dụng cho từng phần tử trong số 10.000 node đó.</li>
<li><strong>Layout / Reflow (Tính toán Tọa độ):</strong> Trình duyệt phải đo đạc xem từng cái hộp (box) nằm ở vị trí X, Y nào, rộng dài bao nhiêu. Ác mộng ở chỗ: Nếu kích thước dòng số 1 thay đổi, nó sẽ đẩy vị trí của 9.999 dòng bên dưới lệch đi, bắt trình duyệt phải tính toán lại toàn bộ.</li>
<li><strong>Paint (Vẽ Pixel):</strong> Đổ màu lên màn hình.</li>
</ol>

<p>Quá trình này ngốn sạch 100% CPU. Màn hình trắng xóa. Tab trình duyệt xuất hiện thông báo kinh điển: <em>"Page Unresponsive - Kill or Wait?"</em>. Bài học xương máu được rút ra: <strong>DOM không phải là Database. Bạn không thể dùng DOM để lưu trữ hàng vạn cục dữ liệu.</strong></p>

<h2>Đột Phá Kỹ Thuật: Cú Lừa Của Cuộn Vô Tận (Infinite Scrolling Illusion)</h2>
<p>Để giải bài toán này, các kỹ sư UI đã vay mượn lại một kỹ thuật kinh điển từ ngành lập trình Game thập niên 80: <strong>Viewport Rendering (Ảo hóa khung nhìn)</strong>. Khi Mario chạy sang phải, máy điện tử Nintendo (NES) đâu có lưu toàn bộ bản đồ dài 10 cây số vào bộ nhớ đồ họa. Nó chỉ vẽ ĐÚNG một cái khung cửa sổ (Window) kích thước 256x224 pixel mà màn hình tivi có thể hiển thị được.</p>

<p>Mang khái niệm này lên Web, ta có <strong>Virtual Grid DOM</strong>. Nếu màn hình laptop của User cao 800px, và mỗi dòng dữ liệu cao 40px, thì về mặt vật lý, màn hình chỉ có thể chứa tối đa 20 dòng cùng một lúc. Do đó, mặc kệ Database của bạn trả về 1.000 hay 1 triệu dòng, trình duyệt của User CHỈ ĐƯỢC PHÉP chứa đúng 20 thẻ DOM mà thôi.</p>

<p>Vậy làm sao để lừa User rằng họ đang cuộn qua 1 triệu dòng? Bạn phải tạo ra một "Bóng ma" (Ghost Container). Đó là một cái thẻ <code>&lt;div&gt;</code> rỗng tuếch, nhưng được ép chiều cao cực khủng bằng CSS để lừa trình duyệt vẽ ra một thanh cuộn (Scrollbar) siêu nhỏ. Khi User hì hục kéo thanh cuộn xuống, JavaScript sẽ đánh chặn sự kiện <code>onScroll</code>, dùng Toán học để tính xem User đang ở tọa độ nào, bốc đúng 20 dòng dữ liệu tương ứng, và nhét đè vào 20 cái thẻ DOM đang có sẵn.</p>

<h2>Giải Phẫu Kiến Trúc: Toán Học Đằng Sau Lưới Ảo Hóa</h2>
<p>Để xây dựng một Virtual Grid mượt mà, bạn không cần phép màu, bạn chỉ cần Toán học cấp 2. Hãy cùng mô phỏng thuật toán cốt lõi.</p>

<h3>1. Dựng Thùng Chứa Bóng Ma (Ghost Container Setup)</h3>
<p>Giả sử ta có 1.000.000 dòng. Mỗi dòng cố định cao 30px.</p>
<code>Tổng Chiều Cao = 1.000.000 * 30px = 30.000.000px</code>
<p>Ta render một thẻ <code>&lt;div style="height: 30000000px; position: relative;"&gt;</code>. Trình duyệt bị lừa, và thanh scrollbar bên phải xuất hiện dài thăm thẳm.</p>

<h3>2. Tính Toán Tọa Độ Cuộn (Scroll Offset Calculation)</h3>
<p>User kéo thanh cuộn xuống tới vị trí 6.000 pixel. Sự kiện <code>onScroll</code> kích hoạt.</p>
<code>ScrollTop = 6000px</code>
<p>Ta tính xem <strong>Index Đầu Tiên (StartIndex)</strong> đang hiển thị trên màn hình là dòng số mấy:</p>
<code>StartIndex = Math.floor(ScrollTop / RowHeight) = Math.floor(6000 / 30) = 200</code>
<p>Ta tính <strong>Index Cuối Cùng (EndIndex)</strong> dựa trên chiều cao cửa sổ trình duyệt (ví dụ 600px):</p>
<code>Số_Dòng_Hiển_Thị = Math.ceil(600 / 30) = 20</code>
<code>EndIndex = StartIndex + Số_Dòng_Hiển_Thị = 200 + 20 = 220</code>

<h3>3. Ma Thuật Định Vị Tuyệt Đối (Absolute Positioning Trick)</h3>
<p>Giờ ta lấy đúng các dòng từ 200 đến 220 trong Array dữ liệu, truyền vào 20 thẻ DOM tái chế. Nhưng làm sao để 20 thẻ này nằm đúng vị trí 6.000px bên trong cái bóng ma 30 triệu pixel kia?</p>
<p>Ta dùng <strong>Absolute Positioning (Định vị tuyệt đối)</strong>. Ta set CSS <code>position: absolute; top: Xpx;</code> cho từng dòng đang render.</p>
<code>Dòng số 200: top = 200 * 30px = 6000px</code>
<code>Dòng số 201: top = 201 * 30px = 6030px</code>

<p>Sự tinh tế ở đây là: Vì các thẻ được định vị <code>absolute</code>, việc thay đổi tọa độ <code>top</code> của chúng sẽ KHÔNG kích hoạt hiệu ứng DOM Reflow dây chuyền (các phần tử khác không bị đẩy đi). Trình duyệt chỉ đơn giản lấy màu vẽ lại tọa độ mới. Ta đạt được tốc độ mượt mà 60 Khung hình/giây (FPS).</p>

<h2>Thực Tiễn Production: Chiều Cao Động (Dynamic Heights) Và Over-scanning</h2>
<p>Thuật toán trên chỉ là bài tập sách giáo khoa với các dòng có chiều cao cố định (Fixed height). Ngoài thực tế, mọi thứ tàn khốc hơn nhiều. Khi bạn làm bảng feed cho Twitter, một bài có video cao 500px, một bài text chỉ cao 50px. Bài toán <strong>Dynamic Height Virtualization</strong> xuất hiện.</p>

<p>Khi chiều cao là Động, bạn không thể nhẩm tính <code>Index * 30px</code> được nữa. Grid của bạn bắt buộc phải render nháp từng dòng ra màn hình tàng hình, lấy JavaScript đo đạc chiều cao thực tế, lưu vào một bộ nhớ đệm (Hash Map Cache), rồi mới cộng dồn lại để tính tọa độ <code>top</code>. Đây là một màn đi dây thuật toán cực kỳ đau não.</p>

<p>Thêm vào đó, nếu bạn chỉ render khít rịt đúng 20 dòng, khi User lướt chuột quá nhanh (Flick), họ sẽ nhìn thấy màn hình trắng chớp nháy (White Flash) do JavaScript tính toán không kịp. Các thư viện Production (như <code>react-window</code>) giải quyết bằng kỹ thuật <strong>Over-scanning (Quét dư)</strong>. Họ render 20 dòng đang hiển thị, cộng thêm 10 dòng dự phòng ở trên, và 10 dòng dự phòng ở dưới. Bộ đệm 40 dòng này giúp che lấp hoàn toàn độ trễ của JavaScript, tạo cảm giác mượt mà tuyệt đối.</p>

<h2>Bình Luận Chuyên Gia & Đánh Đổi (Expert Critique & Trade-offs)</h2>
<p>Virtualization đánh dấu một bước trưởng thành của kỹ sư Frontend: <strong>Tách biệt hoàn toàn Data Model khỏi View Model</strong>. Nó khẳng định rằng DOM chỉ là một cái bảng vẽ ngu ngốc để hiển thị pixels trong khung nhìn hiện tại, chứ DOM tuyệt đối không phải là nơi để lưu trữ State (trạng thái) của phần mềm.</p>

<p>Tuy nhiên, sự thanh lịch này đổi lại bằng một cái giá đắt đỏ: Mất khả năng SEO và phá hỏng Accessibility (a11y). Vì 99% nội dung không tồn tại trong thẻ HTML, tính năng ấn <code>Ctrl+F</code> (Tìm kiếm trên trang) của trình duyệt bị liệt hoàn toàn. Các công cụ đọc màn hình (Screen Reader) của người khiếm thị bị mù hướng. Trình duyệt tìm kiếm Google Bot cũng không thể index được dữ liệu bên dưới.</p>

<p>Bất chấp những giới hạn này, Virtual Grid vẫn là kiến trúc bắt buộc phải có của kỷ nguyên Web App. Mỗi khi bạn cuộn không ngừng nghỉ qua hàng ngàn row trong Airtable, Notion hay Google Cloud Console, bạn đang tận hưởng thành quả của cú lừa thị giác vĩ đại nhất giới Frontend.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Ảo Hóa DOM (Virtual Grid): Toán Học Đằng Sau Việc Vẽ 1 Triệu Dữ Liệu Lên Màn Hình',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Virtualization', 'React Window', 'DOM Performance', 'Frontend Architecture']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> Virtual Grid DOM（UIの仮想化・ウィンドウイング）は、100万行の表データや無限に続くSNSのタイムラインなど、巨大なデータをブラウザをフリーズさせることなく表示するためのフロントエンド・アーキテクチャです。</li>
<li><strong>根本的な問題：</strong> ブラウザのDOM（HTMLのツリー構造）は非常に重たい仕組みです。10万個のHTMLタグ（<code>&lt;div&gt;</code>など）を一度に画面に描こうとすると、メモリが枯渇し、スタイルの計算やレイアウト処理でブラウザが完全に固まって（クラッシュして）しまいます。</li>
<li><strong>解決策：</strong> 10万行すべてを描くのをやめ、ユーザーの「今見えている画面の枠（ビューポート）」に入る分（例えば20行）だけを描画します。ユーザーがスクロールすると、その20個のHTMLタグを捨てずに「中身のデータだけ」を高速に入れ替えて使い回し（リサイクル）ます。</li>
<li><strong>現代の真実：</strong> <code>react-window</code> や <code>ag-Grid</code> といった有名なライブラリはすべてこの仕組みを使っています。描画の計算量を $O(N)$（全データ数）から $O(V)$（見えている数）へと劇的に減らすことで、データが1億件あっても常に60FPSのヌルヌルなスクロールを保証します。</li>
</ul>

<h2>歴史的背景：1万行表示でブラウザが死ぬ時代 💥</h2>
<p>Web 2.0の黎明期、インターネットは単なる「文字を読むだけのページ」から、「複雑なアプリケーション（Web Apps）」へと進化を始めました。フロントエンドエンジニアたちは、巨大なデータテーブルや、無限にスクロールできるフィード画面を作り始めました。当時のもっとも純粋（そして愚か）な作り方は、「データが1万件あるなら、JavaScriptの <code>for</code> ループで <code>&lt;div&gt;</code> タグを1万個作って、一気に画面（DOM）に叩き込む！」というものでした。</p>

<p>結果はUXの悲劇でした。ブラウザのメインスレッド（JavaScriptの実行と画面描画を行う心臓部）はシングルスレッド（1本道）です。そこに1万個のDOMノードが放り込まれると、ブラウザは息を止め、次の3つの恐ろしく重い処理をこなさなければなりません。</p>
<ol>
<li><strong>スタイルの計算：</strong> 1万個の要素すべてに対して、「文字は何色か？枠線はあるか？」というCSSのルールを計算します。</li>
<li><strong>レイアウト（Reflow）：</strong> 「この四角のX座標、Y座標はどこか？ 幅と高さは何ピクセルか？」をすべて計算します。一番上の要素の高さが1px変わるだけで、下にある9999個の要素がすべて押し出され、再計算が発生するという地獄が待っています。</li>
<li><strong>ペイント：</strong> 計算結果をもとに、画面のピクセルに色を塗ります。</li>
</ol>

<p>この計算には数秒から数十秒かかり、その間、画面は完全に真っ白になり、クリックもスクロールも一切受け付けなくなります（恐怖の「ページが応答しません」エラー）。エンジニアたちはついに悟りました。<strong>「DOMはデータベースではない。大量のデータを格納する場所としてDOMを使ってはいけないのだ」</strong>と。</p>

<h2>学術的ブレイクスルー：ファミコンから学んだ「窓（ウィンドウ）」の魔法 🪟</h2>
<p>DOMのボトルネックを解決するため、UIエンジニアたちは1980年代のレトロゲームやデスクトップOSで使われていた古い技術に目を向けました。それが<strong>「ビューポート・レンダリング（Viewport Rendering）」</strong>です。スーパーマリオが右へ走っていくとき、ファミコンは決して「10キロメートル先のゴールまでの巨大なマップ全体」をメモリ上に描画したりしません。ファミコンが描画するのは、テレビの画面に収まる「256x224ピクセルの小さな窓」だけです。</p>

<p>この概念をWebブラウザに翻訳したものが<strong>「Virtual Grid DOM（仮想グリッド）」</strong>です。もしユーザーのノートパソコンの画面の高さが800pxで、1行の高さが40pxなら、物理的に画面に入るのは「最大20行」だけです。データベースから1万行のデータが返ってこようが、100万行のデータが返ってこようが、ブラウザの中に存在してよいHTML（DOM）タグは「20個だけ」に制限するのです。</p>

<p>では、どうやってユーザーに「100万行あるように錯覚」させるのでしょうか？ 答えは、目に見えない<strong>「巨大な幽霊コンテナ（Ghost Container）」</strong>を作ることです。透明な <code>&lt;div&gt;</code> を作り、CSSで強制的に高さを数千万ピクセルに設定します。するとブラウザは騙されて、右側に「めちゃくちゃ小さなスクロールバー」を表示します。ユーザーがそのスクロールバーを一生懸命下に引っ張ると、JavaScriptがスクロール量を読み取り、裏で「今見せるべき20行のデータ」を計算し、画面にある20個のタグの中身だけをサッとすり替えるのです。</p>

<h2>アーキテクチャの徹底解剖：仮想化を支える「算数」 🧮</h2>
<p>滑らかなVirtual Gridを作るために、複雑な魔術は必要ありません。必要なのは中学レベルの算数です。アルゴリズムを追ってみましょう。</p>

<h3>1. 幽霊コンテナのセットアップ</h3>
<p>データが1,000,000件（100万件）あり、各行の高さが固定で30pxだとします。</p>
<code>全体の高さ = 1,000,000行 × 30px = 30,000,000px（3千万ピクセル）</code>
<p>画面に <code>&lt;div style="height: 30000000px; position: relative;"&gt;</code> を描画します。これで無限のスクロール空間が完成します。</p>

<h3>2. スクロール量の計算とインデックスの特定</h3>
<p>ユーザーが下にスクロールし、上から 6,000ピクセル の位置まで来たとします。</p>
<code>ScrollTop = 6000px</code>
<p>ここで、画面の一番上に表示されるべき<strong>「開始インデックス（何行目か）」</strong>を計算します。</p>
<code>開始インデックス = 切り捨て(6000px ÷ 30px) = 200行目</code>
<p>画面の高さが600pxだとすると、表示できる行数は：</p>
<code>表示行数 = 切り上げ(600px ÷ 30px) = 20行</code>
<code>終了インデックス = 200 + 20 = 220行目</code>

<h3>3. 絶対配置（Absolute Positioning）のトリック</h3>
<p>JavaScriptの配列から、200行目〜220行目のデータだけを抜き出し、20個のDOMタグに入れます。しかし、この20個のタグを、3千万ピクセルもある幽霊コンテナの「正しい位置（6000pxの場所）」にどうやって表示させるのでしょうか？</p>
<p>ここで<strong>「絶対配置（position: absolute）」</strong>を使います。描画する各行に <code>top: Xpx;</code> を設定するのです。</p>
<code>200行目の位置： top = 200 × 30px = 6000px</code>
<code>201行目の位置： top = 201 × 30px = 6030px</code>

<p>DOMの <code>absolute</code> 配置を使うことで、行の座標（top）を更新しても、周囲の他の要素を押し出すこと（Reflow：レイアウトの再計算）が起きません。ブラウザは単純に「指定された座標に色を塗り直す（Paint）」だけで済むため、60FPS（1秒間に60回の画面更新）という極めて滑らかなスクロールが実現できるのです。</p>

<h2>現代の絶望的な難題：「動的な高さ」とオーバースキャン 📏</h2>
<p>上記の算数は「すべての行が30pxである（Fixed height）」という平和な前提で成り立っています。しかし、Twitterのタイムラインを作るときはどうでしょう？ 動画付きのツイートは500px、文字だけのツイートは50pxと、行の高さがバラバラです。これが<strong>「動的な高さの仮想化（Dynamic Height Virtualization）」</strong>という恐ろしい難題です。</p>

<p>高さが動的な場合、単純に <code>インデックス × 30px</code> でY座標を計算することができません。グリッドは、データを一度見えない画面の裏側にレンダリングして「本当の高さ」を測り、それをキャッシュ（記憶）しておいてから、高さを足し算して座標を求めなければなりません。これは、パフォーマンスとのギリギリの綱渡りです。</p>

<p>さらに、画面にぴったりの「20行」だけをレンダリングしていると、ユーザーがマウスのホイールで「シュッ」と高速スクロールした瞬間に、JavaScriptの計算が追いつかず「真っ白な画面」が一瞬見えてしまいます。これを防ぐため、本番環境のライブラリ（<code>react-window</code>など）は<strong>「オーバースキャン（Overscanning）」</strong>という技術を使います。見えている20行に加えて、画面の上下に「予備の10行ずつ（計40行）」をあらかじめレンダリングしておくのです。このバッファ（ゆとり）が、高速スクロール時の白飛びを完全に防ぎます。</p>

<h2>専門家による批評と、仮想化の代償 ⚖️</h2>
<p>UI仮想化は、フロントエンド開発における重要な哲学の転換を意味します。それは<strong>「データモデル（真実）とビューモデル（見た目）の完全な分離」</strong>です。「DOMは単なるピクセルを描くキャンバスであり、大量のアプリケーション状態を保持するためのデータベースではない」ということを決定づけたのです。</p>

<p>しかし、この美しいアーキテクチャは、<strong>アクセシビリティ（a11y）とSEOに壊滅的なダメージ</strong>を与えます。データ全体の99%がHTML（DOM）の中に存在しないため、ブラウザ標準の <code>Ctrl+F</code>（ページ内検索）は全く機能しなくなります。視覚障害者が使うスクリーンリーダー（音声読み上げソフト）は、リストの本当の長さをユーザーに伝えることができません。Googleの検索ロボットも隠れたデータをインデックスできません。これらのエッジケースを解決するために、エンジニアたちは今も膨大な時間を費やしています。</p>

<p>それでもなお、Virtual Gridは現代のWebアプリケーションに欠かせない心臓部です。Notion、Airtable、あるいはGoogle Cloudのログ画面で、何十万行ものデータを引っかかりなくスムーズにスクロールできるとき、あなたはフロントエンド界の「最も美しく、計算された錯覚（Illusion）」を楽しんでいるのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'Virtual Grid DOM：100万行のデータをフリーズせずに描画する「錯覚の数学」',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Virtualization', 'React Window', 'DOM Performance', 'Frontend Architecture']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 2 (Virtual Grid DOM)!\n";
