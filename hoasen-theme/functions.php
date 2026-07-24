<?php

require_once __DIR__ . '/inc/markdown.php';

add_theme_support( 'title-tag' );
add_theme_support( 'html5', array( 'style', 'script' ) );
add_theme_support( 'post-thumbnails' );

/**
 * Polylang caches its per-language home URLs in the `pll_languages_list` transient.
 * That cache is built from whatever host made the request that (re)built it — a wp-admin
 * click, a CLI script, a cron job — and then keeps serving that same host to everyone
 * until the cache is cleared. Moving the site to a new domain (dev -> staging -> prod)
 * would otherwise leave every language switcher link silently pointing at the old host
 * until someone remembers to flush the cache by hand. This self-heals it: if the cached
 * value doesn't match the current request's actual home_url(), drop the cache so Polylang
 * rebuilds it fresh, right here, on this request.
 */
add_action( 'init', function () {
    if ( ! function_exists( 'pll_languages_list' ) && ! class_exists( 'PLL' ) ) {
        return;
    }
    $cached = get_transient( 'pll_languages_list' );
    if ( ! is_array( $cached ) || empty( $cached[0]['home_url'] ) ) {
        return;
    }
    $cached_host  = wp_parse_url( $cached[0]['home_url'], PHP_URL_HOST );
    $current_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    if ( $cached_host && $current_host && $cached_host !== $current_host ) {
        delete_transient( 'pll_languages_list' );
    }
}, 1 );

// Allow the theme's own generated SVG cover images to work as valid, displayable
// attachments/featured images (SVG isn't treated as a raster image by default).
add_filter( 'upload_mimes', function ( $mimes ) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
} );
add_filter( 'file_is_displayable_image', function ( $result, $path ) {
    if ( preg_match( '/\.svg$/i', $path ) ) {
        return true;
    }
    return $result;
}, 10, 2 );
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {
    if ( preg_match( '/\.svg$/i', $filename ) ) {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4 );

register_nav_menus(
    array(
        'primary' => 'Primary Menu',
    )
);

function hoasen_blog_url() {
    $page_for_posts = get_option('page_for_posts');
    if ($page_for_posts) {
        return get_permalink($page_for_posts);
    }
    return home_url('/blog/');
}

add_action( 'after_switch_theme', 'hoasen_activate_theme_routes' );
function hoasen_activate_theme_routes() {
    hoasen_ensure_blog_page();
    flush_rewrite_rules();
}

add_action( 'init', 'hoasen_ensure_blog_page' );
function hoasen_ensure_blog_page() {
    $blog_page = get_page_by_path( 'blog' );

    if ( ! $blog_page ) {
        $page_id = wp_insert_post(
            array(
                'post_title'   => 'Blog',
                'post_name'    => 'blog',
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => '',
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return;
        }
    } else {
        $page_id = $blog_page->ID;
    }

    // Removed custom template assignment since we now use home.php

    update_post_meta( $page_id, '_yoast_wpseo_title', 'HoaSen Table Journal - Engineering & Performance' );
    update_post_meta( $page_id, '_yoast_wpseo_metadesc', 'Deep technical articles on SQL autocomplete, smart joins, virtualized data grids, and HoaSen Table architecture.' );
    update_post_meta( $page_id, '_yoast_wpseo_focuskw', 'HoaSen Table blog' );

    if ( (int) get_option( 'page_for_posts' ) !== (int) $page_id ) {
        update_option( 'page_for_posts', $page_id );
    }

    if ( get_option( 'show_on_front' ) !== 'page' ) {
        update_option( 'show_on_front', 'page' );
    }

    hoasen_ensure_blog_menu_item( $page_id );
}

function hoasen_ensure_blog_menu_item( $blog_page_id ) {
    $menu_name = 'HoaSen Main Menu';
    $menu      = wp_get_nav_menu_object( $menu_name );

    if ( ! $menu ) {
        $menu_id = wp_create_nav_menu( $menu_name );
        if ( is_wp_error( $menu_id ) ) {
            return;
        }
    } else {
        $menu_id = $menu->term_id;
    }

    $items    = wp_get_nav_menu_items( $menu_id );
    $has_blog = false;

    if ( $items ) {
        foreach ( $items as $item ) {
            if ( (int) $item->object_id === (int) $blog_page_id && $item->object === 'page' ) {
                $has_blog = true;
                break;
            }
        }
    }

    if ( ! $has_blog ) {
        wp_update_nav_menu_item(
            $menu_id,
            0,
            array(
                'menu-item-title'     => 'Blog',
                'menu-item-object'    => 'page',
                'menu-item-object-id' => $blog_page_id,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            )
        );
    }

    $locations            = get_theme_mod( 'nav_menu_locations', array() );
    $locations['primary'] = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
}

// Removed hoasen_blog_template since we now rely on WP standard routing (home.php)

add_action( 'init', 'hoasen_llms_txt', 0 );
function hoasen_llms_txt() {
    $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    if ( $path !== 'llms.txt' ) {
        return;
    }

    $posts = get_posts(
        array(
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'posts_per_page'   => 24,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        )
    );

    header( 'Content-Type: text/plain; charset=utf-8' );
    echo "# HoaSen Table\n\n";
    echo "HoaSen Table is a native SQL client for PostgreSQL, MySQL, and SQLite. It focuses on grammar-aware SQL autocomplete, smart foreign-key JOIN snippets, relation inspection, virtualized million-row grids, and plugin-driven extensibility.\n\n";
    echo "## Key URLs\n";
    echo "- Home: " . esc_url_raw( home_url( '/' ) ) . "\n";
    echo "- Blog: " . esc_url_raw( home_url( '/blog/' ) ) . "\n\n";
    echo "## Articles\n";

    foreach ( $posts as $post ) {
        $summary = wp_strip_all_tags( get_the_excerpt( $post ) ?: wp_trim_words( $post->post_content, 30, '' ) );
        echo '- ' . html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) . ': ' . esc_url_raw( get_permalink( $post ) ) . "\n";
        if ( $summary ) {
            echo '  Summary: ' . $summary . "\n";
        }
    }

    exit;
}

add_action( 'wp_head', 'hoasen_article_ai_schema', 30 );
function hoasen_article_ai_schema() {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    global $post;
    $language = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID, 'locale' ) : get_locale();
    $summary  = get_post_meta( $post->ID, '_hoasen_ai_summary', true );

    if ( ! $summary ) {
        $summary = wp_strip_all_tags( get_the_excerpt( $post ) ?: wp_trim_words( $post->post_content, 38, '' ) );
    }

    $schema = array(
        '@context'         => 'https://schema.org',
        '@type'            => 'TechArticle',
        'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
        'description'      => $summary,
        'inLanguage'       => $language,
        'datePublished'    => get_the_date( DATE_W3C, $post ),
        'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
        'author'           => array(
            '@type' => 'Organization',
            'name'  => 'HoaSen Table',
        ),
        'publisher'        => array(
            '@type' => 'Organization',
            'name'  => 'HoaSen Table',
            'url'   => home_url( '/' ),
        ),
        'mainEntityOfPage' => get_permalink( $post ),
        'about'            => array( 'schema-aware SQL editor', 'minimal SQL client', 'developer workflow', 'large-table browsing', 'custom plugins', 'creative widgets' ),
    );

    echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
    echo '<meta name="ai-summary" content="' . esc_attr( $summary ) . '">' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}

// Load translation logic without compiling .mo files
$hoasen_translations_vi = array(
    'BLOG' => 'BLOG',
    'CONTACT' => 'LIÊN HỆ',
    '01 / CONNECT' => '01 / KẾT NỐI',
    'Start smart with the Connection Board.' => 'Khởi đầu thông minh từ Connection Board.',
    'Manage grouped and color-coded connection profiles. Easily distinguish between Production (Red) and Development (Green) environments to avoid mistakes.' => 'Quản lý cấu hình kết nối được phân nhóm và gán mã màu trực quan. Dễ dàng phân biệt môi trường Production (Đỏ) và Development (Xanh) để tránh nhầm lẫn.',
    'Grouped Connections' => 'Grouped Connections',
    'Color-Coded Labels' => 'Color-Coded Labels',
    '02 / AUTOCOMPLETE' => '02 / AUTOCOMPLETE',
    'Grammar-Precise Autocomplete.' => 'Autocomplete chuẩn xác theo Ngữ pháp.',
    'Understands deep syntax and dialects of MySQL, Postgres, SQLite... Completely prevents invalid suggestions.' => 'Hiểu sâu cú pháp và các phương ngữ (Dialect) của MySQL, Postgres, SQLite... Ngăn chặn hoàn toàn các gợi ý rác không hợp lệ.',
    'Grammar-Legality' => 'Grammar-Legality',
    'Dialect-Aware' => 'Dialect-Aware',
    '03 / INTELLIGENCE' => '03 / THÔNG MINH',
    'Smart Snippets via Foreign Keys.' => 'Smart Snippet thông minh theo Khoá Ngoại.',
    'Automatically analyzes foreign key (FK) relations to suggest exact JOIN structures. Quickly fill complex ON clauses with a single keystroke.' => 'Tự động phân tích các quan hệ khoá ngoại (FK) để gợi ý cấu trúc JOIN chính xác. Điền nhanh các mệnh đề ON phức tạp chỉ bằng một phím bấm.',
    'FK-Based Join' => 'FK-Based Join',
    'Predictive Logic' => 'Predictive Logic',
    '04 / PRODUCTIVITY' => '04 / HIỆU SUẤT',
    'Minimalist High-Performance Native Workspace.' => 'Native Workspace tối giản hiệu năng cao.',
    'A pure workspace designed to maximize workflow. Visualize schemas and data instantly without redundant details.' => 'Môi trường làm việc thuần khiết được thiết kế nhằm tối đa hoá dòng chảy công việc (flow). Trực quan hoá lược đồ và dữ liệu lập tức mà không có chi tiết thừa.',
    'Focus-First UI' => 'Focus-First UI',
    'Pure Performance' => 'Pure Performance',
    '05 / DISCOVERY' => '05 / KHÁM PHÁ',
    'Instant Table Relations (Hover Relation).' => 'Truy vấn quan hệ bảng tức thời (Hover Relation).',
    'Hover over foreign key values to quickly view linked record contents without writing sub-queries or switching tabs.' => 'Di chuột lên các giá trị khoá ngoại để xem nhanh nội dung bản ghi liên kết mà không cần viết lệnh SQL phụ hay chuyển đổi tab làm việc.',
    'Instant Inspection' => 'Instant Inspection',
    'Zero Friction' => 'Zero Friction',
    '06 / CAPACITY' => '06 / SỨC MẠNH',
    'Smoothly Scroll Millions of Rows with Virtual Grid.' => 'Cuộn mượt mà hàng triệu dòng với Virtual Grid.',
    'Renders only the data visible in the viewport. Ensures ultra-smooth scrolling even with massive databases.' => 'Chỉ kết xuất những phần dữ liệu hiển thị trong khung nhìn (Viewport). Đảm bảo giao diện cuộn cực mượt mà ngay cả với cơ sở dữ liệu khổng lồ.',
    '1,000,000+ Rows' => '1,000,000+ Dòng',
    'Viewport Rendering' => 'Viewport Rendering',
    'Scroll down ↓' => 'Cuộn tiếp ↓',
    'HoaSen Table Journal' => 'HoaSen Table Journal',
    'Articles' => 'Bài viết',
    'Optimizing SQL Autocomplete by Parsing Grammar' => 'Tối ưu hóa Autocomplete SQL bằng Phân tích Ngữ pháp',
    'Published on 09/07/2026 by Engineering Team' => 'Xuất bản ngày 09/07/2026 bởi Đội ngũ kỹ thuật',
    'In traditional SQL editors, autocomplete often works by simply scanning strings. This leads to many invalid garbage suggestions at the cursor.' => 'Trong các trình soạn thảo SQL truyền thống, autocomplete thường hoạt động bằng cách quét chuỗi ký tự đơn giản. Điều này dẫn đến rất nhiều gợi ý rác không hợp lệ tại vị trí con trỏ.',
    'HoaSen Table solves this by directly integrating the parser of each DBMS. As you type, the system builds a temporary AST (Abstract Syntax Tree) to exactly determine the next valid keywords or schema objects.' => 'HoaSen Table giải quyết vấn đề này bằng cách tích hợp trực tiếp bộ phân tích cú pháp (parser) của từng hệ quản trị cơ sở dữ liệu. Khi bạn gõ, hệ thống sẽ dựng một AST (Abstract Syntax Tree) tạm thời, từ đó xác định chính xác các từ khoá hoặc đối tượng lược đồ hợp lệ tiếp theo.',
    'For example, if you just typed <code>SELECT * F</code>, the editor knows the grammar only allows a <code>FROM</code> clause here and filters out all columns or tables starting with "F".' => 'Ví dụ, nếu bạn vừa gõ <code>SELECT * F</code>, trình soạn thảo sẽ biết chắc chắn ngữ pháp chỉ cho phép mệnh đề <code>FROM</code> tại đây và lọc bỏ toàn bộ các cột hay bảng bắt đầu bằng chữ "F".',
    'Virtual Grid Mechanism: Smoothly Handling 1 Million Rows' => 'Cơ chế Virtual Grid: Xử lý 1 triệu dòng mượt mà',
    'Published on 02/07/2026 by Performance Team' => 'Xuất bản ngày 02/07/2026 bởi Đội ngũ hiệu năng',
    'Displaying millions of rows in a GUI is a major challenge for memory and CPU. A standard browser or app will freeze if it tries to render hundreds of thousands of HTML elements at once.' => 'Hiển thị hàng triệu dòng dữ liệu trên giao diện đồ họa (GUI) luôn là một thử thách lớn về bộ nhớ và CPU. Trình duyệt hoặc ứng dụng thông thường sẽ bị đơ cứng nếu cố gắng render hàng trăm ngàn thẻ HTML cùng lúc.',
    'HoaSen Table uses <b>Viewport Virtualization</b>. We only render the data rows currently visible in the user\'s viewport (around 20-30 rows). As you scroll, these elements are recycled to load new data, keeping DOM nodes to a minimum and RAM footprint to a few kilobytes.' => 'HoaSen Table sử dụng kỹ thuật <b>Viewport Virtualization</b>. Chúng tôi chỉ vẽ các dòng dữ liệu đang hiển thị trong khung nhìn của người dùng (khoảng 20-30 dòng). Khi người dùng cuộn, các phần tử này sẽ được tái sử dụng để nạp dữ liệu mới, giữ cho số lượng nút DOM luôn ở mức tối thiểu và bộ nhớ tiêu thụ chỉ khoảng vài kilobyte.',
    'Vintage UI Design: The Art of Restraint' => 'Thiết kế Giao diện Vintage: Nghệ thuật của sự Tiết chế',
    'Published on 25/06/2026 by Design Team' => 'Xuất bản ngày 25/06/2026 bởi Đội ngũ thiết kế',
    'The era of flat, monotonous SaaS interfaces has diluted brand personalities. With HoaSen Table, we return to the core values of classical print typography: elegant typefaces and deep colors.' => 'Thời đại của các giao diện SaaS phẳng lì và đơn điệu đã làm nhạt nhoà cá tính thương hiệu. Với HoaSen Table, chúng tôi quay lại giá trị cốt lõi của nghệ thuật in ấn cổ điển: kiểu chữ tinh tế và màu sắc có chiều sâu.',
    'The combination of the formal <b>Cormorant Garamond</b> serif font, the sharp contrast of ash-gray, and the deep oxblood red creates an inspiring workspace while maintaining maximum focus for complex technical tasks.' => 'Sự kết hợp giữa font chữ serif trang trọng <b>Cormorant Garamond</b>, độ tương phản sắc nét của sắc xám tro ash-gray và màu đỏ huyết dụ oxblood giúp tạo ra một giao diện làm việc đầy cảm hứng nhưng vẫn giữ được sự tập trung tối đa cho công việc kỹ thuật phức tạp.',
    'Contact HoaSen Table' => 'Liên hệ với HoaSen Table',
    'We always welcome feedback and contributions from the developer community.' => 'Chúng tôi luôn đón nhận mọi ý kiến đóng góp và phản hồi từ cộng đồng nhà phát triển.',
    'Unexecuted Query' => 'Chưa thực thi truy vấn',
    'Not loaded' => 'Chưa nạp dữ liệu',
    'Hover to discover' => 'Di chuột khám phá',
    'Hover over a foreign key value to view relations.' => 'Di chuột lên giá trị ngoại khoá để xem quan hệ.',
    'Virtual Grid Metrics' => 'Virtual Grid Metrics',
    'Rendered Rows:' => 'Dòng render:',
    'Total Rows:' => 'Tổng số:',
    'Render Time:' => 'Thời gian Render:',
    'RAM Usage:' => 'RAM tốn:',
    '60 FPS (Smooth)' => '60 FPS (Mượt mà)',
    'rows · virtual viewport' => 'dòng · virtual viewport',
    'LOAD' => 'TẢI',
    'Query Performance' => 'Hiệu năng truy vấn',
    'Execution Time' => 'Thời gian thực thi',
    'Row Count:' => 'Số dòng:',
    'Memory:' => 'Bộ nhớ:',
    'Relation Details' => 'Chi tiết liên kết',
    'Name:' => 'Tên:',
    'Status:' => 'Trạng thái:',
    'Created Date:' => 'Ngày tạo:',
    'Expected keyword' => 'Từ khoá dự kiến',
    'Smart snippet' => 'Smart snippet',
    'Data table' => 'Bảng dữ liệu',
    'Connection' => 'Kết nối',
    'Complete Syntax' => 'Cú pháp hoàn chỉnh',
    'users (Root Table)' => 'users (Bảng gốc)',
    'orders (Relation)' => 'orders (Liên kết)',

    // Blog listing (home.php)
    'HoaSen Journal' => 'HoaSen Nhật Ký',
    'Articles on engineering, performance, and architecture of HoaSen Table.' => 'Bài viết về kỹ thuật, hiệu năng và kiến trúc của HoaSen Table.',
    '← Back to Home' => '← Về trang chủ',
    'Engineering & Performance' => 'Kỹ Thuật & Hiệu Năng',
    'Deep-dives into database engine design, query performance optimization, and front-end architecture from the HoaSen Table team.' => 'Các bài viết đi sâu vào kỹ thuật, hiệu năng và kiến trúc thiết kế hệ thống dữ liệu của đội ngũ HoaSen Table.',
    'No articles have been published in this language yet.' => 'Chưa có bài viết nào được đăng bằng ngôn ngữ này.',
    '« Prev' => '« Trước',
    'Next »' => 'Sau »',

    // Single post (single.php)
    '← Back to Blog' => '← Về Blog',
    'Related articles' => 'Bài liên quan',
    'Read next' => 'Đọc tiếp',
    'min read' => 'phút đọc',

    // Autocomplete deep-dive page (page-autocomplete.php)
    'Autocomplete Deep Dive' => 'Autocomplete Chi Tiết',
    'Discover how AST-driven autocomplete works.' => 'Khám phá cách AST-driven autocomplete hoạt động.',
    'Technology Deep Dive' => 'Khám phá công nghệ',
    'Under the Hood of Autocomplete' => 'Bên dưới lớp vỏ Autocomplete',
    'Not just fuzzy string matching. HoaSen Table integrates a real-time SQL parser to build an AST, ensuring every suggestion is syntactically perfect.' => 'Không chỉ là gợi ý chuỗi đơn thuần. HoaSen Table tích hợp một parser SQL thời gian thực để xây dựng AST, đảm bảo mỗi gợi ý đều chuẩn xác về mặt cú pháp ngữ pháp.',
    'Editor' => 'Trình soạn thảo',
    'Legal Completions' => 'Gợi ý hợp lệ',
    'Live AST Tree' => 'Cây AST Trực tiếp',
);

$hoasen_translations_ja = array(
    'BLOG' => 'ブログ',
    'CONTACT' => 'お問い合わせ',
    '01 / CONNECT' => '01 / 接続',
    'Start smart with the Connection Board.' => 'コネクションボードからスマートに始めましょう。',
    'Manage grouped and color-coded connection profiles. Easily distinguish between Production (Red) and Development (Green) environments to avoid mistakes.' => 'グループ化され色分けされた接続プロファイルを管理します。本番環境（赤）と開発環境（緑）を簡単に区別し、ミスを防ぎます。',
    'Grouped Connections' => 'グループ化された接続',
    'Color-Coded Labels' => '色分けされたラベル',
    '02 / AUTOCOMPLETE' => '02 / 自動補完',
    'Grammar-Precise Autocomplete.' => '文法に正確なオートコンプリート。',
    'Understands deep syntax and dialects of MySQL, Postgres, SQLite... Completely prevents invalid suggestions.' => 'MySQL、Postgres、SQLiteなどの深い構文と方言を理解し、無効な提案を完全に防ぎます。',
    'Grammar-Legality' => '文法適合性',
    'Dialect-Aware' => '方言対応',
    '03 / INTELLIGENCE' => '03 / インテリジェンス',
    'Smart Snippets via Foreign Keys.' => '外部キーによるスマートスニペット。',
    'Automatically analyzes foreign key (FK) relations to suggest exact JOIN structures. Quickly fill complex ON clauses with a single keystroke.' => '外部キー（FK）関係を自動分析し、正確なJOIN構造を提案します。複雑なON句をワンタッチで素早く入力できます。',
    'FK-Based Join' => 'FKベースJOIN',
    'Predictive Logic' => '予測ロジック',
    '04 / PRODUCTIVITY' => '04 / 生産性',
    'Minimalist High-Performance Native Workspace.' => 'ミニマリストで高性能なネイティブワークスペース。',
    'A pure workspace designed to maximize workflow. Visualize schemas and data instantly without redundant details.' => 'ワークフローを最大化するために設計された純粋なワークスペース。冗長な詳細なしでスキーマとデータを即座に視覚化します。',
    'Focus-First UI' => 'フォーカス優先UI',
    'Pure Performance' => '純粋なパフォーマンス',
    '05 / DISCOVERY' => '05 / 発見',
    'Instant Table Relations (Hover Relation).' => '瞬時のテーブルリレーション（ホバーリレーション）。',
    'Hover over foreign key values to quickly view linked record contents without writing sub-queries or switching tabs.' => '外部キー値にホバーするだけで、サブクエリを書いたりタブを切り替えたりせずに、リンクされたレコードの内容をすばやく表示できます。',
    'Instant Inspection' => '即時検査',
    'Zero Friction' => '摩擦ゼロ',
    '06 / CAPACITY' => '06 / キャパシティ',
    'Smoothly Scroll Millions of Rows with Virtual Grid.' => '仮想グリッドで数百万行をスムーズにスクロール。',
    'Renders only the data visible in the viewport. Ensures ultra-smooth scrolling even with massive databases.' => 'ビューポートに表示されるデータのみをレンダリングします。巨大なデータベースでも超スムーズなスクロールを保証します。',
    '1,000,000+ Rows' => '1,000,000+ 行',
    'Viewport Rendering' => 'ビューポートレンダリング',
    'Scroll down ↓' => '下にスクロール ↓',
    'HoaSen Table Journal' => 'HoaSen Table ジャーナル',
    'Articles' => '記事',
    'Optimizing SQL Autocomplete by Parsing Grammar' => '文法解析によるSQLオートコンプリートの最適化',
    'Published on 09/07/2026 by Engineering Team' => 'エンジニアリングチームによって2026年7月9日に公開',
    'In traditional SQL editors, autocomplete often works by simply scanning strings. This leads to many invalid garbage suggestions at the cursor.' => '従来のSQLエディターでは、オートコンプリートは単に文字列をスキャンすることで機能することが多く、カーソル位置に無効な不要な提案が多数表示されます。',
    'HoaSen Table solves this by directly integrating the parser of each DBMS. As you type, the system builds a temporary AST (Abstract Syntax Tree) to exactly determine the next valid keywords or schema objects.' => 'HoaSen Tableは、各DBMSのパーサーを直接統合することでこれを解決します。入力時にシステムが一時的なAST（抽象構文木）を構築し、次に有効なキーワードやスキーマオブジェクトを正確に決定します。',
    'For example, if you just typed <code>SELECT * F</code>, the editor knows the grammar only allows a <code>FROM</code> clause here and filters out all columns or tables starting with "F".' => 'たとえば、<code>SELECT * F</code>と入力した場合、エディターはここでは<code>FROM</code>句のみが文法的に許可されることを認識し、「F」で始まるすべての列やテーブルを除外します。',
    'Virtual Grid Mechanism: Smoothly Handling 1 Million Rows' => '仮想グリッドメカニズム：100万行をスムーズに処理',
    'Published on 02/07/2026 by Performance Team' => 'パフォーマンスチームによって2026年7月2日に公開',
    'Displaying millions of rows in a GUI is a major challenge for memory and CPU. A standard browser or app will freeze if it tries to render hundreds of thousands of HTML elements at once.' => 'GUIに数百万行を表示することは、メモリとCPUにとって大きな課題です。標準のブラウザやアプリは、数十万のHTML要素を一度にレンダリングしようとするとフリーズします。',
    'HoaSen Table uses <b>Viewport Virtualization</b>. We only render the data rows currently visible in the user\'s viewport (around 20-30 rows). As you scroll, these elements are recycled to load new data, keeping DOM nodes to a minimum and RAM footprint to a few kilobytes.' => 'HoaSen Tableは<b>ビューポートの仮想化</b>を使用します。ユーザーのビューポートに現在表示されているデータ行（約20〜30行）のみをレンダリングします。スクロールすると、これらの要素は新しいデータを読み込むためにリサイクルされ、DOMノードを最小限に抑え、RAMの使用量を数キロバイトに保ちます。',
    'Vintage UI Design: The Art of Restraint' => 'ヴィンテージUIデザイン：抑制の美学',
    'Published on 25/06/2026 by Design Team' => 'デザインチームによって2026年6月25日に公開',
    'The era of flat, monotonous SaaS interfaces has diluted brand personalities. With HoaSen Table, we return to the core values of classical print typography: elegant typefaces and deep colors.' => 'フラットで単調なSaaSインターフェースの時代は、ブランドの個性を薄めてしまいました。HoaSen Tableでは、古典的な活版印刷の中核的な価値観、つまりエレガントな書体と深い色合いに立ち返ります。',
    'The combination of the formal <b>Cormorant Garamond</b> serif font, the sharp contrast of ash-gray, and the deep oxblood red creates an inspiring workspace while maintaining maximum focus for complex technical tasks.' => 'フォーマルな<b>Cormorant Garamond</b>セリフフォント、アッシュグレーの鋭いコントラスト、深いオックスブラッドレッドの組み合わせは、複雑な技術的タスクに最大限の集中を維持しながら、インスピレーションを与えるワークスペースを作り出します。',
    'Contact HoaSen Table' => 'HoaSen Tableへのお問い合わせ',
    'We always welcome feedback and contributions from the developer community.' => '開発者コミュニティからのフィードバックや貢献を常に歓迎しています。',
    'Unexecuted Query' => '未実行のクエリ',
    'Not loaded' => '未読み込み',
    'Hover to discover' => 'ホバーして発見',
    'Hover over a foreign key value to view relations.' => '外部キー値にホバーして関係を表示します。',
    'Virtual Grid Metrics' => '仮想グリッドメトリクス',
    'Rendered Rows:' => 'レンダリング行数:',
    'Total Rows:' => '合計行数:',
    'Render Time:' => 'レンダリング時間:',
    'RAM Usage:' => 'RAM使用量:',
    '60 FPS (Smooth)' => '60 FPS（スムーズ）',
    'rows · virtual viewport' => '行 · 仮想ビューポート',
    'LOAD' => 'ロード',
    'Query Performance' => 'クエリパフォーマンス',
    'Execution Time' => '実行時間',
    'Row Count:' => '行数:',
    'Memory:' => 'メモリ:',
    'Relation Details' => '関係の詳細',
    'Name:' => '名前:',
    'Status:' => 'ステータス:',
    'Created Date:' => '作成日:',
    'Expected keyword' => '予期されるキーワード',
    'Smart snippet' => 'スマートスニペット',
    'Data table' => 'データテーブル',
    'Connection' => '接続',
    'Complete Syntax' => '完全な構文',
    'users (Root Table)' => 'users（ルートテーブル）',
    'orders (Relation)' => 'orders（関係）',

    // Blog listing (home.php)
    'HoaSen Journal' => 'HoaSenジャーナル',
    'Articles on engineering, performance, and architecture of HoaSen Table.' => 'HoaSen Tableのエンジニアリング、パフォーマンス、アーキテクチャに関する記事。',
    '← Back to Home' => '← ホームへ戻る',
    'Engineering & Performance' => 'エンジニアリングとパフォーマンス',
    'Deep-dives into database engine design, query performance optimization, and front-end architecture from the HoaSen Table team.' => 'HoaSen Tableチームによる、データベースエンジン設計、クエリパフォーマンス最適化、フロントエンドアーキテクチャの詳細解説。',
    'No articles have been published in this language yet.' => 'この言語ではまだ記事が公開されていません。',
    '« Prev' => '« 前へ',
    'Next »' => '次へ »',

    // Single post (single.php)
    '← Back to Blog' => '← ブログへ戻る',
    'Related articles' => '関連記事',
    'Read next' => '次に読む',
    'min read' => '分で読めます',

    // Autocomplete deep-dive page (page-autocomplete.php)
    'Autocomplete Deep Dive' => 'オートコンプリート詳細解説',
    'Discover how AST-driven autocomplete works.' => 'AST駆動のオートコンプリートの仕組みを解説します。',
    'Technology Deep Dive' => '技術詳細解説',
    'Under the Hood of Autocomplete' => 'オートコンプリートの内部構造',
    'Not just fuzzy string matching. HoaSen Table integrates a real-time SQL parser to build an AST, ensuring every suggestion is syntactically perfect.' => '単なるあいまい文字列一致ではありません。HoaSen Tableはリアルタイムのsqlパーサーを統合してASTを構築し、すべての提案が構文的に正確であることを保証します。',
    'Editor' => 'エディタ',
    'Legal Completions' => '有効な補完候補',
    'Live AST Tree' => 'ライブASTツリー',
);

$hoasen_landing_vi = array(
    'HoaSen Table — Free, Fast & Minimal SQL Client (Postgres, MySQL, SQLite)' => 'HoaSen Table — SQL Client Miễn Phí, Tối Giản Cho Developer',
    'A free, native-speed, and minimal SQL client for Postgres, MySQL, and SQLite. Built with zero bloat and designed to maximize developer focus.' => 'SQL client miễn phí, tốc độ native, siêu nhẹ cho Postgres, MySQL và SQLite. Thiết kế tối giản giúp lập trình viên tập trung tối đa công việc.',
    'BLOG' => 'BLOG',
    'CONTACT' => 'LIÊN HỆ',
    'Scroll to explore ↓' => 'Cuộn khám phá ↓',
    'Installed' => 'Đã cài',
    'Install' => 'Cài đặt',
    'PLUGINS' => 'PLUGINS',
    'Deep dive: Autocomplete →' => 'Chi tiết Autocomplete →',
    '✓ loaded · 50 rows' => '✓ đã tải · 50 hàng',
    'active' => 'hoạt động',
    'Viewing row' => 'Đang xem hàng',
    'of' => 'trong',
    'Minimal DB Workspace' => 'Workspace DB Tối Giản',
    'ENTERPRISE-GRADE · ZERO BLOAT' => 'ĐẲNG CẤP DOANH NGHIỆP · KHÔNG CỒNG KỀNH',
    'The database workspace built to never slow you down.' => 'Không gian làm việc database được xây dựng để không bao giờ làm bạn chậm lại.',
    'HoaSen Table pairs production-grade SQL tooling with obsessive craft. Connect, query, inspect, and move on — native-fast, distraction-free, no ceremony.' => 'HoaSen Table kết hợp công cụ SQL chuẩn production với sự tỉ mỉ tuyệt đối. Kết nối, truy vấn, kiểm tra và làm chủ dữ liệu của bạn ở tốc độ native, không dư thừa, tối đa hiệu suất.',
    
    'AI-POWERED · SCHEMA AWARE' => 'AI-POWERED · NHẬN DIỆN LƯỢC ĐỒ',
    'Real intelligence that anticipates your next query.' => 'Trí tuệ thực sự dự đoán câu truy vấn tiếp theo của bạn.',
    'HoaSen reads your live schema, tracks query context in real time, and applies machine learning that adapts to how you actually work — suggestions that feel like they read your mind.' => 'HoaSen tự động đọc lược đồ cơ sở dữ liệu hiện tại, theo dõi ngữ cảnh thời gian thực và áp dụng Machine Learning để tối ưu hóa gợi ý như đọc được suy nghĩ của bạn.',
    
    'RESULTS STAY CLOSE' => 'KẾT QUẢ NGAY TẠI CHỖ',
    'Blazing-fast execution. Zero context switching.' => 'Thực thi siêu tốc. Không chuyển đổi ngữ cảnh.',
    'Results render instantly inside your active workspace. Peek at foreign key relations on hover — no subqueries, no tab-hopping, pure uninterrupted flow.' => 'Kết quả hiển thị ngay trong workspace hiện tại. Xem nhanh liên kết khóa ngoại khi di chuột qua — không cần viết subquery, không nhảy tab, luồng công việc tuyệt đối.',
    
    'MASSIVE SCALE · EXTREME SPEED' => 'QUY MÔ KHỔNG LỒ · TỐC ĐỘ TỐI ĐA',
    'A million rows at 60 FPS, without breaking a sweat.' => 'Một triệu dòng ở 60 FPS, mượt mà nhẹ nhàng.',
    'Open and scroll through millions of rows instantly. Our virtualized grid renders a constant 23 DOM nodes — never more — so the workspace stays smooth and responsive under any load.' => 'Mở và cuộn qua hàng triệu dòng ngay lập tức. Virtual grid của chúng tôi chỉ render đúng 23 DOM nodes — không bao giờ hơn — giữ cho workspace luôn mượt mà và phản hồi tốt dưới bất kỳ tải trọng nào.',
    
    'PLUGIN ECOSYSTEM · LIMITLESS' => 'HỆ SINH THÁI PLUGIN · KHÔNG GIỚI HẠN',
    'Build the perfect environment around your workflow.' => 'Xây dựng môi trường hoàn hảo xoay quanh quy trình của bạn.',
    'Plugins are fully custom and live inside the workspace, so teams can ship the exact tools they need — no forked context, no lost query, no compromise.' => 'Các plugin được tùy biến hoàn toàn và nằm gọn trong workspace, giúp các đội nhóm thêm đúng công cụ họ cần — không phân tán ngữ cảnh, không mất truy vấn, không thỏa hiệp.',
    
    'CREATIVE CONTROL · STILL MINIMAL' => 'KIỂM SOÁT SÁNG TẠO · VẪN TỐI GIẢN',
    'Turn widgets into your team\'s edge.' => 'Biến các widget thành lợi thế của đội bạn.',
    'Start minimal, then add exactly the power you need. Rearrange widgets to surface the context your workflow demands — nothing more, nothing less.' => 'Bắt đầu một cách tối giản, sau đó thêm đúng sức mạnh bạn cần. Sắp xếp các widget để hiển thị ngữ cảnh quy trình làm việc đòi hỏi — không thừa, không thiếu.',
    
    'FOR TEAMS THAT MOVE FAST' => 'DÀNH CHO ĐỘI NGŨ THẦN TỐC',
    'The sharpest SQL client for teams that move fast.' => 'SQL client sắc bén nhất dành cho các đội ngũ tốc độ cao.',
    'Built by engineers, for engineers. Read the guide, dig into the docs, or tell us where your workflow slows — we\'re obsessed with making you faster.' => 'Được xây dựng bởi kỹ sư, dành cho kỹ sư. Đọc hướng dẫn, xem tài liệu, hoặc cho chúng tôi biết điểm nghẽn của bạn — chúng tôi luôn ám ảnh việc giúp bạn nhanh hơn.',
    'Context-ranked completions' => 'Gợi ý xếp hạng theo ngữ cảnh',
    'Schema + context tree' => 'Cây Schema + Ngữ cảnh',
    'Contact' => 'Liên hệ',
    'Tell us where your database workflow feels slow, noisy, or overbuilt. HoaSen is shaped around developer focus.' => 'Hãy chia sẻ nơi quy trình dữ liệu của bạn đang chậm, ồn ào hoặc quá rườm rà. HoaSen được định hình để bảo vệ sự tập trung của lập trình viên.',
    'Understands deep syntax and dialects of MySQL, Postgres, SQLite... Completely prevents invalid suggestions.' => 'Hiểu sâu cú pháp các hệ DB (MySQL, Postgres, SQLite...). Tự động nhận diện lược đồ (schema-aware), hiểu ngữ cảnh (context-aware) và ứng dụng Machine Learning để tự học thói quen truy vấn.',
    'Smart Snippets via Foreign Keys.' => 'Gợi ý Snippet thông minh theo Khóa Ngoại.',
    'A pure workspace designed to maximize workflow. Visualize schemas and data instantly without redundant details.' => 'Không gian làm việc thuần khiết được thiết kế nhằm tối đa hóa dòng chảy công việc (flow). Trực quan hóa lược đồ và dữ liệu lập tức mà không có chi tiết thừa.',
    'Hover over foreign key values to quickly view linked record contents without writing sub-queries or switching tabs.' => 'Di chuột lên các giá trị khóa ngoại để xem nhanh nội dung bản ghi liên kết mà không cần viết truy vấn phụ hay chuyển đổi tab.',
    'Renders only the data visible in the viewport. Ensures ultra-smooth scrolling even with massive databases.' => 'Chỉ render phần dữ liệu hiển thị trong khung nhìn (Viewport). Đảm bảo trải nghiệm cuộn siêu mượt mà ngay cả với cơ sở dữ liệu khổng lồ.',
    'Native speed, zero bloat. Built for developer focus.' => 'Tốc độ native, không dư thừa. Thiết kế cho sự tập trung.',
);

$hoasen_landing_ja = array(
    'HoaSen Table — Free, Fast & Minimal SQL Client (Postgres, MySQL, SQLite)' => 'HoaSen Table — 無料で高速、ミニマルなSQLクライアント（Postgres、MySQL、SQLite）',
    'A free, native-speed, and minimal SQL client for Postgres, MySQL, and SQLite. Built with zero bloat and designed to maximize developer focus.' => '無料でネイティブな速度、そしてミニマルなPostgres、MySQL、SQLite用SQLクライアント。無駄を省き、開発者の集中力を最大化するように設計されています。',
    'BLOG' => 'ブログ',
    'CONTACT' => 'お問い合わせ',
    'Scroll to explore ↓' => 'スクロールして探索 ↓',
    'Installed' => 'インストール済み',
    'Install' => 'インストール',
    'PLUGINS' => 'プラグイン',
    'Deep dive: Autocomplete →' => '詳細: オートコンプリート →',
    '✓ loaded · 50 rows' => '✓ 読み込み完了 · 50行',
    'active' => 'アクティブ',
    'Viewing row' => '表示中の行',
    'of' => 'の中の',
    'Minimal DB Workspace' => 'ミニマルなDBワークスペース',
    'ENTERPRISE-GRADE · ZERO BLOAT' => 'エンタープライズ品質 · 無駄なし',
    'The database workspace built to never slow you down.' => '決して速度を落とさないために構築されたデータベースワークスペース。',
    'HoaSen Table pairs production-grade SQL tooling with obsessive craft. Connect, query, inspect, and move on — native-fast, distraction-free, no ceremony.' => 'HoaSen Tableは、本番レベルのSQLツールとこだわりのクラフトを組み合わせています。ネイティブな速度、気晴らしゼロ、手順ゼロで、接続、クエリ、検査、そして次に進みます。',
    
    'AI-POWERED · SCHEMA AWARE' => 'AI駆動 · スキーマ認識',
    'Real intelligence that anticipates your next query.' => '次のクエリを予測する真のインテリジェンス。',
    'HoaSen reads your live schema, tracks query context in real time, and applies machine learning that adapts to how you actually work — suggestions that feel like they read your mind.' => 'HoaSenは稼働中のスキーマを読み取り、クエリのコンテキストをリアルタイムで追跡し、実際の作業方法に適応する機械学習を適用します。心を読んでいるかのような提案を提供します。',
    
    'RESULTS STAY CLOSE' => '結果はすぐそばに',
    'Blazing-fast execution. Zero context switching.' => '超高速実行。コンテキスト切り替えゼロ。',
    'Results render instantly inside your active workspace. Peek at foreign key relations on hover — no subqueries, no tab-hopping, pure uninterrupted flow.' => '結果はアクティブなワークスペース内に即座にレンダリングされます。ホバー時に外部キー関係を覗き見します。サブクエリなし、タブ移動なし、純粋な中断のないフロー。',
    
    'MASSIVE SCALE · EXTREME SPEED' => '大規模 · 極限の速度',
    'A million rows at 60 FPS, without breaking a sweat.' => '汗をかかずに100万行を60 FPSで。',
    'Open and scroll through millions of rows instantly. Our virtualized grid renders a constant 23 DOM nodes — never more — so the workspace stays smooth and responsive under any load.' => '100万行を即座に開いてスクロールします。仮想化グリッドは常に23のDOMノードのみをレンダリングするため、どんな負荷の下でもワークスペースはスムーズで応答性が高くなります。',
    
    'PLUGIN ECOSYSTEM · LIMITLESS' => 'プラグインエコシステム · 無限',
    'Build the perfect environment around your workflow.' => 'ワークフローに合わせて完璧な環境を構築します。',
    'Plugins are fully custom and live inside the workspace, so teams can ship the exact tools they need — no forked context, no lost query, no compromise.' => 'プラグインは完全にカスタムでワークスペース内に存在するため、チームは必要なツールを正確に提供できます。コンテキストの分岐なし、クエリの損失なし、妥協なし。',
    
    'CREATIVE CONTROL · STILL MINIMAL' => 'クリエイティブなコントロール · 依然としてミニマル',
    'Turn widgets into your team\'s edge.' => 'ウィジェットをチームの強みに変えます。',
    'Start minimal, then add exactly the power you need. Rearrange widgets to surface the context your workflow demands — nothing more, nothing less.' => 'ミニマルから始めて、必要なだけの力を追加します。ウィジェットを再配置して、ワークフローが要求するコンテキストを表面化させます。多すぎず少なすぎず。',
    
    'FOR TEAMS THAT MOVE FAST' => '速く動くチームのために',
    'The sharpest SQL client for teams that move fast.' => '速く動くチームのための最もシャープなSQLクライアント。',
    'Built by engineers, for engineers. Read the guide, dig into the docs, or tell us where your workflow slows — we\'re obsessed with making you faster.' => 'エンジニアによるエンジニアのための設計。ガイドを読み、ドキュメントを掘り下げ、ワークフローが遅くなる場所を教えてください。私たちはあなたを速くすることに夢中になっています。',
    'Context-ranked completions' => 'コンテキスト順候補',
    'Schema + context tree' => 'スキーマ + コンテキストツリー',
    'Contact' => 'お問い合わせ',
    'Tell us where your database workflow feels slow, noisy, or overbuilt. HoaSen is shaped around developer focus.' => 'データベースのワークフローが遅く、騒がしく、または過剰に構築されていると感じる部分を教えてください。HoaSenは開発者のフォーカスを中心に形作られています。',
    'Native speed, zero bloat. Built for developer focus.' => 'ネイティブの速度、無駄のない構成。開発者のフォーカスのために。',
);

add_filter('gettext', function($translation, $text, $domain) {
    if ($domain === 'hoasen-theme') {
        global $hoasen_translations_vi, $hoasen_translations_ja, $hoasen_landing_vi, $hoasen_landing_ja;
        $loc = get_locale();
        if (strpos($loc, 'vi') === 0) {
            if (isset($hoasen_landing_vi[$text])) {
                return $hoasen_landing_vi[$text];
            }
            if (isset($hoasen_translations_vi[$text])) {
                return $hoasen_translations_vi[$text];
            }
        }
        if (strpos($loc, 'ja') === 0) {
            if (isset($hoasen_landing_ja[$text])) {
                return $hoasen_landing_ja[$text];
            }
            if (isset($hoasen_translations_ja[$text])) {
                return $hoasen_translations_ja[$text];
            }
        }
    }
    return $translation;
}, 10, 3);

// LLM SEO Robots.txt Filter
add_filter( 'robots_txt', 'hoasen_custom_robots_txt', 10, 2 );
function hoasen_custom_robots_txt( $output, $public ) {
    $output .= "\n# LLM SEO Optimizations\n";
    $output .= "User-agent: GPTBot\nAllow: /\n";
    $output .= "User-agent: ClaudeBot\nAllow: /\n";
    $output .= "User-agent: Google-Extended\nAllow: /\n";
    $output .= "User-agent: PerplexityBot\nAllow: /\n";
    $output .= "\nSitemap: " . esc_url( home_url( '/sitemap.xml' ) ) . "\n";
    $output .= "Sitemap: " . esc_url( home_url( '/llms.txt' ) ) . "\n";
    return $output;
}
