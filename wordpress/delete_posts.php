<?php
require_once('wp-load.php');
$args = array(
    'post_type' => 'post',
    'posts_per_page' => -1,
    'post_status' => 'any'
);
$posts = get_posts($args);
$count = 0;
foreach ($posts as $post) {
    if ($post->post_title !== 'Hello world!') { // Keep default post if any, or just delete all
        wp_delete_post($post->ID, true);
        $count++;
    }
}
echo "Deleted $count posts.\n";
