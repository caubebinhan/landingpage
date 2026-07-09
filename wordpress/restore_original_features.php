<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/index.php';
$content = file_get_contents($file);

// 1. Add Favicon Link inside <head>
$favicon_tag = <<<HTML
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'><defs><linearGradient id='lg' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23fca5a5'/><stop offset='50%' stop-color='%23f43f5e'/><stop offset='100%' stop-color='%23be123c'/></linearGradient><mask id='lm'><path d='M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z' fill='white'/><path d='M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z' fill='white'/><path d='M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z' fill='white'/></mask></defs><rect width='100' height='100' fill='url(%23lg)' mask='url(%23lm)'/><g stroke='%23ffffff' stroke-width='3' mask='url(%23lm)'><line x1='0' y1='36' x2='100' y2='36'/><line x1='0' y1='52' x2='100' y2='52'/><line x1='0' y1='68' x2='100' y2='68'/><path d='M38 10 Q45 50 48 90' fill='none'/><path d='M50 0 V100' fill='none'/><path d='M62 10 Q55 50 52 90' fill='none'/></g></svg>" />
HTML;

if (strpos($content, '<head>') !== false) {
    $content = str_replace('<head>', "<head>\n" . $favicon_tag, $content);
}

// 2. Inject detailed static background Lotus SVG into main wrapper
$detailed_lotus_bg = <<<HTML
  <svg class="bg-lotus" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="bgLotusGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#fca5a5" />
        <stop offset="50%" stop-color="#f43f5e" />
        <stop offset="100%" stop-color="#be123c" />
      </linearGradient>
      <mask id="bgLotusMask">
        <path d="M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z" fill="white" />
        <path d="M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z" fill="white" />
        <path d="M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z" fill="white" />
        <path d="M22 50 C 5 50, 0 65, 25 80 C 35 85, 45 88, 50 90 C 25 78, 15 65, 22 50 Z" fill="white" />
        <path d="M78 50 C 95 50, 100 65, 75 80 C 65 85, 55 88, 50 90 C 75 78, 85 65, 78 50 Z" fill="white" />
      </mask>
    </defs>
    <rect width="100" height="100" fill="url(#bgLotusGrad)" mask="url(#bgLotusMask)" />
    <g stroke="#f0f0f0" stroke-width="2.5" mask="url(#bgLotusMask)">
      <line x1="0" y1="36" x2="100" y2="36" />
      <line x1="0" y1="46" x2="100" y2="46" />
      <line x1="0" y1="56" x2="100" y2="56" />
      <line x1="0" y1="66" x2="100" y2="66" />
      <line x1="0" y1="76" x2="100" y2="76" />
      <path d="M38 10 Q45 50 48 90" fill="none" />
      <path d="M50 0 V100" fill="none" />
      <path d="M62 10 Q55 50 52 90" fill="none" />
      <line x1="28" y1="20" x2="28" y2="90" />
      <line x1="72" y1="20" x2="72" y2="90" />
    </g>
  </svg>
HTML;

$content = str_replace('<div class="main-wrapper">', '<div class="main-wrapper">' . "\n" . $detailed_lotus_bg, $content);

// 3. CSS for the static bg-lotus
$bg_css = <<<CSS
.bg-lotus {
  position:fixed; top:50%; left:65%; transform:translate(-50%, -50%);
  width:140vh; height:140vh; z-index:0; opacity:0.035; pointer-events:none;
}
CSS;
$content = str_replace('/* Scene transitions */', $bg_css . "\n/* Scene transitions */", $content);

// 4. Update top-left brand logo with the SVG
$logo_svg = <<<HTML
<div class="brand">
  <svg class="site-logo-icon" width="32" height="32" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 4px 10px rgba(185,28,28,0.25))">
    <defs>
      <linearGradient id="logoGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#fca5a5" />
        <stop offset="50%" stop-color="#f43f5e" />
        <stop offset="100%" stop-color="#be123c" />
      </linearGradient>
      <mask id="logoMask">
        <path d="M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z" fill="white" />
        <path d="M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z" fill="white" />
        <path d="M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z" fill="white" />
        <path d="M22 50 C 5 50, 0 65, 25 80 C 35 85, 45 88, 50 90 C 25 78, 15 65, 22 50 Z" fill="white" />
        <path d="M78 50 C 95 50, 100 65, 75 80 C 65 85, 55 88, 50 90 C 75 78, 85 65, 78 50 Z" fill="white" />
      </mask>
    </defs>
    <rect width="100" height="100" fill="url(#logoGrad)" mask="url(#logoMask)" />
    <g stroke="#ffffff" stroke-width="2.5" mask="url(#logoMask)">
      <line x1="0" y1="36" x2="100" y2="36" />
      <line x1="0" y1="46" x2="100" y2="46" />
      <line x1="0" y1="56" x2="100" y2="56" />
      <line x1="0" y1="66" x2="100" y2="66" />
      <line x1="0" y1="76" x2="100" y2="76" />
      <path d="M38 10 Q45 50 48 90" fill="none" />
      <path d="M50 0 V100" fill="none" />
      <path d="M62 10 Q55 50 52 90" fill="none" />
      <line x1="28" y1="20" x2="28" y2="90" />
      <line x1="72" y1="20" x2="72" y2="90" />
    </g>
  </svg>
  <span style="font-family:'Cormorant Garamond',serif; font-size:22px; font-weight:800; color:var(--text); letter-spacing:-0.02em;">HoaSen Table</span>
</div>
HTML;

$content = preg_replace('/<div class="brand">.*?<\/div>/s', $logo_svg, $content);

// 5. Replace Javascript script tag with optimized proportional coordinates to fix reverse scroll
// We find <script> tag and replace it
$old_js_start = strpos($content, '<script>');
$old_js_end = strpos($content, '</script>', $old_js_start);
if ($old_js_start !== false && $old_js_end !== false) {
    $js_content = substr($content, $old_js_start, $old_js_end - $old_js_start + 9);
}

// Write the Javascript content matching the original style but adding getPoint()
$new_js = <<<'JS'
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

// 100% Robust Proportional Mouse Targets relative to .window
function getPoint(type) {
  const w = document.querySelector('.window').getBoundingClientRect();
  switch(type) {
    case 'center':
      return { x: w.left + w.width * 0.5, y: w.top + w.height * 0.5 };
    case 'cardAipbx':
      return { x: w.left + w.width * 0.72, y: w.top + w.height * 0.44 };
    case 'sqlEditor':
      return { x: w.left + w.width * 0.38, y: w.top + w.height * 0.22 };
    case 'snippetPopup':
      return { x: w.left + w.width * 0.42, y: w.top + w.height * 0.36 };
    case 'rowOrders':
      return { x: w.left + w.width * 0.11, y: w.top + w.height * 0.22 };
    case 'runChip':
      return { x: w.left + w.width * 0.70, y: w.top + w.height * 0.16 };
    case 'fkCell':
      return { x: w.left + w.width * 0.32, y: w.top + w.height * 0.58 };
    case 'load1m':
      return { x: w.left + w.width * 0.46, y: w.top + w.height * 0.68 };
  }
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
  
  const m = mix(getPoint('center'), getPoint('cardAipbx'), p);
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
  
  widgetPane.innerHTML = `
    <div class="widget-title"><?php esc_html_e('Connection', 'hoasen-theme'); ?></div>
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
  
  const m = mix(getPoint('cardAipbx'), getPoint('sqlEditor'), p);
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
    <div class="widget-title"><?php esc_html_e('Complete Syntax', 'hoasen-theme'); ?></div>
    <div class="schema-tree" style="margin-top:6px;">
      <div class="schema-node"><?php esc_html_e('users (Root Table)', 'hoasen-theme'); ?></div>
      <div class="schema-leaf">├─ id (PK)</div>
      <div class="schema-node"><?php esc_html_e('orders (Relation)', 'hoasen-theme'); ?></div>
      <div class="schema-leaf">└─ user_id (FK) ➔ users.id</div>
    </div>
  `;
  
  gridHeader.innerHTML = '<th>id</th><th>name</th><th>email</th>';
  dataRows.innerHTML = '<tr><td colspan="3" class="muted">Chưa thực thi truy vấn</td></tr>';
  
  const m = mix(getPoint('sqlEditor'), getPoint('snippetPopup'), p);
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
  
  let m;
  let click = false;
  
  if (p < 0.4) {
    m = mix(getPoint('snippetPopup'), getPoint('rowOrders'), p / 0.4);
    click = p > 0.26 && p < 0.38;
    if(click) rows.orders.classList.add('clicking');
  } else {
    m = mix(getPoint('rowOrders'), getPoint('runChip'), (p - 0.4) / 0.6);
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
    <div class="widget-title"><?php esc_html_e('Query Performance', 'hoasen-theme'); ?></div>
    <div class="query-stats" style="margin-top:6px;">
      <div class="stat-large">${p>.56?'1.2ms':'--'}</div>
      <div class="stat-label"><?php esc_html_e('Execution Time', 'hoasen-theme'); ?></div>
    </div>
    <div class="widget-details" style="margin-top:8px;">
      <div class="detail-row"><span><?php esc_html_e('Row Count:', 'hoasen-theme'); ?></span><span>${p>.56?'100':'0'}</span></div>
      <div class="detail-row"><span><?php esc_html_e('Memory:', 'hoasen-theme'); ?></span><span>${p>.56?'0.1 MB':'0'}</span></div>
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
      <div class="widget-title"><?php esc_html_e('Relation Details', 'hoasen-theme'); ?></div>
      <div class="hover-card">
        <div class="qtop"><span class="badge">users #42</span></div>
        <div class="kv" style="margin-top:6px;">
          <div class="detail-row"><span><?php esc_html_e('Name:', 'hoasen-theme'); ?></span><strong>Nguyễn Lâm</strong></div>
          <div class="detail-row"><span>Email:</span><span>lam@example.com</span></div>
          <div class="detail-row"><span><?php esc_html_e('Status:', 'hoasen-theme'); ?></span><span class="text-green">Active</span></div>
          <div class="detail-row"><span><?php esc_html_e('Created Date:', 'hoasen-theme'); ?></span><span>2026-07-01</span></div>
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
  
  const m = mix(getPoint('runChip'), getPoint('fkCell'), clamp(p/0.34));
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
      <div class="detail-row"><span><?php esc_html_e('Rendered Rows:', 'hoasen-theme'); ?></span><span>${loaded?'23':'0'}</span></div>
      <div class="detail-row"><span><?php esc_html_e('Total Rows:', 'hoasen-theme'); ?></span><span>${loaded?'1,000,000':'0'}</span></div>
      <div class="detail-row"><span>Render:</span><span class="text-green">${loaded?'0.4ms':'--'}</span></div>
      <div class="detail-row"><span><?php esc_html_e('RAM Usage:', 'hoasen-theme'); ?></span><span>${loaded?'12 KB':'0'}</span></div>
    </div>
    ${loaded?'<div class="fps-meter" style="margin-top:8px;"><?php esc_html_e('60 FPS (Smooth)', 'hoasen-theme'); ?></div>':''}
  `;
  
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
            <button class="load1m" id="load1m"><?php esc_html_e('LOAD', 'hoasen-theme'); ?></button>
          </div>
        </td>
      </tr>
    `;
  }
  
  const m = mix(getPoint('fkCell'), getPoint('load1m'), clamp(p/0.38));
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
 const max=document.documentElement.scrollHeight-innerHeight; 
 const g=clamp(scrollY/max); 
 const raw=clamp(g*SCENES,0,SCENES-.0001); 
 const idx=Math.floor(raw); 
 const local=raw-idx;
 
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
JS;

if ($js_content) {
    $content = str_replace($js_content, $new_js, $content);
}

file_put_contents($file, $content);
echo "Monolithic index.php recovered and features successfully injected.\n";
JS;

file_put_contents('/var/www/html/wp-content/themes/hoasen-theme/restore_original_features.php', $new_js);
echo "PHP script generated.\n";
