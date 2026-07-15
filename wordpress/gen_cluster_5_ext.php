<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'hover_foreign_key_1783998586817.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Hover Foreign Key',
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
<li><strong>What is it?</strong> "Hover-to-Peek" (or Hover Cards) is an advanced UI pattern where hovering over a relational data link (like a User ID or an Issue Ticket) triggers a non-blocking popup containing the fetched foreign key data.</li>
<li><strong>The Core Problem:</strong> Enterprise applications often display relational IDs (e.g., "Assigned to: User #491"). To see who User #491 is, the user must click the link, open a new browser tab, lose their context, and wait for a full page load. This causes the "Infinite Tabs" nightmare.</li>
<li><strong>The Solution:</strong> Implementing a global Hover Card infrastructure. When a mouse enters the bounding box of a foreign key token, a debounced background network request fetches the entity. The data is rendered in a Portal above the current DOM tree, providing instant context without losing state.</li>
<li><strong>Modern Reality:</strong> Apps like Linear, GitHub, and Jira use this extensively. It requires complex orchestration of React Portals, Intersection Observers (to ensure the popup doesn\'t clip off-screen), and aggressive LRU caching to prevent duplicate network requests.</li>
</ul>

<h2>Historical Context & The Catalyst: The "Infinite Tabs" Nightmare</h2>
<p>In the classic era of Server-Rendered web applications (think PHP or Ruby on Rails circa 2010), the web was strictly a collection of documents linked together. If you were looking at a list of GitHub Issues, and you saw an issue assigned to a username you didn\'t recognize, you had exactly one option: Click the username, navigate to their profile page, and lose your place in the issue list.</p>

<p>Power users quickly adapted to this limitation by inventing a workaround: Middle-click. Developers would middle-click every single link they saw to open it in a new background tab. This led to the infamous "Infinite Tabs" syndrome, where a developer\'s browser would have 50 open tabs, consuming 16GB of RAM, just to maintain context across relational data.</p>

<p>As Single Page Applications (SPAs) matured, UI engineers realized they had the power to break this document-centric paradigm. If the browser already had JavaScript running, why force a full page navigation? Why not fetch the data quietly in the background and show it right where the user\'s mouse is?</p>

<h2>The Academic Breakthrough: Progressive Disclosure</h2>
<p>The concept of "Hover-to-Peek" is rooted in a fundamental Human-Computer Interaction (HCI) principle called <strong>Progressive Disclosure</strong>. Progressive Disclosure dictates that a user interface should only show the absolute minimum information necessary at any given time, but offer a seamless, low-friction pathway to drill down for more detail.</p>

<p>A simple tooltip (<code>title="John Doe"</code>) is the most basic form of Progressive Disclosure. But a Hover Card takes this to the extreme. It is a fully functional mini-application floating inside the main application. It requires breaking the strict hierarchical flow of the DOM.</p>

<h2>Deep Architectural Walkthrough: Engineering the Hover Card</h2>
<p>Building a robust Hover-to-Peek system is surprisingly difficult. If you naively attach a <code>onMouseEnter</code> event to fetch data, your application will self-destruct under a DDoS attack of its own making as the user accidentally sweeps their mouse across the screen. Let\'s look at the robust architecture required.</p>

<h3>1. Debouncing the Intent (The "Sweep" Problem)</h3>
<p>When a user moves their mouse from the left side of the screen to the right, the cursor might accidentally pass over 15 different links. We do not want to fire 15 API requests. We must measure <strong>Intent</strong>.</p>
<p>We use a <strong>Debounce</strong> timer. When <code>onMouseEnter</code> fires, we start a timer (e.g., 300ms). If the mouse leaves the element (<code>onMouseLeave</code>) before the 300ms is up, we cancel the timer. Only if the user intentionally rests their cursor on the link for 300ms do we proceed to the fetch phase.</p>

<h3>2. The Global LRU Cache</h3>
<p>If the user hovers over "Issue #123", we fetch the data. If they move their mouse away, and then hover over "Issue #123" again a second later, hitting the network again is an architectural failure. The Hover engine must be backed by a global Cache (often an LRU Cache to manage memory). Before making the <code>fetch()</code>, the engine checks the cache. If the data is hot, the render is instantaneous.</p>

<h3>3. Escaping the DOM Hierarchy (React Portals)</h3>
<p>Where does the HTML for the Hover Card physically live? If you render the popup inside the actual link element (<code>&lt;a&gt;&lt;div class="popup"&gt;&lt;/div&gt;&lt;/a&gt;</code>), you will face the nightmare of <code>overflow: hidden</code> or <code>z-index</code> stacking contexts. A parent container will inevitably clip your popup and cut it in half.</p>

<p>The solution is <strong>Portals</strong>. When the Hover Card needs to render, React (or the framework) mounts the DOM node at the very root of the document (<code>&lt;body&gt;</code>), completely escaping all CSS scoping constraints. It then uses the <code>getBoundingClientRect()</code> of the trigger link to calculate absolute coordinates to physically place the popup on the screen.</p>

<h3>4. Edge Collision Detection</h3>
<p>If the user hovers over a link at the very bottom right edge of their monitor, rendering the popup down and to the right will push it off the screen, creating ugly scrollbars. The positioning engine must calculate the viewport boundaries and intelligently flip the popup. If there is no room below, render above. If there is no room to the right, render to the left.</p>

<h2>Modern Production Reality: Linear and the SWR Pattern</h2>
<p>The gold standard for Hover-to-Peek today is the issue tracker <strong>Linear</strong>. They have perfected the micro-interaction. In Linear, almost every token (a user avatar, an issue ID, a project label) is interactive.</p>

<p>Modern applications pair Hover Cards with the <strong>SWR (Stale-While-Revalidate)</strong> data fetching pattern. When you hover over a user, the application instantly renders whatever stale data it has in the cache (perhaps showing an old avatar). In the background, it silently fetches the fresh data. If the avatar has changed, the image seamlessly swaps out. This creates a perception of Zero-Latency.</p>

<h2>Expert Critique & Legacy</h2>
<p>The Hover Card is a testament to how far we have pushed the web platform. It transforms the browser from a document viewer into a highly dense, multi-layered information workspace. It respects the user\'s context and working memory.</p>

<p>However, it introduces a severe accessibility flaw: <strong>Mobile Devices do not have a Hover state.</strong> You cannot "hover" a finger on a touchscreen. Tooling architects must design dual-interaction paradigms. On Desktop, hovering peeks the data and clicking navigates. On Mobile, tapping must somehow negotiate between peeking and navigating (often requiring a long-press or a bottom-sheet modal).</p>

<p>Despite the mobile compromises, mastering the Hover Card architecture—handling the debounce timers, the portals, the collision mathematics, and the caching layer—is a rite of passage for Senior Frontend Engineers building enterprise-grade software.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Hover-to-Peek Architecture: Solving the Infinite Tabs Nightmare',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Hover Card', 'UI Design', 'React Portals', 'Frontend Architecture']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Khái niệm cốt lõi:</strong> Hover-to-Peek (hay Hover Cards) là một kiến trúc UI bậc cao. Khi người dùng rê chuột (hover) qua một đường link chứa dữ liệu quan hệ (ví dụ: Tên User, Mã Đơn Hàng), một bảng Popup nhỏ sẽ nổi lên để hiển thị chi tiết dữ liệu đó mà không cần chuyển trang.</li>
<li><strong>Vấn đề giải quyết:</strong> Trong các phần mềm quản lý, khi nhìn thấy "Người phụ trách: User #491", nếu muốn biết User #491 là ai, bạn phải bấm vào link, mở tab mới, chờ tải trang, và đánh mất mạch công việc hiện tại. Điều này đẻ ra hội chứng "100 Tab Trình Duyệt" tốn RAM kinh hoàng.</li>
<li><strong>Giải pháp (Workflow):</strong> Hệ thống bắt sự kiện <code>onMouseEnter</code>, chạy bộ đếm thời gian (Debounce) để lọc bớt những cú quét chuột vô tình. Nếu chuột dừng đủ lâu, hệ thống gọi API ngầm lấy dữ liệu, lưu vào Cache, và dùng kỹ thuật Portal để vẽ bảng Popup đè lên trên mọi layer CSS hiện tại.</li>
<li><strong>Thực tiễn Production:</strong> Các siêu ứng dụng như GitHub, Linear hay Jira dùng kỹ thuật này ở mọi nơi. Nó đòi hỏi một hệ thống cực kỳ phức tạp để xử lý chống giật (Debouncing), tính toán tránh mép màn hình (Collision Detection), và quản lý bộ nhớ đệm (LRU Cache).</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Thảm Họa "Hàng Trăm Tab Trình Duyệt"</h2>
<p>Hãy nhớ lại cái thời web cổ đại được code bằng PHP hoặc Ruby on Rails (khoảng năm 2010). Hồi đó, Web thực chất chỉ là một mớ tài liệu (Document) được móc nối với nhau bằng các đường link xanh lè. Giả sử bạn đang làm việc trên một danh sách 50 lỗi (Bugs) của Jira. Bạn thấy dòng chữ: "Bug #999 phụ thuộc vào Bug #42". Bạn muốn biết Bug #42 là cái quái gì? Bạn chỉ có đúng một cách: Bấm vào cái link đó, trình duyệt tải lại toàn bộ trang web mới, và bạn văng ra khỏi danh sách 50 lỗi kia.</p>

<p>Giới lập trình viên cực kỳ ghét việc mất Context (ngữ cảnh) như vậy. Thế là họ sáng chế ra một thói quen chết người: Bấm chuột giữa (Middle-click) để mở link sang Tab mới. Kết quả là, chỉ sau 30 phút làm việc, trình duyệt Chrome của bạn sẽ mọc ra 50 cái Tab mới, ngốn sạch 16GB RAM của máy tính. UX (Trải nghiệm người dùng) thời đó thực sự là một thảm họa tốn tài nguyên.</p>

<p>Cho đến khi Single Page Application (SPA) lên ngôi, các kỹ sư UI mới bừng tỉnh: <em>"Tại sao chúng ta phải tải nguyên cả một trang web mới, trong khi chúng ta có thể dùng JavaScript để lén lút kéo đoạn dữ liệu nhỏ đó về, và hiển thị ngay tại chỗ con trỏ chuột đang đứng?"</em></p>

<h2>Đột Phá Học Thuật: Nguyên Lý Tiết Lộ Dần Dần (Progressive Disclosure)</h2>
<p>Khái niệm Hover-to-Peek không phải do dân Coder đẻ ra, nó bắt nguồn từ một nguyên lý nền tảng của ngành Tương tác Người-Máy (HCI) gọi là: <strong>Progressive Disclosure (Tiết lộ Lũy tiến)</strong>. Nguyên lý này ra lệnh rằng: Một cái UI tốt chỉ được phép show ra những thông tin thật sự cần thiết nhất vào lúc này, giấu nhẹm những thứ rườm rà đi, nhưng phải cung cấp một "Lối tắt" cực kỳ mượt mà để User có thể đào sâu thêm nếu họ muốn.</p>

<p>Cái tooltip mặc định của trình duyệt (thẻ <code>title="Hello"</code>) là dạng rẻ rách nhất của Progressive Disclosure. Hover Card mới là đỉnh cao. Nó không chỉ là một cái nhãn dán, nó là một <strong>Mini-Application (Ứng dụng thu nhỏ)</strong> nổi lềnh bềnh ngay bên trong Ứng dụng mẹ của bạn. Việc xây dựng nó đòi hỏi phải phá vỡ mọi quy tắc phân cấp cấu trúc của HTML DOM.</p>

<h2>Giải Phẫu Kiến Trúc: Nghệ Thuật Thiết Kế Hover Card</h2>
<p>Nghe thì có vẻ dễ: Bắt sự kiện <code>onMouseEnter</code> rồi gọi lệnh <code>fetch()</code> là xong chứ gì? KHÔNG! Nếu bạn code ngây thơ như vậy, App của bạn sẽ tự sát. Giả sử User vung tay lướt chuột ngang qua màn hình, con trỏ chuột vô tình xẹt qua 20 cái link. App của bạn sẽ bắn liền 20 cái request API lên Server trong vòng 0.1 giây (tự DDoS chính mình). Dưới đây là kiến trúc chuẩn kỹ sư để giải quyết bài toán này.</p>

<h3>1. Debouncing (Bộ Lọc Ý Định Của User)</h3>
<p>Chúng ta phải phân biệt được đâu là "Quét chuột vô tình" và đâu là "Chủ ý muốn xem". Ta cài một quả bom hẹn giờ (<strong>Debounce</strong>).</p>
<p>Khi chuột chạm vào link (<code>onMouseEnter</code>), ta đếm ngược 300ms. Nếu chưa hết 300ms mà chuột đã chạy ra ngoài (<code>onMouseLeave</code>), ta tháo ngòi nổ. Chỉ khi User cố tình để chuột yên vị trên cái link đó suốt 300ms, ta mới kích hoạt bước tiếp theo. Đây là nghệ thuật đo lường "Ý định" (Intent).</p>

<h3>2. LRU Cache Toàn Cục (Không Gọi API 2 Lần)</h3>
<p>User rê chuột vào "Mã lỗi #123", ta gọi API kéo dữ liệu về. User kéo chuột ra. 2 giây sau, User lại rê chuột vào lại "Mã lỗi #123". Nếu bạn lại gọi API phát nữa, bạn xứng đáng bị đuổi việc. Động cơ Hover phải được chống lưng bởi một hệ thống Cache toàn cục (LRU Cache). Trước khi gọi API, nó bới trong Cache ra tìm. Nếu có dữ liệu rồi, Popup sẽ nổ ra ngay lập tức với độ trễ 0ms.</p>

<h3>3. Kỹ Thuật Độn Thổ (React Portals)</h3>
<p>Bây giờ mới là đoạn đau đầu: Mã HTML của cái bảng Popup đó sẽ nằm ở đâu? Nếu bạn nhét cái thẻ <code>&lt;div class="popup"&gt;</code> vào bên trong cái link chữ, bạn sẽ dính lời nguyền CSS. Thẻ cha chứa cái link đó có thể đang dùng thuộc tính <code>overflow: hidden</code>, và nó sẽ chém đứt làm đôi cái Popup của bạn. Hoặc dính lỗi <code>z-index</code> bị chìm xuống dưới các phần tử khác.</p>

<p>Tuyệt chiêu duy nhất là dùng <strong>Portals (Cổng không gian)</strong>. Mặc dù bạn viết code Popup nằm cạnh cái Link, nhưng khi render, React sẽ "bốc" cái thẻ DOM của Popup đó, ném thẳng ra ngoài cùng gốc rễ của trang web (thẻ <code>&lt;body&gt;</code>), thoát khỏi mọi gông cùm của CSS. Sau đó, nó dùng hàm <code>getBoundingClientRect()</code> để đo tọa độ X, Y của cái Link, và đẩy cái Popup bay lơ lửng đè đúng vào vị trí đó.</p>

<h3>4. Va Chạm Biên (Edge Collision Detection)</h3>
<p>Sẽ ra sao nếu cái Link nằm tít ở góc dưới cùng bên phải màn hình? Nếu bạn vẽ Popup xổ xuống dưới, nó sẽ văng ra khỏi màn hình máy tính, tạo ra thanh scrollbar xấu xí. Cỗ máy tính toán tọa độ bắt buộc phải đo khoảng cách tới mép màn hình. Nếu không đủ chỗ ở dưới? Vẽ lật lên trên. Nếu không đủ chỗ bên phải? Đẩy sang trái. Mọi thứ phải hoàn toàn tự động.</p>

<h2>Thực Tiễn Production: Đẳng Cấp Của Linear Và Mẫu SWR</h2>
<p>Nếu muốn xem đỉnh cao của Hover-to-Peek, hãy dùng thử App quản lý tiến độ <strong>Linear</strong>. Bọn họ ám ảnh với tốc độ đến mức gần như MỌI CÁI TÊN trên màn hình đều có thể hover vào để xem chi tiết.</p>

<p>Các siêu ứng dụng này kết hợp Hover với pattern <strong>SWR (Stale-While-Revalidate)</strong>. Khi bạn hover, nó lôi ngay lập tức dữ liệu cũ mốc meo trong Cache ra hiển thị để bạn có cái nhìn vào (Zero-latency). Cùng lúc đó, nó ngầm chạy xuống API để xin dữ liệu mới. Nếu dữ liệu có thay đổi, nó mượt mà tráo đổi nội dung bên trong cái Popup mà không hề chớp giật.</p>

<h2>Bình Luận Chuyên Gia & Đánh Đổi (Expert Critique)</h2>
<p>Hover Card là minh chứng rõ ràng nhất cho việc Web đã tiến hóa thành một "Không gian làm việc" (Workspace) đa tầng, chứ không còn là những trang tài liệu phẳng nữa. Nó cực kỳ tôn trọng trí nhớ ngắn hạn và luồng suy nghĩ của người dùng.</p>

<p>Nhưng nó mang theo một nhược điểm chí mạng về Accessibility: <strong>Điện thoại cảm ứng không hề có tính năng Rê chuột (Hover).</strong> Bạn không thể lơ lửng ngón tay trên màn hình điện thoại được. Do đó, các kiến trúc sư phải đau đầu thiết kế UI thành 2 luồng riêng biệt: Trên PC thì Hover để xem nhanh, Click để chuyển trang. Trên Mobile thì phải chế ra trò "Chạm giữ lâu" (Long-press) để mở Popup, hoặc vuốt bảng Bottom-sheet lên.</p>

<p>Mặc cho nhược điểm trên Mobile, việc code thành thạo kiến trúc Hover Card—từ việc đặt bẫy Debounce, mở cổng Portal, tính toán tọa độ ma trận, đến nhét LRU Cache—chính là bài thi tốt nghiệp cực khó để phân loại giữa một Thợ gõ code (Coder) và một Kỹ sư Frontend (Engineer) thực thụ.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Kiến Trúc Hover-to-Peek: Khai Tử Hội Chứng Mở Hàng Trăm Tab Trình Duyệt',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Hover Card', 'UI Design', 'React Portals', 'Frontend Architecture']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 「Hover-to-Peek（ホバー・カード）」は、高度なUIパターンの1つです。ユーザーの名前やチケット番号などのリンクの上にマウスを乗せる（ホバーする）だけで、画面遷移せずに小さなポップアップ画面が開き、そのデータの詳細を「チラ見（Peek）」できる機能です。</li>
<li><strong>根本的な問題：</strong> 昔のWebアプリでは、「担当者：User #491」と書かれている場合、その人が誰かを知るためにはリンクをクリックして別ページに移動しなければなりませんでした。これでは元の作業を見失ってしまうため、開発者たちは「マウスの中ボタン」で無限に新しいタブを開き続け、ブラウザのメモリが食いつぶされる「無限タブ地獄」に陥っていました。</li>
<li><strong>解決策：</strong> マウスがリンクの上に乗ったことを検知し、数ミリ秒の「ため（Debounce）」を作って誤操作を防ぎます。その後、裏側でこっそりAPIを呼び出してデータを取得し、「Portal（ポータル）」という技術を使って現在の画面の最前面にポップアップを描画します。これで、今の作業から目を離さずにコンテキスト（文脈）を維持できます。</li>
<li><strong>現代の真実：</strong> GitHub、Jira、Linearなどの超有名アプリはこれを完璧に実装しています。しかしその裏側では、React Portalsを使ったCSSの突破、画面の端っこでポップアップが切れないようにする衝突判定、無駄な通信を防ぐLRUキャッシュなど、恐ろしいほど複雑なアーキテクチャが動いています。</li>
</ul>

<h2>歴史的背景：「無限タブ地獄」という名の悪夢 😱</h2>
<p>2010年頃の古いWeb（PHPやRuby on Railsなどで作られたサーバーレンダリングの時代）を思い出してください。当時のWebは、ただ「青いリンクで繋がれた書類の山」でした。たとえば、GitHubで50個のバグリストを眺めているとします。「バグ#999 は、バグ#42 に依存している」という文字が見えました。バグ#42の内容を知りたい場合、あなたはどうしますか？ リンクをクリックして別のページに飛びますか？ そうすると、せっかく見ていた50個のバグリストの画面は消え去ってしまいます。</p>

<p>元の画面を見失うことを極端に嫌うパワーユーザーたちは、ある危険な裏技を編み出しました。それが「マウスの中ボタンクリック（別タブで開く）」です。目につくリンクを片っ端から裏のタブで開いていくため、たった30分仕事をしただけで、Chromeには50個のタブが並び、パソコンのメモリ（RAM）を16GBも食いつぶしてパソコンから火が出そうになります。当時のWebのUX（ユーザー体験）は、エコとは程遠いシステムだったのです。</p>

<p>しかし、Single Page Application（SPA：画面遷移のないWebアプリ）の技術が進化すると、UIエンジニアたちは気づきました。<em>「わざわざ重たい画面全体をリロードしなくても、JavaScriptを使って裏側でデータだけをサクッと取ってきて、マウスのカーソルの横に吹き出しで表示すればいいじゃないか！」</em>と。</p>

<h2>学術的ブレイクスルー：「段階的開示（Progressive Disclosure）」の原則 🧠</h2>
<p>この「Hover-to-Peek（ホバーしてチラ見する）」というアイデアは、思いつきで作られたわけではありません。これは「ヒューマン・コンピュータ・インタラクション（HCI）」という学問における基礎的な原則、<strong>「段階的開示（Progressive Disclosure）」</strong>に基づいています。</p>

<p>段階的開示のルールはこうです。「ユーザーには、今その瞬間に絶対必要な情報だけを見せなさい。画面を文字だらけにしてはいけません。ただし、ユーザーが『もっと詳しく知りたい』と思ったときには、極めて少ない労力で詳細データにアクセスできる抜け道を用意しておきなさい」。</p>

<p>ブラウザ標準のツールチップ（<code>title="John Doe"</code>）もこの原則の一部ですが、Hover Cardはそれを究極まで高めたものです。それは単なる文字の吹き出しではなく、<strong>「アプリの中に浮かぶ、もう一つの小さなアプリ」</strong>なのです。これを作るには、HTMLとDOMの常識を破壊しなければなりません。</p>

<h2>アーキテクチャの徹底解剖：ホバーカードの裏で動く「魔術」 🪄</h2>
<p>「マウスが乗ったら（<code>onMouseEnter</code>）、API（<code>fetch()</code>）を呼ぶだけでしょ？」と思ったあなた、甘いです。そんな単純なコードを書いたら、ユーザーが画面の端から端までマウスを「スッ」と動かすだけで、通り道にあった20個のリンクが一斉にAPIを呼び出し、あなたのサーバーは自爆（セルフDDoS攻撃）してしまいます。本物のエンジニアが作る完璧なアーキテクチャを見てみましょう。</p>

<h3>1. デバウンス（Debounce）：ユーザーの「本当の意図」を測る</h3>
<p>「たまたま通り過ぎただけ」と「じっくり見ようとしている」を見分けるために、時限爆弾（Debounceタイマー）を仕掛けます。<br>
マウスがリンクに乗った瞬間、タイマーを「300ミリ秒」にセットします。もし300ミリ秒経つ前にマウスが外に出たら（<code>onMouseLeave</code>）、タイマーをキャンセルします。ユーザーが意図的に300ミリ秒間、ピタッとマウスを止めたときだけ、API呼び出しのフェーズに進みます。これが「意図（Intent）」を測る技術です。</p>

<h3>2. グローバルLRUキャッシュ：同じ質問を二度しない</h3>
<p>ユーザーが「バグ#123」をホバーし、データを取ってきました。その後マウスを外し、2秒後にもう一度「バグ#123」をホバーしました。このとき、またネットワーク通信を発生させたら三流です。<br>
Hoverエンジンは、巨大な「キャッシュ（記憶領域）」と連動していなければなりません。APIを呼ぶ前に、まずキャッシュの箱を覗き込みます。データがあれば、通信をスキップして「遅延ゼロ（0ms）」で即座にポップアップを開きます。</p>

<h3>3. DOMの牢獄からの脱出（React Portals） 🚪</h3>
<p>ここが最大の難所です。ポップアップのHTMLタグ（<code>&lt;div class="popup"&gt;</code>）を、リンク文字のすぐ横に配置してしまうと、CSSの呪いにかけられます。親要素に <code>overflow: hidden</code> （はみ出た部分を隠す）が設定されていた場合、せっかくのポップアップが真っ二つに切られてしまいます。</p>

<p>解決策は<strong>「Portals（ポータル：どこでもドア）」</strong>です。Reactはポップアップをレンダリングする瞬間、その要素をDOMツリーの奥底から引き抜き、Webページの「一番外側（<code>&lt;body&gt;</code>）」にワープさせます。これにより、すべてのCSSの制約から解放されます。その後、JavaScriptの <code>getBoundingClientRect()</code> という関数を使って元リンクのX座標とY座標を計算し、絶対座標で「空中から被せる」ように配置するのです。</p>

<h3>4. 画面端の衝突回避（Collision Detection） 💥</h3>
<p>もしユーザーが、画面の「右下スレスレ」にあるリンクをホバーしたらどうなるでしょう？ そのまま右下にポップアップを描画すると、画面からはみ出してしまい、見苦しいスクロールバーが出てしまいます。<br>
位置計算エンジンは、画面（ビューポート）の境界線を計算し、「下側にスペースがないなら、上に描画する」「右側にスペースがないなら、左にずらす」という数学的計算を、一瞬で行わなければなりません。</p>

<h2>現代のフロントエンドの真実：LinearとSWRパターン 🚀</h2>
<p>現在、Hover-to-Peekの技術で世界最高峰に君臨しているのが、プロジェクト管理アプリの<strong>「Linear」</strong>です。彼らはこの極小のインタラクションに異常なまでの執念を燃やしています。Linearの画面にあるほとんどの文字（ユーザーのアイコン、バグ番号、ラベル）は、すべてホバー可能です。</p>

<p>Linearのような超一流のアプリは、ホバーカードと<strong>「SWR（Stale-While-Revalidate）」</strong>というデータ取得パターンを組み合わせています。ユーザーがホバーした瞬間、とりあえずキャッシュに残っている「古いデータ（Stale）」を爆速で表示します。そして裏側でこっそり最新データをAPIに取りに行き、もしデータが更新されていれば、ユーザーが気づかないほど滑らかに中身の文字を書き換えます。これにより「常に遅延ゼロ」という最強の錯覚を生み出しています。</p>

<h2>専門家による批評と、避けられない弱点（スマホ問題） 📱</h2>
<p>Hover Cardは、Webブラウザを単なる「書類を見るソフト」から、「多層的で立体的なワークスペース（作業空間）」へと進化させた偉大な発明です。ユーザーの短期記憶と作業効率を極限まで尊重しています。</p>

<p>しかし、このアーキテクチャには致命的なアクセシビリティの欠陥があります。<strong>「スマートフォン（タッチスクリーン）には、『ホバー（浮かせる）』という概念が存在しない」</strong>ということです。</p>

<p>スマホの画面を指で「ホバー」することはできません。そのため、UIアーキテクトは「パソコン版ではホバーでチラ見、クリックで別ページ」「スマホ版では、長押し（Long-press）したら下からメニューを出す」というように、端末ごとに全く別の操作体験を設計しなければならないという重い十字架を背負っています。</p>

<p>スマホ対応という頭痛の種はあるものの、このHover Cardの仕組み（デバウンス、Portalでの脱出、座標計算、キャッシュ管理）を一人で完璧に実装できるかどうかは、単なる「コードが書ける人」から「複雑なエンタープライズ製品を作れるシニア・フロントエンドエンジニア」へと昇格するための、最も難格な卒業試験だと言えるでしょう。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'Hover-to-Peekアーキテクチャ：ブラウザの「無限タブ地獄」を終わらせるUIの魔術',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Hover Card', 'UI Design', 'React Portals', 'Frontend Architecture']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 5 (Hover-to-Peek)!\n";
