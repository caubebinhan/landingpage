<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$t = [
  'title'   => esc_html__('HoaSen Table — Free, Fast & Minimal SQL Client (Postgres, MySQL, SQLite)', 'hoasen-theme'),
  'desc'    => esc_html__('A free, native-speed, and minimal SQL client for Postgres, MySQL, and SQLite. Built with zero bloat and designed to maximize developer focus.', 'hoasen-theme'),
  'blog'    => esc_html__('BLOG', 'hoasen-theme'),
  'contact' => esc_html__('CONTACT', 'hoasen-theme'),
  'scroll'  => esc_html__('Scroll to explore ↓', 'hoasen-theme'),
  'inst'    => esc_html__('Installed', 'hoasen-theme'),
  'install' => esc_html__('Install', 'hoasen-theme'),
  'plugins' => esc_html__('PLUGINS', 'hoasen-theme'),
  'learn'   => esc_html__('Deep dive: Autocomplete →', 'hoasen-theme'),
  'loaded'  => esc_html__('✓ loaded · 50 rows', 'hoasen-theme'),
  'active'  => esc_html__('active', 'hoasen-theme'),
  'viewrow' => esc_html__('Viewing row', 'hoasen-theme'),
  'of'      => esc_html__('of', 'hoasen-theme'),
];
?>
<link rel="icon" type="image/svg+xml" href="<?php echo get_stylesheet_directory_uri(); ?>/logo_svg.svg"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<meta name="robots" content="index,follow"/>
<!-- title, meta description, og:title/description/type, twitter:card are emitted by Yoast SEO via wp_head() below -->
<script type="application/ld+json">
<?php echo wp_json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'HoaSen Table',
  'inLanguage' => $lang,
  'applicationCategory' => 'DeveloperApplication',
  'operatingSystem' => 'Windows, macOS, Linux',
  'description' => $t['desc'],
  'url' => home_url('/'),
  'featureList' => [
    'Minimal database workspace for developers',
    'Fast native-feeling database experience',
    'Focused SQL editor with grammar-aware autocomplete',
    'Schema-aware and context-aware query assistance',
    'Machine learning that adapts to developer query habits',
    'Foreign-key aware JOIN assistance',
    'Responsive virtual grid for large database tables',
    'Inline relation inspection',
    'Fully custom plugins inside the workspace',
    'Creative widget controls for personalized developer workflows',
  ],
  'audience' => [
    '@type' => 'Audience',
    'audienceType' => 'Developers, testers, technical product teams',
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>
<?php wp_head(); /* Yoast SEO's Polylang integration already emits complete, correct hreflang tags (en/vi/ja + x-default) here — a hand-written block was removed to avoid duplicating/conflicting with it. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0efed;--red-rgb:190,24,74;--red2-rgb:219,39,119;--red:rgb(var(--red-rgb));--red2:rgb(var(--red2-rgb));--green:#0f6b3e;--blue:#1a4fc4;--amber:#a16207;
  --text:#0f0f0f;--muted:#6b7280;
  --rs:rgba(var(--red-rgb),.07);--rb:rgba(var(--red-rgb),.18);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{height:740vh;overflow-x:hidden;background:var(--bg);color:var(--text);font-family:"Cormorant Garamond",Georgia,serif}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background:radial-gradient(circle at 14% 13%,rgba(var(--red-rgb),.042),transparent 31%),
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
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.brand-tag{font-family:"Outfit",sans-serif;font-size:8px;letter-spacing:.2em;text-transform:uppercase;color:var(--red);font-weight:700;margin-top:1px}
.brand-logo,.brand-name,.brand-tag{transition:opacity .3s,transform .3s}
.brand.scene-hidden{opacity:0;transform:translateY(-10px);pointer-events:none}
.topbar{position:absolute;top:22px;right:32px;z-index:100;display:flex;align-items:center;gap:9px}
.tb-chip{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:999px;
  background:rgba(var(--red-rgb),.06);border:1px solid rgba(var(--red-rgb),.14);
  font-family:"Outfit",sans-serif;font-size:10px;font-weight:700;color:var(--red);
  cursor:pointer;transition:all .18s;letter-spacing:.04em}
.tb-chip:hover{background:rgba(var(--red-rgb),.13)}
.tb-dot{width:5px;height:5px;border-radius:50%;background:var(--red)}
.lang-sw{font-family:"Outfit",sans-serif;font-size:10px;font-weight:600}
.lang-sw ul{list-style:none;display:flex;gap:5px}
.lang-sw a{text-decoration:none;color:#aaa;padding:3px 6px;border-radius:4px;transition:color .15s}
.lang-sw .current-lang a{color:var(--red);font-weight:900;pointer-events:none}
.menu-wrap{position:relative}
.menu-btn{background:transparent;border:none;cursor:pointer;color:var(--text);padding:7px;border-radius:6px;transition:background .18s;display:flex;align-items:center}
.menu-btn:hover{background:rgba(0,0,0,.06)}
.menu-dd{position:absolute;right:0;top:calc(100% + 8px);min-width:138px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:10px;box-shadow:0 12px 36px rgba(0,0,0,.1);display:none;z-index:200;overflow:hidden;font-family:"Outfit",sans-serif}
.menu-dd.show{display:block}
.menu-dd a{display:block;padding:10px 15px;color:#333;text-decoration:none;font-size:11px;font-weight:700;letter-spacing:.05em;transition:background .14s}
.menu-dd a:hover{background:var(--rs);color:var(--red)}
.menu-lang{padding:9px 15px 11px;border-top:1px solid rgba(0,0,0,.07)}
.menu-lang-label{font-size:9px;font-weight:900;letter-spacing:.12em;color:#888;margin-bottom:5px}
.menu-lang .lang-sw ul{gap:4px;flex-wrap:wrap}
.menu-lang .lang-sw a{padding:4px 7px;background:#f5f3f0}

/* Progress bar moved to bottom center */
.progress{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);z-index:100;display:flex;gap:7px}
.bar{width:26px;height:3px;border-radius:99px;background:rgba(0,0,0,.08);overflow:hidden}
.bar span{display:block;width:0;height:100%;background:linear-gradient(90deg,var(--red),var(--green))}

.copy{position:relative;z-index:5;min-height:380px}
.copy-scene{position:absolute;left:0;right:0;top:50%;transform:translateY(-42%) translateY(16px);opacity:0;filter:blur(3px);pointer-events:none}
.copy-scene.active{opacity:1;filter:blur(0);transform:translateY(-42%);pointer-events:auto}
.kicker{font-size:10px;letter-spacing:.22em;text-transform:uppercase;color:var(--red);font-weight:900;margin-bottom:16px;font-family:"Outfit",sans-serif}
h1{font-size:clamp(33px,4.6vw,66px);line-height:.93;letter-spacing:-.03em;font-weight:700;max-width:560px;color:#0f0f0f;text-wrap:balance}
.copy p{font-size:16px;line-height:1.6;color:#4b5563;margin-top:20px;max-width:400px;font-family:"Outfit",sans-serif;text-wrap:pretty}
.chips{display:flex;gap:8px;margin-top:22px;flex-wrap:wrap;font-family:"Outfit",sans-serif}
.chip{padding:5px 11px;border-radius:999px;border:1px solid rgba(0,0,0,.09);background:rgba(0,0,0,.03);font-size:10px;color:#374151;font-weight:700}
.chip.hot{background:linear-gradient(135deg,rgba(var(--red-rgb),.92),rgba(var(--red2-rgb),.88));color:#fff;border:0;box-shadow:0 3px 12px rgba(var(--red-rgb),.18)}
.chip.go{background:linear-gradient(135deg,rgba(15,107,62,.9),rgba(11,87,45,.88));color:#fff;border:0}
.db-logos{display:flex;gap:8px;margin-top:22px;flex-wrap:wrap;font-family:"Outfit",sans-serif;align-items:center}
.db-logo{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,.6);border:1px solid rgba(0,0,0,.09);color:#374151;font-size:10px;font-weight:700;box-shadow:0 1px 3px rgba(0,0,0,.02);transition:all .2s ease-in-out;backdrop-filter:blur(8px);cursor:default}
.db-logo:hover{transform:translateY(-2px);background:#fff;border-color:rgba(var(--red-rgb),.22);box-shadow:0 4px 12px rgba(var(--red-rgb),.08);color:var(--text)}
.db-logo svg{width:12px;height:12px;display:block}
.learn-more{display:inline-flex;align-items:center;gap:6px;margin-top:20px;padding:8px 16px;border-radius:999px;border:1px solid rgba(var(--red-rgb),.28);background:transparent;color:var(--red);font-family:"Outfit",sans-serif;font-size:11px;font-weight:700;letter-spacing:.04em;cursor:pointer;text-decoration:none;transition:all .18s}
.learn-more:hover{background:var(--red);color:#fff}
.download-btn {
  display: inline-flex !important;
  align-items: center;
  gap: 8px;
  padding: 14px 32px !important;
  font-size: 14px !important;
  font-weight: 800 !important;
  text-transform: uppercase;
  letter-spacing: .08em;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--red), var(--red2)) !important;
  color: #fff !important;
  border: none !important;
  box-shadow: 0 8px 30px rgba(var(--red-rgb),.38) !important;
  transition: all .2s cubic-bezier(.16,1,.3,1) !important;
  cursor: pointer;
  text-decoration: none;
  z-index: 100;
  position: relative;
}
.download-btn:hover {
  transform: translateY(-2.5px) scale(1.03);
  box-shadow: 0 12px 36px rgba(var(--red-rgb),.55) !important;
}
.canvas{position:relative;z-index:3;height:min(76vh,780px);min-height:560px;width:100%;display:flex;align-items:center;justify-content:center}

.window{position:relative;width:min(100%,870px);height:100%;overflow:hidden;border-radius:18px;border:1px solid rgba(0,0,0,.11);background:#fdfdfc;box-shadow:0 42px 120px rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.9);display:flex;flex-direction:column}
.window.connection-mode{transform:scale(.88) translateY(-2%)}
.window.connection-mode .wb-tags{display:none}
.window::before{content:"";position:absolute;inset:-1px;background:radial-gradient(circle at 22% 0%,rgba(var(--red-rgb),.03),transparent 28%);pointer-events:none;z-index:1}
.winbar{height:48px;display:flex;align-items:center;gap:10px;padding:0 18px;border-bottom:1px solid rgba(0,0,0,.07);background:rgba(0,0,0,.016);font-size:11px;color:#777;position:relative;z-index:10;font-family:"JetBrains Mono",monospace;flex-shrink:0}
.traf{display:flex;gap:6px}
.traf i{width:10px;height:10px;border-radius:50%;display:block}
.r{background:#ff5f57}.y{background:#febc2e}.g{background:#28c840}
.wpath{flex:1;margin-left:8px}.wpath strong{color:#111}
.wb-tags{display:flex;gap:5px;margin-left:auto;align-items:center}
.wb-tag{padding:2px 8px;border-radius:999px;background:rgba(var(--red-rgb),.07);border:1px solid rgba(var(--red-rgb),.12);font-size:8px;font-weight:700;color:var(--red);font-family:"Outfit",sans-serif;letter-spacing:.05em}
.wb-tag.on{background:linear-gradient(135deg,var(--red),#a82525);color:#fff;border-color:transparent}

/* Standard Client Workspace Layout */
.sc-container{position:relative;flex:1;width:100%;height:100%;overflow:hidden}
.sc{position:absolute;inset:0;transition:opacity .28s,visibility .28s;opacity:0;visibility:hidden;pointer-events:none;background:#fafaf8}
.sc.show{opacity:1;visibility:visible;pointer-events:auto}

/* Workspace Frame (Scenes 1-6) */
.workspace-frame{display:grid;grid-template-columns:140px 1fr 180px;height:100%;width:100%;background:#fafaf8;overflow:hidden;transition:transform .5s cubic-bezier(.22,1,.36,1)}
.w-left{border-right:1px solid rgba(0,0,0,.07);background:#fafaf8;padding:12px;display:flex;flex-direction:column;gap:8px}
.w-middle{display:flex;flex-direction:column;height:100%;overflow:hidden}
.w-right{border-left:1px solid rgba(0,0,0,.07);background:#fafaf8;padding:12px;display:flex;flex-direction:column;gap:12px}
.w-right.widget-removed #widgetQHist{flex:1;justify-content:center}
.w-right.widget-removed #widgetQHist .wb-list{display:flex;flex-direction:column;gap:12px}

/* Left panel styles */
.wl-hd{font-family:"Outfit",sans-serif;font-size:8px;font-weight:900;letter-spacing:.1em;color:#aaa;text-transform:uppercase;margin-bottom:4px}
.wl-item{font-family:"Outfit",sans-serif;font-size:11px;color:#555;padding:6px 10px;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:all .15s}
.wl-item:hover{background:rgba(0,0,0,.03)}
.wl-item.active{background:var(--rs);color:var(--red);font-weight:700}
.wl-badge{font-size:8px;padding:1px 5px;border-radius:99px;background:rgba(0,0,0,.04);color:#888}

/* Middle panel splitter parts */
.w-mid-top{padding:14px 16px 12px;border-bottom:1px solid rgba(0,0,0,.05);background:#fff;flex-shrink:0;position:relative}
.run-btn{position:absolute;right:12px;bottom:9px;border:0;border-radius:6px;background:var(--red);color:#fff;padding:6px 13px;font:700 10px "Outfit",sans-serif;cursor:pointer}
.run-btn.focused{box-shadow:0 0 0 4px rgba(var(--red-rgb),.16);transform:translateY(-1px)}
.w-mid-bottom{flex:1;overflow:hidden;position:relative;display:flex;flex-direction:column;background:#fff}
.data-view{position:absolute;inset:0;display:flex;flex-direction:column;background:#fff}
.data-view .tg{font-size:10px}
.data-view .tg tbody tr{height:28px}
.data-view-status{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;border-bottom:1px solid rgba(0,0,0,.07);font:10px "Outfit",sans-serif;color:#666}
.data-view-status strong{color:var(--green)}
.data-scrollbar{position:absolute;z-index:5;right:4px;top:38px;bottom:5px;width:7px;border-radius:7px;background:rgba(0,0,0,.05);transition:background .18s,box-shadow .18s}
.data-scrollbar.highlight{background:rgba(var(--red-rgb),.16);box-shadow:0 0 0 3px rgba(var(--red-rgb),.08)}
.data-scroll-thumb{position:absolute;left:1px;top:0;width:5px;height:22%;border-radius:6px;background:#aaa;transition:top .08s linear,background .18s}
.data-scrollbar.highlight .data-scroll-thumb{background:var(--red)}
.relation-pop{position:absolute;z-index:8;width:210px;padding:11px;border-radius:9px;background:#171716;color:#fff;box-shadow:0 8px 18px rgba(0,0,0,.22);font:10px "Outfit",sans-serif;opacity:0;transform:translateY(5px);transition:opacity .18s,transform .18s;pointer-events:none}
.relation-pop.show{opacity:1;transform:none}
.relation-pop b{display:block;margin-bottom:7px;font-size:11px}
.relation-pop div{display:flex;justify-content:space-between;padding:3px 0;color:#bbb}
.relation-pop div span:last-child{color:#fff}

/* Right panel Widget Manager */
.wr-title{font-family:"Outfit",sans-serif;font-size:8px;font-weight:900;letter-spacing:.12em;color:#aaa;text-transform:uppercase;margin-bottom:2px}
.widget-box{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:6px;box-shadow:0 2px 6px rgba(0,0,0,.02)}
.widget-box.removed{opacity:0;transform:translateX(18px);pointer-events:none}
.widget-box{transition:opacity .25s,transform .25s}
.widget-remove{margin-left:auto;border:0;background:transparent;color:#999;font-size:15px;line-height:1;cursor:pointer}
.resource-panel{display:none;position:absolute;inset:12px;z-index:9;background:#fafaf8;border:1px solid rgba(0,0,0,.1);border-radius:10px;padding:22px}
.resource-panel.show{display:block}
.resource-panel h2{margin:0 0 14px;font:700 22px "Cormorant Garamond",serif}
.resource-links{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.resource-links a{padding:11px;border-radius:7px;background:#fff;color:#222;text-decoration:none;font:600 11px "Outfit",sans-serif}
.data-view.is-empty{opacity:0;pointer-events:none}
.canvas.resources-mode .window{opacity:0;transform:translateY(18px) scale(.97);pointer-events:none}
.window{transition:opacity .35s,transform .45s cubic-bezier(.22,1,.36,1)}
.site-outro{display:none;position:absolute;z-index:8;left:50%;top:50%;width:min(100%,870px);transform:translate(-50%,-50%);padding:28px 0;border-top:1px solid rgba(0,0,0,.12);border-bottom:1px solid rgba(0,0,0,.12)}
.site-outro.show{display:block}
.site-outro h2{margin:0 0 18px;font:700 clamp(26px,4vw,42px) "Cormorant Garamond",serif}
.outro-links{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.outro-links a{color:#222;text-decoration:none;padding:10px 14px;background:#fff;border-radius:7px;font:600 11px "Outfit",sans-serif;transition:all .2s ease}
.outro-links a:hover{background:#f3f4f6;transform:translateY(-1px)}
.btn-download {
  display: inline-flex !important;
  align-items: center;
  gap: 8px;
  padding: 10px 20px !important;
  background: linear-gradient(135deg, #db2777, #be185d) !important;
  color: #fff !important;
  text-decoration: none;
  border-radius: 999px !important;
  font: 700 11px "Outfit", sans-serif !important;
  letter-spacing: 0.02em;
  box-shadow: 0 4px 14px rgba(190, 18, 60, 0.3);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
  position: relative;
  overflow: hidden;
}
.btn-download::before {
  content: '';
  position: absolute;
  top: 0;
  left: -50%;
  width: 200%;
  height: 100%;
  background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.3), transparent);
  transform: skewX(-25deg);
  transition: 0.75s;
  pointer-events: none;
}
.btn-download.hover::before, .btn-download:hover::before {
  left: 120%;
}
.btn-download.hover, .btn-download:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 6px 20px rgba(190, 18, 60, 0.45);
  background: linear-gradient(135deg, #f472b6, #be185d) !important;
}
.btn-download svg {
  width: 12px;
  height: 12px;
  fill: currentColor;
  display: block;
  transition: transform 0.2s ease;
}
.btn-download.hover svg, .btn-download:hover svg {
  transform: translateY(1px);
}
.stage.resources-mode .copy{opacity:0;pointer-events:none}
.stage.resources-mode .canvas{grid-column:1/-1}
.wb-hd{font-family:"Outfit",sans-serif;font-size:9px;font-weight:800;color:#6b7280;border-bottom:1px solid rgba(0,0,0,.04);padding-bottom:4px;text-transform:uppercase;letter-spacing:.05em}
.wb-list{display:flex;flex-direction:column;gap:5px}
.wb-row{display:flex;justify-content:space-between;align-items:center;font-family:"JetBrains Mono",monospace;font-size:9px;color:#555}

/* Active Highlight Styling */
.highlight-box{box-shadow:0 0 0 2px rgba(var(--red-rgb),.35), 0 0 16px rgba(var(--red-rgb),.18) !important}
.highlight-box-green{box-shadow:0 0 0 2px rgba(15,107,62,.35), 0 0 16px rgba(15,107,62,.18) !important}

/* Scene 0: Connection Board (Full frame) */
#s0{padding:28px;background:#fafaf8;display:grid;grid-template-columns:1fr 1fr;gap:18px}
.cg{border:1px solid rgba(0,0,0,.07);border-radius:14px;background:#fff;padding:16px;display:flex;flex-direction:column;gap:10px}
.cg.dev{border-top:3px solid var(--green)}.cg.prod{border-top:3px solid var(--red)}
.cg-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.cg-n{font-family:"Outfit",sans-serif;font-size:9px;font-weight:900;letter-spacing:.08em;color:#888;text-transform:uppercase}
.cg-b{font-size:8px;padding:2px 7px;border-radius:99px;font-weight:700;font-family:"Outfit",sans-serif}
.cg-b.dev{background:rgba(15,107,62,.1);color:var(--green)}.cg-b.prod{background:rgba(var(--red-rgb),.1);color:var(--red)}
.cc{display:flex;align-items:center;gap:11px;padding:9px 12px;border:1px solid rgba(0,0,0,.06);border-radius:9px;cursor:pointer;transition:all .18s;font-family:"Outfit",sans-serif;position:relative}
.cc:hover,.cc.on{background:rgba(0,0,0,.02);border-color:rgba(0,0,0,.12)}
.cc.on{box-shadow:inset 0 0 0 1.5px rgba(var(--red-rgb),.2)}
.dbi{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-family:"JetBrains Mono",monospace;font-size:10px;font-weight:900;color:#fff;flex-shrink:0}
.dbi.pg{background:#336791}.dbi.my{background:#00758f}.dbi.sq{background:#003b57}
.cc-n{font-size:12px;font-weight:700;color:#222}.cc-h{font-size:9px;color:#aaa;font-family:"JetBrains Mono",monospace;margin-top:1px}
.cc-p{font-size:9px;font-weight:700;color:var(--green);background:rgba(15,107,62,.08);padding:2px 6px;border-radius:99px;font-family:"Outfit",sans-serif;margin-left:auto}

/* Dynamic workspace inner wrappers */
.feat-sc{display:none;position:absolute;z-index:6;overflow:hidden;background:#fafaf8;border:1px solid rgba(0,0,0,.12);border-radius:10px;box-shadow:0 8px 18px rgba(0,0,0,.14);pointer-events:none}
.feat-sc.show{display:flex;flex-direction:column}
#featS1{right:12px;top:12px;width:min(430px,72%);height:210px}
#featS2{display:none!important}
#featS3{right:12px;top:12px;width:min(440px,76%);height:230px}
#featS4{display:none!important}
#featS5{right:12px;bottom:12px;width:190px;height:100px}
#featS6{left:12px;right:12px;top:12px;width:auto;height:min(300px,calc(100% - 24px))!important}

/* Scene 1: Autocomplete */
#s1{display:grid;grid-template-columns:1fr 1fr;height:100%}
.ast-left{background:#fafaf8;border-right:1px solid rgba(0,0,0,.07);padding:14px;display:flex;flex-direction:column;gap:0;overflow:hidden}
.ed-lbl{font-family:"Outfit",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.ed{font-family:"JetBrains Mono",monospace;font-size:13px;line-height:1.7;color:#222;min-height:30px;padding:4px 72px 4px 6px;margin:-4px -6px;border-radius:5px;transition:background .18s,box-shadow .18s}
.ed.focused{background:#fff;box-shadow:0 0 0 2px rgba(var(--red-rgb),.18)}
.kw{color:var(--red);font-weight:700}.tbl{color:var(--blue)}.col{color:var(--green)}.num{color:var(--amber)}.cm{color:#bbb}
.caret{display:inline-block;width:2px;height:1.1em;background:var(--red);vertical-align:-.18em;margin-left:1px;animation:blink .7s steps(1,end) infinite}
@keyframes blink{50%{opacity:0}}
.ac-pop{margin-top:10px;border:1px solid var(--rb);border-radius:9px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.07);overflow:hidden;opacity:0;transform:translateY(-5px) scale(.97);transition:.2s cubic-bezier(.16,1,.3,1)}
.ac-pop.show{opacity:1;transform:none}
.ac-hd{padding:4px 10px;background:rgba(0,0,0,.018);border-bottom:1px solid rgba(0,0,0,.05);font-family:"Outfit",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em}
.ac-it{display:flex;justify-content:space-between;align-items:center;padding:7px 11px;font-family:"JetBrains Mono",monospace;font-size:11px;color:#222}
.ac-it.sel{background:linear-gradient(90deg,rgba(var(--red-rgb),.06),transparent)}
.ac-cat{font-family:"Outfit",sans-serif;font-size:8px;padding:1px 6px;border-radius:3px;font-weight:700}
.ck{background:rgba(var(--red-rgb),.08);color:var(--red)}.ct{background:rgba(15,107,62,.08);color:var(--green)}.cc2{background:rgba(26,79,196,.08);color:var(--blue)}
.ast-right{padding:14px;display:flex;flex-direction:column;overflow:hidden}
.ast-ttl{font-family:"Outfit",sans-serif;font-size:8px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}
.ast-row{display:flex;align-items:center;gap:6px;font-family:"JetBrains Mono",monospace;font-size:10px;color:#555;padding:3px 6px;border-radius:5px;transition:background .15s}
.ast-row.hi{background:rgba(var(--red-rgb),.06);color:var(--red);font-weight:700}
.ast-row.ok{color:var(--green)}
.ast-tag{font-size:8px;padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Outfit",sans-serif;background:rgba(0,0,0,.05);color:#888}
.ast-lg{font-size:8px;color:var(--green);font-weight:700;font-family:"Outfit",sans-serif}
.ast-il{font-size:8px;color:#ef4444;font-weight:700;font-family:"Outfit",sans-serif}

/* Scene 2: FK Join Diagram */
.fk-dia{flex:1;padding:16px 20px;display:flex;align-items:center;justify-content:center;gap:0}
.fk-tbl{border:1px solid rgba(0,0,0,.1);border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);min-width:128px}
.fk-th{padding:7px 12px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-family:"Outfit",sans-serif;font-size:10px;font-weight:900;color:#555;text-transform:uppercase;letter-spacing:.06em}
.fk-r{padding:5px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;color:#333;border-bottom:1px solid rgba(0,0,0,.04);display:flex;align-items:center;gap:6px}
.fk-r:last-child{border-bottom:0}
.pk{font-size:8px;background:rgba(var(--red-rgb),.1);color:var(--red);padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Outfit",sans-serif}
.fk{font-size:8px;background:rgba(15,107,62,.1);color:var(--green);padding:1px 5px;border-radius:3px;font-weight:700;font-family:"Outfit",sans-serif}
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
.sp-unit{font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.sp-desc{font-family:"Outfit",sans-serif;font-size:9px;color:#bbb;margin-top:1px}
.sp-bar{height:3px;border-radius:99px;background:rgba(0,0,0,.06);margin-top:4px;overflow:hidden}
.sp-fill{height:100%;border-radius:99px;width:0;transition:width .6s ease} /* impeccable-disable-line layout-transition */
.sp-r{padding:20px;display:flex;flex-direction:column;gap:12px}
.cmp-card{border:1px solid rgba(0,0,0,.07);border-radius:10px;padding:12px 14px;background:#fff;display:flex;flex-direction:column;gap:8px}
.cmp-lbl{font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px}
.cmp-row{display:flex;align-items:center;gap:8px}
.cmp-name{font-family:"Outfit",sans-serif;font-size:10px;color:#555;font-weight:600;min-width:76px}
.cmp-bw{flex:1;height:6px;background:rgba(0,0,0,.06);border-radius:99px;overflow:hidden}
.cmp-bf{height:100%;border-radius:99px;transition:width .5s ease} /* impeccable-disable-line layout-transition */
.cmp-bf.hs{background:linear-gradient(90deg,var(--red),#a82525)}.cmp-bf.el{background:rgba(0,0,0,.15)}
.cmp-v{font-family:"JetBrains Mono",monospace;font-size:10px;color:#555;min-width:36px;text-align:right}

/* Scene 4: Hover Inspect */
.dt{height:32px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;border-bottom:1px solid rgba(0,0,0,.05);font-size:10px;color:#888;font-family:"Outfit",sans-serif;font-weight:600;flex-shrink:0}
.dt-ok{color:var(--green);font-weight:700}
.tg{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#2a2a2a}
.tg th{padding:6px 12px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-size:9px;font-weight:700;color:#777;text-align:left;white-space:nowrap}
.tg td{padding:6px 12px;border-bottom:1px solid rgba(0,0,0,.04);white-space:nowrap}
.fk-c{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:4px;background:rgba(var(--red-rgb),.05);box-shadow:inset 0 0 0 1px rgba(var(--red-rgb),.12);color:var(--red);font-weight:700;cursor:pointer;position:relative;transition:all .15s}
.fk-c.hl,.fk-c:hover{background:rgba(var(--red-rgb),.12);box-shadow:inset 0 0 0 1px rgba(var(--red-rgb),.3)}
.data-view.focus-fk th:nth-child(2),.data-view.focus-fk td:nth-child(2){background:rgba(var(--red-rgb),.055)}
.data-view.focus-fk th:nth-child(2){color:var(--red)}
.fk-pop{position:absolute;z-index:30;min-width:170px;border-radius:10px;border:1px solid rgba(15,107,62,.22);background:#fff;box-shadow:0 10px 32px rgba(0,0,0,.1);padding:10px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;opacity:0;transform:translateY(-4px) scale(.96);transition:.18s cubic-bezier(.16,1,.3,1);visibility:hidden;pointer-events:none}
.fk-pop.show{opacity:1;transform:none;visibility:visible}
.fkp-t{font-family:"Outfit",sans-serif;font-size:8px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;display:flex;align-items:center;gap:4px}
.fkp-r{display:flex;justify-content:space-between;gap:10px;color:#444;padding:1.5px 0}
.fkp-r span:first-child{color:#bbb}
.fkp-p{margin-top:5px;padding-top:4px;border-top:1px solid rgba(0,0,0,.05);font-size:9px;color:#bbb;font-family:"Outfit",sans-serif}
.ok-g{color:var(--green);font-weight:700}.ok-a{color:var(--amber)}.ok-r{color:#ef4444}

/* Scene 5: Virtual Grid */
#s5{display:flex;flex-direction:column;height:100%}
.vg-hd{border-bottom:1px solid rgba(0,0,0,.07);padding:10px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
.vg-ctr{font-family:"JetBrains Mono",monospace;font-size:11px;color:#555}
.vg-ctr strong{color:var(--green);font-size:13px}
.vg-badge{padding:3px 9px;border-radius:999px;background:rgba(15,107,62,.08);border:1px solid rgba(15,107,62,.15);font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:var(--green)}
.vg-grid{flex:1;overflow:hidden;position:relative}
.vg-t{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#2a2a2a}
.vg-t th{padding:5px 10px;background:rgba(0,0,0,.02);border-bottom:1px solid rgba(0,0,0,.07);font-size:9px;font-weight:700;color:#888;text-align:left}
.vg-t td{padding:5px 10px;border-bottom:1px solid rgba(0,0,0,.04);white-space:nowrap}
.vg-thumb{position:absolute;right:5px;top:0;width:3px;border-radius:99px;background:linear-gradient(var(--red),#a82525);box-shadow:0 0 6px rgba(var(--red-rgb),.3)}
.vg-m{position:absolute;bottom:14px;right:14px;background:rgba(255,255,255,.95);border:1px solid rgba(15,107,62,.18);border-radius:8px;padding:8px 12px;font-family:"JetBrains Mono",monospace;font-size:10px;display:flex;flex-direction:column;gap:3px;backdrop-filter:blur(6px);box-shadow:0 4px 16px rgba(0,0,0,.08);transition:all .3s}
.vm-r{display:flex;justify-content:space-between;gap:14px}
.vm-r span:first-child{color:#bbb}.vm-r span:last-child{color:var(--green);font-weight:700}

/* Scene 6: Plugin Manager (Mock workspace integration) */
#s6{display:grid;grid-template-columns:1fr 1fr;height:100%;background:#fafaf8}
.pm-l{border-right:1px solid rgba(0,0,0,.07);padding:14px;overflow-y:auto}
.pm-hd{font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px}
.pm-lst{display:flex;flex-direction:column;gap:8px}
.pm-it{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;transition:all .18s;font-family:"Outfit",sans-serif}
.pm-it.on{border-color:rgba(15,107,62,.2);background:rgba(15,107,62,.02)}
.pm-it:hover{border-color:rgba(0,0,0,.12)}
.pm-it.installing{border-color:rgba(var(--red-rgb),.22);background:rgba(var(--red-rgb),.025)}
.pm-layout{display:grid;grid-template-columns:minmax(250px,1.35fr) minmax(130px,.65fr);height:100%}
.pm-install{border:0;border-radius:6px;padding:6px 10px;background:var(--red);color:#fff;font:700 9px "Outfit",sans-serif;cursor:pointer;min-width:55px}
.pm-install.installing{background:#8b817a;color:#fff}
.pm-install.done{background:var(--green)}
.pm-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;background:rgba(0,0,0,.04);flex-shrink:0}
.pm-inf{flex:1}.pm-nm{font-size:11px;font-weight:700;color:#222}.pm-ds{font-size:9px;color:#aaa;margin-top:1px}
.pm-tg{width:28px;height:15px;border-radius:99px;border:none;cursor:pointer;position:relative;transition:background .18s;flex-shrink:0}
.pm-tg.on{background:var(--green)}.pm-tg.off{background:#ddd}
.pm-tg::after{content:"";position:absolute;top:2px;width:11px;height:11px;border-radius:50%;background:#fff;transition:left .18s}
.pm-tg.on::after{left:15px}.pm-tg.off::after{left:2px}
.pm-st{font-size:8px;padding:2px 6px;border-radius:999px;font-weight:700;font-family:"Outfit",sans-serif}
.pm-st.on{background:rgba(15,107,62,.1);color:var(--green)}.pm-st.off{background:rgba(0,0,0,.05);color:#bbb}
.pm-r{padding:14px;display:flex;flex-direction:column;gap:10px;overflow-y:auto}
.pm-sc{border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;padding:14px;display:flex;flex-direction:column;gap:6px}
.pm-sl{font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em}
.pm-sv{font-family:"JetBrains Mono",monospace;font-size:22px;font-weight:900;color:#0f0f0f;line-height:1}
.pm-ss{font-family:"Outfit",sans-serif;font-size:9px;color:var(--green);font-weight:600}
.pm-ac{border:1px solid rgba(0,0,0,.07);border-radius:10px;background:#fff;padding:12px}
.pm-at{font-family:"Outfit",sans-serif;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}
.pm-ar{display:flex;align-items:center;gap:8px;padding:4px 0;font-family:"JetBrains Mono",monospace;font-size:10px;color:#555}
.pm-ad{width:6px;height:6px;border-radius:50%;flex-shrink:0}

/* Mouse */
.mouse{position:fixed;z-index:9000;pointer-events:none;left:0;top:0}
.mouse-s{transition:transform .11s cubic-bezier(.1,.8,.2,1);transform-origin:0 0;filter:drop-shadow(0 5px 9px rgba(0,0,0,.13))}
.mouse.click .mouse-s{transform:scale(.82)}
.click-rip{position:fixed;border:2px solid rgba(var(--red-rgb),.7);border-radius:50%;pointer-events:none;z-index:8999;transform:translate(-50%,-50%);animation:rip-out .36s ease-out forwards}
@keyframes rip-out{from{width:0;height:0;opacity:1}to{width:34px;height:34px;opacity:0}}
.scroll-note{position:absolute;bottom:42px;left:50%;transform:translateX(-50%);z-index:50;color:#bbb;font-size:10px;letter-spacing:.12em;font-family:"Outfit",sans-serif;font-weight:700;text-transform:uppercase}

/* Modals */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.38);backdrop-filter:blur(10px);z-index:300;display:none;align-items:center;justify-content:center;font-family:"Outfit",sans-serif}
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

/* Enhanced motion layer */
@keyframes hsFadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes hsFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
@keyframes hsPopIn{from{opacity:0;transform:scale(.94) translateY(4px)}to{opacity:1;transform:scale(1) translateY(0)}}
@keyframes hsPulse{0%,100%{opacity:1}50%{opacity:.55}}

.wl-item{animation:hsFadeUp .45s cubic-bezier(.22,1,.36,1) backwards}
.wl-item:nth-child(1){animation-delay:.02s}.wl-item:nth-child(2){animation-delay:.07s}
.wl-item:nth-child(3){animation-delay:.12s}.wl-item:nth-child(4){animation-delay:.17s}
.wl-item:nth-child(5){animation-delay:.22s}.wl-item:nth-child(6){animation-delay:.27s}

.copy-scene.active h1{animation:hsFadeUp .55s cubic-bezier(.22,1,.36,1) backwards;animation-delay:.03s}
.copy-scene.active p{animation:hsFadeUp .55s cubic-bezier(.22,1,.36,1) backwards;animation-delay:.09s}
.copy-scene.active .kicker{animation:hsFadeUp .5s cubic-bezier(.22,1,.36,1) backwards}
.copy-scene.active .chips,.copy-scene.active .db-logos{animation:hsFadeUp .55s cubic-bezier(.22,1,.36,1) backwards;animation-delay:.15s}

.cc{will-change:transform}
.cc:hover{animation:hsFloat 1.6s ease-in-out infinite}

.fk-pop.show,.relation-pop.show,.ac-pop.show{animation:hsPopIn .22s cubic-bezier(.34,1.56,.64,1)}

.scroll-note{animation:hsPulse 1.8s ease-in-out infinite}

.tg tbody tr,.vg-t tbody tr{transition:background .15s ease}
.tg tbody tr:hover,.vg-t tbody tr:hover{background:rgba(var(--red-rgb),.035)}

.widget-box:not(.removed){animation:hsFadeUp .4s cubic-bezier(.22,1,.36,1) backwards}
.widget-box:nth-of-type(1){animation-delay:.05s}.widget-box:nth-of-type(2){animation-delay:.1s}

.run-btn:active{transform:translateY(1px) scale(.97)}
.download-btn:active,.btn-download:active{transform:translateY(0) scale(.98) !important}

.pm-install:not(.installing):not(.done):hover{filter:brightness(1.08)}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}
}

@media(max-width:980px){
  body{height:680vh}
  .stage{grid-template-columns:1fr;grid-template-rows:auto 1fr;padding:66px 13px 26px;gap:10px}
  .brand{top:15px;left:15px}.topbar{top:15px;right:13px}
  .progress{bottom:12px}.copy{min-height:110px}
  .copy p,.chips,.db-logos{display:none}
  h1{font-size:33px}.canvas{min-height:500px;height:60vh;overflow:hidden}
  .window{width:100%;overflow:hidden}
  .workspace-frame{grid-template-columns:140px 530px 180px;width:850px;min-width:850px;transform-origin:top left;transform:translateX(var(--camera-x,0px)) scale(var(--camera-scale,1))}
  .mouse{display:block;transform:scale(.82)}
  #featS1,#featS2,#featS3{width:390px}
  #featS6{left:10px;right:10px;top:10px;height:280px!important}
  .pm-layout{grid-template-columns:minmax(270px,1fr) 145px}
}
@media(max-width:620px){
  body{height:700vh}
  .stage{grid-template-columns:1fr;grid-template-rows:28vh 1fr;padding:68px 10px 20px;gap:4px}
  .copy{height:28vh;min-height:0}
  .copy-scene{top:48%}
  .kicker{font-size:8px;letter-spacing:.17em;margin-bottom:9px}
  h1{font-size:clamp(25px,7.2vw,29px);line-height:.98;letter-spacing:-.025em;max-width:350px}
  .copy p{display:block;font-size:11px;line-height:1.45;margin-top:10px;max-width:340px}
  .chips,.db-logos{display:none}
  .canvas{height:56vh;min-height:430px}
  .brand-name{font-size:18px}
  .brand-tag{font-size:6px;letter-spacing:.16em}
  .brand-logo{width:32px;height:32px}
  .menu-btn{width:34px;height:34px}
  .window.connection-mode{width:100%;height:96%;transform:none}
  #s0{padding:10px}
  #s0>div{grid-template-columns:1fr 1fr!important;gap:6px!important;padding:8px!important}
  #s0 .cg{padding:7px;gap:5px;border-radius:9px;min-width:0}
  #s0 .cc{padding:5px 6px;gap:5px;min-width:0}
  #s0 .cc-h{display:none}
  #s0 .cc-n{font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  #s0 .cc>div:nth-child(2){min-width:0;flex:1;overflow:hidden}
  #s0 .cc-p{font-size:7px;margin-left:auto}
  #s0 .dbi{display:none}
  #s0 .cg-n{font-size:0}
  #s0 .cg.dev .cg-n::after{content:"DEV";font-size:8px}
  #s0 .cg.prod .cg-n::after{content:"PROD";font-size:8px}
  #s0 .cg-b{display:none}
  #s0 .cg-hd{margin-bottom:0}
  .stage.after-intro{padding-top:16px;grid-template-rows:25vh 1fr}
  .stage.after-intro .canvas{height:66vh;min-height:480px}
  .run-btn{min-width:52px;min-height:34px;padding:7px 12px;font-size:10px}
  .widget-remove{width:32px;height:32px;display:grid;place-items:center;font-size:18px;flex:0 0 32px}
  .data-scrollbar{right:6px;width:9px}
  .data-scroll-thumb{width:7px}
  .tg th,.tg td{padding-left:8px;padding-right:8px}
  .workspace-frame{transform:translateX(var(--camera-x,-130px)) scale(var(--camera-scale,1))}
  .topbar{right:10px;top:12px}.brand{left:10px;top:12px}
  #featS6{height:300px!important}
  .pm-layout{grid-template-columns:1fr}
  .pm-r{display:none}
}
</style>
</head>
<body>

<!-- Background lotus watermark -->
<svg class="bg-lotus" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <linearGradient id="bgl" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#fbcfe8"/><stop offset="50%" stop-color="#ec4899"/><stop offset="100%" stop-color="#be185d"/>
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
  <a href="<?php echo esc_url(home_url('/')); ?>" class="brand" id="siteBrand">
    <img src="<?php echo get_stylesheet_directory_uri(); ?>/logo_svg.svg" width="32" height="32" alt="HoaSen Table logo" style="filter:drop-shadow(0 3px 10px rgba(var(--red-rgb),.22))"/>
    <div>
      <div class="brand-name">HoaSen Table</div>
      <div class="brand-tag" id="brandTag"><?php esc_html_e('Minimal DB Workspace', 'hoasen-theme'); ?></div>
    </div>
  </a>

  <!-- Top right -->
  <div class="topbar">
    <div class="menu-wrap">
      <button class="menu-btn" id="menuBtn" aria-label="Menu">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="menu-dd" id="menuDd">
        <a href="<?php echo esc_url( function_exists('hoasen_blog_url') ? hoasen_blog_url() : home_url('/blog/') ); ?>"><?php echo esc_html($t['blog']); ?></a>
        <a href="#" id="btnContact"><?php echo esc_html($t['contact']); ?></a>
        <?php if(function_exists('pll_the_languages')): ?>
        <div class="menu-lang">
          <div class="menu-lang-label">LANGUAGE</div>
          <div class="lang-sw"><ul><?php pll_the_languages(['show_flags'=>0,'show_names'=>1,'hide_current'=>0]); ?></ul></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="progress" id="progress"></div>

  <!-- Copy left -->
  <div class="copy">
    <section class="copy-scene active">
      <div class="kicker"><?php esc_html_e('ENTERPRISE-GRADE · ZERO BLOAT', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('The database workspace built to never slow you down.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('HoaSen Table pairs production-grade SQL tooling with obsessive craft. Connect, query, inspect, and move on — native-fast, distraction-free, no ceremony.', 'hoasen-theme'); ?></p>
        <div class="db-logos">
          <span class="db-logo" title="PostgreSQL">
            <svg viewBox="0 0 128 128"><path fill="#336791" d="M93.809 92.112c.785-6.533.55-7.492 5.416-6.433l1.235.108c3.742.17 8.637-.602 11.513-1.938 6.191-2.873 9.861-7.668 3.758-6.409-13.924 2.873-14.881-1.842-14.881-1.842 14.703-21.815 20.849-49.508 15.543-56.287-14.47-18.489-39.517-9.746-39.936-9.52l-.134.025c-2.751-.571-5.83-.912-9.289-.968-6.301-.104-11.082 1.652-14.709 4.402 0 0-44.683-18.409-42.604 23.151.442 8.841 12.672 66.898 27.26 49.362 5.332-6.412 10.484-11.834 10.484-11.834 2.558 1.699 5.622 2.567 8.834 2.255l.249-.212c-.078.796-.044 1.575.099 2.497-3.757 4.199-2.653 4.936-10.166 6.482-7.602 1.566-3.136 4.355-.221 5.084 3.535.884 11.712 2.136 17.238-5.598l-.22.882c1.474 1.18 1.375 8.477 1.583 13.69.209 5.214.558 10.079 1.621 12.948 1.063 2.868 2.317 10.256 12.191 8.14 8.252-1.764 14.561-4.309 15.136-27.985"/><path fill="#336791" d="M75.458 125.256c-4.367 0-7.211-1.689-8.938-3.32-2.607-2.46-3.641-5.629-4.259-7.522l-.267-.79c-1.244-3.358-1.666-8.193-1.916-14.419-.038-.935-.064-1.898-.093-2.919-.021-.747-.047-1.684-.085-2.664a18.8 18.8 0 01-4.962 1.568c-3.079.526-6.389.356-9.84-.507-2.435-.609-4.965-1.871-6.407-3.82-4.203 3.681-8.212 3.182-10.396 2.453-3.853-1.285-7.301-4.896-10.542-11.037-2.309-4.375-4.542-10.075-6.638-16.943-3.65-11.96-5.969-24.557-6.175-28.693C4.292 23.698 7.777 14.44 15.296 9.129 27.157.751 45.128 5.678 51.68 7.915c4.402-2.653 9.581-3.944 15.433-3.851 3.143.051 6.136.327 8.916.823 2.9-.912 8.628-2.221 15.185-2.139 12.081.144 22.092 4.852 28.949 13.615 4.894 6.252 2.474 19.381.597 26.651-2.642 10.226-7.271 21.102-12.957 30.57 1.544.011 3.781-.174 6.961-.831 6.274-1.295 8.109 2.069 8.607 3.575 1.995 6.042-6.677 10.608-9.382 11.864-3.466 1.609-9.117 2.589-13.745 2.377l-.202-.013-1.216-.107-.12 1.014-.116.991c-.311 11.999-2.025 19.598-5.552 24.619-3.697 5.264-8.835 6.739-13.361 7.709-1.544.33-2.947.474-4.219.474zm-9.19-43.671c2.819 2.256 3.066 6.501 3.287 14.434.028.99.054 1.927.089 2.802.106 2.65.355 8.855 1.327 11.477.137.371.26.747.39 1.146 1.083 3.316 1.626 4.979 6.309 3.978 3.931-.843 5.952-1.599 7.534-3.851 2.299-3.274 3.585-9.86 3.821-19.575l4.783.116-4.75-.57.14-1.186c.455-3.91.783-6.734 3.396-8.602 2.097-1.498 4.486-1.353 6.389-1.01-2.091-1.58-2.669-3.433-2.823-4.193l-.399-1.965 1.121-1.663c6.457-9.58 11.781-21.354 14.609-32.304 2.906-11.251 2.02-17.226 1.134-18.356-11.729-14.987-32.068-8.799-34.192-8.097l-.359.194-1.8.335-.922-.191c-2.542-.528-5.366-.82-8.393-.869-4.756-.08-8.593 1.044-11.739 3.431l-2.183 1.655-2.533-1.043c-5.412-2.213-21.308-6.662-29.696-.721-4.656 3.298-6.777 9.76-6.305 19.207.156 3.119 2.275 14.926 5.771 26.377 4.831 15.825 9.221 21.082 11.054 21.693.32.108 1.15-.537 1.976-1.529a270.708 270.708 0 0 1 10.694-12.07l2.77-2.915 3.349 2.225c1.35.897 2.839 1.406 4.368 1.502l7.987-6.812-1.157 11.808c-.026.265-.039.626.065 1.296l.348 2.238-1.51 1.688-.174.196 4.388 2.025 1.836-2.301z"/><path fill="#336791" d="M115.731 77.44c-13.925 2.873-14.882-1.842-14.882-1.842 14.703-21.816 20.849-49.51 15.545-56.287C101.924.823 76.875 9.566 76.457 9.793l-.135.024c-2.751-.571-5.83-.911-9.291-.967-6.301-.103-11.08 1.652-14.707 4.402 0 0-44.684-18.408-42.606 23.151.442 8.842 12.672 66.899 27.26 49.363 5.332-6.412 10.483-11.834 10.483-11.834 2.559 1.699 5.622 2.567 8.833 2.255l.25-.212c-.078.796-.042 1.575.1 2.497-3.758 4.199-2.654 4.936-10.167 6.482-7.602 1.566-3.136 4.355-.22 5.084 3.534.884 11.712 2.136 17.237-5.598l-.221.882c1.473 1.18 2.507 7.672 2.334 13.557-.174 5.885-.29 9.926.871 13.082 1.16 3.156 2.316 10.256 12.192 8.14 8.252-1.768 12.528-6.351 13.124-13.995.422-5.435 1.377-4.631 1.438-9.49l.767-2.3c.884-7.367.14-9.743 5.225-8.638l1.235.108c3.742.17 8.639-.602 11.514-1.938 6.19-2.871 9.861-7.667 3.758-6.408z"/><path fill="#fff" d="M75.957 122.307c-8.232 0-10.84-6.519-11.907-9.185-1.562-3.907-1.899-19.069-1.551-31.503a1.59 1.59 0 011.64-1.55 1.594 1.594 0 011.55 1.639c-.401 14.341.168 27.337 1.324 30.229 1.804 4.509 4.54 8.453 12.275 6.796 7.343-1.575 10.093-4.359 11.318-11.46.94-5.449 2.799-20.951 3.028-24.01a1.593 1.593 0 011.71-1.472 1.597 1.597 0 011.472 1.71c-.239 3.185-2.089 18.657-3.065 24.315-1.446 8.387-5.185 12.191-13.794 14.037-1.463.313-2.792.453-4 .454"/></svg>
            <span>PostgreSQL</span>
          </span>
          <span class="db-logo" title="MySQL">
            <svg viewBox="0 0 128 128"><path fill="#00618A" d="M117.688 98.242c-6.973-.191-12.297.461-16.852 2.379-1.293.547-3.355.559-3.566 2.18.711.746.82 1.859 1.387 2.777 1.086 1.754 2.922 4.113 4.559 5.352 1.789 1.348 3.633 2.793 5.551 3.961 3.414 2.082 7.223 3.27 10.504 5.352 1.938 1.23 3.859 2.777 5.75 4.164.934.684 1.563 1.75 2.773 2.18v-.195c-.637-.812-.801-1.93-1.387-2.777l-2.578-2.578c-2.52-3.344-5.719-6.281-9.117-8.719-2.711-1.949-8.781-4.578-9.91-7.73l-.199-.199c1.922-.219 4.172-.914 5.949-1.391 2.98-.797 5.645-.59 8.719-1.387l4.164-1.187v-.793c-1.555-1.594-2.664-3.707-4.359-5.152-4.441-3.781-9.285-7.555-14.273-10.703-2.766-1.746-6.184-2.883-9.117-4.363-.988-.496-2.719-.758-3.371-1.586-1.539-1.961-2.379-4.449-3.566-6.738-2.488-4.793-4.93-10.023-7.137-15.066-1.504-3.437-2.484-6.828-4.359-9.91-9-14.797-18.687-23.73-33.695-32.508-3.195-1.867-7.039-2.605-11.102-3.57l-6.543-.395c-1.332-.555-2.715-2.184-3.965-2.977C16.977 3.52 4.223-3.312.539 5.672-1.785 11.34 4.016 16.871 6.09 19.746c1.457 2.012 3.32 4.273 4.359 6.539.688 1.492.805 2.984 1.391 4.559 1.438 3.883 2.695 8.109 4.559 11.695.941 1.816 1.98 3.727 3.172 5.352.727.996 1.98 1.438 2.18 2.973-1.227 1.715-1.297 4.375-1.984 6.543-3.098 9.77-1.926 21.91 2.578 29.137 1.383 2.223 4.641 6.98 9.117 5.156 3.918-1.598 3.043-6.539 4.164-10.902.254-.988.098-1.715.594-2.379v.199l3.57 7.133c2.641 4.254 7.324 8.699 11.297 11.699 2.059 1.555 3.68 4.242 6.344 5.152v-.199h-.199c-.516-.805-1.324-1.137-1.98-1.781-1.551-1.523-3.277-3.414-4.559-5.156-3.613-4.902-6.805-10.27-9.711-15.855-1.391-2.668-2.598-5.609-3.77-8.324-.453-1.047-.445-2.633-1.387-3.172-1.281 1.988-3.172 3.598-4.164 5.945-1.582 3.754-1.789 8.336-2.375 13.082-.348.125-.195.039-.398.199-2.762-.668-3.73-3.508-4.758-5.949-2.594-6.164-3.078-16.09-.793-23.191.59-1.836 3.262-7.617 2.18-9.316-.516-1.691-2.219-2.672-3.172-3.965-1.18-1.598-2.355-3.703-3.172-5.551-2.125-4.805-3.113-10.203-5.352-15.062-1.07-2.324-2.875-4.676-4.359-6.738-1.645-2.289-3.484-3.977-4.758-6.742-.453-.984-1.066-2.559-.398-3.566.215-.684.516-.969 1.191-1.191 1.148-.887 4.352.297 5.547.793 3.18 1.32 5.832 2.578 8.527 4.363 1.289.855 2.598 2.512 4.16 2.973h1.785c2.789.641 5.914.195 8.523.988 4.609 1.402 8.738 3.582 12.488 5.949 11.422 7.215 20.766 17.48 27.156 29.734 1.027 1.973 1.473 3.852 2.379 5.945 1.824 4.219 4.125 8.559 5.941 12.688 1.816 4.113 3.582 8.27 6.148 11.695 1.348 1.801 6.551 2.766 8.918 3.766 1.66.699 4.379 1.43 5.949 2.379 3 1.809 5.906 3.965 8.723 5.945 1.402.992 5.73 3.168 5.945 4.957zm-88.605-75.52c-1.453-.027-2.48.156-3.566.395v.199h.195c.695 1.422 1.918 2.34 2.777 3.566l1.98 4.164.199-.195c1.227-.867 1.789-2.25 1.781-4.363-.492-.52-.562-1.164-.992-1.785-.562-.824-1.66-1.289-2.375-1.98zm0 0"/></svg>
            <span>MySQL</span>
          </span>
          <span class="db-logo" title="SQLite">
            <svg viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sqlite-original-a" x1="-15.615" x2="-6.741" y1="-9.108" y2="-9.108" gradientTransform="rotate(90 -90.486 64.634) scale(9.2712)" gradientUnits="userSpaceOnUse"><stop stop-color="#95d7f4" offset="0"/><stop stop-color="#0f7fcc" offset=".92"/><stop stop-color="#0f7fcc" offset="1"/></linearGradient></defs><path d="M69.5 99.176c-.059-.73-.094-1.2-.094-1.2S67.2 83.087 64.57 78.642c-.414-.707.043-3.594 1.207-7.88.68 1.169 3.54 6.192 4.118 7.81.648 1.824.78 2.347.78 2.347s-1.57-8.082-4.144-12.797a162.286 162.286 0 012.004-6.265c.973 1.71 3.313 5.859 3.828 7.3.102.293.192.543.27.774.023-.137.05-.274.074-.414-.59-2.504-1.75-6.86-3.336-10.082 3.52-18.328 15.531-42.824 27.84-53.754H16.9c-5.387 0-9.789 4.406-9.789 9.789v88.57c0 5.383 4.406 9.789 9.79 9.789h52.897a118.657 118.657 0 01-.297-14.652" fill="#0b7fcc"/><path d="M65.777 70.762c.68 1.168 3.54 6.188 4.117 7.809.649 1.824.781 2.347.781 2.347s-1.57-8.082-4.144-12.797a164.535 164.535 0 012.004-6.27c.887 1.567 2.922 5.169 3.652 6.872l.082-.961c-.648-2.496-1.633-5.766-2.898-8.328 3.242-16.871 13.68-38.97 24.926-50.898H16.899a6.94 6.94 0 00-6.934 6.933v82.11c17.527-6.731 38.664-12.88 56.855-12.614-.672-2.605-1.441-4.96-2.25-6.324-.414-.707.043-3.597 1.207-7.879" fill="url(#sqlite-original-a)"/><path d="M115.95 2.781c-5.5-4.906-12.164-2.933-18.734 2.899a44.347 44.347 0 00-2.914 2.859c-11.25 11.926-21.684 34.023-24.926 50.895 1.262 2.563 2.25 5.832 2.894 8.328.168.64.32 1.242.442 1.754.285 1.207.437 1.996.437 1.996s-.101-.383-.515-1.582c-.078-.23-.168-.484-.27-.773-.043-.125-.105-.274-.172-.434-.734-1.703-2.765-5.305-3.656-6.867-.762 2.25-1.437 4.36-2.004 6.265 2.578 4.715 4.149 12.797 4.149 12.797s-.137-.523-.782-2.347c-.578-1.621-3.441-6.64-4.117-7.809-1.164 4.281-1.625 7.172-1.207 7.88.809 1.362 1.574 3.722 2.25 6.323 1.524 5.867 2.586 13.012 2.586 13.012s.031.469.094 1.2a118.653 118.653 0 00.297 14.651c.504 6.11 1.453 11.363 2.664 14.172l.828-.449c-1.781-5.535-2.504-12.793-2.188-21.156.48-12.793 3.422-28.215 8.856-44.289 9.191-24.27 21.938-43.738 33.602-53.035-10.633 9.602-25.023 40.684-29.332 52.195-4.82 12.891-8.238 24.984-10.301 36.574 3.55-10.863 15.047-15.53 15.047-15.53s5.637-6.958 12.227-16.888c-3.95.903-10.43 2.442-12.598 3.352-3.2 1.344-4.067 1.8-4.067 1.8s10.371-6.312 19.27-9.171c12.234-19.27 25.562-46.648 12.141-58.621" fill="#003956"/></svg>
            <span>SQLite</span>
          </span>
          <span class="db-logo" title="SQL Server">
            <svg viewBox="0 0 128 128"><defs><linearGradient id="mssql-a" x1="-2901.9519" x2="-2061.249" y1="923.573" y2="1420.3311" gradientTransform="matrix(.01102 0 0 -.01102 56.808 125.521)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#909ca9"/><stop offset="1" stop-color="#ededee"/></linearGradient><linearGradient id="mssql-b" x1="-2882.7" x2="-2206.249" y1="10288.81" y2="10288.81" gradientTransform="matrix(.01102 0 0 -.01102 56.808 125.521)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#939fab"/><stop offset="1" stop-color="#dcdee1"/></linearGradient><radialGradient id="mssql-c" cx="-14217.448" cy="7277.7051" r="898.12" gradientTransform="matrix(-.01059 -.0016 -.00321 .02118 -64.462 -130.43)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ee352c"/><stop offset="1" stop-color="#a91d22"/></radialGradient></defs><path fill="url(#mssql-a)" d="m79.363 59.755-25.634 8.37-22.3 9.842-6.24 1.648a135.666 135.666 0 0 1-5.057 4.592c-1.976 1.704-3.816 3.255-5.23 4.378-1.57 1.24-3.895 3.565-5.077 5.038-1.764 2.209-3.158 4.553-3.759 6.355-1.066 3.255-.542 6.549 1.511 9.591 2.636 3.875 7.886 7.828 14.008 10.52 3.12 1.377 8.37 3.14 12.324 4.127 6.567 1.667 19.278 3.47 26.272 3.74 1.414.059 3.313.059 3.39 0 .156-.097 1.241-2.17 2.501-4.746 4.3-8.778 7.4-17.012 9.087-24.046 1.007-4.262 1.801-9.939 2.324-16.662.136-1.88.194-8.177.078-10.308-.175-3.487-.485-6.316-.97-9.086-.077-.408-.096-.776-.057-.796.077-.057.31-.135 3.468-1.046l-.639-1.51zm-5.851 3.43c.233 0 .852 5.947 1.007 9.706.039.795.02 1.318-.02 1.318-.154 0-3.274-1.84-5.501-3.236-1.938-1.22-5.62-3.661-6.2-4.127-.195-.135-.176-.155 1.413-.697 2.693-.91 9.088-2.965 9.3-2.965zm-13.06 4.3c.175 0 .62.252 1.686.911 3.991 2.5 9.417 5.523 11.742 6.53.716.31.794.193-.853 1.318-3.526 2.402-7.924 4.766-13.31 7.149-.95.426-1.745.755-1.764.755-.039 0 .078-.484.233-1.065 1.297-4.825 2.034-9.707 2.073-13.621.02-1.938.02-1.938.194-1.996-.04.02-.02.02 0 .02zm-2.692 1.027c.116.117.038 4.457-.117 5.639a49.361 49.361 0 0 1-1.782 8.428c-.213.717-.407 1.318-.446 1.356-.078.097-2.732-2.5-3.604-3.507-1.511-1.744-2.693-3.487-3.565-5.192-.445-.872-1.143-2.577-1.085-2.635.31-.214 10.521-4.166 10.599-4.089zm-12.672 4.98c.019 0 .038 0 .058.019.039.039.175.35.291.698.62 1.685 2.014 4.165 3.216 5.754 1.318 1.744 3.042 3.605 4.476 4.825.465.387.891.755.949.813.116.117.155.097-3.004 1.299-3.66 1.395-7.652 2.79-12.225 4.262a609.837 609.837 0 0 0-3.274 1.066c-.175.058-.116-.04.387-.834 2.267-3.544 5.715-10.5 7.653-15.422.33-.853.66-1.705.718-1.899.077-.271.174-.368.425-.504.136-.038.272-.077.33-.077zM41.213 75.1c.058.039-.93 2.112-1.899 4.01-1.88 3.663-3.933 7.267-6.684 11.646-.466.755-.91 1.453-.97 1.53-.096.136-.134.097-.445-.503-.659-1.299-1.201-2.965-1.492-4.496-.29-1.511-.232-4.146.098-5.774.25-1.2.232-1.181.813-1.472 2.48-1.26 10.502-5.018 10.58-4.941zm33.422 1.357v.813c0 4.321-.465 10.25-1.143 14.57-.116.756-.213 1.376-.232 1.396 0 0-.562-.155-1.22-.349a49.985 49.985 0 0 1-8.914-3.817c-1.88-1.027-4.61-2.713-4.533-2.79.019-.02.833-.446 1.782-.95 3.798-1.976 7.44-4.107 10.599-6.22 1.182-.794 2.964-2.072 3.351-2.421zm-48.05 5.734c.077 0 .057.155-.059.853a27.507 27.507 0 0 0-.213 2.072c-.155 2.83.31 4.923 1.705 7.79.388.794.698 1.453.678 1.472-.135.117-12.962 3.876-16.992 4.98-1.201.33-2.247.62-2.325.639-.136.04-.155.02-.097-.31.446-2.848 2.616-6.568 5.639-9.707 2.014-2.093 3.623-3.313 6.374-4.882 1.976-1.124 5.018-2.81 5.25-2.887 0-.02.02-.02.04-.02zm30.225 5.406c.02-.02.484.233 1.046.562 4.147 2.403 9.92 4.631 14.841 5.774l.446.097-.62.349c-2.576 1.434-11.044 4.96-19.704 8.195-1.26.465-2.5.93-2.732 1.027-.233.097-.446.155-.446.135 0-.02.349-.697.794-1.53 2.422-4.534 4.863-10.056 6.104-13.892.155-.368.251-.697.27-.717zm-3.08 1.007c.019.02-.136.427-.33.892-1.686 4.088-3.895 8.545-6.724 13.543-.716 1.28-1.317 2.306-1.336 2.306-.02 0-.601-.349-1.299-.775-4.107-2.519-7.75-5.619-10.132-8.622l-.35-.426 1.764-.485c6.316-1.724 11.683-3.584 17.011-5.87.756-.31 1.376-.563 1.395-.563zm19.142 6.685s.02.02 0 0c.02.446-.969 4.437-1.783 7.324-.678 2.422-1.259 4.32-2.325 7.672-.464 1.474-.87 2.693-.89 2.693-.02 0-.136-.018-.253-.057-5.754-1.047-10.908-2.5-15.752-4.437-1.356-.543-3.293-1.415-3.41-1.512-.038-.039 1.124-.581 2.597-1.22 8.816-3.856 17.96-8.235 21.1-10.114.368-.233.658-.349.716-.349zM28.677 96.8c.039.04-2.422 3.585-5.87 8.41-1.202 1.685-2.597 3.661-3.12 4.397a77.468 77.468 0 0 0-1.763 2.597l-.814 1.26-.872-.737c-1.027-.853-2.809-2.674-3.604-3.681-1.666-2.073-2.79-4.263-3.235-6.258-.214-.93-.214-1.396-.02-1.453a1459.3 1459.3 0 0 1 10.308-2.423 861.65 861.65 0 0 0 6.936-1.627c1.124-.271 2.035-.485 2.054-.485zm2.48.95.62.697c2.79 3.12 5.638 5.426 9.087 7.44.62.35 1.085.66 1.046.68-.135.096-11.974 4.3-17.457 6.199a462.501 462.501 0 0 1-5.638 1.957c-.019 0-.194-.117-.387-.252l-.349-.252.562-.814c1.82-2.635 4.107-5.522 9.086-11.528zm15.462 11.063c.019-.02.871.29 1.918.679 2.519.949 4.514 1.55 7.188 2.228 3.294.833 8.06 1.647 10.87 1.88.426.038.658.077.581.135-.136.077-2.984 1.027-5.076 1.685-3.333 1.047-13.505 4.05-21.798 6.433a218.735 218.735 0 0 1-2.925.834c-.194.038-.834-.137-.834-.214 0-.038.465-.639 1.027-1.298 2.79-3.333 5.561-7.053 7.867-10.579.64-.969 1.182-1.764 1.182-1.783zm-3.41.097c.019.02-1.357 2.228-3.76 6.026-1.026 1.608-2.17 3.43-2.576 4.069-.388.62-.97 1.589-1.298 2.131l-.562.988-.291-.077c-.698-.194-5.6-1.919-6.898-2.442a48.226 48.226 0 0 1-4.514-2.072c-1.55-.834-3.487-2.074-3.332-2.113.038-.02 2.693-.736 5.89-1.608 8.485-2.306 13.194-3.642 16.275-4.611.562-.175 1.046-.31 1.065-.29zm24.122 5.657h.02c.077.195-3.062 8.913-4.206 11.664-.251.62-.348.776-.484.756-.329-.02-4.882-.658-7.653-1.065-4.824-.736-12.924-2.151-14.957-2.616l-.466-.097 2.887-.659c6.2-1.395 9.184-2.15 12.207-3.08a86.251 86.251 0 0 0 11.412-4.399c.6-.27 1.104-.484 1.24-.503z"/><path fill="url(#mssql-b)" d="M52.935.001c-.426-.058-7.305 2.422-11.741 4.224-5.988 2.441-10.637 4.766-13.505 6.781-1.066.756-2.403 2.093-2.616 2.616a1.812 1.812 0 0 0-.116.659l2.597 2.46 6.18 1.977 14.706 2.635 16.817 2.887.175-1.453c-.058 0-.097-.02-.155-.02l-2.209-.348-.445-.795c-2.287-4.03-4.805-9.029-6.278-12.4-1.142-2.616-2.228-5.638-2.828-7.808C53.187.098 53.149.02 52.935 0Zm-.31.988h.02c.019.02.096.563.174 1.202.33 2.712.93 5.328 1.88 8.157.716 2.13.716 2.015-.117 1.763-1.976-.542-10.83-2.073-17.244-2.965-1.027-.135-1.899-.27-1.899-.29-.077-.078 4.63-2.538 6.704-3.507 2.654-1.22 9.94-4.263 10.482-4.36ZM33.947 9.67l.756.252c4.108 1.395 14.434 3.372 20.131 3.837.639.058 1.182.116 1.2.116.02.02-.522.31-1.22.639-2.751 1.376-5.774 3.062-7.866 4.36-.62.387-1.182.698-1.26.698-.077 0-.484-.078-.91-.137l-.775-.116-1.938-1.899a803.532 803.532 0 0 0-7.11-6.84zm-.775.601 2.732 3.41c1.492 1.88 3.004 3.72 3.333 4.127.33.407.6.736.58.756-.077.058-3.952-.698-6.005-1.162-2.112-.485-2.984-.718-4.282-1.125l-1.066-.349v-.27c.02-1.3 1.667-3.237 4.456-5.212zm23.212 4.65c.077 0 .174.174.406.697.66 1.453 2.713 5.367 3.217 6.123.155.252.426.272-2.306-.174-6.568-1.066-8.68-1.415-8.68-1.453 0-.02.194-.155.446-.291 2.035-1.124 4.088-2.557 5.91-4.088.445-.368.852-.717.93-.775.019-.039.057-.058.077-.039z"/><path fill="url(#mssql-c)" d="M25.209 13.35s-.426.679-.02 1.687c.252.62.988 1.375 1.822 2.15 0 0 8.621 8.409 9.668 9.61 4.766 5.503 6.84 10.928 7.033 18.407.117 4.805-.794 9.029-3.061 13.931-4.03 8.796-12.536 18.504-25.653 29.276l1.918-.64c1.24-.93 2.926-1.917 6.879-4.087 9.125-5 19.394-9.591 31.988-14.32 18.135-6.82 47.954-14.802 64.926-17.398l1.764-.271-.272-.427c-1.55-2.403-2.616-3.894-3.895-5.483-3.72-4.611-8.233-8.35-13.756-11.45-7.595-4.244-17.418-7.557-29.857-10.017-2.345-.466-7.499-1.357-11.684-1.996a1193.72 1193.72 0 0 1-20.925-3.41c-2.267-.388-5.658-.969-7.905-1.454-1.163-.252-3.39-.775-5.134-1.375-1.395-.543-3.41-1.085-3.837-2.732Zm4.999 4.844c.019-.018.329.098.736.233a50.336 50.336 0 0 0 2.81.853 142.908 142.908 0 0 0 2.557.678c1.162.29 2.131.561 2.15.561.136.136 2.093 6.394 2.752 8.797.252.91.446 1.685.427 1.685-.02.02-.233-.31-.485-.755-2.267-3.991-5.851-8.04-9.998-11.296-.542-.387-.95-.736-.95-.756Zm9.532 2.636c.098 0 .524.058 1.047.174 3.293.736 9.203 1.86 12.98 2.5.64.097 1.144.213 1.144.251 0 .04-.232.175-.523.33-.64.329-3.216 1.86-4.069 2.44-2.15 1.435-4.088 2.985-5.483 4.38-.562.562-1.046 1.027-1.046 1.027s-.116-.33-.214-.736c-.697-2.694-2.15-6.685-3.468-9.495-.213-.445-.387-.852-.387-.89 0 .038 0 .019.02.019zm16.78 3.196c.116.04.31.698.697 2.151a31.732 31.732 0 0 1 .93 8.874c-.039.814-.078 1.57-.117 1.667l-.058.193-1.007-.33c-2.073-.658-5.444-1.646-8.331-2.46-1.647-.446-2.984-.852-2.984-.89 0-.117 2.403-2.52 3.43-3.43 1.956-1.725 7.265-5.832 7.44-5.775zm1.336.194c.058-.058 8.022 1.317 11.645 2.015 2.694.523 6.607 1.337 6.84 1.434.115.039-.291.27-1.59.853-5.115 2.305-8.912 4.378-12.69 6.897-.988.659-1.822 1.202-1.84 1.202-.02 0-.04-.562-.04-1.24 0-3.681-.735-7.402-2.092-10.54-.136-.31-.252-.601-.233-.62zm20.596 4.07c.058.057-.193 1.627-.426 2.557-.698 2.887-2.577 7.169-4.882 11.199-.408.717-.776 1.298-.815 1.317-.038.02-.56-.271-1.162-.62-2.247-1.318-4.805-2.557-7.595-3.72-.775-.33-1.453-.6-1.472-.64-.136-.115 6.103-4.242 9.396-6.219 2.617-1.589 6.88-3.952 6.956-3.875zm1.473.232c.174 0 3.7.968 5.541 1.511 4.553 1.356 9.785 3.274 13.195 4.824l1.414.64-.988.232c-8.33 1.918-15.461 4.128-22.34 6.917-.562.233-1.066.427-1.104.427-.039 0 .155-.446.407-.988 2.073-4.399 3.41-8.99 3.74-12.905.019-.368.077-.658.135-.658zm-35.108 8.06c.058-.058 2.75.581 4.204.988 2.21.62 6.898 2.19 6.898 2.305 0 .02-.523.466-1.143 1.008-2.538 2.112-4.98 4.34-7.906 7.169-.871.833-1.607 1.511-1.646 1.511-.04 0-.058-.116-.04-.271.446-3.255.35-7.44-.27-11.683-.059-.543-.117-1.008-.098-1.027zm56.595.058c.038.039-1.24 2.053-2.054 3.196-1.162 1.667-2.868 3.876-6.723 8.72a1289.453 1289.453 0 0 0-5.076 6.413c-.775.969-1.414 1.782-1.435 1.782-.018 0-.27-.348-.542-.774-2.17-3.256-4.766-6.104-7.847-8.661a44.534 44.534 0 0 0-1.433-1.163c-.214-.155-.388-.31-.388-.33 0-.057 3.293-1.472 5.793-2.479 4.38-1.783 10.346-3.914 14.823-5.29 2.344-.736 4.843-1.453 4.882-1.414zm1.492.387c.077-.019.543.214 1.104.543 4.709 2.693 9.32 6.162 12.962 9.726 1.027 1.008 3.566 3.643 3.527 3.662 0 0-.892.078-1.938.155-8.157.62-18.6 2.344-28.636 4.766-.679.155-1.28.29-1.318.29-.038 0 .717-.755 1.667-1.665 5.89-5.677 8.583-9.261 11.76-15.656.446-.948.833-1.762.872-1.82-.02 0-.02 0 0 0zm-43.149 4.418c.271.058 2.79 1.24 4.689 2.19 1.744.871 4.36 2.266 4.495 2.383.02.019-.91.503-2.054 1.066a135.032 135.032 0 0 0-10.017 5.521c-.93.562-1.705 1.027-1.724 1.027-.078 0-.058-.078.465-1.027 1.744-3.177 3.139-6.975 3.933-10.676.077-.29.155-.484.213-.484zm-2.519.465c.058.058-.6 2.441-1.007 3.74-.795 2.46-2.132 5.54-3.43 7.866-.31.542-.775 1.337-1.027 1.782l-.484.775-1.085-1.046c-1.26-1.22-2.286-1.976-3.603-2.655-.524-.27-.931-.503-.931-.542 0-.155 3.314-3.158 5.852-5.328 1.82-1.57 5.657-4.65 5.715-4.592zm15.404 6.336.95.62c2.17 1.415 4.727 3.294 6.684 4.94 1.104.91 3.235 2.83 3.662 3.294l.232.252-1.57.446c-8.873 2.46-15.732 4.65-23.734 7.595-.892.33-1.647.6-1.705.6-.116 0-.213.097 1.783-1.744 5.115-4.707 9.648-9.9 13.02-14.957zm-4.05 1.007c.04.039-2.615 3.778-4.204 5.89-1.899 2.519-5.27 6.743-7.596 9.494-.968 1.144-1.8 2.093-1.84 2.112-.058.02-.078-.27-.078-.717 0-2.344-.6-4.844-1.646-6.975-.446-.891-.524-1.104-.426-1.201.368-.33 6.006-3.546 9.57-5.464 2.404-1.279 6.162-3.177 6.22-3.139zM44.1 55.26c.058 0 .503.232 1.008.503a21.28 21.28 0 0 1 3.332 2.248c.039.038-.465.446-1.124.93-1.84 1.317-4.63 3.43-6.258 4.728-1.705 1.356-1.763 1.394-1.57 1.104 1.28-1.957 1.919-3.061 2.597-4.476a36.066 36.066 0 0 0 1.627-4.05c.155-.56.349-.987.388-.987zm6.53 5.114c.096-.018.213.156.735.931 1.104 1.647 1.957 3.856 2.17 5.638l.04.387-2.655 1.028c-4.747 1.84-9.126 3.661-12.09 5.018a217.066 217.066 0 0 0-3.236 1.55c-.95.484-1.724.852-1.724.833 0-.02.6-.465 1.336-1.008C41 70.547 46.018 65.935 49.777 61.324c.407-.484.775-.93.813-.949zm-3.004.737c.078.077-2.131 2.577-3.642 4.108-3.74 3.816-7.44 6.8-12.032 9.706-.582.368-1.105.698-1.163.736-.135.078.038-.116 2.054-2.305a52.694 52.694 0 0 0 3.352-3.972c.736-.95.871-1.085 1.937-1.84 2.849-2.055 9.417-6.511 9.494-6.434z"/></svg>
            <span>SQL Server</span>
          </span>
        </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('AI-POWERED · SCHEMA AWARE', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Real intelligence that anticipates your next query.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('HoaSen reads your live schema, tracks query context in real time, and applies machine learning that adapts to how you actually work — suggestions that feel like they read your mind.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Schema-aware', 'hoasen-theme'); ?></span>
        <span class="chip go"><?php esc_html_e('Context-aware', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Learns your habits', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('RESULTS STAY CLOSE', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Blazing-fast execution. Zero context switching.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Results render instantly inside your active workspace. Peek at foreign key relations on hover — no subqueries, no tab-hopping, pure uninterrupted flow.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Inline results', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('No tab hopping', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('MASSIVE SCALE · EXTREME SPEED', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('A million rows at 60 FPS, without breaking a sweat.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Open and scroll through millions of rows instantly. Our virtualized grid renders a constant 23 DOM nodes — never more — so the workspace stays smooth and responsive under any load.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Instant rendering', 'hoasen-theme'); ?></span>
        <span class="chip go"><?php esc_html_e('Zero memory bloat', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Smooth scrolling', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('PLUGIN ECOSYSTEM · LIMITLESS', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Build the perfect environment around your workflow.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Plugins are fully custom and live inside the workspace, so teams can ship the exact tools they need — no forked context, no lost query, no compromise.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Fully custom', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Team workflows', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('CREATIVE CONTROL · STILL MINIMAL', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('Turn widgets into your team\'s edge.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Start minimal, then add exactly the power you need. Rearrange widgets to surface the context your workflow demands — nothing more, nothing less.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Creative control', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Still minimal', 'hoasen-theme'); ?></span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker"><?php esc_html_e('FOR TEAMS THAT MOVE FAST', 'hoasen-theme'); ?></div>
      <h1><?php esc_html_e('The sharpest SQL client for teams that move fast.', 'hoasen-theme'); ?></h1>
      <p><?php esc_html_e('Built by engineers, for engineers. Read the guide, dig into the docs, or tell us where your workflow slows — we\'re obsessed with making you faster.', 'hoasen-theme'); ?></p>
      <div class="chips">
        <span class="chip hot"><?php esc_html_e('Docs', 'hoasen-theme'); ?></span>
        <span class="chip"><?php esc_html_e('Engineering notes', 'hoasen-theme'); ?></span>
        <span class="chip go"><?php esc_html_e('Talk to us', 'hoasen-theme'); ?></span>
      </div>
    </section>
  </div>

  <!-- App window mockup -->
  <div class="canvas" id="canvas">
    <div class="window connection-mode" id="appWindow">
      <div class="winbar">
        <div class="traf"><i class="r"></i><i class="y"></i><i class="g"></i></div>
        <span class="wpath" id="winPath"><strong>HoaSen Table</strong> · Connection Board</span>
        <div class="wb-tags">
          <span class="wb-tag" id="winBtnPlugins" style="cursor:pointer;background:rgba(var(--red-rgb),.07)">🔌 Plugins</span>
        </div>
      </div>

      <div class="sc-container">
        <!-- Scene 0: Connection Board (Full screen connection cards) -->
        <div class="sc show" id="s0">
          <div style="padding:28px;background:#fafaf8;display:grid;grid-template-columns:1fr 1fr;gap:18px;height:100%;width:100%">
            <div class="cg dev">
              <div class="cg-hd"><span class="cg-n">Development</span><span class="cg-b dev">DEV</span></div>
              <div class="cc"><div class="dbi my" style="background:#00758f;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 128 128" width="18" height="18" fill="currentColor" style="color:#fff"><path fill="#fff" d="M117.688 98.242c-6.973-.191-12.297.461-16.852 2.379-1.293.547-3.355.559-3.566 2.18.711.746.82 1.859 1.387 2.777 1.086 1.754 2.922 4.113 4.559 5.352 1.789 1.348 3.633 2.793 5.551 3.961 3.414 2.082 7.223 3.27 10.504 5.352 1.938 1.23 3.859 2.777 5.75 4.164.934.684 1.563 1.75 2.773 2.18v-.195c-.637-.812-.801-1.93-1.387-2.777l-2.578-2.578c-2.52-3.344-5.719-6.281-9.117-8.719-2.711-1.949-8.781-4.578-9.91-7.73l-.199-.199c1.922-.219 4.172-.914 5.949-1.391 2.98-.797 5.645-.59 8.719-1.387l4.164-1.187v-.793c-1.555-1.594-2.664-3.707-4.359-5.152-4.441-3.781-9.285-7.555-14.273-10.703-2.766-1.746-6.184-2.883-9.117-4.363-.988-.496-2.719-.758-3.371-1.586-1.539-1.961-2.379-4.449-3.566-6.738-2.488-4.793-4.93-10.023-7.137-15.066-1.504-3.437-2.484-6.828-4.359-9.91-9-14.797-18.687-23.73-33.695-32.508-3.195-1.867-7.039-2.605-11.102-3.57l-6.543-.395c-1.332-.555-2.715-2.184-3.965-2.977C16.977 3.52 4.223-3.312.539 5.672-1.785 11.34 4.016 16.871 6.09 19.746c1.457 2.012 3.32 4.273 4.359 6.539.688 1.492.805 2.984 1.391 4.559 1.438 3.883 2.695 8.109 4.559 11.695.941 1.816 1.98 3.727 3.172 5.352.727.996 1.98 1.438 2.18 2.973-1.227 1.715-1.297 4.375-1.984 6.543-3.098 9.77-1.926 21.91 2.578 29.137 1.383 2.223 4.641 6.98 9.117 5.156 3.918-1.598 3.043-6.539 4.164-10.902.254-.988.098-1.715.594-2.379v.199l3.57 7.133c2.641 4.254 7.324 8.699 11.297 11.699 2.059 1.555 3.68 4.242 6.344 5.152v-.199h-.199c-.516-.805-1.324-1.137-1.98-1.781-1.551-1.523-3.277-3.414-4.559-5.156-3.613-4.902-6.805-10.27-9.711-15.855-1.391-2.668-2.598-5.609-3.77-8.324-.453-1.047-.445-2.633-1.387-3.172-1.281 1.988-3.172 3.598-4.164 5.945-1.582 3.754-1.789 8.336-2.375 13.082-.348.125-.195.039-.398.199-2.762-.668-3.73-3.508-4.758-5.949-2.594-6.164-3.078-16.09-.793-23.191.59-1.836 3.262-7.617 2.18-9.316-.516-1.691-2.219-2.672-3.172-3.965-1.18-1.598-2.355-3.703-3.172-5.551-2.125-4.805-3.113-10.203-5.352-15.062-1.07-2.324-2.875-4.676-4.359-6.738-1.645-2.289-3.484-3.977-4.758-6.742-.453-.984-1.066-2.559-.398-3.566.215-.684.516-.969 1.191-1.191 1.148-.887 4.352.297 5.547.793 3.18 1.32 5.832 2.578 8.527 4.363 1.289.855 2.598 2.512 4.16 2.973h1.785c2.789.641 5.914.195 8.523.988 4.609 1.402 8.738 3.582 12.488 5.949 11.422 7.215 20.766 17.48 27.156 29.734 1.027 1.973 1.473 3.852 2.379 5.945 1.824 4.219 4.125 8.559 5.941 12.688 1.816 4.113 3.582 8.27 6.148 11.695 1.348 1.801 6.551 2.766 8.918 3.766 1.66.699 4.379 1.43 5.949 2.379 3 1.809 5.906 3.965 8.723 5.945 1.402.992 5.73 3.168 5.945 4.957zm-88.605-75.52c-1.453-.027-2.48.156-3.566.395v.199h.195c.695 1.422 1.918 2.34 2.777 3.566l1.98 4.164.199-.195c1.227-.867 1.789-2.25 1.781-4.363-.492-.52-.562-1.164-.992-1.785-.562-.824-1.66-1.289-2.375-1.98zm0 0"/></svg></div><div><div class="cc-n">analytics_dev</div><div class="cc-h">localhost:3306</div></div><span class="cc-p">4ms</span></div>
              <div class="cc"><div class="dbi sq" style="background:#003b57;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 128 128" width="18" height="18" xmlns="http://www.w3.org/2000/svg"><path d="M69.5 99.176c-.059-.73-.094-1.2-.094-1.2S67.2 83.087 64.57 78.642c-.414-.707.043-3.594 1.207-7.88.68 1.169 3.54 6.192 4.118 7.81.648 1.824.78 2.347.78 2.347s-1.57-8.082-4.144-12.797a162.286 162.286 0 012.004-6.265c.973 1.71 3.313 5.859 3.828 7.3.102.293.192.543.27.774.023-.137.05-.274.074-.414-.59-2.504-1.75-6.86-3.336-10.082 3.52-18.328 15.531-42.824 27.84-53.754H16.9c-5.387 0-9.789 4.406-9.789 9.789v88.57c0 5.383 4.406 9.789 9.79 9.789h52.897a118.657 118.657 0 01-.297-14.652" fill="#fff"/><path d="M65.777 70.762c.68 1.168 3.54 6.188 4.117 7.809.649 1.824.781 2.347.781 2.347s-1.57-8.082-4.144-12.797a164.535 164.535 0 012.004-6.27c.887 1.567 2.922 5.169 3.652 6.872l.082-.961c-.648-2.496-1.633-5.766-2.898-8.328 3.242-16.871 13.68-38.97 24.926-50.898H16.899a6.94 6.94 0 00-6.934 6.933v82.11c17.527-6.731 38.664-12.88 56.855-12.614-.672-2.605-1.441-4.96-2.25-6.324-.414-.707.043-3.597 1.207-7.879" fill="#fff"/></svg></div><div><div class="cc-n">sandbox_local</div><div class="cc-h">local.db</div></div><span class="cc-p">0ms</span></div>
            </div>
            <div class="cg prod">
              <div class="cg-hd"><span class="cg-n">Production</span><span class="cg-b prod">PROD</span></div>
              <div class="cc" id="cardA"><div class="dbi pg" style="background:#336791;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 128 128" width="18" height="18" fill="currentColor" style="color:#fff"><path fill="#fff" d="M93.809 92.112c.785-6.533.55-7.492 5.416-6.433l1.235.108c3.742.17 8.637-.602 11.513-1.938 6.191-2.873 9.861-7.668 3.758-6.409-13.924 2.873-14.881-1.842-14.881-1.842 14.703-21.815 20.849-49.508 15.543-56.287-14.47-18.489-39.517-9.746-39.936-9.52l-.134.025c-2.751-.571-5.83-.912-9.289-.968-6.301-.104-11.082 1.652-14.709 4.402 0 0-44.683-18.409-42.604 23.151.442 8.841 12.672 66.898 27.26 49.362 5.332-6.412 10.484-11.834 10.484-11.834 2.558 1.699 5.622 2.567 8.834 2.255l.249-.212c-.078.796-.044 1.575.099 2.497-3.757 4.199-2.653 4.936-10.166 6.482-7.602 1.566-3.136 4.355-.221 5.084 3.535.884 11.712 2.136 17.238-5.598l-.22.882c1.474 1.18 1.375 8.477 1.583 13.69.209 5.214.558 10.079 1.621 12.948 1.063 2.868 2.317 10.256 12.191 8.14 8.252-1.764 14.561-4.309 15.136-27.985"/><path fill="#fff" d="M75.458 125.256c-4.367 0-7.211-1.689-8.938-3.32-2.607-2.46-3.641-5.629-4.259-7.522l-.267-.79c-1.244-3.358-1.666-8.193-1.916-14.419-.038-.935-.064-1.898-.093-2.919-.021-.747-.047-1.684-.085-2.664a18.8 18.8 0 01-4.962 1.568c-3.079.526-6.389.356-9.84-.507-2.435-.609-4.965-1.871-6.407-3.82-4.203 3.681-8.212 3.182-10.396 2.453-3.853-1.285-7.301-4.896-10.542-11.037-2.309-4.375-4.542-10.075-6.638-16.943-3.65-11.96-5.969-24.557-6.175-28.693C4.292 23.698 7.777 14.44 15.296 9.129 27.157.751 45.128 5.678 51.68 7.915c4.402-2.653 9.581-3.944 15.433-3.851 3.143.051 6.136.327 8.916.823 2.9-.912 8.628-2.221 15.185-2.139 12.081.144 22.092 4.852 28.949 13.615 4.894 6.252 2.474 19.381.597 26.651-2.642 10.226-7.271 21.102-12.957 30.57 1.544.011 3.781-.174 6.961-.831 6.274-1.295 8.109 2.069 8.607 3.575 1.995 6.042-6.677 10.608-9.382 11.864-3.466 1.609-9.117 2.589-13.745 2.377l-.202-.013-1.216-.107-.12 1.014-.116.991c-.311 11.999-2.025 19.598-5.552 24.619-3.697 5.264-8.835 6.739-13.361 7.709-1.544.33-2.947.474-4.219.474zm-9.19-43.671c2.819 2.256 3.066 6.501 3.287 14.434.028.99.054 1.927.089 2.802.106 2.65.355 8.855 1.327 11.477.137.371.26.747.39 1.146 1.083 3.316 1.626 4.979 6.309 3.978 3.931-.843 5.952-1.599 7.534-3.851 2.299-3.274 3.585-9.86 3.821-19.575l4.783.116-4.75-.57.14-1.186c.455-3.91.783-6.734 3.396-8.602 2.097-1.498 4.486-1.353 6.389-1.01-2.091-1.58-2.669-3.433-2.823-4.193l-.399-1.965 1.121-1.663c6.457-9.58 11.781-21.354 14.609-32.304 2.906-11.251 2.02-17.226 1.134-18.356-11.729-14.987-32.068-8.799-34.192-8.097l-.359.194-1.8.335-.922-.191c-2.542-.528-5.366-.82-8.393-.869-4.756-.08-8.593 1.044-11.739 3.431l-2.183 1.655-2.533-1.043c-5.412-2.213-21.308-6.662-29.696-.721-4.656 3.298-6.777 9.76-6.305 19.207.156 3.119 2.275 14.926 5.771 26.377 4.831 15.825 9.221 21.082 11.054 21.693.32.108 1.15-.537 1.976-1.529a270.708 270.708 0 0 1 10.694-12.07l2.77-2.915 3.349 2.225c1.35.897 2.839 1.406 4.368 1.502l7.987-6.812-1.157 11.808c-.026.265-.039.626.065 1.296l.348 2.238-1.51 1.688-.174.196 4.388 2.025 1.836-2.301z"/><path fill="#336791" d="M115.731 77.44c-13.925 2.873-14.882-1.842-14.882-1.842 14.703-21.816 20.849-49.51 15.545-56.287C101.924.823 76.875 9.566 76.457 9.793l-.135.024c-2.751-.571-5.83-.911-9.291-.967-6.301-.103-11.08 1.652-14.707 4.402 0 0-44.684-18.408-42.606 23.151.442 8.842 12.672 66.899 27.26 49.363 5.332-6.412 10.483-11.834 10.483-11.834 2.559 1.699 5.622 2.567 8.833 2.255l.25-.212c-.078.796-.042 1.575.1 2.497-3.758 4.199-2.654 4.936-10.167 6.482-7.602 1.566-3.136 4.355-.22 5.084 3.534.884 11.712 2.136 17.237-5.598l-.221.882c1.473 1.18 2.507 7.672 2.334 13.557-.174 5.885-.29 9.926.871 13.082 1.16 3.156 2.316 10.256 12.192 8.14 8.252-1.768 12.528-6.351 13.124-13.995.422-5.435 1.377-4.631 1.438-9.49l.767-2.3c.884-7.367.14-9.743 5.225-8.638l1.235.108c3.742.17 8.639-.602 11.514-1.938 6.19-2.871 9.861-7.667 3.758-6.408z"/><path fill="#fff" d="M75.957 122.307c-8.232 0-10.84-6.519-11.907-9.185-1.562-3.907-1.899-19.069-1.551-31.503a1.59 1.59 0 011.64-1.55 1.594 1.594 0 011.55 1.639c-.401 14.341.168 27.337 1.324 30.229 1.804 4.509 4.54 8.453 12.275 6.796 7.343-1.575 10.093-4.359 11.318-11.46.94-5.449 2.799-20.951 3.028-24.01a1.593 1.593 0 011.71-1.472 1.597 1.597 0 011.472 1.71c-.239 3.185-2.089 18.657-3.065 24.315-1.446 8.387-5.185 12.191-13.794 14.037-1.463.313-2.792.453-4 .454"/></svg></div><div><div class="cc-n">app_production</div><div class="cc-h">prod-db-01:5432</div></div><span class="cc-p">9ms</span></div>
              <div class="cc"><div class="dbi pg" style="background:#336791;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 128 128" width="18" height="18" fill="currentColor" style="color:#fff"><path fill="#fff" d="M93.809 92.112c.785-6.533.55-7.492 5.416-6.433l1.235.108c3.742.17 8.637-.602 11.513-1.938 6.191-2.873 9.861-7.668 3.758-6.409-13.924 2.873-14.881-1.842-14.881-1.842 14.703-21.815 20.849-49.508 15.543-56.287-14.47-18.489-39.517-9.746-39.936-9.52l-.134.025c-2.751-.571-5.83-.912-9.289-.968-6.301-.104-11.082 1.652-14.709 4.402 0 0-44.683-18.409-42.604 23.151.442 8.841 12.672 66.898 27.26 49.362 5.332-6.412 10.484-11.834 10.484-11.834 2.558 1.699 5.622 2.567 8.834 2.255l.249-.212c-.078.796-.044 1.575.099 2.497-3.757 4.199-2.653 4.936-10.166 6.482-7.602 1.566-3.136 4.355-.221 5.084 3.535.884 11.712 2.136 17.238-5.598l-.22.882c1.474 1.18 1.375 8.477 1.583 13.69.209 5.214.558 10.079 1.621 12.948 1.063 2.868 2.317 10.256 12.191 8.14 8.252-1.764 14.561-4.309 15.136-27.985"/><path fill="#fff" d="M75.458 125.256c-4.367 0-7.211-1.689-8.938-3.32-2.607-2.46-3.641-5.629-4.259-7.522l-.267-.79c-1.244-3.358-1.666-8.193-1.916-14.419-.038-.935-.064-1.898-.093-2.919-.021-.747-.047-1.684-.085-2.664a18.8 18.8 0 01-4.962 1.568c-3.079.526-6.389.356-9.84-.507-2.435-.609-4.965-1.871-6.407-3.82-4.203 3.681-8.212 3.182-10.396 2.453-3.853-1.285-7.301-4.896-10.542-11.037-2.309-4.375-4.542-10.075-6.638-16.943-3.65-11.96-5.969-24.557-6.175-28.693C4.292 23.698 7.777 14.44 15.296 9.129 27.157.751 45.128 5.678 51.68 7.915c4.402-2.653 9.581-3.944 15.433-3.851 3.143.051 6.136.327 8.916.823 2.9-.912 8.628-2.221 15.185-2.139 12.081.144 22.092 4.852 28.949 13.615 4.894 6.252 2.474 19.381.597 26.651-2.642 10.226-7.271 21.102-12.957 30.57 1.544.011 3.781-.174 6.961-.831 6.274-1.295 8.109 2.069 8.607 3.575 1.995 6.042-6.677 10.608-9.382 11.864-3.466 1.609-9.117 2.589-13.745 2.377l-.202-.013-1.216-.107-.12 1.014-.116.991c-.311 11.999-2.025 19.598-5.552 24.619-3.697 5.264-8.835 6.739-13.361 7.709-1.544.33-2.947.474-4.219.474zm-9.19-43.671c2.819 2.256 3.066 6.501 3.287 14.434.028.99.054 1.927.089 2.802.106 2.65.355 8.855 1.327 11.477.137.371.26.747.39 1.146 1.083 3.316 1.626 4.979 6.309 3.978 3.931-.843 5.952-1.599 7.534-3.851 2.299-3.274 3.585-9.86 3.821-19.575l4.783.116-4.75-.57.14-1.186c.455-3.91.783-6.734 3.396-8.602 2.097-1.498 4.486-1.353 6.389-1.01-2.091-1.58-2.669-3.433-2.823-4.193l-.399-1.965 1.121-1.663c6.457-9.58 11.781-21.354 14.609-32.304 2.906-11.251 2.02-17.226 1.134-18.356-11.729-14.987-32.068-8.799-34.192-8.097l-.359.194-1.8.335-.922-.191c-2.542-.528-5.366-.82-8.393-.869-4.756-.08-8.593 1.044-11.739 3.431l-2.183 1.655-2.533-1.043c-5.412-2.213-21.308-6.662-29.696-.721-4.656 3.298-6.777 9.76-6.305 19.207.156 3.119 2.275 14.926 5.771 26.377 4.831 15.825 9.221 21.082 11.054 21.693.32.108 1.15-.537 1.976-1.529a270.708 270.708 0 0 1 10.694-12.07l2.77-2.915 3.349 2.225c1.35.897 2.839 1.406 4.368 1.502l7.987-6.812-1.157 11.808c-.026.265-.039.626.065 1.296l.348 2.238-1.51 1.688-.174.196 4.388 2.025 1.836-2.301z"/><path fill="#336791" d="M115.731 77.44c-13.925 2.873-14.882-1.842-14.882-1.842 14.703-21.816 20.849-49.51 15.545-56.287C101.924.823 76.875 9.566 76.457 9.793l-.135.024c-2.751-.571-5.83-.911-9.291-.967-6.301-.103-11.08 1.652-14.707 4.402 0 0-44.684-18.408-42.606 23.151.442 8.842 12.672 66.899 27.26 49.363 5.332-6.412 10.483-11.834 10.483-11.834 2.559 1.699 5.622 2.567 8.833 2.255l.25-.212c-.078.796-.042 1.575.1 2.497-3.758 4.199-2.654 4.936-10.167 6.482-7.602 1.566-3.136 4.355-.22 5.084 3.534.884 11.712 2.136 17.237-5.598l-.221.882c1.473 1.18 2.507 7.672 2.334 13.557-.174 5.885-.29 9.926.871 13.082 1.16 3.156 2.316 10.256 12.192 8.14 8.252-1.768 12.528-6.351 13.124-13.995.422-5.435 1.377-4.631 1.438-9.49l.767-2.3c.884-7.367.14-9.743 5.225-8.638l1.235.108c3.742.17 8.639-.602 11.514-1.938 6.19-2.871 9.861-7.667 3.758-6.408z"/><path fill="#fff" d="M75.957 122.307c-8.232 0-10.84-6.519-11.907-9.185-1.562-3.907-1.899-19.069-1.551-31.503a1.59 1.59 0 011.64-1.55 1.594 1.594 0 011.55 1.639c-.401 14.341.168 27.337 1.324 30.229 1.804 4.509 4.54 8.453 12.275 6.796 7.343-1.575 10.093-4.359 11.318-11.46.94-5.449 2.799-20.951 3.028-24.01a1.593 1.593 0 011.71-1.472 1.597 1.597 0 011.472 1.71c-.239 3.185-2.089 18.657-3.065 24.315-1.446 8.387-5.185 12.191-13.794 14.037-1.463.313-2.792.453-4 .454"/></svg></div><div><div class="cc-n">payments_replica</div><div class="cc-h">prod-db-02:5432</div></div><span class="cc-p">11ms</span></div>
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
                <div class="wl-item" id="wlTransactions">transactions <span class="wl-badge">1M</span></div>
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
                <button class="run-btn" id="runQuery">Run</button>
              </div>
              <div class="w-mid-bottom">
                <div class="data-view" id="dataView">
                  <div class="data-view-status"><span id="dataViewName">users · data</span><strong id="dataViewStatus">23 rows visible · ready</strong></div>
                  <div style="flex:1;overflow:hidden;position:relative">
                    <table class="tg">
                      <thead><tr><th>id</th><th>user_id</th><th>name / amount</th><th>status</th></tr></thead>
                      <tbody id="dataRows"></tbody>
                    </table>
                <div class="relation-pop" id="relationPop">
                      <b>users #42</b>
                      <div><span>name</span><span>Nguyễn Lâm</span></div>
                      <div><span>email</span><span>lam@example.com</span></div>
                      <div><span>status</span><span>active</span></div>
                    </div>
                  </div>
                  <div class="data-scrollbar" id="dataScrollbar"><div class="data-scroll-thumb" id="dataScrollThumb"></div></div>
                </div>
                <div class="resource-panel" id="resourcePanel">
                  <h2>Keep the workflow small.</h2>
                  <div class="resource-links">
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a>
                    <a href="<?php echo esc_url(home_url('/guide/')); ?>">Minimal setup guide</a>
                    <a href="<?php echo esc_url(home_url('/docs/')); ?>">Documentation</a>
                    <a href="<?php echo esc_url( function_exists('hoasen_blog_url') ? hoasen_blog_url() : home_url('/blog/') ); ?>">Blog</a>
                  </div>
                </div>
                <!-- S1: Autocomplete Feature -->
                <div class="feat-sc" id="featS1">
                  <div style="display:grid;grid-template-columns:1.2fr 1fr;height:100%">
                    <div class="ast-left">
                      <div class="ac-pop" id="acPop">
                        <div class="ac-hd"><?php esc_html_e('Context-ranked completions', 'hoasen-theme'); ?></div>
                        <div class="ac-it sel"><span>FROM</span><span class="ac-cat ck">keyword</span></div>
                        <div class="ac-it"><span>FOR UPDATE</span><span class="ac-cat ck">keyword</span></div>
                        <div class="ac-it"><span>FETCH NEXT</span><span class="ac-cat ck">keyword</span></div>
                      </div>
                    </div>
                    <div class="ast-right">
                      <div class="ast-ttl"><?php esc_html_e('Schema + context tree', 'hoasen-theme'); ?></div>
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
                        <div class="sp-num" id="spN1">—</div><div class="sp-unit">First viewport</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB1" style="background:linear-gradient(90deg,var(--red),var(--green))"></div></div>
                      </div>
                      <div class="sp-stat">
                        <div class="sp-num" id="spN2">—</div><div class="sp-unit">Frame time</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB2" style="background:linear-gradient(90deg,var(--green),#0f6b3e)"></div></div>
                      </div>
                      <div class="sp-stat">
                        <div class="sp-num" id="spN3">—</div><div class="sp-unit">DOM rows</div>
                        <div class="sp-bar"><div class="sp-fill" id="spB3" style="background:linear-gradient(90deg,var(--blue),var(--green))"></div></div>
                      </div>
                    </div>
                    <div class="sp-r">
                      <div class="cmp-card">
                        <div class="cmp-lbl">Open transactions table</div>
                        <div class="cmp-row"><div class="cmp-name">Rows</div><div class="cmp-bw"><div class="cmp-bf hs" id="cB1" style="width:0"></div></div><div class="cmp-v">1,000,000</div></div>
                        <div class="cmp-row"><div class="cmp-name">Visible</div><div class="cmp-bw"><div class="cmp-bf el" style="width:40%"></div></div><div class="cmp-v">23 rows</div></div>
                      </div>
                      <div class="cmp-card">
                        <div class="cmp-lbl">Interaction stays responsive</div>
                        <div class="cmp-row"><div class="cmp-name">Render</div><div class="cmp-bw"><div class="cmp-bf hs" id="cB2" style="width:0"></div></div><div class="cmp-v">0.4ms</div></div>
                        <div class="cmp-row"><div class="cmp-name">Scroll</div><div class="cmp-bw"><div class="cmp-bf el" style="width:38%"></div></div><div class="cmp-v">60 FPS</div></div>
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
                  <div class="pm-layout">
                    <div class="pm-l">
                      <div class="pm-hd">Installed Plugins</div>
                      <div class="pm-lst">
                        <div class="pm-it on"><div class="pm-ico">⌘</div><div class="pm-inf"><div class="pm-nm">Custom Command Bar</div><div class="pm-ds">Team-defined fast actions</div></div><span class="pm-st on">ON</span><button class="pm-tg on"></button></div>
                        <div class="pm-it on" id="pmAicop"><div class="pm-ico">SQL</div><div class="pm-inf"><div class="pm-nm">Custom Query Explain</div><div class="pm-ds">Plans, costs, plain notes</div></div><span class="pm-st on">ON</span><button class="pm-tg on"></button></div>
                        <div class="pm-it" id="pmRed"><div class="pm-ico">🔴</div><div class="pm-inf"><div class="pm-nm">Custom Redis Inspector</div><div class="pm-ds">Keys, TTL, cluster</div></div><span class="pm-st off" id="redSt">AVAILABLE</span><button class="pm-install" id="redTg">Install</button></div>
                      </div>
                    </div>
                    <div class="pm-r">
                      <div class="pm-sc">
                        <div class="pm-sl">Custom Tools</div>
                        <div class="pm-sv" id="pmCnt">2</div>
                        <div class="pm-ss">loaded in workspace</div>
                      </div>
                      <div class="pm-ac">
                        <div class="pm-at">Activity</div>
                        <div class="pm-ar"><div class="pm-ad" style="background:var(--green)"></div><span>Custom Query Explain — active</span></div>
                        <div class="pm-ar" id="redAct" style="opacity:0;transition:opacity .3s"><div class="pm-ad" style="background:#ef4444"></div><span>Redis Inspector — loaded</span></div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </main>

            <!-- Right panel: Widget list (Query History, Table Stats) -->
            <aside class="w-right">
              <div class="wr-title">CREATIVE WIDGETS</div>
              
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
                <div class="wb-hd" style="display:flex;align-items:center">Table Statistics <button class="widget-remove" id="removeStats" aria-label="Remove Table Statistics">×</button></div>
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
  <div class="site-outro" id="siteOutro">
    <h2><?php esc_html_e('Native speed, zero bloat. Built for developer focus.', 'hoasen-theme'); ?></h2>
    <div class="outro-links">
      <a href="#" class="btn-download">
        <svg viewBox="0 0 24 24"><path d="M5 20h14v-2H5v2zm14-9h-4V3H9v8H5l7 7 7-7z"/></svg>
        Download App
      </a>
      <a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a>
      <a href="<?php echo esc_url(home_url('/guide/')); ?>">Guide</a>
      <a href="<?php echo esc_url(home_url('/docs/')); ?>">Documentation</a>
      <a href="<?php echo esc_url( function_exists('hoasen_blog_url') ? hoasen_blog_url() : home_url('/blog/') ); ?>">Blog</a>
    </div>
  </div><!-- .stage -->

<!-- Mouse cursor mockup -->
<div class="mouse" id="mouse" aria-hidden="true"><div class="mouse-s"><svg width="27" height="27" viewBox="0 0 27 27" fill="none"><path d="M3.5 2L21 13.8L13.8 15.5L9.8 23.2L3.5 2Z" fill="#F5F3F0" stroke="#0a0a0a" stroke-width="1.5" stroke-linejoin="round"/></svg></div></div>

<!-- Contact Modal -->
<div class="ov" id="contactMod">
  <div class="mc mini">
    <button class="xbtn" id="closeContact">&times;</button>
    <div class="mhd"><div class="mhdi"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><?php esc_html_e('Contact', 'hoasen-theme'); ?></div>
    <div class="cc-cnt">
      <p><?php esc_html_e('Tell us where your database workflow feels slow, noisy, or overbuilt. HoaSen is shaped around developer focus.', 'hoasen-theme'); ?></p>
      <div class="cc-lnks">
        <a href="https://facebook.com/hoasentable" target="_blank" class="cc-itm fb"><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c4.56-.93 8-4.96 8-9.75z"/></svg>facebook.com/hoasentable</a>
        <a href="mailto:support@hoasentable.localhost" class="cc-itm"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>support@hoasentable.localhost</a>
      </div>
    </div>
  </div>
</div>

<script>
const SCENES=7,LANG='<?php echo esc_js($lang); ?>';
if('scrollRestoration' in history)history.scrollRestoration='manual';
scrollTo(0,0);
const progEl=document.getElementById('progress');
for(let i=0;i<SCENES;i++){const b=document.createElement('div');b.className='bar';b.innerHTML='<span></span>';progEl.appendChild(b);}
const bars=[...progEl.querySelectorAll('span')];
const copies=[...document.querySelectorAll('.copy-scene')];
const mouse=document.getElementById('mouse');
const winPath=document.getElementById('winPath');
const winBtnPlugins=document.getElementById('winBtnPlugins');
const appWindow=document.getElementById('appWindow');
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
const dataRows=document.getElementById('dataRows');
const dataView=document.getElementById('dataView');
const dataScrollbar=document.getElementById('dataScrollbar');
const dataScrollThumb=document.getElementById('dataScrollThumb');
const dataViewName=document.getElementById('dataViewName');
const dataViewStatus=document.getElementById('dataViewStatus');
const relationPop=document.getElementById('relationPop');
const runQuery=document.getElementById('runQuery');
const removeStats=document.getElementById('removeStats');
const resourcePanel=document.getElementById('resourcePanel');
const canvas=document.getElementById('canvas');
const siteOutro=document.getElementById('siteOutro');
const stage=document.querySelector('.stage');
const siteBrand=document.getElementById('siteBrand');
const brandTag=document.getElementById('brandTag');

// Mock Layout Dom References
const s0=document.getElementById('s0');
const workspaceLayout=document.getElementById('workspaceLayout');
const wlUsers=document.getElementById('wlUsers');
const wlOrders=document.getElementById('wlOrders');
const wlTransactions=document.getElementById('wlTransactions');
const widgetQHist=document.getElementById('widgetQHist');
const widgetTStats=document.getElementById('widgetTStats');
const widgetRail=document.querySelector('.w-right');
const fkLine=document.getElementById('fkLine');
const vgMetricsBox=document.getElementById('vgMetricsBox');
const pmAicop=document.getElementById('pmAicop');

// Modals
const menuBtn=document.getElementById('menuBtn'),menuDd=document.getElementById('menuDd');
const contactMod=document.getElementById('contactMod');
menuBtn.addEventListener('click',e=>{e.stopPropagation();menuDd.classList.toggle('show');});
document.addEventListener('click',()=>menuDd.classList.remove('show'));
document.getElementById('btnContact').addEventListener('click',e=>{e.preventDefault();contactMod.classList.add('show');});
document.getElementById('closeContact').addEventListener('click',()=>contactMod.classList.remove('show'));
contactMod.addEventListener('click',e=>{if(e.target===contactMod)contactMod.classList.remove('show');});

function clamp(v,a=0,b=1){return Math.min(b,Math.max(a,v));}
function ease(t){return t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;}
function lerp(a,b,t){return a+(b-a)*t;}
function mix(a,b,t){t=ease(clamp(t));return{x:lerp(a.x,b.x,t),y:lerp(a.y,b.y,t)};}
let lastClick=false;
let connectionEnd=null;
let joinEnd=null;
let pluginEnd=null;
let widgetEnd=null;
let millionEnd=null;
function setMouse(p,click=false){
  mouse.style.transform=`translate(${p.x}px,${p.y}px)`;
  mouse.classList.toggle('click',click);
  if(click&&!lastClick){const r=document.createElement('div');r.className='click-rip';r.style.left=p.x+'px';r.style.top=p.y+'px';document.body.appendChild(r);setTimeout(()=>r.remove(),380);}
  lastClick=click;
}
function gp(rx,ry){const w=document.querySelector('.window').getBoundingClientRect();return{x:w.left+w.width*rx,y:w.top+w.height*ry};}
function getElementPoint(el){const r=el.getBoundingClientRect();return{x:r.left+r.width/2,y:r.top+r.height/2};}
function setCamera(target,scale=1){
  if(innerWidth>980){
    workspaceLayout.style.setProperty('--camera-x','0px');
    workspaceLayout.style.setProperty('--camera-scale','1');
    return;
  }
  const centers={left:70,middle:405,detail:500,right:760};
  const scaledWidth=850*scale;
  const desired=innerWidth/2-(centers[target]??centers.middle)*scale;
  const shift=clamp(desired,innerWidth-scaledWidth-28,0);
  workspaceLayout.style.setProperty('--camera-x',shift+'px');
  workspaceLayout.style.setProperty('--camera-scale',scale);
}
function renderData(table='users',start=1){
  dataViewName.textContent=table+' · data';
  dataViewStatus.textContent=table==='transactions'?'1,000,000 rows · 23 rendered':'23 rows visible · ready';
  let html='';
  for(let i=0;i<14;i++){
    const n=start+i,uid=(n*7)%997;
    html+=`<tr><td>${n.toLocaleString()}</td><td><span class="fk-c" ${i===2?'id="activeFkCell"':''}>${uid}</span></td><td>${table==='users'?'User '+n:(1200+(n%200)*31).toLocaleString()}</td><td><span class="${i%7===0?'ok-a':'ok-g'}">${i%7===0?'pending':'active'}</span></td></tr>`;
  }
  dataRows.innerHTML=html;
}
renderData();

function showScene(sceneId) {
  appWindow.classList.toggle('connection-mode',sceneId===0);
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
  sqlEd.classList.remove('focused');
  wlUsers.classList.remove('active');
  wlOrders.classList.remove('active');
  wlTransactions.classList.remove('active');
  acPop.classList.remove('highlight-box');
  fkSnip.classList.remove('highlight-box');
  fkLine.classList.remove('highlight-box-green');
  vgMetricsBox.classList.remove('highlight-box-green');
  pmAicop.classList.remove('highlight-box');
  document.getElementById('cardA').classList.remove('highlight-box');
  winBtnPlugins.classList.remove('highlight-box');
  relationPop.classList.remove('show');
}

// Scene 0: Connection Board
function sc0(p){
  winPath.innerHTML='<strong>HoaSen Table</strong> · Connection Board';
  showScene(0);
  removeHighlights();
  
  setCamera('detail');
  const card = document.getElementById('cardA');
  card.classList.toggle('highlight-box', p > 0.7);
  const cardPoint=getElementPoint(card);
  connectionEnd=cardPoint;
  setMouse(mix(gp(.5,.5),cardPoint,p),p>.78&&p<.92);
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
  winPath.innerHTML='<strong>query.sql</strong> · schema + context aware';
  showScene(1);
  removeHighlights();
  setCamera('detail');
  renderData('users',1);
  
  const editorPoint=getElementPoint(sqlEd);
  const phase=p<.28?0:p<.68?1:2;
  const texts=['<span class="kw">SELECT</span> * <span class="caret"></span>','<span class="kw">SELECT</span> * <span class="kw">F</span><span class="caret"></span>','<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">users</span> u<span class="caret"></span>'];
  sqlEd.innerHTML=texts[phase];
  sqlEd.classList.toggle('focused',p>.18);
  
  acPop.classList.toggle('show',phase>0);
  acPop.classList.toggle('highlight-box', phase>0 && p>0.4);
  renderAst(AST[phase]);
  
  if(p<.18) setMouse(mix(connectionEnd||gp(.73,.44),editorPoint,p/.18),false);
  else if(p<.27) setMouse(editorPoint,true);
  else setMouse(editorPoint,false);
}

// Scene 2: Smart Joins
function sc2(p){
  winPath.innerHTML='<strong>query.sql</strong> · learned JOIN pattern';
  showScene(2);
  removeHighlights();
  setCamera('detail');
  renderData('users',1);
  
  sqlEd.classList.add('focused');
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
  const editorPoint=getElementPoint(sqlEd);
  const joinPoint=p>=.4?getElementPoint(fkSnip):editorPoint;
  if(p>=.4) joinEnd=joinPoint;
  setMouse(mix(editorPoint,joinPoint,p),p>.4&&p<.55);
}

// Scene 3: Performance
function sc3(p){
  winPath.innerHTML='<strong>transactions</strong> · 1,000,000 rows';
  showScene(3);
  removeHighlights();
  setCamera(p<.34?'left':'detail');
  const tablePoint=getElementPoint(wlTransactions);
  wlTransactions.classList.toggle('active',p>.28);
  if(p>.34) renderData('transactions',1);
  sqlEd.innerHTML='<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">transactions</span> <span class="kw">LIMIT</span> <span class="num">1000000</span>;';
  
  const t=ease(clamp(p/.5));
  if(p>.12){
    spNs[0].textContent=Math.round(lerp(80,12,t))+'ms';
    spNs[1].textContent=lerp(4.8,.4,t).toFixed(1)+'ms';
    spNs[2].textContent=Math.round(lerp(1000,23,t));
    const w=Math.round(lerp(0,5,t))+'%';
    spBs[0].style.width=w;spBs[1].style.width=w;spBs[2].style.width=w;
    cBs[0].style.width=w;cBs[1].style.width=w;
  } else {spNs.forEach(n=>n.textContent='—');spBs.forEach(b=>b.style.width='0');cBs.forEach(b=>b.style.width='0');}
  if(p<.24) setMouse(mix(joinEnd||gp(.5,.62),tablePoint,p/.24),false);
  else if(p<.34) setMouse(tablePoint,true);
  else {
    const firstCell=document.getElementById('activeFkCell');
    setMouse(mix(tablePoint,firstCell?getElementPoint(firstCell):gp(.52,.56),(p-.34)/.66),false);
  }
}

// Scene 4: Hover Inspect
let s4Init=false;
function sc4(p){
  winPath.innerHTML='<strong>orders</strong> · hover FK inspect';
  showScene(4);
  removeHighlights();
  setCamera('middle');
  renderData('transactions',90020);
  
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
  const fc=document.getElementById('activeFkCell');
  if(fc&&show){
    const gr=dataRows.closest('div').getBoundingClientRect();
    const cr=fc.getBoundingClientRect();
    relationPop.style.top=(cr.top-gr.top+cr.height/2-38)+'px';
    relationPop.style.left=Math.min(cr.right-gr.left+8,gr.width-220)+'px';
    relationPop.classList.add('show');
  }
  const startPoint=fc?getElementPoint(fc):gp(.5,.55);
  setMouse(startPoint,false);
}

// Scene 5: Virtual Grid
function sc5(p){
  winPath.innerHTML='<strong>transactions</strong> · virtual grid';
  showScene(5);
  removeHighlights();
  setCamera('middle');
  
  wlTransactions.classList.add('active');
  
  sqlEd.innerHTML='<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">transactions</span>; <span class="cm">-- 1M records</span>';
  
  const s=Math.floor(1+p*999977);
  renderData('transactions',s);
  vgS.textContent=s.toLocaleString();vgE.textContent=(s+22).toLocaleString();
  const sc=['ok-g','ok-g','ok-a','ok-g','ok-g','ok-r','ok-g','ok-a','ok-g','ok-g','ok-g','ok-g','ok-a','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-g','ok-a'];
  const sn=['paid','paid','pending','paid','paid','failed','paid','pending','paid','paid','paid','paid','pending','paid','paid','paid','paid','paid','paid','paid','paid','paid','pending'];
  let h='';
  for(let i=0;i<23;i++){const n=s+i;h+=`<tr><td>${n.toLocaleString()}</td><td>${800000+n}</td><td>${(n*7)%999}</td><td>${(1200+(n%200)*31).toLocaleString()}</td><td><span class="${sc[i]}">${sn[i]}</span></td></tr>`;}
  vgR.innerHTML=h;
  vgTh.style.top=(p*80)+'%';
  vgMetricsBox.classList.add('highlight-box-green');
  
  const firstCell=document.getElementById('activeFkCell');
  const tailRow=dataRows.querySelector('tr:last-child');
  setMouse(mix(firstCell?getElementPoint(firstCell):gp(.5,.45),tailRow?getElementPoint(tailRow):gp(.5,.72),p),false);
}

function resetFlowUi(){
  removeHighlights();
  resourcePanel.classList.remove('show');
  canvas.classList.remove('resources-mode');
  stage.classList.remove('resources-mode');
  siteOutro.classList.remove('show');
  dataView.classList.remove('is-empty');
  dataView.classList.remove('focus-fk');
  runQuery.classList.remove('focused');
  dataScrollbar.classList.remove('highlight');
  dataScrollThumb.style.top='0%';
  widgetTStats.classList.remove('removed');
  widgetRail.classList.remove('widget-removed');
  pmRed.classList.remove('on','installing');
  redSt.textContent='AVAILABLE';
  redSt.className='pm-st off';
  redTg.className='pm-install';
  redTg.textContent='Install';
  redAct.style.opacity='0';
  pmCnt.textContent='2';
  const dlBtn=siteOutro.querySelector('.btn-download');
  if(dlBtn) dlBtn.classList.remove('hover');
}

function flowJoin(p){
  showScene(2);resetFlowUi();setCamera('detail',lerp(.88,1,ease(p)));
  dataView.classList.add('is-empty');
  winPath.innerHTML='<strong>query.sql</strong> · learned context';
  sqlEd.classList.add('focused');
  const editorPoint=getElementPoint(sqlEd);
  if(p<.42){
    sqlEd.innerHTML='<span class="kw">SELECT</span> u.id, u.name, o.total <span class="kw">FROM</span> <span class="tbl">users</span> u <span class="kw">JOIN</span><span class="caret"></span>';
    fkSnip.classList.remove('show');
    setMouse(mix(connectionEnd||gp(.73,.44),editorPoint,p/.42),p>.28&&p<.38);
  }else{
    sqlEd.innerHTML='<span class="kw">SELECT</span> u.id, u.name, o.total <span class="kw">FROM</span> <span class="tbl">users</span> u <span class="kw">JOIN</span> <span class="tbl">orders</span> o <span class="kw">ON</span> <span class="col">o.user_id</span>=<span class="col">u.id</span>';
    fkSnip.classList.add('show');
    joinEnd=editorPoint;
    setMouse(editorPoint,false);
  }
}

function flowRunInspect(p){
  showScene(4);resetFlowUi();setCamera(p<.42?'detail':'middle',p<.42?1:.94);
  winPath.innerHTML='<strong>query.sql</strong> · results';
  sqlEd.innerHTML='<span class="kw">SELECT</span> u.id, u.name, o.total <span class="kw">FROM</span> <span class="tbl">users</span> u <span class="kw">JOIN</span> <span class="tbl">orders</span> o <span class="kw">ON</span> <span class="col">o.user_id</span>=<span class="col">u.id</span>';
  const runPoint=getElementPoint(runQuery);
  if(p<.28){dataView.classList.add('is-empty');runQuery.classList.add('focused');setMouse(mix(joinEnd||getElementPoint(sqlEd),runPoint,p/.28),false);return;}
  if(p<.38){dataView.classList.add('is-empty');runQuery.classList.add('focused');setMouse(runPoint,true);return;}
  renderData('orders',90020);
  const cell=document.getElementById('activeFkCell');
  const cellPoint=cell?getElementPoint(cell):gp(.5,.55);
  if(p>.55)dataView.classList.add('focus-fk');
  setMouse(mix(runPoint,cellPoint,(p-.38)/.32),false);
  if(p>.7&&cell){
    const host=dataRows.closest('div').getBoundingClientRect(),rect=cell.getBoundingClientRect();
    relationPop.style.top=(rect.top-host.top-28)+'px';
    relationPop.style.left=Math.min(rect.right-host.left+8,host.width-220)+'px';
    relationPop.classList.add('show');
  }
}

function flowMillion(p){
  const mobileScale=p<.46?.9:p<.58?.84:lerp(.84,1,clamp((p-.58)/.2));
  showScene(4);resetFlowUi();setCamera(p<.46?'left':p<.58?'middle':'detail',mobileScale);
  winPath.innerHTML='<strong>transactions</strong> · data';
  const sweep=clamp((p-.58)/.42);
  const startRow=p>.46?Math.max(1,Math.floor(sweep*999977)):90020;
  renderData(p>.46?'transactions':'orders',startRow);
  const startCell=document.getElementById('activeFkCell');
  const tablePoint=getElementPoint(wlTransactions);
  if(p<.34){setMouse(mix(startCell?getElementPoint(startCell):gp(.5,.55),tablePoint,p/.34),false);return;}
  if(p<.46){setMouse(tablePoint,true);return;}
  wlTransactions.classList.add('active');
  sqlEd.innerHTML='<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">transactions</span>;';
  dataViewStatus.textContent=`Viewing ${startRow.toLocaleString()}–${Math.min(startRow+13,1000000).toLocaleString()} of 1,000,000`;
  const gridRect=dataRows.closest('div').getBoundingClientRect();
  const gridCenter={x:gridRect.left+gridRect.width*.5,y:gridRect.top+gridRect.height*.5};
  millionEnd=gridCenter;
  if(p<.58){
    setMouse(mix(tablePoint,gridCenter,(p-.46)/.12),false);
    return;
  }
  dataScrollbar.classList.add('highlight');
  dataScrollThumb.style.top=(sweep*78)+'%';
  const scrollRect=dataScrollbar.getBoundingClientRect();
  const thumbPoint={x:scrollRect.left+scrollRect.width/2,y:scrollRect.top+11+(scrollRect.height-22)*sweep};
  millionEnd=thumbPoint;
  setMouse(thumbPoint,false);
}

function flowPlugins(p){
  renderData('transactions',999978);resetFlowUi();
  const buttonPoint=getElementPoint(winBtnPlugins);
  if(p<.3){showScene(4);setCamera('middle',.9);setMouse(mix(millionEnd||gp(.5,.55),buttonPoint,p/.3),false);return;}
  showScene(6);setCamera('detail',.94);
  if(p<.4){setMouse(buttonPoint,true);return;}
  const togglePoint=getElementPoint(redTg);pluginEnd=togglePoint;
  if(p<.62){setMouse(mix(buttonPoint,togglePoint,(p-.4)/.22),false);return;}
  if(p<.72){
    pmRed.classList.add('installing');
    redSt.textContent='INSTALLING';
    redTg.className='pm-install installing';
    redTg.textContent='Installing…';
    setMouse(togglePoint,true);
    return;
  }
  pmRed.classList.add('on');
  redSt.textContent='ON';
  redSt.className='pm-st on';
  redTg.className='pm-install done';
  redTg.textContent='Installed';
  redAct.style.opacity='1';
  pmCnt.textContent='3';
  setMouse(togglePoint,false);
}

function flowRemoveWidget(p){
  showScene(4);resetFlowUi();
  document.getElementById('featS6').classList.remove('show');
  setCamera('right',.88);renderData('transactions',500001);
  winPath.innerHTML='<strong>transactions</strong> · workspace';
  const removePoint=getElementPoint(removeStats);widgetEnd=removePoint;
  if(p<.62){setMouse(mix(pluginEnd||gp(.62,.45),removePoint,p/.62),false);return;}
  setMouse(removePoint,p<.76);
  if(p>.76){
    widgetTStats.classList.add('removed');
    widgetRail.classList.add('widget-removed');
  }
}

function flowResources(p){
  showScene(4);resetFlowUi();
  widgetRail.classList.add('widget-removed');
  canvas.classList.add('resources-mode');
  stage.classList.add('resources-mode');
  siteOutro.classList.add('show');
  const firstLink=siteOutro.querySelector('a');
  setMouse(mix(widgetEnd||gp(.78,.45),getElementPoint(firstLink),p),false);
  if(firstLink) {
    firstLink.classList.toggle('hover', p > 0.92);
  }
}

// Main scroll loop
function update(){
  const max=document.documentElement.scrollHeight-innerHeight;
  const g=clamp(scrollY/max);
  const raw=clamp(g*SCENES,0,SCENES-.0001);
  const idx=Math.floor(raw),loc=raw-idx;
  siteBrand.classList.toggle('scene-hidden',idx>0);
  stage.classList.toggle('after-intro',idx>0&&idx<SCENES-1);
  brandTag.textContent='MINIMAL DB WORKSPACE';
  bars.forEach((b,i)=>b.style.width=(i<idx?100:i>idx?0:loc*100)+'%');
  copies.forEach((c,i)=>{
    const entering=idx===0?1:clamp((loc-.18)/.14);
    const leaving=clamp((1-loc)/.12);
    const current=i===idx?entering*leaving:0;
    const previous=i===idx-1?clamp((.18-loc)/.18):0;
    const op=Math.max(current,previous);
    const offset=i===idx?(1-entering)*12:-loc*8;
    c.style.opacity=op;c.style.filter=`blur(${(1-op)*3}px)`;
    c.style.transform=`translateY(-42%) translateY(${offset}px)`;
    c.classList.toggle('active',op>.55);
  });
  
  [sc0,flowJoin,flowRunInspect,flowMillion,flowPlugins,flowRemoveWidget,flowResources][idx]?.(loc);
}

let tick=false;
addEventListener('scroll',()=>{if(!tick){requestAnimationFrame(()=>{update();tick=false;});tick=true;}},{passive:true});
addEventListener('resize',update);
addEventListener('pageshow',()=>{scrollTo(0,0);requestAnimationFrame(update);});
setTimeout(update,80);
</script>
<?php wp_footer(); ?>
</body>
</html>
