<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// Find JS block start and end
$old_js_start = strpos($content, '<script>');
$old_js_end = strpos($content, '</script>', $old_js_start);
if ($old_js_start !== false && $old_js_end !== false) {
    $js_content = substr($content, $old_js_start, $old_js_end - $old_js_start + 9);
}

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

// Menu toggle
menuBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  menuDropdown.classList.toggle('show');
});
document.addEventListener('click', () => {
  menuDropdown.classList.remove('show');
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

// Global cached widgets labels
const connLbl = "Active Connections";
const typeLbl = "Table Type";
const joinLbl = "JOIN Analysis";
const timeLbl = "Execution Time";
const ramLbl = "RAM Allocation";
const relLbl = "Relation Info";
const viewLbl = "Viewport Size";
const sizeLbl = "Total Data Size";

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
    <div class="widget-header">WIDGETS</div>
    <div class="widget-card">
      <div class="widget-card-title">${connLbl}</div>
      <div class="widget-card-val">1</div>
      <div class="widget-card-desc">PostgreSQL (10.0.0.4)</div>
    </div>
    <div class="widget-card">
      <div class="widget-card-title">${typeLbl}</div>
      <div class="widget-card-val">BASE TABLE</div>
      <div class="widget-card-desc">users (12,000 rows)</div>
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
    <div class="widget-header">WIDGETS</div>
    <div class="widget-card">
      <div class="widget-card-title">${joinLbl}</div>
      <div class="widget-card-val">1 FK Relation</div>
      <div class="widget-card-desc">orders.user_id ➔ users.id</div>
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
    <div class="widget-header">WIDGETS</div>
    <div class="widget-card">
      <div class="widget-card-title">${timeLbl}</div>
      <div class="widget-card-val">${p>.56?'1.2ms':'--'}</div>
      <div class="widget-card-desc">Query speed optimization</div>
    </div>
    <div class="widget-card">
      <div class="widget-card-title">${ramLbl}</div>
      <div class="widget-card-val">${p>.56?'0.1 MB':'0'}</div>
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
      <div class="widget-header">WIDGETS</div>
      <div class="widget-card">
        <div class="widget-card-title">${relLbl}</div>
        <div class="widget-card-val">users #42</div>
        <div class="widget-card-desc">Nguyễn Lâm (lam@example.com)</div>
      </div>
    `;
  } else {
    widgetPane.innerHTML = `
      <div class="widget-header">WIDGETS</div>
      <div class="widget-card">
        <div class="widget-card-title">Relation Info</div>
        <div class="widget-card-val">--</div>
        <div class="widget-card-desc">Hover foreign key cell to preview</div>
      </div>
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
    <div class="widget-header">WIDGETS</div>
    <div class="widget-card">
      <div class="widget-card-title">${sizeLbl}</div>
      <div class="widget-card-val">${loaded?'1,000,000':'0'}</div>
      <div class="widget-card-desc">Virtual grid container</div>
    </div>
    <div class="widget-card">
      <div class="widget-card-title">${viewLbl}</div>
      <div class="widget-card-val">${loaded?'23 Rows':'--'}</div>
      <div class="widget-card-desc">0.4ms overhead (60 FPS)</div>
    </div>
  `;
  
  // Custom Load UI in data-pane if not loaded
  if(!loaded){
    gridHeader.innerHTML = '<th>#</th><th>id</th><th>user_id</th><th>total</th>';
    dataRows.innerHTML = `
      <tr>
        <td colspan="4">
          <div class="massive-load-center">
            <div style="font-size:42px; font-weight:800; font-family:'Cormorant Garamond',serif; line-height:1; color:var(--blue);">1,000,000</div>
            <div style="font-size:11px; font-weight:700; font-family:'Inter',sans-serif; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); margin-bottom:20px;">rows · virtual viewport</div>
            <button class="massive-load-btn ${p>0.26?'hover':''}" style="font-size:14px; padding:10px 30px;" id="load1m">LOAD</button>
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
   c.style.transform=window.innerWidth > 980 ? `translateY(-42%) translateY(${d*22}px)` : `translateY(-50%) translateY(${d*12}px)`;
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
echo "JavaScript coordinates and Widgets rendering updated successfully.\n";
