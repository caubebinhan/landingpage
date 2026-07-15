<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$image_path = ABSPATH . 'relational_model_codd.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'Relational Model Codd',
    'post_content'   => '',
    'post_status'    => 'inherit'
];
$attach_id = wp_insert_attachment($attachment, $destination);
$attach_data = wp_generate_attachment_metadata($attach_id, $destination);
wp_update_attachment_metadata($attach_id, $attach_data);

$titles = [
    'The Fall of the Network Model and the Genius of E.F. Codd',
    'Sự Sụp Đổ Của Network Model Và Ánh Sáng Của Relational Model (E.F. Codd)',
    '物理ポインタの迷宮からの脱出：エドガー・F・コッドとリレーショナルモデルの誕生'
];

foreach ($titles as $title) {
    $p = get_page_by_title($title, OBJECT, 'post');
    if ($p) {
        set_post_thumbnail($p->ID, $attach_id);
        echo "Attached to " . $p->ID . "\n";
    }
}
