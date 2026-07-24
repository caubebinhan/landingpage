<?php
require 'wp-load.php';
$res = [
    'show_on_front' => get_option('show_on_front'),
    'page_on_front' => get_option('page_on_front'),
    'page_for_posts' => get_option('page_for_posts'),
];
echo json_encode($res);
