<?php
/**
 * Idempotent Markdown -> WordPress/Polylang importer.
 *
 * Source of truth: /var/www/html/blogs/part-N/NN-slug(-lang).md
 *   - If a numbered article has explicit -en/-vi/-ja files, only those are imported
 *     (a bare "NN-slug.md" alongside them is a legacy draft and is skipped).
 *   - If a numbered article has only a single bare "NN-slug.md" file, its language
 *     is detected from the body content (Vietnamese diacritics heuristic) instead
 *     of being blindly defaulted to English.
 *
 * Re-running this script is safe: each post is looked up by a stable
 * `_hoasen_slug` meta key (derived from the filename, independent of title text),
 * so edits update the existing post instead of creating duplicates.
 */

require_once __DIR__ . '/wp-load.php';

if ( ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) ) {
    fwrite( STDERR, "Polylang functions not available. Aborting.\n" );
    exit( 1 );
}

$blogs_dir = '/var/www/html/blogs';

function hoasen_detect_language( $body ) {
    $vi_chars = preg_match_all(
        '/[ăâđêôơưàằầèéìíòóùúỳáạảãấầẩẫậắằẳẵặẻẽẹềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỷỹ]/u',
        $body
    );
    // A handful of stray Vietnamese words (e.g. a bilingual heading gloss) shouldn't
    // flip an otherwise all-English document, so the bar is set well above that noise floor.
    return $vi_chars > 100 ? 'vi' : 'en';
}

function hoasen_parse_frontmatter( $block ) {
    // Deliberately simple: only flat "key: value" lines are supported (no nested
    // YAML), which is all this project's frontmatter blocks ever use. Quoted
    // values have their surrounding quotes stripped.
    $seo = array();
    foreach ( explode( "\n", $block ) as $line ) {
        if ( ! preg_match( '/^([a-zA-Z_\-]+):\s*(.*)$/', trim( $line ), $m ) ) {
            continue;
        }
        $key = strtolower( $m[1] );
        $val = trim( $m[2] );
        $val = preg_replace( '/^"(.*)"$/', '$1', $val );
        $val = preg_replace( "/^'(.*)'\$/", '$1', $val );
        if ( in_array( $key, array( 'seo_title', 'seo_description', 'focus_keyword' ), true ) ) {
            $seo[ $key ] = $val;
        }
    }
    return $seo;
}

function hoasen_parse_md( $path ) {
    $raw   = file_get_contents( $path );
    $raw   = str_replace( "\r\n", "\n", $raw );
    $lines = explode( "\n", $raw );

    // Skip an optional YAML frontmatter block delimited by "---" lines, capturing
    // any seo_title / seo_description / focus_keyword fields it declares.
    $seo = array();
    if ( isset( $lines[0] ) && trim( $lines[0] ) === '---' ) {
        for ( $j = 1; $j < count( $lines ); $j++ ) {
            if ( trim( $lines[ $j ] ) === '---' ) {
                $seo   = hoasen_parse_frontmatter( implode( "\n", array_slice( $lines, 1, $j - 1 ) ) );
                $lines = array_slice( $lines, $j + 1 );
                break;
            }
        }
    }

    $title = '';
    $body_start = 0;
    foreach ( $lines as $i => $line ) {
        if ( trim( $line ) === '' ) {
            continue;
        }
        if ( strpos( ltrim( $line ), '# ' ) === 0 ) {
            $title = trim( substr( ltrim( $line ), 2 ) );
            $body_start = $i + 1;
        }
        break;
    }

    // Normalize a leading "NN: " numeric prefix left over from inconsistent authoring.
    $title = preg_replace( '/^\d+:\s*/', '', $title );

    $body = trim( implode( "\n", array_slice( $lines, $body_start ) ) );
    $lang = hoasen_detect_language( $body );

    return array( 'title' => $title, 'content' => $body, 'lang' => $lang, 'seo' => $seo );
}

function hoasen_find_existing( $source_key ) {
    global $wpdb;
    $id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE m.meta_key = '_hoasen_source' AND m.meta_value = %s
             AND p.post_type = 'post' AND p.post_status != 'trash'",
            $source_key
        )
    );
    return $id ? (int) $id : 0;
}

function hoasen_upsert( $source_key, $slug_key, $lang, $title, $content, $seo = array() ) {
    // Matching by the source file's own identity (not by language) means a post
    // whose detected language changes on a later run gets updated in place instead
    // of leaving behind an orphaned duplicate under the old language.
    $existing_id = hoasen_find_existing( $source_key );

    $content_html = function_exists( 'hoasen_markdown_to_html' ) ? hoasen_markdown_to_html( $content ) : wpautop( $content );

    $post_data = array(
        'post_title'   => $title,
        'post_content' => $content_html,
        'post_status'  => 'publish',
        'post_author'  => 1,
        'post_type'    => 'post',
    );

    if ( $existing_id ) {
        $post_data['ID'] = $existing_id;
        wp_update_post( $post_data );
        $post_id = $existing_id;
        echo "Updated [$lang] $slug_key: $title\n";
    } else {
        $post_id = wp_insert_post( $post_data );
        update_post_meta( $post_id, '_hoasen_source', $source_key );
        update_post_meta( $post_id, '_hoasen_slug', $slug_key );
        echo "Inserted [$lang] $slug_key: $title\n";
    }

    pll_set_post_language( $post_id, $lang );

    if ( ! empty( $seo['seo_title'] ) ) {
        update_post_meta( $post_id, '_yoast_wpseo_title', $seo['seo_title'] );
        update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $seo['seo_title'] );
    }
    if ( ! empty( $seo['seo_description'] ) ) {
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo['seo_description'] );
        update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $seo['seo_description'] );
    }
    if ( ! empty( $seo['focus_keyword'] ) ) {
        update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seo['focus_keyword'] );
    }

    return $post_id;
}

// --- Discover and group source files ---

$files = glob( $blogs_dir . '/part-*/*.md' );
$groups = array(); // slug_key (e.g. "09-mvcc-low-level") => [ 'en' => path, 'vi' => path, 'ja' => path ]

foreach ( $files as $path ) {
    $basename = basename( $path, '.md' );
    if ( ! preg_match( '/^(\d+)-(.+?)(?:-(en|vi|ja))?$/', $basename, $m ) ) {
        continue;
    }
    $number = $m[1];
    $slug   = $m[2];
    $lang   = isset( $m[3] ) ? $m[3] : null;
    $slug_key = $number . '-' . $slug;

    if ( ! isset( $groups[ $slug_key ] ) ) {
        $groups[ $slug_key ] = array();
    }
    $groups[ $slug_key ][ $lang ?: '_bare' ] = $path;
}

$total_groups = 0;
$total_posts  = 0;

foreach ( $groups as $slug_key => $variants ) {
    // A bare (unsuffixed) file is only a stand-in for whichever language it's actually
    // written in. If a translator later added an explicit file for that SAME language
    // (a redundant re-export of the original), the bare file is superseded and skipped.
    // But if the bare file's language has no explicit counterpart, it's still the only
    // source for that language and must be kept.
    if ( isset( $variants['_bare'] ) ) {
        $bare_lang = hoasen_parse_md( $variants['_bare'] )['lang'];
        if ( isset( $variants[ $bare_lang ] ) ) {
            unset( $variants['_bare'] );
        } else {
            $variants[ $bare_lang ] = $variants['_bare'];
            unset( $variants['_bare'] );
        }
    }

    $ids_by_lang = array();

    foreach ( $variants as $lang => $path ) {
        $parsed = hoasen_parse_md( $path );

        if ( ! $parsed['title'] ) {
            fwrite( STDERR, "WARNING: no H1 title found in $path, skipping.\n" );
            continue;
        }

        $source_key = substr( $path, strlen( $blogs_dir ) + 1 ); // e.g. "part-2/10-....md"
        $post_id    = hoasen_upsert( $source_key, $slug_key, $lang, $parsed['title'], $parsed['content'], $parsed['seo'] );
        $ids_by_lang[ $lang ] = $post_id;
        $total_posts++;
    }

    if ( count( $ids_by_lang ) > 1 ) {
        pll_save_post_translations( $ids_by_lang );
    }

    $total_groups++;
}

echo "\nDone. $total_groups article groups, $total_posts posts upserted.\n";
