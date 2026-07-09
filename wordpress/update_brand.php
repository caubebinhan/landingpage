<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// 1. Add Favicon Link inside <head>
$favicon_tag = <<<HTML
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'><defs><linearGradient id='lg' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='%23fca5a5'/><stop offset='50%' stop-color='%23f43f5e'/><stop offset='100%' stop-color='%23be123c'/></linearGradient><mask id='lm'><path d='M50 15 C 62 30, 62 70, 50 90 C 38 70, 38 30, 50 15 Z' fill='white'/><path d='M40 30 C 25 30, 15 50, 25 75 C 32 82, 45 88, 50 90 C 32 70, 32 45, 40 30 Z' fill='white'/><path d='M60 30 C 75 30, 85 50, 75 75 C 68 82, 55 88, 50 90 C 68 70, 68 45, 60 30 Z' fill='white'/></mask></defs><rect width='100' height='100' fill='url(%23lg)' mask='url(%23lm)'/><g stroke='%23ffffff' stroke-width='3' mask='url(%23lm)'><line x1='0' y1='36' x2='100' y2='36'/><line x1='0' y1='52' x2='100' y2='52'/><line x1='0' y1='68' x2='100' y2='68'/><path d='M38 10 Q45 50 48 90' fill='none'/><path d='M50 0 V100' fill='none'/><path d='M62 10 Q55 50 52 90' fill='none'/></g></svg>" />
HTML;

if (strpos($content, '<head>') !== false) {
    $content = str_replace('<head>', "<head>\n" . $favicon_tag, $content);
}

// 2. Overwrite the Brand Logo in the top-left of the page
// Old: <div class="brand"><div class="logo"></div>HoaSen Table</div>
// New: <div class="brand">[SVG LOGO] HoaSen Table</div>
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

// 3. Overwrite the background SVG with the detailed Lotus logo
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

// Clean up old bg-lotus if present
$content = preg_replace('/<svg class="bg-lotus".*?<\/svg>/s', $detailed_lotus_bg, $content);

// 4. Update CSS background effect
// Let's add slow floating animation to the logo background
$pulse_css = <<<CSS
.bg-lotus {
  position:fixed; top:50%; left:65%; transform:translate(-50%, -50%);
  width:140vh; height:140vh; z-index:0; opacity:0.045; pointer-events:none;
  animation: bgFloat 24s ease-in-out infinite alternate;
}
@keyframes bgFloat {
  0% { transform: translate(-50%, -50%) rotate(0deg) scale(1); }
  50% { transform: translate(-48%, -52%) rotate(3deg) scale(1.03); }
  100% { transform: translate(-52%, -48%) rotate(-2deg) scale(0.98); }
}
CSS;

$content = str_replace('.bg-lotus {', '/* .bg-lotus placeholder */', $content);
$content = str_replace('/* .bg-lotus placeholder */', $pulse_css, $content);

file_put_contents($file, $content);
echo "Brand, Logo and Background updated.\n";
