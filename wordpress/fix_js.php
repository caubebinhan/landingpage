<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// Replace updateScene5 function
$old_scene5 = <<<'JS'
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
JS;

$new_scene5 = <<<'JS'
function updateScene5(p) {
  winPath.innerHTML = '<strong>virtual grid</strong> · 1M rows';
  connectionBoard.classList.remove('show');
  softwareLayout.classList.add('show');
  rows.orders.classList.add('active');
  
  sqlEditor.innerHTML = `
    <div><span class="kw">SELECT</span> * <span class="kw">FROM</span> <span class="tbl">massive_data</span>;</div>
  `;
  
  const loaded = p>.34;
  
  // Toggle overlay outside table
  const massiveLoadCenter = document.getElementById('massiveLoadCenter');
  if (massiveLoadCenter) {
    massiveLoadCenter.style.display = loaded ? 'none' : 'flex';
    // Handle button hover class dynamically
    const loadBtn = massiveLoadCenter.querySelector('.massive-load-btn');
    if (loadBtn) {
      if (p > 0.26) loadBtn.classList.add('hover');
      else loadBtn.classList.remove('hover');
    }
  }
  
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
  
  const m = mix(getPoint('fkCell'), getPoint('load1m'), clamp(p/0.38));
  const click = p > 0.26 && p < 0.45;
  setMouse(m, click);
}
JS;

$content = str_replace($old_scene5, $new_scene5, $content);

file_put_contents($file, $content);
echo "JavaScript fix completed successfully.\n";
JS;

file_put_contents('/var/www/html/wp-content/themes/hoasen-theme/fix_js.php', $content);
echo "Write completed.\n";
