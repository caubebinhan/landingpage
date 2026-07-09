<?php
$file = '/var/www/html/wp-content/themes/hoasen-theme/front-page.php';
$content = file_get_contents($file);

// Let's replace the floating animation CSS with static CSS
$old_pulse_css = <<<CSS
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

$new_static_css = <<<CSS
.bg-lotus {
  position:fixed; top:50%; left:65%; transform:translate(-50%, -50%);
  width:140vh; height:140vh; z-index:0; opacity:0.035; pointer-events:none;
}
CSS;

$content = str_replace($old_pulse_css, $new_static_css, $content);

file_put_contents($file, $content);
echo "Background animation removed. Watermark is now static.\n";
