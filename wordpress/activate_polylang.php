<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
$result = activate_plugin('polylang/polylang.php');
if (is_wp_error($result)) {
    echo "Error: " . $result->get_error_message() . "\n";
} else {
    echo "Polylang plugin activated successfully!\n";
}
