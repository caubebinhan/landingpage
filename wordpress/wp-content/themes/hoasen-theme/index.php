<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
<title>HoaSen Table — Cinematic Scroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=Inter:wght@400;500;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
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
    radial-gradient(circle at 18% 16%, rgba(136,24,24,.05), transparent 34%),
    radial-gradient(circle at 80% 24%, rgba(16,122,74,.04), transparent 32%),
    radial-gradient(circle at 48% 86%, rgba(157,23,77,.04), transparent 30%),
    linear-gradient(180deg,#f0f0f0,#e5e5e5);
  z-index: -1;
}
.stage{position:fixed; inset:0; display:grid; grid-template-columns:.78fr 1.4fr; gap:56px; align-items:center; padding:56px; overflow:hidden}
.grid{position:absolute; inset:0; opacity:.18; pointer-events:none; background-image:linear-gradient(rgba(0,0,0,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(0,0,0,.06) 1px,transparent 1px); background-size:54px 54px; mask-image:radial-gradient(circle at 58% 50%,black,transparent 78%)}

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
.blog-layout{display:grid;grid-template-columns:220px 1fr;height:450px}
.blog-sidebar{border-right:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.015);padding:18px}
.blog-sidebar h3{font-size:10px;text-transform:uppercase;color:#888888;letter-spacing:.05em;margin-bottom:12px}
.blog-link{padding:10px 12px;border-radius:6px;font-size:12px;font-weight:700;color:#555555;cursor:pointer;margin-bottom:6px;transition:all .2s}
.blog-link.active,.blog-link:hover{background:rgba(136,24,24,.06);color:var(--blue)}
.blog-content{padding:24px;overflow-y:auto;background:#ffffff;font-family:"Cormorant Garamond",serif}
.blog-post{display:none}
.blog-post.active{display:block}
.blog-post h2{font-size:24px;font-weight:800;margin-bottom:8px;color:#111111;line-height:1.2}
.post-meta{font-size:12px;color:#888888;margin-bottom:16px;font-family:"Inter",sans-serif}
.blog-post p{font-size:17px;line-height:1.6;color:#333333;margin-bottom:14px}
.contact-content{padding:24px}
.contact-content p{font-size:14px;color:#4b5563;line-height:1.5;margin-bottom:20px}
.contact-links{display:flex;flex-direction:column;gap:12px}
.contact-item{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:10px;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.01);text-decoration:none;color:#333333;font-family:"JetBrains Mono",monospace;font-size:12px;transition:all .2s}
.contact-item.fb{color:#1877f2}
.contact-item:hover{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.15)}

.progress{position:absolute;right:84px;top:38px;z-index:50;display:flex;gap:8px}
.bar{width:32px;height:4px;border-radius:99px;background:rgba(0,0,0,0.08);overflow:hidden}
.bar span{display:block;width:0;height:100%;background:linear-gradient(90deg,var(--blue),var(--green))}

.copy{position:relative;z-index:5;min-height:360px}
.copy-scene{position:absolute;left:0;right:0;top:50%;transform:translateY(-42%) translateY(24px);opacity:0;filter:blur(10px)}
.copy-scene.active{opacity:1;filter:blur(0);transform:translateY(-42%)}
.kicker{font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--blue);font-weight:900;margin-bottom:14px;font-family:"Inter",sans-serif}
h1{font-size:clamp(36px,5vw,72px);line-height:.94;letter-spacing:-.03em;font-weight:800;max-width:650px;color:#111111;text-wrap:balance}
.copy p{font-size:18px;line-height:1.5;color:#4b5563;margin-top:22px;max-width:430px;font-family:"Inter",sans-serif;text-wrap:pretty}
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

.software-layout{display:grid;grid-template-columns:170px 1fr 230px;overflow:hidden;background:#ffffff}

/* Column 1: Sidebar */
.sidebar{border-right:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.012);padding:18px 12px;display:flex;flex-direction:column}
.side-title{font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:#777777;font-weight:700;margin-bottom:13px;font-family:"Inter",sans-serif}
.table-row{display:flex;justify-content:space-between;align-items:center;padding:9px 10px;border-radius:8px;margin-bottom:5px;color:#555555;font-size:12px;position:relative;transition:.25s;font-family:"JetBrains Mono",monospace;cursor:pointer}
.table-row span{color:#999999;font-size:10px}
.table-row.active,.table-row.demo-hot{background:rgba(136,24,24,.07);color:var(--blue);box-shadow:inset 0 0 0 1px rgba(136,24,24,.12);font-weight:700}
.table-row.active span,.table-row.demo-hot span{color:var(--blue)}

/* Column 2: Main Area */
.main-area{display:grid;grid-template-rows:48% 52%;overflow:hidden;border-right:1px solid rgba(0,0,0,.08)}
.sql-pane{border-bottom:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.003);position:relative;padding:18px;overflow-y:auto}
.editor{font-family:"JetBrains Mono",monospace;font-size:14px;line-height:1.62;color:#222222;position:relative;z-index:2}
.kw{color:var(--blue);font-weight:700}
.tbl{color:#0c58a6}
.col{color:var(--green)}
.num{color:var(--yellow)}
.muted{color:#888888}
.caret{display:inline-block;width:2px;height:1.2em;background:var(--blue);vertical-align:-.22em;margin-left:2px;animation:blink .7s steps(1,end) infinite}@keyframes blink{50%{opacity:0}}

.run-chip{position:absolute;right:18px;top:18px;border-radius:999px;background:linear-gradient(135deg,var(--blue),#a82525);color:#ffffff;padding:7px 12px;font-size:11px;font-weight:800;box-shadow:0 6px 18px rgba(136,24,24,.2);font-family:"Inter",sans-serif;cursor:pointer;transition:transform .1s}
.run-chip.clicking{transform:scale(0.92)}

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
.data-pane{display:flex;flex-direction:column;overflow:hidden;background:#ffffff}
.data-toolbar{height:36px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid rgba(0,0,0,.06);font-size:11px;color:#666666;font-family:"Inter",sans-serif;font-weight:500;flex-shrink:0}
.status-ok{color:var(--green);opacity:0;transform:translateY(5px);transition:.2s}
.loaded .status-ok{opacity:1;transform:translateY(0)}

.grid-viewport{flex:1;overflow:hidden;position:relative}
.table-grid{width:100%;border-collapse:collapse;font-family:"JetBrains Mono",monospace;font-size:11px;color:#333333;opacity:.23;filter:blur(2px);transform:translateY(8px);transition:.24s}
.loaded .table-grid{opacity:1;filter:blur(0);transform:translateY(0)}
.table-grid th,.table-grid td{padding:8px 12px;border-bottom:1px solid rgba(0,0,0,.05);text-align:left;white-space:nowrap}
.table-grid th{color:#555555;background:rgba(0,0,0,.025);border-bottom:1px solid rgba(0,0,0,.08);font-weight:700}
.sweep{position:absolute;inset:0 -40% 0 -40%;background:linear-gradient(90deg,transparent,rgba(136,24,24,.08),rgba(16,122,74,.05),transparent);transform:translateX(-70%);opacity:0;pointer-events:none;z-index:2}
.loaded .sweep{animation:sweep .55s ease}@keyframes sweep{0%{opacity:0;transform:translateX(-70%)}28%{opacity:1}100%{opacity:0;transform:translateX(70%)}}

.fk-cell{position:relative;display:inline-flex;gap:5px;align-items:center;border-radius:4px;padding:2px 6px;background:rgba(136,24,24,.05);box-shadow:inset 0 0 0 1px rgba(136,24,24,.1);color:var(--blue);font-weight:700;cursor:pointer}
.fk-cell.demo-hover,.fk-cell:hover{background:rgba(136,24,24,.12);box-shadow:inset 0 0 0 1px rgba(136,24,24,.28)}

/* Column 3: Widget Pane */
.widget-pane{background:rgba(0,0,0,.008);padding:18px 14px;overflow-y:auto;display:flex;flex-direction:column;gap:16px;font-family:"Inter",sans-serif}
.widget-title{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#888888;font-weight:700;border-bottom:1px solid rgba(0,0,0,.06);padding-bottom:6px}
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
.hover-card{border-radius:10px;border:1px solid rgba(16,122,74,0.2);background:#ffffff;padding:12px;box-shadow:0 8px 24px rgba(0,0,0,.04);display:flex;flex-direction:column;gap:8px}
.badge{font-size:9px;color:#ffffff;background:var(--green);border-radius:99px;padding:2px 6px;font-weight:700;align-self:flex-start}
.path-line{color:#0b572d;font-family:"JetBrains Mono",monospace;font-size:10px;padding:6px;border-radius:6px;background:rgba(16,122,74,.06);margin-top:4px}
.fps-meter{font-size:10px;font-weight:700;color:var(--green);background:rgba(16,122,74,.08);padding:4px 8px;border-radius:4px;align-self:flex-start}

.scrollbar{position:absolute;right:6px;top:8px;bottom:8px;width:4px;background:rgba(0,0,0,.04);border-radius:99px}
.thumb{position:absolute;right:0;top:0;width:4px;height:44px;background:linear-gradient(var(--blue),#a82525);border-radius:99px;box-shadow:0 0 8px rgba(136,24,24,.3)}

/* Mouse, highlight spots */
.mouse{position:fixed;z-index:9000;pointer-events:none;left:0;top:0}
.mouse-scale{transition:transform .12s cubic-bezier(0.1, 0.8, 0.2, 1.0);transform-origin:0 0;filter:drop-shadow(0 8px 12px rgba(0,0,0,.15))}
.mouse.click .mouse-scale{transform:scale(0.82)}
.mouse-scale:after{content:"";position:absolute;left:9px;top:9px;width:18px;height:18px;border:2px solid rgba(136,24,24,.8);border-radius:50%;opacity:0}
.mouse.click .mouse-scale:after{animation:mouseRing .55s ease forwards}@keyframes mouseRing{0%{opacity:1;transform:scale(.6)}100%{opacity:0;transform:scale(3.4)}}

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
@keyframes ripple-out {
  from { width: 0; height: 0; opacity: 1; }
  to { width: 40px; height: 40px; opacity: 0; }
}



.scroll-note{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);z-index:50;color:#888888;font-size:11px;letter-spacing:.1em;font-family:"Inter",sans-serif;font-weight:700;text-transform:uppercase}

.table-row.clicking:after,.run-chip.clicking:after,.load1m.clicking:after,.fk-cell.demo-hover:after{content:"";position:absolute;left:50%;top:50%;width:20px;height:20px;margin:-10px;border:2px solid rgba(136,24,24,.8);border-radius:50%;animation:ripple .55s ease forwards}

.big-num-container{padding:14px;border:1px solid rgba(0,0,0,.06);border-radius:12px;background:rgba(0,0,0,.008);display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.load1m{border:0;border-radius:999px;background:linear-gradient(135deg,var(--blue),#a82525);padding:10px 15px;color:#ffffff;font-weight:800;font-size:11px;box-shadow:0 8px 24px rgba(136,24,24,.2);font-family:"Inter",sans-serif;cursor:pointer}

@media(max-width:980px){
  body{height:560vh}
  .stage{grid-template-columns:1fr;grid-template-rows:auto 1fr;padding:74px 16px 32px;gap:14px}
  .brand{top:18px;left:18px}
  .menu-container{top:18px;right:18px}
  .progress{right:64px;top:25px}
  .copy{min-height:132px}
  .copy p{display:none}
  h1{font-size:36px}
  .canvas{min-height:540px;height:64vh}
  .software-layout{grid-template-columns:120px 1fr}
  .widget-pane{display:none}
  .sidebar{padding:12px 6px}
  .table-row{font-size:11px;padding:9px 5px}
  .mouse{display:none}
  .editor{font-size:12px}
}
</style>
</head>
<body>
<div class="stage">
  <div class="grid"></div>
  <div class="brand"><span class="logo"></span><span>HoaSen Table</span></div>
  
  <!-- Menu Button top right -->
  <div class="menu-container">
    <button class="menu-btn" id="menuBtn" aria-label="Menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <div class="menu-dropdown" id="menuDropdown">
      <a href="#" id="btnBlog">BLOG</a>
      <a href="#" id="btnContact">CONTACT</a>
    </div>
  </div>

  <div class="progress" id="progress"></div>

  <div class="copy">
    <section class="copy-scene active">
      <div class="kicker">01 / CONNECT</div>
      <h1>Khởi đầu thông minh từ Connection Board.</h1>
      <p>Quản lý cấu hình kết nối được phân nhóm và gán mã màu trực quan. Dễ dàng phân biệt môi trường Production (Đỏ) và Development (Xanh) để tránh nhầm lẫn.</p>
      <div class="chips">
        <span class="chip hot">Grouped Connections</span>
        <span class="chip">Color-Coded Labels</span>
      </div>
    </section>

    <section class="copy-scene">
      <div class="kicker">02 / AUTOCOMPLETE</div>
      <h1>Autocomplete chuẩn xác theo Ngữ pháp.</h1>
      <p>Hiểu sâu cú pháp và các phương ngữ (Dialect) của MySQL, Postgres, SQLite... Ngăn chặn hoàn toàn các gợi ý rác không hợp lệ.</p>
      <div class="chips">
        <span class="chip hot">Grammar-Legality</span>
        <span class="chip">Dialect-Aware</span>
      </div>
    </section>
    
    <section class="copy-scene">
      <div class="kicker">03 / INTELLIGENCE</div>
      <h1>Smart Snippet thông minh theo Khoá Ngoại.</h1>
      <p>Tự động phân tích các quan hệ khoá ngoại (FK) để gợi ý cấu trúc JOIN chính xác. Điền nhanh các mệnh đề ON phức tạp chỉ bằng một phím bấm.</p>
      <div class="chips">
        <span class="chip hot">FK-Based Join</span>
        <span class="chip">Predictive Logic</span>
      </div>
    </section>
    
    <section class="copy-scene">
      <div class="kicker">04 / PRODUCTIVITY</div>
      <h1>Native Workspace tối giản hiệu năng cao.</h1>
      <p>Môi trường làm việc thuần khiết được thiết kế nhằm tối đa hoá dòng chảy công việc (flow). Trực quan hoá lược đồ và dữ liệu lập tức mà không có chi tiết thừa.</p>
      <div class="chips">
        <span class="chip hot">Focus-First UI</span>
        <span class="chip">Pure Performance</span>
      </div>
    </section>
    
    <section class="copy-scene">
      <div class="kicker">05 / DISCOVERY</div>
      <h1>Truy vấn quan hệ bảng tức thời (Hover Relation).</h1>
      <p>Di chuột lên các giá trị khoá ngoại để xem nhanh nội dung bản ghi liên kết mà không cần viết lệnh SQL phụ hay chuyển đổi tab làm việc.</p>
      <div class="chips">
        <span class="chip hot">Instant Inspection</span>
        <span class="chip">Zero Friction</span>
      </div>
    </section>
    
    <section class="copy-scene">
      <div class="kicker">06 / CAPACITY</div>
      <h1>Cuộn mượt mà hàng triệu dòng với Virtual Grid.</h1>
      <p>Chỉ kết xuất những phần dữ liệu hiển thị trong khung nhìn (Viewport). Đảm bảo giao diện cuộn cực mượt mà ngay cả với cơ sở dữ liệu khổng lồ.</p>
      <div class="chips">
        <span class="chip hot">1,000,000+ Rows</span>
        <span class="chip">Viewport Rendering</span>
      </div>
    </section>
  </div>

  <div class="canvas" id="canvas">
    <div class="window">
      <div class="winbar">
        <div class="traffic"><i class="r"></i><i class="y"></i><i class="g"></i></div>
        <span class="path" id="winPath"><strong>HoaSen Table</strong> · Connection Board</span>
      </div>
      
      <!-- Connection Board Sub-Layout (Scene 0) -->
      <div class="connection-board" id="connectionBoard">
        <div class="conn-group dev">
          <div class="group-header">
            <span class="group-name">Development</span>
            <span class="group-badge dev">DEV</span>
          </div>
          <div class="conn-card">
            <div class="db-icon my">My</div>
            <div class="conn-details">
              <span class="conn-name">miraiai_dev</span>
              <span class="conn-host">localhost:3306</span>
            </div>
          </div>
          <div class="conn-card">
            <div class="db-icon sq">Sq</div>
            <div class="conn-details">
              <span class="conn-name">hoasentable_local</span>
              <span class="conn-host">local.db</span>
            </div>
          </div>
        </div>
        
        <div class="conn-group prod">
          <div class="group-header">
            <span class="group-name">Production</span>
            <span class="group-badge prod">PROD</span>
          </div>
          <div class="conn-card" id="cardAipbx">
            <div class="db-icon pg">Pg</div>
            <div class="conn-details">
              <span class="conn-name">aipbx_prod</span>
              <span class="conn-host">10.0.0.4:5432</span>
            </div>
          </div>
          <div class="conn-card">
            <div class="db-icon pg">Pg</div>
            <div class="conn-details">
              <span class="conn-name">payments_db</span>
              <span class="conn-host">10.0.0.5:5432</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Workspace Sub-Layout (Scenes 1 - 5) -->
      <div class="software-layout" id="softwareLayout">
        <!-- Sidebar (Left Column) -->
        <aside class="sidebar">
          <div class="side-title">Tables</div>
          <div class="table-row" id="row-users">users <span>12k</span></div>
          <div class="table-row" id="row-orders">orders <span>1M</span></div>
          <div class="table-row" id="row-payments">payments <span>88k</span></div>
          <div class="table-row" id="row-clinics">clinics <span>42</span></div>
          <div class="table-row" id="row-settings">settings <span>json</span></div>
        </aside>

        <!-- Main Area (Middle Column) -->
        <main class="main-area">
          <div class="sql-pane" id="sqlPane">
            <div class="editor" id="sqlEditor"></div>
            <div class="popup" id="autoPopup"><div class="head">Expected keyword</div><div class="item active"><span>FROM</span><span class="tag">keyword</span></div><div class="item"><span>FOR UPDATE</span><span class="tag">keyword</span></div><div class="item"><span>FETCH</span><span class="tag">keyword</span></div></div>
            <div class="popup" id="snippetPopup"><div class="head">Smart snippet</div><div class="item active"><span>JOIN orders by FK</span><span class="tag">orders.user_id → users.id</span></div><div class="item"><span>LEFT JOIN profile</span><span class="tag">profiles.user_id → users.id</span></div></div>
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

        <!-- Widget Pane (Right Column) -->
        <aside class="widget-pane" id="widgetPane">
          <!-- Rendered dynamically -->
        </aside>
      </div>
    </div>

  </div>
  
  <div class="scroll-note">Cuộn tiếp ↓</div>
</div>

<!-- Fixed Viewport Mouse Cursor -->
<div class="mouse" id="mouse" aria-hidden="true"><div class="mouse-scale"><svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 3L24 15.4L15.4 17.4L11.1 26L5 3Z" fill="#F3F8FF" stroke="#0B1020" stroke-width="1.8" stroke-linejoin="round"/></svg></div></div>

<!-- Blog Modal -->
<div class="overlay-modal" id="blogModal">
  <div class="modal-card">
    <button class="close-btn" id="closeBlog">&times;</button>
    <div class="modal-header">HoaSen Table Journal</div>
    <div class="blog-layout">
      <aside class="blog-sidebar">
        <h3>Bài viết</h3>
        <div class="blog-link active" data-post="post1">Tối ưu hóa Autocomplete SQL</div>
        <div class="blog-link" data-post="post2">Cơ chế Virtual Grid triệu dòng</div>
        <div class="blog-link" data-post="post3">Thiết kế UI vintage bằng OKLCH</div>
      </aside>
      <main class="blog-content">
        <article id="post1" class="blog-post active">
          <h2>Tối ưu hóa Autocomplete SQL bằng Phân tích Ngữ pháp</h2>
          <p class="post-meta">Xuất bản ngày 09/07/2026 bởi Đội ngũ kỹ thuật</p>
          <p>Trong các trình soạn thảo SQL truyền thống, autocomplete thường hoạt động bằng cách quét chuỗi ký tự đơn giản. Điều này dẫn đến rất nhiều gợi ý rác không hợp lệ tại vị trí con trỏ.</p>
          <p>HoaSen Table giải quyết vấn đề này bằng cách tích hợp trực tiếp bộ phân tích cú pháp (parser) của từng hệ quản trị cơ sở dữ liệu. Khi bạn gõ, hệ thống sẽ dựng một AST (Abstract Syntax Tree) tạm thời, từ đó xác định chính xác các từ khoá hoặc đối tượng lược đồ hợp lệ tiếp theo.</p>
          <p>Ví dụ, nếu bạn vừa gõ <code>SELECT * F</code>, trình soạn thảo sẽ biết chắc chắn ngữ pháp chỉ cho phép mệnh đề <code>FROM</code> tại đây và lọc bỏ toàn bộ các cột hay bảng bắt đầu bằng chữ "F".</p>
        </article>
        <article id="post2" class="blog-post">
          <h2>Cơ chế Virtual Grid: Xử lý 1 triệu dòng mượt mà</h2>
          <p class="post-meta">Xuất bản ngày 02/07/2026 bởi Đội ngũ hiệu năng</p>
          <p>Hiển thị hàng triệu dòng dữ liệu trên giao diện đồ họa (GUI) luôn là một thử thách lớn về bộ nhớ và CPU. Trình duyệt hoặc ứng dụng thông thường sẽ bị đơ cứng nếu cố gắng render hàng trăm ngàn thẻ HTML cùng lúc.</p>
          <p>HoaSen Table sử dụng kỹ thuật <b>Viewport Virtualization</b>. Chúng tôi chỉ vẽ các dòng dữ liệu đang hiển thị trong khung nhìn của người dùng (khoảng 20-30 dòng). Khi người dùng cuộn, các phần tử này sẽ được tái sử dụng để nạp dữ liệu mới, giữ cho số lượng nút DOM luôn ở mức tối thiểu và bộ nhớ tiêu thụ chỉ khoảng vài kilobyte.</p>
        </article>
        <article id="post3" class="blog-post">
          <h2>Thiết kế Giao diện Vintage: Nghệ thuật của sự Tiết chế</h2>
          <p class="post-meta">Xuất bản ngày 25/06/2026 bởi Đội ngũ thiết kế</p>
          <p>Thời đại của các giao diện SaaS phẳng lì và đơn điệu đã làm nhạt nhoà cá tính thương hiệu. Với HoaSen Table, chúng tôi quay lại giá trị cốt lõi của nghệ thuật in ấn cổ điển: kiểu chữ tinh tế và màu sắc có chiều sâu.</p>
          <p>Sự kết hợp giữa font chữ serif trang trọng <b>Cormorant Garamond</b>, độ tương phản sắc nét của sắc xám tro ash-gray và màu đỏ huyết dụ oxblood giúp tạo ra một giao diện làm việc đầy cảm hứng nhưng vẫn giữ được sự tập trung tối đa cho công việc kỹ thuật phức tạp.</p>
        </article>
      </main>
    </div>
  </div>
</div>

<!-- Contact Modal -->
<div class="overlay-modal" id="contactModal">
  <div class="modal-card mini">
    <button class="close-btn" id="closeContact">&times;</button>
    <div class="modal-header">Liên hệ với HoaSen Table</div>
    <div class="contact-content">
      <p>Chúng tôi luôn đón nhận mọi ý kiến đóng góp và phản hồi từ cộng đồng nhà phát triển.</p>
      <div class="contact-links">
        <a href="https://facebook.com/hoasentable" target="_blank" class="contact-item fb">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c4.56-.93 8-4.96 8-9.75z"/></svg>
          <span>facebook.com/hoasentable</span>
        </a>
        <a href="mailto:support@hoasentable.localhost" class="contact-item mail">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <span>support@hoasentable.localhost</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
const SCENES=6;
const progress=document.getElementById('progress');
for(let i=0;i<SCENES;i++){const b=document.createElement('div');b.className='bar';b.innerHTML='<span></span>';progress.appendChild(b)}
const bars=[...progress.querySelectorAll('span')];
const copies=[...document.querySelectorAll('.copy-scene')];
const mouse=document.getElementById('mouse');

// Layout views
const connectionBoard = document.getElementById('connectionBoard');
const softwareLayout = document.getElementById('softwareLayout');

// Software UI elements
const winPath=document.getElementById('winPath');
const sqlEditor=document.getElementById('sqlEditor');
const autoPopup=document.getElementById('autoPopup');
const snippetPopup=document.getElementById('snippetPopup');
const runChip=document.getElementById('runChip');
const dataPane=document.getElementById('dataPane');
const dataTitle=document.getElementById('dataTitle');
const statusOk=document.getElementById('statusOk');
const gridHeader=document.getElementById('gridHeader');
const dataRows=document.getElementById('dataRows');
const widgetPane=document.getElementById('widgetPane');
const gridScrollbar=document.getElementById('gridScrollbar');
const gridThumb=document.getElementById('gridThumb');
const gridSweep=document.getElementById('gridSweep');

// Sidebar rows
const rows = {
  users: document.getElementById('row-users'),
  orders: document.getElementById('row-orders'),
  payments: document.getElementById('row-payments'),
  clinics: document.getElementById('row-clinics'),
  settings: document.getElementById('row-settings')
};

// Target items
const cardAipbx = document.getElementById('cardAipbx');

// Menu & Overlay elements
const menuBtn = document.getElementById('menuBtn');
const menuDropdown = document.getElementById('menuDropdown');
const btnBlog = document.getElementById('btnBlog');
const btnContact = document.getElementById('btnContact');
const blogModal = document.getElementById('blogModal');
const contactModal = document.getElementById('contactModal');
const closeBlog = document.getElementById('closeBlog');
const closeContact = document.getElementById('closeContact');

// Menu toggle
menuBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  menuDropdown.classList.toggle('show');
});
document.addEventListener('click', () => {
  menuDropdown.classList.remove('show');
});

// Modals logic
btnBlog.addEventListener('click', (e) => {
  e.preventDefault();
  blogModal.classList.add('show');
});
btnContact.addEventListener('click', (e) => {
  e.preventDefault();
  contactModal.classList.add('show');
});
closeBlog.addEventListener('click', () => blogModal.classList.remove('show'));
closeContact.addEventListener('click', () => contactModal.classList.remove('show'));

// Blog tab switching
const blogLinks = document.querySelectorAll('.blog-link');
const blogPosts = document.querySelectorAll('.blog-post');
blogLinks.forEach(link => {
  link.addEventListener('click', () => {
    blogLinks.forEach(l => l.classList.remove('active'));
    blogPosts.forEach(p => p.classList.remove('active'));
    link.classList.add('active');
    document.getElementById(link.dataset.post).classList.add('active');
  });
});

function clamp(v,a=0,b=1){return Math.min(b,Math.max(a,v))}
function ease(t){return t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2}
function lerp(a,b,t){return a+(b-a)*t}
function mix(a,b,t){t=ease(clamp(t));return{x:lerp(a.x,b.x,t),y:lerp(a.y,b.y,t)}}

let lastClick = false;
function setMouse(p,click=false){
  mouse.style.transform=`translate(${p.x}px,${p.y}px)`;
  mouse.classList.toggle('click',click);
  
  if (click && !lastClick) {
    const rip = document.createElement('div');
    rip.className = 'click-ripple';
    rip.style.left = p.x + 'px';
    rip.style.top = p.y + 'px';
    document.body.appendChild(rip);
    setTimeout(() => rip.remove(), 400);
  }
  lastClick = click;
}



// State helpers
function clearStates(){
  autoPopup.classList.remove('show');
  snippetPopup.classList.remove('show');
  runChip.classList.remove('clicking');
  dataPane.classList.remove('loaded');
  Object.values(rows).forEach(r => r.classList.remove('active','demo-hot','clicking'));
  cardAipbx.classList.remove('active');
  gridScrollbar.style.display = 'none';
}

function updateScene0(p) {
  winPath.innerHTML = '<strong>HoaSen Table</strong> · Connection Board';
  connectionBoard.classList.add('show');
  softwareLayout.classList.remove('show');
  
  const winRect = document.querySelector('.window').getBoundingClientRect();
  const startM = { x: winRect.left + winRect.width / 2, y: winRect.top + winRect.height / 2 };
  
  const cardRect = cardAipbx.getBoundingClientRect();
  const endM = { x: cardRect.left + cardRect.width * 0.5, y: cardRect.top + cardRect.height * 0.5 };
  
  const m = mix(startM, endM, p);
  const click = p > 0.8 && p < 0.95;
  if(p > 0.75) {
    cardAipbx.classList.add('active');
  }
  setMouse(m, click);
}

function updateScene1(p) {
  winPath.innerHTML = '<strong>query.sql</strong> · autocomplete';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.users.classList.add('active');
  
  if(p<.38){sqlEditor.innerHTML='<span class="kw">SELECT</span> * <span class="caret"></span>'}
  else if(p<.62){sqlEditor.innerHTML='<span class="kw">SELECT</span> * <span class="kw">F</span><span class="caret"></span>'}
  else if(p<.82){sqlEditor.innerHTML='<span class="kw">SELECT</span> * <span class="kw">F</span><span class="caret"></span>';autoPopup.classList.add('show')}
  else{sqlEditor.innerHTML='<span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">users</span> u<span class="caret"></span>';autoPopup.classList.add('show')}
  
  // Set Widget
  widgetPane.innerHTML = `
    <div class="widget-title">Kết nối</div>
    <div class="connection-status">
      <span class="indicator green"></span>
      <span>PostgreSQL Local</span>
    </div>
    <div class="widget-details">
      <div class="detail-row"><span>Host:</span><span>10.0.0.4</span></div>
      <div class="detail-row"><span>Port:</span><span>5432</span></div>
      <div class="detail-row"><span>DB:</span><span>aipbx_prod</span></div>
      <div class="detail-row"><span>Dialect:</span><span>PostgreSQL</span></div>
    </div>
  `;
  
  dataTitle.textContent = "Data table";
  gridHeader.innerHTML = '<th>id</th><th>name</th><th>email</th>';
  dataRows.innerHTML = '<tr><td colspan="3" class="muted">Chưa thực thi truy vấn</td></tr>';
  
  const cardRect = cardAipbx.getBoundingClientRect();
  const startM = { x: cardRect.left + cardRect.width * 0.5, y: cardRect.top + cardRect.height * 0.5 };
  
  const editorRect = sqlEditor.getBoundingClientRect();
  const endM = { x: editorRect.left + 150, y: editorRect.top + 20 };
  const m = mix(startM, endM, p);
  
  setMouse(m, false);
}

function updateScene2(p) {
  winPath.innerHTML = '<strong>query.sql</strong> · smart snippet';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.users.classList.add('active');
  
  sqlEditor.innerHTML = `
    <div><span class="kw">SELECT</span> u.id, u.name, o.total</div>
    <div><span class="kw">FROM</span> <span class="tbl">users</span> u</div>
    <div><span class="kw">join</span><span class="caret"></span></div>
    <div class="snippet-result ${p>.55?'show':''}" id="snippetResult">
      <div><span class="kw">JOIN</span> <span class="tbl">orders</span> o <span class="kw">ON</span> <span class="col">o.user_id</span> = <span class="col">u.id</span></div>
    </div>
  `;
  
  if(p>.16 && p<.95) snippetPopup.classList.add('show');
  
  widgetPane.innerHTML = `
    <div class="widget-title">Cú pháp hoàn chỉnh</div>
    <div class="schema-tree" style="margin-top:6px;">
      <div class="schema-node">users (Bảng gốc)</div>
      <div class="schema-leaf">├─ id (PK)</div>
      <div class="schema-node">orders (Liên kết)</div>
      <div class="schema-leaf">└─ user_id (FK) ➔ users.id</div>
    </div>
  `;
  
  gridHeader.innerHTML = '<th>id</th><th>name</th><th>email</th>';
  dataRows.innerHTML = '<tr><td colspan="3" class="muted">Chưa thực thi truy vấn</td></tr>';
  
  const editorRect = sqlEditor.getBoundingClientRect();
  const startM = { x: editorRect.left + 150, y: editorRect.top + 20 };
  const popRect = snippetPopup.getBoundingClientRect();
  const endM = { x: popRect.left + 120, y: popRect.top + 42 };
  const m = mix(startM, endM, p);
  const click = p > 0.42 && p < 0.53;
  
  setMouse(m, click);
}

function updateScene3(p) {
  winPath.innerHTML = '<strong>query.sql</strong> · workspace';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.orders.classList.add('active');
  
  sqlEditor.innerHTML = `
    <div><span class="kw">SELECT</span> id, user_id, total, status</div>
    <div><span class="kw">FROM</span> <span class="tbl">orders</span></div>
    <div><span class="kw">ORDER BY</span> created_at <span class="kw">DESC</span></div>
    <div><span class="kw">LIMIT</span> <span class="num">100</span>;</div>
  `;
  
  if(p>.20) rows.orders.classList.add('demo-hot');
  
  const rowRect = rows.orders.getBoundingClientRect();
  const chipRect = runChip.getBoundingClientRect();
  
  let m;
  let click = false;
  
  if (p < 0.4) {
    const popRect = snippetPopup.getBoundingClientRect();
    const startM = { x: popRect.left + 120, y: popRect.top + 42 };
    const endM = { x: rowRect.left + rowRect.width * 0.5, y: rowRect.top + rowRect.height * 0.5 };
    m = mix(startM, endM, p / 0.4);
    click = p > 0.26 && p < 0.38;
    if(click) rows.orders.classList.add('clicking');
  } else {
    const startM = { x: rowRect.left + rowRect.width * 0.5, y: rowRect.top + rowRect.height * 0.5 };
    const endM = { x: chipRect.left + chipRect.width * 0.5, y: chipRect.top + chipRect.height * 0.5 };
    m = mix(startM, endM, (p - 0.4) / 0.6);
    click = p > 0.52 && p < 0.65;
    if(click) runChip.classList.add('clicking');
  }
  
  if(p>.56) {
    dataPane.classList.add('loaded');
    renderOrdersGrid();
  } else {
    gridHeader.innerHTML = '<th>id</th><th>user_id</th><th>total</th><th>status</th>';
    dataRows.innerHTML = '<tr><td colspan="4" class="muted">Chưa nạp dữ liệu</td></tr>';
  }
  
  widgetPane.innerHTML = `
    <div class="widget-title">Hiệu năng truy vấn</div>
    <div class="query-stats" style="margin-top:6px;">
      <div class="stat-large">${p>.56?'1.2ms':'--'}</div>
      <div class="stat-label">Thời gian thực thi</div>
    </div>
    <div class="widget-details" style="margin-top:8px;">
      <div class="detail-row"><span>Số dòng:</span><span>${p>.56?'100':'0'}</span></div>
      <div class="detail-row"><span>Bộ nhớ:</span><span>${p>.56?'0.1 MB':'0'}</span></div>
    </div>
  `;
  
  setMouse(m, click);
}

function updateScene4(p) {
  winPath.innerHTML = '<strong>orders</strong> · relation quick view';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.orders.classList.add('active');
  
  sqlEditor.innerHTML = `
    <div><span class="kw">SELECT</span> id, user_id, total, status</div>
    <div><span class="kw">FROM</span> <span class="tbl">orders</span></div>
    <div><span class="kw">ORDER BY</span> created_at <span class="kw">DESC</span></div>
    <div><span class="kw">LIMIT</span> <span class="num">100</span>;</div>
  `;
  
  dataPane.classList.add('loaded');
  gridHeader.innerHTML = '<th>id</th><th>user_id</th><th>total</th><th>status</th>';
  dataRows.innerHTML = `
    <tr><td>90021</td><td><span class="fk-cell ${p>.18?'demo-hover':''}" id="fkCell">42 ↗</span></td><td>12,800</td><td><span style="color:var(--green)">paid</span></td></tr>
    <tr><td>90020</td><td><span class="fk-cell">87 ↗</span></td><td>8,400</td><td>pending</td></tr>
    <tr><td>90019</td><td><span class="fk-cell">18 ↗</span></td><td>3,200</td><td><span style="color:var(--green)">paid</span></td></tr>
    <tr><td>90018</td><td><span class="fk-cell">92 ↗</span></td><td>19,200</td><td>paid</td></tr>
  `;
  
  if(p>.26) {
    widgetPane.innerHTML = `
      <div class="widget-title">Chi tiết liên kết</div>
      <div class="hover-card">
        <div class="qtop"><span class="badge">users #42</span></div>
        <div class="kv" style="margin-top:6px;">
          <div class="detail-row"><span>Tên:</span><strong>Nguyễn Lâm</strong></div>
          <div class="detail-row"><span>Email:</span><span>lam@example.com</span></div>
          <div class="detail-row"><span>Trạng thái:</span><span class="text-green">Active</span></div>
          <div class="detail-row"><span>Ngày tạo:</span><span>2026-07-01</span></div>
        </div>
        <div class="path-line">orders.user_id ➔ users.id</div>
      </div>
    `;
  } else {
    widgetPane.innerHTML = `
      <div class="widget-title">Di chuột khám phá</div>
      <div class="muted" style="font-size:11px; margin-top:8px;">Di chuột lên giá trị ngoại khoá để xem quan hệ.</div>
    `;
  }
  
  const chipRect = runChip.getBoundingClientRect();
  const startM = { x: chipRect.left + chipRect.width * 0.5, y: chipRect.top + chipRect.height * 0.5 };
  
  const cell = document.getElementById('fkCell');
  const cellRect = cell.getBoundingClientRect();
  const endM = { x: cellRect.left + cellRect.width * 0.5, y: cellRect.top + cellRect.height * 0.5 };
  
  const m = mix(startM, endM, clamp(p/0.34));
  
  setMouse(m, false);
}

function updateScene5(p) {
  winPath.innerHTML = '<strong>virtual grid</strong> · 1M rows';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.orders.classList.add('active');
  
  sqlEditor.innerHTML = `
    <div><span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">massive_data</span>;</div>
  `;
  
  const loaded = p>.34;
  
  if (loaded) {
    dataPane.classList.add('loaded');
    gridScrollbar.style.display = 'block';
    renderVirtualRows(Math.floor(1+p*999960));
    gridThumb.style.top = (p*70)+'%';
  } else {
    gridHeader.innerHTML = '<th>#</th><th>id</th><th>user_id</th><th>total</th>';
    dataRows.innerHTML = '<tr><td colspan="4" class="muted">Chưa tải dữ liệu lớn</td></tr>';
  }
  
  widgetPane.innerHTML = `
    <div class="widget-title">Virtual Grid Metrics</div>
    <div class="grid-stats" style="margin-top:6px; display:flex; flex-direction:column; gap:4px;">
      <div class="detail-row"><span>Dòng render:</span><span>${loaded?'23':'0'}</span></div>
      <div class="detail-row"><span>Tổng số:</span><span>${loaded?'1,000,000':'0'}</span></div>
      <div class="detail-row"><span>Render:</span><span class="text-green">${loaded?'0.4ms':'--'}</span></div>
      <div class="detail-row"><span>RAM tốn:</span><span>${loaded?'12 KB':'0'}</span></div>
    </div>
    ${loaded?'<div class="fps-meter" style="margin-top:8px;">60 FPS (Mượt mà)</div>':''}
  `;
  
  const cell = document.getElementById('fkCell');
  const cellRect = cell.getBoundingClientRect();
  
  // Custom Load UI in data-pane if not loaded
  if(!loaded){
    gridHeader.innerHTML = '<th>#</th><th>id</th><th>user_id</th><th>total</th>';
    dataRows.innerHTML = `
      <tr>
        <td colspan="4">
          <div class="big-num-container">
            <div>
              <div class="big-num" style="font-size:28px;">1,000,000</div>
              <div class="tiny">rows · virtual viewport</div>
            </div>
            <button class="load1m" id="load1m">LOAD</button>
          </div>
        </td>
      </tr>
    `;
  }
  
  const loadBtn = document.getElementById('load1m');
  const loadRect = loadBtn ? loadBtn.getBoundingClientRect() : { left: 0, top: 0, width: 0, height: 0 };
  const endM = { x: loadRect.left + loadRect.width * 0.5, y: loadRect.top + loadRect.height * 0.5 };
  
  const m = mix({ x: cellRect.left + cellRect.width * 0.5, y: cellRect.top + cellRect.height * 0.5 }, endM, clamp(p/0.38));
  const click = p > 0.26 && p < 0.45;
  
  setMouse(m, click);
}

function renderOrdersGrid(){
  gridHeader.innerHTML = '<th>id</th><th>user_id</th><th>total</th><th>status</th><th>created_at</th>';
  let html='';
  const st=['paid','pending','paid','failed','paid','pending','paid','paid','pending'];
  for(let i=0;i<9;i++){
    const id=90031-i;
    html+=`<tr><td>${id}</td><td>${[42,87,18,92,64,21,75,36,9][i]} ↗</td><td>${(12800-i*740).toLocaleString()}</td><td>${st[i]}</td><td>2026-07-${String(8-i).padStart(2,'0')}</td></tr>`;
  }
  dataRows.innerHTML=html;
}

function renderVirtualRows(start){
  gridHeader.innerHTML = '<th>#</th><th>id</th><th>user_id</th><th>total</th><th>status</th>';
  let html='';
  for(let i=0;i<23;i++){
    const n=start+i;
    html+=`<tr><td>${n.toLocaleString()}</td><td>${800000+n}</td><td>${(n*7)%999}</td><td>${(1200+(n%200)*31).toLocaleString()}</td><td>${n%5===0?'pending':'paid'}</td></tr>`;
  }
  dataRows.innerHTML=html;
}

function update(){
 const max=document.documentElement.scrollHeight-innerHeight; const g=clamp(scrollY/max); const raw=clamp(g*SCENES,0,SCENES-.0001); const idx=Math.floor(raw); const local=raw-idx;
 bars.forEach((b,i)=>b.style.width=(i<idx?100:i>idx?0:local*100)+'%');
 
 copies.forEach((c,i)=>{
   const d=i-(idx+local);
   const op=clamp(1-Math.abs(d)*1.7);
   c.style.opacity=op;
   c.style.filter=`blur(${Math.abs(d)*10}px)`;
   c.style.transform=`translateY(-42%) translateY(${d*22}px)`;
   c.classList.toggle('active',op>.55)
 });
 
 clearStates();
 if(idx===0) updateScene0(local);
 else if(idx===1) updateScene1(local);
 else if(idx===2) updateScene2(local);
 else if(idx===3) updateScene3(local);
 else if(idx===4) updateScene4(local);
 else if(idx===5) updateScene5(local);
}

let ticking=false;
addEventListener('scroll',()=>{
  if(!ticking){
    requestAnimationFrame(()=>{update();ticking=false});
    ticking=true;
  }
}, {passive:true});
addEventListener('resize',update);

// Initial call after elements layout
setTimeout(update, 100);
</script>
</body>
</html>
