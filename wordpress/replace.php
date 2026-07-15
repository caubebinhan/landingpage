<?php
$file = 'hoasen-theme/index.php';
$content = file_get_contents($file);

$search = <<<HTML
<?php if(function_exists('pll_the_languages')): ?>
<link rel="alternate" hreflang="vi" href="<?php echo esc_url(pll_home_url('vi')); ?>"/>
<link rel="alternate" hreflang="en" href="<?php echo esc_url(pll_home_url('en')); ?>"/>
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(home_url('/')); ?>"/>
<?php endif; ?>
HTML;

$replace = <<<HTML
<?php if(function_exists('pll_the_languages')): 
  \$langs = pll_the_languages(array('raw'=>1, 'hide_if_empty'=>0));
  if (is_array(\$langs)) {
    foreach(\$langs as \$l) {
      echo '<link rel="alternate" hreflang="'.esc_attr(\$l['slug']).'" href="'.esc_url(\$l['url']).'"/>'."\\n";
    }
  }
?>
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(home_url('/')); ?>"/>
<?php endif; ?>
HTML;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Replaced successfully\n";
