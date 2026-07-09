<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/index.php';
$content = file_get_contents($file);

$replacements = [
    '<html lang="vi">' => '<html <?php language_attributes(); ?>>',
    '<title>HoaSen Table — Cinematic Scroll</title>' => '<title><?php wp_title( \'|\', true, \'right\' ); bloginfo( \'name\' ); ?> — Cinematic Scroll</title>' . "\n" . '<?php wp_head(); ?>',
    '</body>' => '<?php wp_footer(); ?>' . "\n" . '</body>',
    
    'id="btnBlog">BLOG</a>' => 'id="btnBlog"><?php esc_html_e(\'BLOG\', \'hoasen-theme\'); ?></a>',
    'id="btnContact">CONTACT</a>' => 'id="btnContact"><?php esc_html_e(\'CONTACT\', \'hoasen-theme\'); ?></a>',
    
    '<div class="kicker">01 / CONNECT</div>' => '<div class="kicker"><?php esc_html_e(\'01 / CONNECT\', \'hoasen-theme\'); ?></div>',
    '<h1>Khởi đầu thông minh từ Connection Board.</h1>' => '<h1><?php esc_html_e(\'Start smart with the Connection Board.\', \'hoasen-theme\'); ?></h1>',
    '<p>Quản lý cấu hình kết nối được phân nhóm và gán mã màu trực quan. Dễ dàng phân biệt môi trường Production (Đỏ) và Development (Xanh) để tránh nhầm lẫn.</p>' => '<p><?php esc_html_e(\'Manage grouped and color-coded connection profiles. Easily distinguish between Production (Red) and Development (Green) environments to avoid mistakes.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="kicker">02 / AUTOCOMPLETE</div>' => '<div class="kicker"><?php esc_html_e(\'02 / AUTOCOMPLETE\', \'hoasen-theme\'); ?></div>',
    '<h1>Autocomplete chuẩn xác theo Ngữ pháp.</h1>' => '<h1><?php esc_html_e(\'Grammar-Precise Autocomplete.\', \'hoasen-theme\'); ?></h1>',
    '<p>Hiểu sâu cú pháp và các phương ngữ (Dialect) của MySQL, Postgres, SQLite... Ngăn chặn hoàn toàn các gợi ý rác không hợp lệ.</p>' => '<p><?php esc_html_e(\'Understands deep syntax and dialects of MySQL, Postgres, SQLite... Completely prevents invalid suggestions.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="kicker">03 / INTELLIGENCE</div>' => '<div class="kicker"><?php esc_html_e(\'03 / INTELLIGENCE\', \'hoasen-theme\'); ?></div>',
    '<h1>Smart Snippet thông minh theo Khoá Ngoại.</h1>' => '<h1><?php esc_html_e(\'Smart Snippets via Foreign Keys.\', \'hoasen-theme\'); ?></h1>',
    '<p>Tự động phân tích các quan hệ khoá ngoại (FK) để gợi ý cấu trúc JOIN chính xác. Điền nhanh các mệnh đề ON phức tạp chỉ bằng một phím bấm.</p>' => '<p><?php esc_html_e(\'Automatically analyzes foreign key (FK) relations to suggest exact JOIN structures. Quickly fill complex ON clauses with a single keystroke.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="kicker">04 / PRODUCTIVITY</div>' => '<div class="kicker"><?php esc_html_e(\'04 / PRODUCTIVITY\', \'hoasen-theme\'); ?></div>',
    '<h1>Native Workspace tối giản hiệu năng cao.</h1>' => '<h1><?php esc_html_e(\'Minimalist High-Performance Native Workspace.\', \'hoasen-theme\'); ?></h1>',
    '<p>Môi trường làm việc thuần khiết được thiết kế nhằm tối đa hoá dòng chảy công việc (flow). Trực quan hoá lược đồ và dữ liệu lập tức mà không có chi tiết thừa.</p>' => '<p><?php esc_html_e(\'A pure workspace designed to maximize workflow. Visualize schemas and data instantly without redundant details.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="kicker">05 / DISCOVERY</div>' => '<div class="kicker"><?php esc_html_e(\'05 / DISCOVERY\', \'hoasen-theme\'); ?></div>',
    '<h1>Truy vấn quan hệ bảng tức thời (Hover Relation).</h1>' => '<h1><?php esc_html_e(\'Instant Table Relations (Hover Relation).\', \'hoasen-theme\'); ?></h1>',
    '<p>Di chuột lên các giá trị khoá ngoại để xem nhanh nội dung bản ghi liên kết mà không cần viết lệnh SQL phụ hay chuyển đổi tab làm việc.</p>' => '<p><?php esc_html_e(\'Hover over foreign key values to quickly view linked record contents without writing sub-queries or switching tabs.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="kicker">06 / CAPACITY</div>' => '<div class="kicker"><?php esc_html_e(\'06 / CAPACITY\', \'hoasen-theme\'); ?></div>',
    '<h1>Cuộn mượt mà hàng triệu dòng với Virtual Grid.</h1>' => '<h1><?php esc_html_e(\'Smoothly Scroll Millions of Rows with Virtual Grid.\', \'hoasen-theme\'); ?></h1>',
    '<p>Chỉ kết xuất những phần dữ liệu hiển thị trong khung nhìn (Viewport). Đảm bảo giao diện cuộn cực mượt mà ngay cả với cơ sở dữ liệu khổng lồ.</p>' => '<p><?php esc_html_e(\'Renders only the data visible in the viewport. Ensures ultra-smooth scrolling even with massive databases.\', \'hoasen-theme\'); ?></p>',
    
    '<div class="scroll-note">Cuộn tiếp ↓</div>' => '<div class="scroll-note"><?php esc_html_e(\'Scroll down ↓\', \'hoasen-theme\'); ?></div>',
    
    '<div class="modal-header">HoaSen Table Journal</div>' => '<div class="modal-header"><?php esc_html_e(\'HoaSen Table Journal\', \'hoasen-theme\'); ?></div>',
    '<h3>Bài viết</h3>' => '<h3><?php esc_html_e(\'Articles\', \'hoasen-theme\'); ?></h3>',
    
    'Tối ưu hóa Autocomplete SQL bằng Phân tích Ngữ pháp' => '<?php esc_html_e(\'Optimizing SQL Autocomplete by Parsing Grammar\', \'hoasen-theme\'); ?>',
    'Xuất bản ngày 09/07/2026 bởi Đội ngũ kỹ thuật' => '<?php esc_html_e(\'Published on 09/07/2026 by Engineering Team\', \'hoasen-theme\'); ?>',
    'Trong các trình soạn thảo SQL truyền thống, autocomplete thường hoạt động bằng cách quét chuỗi ký tự đơn giản. Điều này dẫn đến rất nhiều gợi ý rác không hợp lệ tại vị trí con trỏ.' => '<?php esc_html_e(\'In traditional SQL editors, autocomplete often works by simply scanning strings. This leads to many invalid garbage suggestions at the cursor.\', \'hoasen-theme\'); ?>',
    'HoaSen Table giải quyết vấn đề này bằng cách tích hợp trực tiếp bộ phân tích cú pháp (parser) của từng hệ quản trị cơ sở dữ liệu. Khi bạn gõ, hệ thống sẽ dựng một AST (Abstract Syntax Tree) tạm thời, từ đó xác định chính xác các từ khoá hoặc đối tượng lược đồ hợp lệ tiếp theo.' => '<?php esc_html_e(\'HoaSen Table solves this by directly integrating the parser of each DBMS. As you type, the system builds a temporary AST (Abstract Syntax Tree) to exactly determine the next valid keywords or schema objects.\', \'hoasen-theme\'); ?>',
    'Ví dụ, nếu bạn vừa gõ <code>SELECT * F</code>, trình soạn thảo sẽ biết chắc chắn ngữ pháp chỉ cho phép mệnh đề <code>FROM</code> tại đây và lọc bỏ toàn bộ các cột hay bảng bắt đầu bằng chữ "F".' => '<?php _e(\'For example, if you just typed <code>SELECT * F</code>, the editor knows the grammar only allows a <code>FROM</code> clause here and filters out all columns or tables starting with "F".\', \'hoasen-theme\'); ?>',
    
    'Cơ chế Virtual Grid: Xử lý 1 triệu dòng mượt mà' => '<?php esc_html_e(\'Virtual Grid Mechanism: Smoothly Handling 1 Million Rows\', \'hoasen-theme\'); ?>',
    'Xuất bản ngày 02/07/2026 bởi Đội ngũ hiệu năng' => '<?php esc_html_e(\'Published on 02/07/2026 by Performance Team\', \'hoasen-theme\'); ?>',
    'Hiển thị hàng triệu dòng dữ liệu trên giao diện đồ họa (GUI) luôn là một thử thách lớn về bộ nhớ và CPU. Trình duyệt hoặc ứng dụng thông thường sẽ bị đơ cứng nếu cố gắng render hàng trăm ngàn thẻ HTML cùng lúc.' => '<?php esc_html_e(\'Displaying millions of rows in a GUI is a major challenge for memory and CPU. A standard browser or app will freeze if it tries to render hundreds of thousands of HTML elements at once.\', \'hoasen-theme\'); ?>',
    'HoaSen Table sử dụng kỹ thuật <b>Viewport Virtualization</b>. Chúng tôi chỉ vẽ các dòng dữ liệu đang hiển thị trong khung nhìn của người dùng (khoảng 20-30 dòng). Khi người dùng cuộn, các phần tử này sẽ được tái sử dụng để nạp dữ liệu mới, giữ cho số lượng nút DOM luôn ở mức tối thiểu và bộ nhớ tiêu thụ chỉ khoảng vài kilobyte.' => '<?php _e(\'HoaSen Table uses <b>Viewport Virtualization</b>. We only render the data rows currently visible in the user\\\'s viewport (around 20-30 rows). As you scroll, these elements are recycled to load new data, keeping DOM nodes to a minimum and RAM footprint to a few kilobytes.\', \'hoasen-theme\'); ?>',
    
    'Thiết kế Giao diện Vintage: Nghệ thuật của sự Tiết chế' => '<?php esc_html_e(\'Vintage UI Design: The Art of Restraint\', \'hoasen-theme\'); ?>',
    'Xuất bản ngày 25/06/2026 bởi Đội ngũ thiết kế' => '<?php esc_html_e(\'Published on 25/06/2026 by Design Team\', \'hoasen-theme\'); ?>',
    'Thời đại của các giao diện SaaS phẳng lì và đơn điệu đã làm nhạt nhoà cá tính thương hiệu. Với HoaSen Table, chúng tôi quay lại giá trị cốt lõi của nghệ thuật in ấn cổ điển: kiểu chữ tinh tế và màu sắc có chiều sâu.' => '<?php esc_html_e(\'The era of flat, monotonous SaaS interfaces has diluted brand personalities. With HoaSen Table, we return to the core values of classical print typography: elegant typefaces and deep colors.\', \'hoasen-theme\'); ?>',
    'Sự kết hợp giữa font chữ serif trang trọng <b>Cormorant Garamond</b>, độ tương phản sắc nét của sắc xám tro ash-gray và màu đỏ huyết dụ oxblood giúp tạo ra một giao diện làm việc đầy cảm hứng nhưng vẫn giữ được sự tập trung tối đa cho công việc kỹ thuật phức tạp.' => '<?php _e(\'The combination of the formal <b>Cormorant Garamond</b> serif font, the sharp contrast of ash-gray, and the deep oxblood red creates an inspiring workspace while maintaining maximum focus for complex technical tasks.\', \'hoasen-theme\'); ?>',
    
    '<div class="modal-header">Liên hệ với HoaSen Table</div>' => '<div class="modal-header"><?php esc_html_e(\'Contact HoaSen Table\', \'hoasen-theme\'); ?></div>',
    '<p>Chúng tôi luôn đón nhận mọi ý kiến đóng góp và phản hồi từ cộng đồng nhà phát triển.</p>' => '<p><?php esc_html_e(\'We always welcome feedback and contributions from the developer community.\', \'hoasen-theme\'); ?></p>',
];

foreach($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Add the language switcher
$switcher = <<<HTML
    <?php if (function_exists('pll_the_languages')): ?>
    <div class="lang-switcher" style="display:inline-block;margin-right:12px;font-family:'Inter',sans-serif;font-size:12px;font-weight:700;">
      <ul style="list-style:none;display:flex;gap:8px;margin:0;padding:0;">
        <?php pll_the_languages(array('show_flags'=>0,'show_names'=>1,'hide_current'=>0)); ?>
      </ul>
    </div>
    <style>.lang-switcher a { text-decoration:none; color:#888; } .lang-switcher .current-lang a { color:var(--blue); pointer-events:none; }</style>
    <?php endif; ?>
    <button class="menu-btn"
HTML;
$content = str_replace('<button class="menu-btn"', $switcher, $content);

// For JS strings, we output PHP directly in JS.
$js_replacements = [
    "'Chưa thực thi truy vấn'" => '"<?php esc_html_e(\'Unexecuted Query\', \'hoasen-theme\'); ?>"',
    "'Chưa nạp dữ liệu'" => '"<?php esc_html_e(\'Not loaded\', \'hoasen-theme\'); ?>"',
    "'Chưa tải dữ liệu lớn'" => '"<?php esc_html_e(\'Not loaded\', \'hoasen-theme\'); ?>"',
    "'Di chuột khám phá'" => '"<?php esc_html_e(\'Hover to discover\', \'hoasen-theme\'); ?>"',
    "'Di chuột lên giá trị ngoại khoá để xem quan hệ.'" => '"<?php esc_html_e(\'Hover over a foreign key value to view relations.\', \'hoasen-theme\'); ?>"',
    
    "Hiệu năng truy vấn" => "<?php esc_html_e('Query Performance', 'hoasen-theme'); ?>",
    "Thời gian thực thi" => "<?php esc_html_e('Execution Time', 'hoasen-theme'); ?>",
    "Số dòng:" => "<?php esc_html_e('Row Count:', 'hoasen-theme'); ?>",
    "Bộ nhớ:" => "<?php esc_html_e('Memory:', 'hoasen-theme'); ?>",
    "Chi tiết liên kết" => "<?php esc_html_e('Relation Details', 'hoasen-theme'); ?>",
    "Tên:" => "<?php esc_html_e('Name:', 'hoasen-theme'); ?>",
    "Trạng thái:" => "<?php esc_html_e('Status:', 'hoasen-theme'); ?>",
    "Ngày tạo:" => "<?php esc_html_e('Created Date:', 'hoasen-theme'); ?>",
    
    "Dòng render:" => "<?php esc_html_e('Rendered Rows:', 'hoasen-theme'); ?>",
    "Tổng số:" => "<?php esc_html_e('Total Rows:', 'hoasen-theme'); ?>",
    "Thời gian Render:" => "<?php esc_html_e('Render Time:', 'hoasen-theme'); ?>",
    "RAM tốn:" => "<?php esc_html_e('RAM Usage:', 'hoasen-theme'); ?>",
    "60 FPS (Mượt mà)" => "<?php esc_html_e('60 FPS (Smooth)', 'hoasen-theme'); ?>",
    "dòng · virtual viewport" => "<?php esc_html_e('rows · virtual viewport', 'hoasen-theme'); ?>",
    "LOAD" => "<?php esc_html_e('LOAD', 'hoasen-theme'); ?>",
    
    "Từ khoá dự kiến" => "<?php esc_html_e('Expected keyword', 'hoasen-theme'); ?>",
    "Smart snippet" => "<?php esc_html_e('Smart snippet', 'hoasen-theme'); ?>",
    "Bảng dữ liệu" => "<?php esc_html_e('Data table', 'hoasen-theme'); ?>",
    "Kết nối" => "<?php esc_html_e('Connection', 'hoasen-theme'); ?>",
    "Cú pháp hoàn chỉnh" => "<?php esc_html_e('Complete Syntax', 'hoasen-theme'); ?>",
    "users (Bảng gốc)" => "<?php esc_html_e('users (Root Table)', 'hoasen-theme'); ?>",
    "orders (Liên kết)" => "<?php esc_html_e('orders (Relation)', 'hoasen-theme'); ?>",
];

foreach($js_replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Modification complete.\n";
