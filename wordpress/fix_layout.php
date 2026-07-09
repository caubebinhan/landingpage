<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// 1. Let's add the HTML markup of massive-load-center into grid-viewport in front-page.php
$old_viewport = <<<HTML
            <div class="grid-viewport">
              <div class="sweep" id="gridSweep"></div>
              <table class="table-grid" id="dataGrid">
HTML;

$new_viewport = <<<HTML
            <div class="grid-viewport">
              <div class="sweep" id="gridSweep"></div>
              <div class="massive-load-center" id="massiveLoadCenter" style="display:none;">
                <div class="massive-load-num" style="font-size:42px; font-weight:800; font-family:'Cormorant Garamond',serif; line-height:1; color:var(--blue);">1,000,000</div>
                <div class="massive-load-tag" style="font-size:11px; font-weight:700; font-family:'Inter',sans-serif; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); margin-bottom:20px;">rows · virtual viewport</div>
                <button class="massive-load-btn" style="font-size:14px; padding:10px 30px;" id="load1m">LOAD</button>
              </div>
              <table class="table-grid" id="dataGrid">
HTML;

$content = str_replace($old_viewport, $new_viewport, $content);

// 2. Adjust CSS for massive-load-center positioning
$css_adjustment = <<<CSS
.massive-load-center {
  position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
  background:linear-gradient(180deg, rgba(255,255,255,0.85) 0%, #fff 100%); z-index:10; backdrop-filter:blur(4px);
}
CSS;

// Ensure it is styled properly in the CSS block
if (strpos($content, '.massive-load-center {') === false) {
    $content = str_replace('</style>', $css_adjustment . "\n</style>", $content);
}

file_put_contents($file, $content);
echo "HTML structure adjusted.\n";
