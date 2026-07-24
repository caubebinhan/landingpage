<?php
require 'wp-load.php';
$pll = get_option('polylang');
echo json_encode($pll);
