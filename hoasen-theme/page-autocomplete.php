<?php
/**
 * Template Name: Autocomplete Deep Dive
 */
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$is_vi = (strpos($lang,'vi')===0);
$t = [
  'title'   => $is_vi ? 'Autocomplete Chi Tiết' : 'Autocomplete Deep Dive',
  'desc'    => $is_vi ? 'Khám phá cách AST-driven autocomplete hoạt động.' : 'Discover how AST-driven autocomplete works.',
  'back'    => $is_vi ? '← Về trang chủ' : '← Back to Home',
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="icon" type="image/png" href="<?php echo get_stylesheet_directory_uri(); ?>/logo.png"/>"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title><?php echo esc_html($t['title']); ?> — HoaSen Table</title>
<meta name="description" content="<?php echo esc_attr($t['desc']); ?>"/>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0efed;--red:#891818;--red2:#a82525;--green:#0f6b3e;--blue:#1a4fc4;
  --text:#0f0f0f;--rs:rgba(137,24,24,.07);
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Inter",sans-serif;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background:radial-gradient(circle at 14% 13%,rgba(137,24,24,.042),transparent 31%),
             radial-gradient(circle at 83% 21%,rgba(15,107,62,.03),transparent 29%),
             linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;
  background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(ellipse at 50% 10%,black,transparent 70%)}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#666;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}

.hero{text-align:center;padding:120px 20px 60px;position:relative;z-index:10}
.hero-tag{font-size:11px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:var(--red);margin-bottom:20px;display:inline-block;padding:6px 14px;background:var(--rs);border-radius:999px}
.hero h1{font-family:"Cormorant Garamond",serif;font-size:clamp(40px,5vw,72px);font-weight:700;letter-spacing:-.03em;line-height:1;margin-bottom:24px;color:#111}
.hero p{font-size:18px;color:#555;max-width:600px;margin:0 auto;line-height:1.6}

.demo-wrap{max-width:960px;margin:0 auto 80px;position:relative;z-index:10;padding:0 20px}
.app-window{background:#fff;border-radius:18px;border:1px solid rgba(0,0,0,.1);box-shadow:0 32px 80px rgba(0,0,0,.08);overflow:hidden;display:grid;grid-template-columns:1fr 1fr;height:520px}
@media(max-width:768px){.app-window{grid-template-columns:1fr;height:auto}}
.app-left{padding:32px;border-right:1px solid rgba(0,0,0,.07);background:#fafaf8;display:flex;flex-direction:column;gap:16px}
.app-right{padding:32px;display:flex;flex-direction:column;gap:16px}

.lbl{font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#aaa}
.ed{font-family:"JetBrains Mono",monospace;font-size:15px;line-height:1.7;color:#222;background:#fff;padding:24px;border-radius:12px;border:1px solid rgba(0,0,0,.06);box-shadow:inset 0 2px 10px rgba(0,0,0,.02)}
.kw{color:var(--red);font-weight:700}.tbl{color:var(--blue)}.col{color:var(--green)}.num{color:#a16207}

.ac-pop{margin-top:10px;border:1px solid rgba(137,24,24,.2);border-radius:12px;background:#fff;box-shadow:0 12px 32px rgba(0,0,0,.08);overflow:hidden}
.ac-hd{padding:8px 16px;background:#f9f9f9;border-bottom:1px solid rgba(0,0,0,.06);font-size:9px;font-weight:800;color:#888;text-transform:uppercase;letter-spacing:.1em}
.ac-it{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-family:"JetBrains Mono",monospace;font-size:13px;color:#222;border-bottom:1px solid rgba(0,0,0,.03)}
.ac-it.sel{background:linear-gradient(90deg,rgba(137,24,24,.06),transparent)}
.ac-cat{font-family:"Inter",sans-serif;font-size:9px;padding:2px 8px;border-radius:4px;font-weight:700}
.ck{background:rgba(137,24,24,.08);color:var(--red)}
.cc2{background:rgba(26,79,196,.08);color:var(--blue)}

.ast-row{display:flex;align-items:center;gap:10px;font-family:"JetBrains Mono",monospace;font-size:12px;color:#555;padding:6px 10px;border-radius:6px}
.ast-row.hi{background:rgba(137,24,24,.06);color:var(--red);font-weight:700}
.ast-tag{font-size:9px;padding:2px 6px;border-radius:4px;font-weight:800;font-family:"Inter",sans-serif;background:#eee;color:#888}
.ast-lg{font-size:9px;color:var(--green);font-weight:800;font-family:"Inter",sans-serif;margin-left:auto}
</style>
</head>
<body>

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

<header class="hero">
  <div class="hero-tag"><?php esc_html_e($is_vi ? 'Khám phá công nghệ' : 'Technology Deep Dive', 'hoasen-theme'); ?></div>
  <h1><?php esc_html_e($is_vi ? 'Bên dưới lớp vỏ Autocomplete' : 'Under the Hood of Autocomplete', 'hoasen-theme'); ?></h1>
  <p><?php esc_html_e($is_vi ? 'Không chỉ là gợi ý chuỗi đơn thuần. HoaSen Table tích hợp một parser SQL thời gian thực để xây dựng AST, đảm bảo mỗi gợi ý đều chuẩn xác về mặt cú pháp ngữ pháp.' : 'Not just fuzzy string matching. HoaSen Table integrates a real-time SQL parser to build an AST, ensuring every suggestion is syntactically perfect.', 'hoasen-theme'); ?></p>
</header>

<main class="demo-wrap">
  <div class="app-window">
    <div class="app-left">
      <div class="lbl"><?php esc_html_e('Editor', 'hoasen-theme'); ?></div>
      <div class="ed">
        <span class="kw">SELECT</span> u.id, u.name<br>
        <span class="kw">FROM</span> <span class="tbl">users</span> u<br>
        <span class="kw">WHERE</span> u.status = <span class="num">1</span><br>
        <span class="kw">ORDER</span> <span style="display:inline-block;width:2px;height:1.2em;background:var(--red);vertical-align:bottom;animation:blink .7s infinite"></span>
      </div>
      <div class="ac-pop">
        <div class="ac-hd"><?php esc_html_e('Legal Completions', 'hoasen-theme'); ?></div>
        <div class="ac-it sel"><span>BY</span><span class="ac-cat ck">keyword</span></div>
      </div>
    </div>
    <div class="app-right">
      <div class="lbl"><?php esc_html_e('Live AST Tree', 'hoasen-theme'); ?></div>
      <div class="ast-row"><div style="width:0"></div><span>SelectStatement</span><span class="ast-tag">stmt</span><span class="ast-lg">✓</span></div>
      <div class="ast-row"><div style="width:16px"></div><span>SELECT u.id, u.name</span><span class="ast-tag">proj</span><span class="ast-lg">✓</span></div>
      <div class="ast-row"><div style="width:16px"></div><span>FROM users u</span><span class="ast-tag">from</span><span class="ast-lg">✓</span></div>
      <div class="ast-row"><div style="width:16px"></div><span>WHERE u.status = 1</span><span class="ast-tag">where</span><span class="ast-lg">✓</span></div>
      <div class="ast-row hi"><div style="width:16px"></div><span>ORDER</span><span class="ast-tag">partial</span></div>
      <div class="ast-row"><div style="width:32px"></div><span>→ expecting: BY</span><span class="ast-tag">hint</span></div>
    </div>
  </div>
</main>

<?php wp_footer(); ?>
<style>@keyframes blink{50%{opacity:0}}</style>
</body>
</html>
