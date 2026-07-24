<?php
require_once __DIR__ . '/wp-load.php';
echo "Published posts: " . wp_count_posts()->publish . "\n";
echo "Trashed posts: " . wp_count_posts()->trash . "\n";
