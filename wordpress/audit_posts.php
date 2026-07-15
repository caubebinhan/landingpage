<?php
require_once('wp-load.php');

$posts = get_posts([
    'post_type' => 'post',
    'numberposts' => -1,
    'post_status' => 'publish',
    'lang' => '' // Get all languages
]);

echo "ID\tLang\tWords\tTitle\n";
foreach ($posts as $post) {
    $lang = pll_get_post_language($post->ID);
    $word_count = str_word_count(strip_tags($post->post_content));
    if ($lang == 'ja') {
        $word_count = mb_strlen(strip_tags($post->post_content));
    }
    echo "{$post->ID}\t{$lang}\t{$word_count}\t{$post->post_title}\n";
}
