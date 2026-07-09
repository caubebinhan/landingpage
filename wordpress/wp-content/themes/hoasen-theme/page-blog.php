<?php
/**
 * Template Name: Blog
 */
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$is_vi = (strpos($lang,'vi')===0);
$t = [
  'title'   => $is_vi ? 'HoaSen Table Journal' : 'HoaSen Table Journal',
  'desc'    => $is_vi ? 'Bài viết về kỹ thuật, hiệu năng và kiến trúc của HoaSen Table.' : 'Articles on engineering, performance, and architecture of HoaSen Table.',
  'back'    => $is_vi ? '← Về trang chủ' : '← Back to Home',
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="icon" type="image/png" href="<?php echo get_stylesheet_directory_uri(); ?>/logo.png"/>"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title><?php echo esc_html($t['title']); ?></title>
<meta name="description" content="<?php echo esc_attr($t['desc']); ?>"/>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0efed;--red:#891818;--red2:#a82525;
  --text:#0f0f0f;--rs:rgba(137,24,24,.07);
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Inter",sans-serif;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background:radial-gradient(circle at 14% 13%,rgba(137,24,24,.042),transparent 31%),
             linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.bg-lotus{position:fixed;top:50%;left:65%;transform:translate(-50%,-50%);
  width:max(90vh,600px);height:max(90vh,600px);z-index:0;opacity:.035;pointer-events:none;
  background-image:url('<?php echo get_stylesheet_directory_uri(); ?>/logo.png');
  background-size:contain;background-repeat:no-repeat;background-position:center}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;
  background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(ellipse at 50% 10%,black,transparent 70%)}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#666;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}

.blog-wrap{position:relative;z-index:5;max-width:860px;margin:100px auto 40px;display:grid;grid-template-columns:220px 1fr;gap:32px;align-items:start}
@media(max-width:768px){.blog-wrap{grid-template-columns:1fr;padding:0 20px}}
.blog-sb{position:sticky;top:40px;background:rgba(255,255,255,.6);border-radius:14px;border:1px solid rgba(0,0,0,.06);padding:24px}
.blog-sb h3{font-size:10px;text-transform:uppercase;color:#888;letter-spacing:.08em;margin-bottom:16px;font-weight:900}
.blog-lnk{display:block;padding:10px 14px;border-radius:8px;font-size:12px;font-weight:600;color:#555;cursor:pointer;margin-bottom:6px;transition:all .18s;border:1px solid transparent;background:transparent}
.blog-lnk.active,.blog-lnk:hover{background:var(--rs);color:var(--red);border-color:rgba(137,24,24,.15)}

.blog-cnt{background:#fff;border-radius:16px;border:1px solid rgba(0,0,0,.08);box-shadow:0 32px 80px rgba(0,0,0,.06);padding:42px}
.bp{display:none;font-family:"Cormorant Garamond",serif}
.bp.active{display:block;animation:fadeIn .3s ease-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.bp h2{font-size:36px;font-weight:700;margin-bottom:12px;color:#0f0f0f;line-height:1.1;letter-spacing:-.02em}
.post-mt{font-size:12px;color:#888;margin-bottom:32px;font-family:"Inter",sans-serif;font-weight:600}
.bp p{font-size:18px;line-height:1.75;color:#333;margin-bottom:20px;text-wrap:pretty}
.bp code{font-family:"JetBrains Mono",monospace;font-size:14px;background:rgba(0,0,0,.04);padding:2px 6px;border-radius:4px;color:var(--red2)}
</style>
</head>
<body>

<div class="bg-lotus" aria-hidden="true"></div>
<div class="dot-grid"></div>

<a href="<?php echo esc_url(home_url('/')); ?>" class="brand">
  <svg width="32" height="32" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="filter:drop-shadow(0 3px 10px rgba(185,28,28,.28))">
    <defs>
      <linearGradient id="lg3" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#fca5a5"/><stop offset="50%" stop-color="#f43f5e"/><stop offset="100%" stop-color="#be123c"/>
      </linearGradient>
      <mask id="lm3">
        <path d="M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z" fill="white" />
        <path d="M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z" fill="white" />
        <path d="M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z" fill="white" />
        <path d="M22 50 C 5 50, 0 65, 25 80 C 35 85, 45 88, 50 90 C 25 78, 15 65, 22 50 Z" fill="white" />
        <path d="M78 50 C 95 50, 100 65, 75 80 C 65 85, 55 88, 50 90 C 75 78, 85 65, 78 50 Z" fill="white" />
      </mask>
    </defs>
    <rect width="100" height="100" fill="url(#lg3)" mask="url(#lm3)"/>
    <g stroke="#fff" stroke-width="2.8" mask="url(#lm3)">
      <line x1="0" y1="31" x2="100" y2="31"/><line x1="0" y1="38" x2="100" y2="38"/><line x1="0" y1="45" x2="100" y2="45"/><line x1="0" y1="52" x2="100" y2="52"/><line x1="0" y1="59" x2="100" y2="59"/><line x1="0" y1="66" x2="100" y2="66"/><line x1="0" y1="73" x2="100" y2="73"/><line x1="0" y1="80" x2="100" y2="80"/>
      <path d="M38 10 Q45 50 48 90" fill="none"/><path d="M50 0 V100" fill="none"/><path d="M62 10 Q55 50 52 90" fill="none"/>
      <line x1="28" y1="20" x2="28" y2="90"/><line x1="72" y1="20" x2="72" y2="90"/>
    </g>
  </svg>
  <div class="brand-name">HoaSen Table</div>
</a>
<a href="<?php echo esc_url(home_url('/')); ?>" class="back-btn"><?php echo esc_html($t['back']); ?></a>

<div class="blog-wrap">
  <aside class="blog-sb">
    <h3><?php esc_html_e($is_vi ? 'Bài viết nổi bật' : 'Featured Articles', 'hoasen-theme'); ?></h3>
    <button class="blog-lnk active" data-post="p1"><?php esc_html_e($is_vi ? 'Tối ưu Autocomplete với Grammar Parsing' : 'Optimizing SQL Autocomplete', 'hoasen-theme'); ?></button>
    <button class="blog-lnk" data-post="p2"><?php esc_html_e($is_vi ? 'Virtual Grid: Render 1 triệu hàng 60 FPS' : 'Virtual Grid: 1M rows at 60 FPS', 'hoasen-theme'); ?></button>
    <button class="blog-lnk" data-post="p3"><?php esc_html_e($is_vi ? 'Tại sao Native đánh bại Electron' : 'Why Native beats Electron', 'hoasen-theme'); ?></button>
  </aside>
  <main class="blog-cnt">
    <article id="p1" class="bp active">
      <h2><?php esc_html_e($is_vi ? 'Tối ưu hóa Autocomplete với Grammar Parsing' : 'Optimizing SQL Autocomplete with Grammar Parsing', 'hoasen-theme'); ?></h2>
      <p class="post-mt"><?php esc_html_e($is_vi ? '09/07/2026 · Nhóm Kỹ Thuật' : '09/07/2026 · Engineering Team', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'Các editor truyền thống chỉ quét qua token để gợi ý. HoaSen Table tích hợp một grammar parser (phân tích cú pháp) hoạt động trực tiếp cho mỗi phương ngữ — một Abstract Syntax Tree (AST) tạm thời sẽ quyết định chính xác token nào là hợp lệ tại con trỏ.' : 'Traditional editors scan tokens. HoaSen Table integrates a live grammar parser per dialect — a temporary AST determines exactly which tokens are legal at the cursor.', 'hoasen-theme'); ?></p>
      <p><?php _e($is_vi ? 'Thử gõ <code>SELECT * F</code> và parser sẽ biết chỉ có <code>FROM</code> là được phép ở đây — không có các cột bắt đầu bằng F nào được gợi ý làm nhiễu.' : 'Type <code>SELECT * F</code> and the parser knows only <code>FROM</code> is allowed here — no columns starting with F.', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'Quá trình phân tích cú pháp mất khoảng 1.2ms cho một câu truy vấn lớn, và được thực hiện trong một thread nền độc lập (background thread) để không làm block UI.' : 'The parsing process takes around 1.2ms for a large query and runs on an independent background thread to prevent UI blocking.', 'hoasen-theme'); ?></p>
    </article>
    <article id="p2" class="bp">
      <h2><?php esc_html_e($is_vi ? 'Virtual Grid: Render 1 Triệu Hàng ở 60 FPS' : 'Virtual Grid: Rendering 1 Million Rows at 60 FPS', 'hoasen-theme'); ?></h2>
      <p class="post-mt"><?php esc_html_e($is_vi ? '02/07/2026 · Nhóm Hiệu Năng' : '02/07/2026 · Performance Team', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'Chúng tôi duy trì chính xác 23 DOM node cho dù tập dữ liệu của bạn lớn tới đâu — tái chế các thành phần này khi bạn cuộn. Dung lượng RAM tiêu thụ: 12 KB. Thời gian render: 0.4ms cho mỗi khung hình (frame).' : 'We maintain exactly 23 DOM nodes regardless of dataset size — recycling elements as you scroll. RAM footprint: 12 KB. Render time: 0.4ms per frame.', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'Thay vì render tất cả và dùng scrollbar mặc định, chúng tôi ánh xạ giá trị cuộn để tính toán offset của từng hàng, từ đó đem lại hiệu năng "chạm đỉnh" của native app.' : 'Instead of rendering everything and using default scrollbars, we map scroll values to calculate row offsets, delivering the absolute peak performance of a native app.', 'hoasen-theme'); ?></p>
    </article>
    <article id="p3" class="bp">
      <h2><?php esc_html_e($is_vi ? 'Tại sao Giao Diện Native đánh bại Electron — và tại sao chúng tôi chọn egui' : 'Why Native UI Beats Electron — and Why We Chose egui', 'hoasen-theme'); ?></h2>
      <p class="post-mt"><?php esc_html_e($is_vi ? '25/06/2026 · Nhóm Thiết Kế' : '25/06/2026 · Design Team', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'HoaSen Table sử dụng egui — một thư viện UI immediate-mode bằng Rust biên dịch ra một tệp thực thi chỉ 3 MB, khởi động trong 78ms, và sử dụng 18 MB RAM khi rảnh. Tốc độ không phải là một tính năng. Tốc độ là BẢN CHẤT CỦA SẢN PHẨM.' : 'HoaSen Table uses egui — a Rust immediate-mode UI that compiles to 3 MB, starts in 78ms, and uses 18 MB idle RAM. Speed is not a feature. It is the core of the product.', 'hoasen-theme'); ?></p>
      <p><?php esc_html_e($is_vi ? 'Các công cụ viết bằng JS thường ngốn vài trăm MB RAM ngay khi vừa mở. Bằng cách vứt bỏ trình duyệt Chromium nặng nề, chúng tôi trả lại bộ nhớ để phục vụ cho các tập truy vấn lớn.' : 'JS-based tools often consume hundreds of MBs of RAM on startup. By ditching the heavy Chromium browser, we return that memory to serve massive query sets.', 'hoasen-theme'); ?></p>
    </article>
  </main>
</div>

<script>
document.querySelectorAll('.blog-lnk').forEach(l=>l.addEventListener('click',()=>{
  document.querySelectorAll('.blog-lnk').forEach(x=>x.classList.remove('active'));
  document.querySelectorAll('.bp').forEach(x=>x.classList.remove('active'));
  l.classList.add('active');
  document.getElementById(l.dataset.post).classList.add('active');
}));
</script>
<?php wp_footer(); ?>
</body>
</html>
