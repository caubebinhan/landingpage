<?php
/**
 * Minimal Markdown -> HTML converter tailored to this theme's blog content:
 * headers, bold/italic, inline & fenced code (incl. mermaid diagrams),
 * blockquotes, ordered/unordered lists, tables, links, horizontal rules,
 * and $$...$$ / $...$ math left untouched for client-side KaTeX rendering.
 *
 * Not a full CommonMark implementation - deliberately scoped to the
 * subset of Markdown actually used across the blog source files.
 */

function hoasen_markdown_to_html( $md ) {
    $md = str_replace( "\r\n", "\n", $md );

    // --- Step 1: protect fenced code blocks (```lang ... ```) from further processing ---
    $code_blocks = array();
    $md = preg_replace_callback(
        '/```([a-zA-Z0-9_-]*)\n(.*?)```/s',
        function ( $m ) use ( &$code_blocks ) {
            $lang = trim( $m[1] );
            $code = rtrim( $m[2], "\n" );
            $key  = "\x02CODEBLOCK" . count( $code_blocks ) . "\x03";
            if ( $lang === 'mermaid' ) {
                $html = '<pre class="mermaid">' . $code . '</pre>';
            } else {
                $class = $lang ? ' class="language-' . esc_attr( $lang ) . '"' : '';
                $html  = '<pre><code' . $class . '>' . esc_html( $code ) . '</code></pre>';
            }
            $code_blocks[] = $html;
            return $key;
        },
        $md
    );

    // --- Step 2: protect math ($$...$$ and inline $...$) so table/inline rules don't
    // mistake pipe characters inside expressions (e.g. "$|R|$") for a table column ---
    $math_blocks = array();
    $md = preg_replace_callback(
        '/\$\$(.+?)\$\$/s',
        function ( $m ) use ( &$math_blocks ) {
            $key = "\x02MATHBLOCK" . count( $math_blocks ) . "\x03";
            $math_blocks[] = '$$' . $m[1] . '$$';
            return $key;
        },
        $md
    );
    $md = preg_replace_callback(
        '/\$([^\$\n]+?)\$/',
        function ( $m ) use ( &$math_blocks ) {
            $key = "\x02MATHBLOCK" . count( $math_blocks ) . "\x03";
            $math_blocks[] = '$' . $m[1] . '$';
            return $key;
        },
        $md
    );

    $lines = explode( "\n", $md );
    $html_lines = array();
    $in_ul = false;
    $in_ol = false;
    $in_table = false;
    $table_rows = array();
    $para_buffer = array();

    $flush_paragraph = function () use ( &$para_buffer, &$html_lines ) {
        if ( ! empty( $para_buffer ) ) {
            $text = implode( ' ', $para_buffer );
            $text = trim( $text );
            if ( $text !== '' ) {
                $html_lines[] = '<p>' . hoasen_markdown_inline( $text ) . '</p>';
            }
            $para_buffer = array();
        }
    };

    $close_lists = function () use ( &$in_ul, &$in_ol, &$html_lines ) {
        if ( $in_ul ) {
            $html_lines[] = '</ul>';
            $in_ul = false;
        }
        if ( $in_ol ) {
            $html_lines[] = '</ol>';
            $in_ol = false;
        }
    };

    $flush_table = function () use ( &$table_rows, &$in_table, &$html_lines ) {
        if ( ! $in_table || empty( $table_rows ) ) {
            $table_rows = array();
            $in_table   = false;
            return;
        }
        $html_lines[] = '<table>';
        $header = array_shift( $table_rows );
        $html_lines[] = '<thead><tr>' . implode(
            '',
            array_map(
                function ( $cell ) {
                    return '<th>' . hoasen_markdown_inline( trim( $cell ) ) . '</th>';
                },
                $header
            )
        ) . '</tr></thead>';
        $html_lines[] = '<tbody>';
        foreach ( $table_rows as $row ) {
            $html_lines[] = '<tr>' . implode(
                '',
                array_map(
                    function ( $cell ) {
                        return '<td>' . hoasen_markdown_inline( trim( $cell ) ) . '</td>';
                    },
                    $row
                )
            ) . '</tr>';
        }
        $html_lines[] = '</tbody></table>';
        $table_rows = array();
        $in_table   = false;
    };

    foreach ( $lines as $line ) {
        // Table row detection: line starts and contains a pipe.
        if ( preg_match( '/^\s*\|?(.+\|.+)\|?\s*$/', $line ) && strpos( $line, '|' ) !== false ) {
            $trimmed = trim( $line );
            $is_separator = (bool) preg_match( '/^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?$/', $trimmed );
            if ( $is_separator ) {
                if ( ! $in_table ) {
                    continue; // stray separator with no header, ignore
                }
                continue; // skip the "---|---" row, already handled by flush_table
            }
            $flush_paragraph();
            $close_lists();
            $cells = array_map( 'trim', explode( '|', trim( $trimmed, '|' ) ) );
            $table_rows[] = $cells;
            $in_table = true;
            continue;
        } elseif ( $in_table ) {
            $flush_table();
        }

        // Horizontal rule.
        if ( preg_match( '/^\s*(-{3,}|\*{3,}|_{3,})\s*$/', $line ) ) {
            $flush_paragraph();
            $close_lists();
            $html_lines[] = '<hr/>';
            continue;
        }

        // Headers.
        if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
            $flush_paragraph();
            $close_lists();
            $level = strlen( $m[1] );
            $html_lines[] = "<h{$level}>" . hoasen_markdown_inline( trim( $m[2] ) ) . "</h{$level}>";
            continue;
        }

        // Blockquote.
        if ( preg_match( '/^\s*>\s?(.*)$/', $line, $m ) ) {
            $flush_paragraph();
            $close_lists();
            $html_lines[] = '<blockquote><p>' . hoasen_markdown_inline( trim( $m[1] ) ) . '</p></blockquote>';
            continue;
        }

        // Unordered list item.
        if ( preg_match( '/^\s*[-*+]\s+(.*)$/', $line, $m ) ) {
            $flush_paragraph();
            if ( $in_ol ) {
                $html_lines[] = '</ol>';
                $in_ol = false;
            }
            if ( ! $in_ul ) {
                $html_lines[] = '<ul>';
                $in_ul = true;
            }
            $html_lines[] = '<li>' . hoasen_markdown_inline( trim( $m[1] ) ) . '</li>';
            continue;
        }

        // Ordered list item.
        if ( preg_match( '/^\s*\d+\.\s+(.*)$/', $line, $m ) ) {
            $flush_paragraph();
            if ( $in_ul ) {
                $html_lines[] = '</ul>';
                $in_ul = false;
            }
            if ( ! $in_ol ) {
                $html_lines[] = '<ol>';
                $in_ol = true;
            }
            $html_lines[] = '<li>' . hoasen_markdown_inline( trim( $m[1] ) ) . '</li>';
            continue;
        }

        // Protected code/math block placeholder on its own line.
        if ( preg_match( '/^\x02(CODEBLOCK|MATHBLOCK)\d+\x03$/', trim( $line ) ) ) {
            $flush_paragraph();
            $close_lists();
            $html_lines[] = trim( $line );
            continue;
        }

        // Blank line: paragraph/list boundary.
        if ( trim( $line ) === '' ) {
            $flush_paragraph();
            $close_lists();
            continue;
        }

        // Otherwise: accumulate into the current paragraph.
        $para_buffer[] = trim( $line );
    }

    $flush_paragraph();
    $close_lists();
    if ( $in_table ) {
        $flush_table();
    }

    $html = implode( "\n", $html_lines );

    // --- Restore protected blocks ---
    foreach ( $math_blocks as $i => $block ) {
        $html = str_replace( "\x02MATHBLOCK{$i}\x03", $block, $html );
    }
    foreach ( $code_blocks as $i => $block ) {
        $html = str_replace( "<p>\x02CODEBLOCK{$i}\x03</p>", $block, $html );
        $html = str_replace( "\x02CODEBLOCK{$i}\x03", $block, $html );
    }

    return $html;
}

function hoasen_markdown_inline( $text ) {
    // Inline code first, so its contents are shielded from bold/italic/link parsing.
    $inline_code = array();
    $text = preg_replace_callback(
        '/`([^`]+)`/',
        function ( $m ) use ( &$inline_code ) {
            $key = "\x02ICODE" . count( $inline_code ) . "\x03";
            $inline_code[] = '<code>' . esc_html( $m[1] ) . '</code>';
            return $key;
        },
        $text
    );

    // Links: [text](url)
    $text = preg_replace( '/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $text );

    // Bold: **text** or __text__
    $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
    $text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );

    // Italic: *text* or _text_ (single, not doubled)
    $text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text );
    $text = preg_replace( '/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/', '<em>$1</em>', $text );

    foreach ( $inline_code as $i => $code ) {
        $text = str_replace( "\x02ICODE{$i}\x03", $code, $text );
    }

    return $text;
}
