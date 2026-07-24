<?php
/**
 * One-off cleanup pass over all blog source markdown files:
 *   1. Strips numbered section-heading prefixes ("## 1. Title" -> "## Title"),
 *      a stereotypical "AI whitepaper" tic the user asked to remove.
 *   2. Strips a trailing "SEO Metadata / SEO Optimization / SEO Meta Information"
 *      section (and any lone "---" divider right before it) that some sources
 *      had baked into the visible article body instead of the frontmatter.
 */

$files = glob( '/var/www/html/blogs/part-*/*.md' );
$changed = 0;

foreach ( $files as $path ) {
    $raw  = file_get_contents( $path );
    $orig = $raw;

    // 1. Strip numbered heading prefixes at H2-H4 level: "## 3. Title" -> "## Title"
    $raw = preg_replace( '/^(#{2,4})\s*\d+\.\s*/m', '$1 ', $raw );

    // 2. Strip a trailing SEO metadata section, if present.
    $lines = explode( "\n", $raw );
    $cut_at = null;
    foreach ( $lines as $i => $line ) {
        if ( preg_match( '/^#{1,3}\s*(\d+\.\s*)?SEO\b/i', trim( $line ) ) ) {
            $cut_at = $i;
            break;
        }
    }
    if ( $cut_at !== null ) {
        // Also drop a lone "---" divider immediately preceding the SEO heading.
        $end = $cut_at;
        if ( $end > 0 && trim( $lines[ $end - 1 ] ) === '---' ) {
            $end--;
        }
        $lines = array_slice( $lines, 0, $end );
        $raw   = rtrim( implode( "\n", $lines ) ) . "\n";
    }

    if ( $raw !== $orig ) {
        file_put_contents( $path, $raw );
        $changed++;
        echo "Cleaned: $path\n";
    }
}

echo "\nDone. $changed files changed out of " . count( $files ) . ".\n";
