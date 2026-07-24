<?php
/**
 * Generates one branded SVG cover image per article group (shared across its
 * en/vi/ja posts) and sets it as the featured image. Category text stays in
 * Latin script by design so a single cover works across all three languages
 * without needing CJK font embedding; the actual article title is already
 * shown in the page's own <h1>.
 */

require_once __DIR__ . '/wp-load.php';

$upload_dir = wp_upload_dir();
$covers_dir = $upload_dir['basedir'] . '/hoasen-covers';
if ( ! file_exists( $covers_dir ) ) {
    wp_mkdir_p( $covers_dir );
}

$categories = array(
    1 => 'Storage Engines',
    2 => 'Concurrency Control',
    3 => 'Indexing & Data Structures',
    4 => 'Distributed Systems',
    5 => 'Query Optimization',
    6 => 'I/O & Memory',
    7 => 'Modern Architectures',
);

function hoasen_cover_svg( $number, $category, $part ) {
    $palette = array(
        array( '#7f1d1d', '#be123c' ),
        array( '#1c1917', '#7f1d1d' ),
        array( '#78350f', '#be123c' ),
    );
    list( $c1, $c2 ) = $palette[ $part % 3 ];

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$c1}"/>
      <stop offset="100%" stop-color="{$c2}"/>
    </linearGradient>
    <pattern id="dots" width="28" height="28" patternUnits="userSpaceOnUse">
      <circle cx="2" cy="2" r="1.4" fill="rgba(255,255,255,0.14)"/>
    </pattern>
  </defs>
  <rect width="800" height="450" fill="url(#bg)"/>
  <rect width="800" height="450" fill="url(#dots)"/>
  <circle cx="680" cy="90" r="150" fill="rgba(255,255,255,0.06)"/>
  <text x="60" y="180" font-family="Georgia, 'Times New Roman', serif" font-size="150" font-weight="700" fill="rgba(255,255,255,0.16)">{$number}</text>
  <text x="64" y="300" font-family="Arial, sans-serif" font-size="15" font-weight="700" letter-spacing="3" fill="rgba(255,255,255,0.85)">PART {$part} &#183; {$category}</text>
  <text x="64" y="340" font-family="Georgia, 'Times New Roman', serif" font-style="italic" font-size="30" font-weight="600" fill="#ffffff">HoaSen Table Journal</text>
  <line x1="64" y1="360" x2="164" y2="360" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
</svg>
SVG;
}

global $wpdb;
$rows = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_hoasen_slug'" );

$done = 0;
foreach ( $rows as $row ) {
    $slug_key = $row->meta_value;
    if ( ! preg_match( '/^(\d+)-/', $slug_key, $m ) ) {
        continue;
    }
    $number = (int) $m[1];
    $part   = (int) ceil( $number <= 8 ? 1 : ( $number <= 16 ? 2 : ( $number <= 24 ? 3 : ( $number <= 32 ? 4 : ( $number <= 39 ? 5 : ( $number <= 47 ? 6 : 7 ) ) ) ) ) );
    $category = $categories[ $part ];

    $svg = hoasen_cover_svg( str_pad( $number, 2, '0', STR_PAD_LEFT ), $category, $part );
    $file_path = $covers_dir . '/' . $slug_key . '.svg';
    file_put_contents( $file_path, $svg );

    $file_url = $upload_dir['baseurl'] . '/hoasen-covers/' . $slug_key . '.svg';

    // Find (or create) the attachment for this cover.
    $existing_attachment_id = $wpdb->get_var(
        $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hoasen_cover_slug' AND meta_value = %s", $slug_key )
    );

    if ( $existing_attachment_id ) {
        $attachment_id = (int) $existing_attachment_id;
    } else {
        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => 'image/svg+xml',
                'post_title'     => 'Cover ' . $slug_key,
                'post_status'    => 'inherit',
                'guid'           => $file_url,
            ),
            $file_path
        );
        update_post_meta( $attachment_id, '_hoasen_cover_slug', $slug_key );
    }

    update_post_meta( $attachment_id, '_wp_attached_file', 'hoasen-covers/' . $slug_key . '.svg' );
    update_post_meta(
        $attachment_id,
        '_wp_attachment_metadata',
        array( 'width' => 800, 'height' => 450, 'file' => 'hoasen-covers/' . $slug_key . '.svg' )
    );

    // Attach it as the featured image of every language post in this group.
    $post_ids = $wpdb->get_col(
        $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hoasen_slug' AND meta_value = %s", $slug_key )
    );
    foreach ( $post_ids as $post_id ) {
        set_post_thumbnail( (int) $post_id, $attachment_id );
    }

    $done++;
    echo "Cover set for $slug_key (" . count( $post_ids ) . " posts)\n";
}

echo "\nDone. $done covers generated.\n";
