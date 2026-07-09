<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$is_vi = (strpos($lang,'vi')===0);
$t = [
  'title'   => $is_vi ? 'HoaSen Table — SQL Client tốc độ ánh sáng' : 'HoaSen Table — Blazing Fast SQL Client',
  'desc'    => $is_vi ? 'Truy vấn SQL cực nhanh: autocomplete ngữ pháp AST, join thông minh FK, hover quan hệ inline, virtual grid 1 triệu dòng, plugin manager hot-reload.' : 'Query databases at lightning speed: AST grammar autocomplete, FK smart joins, inline relation hover, 1M-row virtual grid, hot-reload plugin manager.',
  'blog'    => $is_vi ? 'BLOG' : 'BLOG',
  'contact' => $is_vi ? 'LIÊN HỆ' : 'CONTACT',
  'scroll'  => $is_vi ? 'Cuộn khám phá ↓' : 'Scroll to explore ↓',
  'inst'    => $is_vi ? 'Đã cài' : 'Installed',
  'install' => $is_vi ? 'Cài đặt' : 'Install',
  'plugins' => 'PLUGINS',
  'learn'   => $is_vi ? 'Chi tiết Autocomplete →' : 'Deep dive: Autocomplete →',
  'loaded'  => $is_vi ? '✓ đã tải · 50 hàng' : '✓ loaded · 50 rows',
  'active'  => $is_vi ? 'hoạt động' : 'active',
  'viewrow' => $is_vi ? 'Đang xem hàng' : 'Viewing row',
  'of'      => $is_vi ? 'trong' : 'of',
];
?>
<link rel="icon" type="image/png" href="<?php echo get_stylesheet_directory_uri(); ?>/logo.png"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title><?php echo esc_html($t['title']); ?></title>
<meta name="description" content="<?php echo esc_attr($t['desc']); ?>"/>
<meta name="robots" content="index,follow"/>
<meta property="og:title" content="<?php echo esc_attr($t['title']); ?>"/>
<meta property="og:description" content="<?php echo esc_attr($t['desc']); ?>"/>
<?php if(function_exists('pll_the_languages')): ?>
<link rel="alternate" hreflang="vi" href="<?php echo esc_url(pll_home_url('vi')); ?>"/>
<link rel="alternate" hreflang="en" href="<?php echo esc_url(pll_home_url('en')); ?>"/>
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(home_url('/')); ?>"/>
<?php endif; ?>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0efed;--red:#891818;--red2:#a82525;--green:#0f6b3e;--blue:#1a4fc4;--amber:#a16207;
  --text:#0f0f0f;--muted:#6b7280;
  --rs:rgba(137,24,24,.07);--rb:rgba(137,24,24,.18);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{height:740vh;overflow-x:hidden;background:var(--bg);color:var(--text);font-family:"Cormorant Garamond",Georgia,serif}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background:radial-gradient(circle at 14% 13%,rgba(137,24,24,.042),transparent 31%),
             radial-gradient(circle at 83% 21%,rgba(15,107,62,.03),transparent 29%),
             linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.bg-lotus{position:fixed;top:50%;left:65%;transform:translate(-50%,-50%);
  width:max(90vh,600px);height:max(90vh,600px);z-index:0;opacity:.035;pointer-events:none;
  background-image:url('<?php echo get_stylesheet_directory_uri(); ?>/logo.png');
  background-size:contain;background-repeat:no-repeat;background-position:center}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;
  background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(ellipse at 64% 52%,black,transparent 70%)}
.stage{position:fixed;inset:0;display:grid;grid-template-columns:.7fr 1.42fr;gap:48px;
  align-items:center;padding:52px;overflow:hidden}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.brand-tag{font-family:"Inter",sans-serif;font-size:8px;letter-spacing:.2em;text-transform:uppercase;color:var(--red);font-weight:700;margin-top:1px}
.topbar{position:absolute;top:22px;right:32px;z-index:100;display:flex;align-items:center;gap:9px}
.tb-chip{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:999px;
  background:rgba(137,24,24,.06);border:1px solid rgba(137,24,24,.14);
  font-family:"Inter",sans-serif;font-size:10px;font-weight:700;color:var(--red);
  cursor:pointer;transition:all .18s;letter-spacing:.04em}
.tb-chip:hover{background:rgba(137,24,24,.13)}
.tb-dot{width:5px;height:5px;border-radius:50%;background:var(--red)}
.lang-sw{font-family:"Inter",sans-serif;font-size:10px;font-weight:600}
.lang-sw ul{list-style:none;display:flex;gap:5px}
.lang-sw a{text-decoration:none;color:#aaa;padding:3px 6px;border-radius:4px;transition:color .15s}
.lang-sw .current-lang a{color:var(--red);font-weight:900;pointer-events:none}
.menu-wrap{position:relative}
.menu-btn{background:transparent;border:none;cursor:pointer;color:var(--text);padding:7px;border-radius:6px;transition:background .18s;display:flex;align-items:center}
.menu-btn:hover{background:rgba(0,0,0,.06)}
.menu-dd{position:absolute;right:0;top:calc(100% + 8px);min-width:138px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:10px;box-shadow:0 12px 36px rgba(0,0,0,.1);display:none;z-index:200;overflow:hidden;font-family:"Inter",sans-serif}
.menu-dd.show{display:block}
.menu-dd a{display:block;padding:10px 15px;color:#333;text-decoration:none;font-size:11px;font-weight:700;letter-spacing:.05em;transition:background .14s}
.menu-dd a:hover{background:var(--rs);color:var(--red)}

/* Progress bar moved to bottom center */
.progress{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);z-index:100;display:flex;gap:7px}
.bar{width:26px;height:3px;border-radius:99px;background:rgba(0,0,0,.08);overflow:hidden}
.bar span{display:block;width:0;height:100%;background:linear-gradient(90deg,var(--red),var(--green))}

.copy{position:relative;z-index:5;min-height:380px}
.copy-scene{position:absolute;left:0;right:0;top:50%;transform:translateY(-42%) translateY(24px);opacity:0;filter:blur(10px);pointer-events:none}
.copy-scene.active{opacity:1;filter:blur(0);transform:translateY(-42%);pointer-events:auto}
.kicker{font-size:10px;letter-spacing:.22em;text-transform:uppercase;color:var(--red);font-weight:900;margin-bottom:16px;font-family:"Inter",sans-serif}
h1{font-size:clamp(33px,4.6vw,66px);line-height:.93;letter-spacing:-.03em;font-weight:700;max-width:560px;color:#0f0f0f;text-wrap:balance}
.copy p{font-size:16px;line-height:1.6;color:#4b5563;margin-top:20px;max-width:400px;font-family:"Inter",sans-serif;text-wrap:pretty}
.chips{display:flex;gap:8px;margin-top:22px;flex-wrap:wrap;font-family:"Inter",sans-serif}
.chip{padding:5px 11px;border-radius:999px;border:1px solid rgba(0,0,0,.09);background:rgba(0,0,0,.03);font-size:10px;color:#374151;font-weight:700}
.chip.hot{background:linear-gradient(135deg,rgba(137,24,24,.92),rgba(168,37,37,.88));color:#fff;border:0;box-shadow:0 3px 12px rgba(137,24,24,.18)}
.chip.go{background:linear-gradient(135deg,rgba(15,107,62,.9),rgba(11,87,45,.88));color:#fff;border:0}
.learn-more{display:inline-flex;align-items:center;gap:6px;margin-top:20px;padding:8px 16px;border-radius:999px;border:1px solid rgba(137,24,24,.28);background:transparent;color:var(--red);font-family:"Inter",sans-serif;font-size:11px;font-weight:700;letter-spacing:.04em;cursor:pointer;text-decoration:none;transition:all .18s}
.learn-more:hover{background:var(--red);color:#fff}
.canvas{position:relative;z-index:3;height:min(76vh,780px);min-height:560px;width:100%;display:flex;align-items:center;justify-content:center}

.window{position:relative;width:min(100%,870px);height:100%;overflow:hidden;border-radius:18px;border:1px solid rgba(0,0,0,.11);background:#fdfdfc;box-shadow:0 42px 120px rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.9);display:flex;flex-direction:column}
.window::before{content:"";position:absolute;inset:-1px;background:radial-gradient(circle at 22% 0%,rgba(137,24,24,.03),transparent 28%);pointer-events:none;z-index:1}
.winbar{height:48px;display:flex;align-items:center;gap:10px;padding:0 18px;border-bottom:1px solid rgba(0,0,0,.07);background:rgba(0,0,0,.016);font-size:11px;color:#777;position:relative;z-index:10;font-family:"JetBrains Mono",monospace;flex-shrink:0}
.traf{display:flex;gap:6px}
.traf i{width:10px;height:10px;border-radius:50%;display:block}
.r{background:#ff5f57}.y{background:#febc2e}.g{background:#28c840}
.wpath{flex:1;margin-left:8px}.wpath strong{color:#111}
.wb-tags{display:flex;gap:5px;margin-left:auto;align-items:center}
.wb-tag{padding:2px 8px;border-radius:999px;background:rgba(137,24,24,.07);border:1px solid rgba(137,24,24,.12);font-size:8px;font-weight:700;color:var(--red);font-family:"Inter",sans-serif;letter-spacing:.05em}
.wb-tag.on{background:linear-gradient(135deg,var(--red),#a82525);color:#fff;border-color:transparent}

/* Standard Client Workspace Layout */
.sc-container{position:relative;flex:1;width:100%;height:100%;overflow:hidden}
.sc{position:absolute;inset:0;transition:opacity .28s,visibility .28s;opacity:0;visibility:hidden;pointer-events:none;background:#fafaf8}
.sc.show{opacity:1;visibility:visible;pointer-events:auto}

/* Workspace Frame (Scenes 1-6) */
.workspace-frame{display:grid;grid-template-columns:140px 1fr 180px;height:100%;width:100%;background:#fafaf8;overflow:hidden}
.w-left{border-right:1px solid rgba(0,0,0,.07);background:#fafaf8;padding:12px;display:flex;flex-direction:column;gap:8px}
.w-middle{display:flex;flex-direction:column;height:100%;overflow:hidden}
.w-right{border-left:1px solid rgba(0,0,0,.07);background:#fafaf8;padding:12px;display:flex;flex-direction:column;gap:12px}

/* Left panel styles */
.wl-hd{font-family:"Inter",sans-serif;font-size:8px;font-weight:900;letter-spacing:.1em;color:#aaa;text-transform:uppercase;margin-bottom:4px}
.wl-item{font-family:"Inter",sans-serif;font-size:11px;color:#555;padding:6px 10px;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:all .15s}
.wl-item:hover{background:rgba(0,0,0,.03)}
.wl-item.active{background:var(--rs);color:var(--red);font-weight:700}
.wl-badge{font-size:8px;padding:1px 5px;border-radius:99px;background:rgba(0,0,0,.04);color:#888}

/* Middle panel splitter parts */
.w-mid-top{padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.05);background:#fff;flex-shrink:0}
.w-mid-bottom{flex:1;overflow:hidden;position:relative;display:flex;flex-direction:column;background:#fff}

/* Right panel Widget Manager */
.wr-title{font-family:"Inter",sans-serif;font-size:8px;font-weight:900;letter-spacing:.12em;color:#aaa;text-transform:uppercase;margin-bottom:2px}
.widget-box{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:6px;box-shadow:0 2px 6px rgba(0,0,0,.02)}
.wb-hd{font-family:"Inter",sans-serif;font-size:9px;font-weight:800;color:#6b7280;border-bottom:1px solid rgba(0,0,0,.04);padding-bottom:4px;text-transform:uppercase;letter-spacing:.05em}
.wb-list{display:flex;flex-direction:column;gap:5px}
.wb-row{display:flex;justify-content:space-between;align-items:center;font-family:"JetBrains Mono",monospace;font-size:9px;color:#555}

/* Active Highlight Styling */
.highlight-box{box-shadow:0 0 0 2px rgba(137,24,24,.35), 0 0 16px rgba(137,24,24,.18) !important}
.highlight-box-green{box-shadow:0 0 0 2px rgba(15,107,62,.35), 0 0 16px rgba(15,107,62,.18) !important}

/* Scene 0: Connection Board (Full frame) */
#s0{padding:28px;background:#fafaf8;display:grid;grid-template-columns:1fr 1fr;gap:18px}
.cg{border:1px solid rgba(0,0,0,.07);border-radius:14px;background:#fff;padding:16px;display:flex;flex-direction:column;gap:10px}
.cg.dev{border-top:3px solid var(--green)}.cg.prod{border-top:3px solid var(--red)}
.cg-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.cg-n{font-family:"Inter",sans-serif;font-size:9px;font-weight:900;letter-spacing:.08em;color:#888;text-transform:uppercase}
.cg-b{font-size:8px;padding:2px 7px;border-radius:99px;font-weight:700;font-family:"Inter",sans-serif}
.cg-b.dev{background:rgba(15,107,62,.1);color:var(--green)}.cg-b.prod{background:rgba(137,24,24,.1);color:var(--red)}
.cc{display:flex;align-items:center;gap:11px;padding:9px 12px;border:1px solid rgba(0,0,0,.06);border-radius:9px;cursor:pointer;transition:all .18s;font-family:"Inter",sans-serif;position:relative}
.cc:hover,.cc.on{background:rgba(0,0,0,.02);border-color:rgba(0,0,0,.12)}
.cc.on{box-shadow:inset 0 0 0 1.5px rgba(137,24,24,.2)}
.dbi{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-family:"JetBrains Mono",monospace;font-size:10px;font-weight:900;color:#fff;flex-shrink:0}
.dbi.pg{background:#336791}.dbi.my{background:#00758f}.dbi.sq{background:#003b57}
.cc-n{font-size:12px;font-weight:700;color:#222}.cc-h{font-size:9px;color:#aaa;font-family:"JetBrains Mono",monospace;margin-top:1px}
.cc-p{font-size:9px;font-weight:700;color:var(--green);background:rgba(15,107,62,.08);padding:2px 6px;border-radius:99px;font-family:"Inter",sans-serif;margin-left:auto}

/* Dynamic workspace inner wrappers */
.feat-sc{display:none;width:100%;height:100%;overflow:hidden}
.feat-sc.show{display:flex;flex-direction:column}

/* Scene 1: Autocomplete */
#s1{display:grid;grid-template-columns:1fr 1fr;height:100%}
.ast-left{background:#fafaf8;border-right:1px solid rgba(0,0,0,.07);padding:14px;display:flex;flex-direction:column;gap:0;overflow:hidden}
.ed-lbl{font-family:"Inter",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.ed{font-family:"JetBrains Mono",monospace;font-size:13px;line-height:1.7;color:#222}
.kw{color:var(--red);font-weight:700}.tbl{color:var(--blue)}.col{color:var(--green)}.num{color:var(--amber)}.cm{color:#bbb}
.caret{display:inline-block;width:2px;height:1.1em;background:var(--red);vertical-align:-.18em;margin-left:1px;animation:blink .7s steps(1,end) infinite}
@keyframes blink{50%{opacity:0}}
.ac-pop{margin-top:10px;border:1px solid var(--rb);border-radius:9px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.07);overflow:hidden;opacity:0;transform:translateY(-5px) scale(.97);transition:.2s cubic-bezier(.16,1,.3,1)}
.ac-pop.show{opacity:1;transform:none}
.ac-hd{padding:4px 10px;background:rgba(0,0,0,.018);border-bottom:1px solid rgba(0,0,0,.05);font-family:"Inter",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em}
.ac-it{display:flex;justify-content:space-between;align-items:center;padding:7px 11px;font-family:"JetBrains Mono",monospace;font-size:11px;color:#222}
.ac-it.sel{background:linear-gradient(90deg,rgba(137,24,24,.06),transparent)}
.ac-cat{font-family:"Inter",sans-serif;font-size:8px;padding:1px 6px;border-radius:3px;font-weight:700}
.ck{background:rgba(137,24,24,.08);color:var(--red)}.ct{background:rgba(15,107,62,.08);color:var(--green)}.cc2{background:rgba(26,79,196,.08);color:var(--blue)}
.ast-right{padding:14px;display:flex;flex-direction:column;overflow:hidden}
.ast-ttl{font-family:"Inter",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}
.ast-row{display:flex;align-items:center;gap:6px;font-family:"JetBrains Mono",monospace;font-size:10px;color:#555;padding:3px 6px;border-radius:5px;transition:background .15s}
.ast-row.hi{background:rgba(137,24,24,.06);color:var(--red);font-weight:700}
.ast-row.ok{color:var(--green)}
.ast-tag{font-size:8px;padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Inter",sans-serif;background:rgba(0,0,0,.05);color:#888}
.ast-lg{font-size:8px;color:var(--green);font-weight:700;font-family:"Inter",sans-serif}
.ast-il{font-size:8px;color:#ef4444;font-weight:700;font-family:"Inter",sans-serif}

/* Scene 2: FK Join Diagram */
.fk-dia{flex:1;padding:16px 20px;display:flex;align-items:center;justify-content:center;gap:0}
.fk-tbl{border:1px solid rgba(0,0,0,.1);border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);min-width:128px}
.fk-th{padding:7px 12px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-family:"Inter",sans-serif;font-size:10px;font-weight:900;color:#555;text-transform:uppercase;letter-spacing:.06em}
.fk-r{padding:5px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;color:#333;border-bottom:1px solid rgba(0,0,0,.04);display:flex;align-items:center;gap:6px}
.fk-r:last-child{border-bottom:0}
.pk{font-size:8px;background:rgba(137,24,24,.1);color:var(--red);padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Inter",sans-serif}
.fk{font-size:8px;background:rgba(15,107,62,.1);color:var(--green);padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Inter",sans-serif}
.fk-arr{display:flex;flex-direction:column;align-items:center;padding:0 14px;gap:4px}
.fk-ln{width:56px;height:2px;background:linear-gradient(90deg,var(--green),rgba(15,107,62,.2));position:relative;transition:all .3s}
.fk-ln::after{content:"▶";position:absolute;right:-8px;top:-7px;font-size:11px;color:var(--green)}
.fk-snip{margin:0 20px 16px;border-radius:8px;background:rgba(15,107,62,.04);border:1px solid rgba(15,107,62,.14);padding:10px 14px;font-family:"JetBrains Mono",monospace;font-size:12px;opacity:0;transform:translateY(8px);transition:.24s}
.fk-snip.show{opacity:1;transform:none}

/* Scene 3: Speed comparison */
#s3{display:grid;grid-template-columns:1fr 1fr;background:#fafaf8;height:100%}
.sp-l{border-right:1px solid rgba(0,0,0,.07);padding:24px;display:flex;flex-direction:column;justify-content:center;gap:18px}
.sp-stat{display:flex;flex-direction:column;gap:2px}
.sp-num{font-family:"JetBrains Mono",monospace;font-size:clamp(22px,2.8vw,36px);font-weight:900;color:#0f0f0f;letter-spacing:-.03em;line-height:1}
.sp-unit{font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.sp-desc{font-family:"Inter",sans-serif;font-size:9px;color:#bbb;margin-top:1px}
.sp-bar{height:3px;border-radius:99px;background:rgba(0,0,0,.06);margin-top:4px;overflow:hidden}
.sp-fill{height:100%;border-radius:99px;width:0;transition:width .6s ease}
.sp-r{padding:20px;display:flex;flex-direction:column;gap:12px}
.cmp-card{border:1px solid rgba(0,0,0,.07);border-radius:10px;padding:12px 14px;background:#fff;display:flex;flex-direction:column;gap:8px}
.cmp-lbl{font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px}
.cmp-row{display:flex;align-items:center;gap:8px}
.cmp-name{font-family:"Inter",sans-serif;font-size:10px;color:#555;font-weight:600;min-width:76px}
.cmp-bw{flex:1;height:6px;background:rgba(0,0,0,.06);border-radius:99px;overflow:hidden}
.cmp-bf{height:100%;border-radius:99px;transition:width .5s ease}
.cmp-bf.hs{background:linear-gradient(90deg,var(--red),#a82525)}.cmp-bf.el{background:rgba(0,0,0,.15)}
.cmp-v{font-family:"JetBrains Mono",monospace;font-size:10px;color:#555;min-width:36px;text-align:right}

/* Scene 4: Hover Inspect */
.dt{height:32px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;border-bottom:1px solid rgba(0,0,0,.05);font-size:10px;color:#888;font-family:"Inter",sans-serif;font-weight:600;flex-shrink:0}
.dt-ok{color:var(--green);font-weight:700}
.tg{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#2a2a2a}
.tg th{padding:6px 12px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-size:9px;font-weight:700;color:#777;text-align:left;white-space:nowrap}
.tg td{padding:6px 12px;border-bottom:1px solid rgba(0,0,0,.04);white-space:nowrap}
.fk-c{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:4px;background:rgba(137,24,24,.05);box-shadow:inset 0 0 0 1px rgba(137,24,24,.12);color:var(--red);font-weight:700;cursor:pointer;position:relative;transition:all .15s}
.fk-c.hl,.fk-c:hover{background:rgba(137,24,24,.12);box-shadow:inset 0 0 0 1px rgba(137,24,24,.3)}
.fk-pop{position:absolute;z-index:30;min-width:170px;border-radius:10px;border:1px solid rgba(15,107,62,.22);background:#fff;box-shadow:0 10px 32px rgba(0,0,0,.1);padding:10px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;opacity:0;transform:translateY(-4px) scale(.96);transition:.18s cubic-bezier(.16,1,.3,1);visibility:hidden;pointer-events:none}
.fk-pop.show{opacity:1;transform:none;visibility:visible}
.fkp-t{font-family:"Inter",sans-serif;font-size:8px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;display:flex;align-items:center;gap:4px}
.fkp-r{display:flex;justify-content:space-between;gap:10px;color:#444;padding:1.5px 0}
.fkp-r span:first-child{color:#bbb}
.fkp-p{margin-top:5px;padding-top:4px;border-top:1px solid rgba(0,0,0,.05);font-size:9px;color:#bbb;font-family:"Inter",sans-serif}
.ok-g{color:var(--green);font-weight:700}.ok-a{color:var(--amber)}.ok-r{color:#ef4444}

/* Scene 5: Virtual Grid */
#s5{display:flex;flex-direction:column;height:100%}
.vg-hd{border-bottom:1px solid rgba(0,0,0,.07);padding:10px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
.vg-ctr{font-family:"JetBrains Mono",monospace;font-size:11px;color:#555}
.vg-ctr strong{color:var(--green);font-size:13px}
.vg-badge{padding:3px 9px;border-radius:999px;background:rgba(15,107,62,.08);border:1px solid rgba(15,107,62,.15);font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:var(--green)}
.vg-grid{flex:1;overflow:hidden;position:relative}
.vg-t{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#2a2a2a}
.vg-t th{padding:5px 10px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-size:9px;font-weight:700;color:#888;text-align:left}
.vg-t td{padding:5px 10px;border-bottom:1px solid rgba(0,0,0,.04);white-space:nowrap}
.vg-thumb{position:absolute;right:5px;top:0;width:3px;border-radius:99px;background:linear-gradient(var(--red),#a82525);box-shadow:0 0 6px rgba(137,24,24,.3)}
.vg-m{position:absolute;bottom:14px;right:14px;background:rgba(255,255,255,.95);border:1px solid rgba(15,107,62,.18);border-radius:8px;padding:8px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;display:flex;flex-direction:column;gap:3px;backdrop-filter:blur(6px);box-shadow:0 4px 16px rgba(0,0,0,.08);transition:all .3s}
.vm-r{display:flex;justify-content:space-between;gap:14px}
.vm-r span:first-child{color:#bbb}.vm-r span:last-child{color:var(--green);font-weight:700}

/* Scene 6: Plugin Manager (Mock workspace integration) */
#s6{display:grid;grid-template-columns:1fr 1fr;height:100%;background:#fafaf8}
.pm-l{border-right:1px solid rgba(0,0,0,.07);padding:14px;overflow-y:auto}
.pm-hd{font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px}
.pm-lst{display:flex;flex-direction:column;gap:8px}
.pm-it{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;transition:all .18s;font-family:"Inter",sans-serif}
.pm-it.on{border-color:rgba(15,107,62,.2);background:rgba(15,107,62,.02)}
.pm-it:hover{border-color:rgba(0,0,0,.12)}
.pm-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;background:rgba(0,0,0,.04);flex-shrink:0}
.pm-inf{flex:1}.pm-nm{font-size:11px;font-weight:700;color:#222}.pm-ds{font-size:9px;color:#aaa;margin-top:1px}
.pm-tg{width:28px;height:15px;border-radius:99px;border:none;cursor:pointer;position:relative;transition:background .18s;flex-shrink:0}
.pm-tg.on{background:var(--green)}.pm-tg.off{background:#ddd}
.pm-tg::after{content:"";position:absolute;top:2px;width:11px;height:11px;border-radius:50%;background:#fff;transition:left .18s}
.pm-tg.on::after{left:15px}.pm-tg.off::after{left:2px}
.pm-st{font-size:8px;padding:2px 6px;border-radius:999px;font-weight:700;font-family:"Inter",sans-serif}
.pm-st.on{background:rgba(15,107,62,.1);color:var(--green)}.pm-st.off{background:rgba(0,0,0,.05);color:#bbb}
.pm-r{padding:14px;display:flex;flex-direction:column;gap:10px;overflow-y:auto}
.pm-sc{border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;padding:14px;display:flex;flex-direction:column;gap:6px}
.pm-sl{font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em}
.pm-sv{font-family:"JetBrains Mono",monospace;font-size:22px;font-weight:900;color:#0f0f0f;line-height:1}
.pm-ss{font-family:"Inter",sans-serif;font-size:9px;color:var(--green);font-weight:600}
.pm-ac{border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;padding:12px}
.pm-at{font-family:"Inter",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}
.pm-ar{display:flex;align-items:center;gap:8px;padding:4px 0;font-family:"JetBrains Mono",monospace;font-size:10px;color:#555}
.pm-ad{width:6px;height:6px;border-radius:50%;flex-shrink:0}

/* Mouse */
.mouse{position:fixed;z-index:9000;pointer-events:none;left:0;top:0}
.mouse-s{transition:transform .11s cubic-bezier(.1,.8,.2,1);transform-origin:0 0;filter:drop-shadow(0 5px 9px rgba(0,0,0,.13))}
.mouse.click .mouse-s{transform:scale(.82)}
.click-rip{position:fixed;border:2px solid rgba(137,24,24,.7);border-radius:50%;pointer-events:none;z-index:8999;transform:translate(-50%,-50%);animation:rip-out .36s ease-out forwards}
@keyframes rip-out{from{width:0;height:0;opacity:1}to{width:34px;height:34px;opacity:0}}
.scroll-note{position:absolute;bottom:42px;left:50%;transform:translateX(-50%);z-index:50;color:#bbb;font-size:10px;letter-spacing:.12em;font-family:"Inter",sans-serif;font-weight:700;text-transform:uppercase}

/* Modals */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.38);backdrop-filter:blur(10px);z-index:300;display:none;align-items:center;justify-content:center;font-family:"Inter",sans-serif}
.ov.show{display:flex}
.mc{background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:18px;box-shadow:0 40px 100px rgba(0,0,0,.2);width:min(92vw,680px);max-height:86vh;display:flex;flex-direction:column;position:relative;overflow:hidden}
.mc.mini{width:min(92vw,420px)}
.xbtn{position:absolute;top:13px;right:15px;background:transparent;border:none;font-size:22px;cursor:pointer;color:#ccc;transition:color .15s;z-index:10}
.xbtn:hover{color:#111}
.mhd{padding:18px 22px;border-bottom:1px solid rgba(0,0,0,.07);font-size:16px;font-weight:800;color:#111;display:flex;align-items:center;gap:9px}
.mhdi{width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,var(--red),#a82525);display:flex;align-items:center;justify-content:center}

.cc-cnt{padding:24px}
.cc-cnt p{font-size:13px;color:#555;line-height:1.6;margin-bottom:20px}
.cc-lnks{display:flex;flex-direction:column;gap:10px}
.cc-itm{display:flex;align-items:center;gap:11px;padding:12px 16px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.01);text-decoration:none;color:#333;font-family:"JetBrains Mono",monospace;font-size:11px;transition:all .18s}
.cc-itm.fb{color:#1877f2}.cc-itm:hover{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.14)}
.pm-body{padding:18px 22px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:12px}
.pm-srch{width:100%;border:1px solid rgba(0,0,0,.11);border-radius:7px;padding:8px 13px;font-family:"JetBrains Mono",monospace;font-size:11px;outline:none;transition:border .18s;background:#fafafa}
.pm-srch:focus{border-color:rgba(137,24,24,.32)}
.pm-mi{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.07);border-radius:11px;background:#fafafa;transition:all .18s}
.pm-mi:hover{border-color:rgba(137,24,24,.18);background:#fff;box-shadow:0 3px 12px rgba(0,0,0,.04)}
.pm-mico{width:36px;height:36px;border-radius:99px;display:flex;align-items:center;justify-content:center;font-size:17px;background:rgba(0,0,0,.04);flex-shrink:0}
.pm-min{flex:1}.pm-mnm{font-size:12px;font-weight:700;color:#111;margin-bottom:2px}.pm-mds{font-size:10px;color:#999;line-height:1.35}
.pm-mbg{font-size:8px;padding:2px 7px;border-radius:999px;font-weight:700;white-space:nowrap}
.pm-mbg.inst{background:rgba(15,107,62,.1);color:var(--green)}.pm-mbg.pop{background:rgba(137,24,24,.1);color:var(--red)}
.pm-ib{padding:6px 13px;border-radius:999px;border:1px solid rgba(137,24,24,.28);background:transparent;color:var(--red);font-family:"Inter",sans-serif;font-size:10px;font-weight:700;cursor:pointer;transition:all .18s;white-space:nowrap}
.pm-ib:hover,.pm-ib.done{background:linear-gradient(135deg,var(--red),#a82525);color:#fff;border-color:transparent}

@media(max-width:980px){
  body{height:680vh}
  .stage{grid-template-columns:1fr;grid-template-rows:auto 1fr;padding:66px 13px 26px;gap:10px}
  .brand{top:15px;left:15px}.topbar{top:15px;right:13px}
  .progress{bottom:12px}.copy{min-height:110px}
  .copy p,.chips{display:none}
  h1{font-size:33px}.canvas{min-height:500px;height:60vh}.mouse{display:none}
  .workspace-frame{grid-template-columns:1fr}
  .w-left,.w-right{display:none}
}
</style>
</head>
<body>

<!-- Background lotus watermark -->
<svg class="bg-lotus" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <linearGradient id="bgl" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#fca5a5"/><stop offset="50%" stop-color="#f43f5e"/><stop offset="100%" stop-color="#be123c"/>
    </linearGradient>
    <mask id="bgm">
      <path d="M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z" fill="white" />
      <path d="M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z" fill="white" />
      <path d="M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z" fill="white" />
      <path d="M22 50 C 5 50, 0 65, 25 80 C 35 85, 45 88, 50 90 C 25 78, 15 65, 22 50 Z" fill="white" />
      <path d="M78 50 C 95 50, 100 65, 75 80 C 65 85, 55 88, 50 90 C 75 78, 85 65, 78 50 Z" fill="white" />
    </mask>
  </defs>
  <rect width="100" height="100" fill="url(#bgl)" mask="url(#bgm)"/>
  <g stroke="#e8e5df" stroke-width="2" mask="url(#bgm)">
    <line x1="0" y1="31" x2="100" y2="31"/><line x1="0" y1="38" x2="100" y2="38"/><line x1="0" y1="45" x2="100" y2="45"/><line x1="0" y1="52" x2="100" y2="52"/><line x1="0" y1="59" x2="100" y2="59"/><line x1="0" y1="66" x2="100" y2="66"/><line x1="0" y1="73" x2="100" y2="73"/><line x1="0" y1="80" x2="100" y2="80"/>
    <path d="M38 10 Q45 50 48 90" fill="none"/><path d="M50 0 V100" fill="none"/><path d="M62 10 Q55 50 52 90" fill="none"/>
    <line x1="28" y1="20" x2="28" y2="90"/><line x1="72" y1="20" x2="72" y2="90"/>
  </g>
</svg>

<div class="stage">
  <div class="dot-grid"></div>

  <!-- Brand -->
  <div class="brand">
    <img src="<?php echo get_stylesheet_directory_uri(); ?>/logo.png" width="32" height="32" alt="HoaSen Table logo" style="filter:drop-shadow(0 3px 10px rgba(185,28,28,.28))"/>
    <div>
      <div class="brand-name">HoaSen Table</div>
      <div class="brand-tag"><?php esc_html_e('Blazing Fast SQL', 'hoasen-theme'); ?></div>
    </div>
  </div>

  <!-- Top right -->
  <div class="topbar">
    <?php if(function_exists('pll_the_languages')): ?>
    <div class="lang-sw"><ul><?php pll_the_languages(['show_flags'=>0,'show_names'=>1,'hide_current'=>0]); ?></ul></div>
    <?php endif; ?>
    <button class="tb-chip" id="btnPlugins"><span class="tb-dot"></span><?php echo esc_html($t['plugins']); ?></button>
    <div class="menu-wrap">
      <button class="menu-btn" id="menuBtn" aria-label="Menu">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="menu-dd" id="menuDd">
        <a href="<?php echo esc_url(home_url('/blog/')); ?>"><?php echo esc_html($t['blog']); ?></a>
        <a href="#" id="btnContact"><?php echo esc_html($t['contact']); ?></a>
      </div>
    </div>
  </div>

  <div class="progress" id="progress"></div>

  <!-- Copy left -->
  <div class="copy">
    <section class="copy-scene active">
      <div class="kicker"><?php esc_html_e('01 / CONNECT', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Your databases, organized at a glance.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Group connections by environment. Color-coded Dev and Prod profiles — so you never run DROP on the wrong server.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Grouped Envs', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('PG · MySQL · SQLite', 'hoasen-theme'); ?></span>
        <span class="chip go">⚡ <?php esc_html_e('< 10ms ping', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('02 / AUTOCOMPLETE', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Not suggestions. Grammar-legal completions only.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('A live AST parser per dialect validates every completion. Only tokens syntactically legal at your cursor position appear — zero noise.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('AST-Driven', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('6 Dialects', 'hoasen-theme'); ?></span>
        <span class="chip go">⚡ <?php esc_html_e('0-lag', 'hoasen-theme'); ?></span>
      </div>
      <a href="<?php echo esc_url(home_url('/autocomplete/')); ?>" class="learn-more"><?php echo esc_html($t['learn']); ?></a>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('03 / SMART JOINS', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Type "join" — get the exact ON clause.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('The FK graph is always live from your schema. One keystroke fills any JOIN with the exact column references — no more guessing.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('FK Snippet Engine', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Live Schema Graph', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('04 / PERFORMANCE', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Blazing fast. No Electron. No excuses.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Native egui rendering, 3 MB binary, 78ms cold start, 18 MB idle RAM. Faster than most tools finish loading.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot">⚡ <?php esc_html_e('78ms start', 'hoasen-theme'); ?></span>
        <span class="chip go"><?php esc_html_e('3 MB binary', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('18 MB RAM', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('05 / HOVER INSPECT', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Foreign keys are portals, not numbers.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Hover any FK cell to instantly preview the linked record inline — name, status, plan — without writing sub-queries or switching tabs.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Inline Preview', 'hoasen-theme'); ?></span>
        <span class="chip go">⚡ <?php esc_html_e('Instant', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Zero sub-queries', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('06 / VIRTUAL GRID', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('1,000,000 rows. 0.4 ms render. 12 KB RAM.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Viewport virtualization keeps exactly 23 DOM nodes active. Scroll a million rows at 60 FPS — no pagination, no "Load More".', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('1M+ Rows', 'hoasen-theme'); ?></span>
        <span class="chip go">⚡ <?php esc_html_e('60 FPS', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('12 KB Memory', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('07 / PLUGIN MANAGER', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Extend everything. Break nothing.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Install community plugins in one click. Hot-reload without restart. Each plugin sandboxed — Dark Mode, AI Copilot, Redis Inspector, Chart Builder.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Hot Reload', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Sandboxed', 'hoasen-theme'); ?></span>
        <span class="chip go">⚡ <?php esc_html_e('1-click', 'hoasen-theme'); ?></span>
      </div>
    </section>
  </div>

  <!-- App window mockup -->
  <div class="canvas" id="canvas">
    <div class="window">
      <div class="winbar">
        <div class="traf"><i class="r"></i><i class="y"></i><i class="g"></i></div>
        <span class="wpath" id="winPath"><strong>HoaSen Table</strong> · Connection Board</span>
        <div class="wb-tags">
          <span class="wb-tag" id="wbDark">🌙 Dark</span>
          <span class="wb-tag on" id="wbAI">✦ AI</span>
          <span class="wb-tag" id="winBtnPlugins" style="cursor:pointer;background:rgba(137,24,24,.07)">🔌 Plugins</span>
        </div>
      </div>

      <div class="sc-container">
        <!-- Scene 0: Connection Board (Full screen connection cards) -->
        <div class="sc show" id="s0">
          <div style="padding:28px;background:#fafaf8;display:grid;grid-template-columns:1fr 1fr;gap:18px;height:100%;width:100%">
            <div class="cg dev">
              <div class="cg-hd"><span class="cg-n">Development</span><span class="cg-b dev">DEV</span></div>
              <div class="cc"><div class="dbi my">My</div><div><div class="cc-n">miraiai_dev</div><div class="cc-h">localhost:3306</div></div><span class="cc-p">4ms</span></div>
              <div class="cc"><div class="dbi sq">Sq</div><div><div class="cc-n">hoasentable_local</div><div class="cc-h">local.db</div></div><span class="cc-p">0ms</span></div>
            </div>
            <div class="cg prod">
              <div class="cg-hd"><span class="cg-n">Production</span><span class="cg-b prod">PROD</span></div>
              <div class="cc" id="cardA"><div class="dbi pg">Pg</div><div><div class="cc-n">aipbx_prod</div><div class="cc-h">10.0.0.4:5432</div></div><span class="cc-p">9ms</span></div>
              <div class="cc"><div class="dbi pg">Pg</div><div><div class="cc-n">payments_db</div><div class="cc-h">10.0.0.5:5432</div></div><span class="cc-p">11ms</span></div>
            </div>
          </div>
        </div>

        <!-- 3-Column Workspace Frame for Scenes 1-6 -->
        <div class="sc" id="workspaceLayout">
          <div class="workspace-frame">
            <!-- Left panel: entities -->
            <aside class="w-left">
              <div class="wl-hd">ENTITIES</div>
              <div class="wl-list">
                <div class="wl-item active" id="wlUsers">users <span class="wl-badge">1M</span></div>
                <div class="wl-item" id="wlOrders">orders <span class="wl-badge">50k</span></div>
                <div class="wl-item">payments</div>
                <div class="wl-item">products</div>
                <div class="wl-item">categories</div>
              </div>
            </aside>

            <!-- Middle panel: SQL Editor & Data view -->
            <main class="w-middle">
              <div class="w-mid-top">
                <div class="ed-lbl">SQL EDITOR</div>
                <div class="ed" id="sqlEd">SELECT * FROM users u;</div>
              </div>
              <div class="w-mid-bottom">
                <!-- S1: Autocomplete Feature -->
                <div class="feat-sc" id="featS1">
                  <div style="display:grid;grid-template-columns:1.2fr 1fr;height:100%">
                    <div class="ast-left">
                      <div class="ac-pop" id="acPop">
                        <div class="ac-hd"><?php esc_html_e('Legal completions at cursor', 'hoasen-theme'); ?></div>
                        <div class="ac-it sel"><span>FROM</span><span class="ac-cat ck">keyword</span></div>
                        <div class="ac-it"><span>FOR UPDATE</span><span class="ac-cat ck">keyword</span></div>
                        <div class="ac-it"><span>FETCH NEXT</span><span class="ac-cat ck">keyword</span></div>
                      </div>
                    </div>
                    <div class="ast-right">
                      <div class="ast-ttl"><?php esc_html_e('Live AST Tree', 'hoasen-theme'); ?></div>
                      <div id="astTree"></div>
                    </div>
                  </div>
                </div>

                <!-- S2: FK Join Snippet -->
                <div class="feat-sc" id="featS2">
                  <div class="fk-dia">
                    <div class="fk-tbl">
                      <div class="fk-th">users</div>
                      <div class="fk-r"><span class="pk">PK</span> id</div>
                      <div class="fk-r">name</div>
                    </div>
                    <div class="fk-arr">
                      <div class="fk-ln" id="fkLine"></div>
                    </div>
                    <div class="fk-tbl">
                      <div class="fk-th">orders</div>
                      <div class="fk-r"><span class="pk">PK</span> id</div>
                      <div class="fk-r"><span class="fk">FK</span> user_id</div>
                    </div>
                  </div>
                  <div class="fk-snip" id="fkSnip">
                    <div><span class="kw">JOIN</span> <span class="tbl">orders</span> o <span class="kw">ON</span> <span class="col">o.user_id</span>=<span class="col">u.id</span></div>
                  </div>
                </div>

                <!-- S3: Performance Benchmark stats -->
                <div class="feat-sc" id="featS3">
                  <div style="display:grid;grid-template-columns:1fr 1fr;height:100%">
                    <div class="sp-l">
                      <div class="sp-stat">
                        <div class="sp-num" id="spN1">—</div><div class="sp-unit">Cold Start</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB1" style="background:linear-gradient(90deg,var(--red),var(--green))"></div></div>
                      </div>
                      <div class="sp-stat">
                        <div class="sp-num" id="spN2">—</div><div class="sp-unit">Idle RAM</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB2" style="background:linear-gradient(90deg,var(--green),#0f6b3e)"></div></div>
                      </div>
                      <div class="sp-stat">
                        <div class="sp-num" id="spN3">—</div><div class="sp-unit">Binary Size</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB3" style="background:linear-gradient(90deg,var(--blue),var(--green))"></div></div>
                      </div>
                    </div>
                    <div class="sp-r">
                      <div class="cmp-card">
                        <div class="cmp-lbl">Cold start time</div>
                        <div class="cmp-row"><div class="cmp-name">HoaSen</div><div class="cmp-bw"><div class="cmp-bf hs" id="cB1" style="width:0"></div></div><div class="cmp-v">78ms</div></div>
                        <div class="cmp-row"><div class="cmp-name">TablePlus</div><div class="cmp-bw"><div class="cmp-bf el" style="width:40%"></div></div><div class="cmp-v">1.4s</div></div>
                      </div>
                      <div class="cmp-card">
                        <div class="cmp-lbl">RAM (idle)</div>
                        <div class="cmp-row"><div class="cmp-name">HoaSen</div><div class="cmp-bw"><div class="cmp-bf hs" id="cB2" style="width:0"></div></div><div class="cmp-v">18 MB</div></div>
                        <div class="cmp-row"><div class="cmp-name">TablePlus</div><div class="cmp-bw"><div class="cmp-bf el" style="width:38%"></div></div><div class="cmp-v">120 MB</div></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- S4: Hover FK Inspect -->
                <div class="feat-sc" id="featS4">
                  <div class="dt"><span>orders</span><span class="dt-ok"><?php echo esc_html($t['loaded']); ?></span></div>
                  <div style="flex:1;overflow:hidden;position:relative">
                    <table class="tg" id="s4Tbl">
                      <thead><tr><th>id</th><th>user_id</th><th>total</th><th>status</th></tr></thead>
                      <tbody id="s4Rows"></tbody>
                    </table>
                    <div class="fk-pop" id="fkPop">
                      <div class="fkp-t"><svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#0f6b3e"/></svg>users #42</div>
                      <div class="fkp-r"><span>name</span><span>Nguyễn Lâm</span></div>
                      <div class="fkp-r"><span>email</span><span>lam@example.com</span></div>
                      <div class="fkp-r"><span>status</span><span class="ok-g"><?php echo esc_html($t['active']); ?></span></div>
                      <div class="fkp-p">orders.user_id ➔ users.id</div>
                    </div>
                  </div>
                </div>

                <!-- S5: Virtual Grid 1M rows -->
                <div class="feat-sc" id="featS5">
                  <div class="vg-hd">
                    <div class="vg-ctr"><?php echo esc_html($t['viewrow']); ?> <strong id="vgS">1</strong>–<span id="vgE">23</span> <?php echo esc_html($t['of']); ?> <span>1,000,000</span></div>
                    <span class="vg-badge">23 DOM nodes · 60 FPS</span>
                  </div>
                  <div class="vg-grid">
                    <table class="vg-t" id="vgT">
                      <thead><tr><th>#</th><th>id</th><th>user_id</th><th>total</th><th>status</th></tr></thead>
                      <tbody id="vgR"></tbody>
                    </table>
                    <div class="vg-thumb" id="vgTh" style="height:20px;top:0"></div>
                    <div class="vg-m" id="vgMetricsBox">
                      <div class="vm-r"><span>render/frame</span><span>0.4ms</span></div>
                      <div class="vm-r"><span>DOM nodes</span><span>23</span></div>
                      <div class="vm-r"><span>RAM usage</span><span>12 KB</span></div>
                    </div>
                  </div>
                </div>

                <!-- S6: Plugin Manager (occupies middle panel) -->
                <div class="feat-sc" id="featS6" style="background:#fafaf8;height:100%">
                  <div style="display:grid;grid-template-columns:1.1fr 0.9fr;height:100%">
                    <div class="pm-l">
                      <div class="pm-hd">Installed Plugins</div>
                      <div class="pm-lst">
                        <div class="pm-it on"><div class="pm-ico">🌙</div><div class="pm-inf"><div class="pm-nm">Dark Mode Pro</div><div class="pm-ds">OLED-optimized theme</div></div><span class="pm-st on">ON</span><button class="pm-tg on"></button></div>
                        <div class="pm-it on" id="pmAicop"><div class="pm-ico">✦</div><div class="pm-inf"><div class="pm-nm">AI Copilot</div><div class="pm-ds">NL → SQL, query explain</div></div><span class="pm-st on">ON</span><button class="pm-tg on"></button></div>
                        <div class="pm-it" id="pmRed"><div class="pm-ico">🔴</div><div class="pm-inf"><div class="pm-nm">Redis Inspector</div><div class="pm-ds">Keys, TTL, cluster</div></div><span class="pm-st off" id="redSt">OFF</span><button class="pm-tg off" id="redTg"></button></div>
                      </div>
                    </div>
                    <div class="pm-r">
                      <div class="pm-sc">
                        <div class="pm-sl">Active Plugins</div>
                        <div class="pm-sv" id="pmCnt">2</div>
                        <div class="pm-ss">hot-reload enabled</div>
                      </div>
                      <div class="pm-ac">
                        <div class="pm-at">Activity</div>
                        <div class="pm-ar"><div class="pm-ad" style="background:var(--green)"></div><span>AI Copilot — active</span></div>
                        <div class="pm-ar" id="redAct" style="opacity:0;transition:opacity .3s"><div class="pm-ad" style="background:#ef4444"></div><span>Redis Inspector — loaded</span></div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </main>

            <!-- Right panel: Widget list (Query History, Table Stats) -->
            <aside class="w-right">
              <div class="wr-title">WIDGET</div>
              
              <!-- Widget 1: Query History -->
              <div class="widget-box" id="widgetQHist">
                <div class="wb-hd">Query History</div>
                <div class="wb-list">
                  <div class="wb-row"><span>SELECT *</span><span class="ok-g">0.4ms</span></div>
                  <div class="wb-row"><span>JOIN orders</span><span class="ok-g">1.2ms</span></div>
                  <div class="wb-row"><span>EXPLAIN plan</span><span class="ok-a">0.8ms</span></div>
                </div>
              </div>

              <!-- Widget 2: Table Statistics -->
              <div class="widget-box" id="widgetTStats">
                <div class="wb-hd">Table Statistics</div>
                <div class="wb-list">
                  <div class="wb-row"><span>Active connection</span><span class="ok-g">Prod</span></div>
                  <div class="wb-row"><span>Total tables</span><span>5</span></div>
                  <div class="wb-row"><span>Cached queries</span><span>142</span></div>
                </div>
              </div>
            </aside>
          </div>
        </div>

      </div>

    </div><!-- .window -->
  </div><!-- .canvas -->
  <div class="scroll-note"><?php echo esc_html($t['scroll']); ?></div>
</div><!-- .stage -->

<!-- Mouse cursor mockup -->
<div class="mouse" id="mouse" aria-hidden="true"><div class="mouse-s"><svg width="27" height="27" viewBox="0 0 27 27" fill="none"><path d="M3.5 2L21 13.8L13.8 15.5L9.8 23.2L3.5 2Z" fill="#F5F3F0" stroke="#0a0a0a" stroke-width="1.5" stroke-linejoin="round"/></svg></div></div>

<!-- Contact Modal -->
<div class="ov" id="contactMod">
  <div class="mc mini">
    <button class="xbtn" id="closeContact">&times;</button>
    <div class="mhd"><div class="mhdi"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><?php esc_html_e('Contact', 'hoasen-theme'); ?></div>
    <div class="cc-cnt">
      <p><?php esc_html_e('We welcome feedback and contributions from the developer community.', 'hoasen-theme'); ?></p>
      <div class="cc-lnks">
        <a href="https://facebook.com/hoasentable" target="_blank" class="cc-itm fb"><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c4.56-.93 8-4.96 8-9.75z"/></svg>facebook.com/hoasentable</a>
        <a href="mailto:support@hoasentable.localhost" class="cc-itm"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>support@hoasentable.localhost</a>
      </div>
    </div>
  </div>
</div>

<!-- Plugin Catalog Dialog/Modal (Opens in Scene 6) -->
<div class="ov" id="pluginMod">
  <div class="mc">
    <button class="xbtn" id="closePlugin">&times;</button>
    <div class="mhd"><div class="mhdi"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><?php esc_html_e('Plugin Manager', 'hoasen-theme'); ?></div>
    <div class="pm-body">
      <input class="pm-srch" id="pmSrch" placeholder="<?php esc_attr_e('Search plugins…', 'hoasen-theme'); ?>"/>
      <div class="pm-mi"><div class="pm-mico">🌙</div><div class="pm-min"><div class="pm-mnm">Dark Mode Pro</div><div class="pm-mds"><?php esc_html_e('Full dark theme, OLED-optimized, zero flicker.', 'hoasen-theme'); ?></div></div><span class="pm-mbg inst"><?php echo esc_html($t['inst']); ?></span><button class="pm-ib done"><?php echo esc_html($t['inst']); ?></button></div>
      <div class="pm-mi"><div class="pm-mico">✦</div><div class="pm-min"><div class="pm-mnm">AI Copilot</div><div class="pm-mds"><?php esc_html_e('Natural language to SQL. Explain and optimize plans.', 'hoasen-theme'); ?></div></div><span class="pm-mbg inst"><?php echo esc_html($t['inst']); ?></span><button class="pm-ib done"><?php echo esc_html($t['inst']); ?></button></div>
      <div class="pm-mi" id="popRedisRow"><div class="pm-mico">🔴</div><div class="pm-min"><div class="pm-mnm">Redis Inspector</div><div class="pm-mds"><?php esc_html_e('Browse keys, TTL, memory. Supports cluster + Sentinel.', 'hoasen-theme'); ?></div></div><span class="pm-mbg pop"><?php esc_html_e('Popular', 'hoasen-theme'); ?></span><button class="pm-ib" id="btnInstallRedis" onclick="this.textContent='…';setTimeout(()=>{this.classList.add('done');this.textContent='<?php echo esc_js($t['inst']); ?>'},700)"><?php echo esc_html($t['install']); ?></button></div>
      <div class="pm-mi"><div class="pm-mico">📊</div><div class="pm-min"><div class="pm-mnm">Chart Builder</div><div class="pm-mds"><?php esc_html_e('Visualize query results as bar, line, pie charts.', 'hoasen-theme'); ?></div></div><span class="pm-mbg pop"><?php esc_html_e('Popular', 'hoasen-theme'); ?></span><button class="pm-ib" onclick="this.textContent='…';setTimeout(()=>{this.classList.add('done');this.textContent='<?php echo esc_js($t['inst']); ?>'},700)"><?php echo esc_html($t['install']); ?></button></div>
    </div>
  </div>
</div>

<script>
const SCENES=7,LANG='<?php echo esc_js($is_vi?"vi":"en"); ?>';
const progEl=document.getElementById('progress');
for(let i=0;i<SCENES;i++){const b=document.createElement('div');b.className='bar';b.innerHTML='<span></span>';progEl.appendChild(b);}
const bars=[...progEl.querySelectorAll('span')];
const copies=[...document.querySelectorAll('.copy-scene')];
const mouse=document.getElementById('mouse');
const winPath=document.getElementById('winPath');
const wbDark=document.getElementById('wbDark');
const wbAI=document.getElementById('wbAI');
const winBtnPlugins=document.getElementById('winBtnPlugins');
const sqlEd=document.getElementById('sqlEd');
const acPop=document.getElementById('acPop');
const astTree=document.getElementById('astTree');
const fkSnip=document.getElementById('fkSnip');
const s4Rows=document.getElementById('s4Rows');
const fkPop=document.getElementById('fkPop');
const s4Tbl=document.getElementById('s4Tbl');
const vgR=document.getElementById('vgR');
const vgTh=document.getElementById('vgTh');
const vgS=document.getElementById('vgS');
const vgE=document.getElementById('vgE');
const pmRed=document.getElementById('pmRed');
const redSt=document.getElementById('redSt');
const redTg=document.getElementById('redTg');
const redAct=document.getElementById('redAct');
const pmCnt=document.getElementById('pmCnt');
const spNs=[document.getElementById('spN1'),document.getElementById('spN2'),document.getElementById('spN3')];
const spBs=[document.getElementById('spB1'),document.getElementById('spB2'),document.getElementById('spB3')];
const cBs=[document.getElementById('cB1'),document.getElementById('cB2')];

// Mock Layout Dom References
const s0=document.getElementById('s0');
const workspaceLayout=document.getElementById('workspaceLayout');
const wlUsers=document.getElementById('wlUsers');
const wlOrders=document.getElementById('wlOrders');
const widgetQHist=document.getElementById('widgetQHist');
const widgetTStats=document.getElementById('widgetTStats');
const fkLine=document.getElementById('fkLine');
const vgMetricsBox=document.getElementById('vgMetricsBox');
const pmAicop=document.getElementById('pmAicop');

// Modals
const menuBtn=document.getElementById('menuBtn'),menuDd=document.getElementById('menuDd');
const contactMod=document.getElementById('contactMod'),pluginMod=document.getElementById('pluginMod');
menuBtn.addEventListener('click',e=>{e.stopPropagation();menuDd.classList.toggle('show');});
document.addEventListener('click',()=>menuDd.classList.remove('show'));
document.getElementById('btnContact').addEventListener('click',e=>{e.preventDefault();contactMod.classList.add('show');});
document.getElementById('btnPlugins').addEventListener('click',()=>pluginMod.classList.add('show'));
winBtnPlugins.addEventListener('click',()=>pluginMod.classList.add('show'));

['closeContact','closePlugin'].forEach(id=>document.getElementById(id).addEventListener('click',()=>{contactMod.classList.remove('show');pluginMod.classList.remove('show');}));
[contactMod,pluginMod].forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

function clamp(v,a=0,b=1){return Math.min(b,Math.max(a,v));}
function ease(t){return t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;}
function lerp(a,b,t){return a+(b-a)*t;}
function mix(a,b,t){t=ease(clamp(t));return{x:lerp(a.x,b.x,t),y:lerp(a.y,b.y,t)};}
let lastClick=false;
function setMouse(p,click=false){
  mouse.style.transform=`translate(${p.x}px,${p.y}px)`;
  mouse.classList.toggle('click',click);
  if(click&&!lastClick){const r=document.createElement('div');r.className='click-rip';r.style.left=p.x+'px';r.style.top=p.y+'px';document.body.appendChild(r);setTimeout(()=>r.remove(),380);}
  lastClick=click;
}
function gp(rx,ry){const w=document.querySelector('.window').getBoundingClientRect();return{x:w.left+w.width*rx,y:w.top+w.height*ry};}
function getElementPoint(el){const r=el.getBoundingClientRect();return{x:r.left+r.width/2,y:r.top+r.height/2};}

function showScene(sceneId) {
  // Show / Hide main views
  if (sceneId === 0) {
    s0.classList.add('show');
    workspaceLayout.classList.remove('show');
  } else {
    s0.classList.remove('show');
    workspaceLayout.classList.add('show');
    
    // Hide all feature sub-views in the middle panel
    for(let i=1; i<=6; i++) {
      document.getElementById('featS1').classList.remove('show');
      document.getElementById('featS2').classList.remove('show');
      document.getElementById('featS3').classList.remove('show');
      document.getElementById('featS4').classList.remove('show');
      document.getElementById('featS5').classList.remove('show');
      document.getElementById('featS6').classList.remove('show');
    }
    
    // Show active feature sub-view
    document.getElementById('featS' + sceneId).classList.add('show');
  }
}

function removeHighlights() {
  acPop.classList.remove('highlight-box');
  fkSnip.classList.remove('highlight-box');
  fkLine.classList.remove('highlight-box-green');
  vgMetricsBox.classList.remove('highlight-box-green');
  pmAicop.classList.remove('highlight-box');
  document.getElementById('popRedisRow')?.classList.remove('highlight-box');
  document.getElementById('cardA').classList.remove('highlight-box');
  winBtnPlugins.classList.remove('highlight-box');
  pluginMod.querySelector('.mc')?.classList.remove('highlight-box');
}

// Scene 0: Connection Board
function sc0(p){
  winPath.innerHTML='<strong>HoaSen Table</strong> · Connection Board';
  showScene(0);
  removeHighlights();
  
  const card = document.getElementById('cardA');
  card.classList.toggle('highlight-box', p > 0.7);
  setMouse(mix(gp(.5,.5), gp(.73,.44), p), p > .78 && p < .95);
}

// Scene 1: Autocomplete
const AST=[
  [{indent:0,text:'SelectStatement',tag:'stmt',v:true,hi:false},
   {indent:1,text:'SELECT *',tag:'projection',v:true,hi:false},
   {indent:1,text:'_cursor_',tag:'next-clause',v:null,hi:true}],
  [{indent:0,text:'SelectStatement',tag:'stmt',v:true,hi:false},
   {indent:1,text:'SELECT *',tag:'projection',v:true,hi:false},
   {indent:1,text:'F',tag:'partial-token',v:false,hi:true},
   {indent:2,text:'→ expecting: FROM | FOR UPDATE | FETCH',tag:'hint',v:null,hi:false}],
  [{indent:0,text:'SelectStatement',tag:'stmt',v:true,hi:false},
   {indent:1,text:'SELECT *',tag:'projection',v:true,hi:false},
   {indent:1,text:'FROM users u',tag:'from-clause',v:true,hi:false},
   {indent:2,text:'TableRef: users (alias u)',tag:'table-ref',v:true,hi:false}]
];
function renderAst(nodes){
  astTree.innerHTML=nodes.map(n=>`<div class="ast-row${n.hi?' hi':''}${n.v===true?' ok':''}"><div style="width:${n.indent*14}px;flex-shrink:0"></div><span>${n.text}</span><span class="ast-tag">${n.tag}</span>${n.v===true?'<span class="ast-lg">✓</span>':''}${n.v===false?'<span class="ast-il">✗</span>':''}</div>`).join('');
}
function sc1(p){
  winPath.innerHTML='<strong>query.sql</strong> · grammar autocomplete';
  showScene(1);
  removeHighlights();
  
  wlUsers.classList.add('active');
  wlOrders.classList.remove('active');
  
  const phase=p<.35?0:p<.65?1:2;
  const texts=['<span class="kw">SELECT</span> * <span class="caret"></span>','<span class="kw">SELECT</span> * <span class="kw">F</span><span class="caret"></span>','<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">users</span> u<span class="caret"></span>'];
  sqlEd.innerHTML=texts[phase];
  
  acPop.classList.toggle('show',phase>0);
  acPop.classList.toggle('highlight-box', phase>0 && p>0.4);
  renderAst(AST[phase]);
  
  setMouse(mix(gp(.73,.44), gp(.38,.22), p), false);
}

// Scene 2: Smart Joins
function sc2(p){
  winPath.innerHTML='<strong>query.sql</strong> · FK join snippet';
  showScene(2);
  removeHighlights();
  
  wlUsers.classList.add('active');
  wlOrders.classList.add('active'); // active highlight both tables joined
  
  if(p<.4){
    sqlEd.innerHTML='<span class="kw">SELECT</span> u.id, u.name, o.total <span class="kw">FROM</span> <span class="tbl">users</span> u <span class="kw">join</span><span class="caret"></span>';
    fkSnip.classList.remove('show');
  }
  else{
    sqlEd.innerHTML='<span class="kw">SELECT</span> u.id, u.name, o.total <span class="kw">FROM</span> <span class="tbl">users</span> u <span class="kw">JOIN</span> <span class="tbl">orders</span> o <span class="kw">ON</span> <span class="col">o.user_id</span>=<span class="col">u.id</span>';
    fkSnip.classList.add('show');
    fkSnip.classList.add('highlight-box');
    fkLine.classList.add('highlight-box-green');
  }
  setMouse(mix(gp(.38,.22), gp(.5,.62), p), p>.4 && p<.55);
}

// Scene 3: Performance
function sc3(p){
  winPath.innerHTML='<strong>benchmark</strong> · native vs Electron';
  showScene(3);
  removeHighlights();
  
  wlUsers.classList.remove('active');
  wlOrders.classList.remove('active');
  
  sqlEd.innerHTML='<span class="kw">SELECT</span> BENCHMARK(<span class="num">10000000</span>, <span class="cm">\'AES_ENCRYPT\'</span>);';
  
  const t=ease(clamp(p/.5));
  if(p>.12){
    spNs[0].textContent=Math.round(lerp(500,78,t))+'ms';
    spNs[1].textContent=Math.round(lerp(250,18,t))+' MB';
    spNs[2].textContent=(3+lerp(150,0,t)).toFixed(1)+' MB';
    const w=Math.round(lerp(0,5,t))+'%';
    spBs[0].style.width=w;spBs[1].style.width=w;spBs[2].style.width=w;
    cBs[0].style.width=w;cBs[1].style.width=w;
  } else {spNs.forEach(n=>n.textContent='—');spBs.forEach(b=>b.style.width='0');cBs.forEach(b=>b.style.width='0');}
  setMouse(mix(gp(.5,.5), gp(.28,.38), p), false);
}

// Scene 4: Hover Inspect
let s4Init=false;
function sc4(p){
  winPath.innerHTML='<strong>orders</strong> · hover FK inspect';
  showScene(4);
  removeHighlights();
  
  wlUsers.classList.remove('active');
  wlOrders.classList.add('active');
  
  sqlEd.innerHTML='<span class="kw">SELECT</span> id, <span class="col">user_id</span>, total, status <span class="kw">FROM</span> <span class="tbl">orders</span> <span class="kw">LIMIT</span> <span class="num">50</span>;';
  
  if(!s4Init){
    const st=[['ok-g','paid'],['ok-a','pending'],['ok-g','paid'],['ok-g','paid'],['ok-r','failed']];
    const uid=[42,87,18,92,64];
    let h='';
    for(let i=0;i<5;i++)h+=`<tr><td>${90020+i}</td><td style="position:relative"><span class="fk-c${i===0?' hl':''}" id="fkC${i}">${uid[i]} ↗</span></td><td>${(8000+i*1800).toLocaleString()}</td><td><span class="${st[i][0]}">${st[i][1]}</span></td></tr>`;
    s4Rows.innerHTML=h;s4Init=true;
  }
  
  const show=p>.18;
  const fc=document.getElementById('fkC0');
  if(fc&&show){
    const gr=s4Tbl.parentElement.getBoundingClientRect();
    const cr=fc.getBoundingClientRect();
    fkPop.style.top=(cr.top-gr.top+cr.height/2-38)+'px';
    fkPop.style.left=(cr.right-gr.left+8)+'px';
    fkPop.classList.add('show');
    fkPop.classList.add('highlight-box-green');
  } else {fkPop.classList.remove('show');}
  setMouse(mix(gp(.5,.35), gp(.36,.59), p), false);
}

// Scene 5: Virtual Grid
function sc5(p){
  winPath.innerHTML='<strong>massive_data</strong> · virtual grid';
  showScene(5);
  removeHighlights();
  
  wlUsers.classList.add('active');
  wlOrders.classList.remove('active');
  
  sqlEd.innerHTML='<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">huge_table</span>; <span class="cm">-- 1M records</span>';
  
  const s=Math.floor(1+p*999977);
  vgS.textContent=s.toLocaleString();vgE.textContent=(s+22).toLocaleString();
  const sc=['ok-g','ok-g','ok-a','ok-g','ok-g','ok-r','ok-g','ok-a','ok-g','ok-g','ok-g','ok-g','ok-a','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-a'];
  const sn=['paid','paid','pending','paid','paid','failed','paid','pending','paid','paid','paid','paid','pending','paid','paid','paid','paid','paid','paid','paid','paid','paid','pending'];
  let h='';
  for(let i=0;i<23;i++){const n=s+i;h+=`<tr><td>${n.toLocaleString()}</td><td>${800000+n}</td><td>${(n*7)%999}</td><td>${(1200+(n%200)*31).toLocaleString()}</td><td><span class="${sc[i]}">${sn[i]}</span></td></tr>`;}
  vgR.innerHTML=h;
  vgTh.style.top=(p*80)+'%';
  vgMetricsBox.classList.add('highlight-box-green');
  
  setMouse(mix(gp(.5,.4), gp(.88,.5+p*.2), p), false);
}

// Scene 6: Plugin Manager
function sc6(p){
  winPath.innerHTML='<strong>plugin manager</strong> · extensions';
  
  // Custom interactive mouse workflow:
  // 1. Move to "Plugins" button in winbar and hover
  // 2. Click winbar "Plugins" button -> open Plugin catalog modal overlay
  // 3. Highlight the Redis Inspector row install trigger, click install
  // 4. Close modal -> highlight active count widget & show Redis active inside workspace frame
  
  removeHighlights();
  
  const ptBtn = getElementPoint(winBtnPlugins);
  const ptInst = getElementPoint(document.getElementById('btnInstallRedis') || ptBtn);
  const ptClose = getElementPoint(document.getElementById('closePlugin') || ptBtn);
  
  if (p < 0.25) {
    // Phase 1: Move mouse cursor to winBtnPlugins button in winbar
    showScene(5); // Show workspace background first
    pluginMod.classList.remove('show');
    setMouse(mix(gp(.5,.5), ptBtn, p/0.25), false);
    winBtnPlugins.classList.add('highlight-box');
  } 
  else if (p < 0.35) {
    // Phase 2: Click! Show catalog modal
    showScene(5);
    pluginMod.classList.add('show');
    setMouse(ptBtn, true); // Click rip triggered
    pluginMod.querySelector('.mc')?.classList.add('highlight-box');
  }
  else if (p < 0.7) {
    // Phase 3: Move to Install Redis button inside modal
    showScene(5);
    pluginMod.classList.add('show');
    // Animate to Install button
    setMouse(mix(ptBtn, ptInst, (p-0.35)/0.35), (p > 0.6));
    document.getElementById('popRedisRow')?.classList.add('highlight-box');
    
    // Simulate install click
    if (p > 0.65) {
      document.getElementById('btnInstallRedis').classList.add('done');
      document.getElementById('btnInstallRedis').textContent = '<?php echo esc_js($t['inst']); ?>';
      pmRed.classList.add('on');
      redSt.textContent = 'ON'; redSt.className = 'pm-st on';
      redTg.className = 'pm-tg on';
      redAct.style.opacity = '1';
      pmCnt.textContent = '3';
    }
  }
  else if (p < 0.85) {
    // Phase 4: Close modal
    showScene(5);
    pluginMod.classList.add('show');
    setMouse(mix(ptInst, ptClose, (p-0.7)/0.15), (p > 0.8));
  }
  else {
    // Phase 5: Hide modal & show integrated Plugin Manager workspace view (#featS6)
    pluginMod.classList.remove('show');
    showScene(6); // Switch to Plugin tab in middle panel
    wbDark.classList.add('on'); // Apply dark theme to preview tags
    
    // Highlight Aicop plugin card
    pmAicop.classList.add('highlight-box');
    
    // Idle mouse in widget pane
    setMouse(ptClose, false);
  }
}

// Main scroll loop
function update(){
  const max=document.documentElement.scrollHeight-innerHeight;
  const g=clamp(scrollY/max);
  const raw=clamp(g*SCENES,0,SCENES-.0001);
  const idx=Math.floor(raw),loc=raw-idx;
  bars.forEach((b,i)=>b.style.width=(i<idx?100:i>idx?0:loc*100)+'%');
  copies.forEach((c,i)=>{
    const d=i-(idx+loc),op=clamp(1-Math.abs(d)*1.7);
    c.style.opacity=op;c.style.filter=`blur(${Math.abs(d)*10}px)`;
    c.style.transform=`translateY(-42%) translateY(${d*22}px)`;
    c.classList.toggle('active',op>.55);
  });
  
  [sc0,sc1,sc2,sc3,sc4,sc5,sc6][idx]?.(loc);
}

let tick=false;
addEventListener('scroll',()=>{if(!tick){requestAnimationFrame(()=>{update();tick=false;});tick=true;}},{passive:true});
addEventListener('resize',update);
setTimeout(update,80);
</script>
<?php wp_footer(); ?>
</body>
</html>
