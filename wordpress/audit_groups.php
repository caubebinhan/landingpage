<?php
require_once('wp-load.php');

$posts = get_posts(['post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish', 'lang' => '']);
$groups = [];
foreach ($posts as $post) {
    $lang = pll_get_post_language($post->ID);
    $translations = pll_get_post_translations($post->ID);
    $group_key = json_encode($translations);
    if (!isset($groups[$group_key])) {
        $groups[$group_key] = $translations;
    }
}

foreach ($groups as $group) {
    print_r($group);
}
