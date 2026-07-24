<?php
require '/var/www/html/wp-load.php';

require_once '/var/www/html/wp-content/themes/hoasen-theme/functions.php';

global $hoasen_translations_vi, $hoasen_landing_vi, $hoasen_translations_ja, $hoasen_landing_ja;

if (!function_exists('pll_register_string')) {
    die("Polylang not active.\n");
}

$all_keys = array_unique(array_merge(
    array_keys($hoasen_translations_vi ?? []),
    array_keys($hoasen_landing_vi ?? []),
    array_keys($hoasen_translations_ja ?? []),
    array_keys($hoasen_landing_ja ?? [])
));

foreach ($all_keys as $key) {
    pll_register_string('hoasen-theme-' . md5($key), $key, 'hoasen-theme', true);
}

// Now we insert the translations directly into Polylang's MO layer if possible
// Polylang stores translations in the wp_options or postmeta for strings.
// But it's easier to just dump them into a MO file, OR use the pll_register_string and let admin translate.
// Wait, the user wants us to IMPORT these translations so they don't have to retype them!
// To programmatically add translations for registered strings in Polylang:
global $wpdb;
$strings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}icl_strings WHERE context = 'hoasen-theme'");
if (empty($strings)) {
    // If not using WPML tables, Polylang uses wp_posts with post_type 'polylang_mo'
    $mos = get_posts(['post_type' => 'polylang_mo', 'post_status' => 'publish', 'numberposts' => -1]);
    foreach ($mos as $mo) {
        $lang = pll_get_post_language($mo->ID);
        $translations = unserialize($mo->post_content);
        if (!is_array($translations)) $translations = [];
        
        $dirty = false;
        if ($lang === 'vi') {
            foreach (array_merge($hoasen_translations_vi??[], $hoasen_landing_vi??[]) as $k => $v) {
                $translations[$k] = $v;
                $dirty = true;
            }
        }
        if ($lang === 'ja') {
            foreach (array_merge($hoasen_translations_ja??[], $hoasen_landing_ja??[]) as $k => $v) {
                $translations[$k] = $v;
                $dirty = true;
            }
        }
        if ($dirty) {
            wp_update_post(['ID' => $mo->ID, 'post_content' => serialize($translations)]);
            echo "Updated translations for $lang.\n";
        }
    }
}

echo "Registered " . count($all_keys) . " strings in Polylang.\n";
