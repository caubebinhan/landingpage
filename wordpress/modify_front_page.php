<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// 1. Inject Lotus Background
$lotus_bg = <<<HTML
  <svg class="bg-lotus" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="lotusGrad" x1="50" y1="10" x2="50" y2="90" gradientUnits="userSpaceOnUse">
        <stop stop-color="#b91c1c"/>
        <stop offset="1" stop-color="#9d174d"/>
      </linearGradient>
      <mask id="gridMask">
        <rect width="100" height="100" fill="white" />
        <path d="M0 30H100 M0 50H100 M0 70H100 M30 0V100 M50 0V100 M70 0V100" stroke="black" stroke-width="2" />
      </mask>
    </defs>
    <!-- Center Petal -->
    <path d="M50 15 C65 40 65 70 50 90 C35 70 35 40 50 15 Z" fill="url(#lotusGrad)" mask="url(#gridMask)" />
    <!-- Left Petal -->
    <path d="M40 30 C20 30 10 50 20 75 C30 85 45 85 50 90 C30 65 30 40 40 30 Z" fill="url(#lotusGrad)" mask="url(#gridMask)" />
    <!-- Right Petal -->
    <path d="M60 30 C80 30 90 50 80 75 C70 85 55 85 50 90 C70 65 70 40 60 30 Z" fill="url(#lotusGrad)" mask="url(#gridMask)" />
  </svg>
HTML;

$content = str_replace('<div class="main-wrapper">', '<div class="main-wrapper">' . "\n" . $lotus_bg, $content);

// 2. Add CSS for new UI
$new_css = <<<CSS
.bg-lotus {
  position:fixed; top:50%; left:65%; transform:translate(-50%, -50%);
  width:140vh; height:140vh; z-index:0; opacity:0.04; pointer-events:none;
}
.software-layout { display:flex; height:100%; width:100%; background:var(--panel); border-radius:0 0 10px 10px; overflow:hidden; }
.sidebar { width:240px; background:var(--panel2); border-right:1px solid var(--line); display:flex; flex-direction:column; }
.side-title { padding:12px 16px; font-size:11px; text-transform:uppercase; font-weight:800; letter-spacing:0.08em; color:var(--muted); border-bottom:1px solid var(--line); }
.tree-item { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; font-size:13px; font-family:"Inter",sans-serif; color:#333; cursor:pointer; }
.tree-item:hover { background:rgba(0,0,0,0.04); }
.tree-item.active { background:#fff; border-left:3px solid var(--blue); color:var(--blue); font-weight:700; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.tree-item span.count { font-size:11px; color:var(--muted); font-family:"JetBrains Mono",monospace; background:rgba(0,0,0,0.05); padding:2px 6px; border-radius:4px; }

.main-area { flex:1; display:flex; flex-direction:column; min-width:0; }
.main-tabs { display:flex; border-bottom:1px solid var(--line); background:#fafafa; }
.main-tab { padding:10px 20px; font-size:12px; font-weight:700; color:var(--muted); border-right:1px solid var(--line); border-bottom:2px solid transparent; cursor:pointer; }
.main-tab.active { background:#fff; color:var(--blue); border-bottom-color:var(--blue); }

.sql-pane { padding:20px; border-bottom:1px solid var(--line); position:relative; flex:shrink:0; }
.data-pane { flex:1; display:flex; flex-direction:column; position:relative; overflow:hidden; background:#fff; }

.widget-pane { width:260px; background:var(--panel2); border-left:1px solid var(--line); display:flex; flex-direction:column; }
.widget-section { border-bottom:1px solid var(--line); padding:16px; }
.widget-title { font-size:11px; text-transform:uppercase; font-weight:800; letter-spacing:0.05em; color:var(--muted); margin-bottom:12px; display:flex; justify-content:space-between; }
.plugin-item { display:flex; align-items:center; gap:8px; padding:8px; background:#fff; border:1px solid var(--line); border-radius:6px; margin-bottom:8px; font-size:12px; }
.plugin-icon { width:24px; height:24px; background:var(--blue); border-radius:4px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:10px; font-weight:bold; }
.plugin-item.disabled .plugin-icon { background:var(--muted); }

/* Load 1M Scene UI */
.massive-load-center {
  position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
  background:linear-gradient(180deg, rgba(255,255,255,0.8) 0%, #fff 100%); z-index:10; backdrop-filter:blur(4px);
}
.massive-load-btn {
  background:linear-gradient(135deg, var(--red), var(--pink)); color:#fff;
  border:none; border-radius:99px; padding:16px 48px; font-size:18px; font-weight:900;
  letter-spacing:0.1em; cursor:pointer; box-shadow:0 12px 30px rgba(185,28,28,0.3);
  font-family:"Inter",sans-serif; transition:transform 0.2s, box-shadow 0.2s;
}
.massive-load-btn.hover { transform:scale(1.05); box-shadow:0 16px 40px rgba(185,28,28,0.4); }
CSS;

$content = str_replace('/* Scene transitions */', $new_css . "\n/* Scene transitions */", $content);

// 3. Rewrite HTML structure inside software-layout
$old_sidebar = <<<HTML
        <!-- Sidebar (Left Column) -->
        <aside class="sidebar">
          <div class="side-title">Tables</div>
          <div class="table-row" id="row-users">users <span>12k</span></div>
          <div class="table-row" id="row-orders">orders <span>1M</span></div>
          <div class="table-row" id="row-payments">payments <span>88k</span></div>
          <div class="table-row" id="row-clinics">clinics <span>42</span></div>
          <div class="table-row" id="row-settings">settings <span>json</span></div>
        </aside>
HTML;

$new_sidebar = <<<HTML
        <aside class="sidebar">
          <div class="side-title">Explorer</div>
          <div class="tree-item" id="row-users">users <span class="count">12k</span></div>
          <div class="tree-item" id="row-orders">orders <span class="count">1M</span></div>
          <div class="tree-item" id="row-payments">payments <span class="count">88k</span></div>
          <div class="tree-item" id="row-clinics">clinics <span class="count">42</span></div>
          <div class="tree-item" id="row-settings">settings <span class="count">json</span></div>
        </aside>
HTML;
$content = str_replace($old_sidebar, $new_sidebar, $content);

// Add main-tabs to main-area
$content = str_replace('<main class="main-area">', '<main class="main-area">'."\n".'<div class="main-tabs"><div class="main-tab active">Query 1</div><div class="main-tab">Schema Info</div></div>', $content);

// 4. Update widget pane generation in JS
// We must replace the content generation for widgetPane
$scene4_widget = <<<JS
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
JS;

$scene4_widget_new = <<<JS
      <div class="widget-section">
        <div class="widget-title"><?php esc_html_e('Relation Details', 'hoasen-theme'); ?></div>
        <div class="hover-card" style="box-shadow:none; border:1px solid var(--line);">
          <div class="qtop"><span class="badge">users #42</span></div>
          <div class="kv" style="margin-top:6px;">
            <div class="detail-row"><span><?php esc_html_e('Name:', 'hoasen-theme'); ?></span><strong>Nguyễn Lâm</strong></div>
            <div class="detail-row"><span>Email:</span><span>lam@example.com</span></div>
            <div class="detail-row"><span><?php esc_html_e('Status:', 'hoasen-theme'); ?></span><span class="text-green">Active</span></div>
          </div>
          <div class="path-line" style="margin-top:12px;">orders.user_id ➔ users.id</div>
        </div>
      </div>
      <div class="widget-section" style="flex:1;">
        <div class="widget-title">Plugin Manager <span class="badge" style="background:var(--blue);color:#fff;">PRO</span></div>
        <div class="plugin-item">
          <div class="plugin-icon">AI</div>
          <div style="flex:1"><strong>Data Gen</strong><div style="font-size:10px;color:var(--muted)">Active</div></div>
          <input type="checkbox" checked disabled>
        </div>
        <div class="plugin-item disabled">
          <div class="plugin-icon">PG</div>
          <div style="flex:1"><strong>PgStat</strong><div style="font-size:10px;color:var(--muted)">Inactive</div></div>
          <input type="checkbox" disabled>
        </div>
      </div>
JS;
$content = str_replace($scene4_widget, $scene4_widget_new, $content);

// For scene 5 Load UI
$scene5_load = <<<JS
          <div class="big-num-container">
            <div>
              <div class="big-num" style="font-size:28px;">1,000,000</div>
              <div class="tiny">rows · virtual viewport</div>
            </div>
            <button class="load1m" id="load1m">LOAD</button>
          </div>
JS;
$scene5_load_new = <<<JS
          <div class="massive-load-center">
            <div style="font-size:72px; font-weight:800; font-family:'Cormorant Garamond',serif; line-height:1; color:var(--blue);">1,000,000</div>
            <div style="font-size:14px; font-weight:700; font-family:'Inter',sans-serif; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); margin-bottom:32px;"><?php esc_html_e('rows · virtual viewport', 'hoasen-theme'); ?></div>
            <button class="massive-load-btn \${p>0.26?'hover':''}" id="load1m"><?php esc_html_e('LOAD', 'hoasen-theme'); ?></button>
          </div>
JS;
$content = str_replace($scene5_load, $scene5_load_new, $content);

file_put_contents($file, $content);
echo "Modification complete.\n";
