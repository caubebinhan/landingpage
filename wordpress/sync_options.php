<?php
require '/var/www/html/wp-load.php';
$opt = get_option('polylang');
if (!is_array($opt)) $opt = [];
$opt['sync'] = array_unique(array_merge($opt['sync'] ?? [], ['taxonomies', 'post_meta', '_thumbnail_id', 'post_date']));
update_option('polylang', $opt);
echo "Updated polylang sync options.\n";
