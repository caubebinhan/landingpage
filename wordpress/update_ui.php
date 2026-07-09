<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// Replace CSS in front-page.php
$old_style_start = strpos($content, '<style>');
$old_style_end = strpos($content, '</style>');
if ($old_style_start !== false && $old_style_end !== false) {
    $style_content = substr($content, $old_style_start, $old_style_end - $old_style_start + 8);
}

// Let's create the new complete <style> block
$new_style = <<<CSS
<style>
:root{
  --bg:#f0f0f0; --panel:#ffffff; --panel2:#f8f8f8; --line:#d2d6dc;
  --text:#111827; --muted:#55667d; --blue:#881818; --green:#107a4a;
  --red:#b91c1c; --yellow:#b45309; --orange:#c2410c; --pink:#9d174d;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  height:620vh; overflow-x:hidden; background:#f0f0f0; color:var(--text);
  font-family:"Cormorant Garamond", Georgia, serif;
}
body:before{
  content:""; position:fixed; inset:0; pointer-events:none;
  background:
    radial-gradient(circle at 18% 16%, rgba(136,24,24,.03), transparent 34%),
    radial-gradient(circle at 80% 24%, rgba(16,122,74,.03), transparent 32%),
    linear-gradient(180deg,#f0f0f0,#e5e5e5);
  z-index: -1;
}
.bg-lotus {
  position:fixed; top:50%; left:65%; transform:translate(-50%, -50%);
  width:140vh; height:140vh; z-index:0; opacity:0.035; pointer-events:none;
}
.stage{position:fixed; inset:0; display:grid; grid-template-columns:.85fr 1.35fr; gap:56px; align-items:center; padding:56px; overflow:hidden}
.grid{position:absolute; inset:0; opacity:.15; pointer-events:none; background-image:linear-gradient(rgba(0,0,0,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(0,0,0,.06) 1px,transparent 1px); background-size:54px 54px; mask-image:radial-gradient(circle at 58% 50%,black,transparent 78%)}

.brand{position:absolute;top:28px;left:38px;z-index:40;display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:-.01em;font-size:20px;font-family:"Inter",sans-serif}
.logo{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,var(--blue),var(--green));box-shadow:0 0 20px rgba(136,24,24,.20);position:relative}
.logo:after{content:"";position:absolute;inset:7px;border-radius:3px;background:#f0f0f0}

/* Menu Header */
.menu-container{position:absolute;top:28px;right:38px;z-index:100;display:inline-block}
.menu-btn{background:transparent;border:none;cursor:pointer;color:var(--text);padding:8px;border-radius:6px;transition:background .2s;display:flex;align-items:center;justify-content:center}
.menu-btn:hover{background:rgba(0,0,0,.06)}
.menu-dropdown{position:absolute;right:0;top:100%;margin-top:8px;min-width:140px;background:#ffffff;border:1px solid rgba(0,0,0,.1);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.08);display:none;z-index:101;overflow:hidden;font-family:"Inter",sans-serif}
.menu-dropdown.show{display:block}
.menu-dropdown a{display:block;padding:10px 16px;color:#333333;text-decoration:none;font-size:12px;font-weight:700;letter-spacing:.05em;transition:background .2s}
.menu-dropdown a:hover{background:rgba(136,24,24,.06);color:var(--blue)}

/* Modal overlays */
.overlay-modal{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(8px);z-index:200;display:none;align-items:center;justify-content:center;opacity:0;transition:opacity .3s ease;font-family:"Inter",sans-serif}
.overlay-modal.show{display:flex;opacity:1}
.modal-card{background:#ffffff;border:1px solid rgba(0,0,0,.15);border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.2);width:min(90vw,720px);max-height:85vh;display:flex;flex-direction:column;position:relative;overflow:hidden}
.modal-card.mini{width:min(90vw,420px)}
.close-btn{position:absolute;top:16px;right:18px;background:transparent;border:none;font-size:24px;cursor:pointer;color:#888888;transition:color .2s;z-index:10}
.close-btn:hover{color:#111111}
.modal-header{padding:20px 24px;border-bottom:1px solid rgba(0,0,0,.08);font-size:18px;font-weight:800;color:#111111}
.contact-content{padding:24px}
.contact-content p{font-size:14px;color:#4b5563;line-height:1.5;margin-bottom:20px}
.contact-links{display:flex;flex-direction:column;gap:12px}
.contact-item{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:10px;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.01);text-decoration:none;color:#333333;font-family:"JetBrains Mono",monospace;font-size:12px;transition:all .2s}
.contact-item.fb{color:#1877f2}
.contact-item:hover{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.15)}

.progress{position:absolute;right:84px;top:38px;z-index:50;display:flex;gap:8px}
.bar{width:32px;height:4px;border-radius:99px;background:rgba(0,0,0,0.08);overflow:hidden}
.bar span{display:block;width:0;height:100%;background:linear-gradient(90deg,var(--blue),var(--green))}

.copy{position:relative;z-index:5;min-height:380px}
.copy-scene{position:absolute;left:0;right:0;top:50%;transform:translateY(-42%) translateY(24px);opacity:0;filter:blur(10px);transition:transform 0.4s ease, opacity 0.4s ease, filter 0.4s ease;}
.copy-scene.active{opacity:1;filter:blur(0);transform:translateY(-42%)}
.kicker{font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--blue);font-weight:900;margin-bottom:14px;font-family:"Inter",sans-serif}
h1{font-size:clamp(32px,4vw,56px);line-height:1.0;letter-spacing:-.03em;font-weight:800;max-width:650px;color:#111111;text-wrap:balance}
.copy p{font-size:16px;line-height:1.55;color:#4b5563;margin-top:22px;max-width:430px;font-family:"Inter",sans-serif;text-wrap:pretty}
.chips{display:flex;gap:10px;margin-top:26px;flex-wrap:wrap;font-family:"Inter",sans-serif}
.chip{padding:7px 12px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.03);font-size:11px;color:#374151;font-weight:700}
.chip.hot{background:linear-gradient(135deg,rgba(136,24,24,.9),rgba(136,24,24,.85));color:#ffffff;border:0;box-shadow:0 4px 12px rgba(136,24,24,.15)}

/* Unified Fixed Window Layout */
.canvas{position:relative;z-index:3;height:min(74vh,760px);min-height:580px;width:100%;display:flex;align-items:center;justify-content:center}
.window{position:relative;width:min(100%,840px);height:100%;overflow:hidden;border-radius:20px;border:1px solid rgba(0,0,0,.14);background:#ffffff;box-shadow:0 35px 100px rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.8);display:flex;flex-direction:column}
.window:before{content:"";position:absolute;inset:-1px;background:radial-gradient(circle at 22% 0%,rgba(136,24,24,.04),transparent 34%);pointer-events:none}
.winbar{height:54px;display:flex;align-items:center;gap:12px;padding:0 20px;border-bottom:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.025);font-size:12px;color:#555555;position:relative;z-index:2;font-family:"JetBrains Mono",monospace;flex-shrink:0}
.traffic{display:flex;gap:7px}
.traffic i{width:11px;height:11px;border-radius:50%;display:block}
.r{background:#ff5f57}
.y{background:#febc2e}
.g{background:#28c840}
.path strong{color:#111111}

/* Workspace sub-layouts */
.connection-board, .software-layout {
  position: absolute; inset: 54px 0 0 0;
  transition: opacity 0.3s, visibility 0.3s;
  opacity: 0; visibility: hidden; pointer-events: none;
}
.connection-board.show, .software-layout.show {
  opacity: 1; visibility: visible; pointer-events: auto;
}

/* Connection Board styles */
.connection-board{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:34px;background:#fcfcfc}
.conn-group{border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#ffffff;padding:20px;display:flex;flex-direction:column;gap:12px;box-shadow:0 4px 12px rgba(0,0,0,.02)}
.conn-group.dev{border-top:4px solid var(--green)}
.conn-group.prod{border-top:4px solid var(--blue)}
.group-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.group-name{font-family:"Inter",sans-serif;font-size:11px;font-weight:900;letter-spacing:.05em;color:#555555;text-transform:uppercase}
.group-badge{font-size:10px;padding:3px 8px;border-radius:99px;font-weight:700;font-family:"Inter",sans-serif}
.group-badge.dev{background:rgba(16,122,74,.1);color:var(--green)}
.group-badge.prod{background:rgba(136,24,24,.1);color:var(--blue)}
.conn-card{display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid rgba(0,0,0,.06);border-radius:10px;background:rgba(0,0,0,.005);cursor:pointer;position:relative;transition:all .2s;font-family:"Inter",sans-serif}
.conn-card:hover,.conn-card.active{background:rgba(0,0,0,.02);border-color:rgba(0,0,0,.12)}
.conn-card.active{box-shadow:inset 0 0 0 1px rgba(136,24,24,.18), 0 4px 12px rgba(0,0,0,.05)}
.db-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:"JetBrains Mono",monospace;font-size:12px;font-weight:900;color:#ffffff}
.db-icon.pg{background:#336791}
.db-icon.my{background:#00758f}
.db-icon.sq{background:#003b57}
.conn-details{display:flex;flex-direction:column;gap:2px}
.conn-name{font-size:13px;font-weight:700;color:#222222}
.conn-host{font-size:10px;color:#888888;font-family:"JetBrains Mono",monospace}

/* Software Workspace Layout - Strict Egui Parity */
.software-layout{display:grid;grid-template-columns:190px 1fr 210px;overflow:hidden;background:#ffffff}

/* Column 1: Sidebar (Explorer) */
.sidebar{border-right:1px solid var(--line);background:#f6f6f6;padding:8px 0;display:flex;flex-direction:column}
.side-title{font-size:9px;text-transform:uppercase;letter-spacing:.12em;color:#888888;font-weight:800;margin:6px 14px 12px;font-family:"Inter",sans-serif}
.tree-item {
  display:flex; align-items:center; justify-content:space-between;
  padding:8px 14px; color:#444; font-size:12px; font-family:"Inter",sans-serif;
  cursor:pointer; transition:background .15s, color .15s;
}
.tree-item:hover { background:rgba(0,0,0,0.04); }
.tree-item.active, .tree-item.demo-hot {
  background:rgba(136,24,24,0.07); color:var(--blue); font-weight:700;
}
.tree-item.active { border-left:3px solid var(--blue); }
.tree-item .count {
  font-size:9px; color:#888; font-family:"JetBrains Mono",monospace;
  background:rgba(0,0,0,0.05); padding:2px 5px; border-radius:4px;
}
.tree-item.active .count { background:rgba(136,24,24,0.12); color:var(--blue); }

/* Column 2: Main Area (Tabs + Query Editor + Data View) */
.main-area{display:grid; grid-template-rows:32px 140px 6px 1fr; overflow:hidden; border-right:1px solid var(--line);}
.main-tabs{display:flex; border-bottom:1px solid var(--line); background:#f3f3f3; height:32px;}
.main-tab{
  padding:8px 16px; font-size:11px; font-weight:700; color:#666;
  border-right:1px solid var(--line); border-bottom:2px solid transparent;
  font-family:"Inter",sans-serif; cursor:pointer; display:flex; align-items:center;
}
.main-tab.active{background:#fff; color:var(--blue); font-weight:800;}

.sql-pane{background:rgba(0,0,0,.003);position:relative;padding:14px;overflow-y:auto;height:100%;}
.editor{font-family:"JetBrains Mono",monospace;font-size:13px;line-height:1.55;color:#222}
.kw{color:var(--blue);font-weight:700}
.tbl{color:#0c58a6}
.col{color:var(--green)}
.num{color:var(--yellow)}
.muted{color:#888888}
.caret{display:inline-block;width:2px;height:1.2em;background:var(--blue);vertical-align:-.22em;margin-left:2px;animation:blink .7s steps(1,end) infinite}@keyframes blink{50%{opacity:0}}

.run-chip{position:absolute;right:14px;top:14px;border-radius:999px;background:linear-gradient(135deg,var(--blue),#a82525);color:#ffffff;padding:6px 12px;font-size:10px;font-weight:800;box-shadow:0 6px 18px rgba(136,24,24,.2);font-family:"Inter",sans-serif;cursor:pointer;transition:transform .1s;z-index:10;}
.run-chip.clicking{transform:scale(0.92)}

/* Egui Style Resize Handle */
.resize-handle {
  background:#e5e7eb; border-top:1px solid var(--line); border-bottom:1px solid var(--line);
  display:flex; align-items:center; justify-content:center; cursor:row-resize; position:relative;
}
.resize-handle::after {
  content:""; width:40px; height:2px; background:#9ca3af; border-radius:99px;
}

.popup{position:absolute;z-index:20;min-width:230px;overflow:hidden;border-radius:10px;border:1px solid rgba(136,24,24,.22);background:rgba(255,255,255,.98);box-shadow:0 15px 35px rgba(0,0,0,.08);backdrop-filter:blur(8px);opacity:0;transform:translateY(-8px) scale(.965);transition:.22s cubic-bezier(.16,1,.3,1);visibility:hidden;pointer-events:none}
.popup.show{visibility:visible;pointer-events:auto;opacity:1;transform:translateY(0) scale(1)}
.popup .head{padding:6px 10px;border-bottom:1px solid rgba(0,0,0,.06);font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:#777777;font-family:"Inter",sans-serif;font-weight:700}
.item{display:flex;justify-content:space-between;gap:18px;padding:9px 11px;font-family:"JetBrains Mono",monospace;font-size:11px;color:#222222}
.item.active{background:linear-gradient(90deg,rgba(136,24,24,.07),rgba(136,24,24,.01))}
.tag{font-family:Inter,system-ui;font-size:9px;color:#777777}

#autoPopup{top:54px;left:18px}
#snippetPopup{top:84px;left:18px;width:340px}
.snippet-result{margin-top:10px;padding:12px;border-radius:8px;background:rgba(16,122,74,.04);border:1px solid rgba(16,122,74,.14);opacity:0;transform:translateY(12px);transition:.28s cubic-bezier(.16,1,.3,1);font-family:"JetBrains Mono",monospace;font-size:12px}
.snippet-result.show{opacity:1;transform:translateY(0)}

/* Data grid */
.data-pane{display:flex;flex-direction:column;overflow:hidden;background:#ffffff;height:100%;}
.data-toolbar{height:36px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid rgba(0,0,0,.06);font-size:11px;color:#666666;font-family:"Inter",sans-serif;font-weight:500;flex-shrink:0}
.status-ok{color:var(--green);opacity:0;transform:translateY(5px);transition:.2s}
.loaded .status-ok{opacity:1;transform:translateY(0)}

.grid-viewport{flex:1;overflow:hidden;position:relative}
.table-grid{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#333333;opacity:.23;filter:blur(2px);transform:translateY(8px);transition:.24s}
.loaded .table-grid{opacity:1;filter:blur(0);transform:translateY(0)}
.table-grid th,.table-grid td{padding:8px 12px;border-bottom:1px solid rgba(0,0,0,.05);text-align:left;white-space:nowrap}
.table-grid th{color:#555555;background:rgba(0,0,0,.025);border-bottom:1px solid rgba(0,0,0,.08);font-weight:700}
.sweep{position:absolute;inset:0 -40% 0 -40%;background:linear-gradient(90deg,transparent,rgba(136,24,24,.08),rgba(16,122,74,.05),transparent);transform:translateX(-70%);opacity:0;pointer-events:none;z-index:2}
.loaded .sweep{animation:sweep .55s ease}

.fk-cell{position:relative;display:inline-flex;gap:5px;align-items:center;border-radius:4px;padding:2px 6px;background:rgba(136,24,24,.05);box-shadow:inset 0 0 0 1px rgba(136,24,24,.1);color:var(--blue);font-weight:700;cursor:pointer}
.fk-cell.demo-hover,.fk-cell:hover{background:rgba(136,24,24,.12);box-shadow:inset 0 0 0 1px rgba(136,24,24,.28)}

/* Column 3: Widgets Sidepane */
.widget-pane{background:#fdfcfb;border-left:1px solid var(--line);padding:12px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;font-family:"Inter",sans-serif}
.widget-header{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#888;font-weight:800;border-bottom:1px solid var(--line);padding-bottom:6px;margin-bottom:4px;}
.widget-card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,0.02);}
.widget-card-title{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:6px;}
.widget-card-val{font-size:22px;font-weight:900;color:var(--text);font-family:"JetBrains Mono",monospace;}
.widget-card-desc{font-size:10px;color:#888;margin-top:2px;}

.connection-status{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#222222}
.indicator{width:8px;height:8px;border-radius:50%;display:block}
.indicator.green{background:var(--green)}
.widget-details{display:flex;flex-direction:column;gap:6px}
.detail-row{display:flex;justify-content:space-between;font-size:11px;color:#555555;font-family:"JetBrains Mono",monospace}
.detail-row span:first-child{color:#888888}
.text-green{color:var(--green);font-weight:700}
.schema-tree{display:flex;flex-direction:column;gap:4px;font-family:"JetBrains Mono",monospace;font-size:11px;color:#333333}
.schema-node{font-weight:700;color:#111111}
.schema-leaf{padding-left:10px;color:#666666}
.stat-large{font-size:28px;font-weight:900;color:#111111;font-family:"JetBrains Mono",monospace}
.stat-label{font-size:10px;color:#888888;font-weight:500;margin-top:2px}
.hover-card{border-radius:8px;border:1px solid rgba(16,122,74,0.15);background:#ffffff;padding:10px;display:flex;flex-direction:column;gap:6px}
.badge{font-size:9px;color:#ffffff;background:var(--green);border-radius:99px;padding:2px 6px;font-weight:700;align-self:flex-start}
.path-line{color:#0b572d;font-family:"JetBrains Mono",monospace;font-size:10px;padding:6px;border-radius:6px;background:rgba(16,122,74,.06);margin-top:4px}
.fps-meter{font-size:10px;font-weight:700;color:var(--green);background:rgba(16,122,74,.08);padding:4px 8px;border-radius:4px;align-self:flex-start}

.scrollbar{position:absolute;right:6px;top:8px;bottom:8px;width:4px;background:rgba(0,0,0,.04);border-radius:99px}
.thumb{position:absolute;right:0;top:0;width:4px;height:44px;background:linear-gradient(var(--blue),#a82525);border-radius:99px;box-shadow:0 0 8px rgba(136,24,24,.3)}

/* Virtual Grid Scene Load Center */
.massive-load-center {
  position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
  background:linear-gradient(180deg, rgba(255,255,255,0.85) 0%, #fff 100%); z-index:10; backdrop-filter:blur(4px);
}
.massive-load-btn {
  background:linear-gradient(135deg, var(--red), var(--pink)); color:#fff;
  border:none; border-radius:99px; padding:16px 48px; font-size:18px; font-weight:900;
  letter-spacing:0.1em; cursor:pointer; box-shadow:0 12px 30px rgba(185,28,28,0.3);
  font-family:"Inter",sans-serif; transition:transform 0.2s, box-shadow 0.2s;
}
.massive-load-btn.hover { transform:scale(1.05); box-shadow:0 16px 40px rgba(185,28,28,0.4); }

/* Mouse, highlight spots */
.mouse{position:fixed;z-index:9000;pointer-events:none;left:0;top:0}
.mouse-scale{transition:transform .12s cubic-bezier(0.1, 0.8, 0.2, 1.0);transform-origin:0 0;filter:drop-shadow(0 8px 12px rgba(0,0,0,.15))}
.mouse.click .mouse-scale{transform:scale(0.82)}
.mouse-scale:after{content:"";position:absolute;left:9px;top:9px;width:18px;height:18px;border:2px solid rgba(136,24,24,.8);border-radius:50%;opacity:0}
.mouse.click .mouse-scale:after{animation:mouseRing .55s ease forwards}

/* Physical Click Ripple in DOM */
.click-ripple {
  position: fixed;
  border: 2px solid rgba(136,24,24,0.8);
  border-radius: 50%;
  pointer-events: none;
  z-index: 8999;
  transform: translate(-50%, -50%);
  animation: ripple-out 0.4s ease-out forwards;
}

.scroll-note{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);z-index:50;color:#888888;font-size:11px;letter-spacing:.1em;font-family:"Inter",sans-serif;font-weight:700;text-transform:uppercase}
.table-row.clicking:after,.run-chip.clicking:after,.load1m.clicking:after,.fk-cell.demo-hover:after{content:"";position:absolute;left:50%;top:50%;width:20px;height:20px;margin:-10px;border:2px solid rgba(136,24,24,.8);border-radius:50%;animation:ripple .55s ease forwards}

/* Responsive & Mobile optimizations */
@media(max-width:980px){
  body{height:600vh}
  .stage{grid-template-columns:1fr; grid-template-rows:220px 1fr; padding:64px 20px 20px; gap:20px}
  .brand{top:18px;left:20px}
  .menu-container{top:18px;right:20px}
  .progress{right:64px;top:25px}
  .copy{min-height:220px; display:flex; align-items:center; justify-content:center;}
  .copy-scene{transform:translateY(-50%) translateY(12px)}
  .copy-scene.active{transform:translateY(-50%)}
  .copy p{display:block; font-size:14px; margin-top:10px;}
  h1{font-size:28px;}
  .chips {margin-top:14px;}
  .canvas{min-height:380px;height:100%}
  .software-layout{grid-template-columns:110px 1fr}
  .widget-pane{display:none}
  .sidebar{padding:8px 0}
  .tree-item{font-size:10px; padding:6px 8px}
  .tree-item .count{display:none}
}
</style>
CSS;

if ($style_content) {
    $content = str_replace($style_content, $new_style, $content);
}

// Ensure the HTML layout for main-area in front-page.php has the resize handle row
// Old: <main class="main-area"><div class="main-tabs">...</div><div class="sql-pane">...</div><section class="data-pane">...</section></main>
// New: <main class="main-area"><div class="main-tabs">...</div><div class="sql-pane">...</div><div class="resize-handle"></div><section class="data-pane">...</section></main>

$content = str_replace('<div class="sql-pane" id="sqlPane">', '</div><div class="sql-pane" id="sqlPane">', $content);
// Clean up any double main-tabs if already present
$content = str_replace('<main class="main-area">'."\n".'<div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>'."\n".'<div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>', '<main class="main-area">'."\n".'<div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>', $content);

// Let's rewrite the main-area markup explicitly to be safe:
$old_main_area = <<<HTML
        <!-- Main Area (Middle Column) -->
        <main class="main-area">
<div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>
          <div class="sql-pane" id="sqlPane">
            <div class="editor" id="sqlEditor"></div>
            <div class="popup" id="autoPopup"><div class="head">Expected keyword</div><div class="item active"><span>FROM</span><span class="tag">keyword</span></div><div class="item"><span>FOR UPDATE</span><span class="tag">keyword</span></div><div class="item"><span>FETCH</span><span class="tag">keyword</span></div></div>
            <div class="popup" id="snippetPopup"><div class="head"><?php esc_html_e('Smart snippet', 'hoasen-theme'); ?></div><div class="item active"><span>JOIN orders by FK</span><span class="tag">orders.user_id → users.id</span></div><div class="item"><span>LEFT JOIN profile</span><span class="tag">profiles.user_id → users.id</span></div></div>
            <div class="run-chip" id="runChip">RUN</div>
          </div>
          
          <section class="data-pane" id="dataPane">
            <div class="data-toolbar">
              <span id="dataTitle">Data table</span>
              <span class="status-ok" id="statusOk">✓ loaded</span>
            </div>
            <div class="grid-viewport">
              <div class="sweep" id="gridSweep"></div>
              <table class="table-grid" id="dataGrid">
                <thead><tr id="gridHeader"></tr></thead>
                <tbody id="dataRows"></tbody>
              </table>
              <div class="scrollbar" id="gridScrollbar"><div class="thumb" id="gridThumb"></div></div>
            </div>
          </section>
        </main>
HTML;

$new_main_area = <<<HTML
        <!-- Main Area (Middle Column) -->
        <main class="main-area">
          <div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>
          <div class="sql-pane" id="sqlPane">
            <div class="editor" id="sqlEditor"></div>
            <div class="popup" id="autoPopup"><div class="head">Expected keyword</div><div class="item active"><span>FROM</span><span class="tag">keyword</span></div><div class="item"><span>FOR UPDATE</span><span class="tag">keyword</span></div><div class="item"><span>FETCH</span><span class="tag">keyword</span></div></div>
            <div class="popup" id="snippetPopup"><div class="head"><?php esc_html_e('Smart snippet', 'hoasen-theme'); ?></div><div class="item active"><span>JOIN orders by FK</span><span class="tag">orders.user_id → users.id</span></div><div class="item"><span>LEFT JOIN profile</span><span class="tag">profiles.user_id → users.id</span></div></div>
            <div class="run-chip" id="runChip">RUN</div>
          </div>
          <div class="resize-handle"></div>
          <section class="data-pane" id="dataPane">
            <div class="data-toolbar">
              <span id="dataTitle">Data table</span>
              <span class="status-ok" id="statusOk">✓ loaded</span>
            </div>
            <div class="grid-viewport">
              <div class="sweep" id="gridSweep"></div>
              <table class="table-grid" id="dataGrid">
                <thead><tr id="gridHeader"></tr></thead>
                <tbody id="dataRows"></tbody>
              </table>
              <div class="scrollbar" id="gridScrollbar"><div class="thumb" id="gridThumb"></div></div>
            </div>
          </section>
        </main>
HTML;

$content = str_replace($old_main_area, $new_main_area, $content);

file_put_contents($file, $content);
echo "Style and Main Area layout updated.\n";
