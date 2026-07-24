<?php
require 'wp-load.php';
$pages = [4, 5];
$res = [];
foreach($pages as $p) {
    $lang = pll_get_post_language($p);
    $translations = pll_get_post_translations($p);
    $res[$p] = ['lang' => $lang, 'translations' => $translations];
}
echo json_encode($res);
