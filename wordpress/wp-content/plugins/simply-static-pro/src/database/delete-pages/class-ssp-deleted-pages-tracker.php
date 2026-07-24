<?php

namespace simply_static_pro\pages;

use wpdb;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro-only: Track deletions and visibility removals in WordPress so the static site
 * can later remove corresponding files and search index entries.
 *
 * Creates/maintains a custom table `{prefix}simply_static_delete_pages` with columns:
 *  - id (BIGINT PK)
 *  - old_url (TEXT)
 *  - file_path (TEXT)
 *  - content_type (VARCHAR)
 *  - object_id (BIGINT NULL)
 *  - object_type (VARCHAR)
 *  - site_id (BIGINT)
 *  - deleted_at (DATETIME, GMT)
 *  - source (VARCHAR) e.g., post_delete, media_delete, term_delete, user_delete, status_change
 *  - meta (LONGTEXT) JSON for extra data (e.g., media sizes)
 *  - unique_hash (VARCHAR) to dedupe identical events
 *
 * Note: Only loaded as part of Simply Static Pro bootstrap.
 */
class Deleted_Pages_Tracker {
    /** @var Deleted_Pages_Tracker */
    private static $instance;

    /** Table version for schema migrations. */
    private const DB_VERSION = '1.0.0';

    /** Option name storing version. */
    private const DB_VERSION_OPTION = 'ssp_deleted_pages_db_version';

    /**
     * Get singleton instance.
     */
    public static function get_instance() : Deleted_Pages_Tracker {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Ensure schema exists in all contexts.
        // Call once immediately (covers WP-CLI and early hooks), and also on common hooks.
        $this->maybe_install();
        add_action( 'admin_init', [ $this, 'maybe_install' ] );
        add_action( 'plugins_loaded', [ $this, 'maybe_install' ] );
        add_action( 'init', [ $this, 'maybe_install' ] );

        // Allow disabling via filter (defaults to enabled in Pro).
        if ( ! apply_filters( 'ssp_enable_deleted_pages_tracking', true ) ) {
            return;
        }

        // Post/CPT deletion.
        add_action( 'before_delete_post', [ $this, 'handle_before_delete_post' ], 10, 1 );

        // Media deletion (attachment).
        add_action( 'delete_attachment', [ $this, 'handle_delete_attachment' ], 10, 1 );

        // Term deletion.
        add_action( 'delete_term', [ $this, 'handle_delete_term' ], 10, 5 );

        // User deletion.
        add_action( 'delete_user', [ $this, 'handle_delete_user' ], 10, 3 );

        // Status change from public to non-public (draft/private/trash).
        add_action( 'transition_post_status', [ $this, 'handle_transition_post_status' ], 10, 3 );
    }

    /**
     * Create or update the custom table.
     */
    public function maybe_install() : void {
        global $wpdb;
        $installed = get_option( self::DB_VERSION_OPTION );
        if ( $installed !== self::DB_VERSION ) {
            $this->create_table( $wpdb );
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
            return;
        }

        // If option says installed but table is missing (fresh site clones, multisite new blog, etc.), recreate it.
        if ( ! $this->table_exists( $wpdb ) ) {
            $this->create_table( $wpdb );
        }
    }

    /**
     * Create the table using dbDelta.
     *
     * @param wpdb $wpdb
     */
    private function create_table( wpdb $wpdb ) : void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            old_url LONGTEXT NULL,
            file_path LONGTEXT NULL,
            content_type VARCHAR(100) NULL,
            object_id BIGINT(20) UNSIGNED NULL,
            object_type VARCHAR(50) NULL,
            site_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            deleted_at DATETIME NOT NULL,
            source VARCHAR(50) NULL,
            meta LONGTEXT NULL,
            unique_hash VARCHAR(191) NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_deleted_at (deleted_at),
            KEY idx_site (site_id),
            KEY idx_object (object_type, object_id),
            UNIQUE KEY idx_unique_hash (unique_hash)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /** Check if our custom table exists. */
    private function table_exists( wpdb $wpdb ) : bool {
        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is internal, composed from prefix and constant
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        return $exists === $table;
    }

    private function table_name() : string {
        global $wpdb;
        return $wpdb->prefix . 'simply_static_delete_pages';
    }

    /**
     * Record a deletion event. Dedupe by unique_hash; if exists, update deleted_at and meta.
     *
     * @param array $args
     */
    private function record( array $args ) : void {
        global $wpdb;

        // Ensure table exists; if not, try to create and bail gracefully if still missing.
        if ( ! $this->table_exists( $wpdb ) ) {
            $this->maybe_install();
            if ( ! $this->table_exists( $wpdb ) ) {
                return;
            }
        }

        $defaults = [
            'old_url'      => '',
            'file_path'    => '',
            'content_type' => '',
            'object_id'    => null,
            'object_type'  => '',
            'site_id'      => get_current_blog_id(),
            'deleted_at'   => gmdate( 'Y-m-d H:i:s' ),
            'source'       => '',
            'meta'         => null,
        ];
        $data = wp_parse_args( $args, $defaults );

        // Normalize and limit sizes.
        $data['old_url']   = is_string( $data['old_url'] ) ? $data['old_url'] : '';
        $data['file_path'] = is_string( $data['file_path'] ) ? ltrim( $data['file_path'], '/' ) : '';
        $data['content_type'] = substr( (string) $data['content_type'], 0, 100 );
        $data['object_type']  = substr( (string) $data['object_type'], 0, 50 );
        $data['source']       = substr( (string) $data['source'], 0, 50 );

        $unique_hash = $this->make_unique_hash( $data['old_url'], $data['file_path'], $data['content_type'], (int) $data['site_id'] );

        $table = $this->table_name();

        // Try insert; on duplicate, update deleted_at and meta.
        $wpdb->hide_errors();
        $inserted = $wpdb->insert(
            $table,
            [
                'old_url'      => $data['old_url'],
                'file_path'    => $data['file_path'],
                'content_type' => $data['content_type'],
                'object_id'    => $data['object_id'],
                'object_type'  => $data['object_type'],
                'site_id'      => $data['site_id'],
                'deleted_at'   => $data['deleted_at'],
                'source'       => $data['source'],
                'meta'         => is_null( $data['meta'] ) ? null : wp_json_encode( $data['meta'] ),
                'unique_hash'  => $unique_hash,
            ],
            [ '%s','%s','%s','%d','%s','%d','%s','%s','%s','%s' ]
        );
        $wpdb->show_errors();

        if ( false === $inserted ) {
            // Assume duplicate key; perform update.
            $wpdb->update(
                $table,
                [
                    'deleted_at' => $data['deleted_at'],
                    'meta'       => is_null( $data['meta'] ) ? null : wp_json_encode( $data['meta'] ),
                ],
                [ 'unique_hash' => $unique_hash ],
                [ '%s','%s' ],
                [ '%s' ]
            );
        }
    }

    private function make_unique_hash( string $old_url, string $file_path, string $content_type, int $site_id ) : string {
        return md5( implode( '|', [ (string) $site_id, $old_url, $file_path, $content_type ] ) );
    }

    /**
     * Resolve a WordPress URL to a static file path used by Simply Static.
     */
    private function url_to_static_file_path( string $url ) : string {
        $origin = home_url();
        
        // Parse URL components to handle query strings properly.
        $parsed = parse_url( $url );
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        $query = isset( $parsed['query'] ) ? $parsed['query'] : '';
        
        // Get the path relative to the origin.
        $origin_parsed = parse_url( $origin );
        $origin_path = isset( $origin_parsed['path'] ) ? $origin_parsed['path'] : '';
        
        // Remove origin path prefix if present.
        if ( $origin_path && strpos( $path, $origin_path ) === 0 ) {
            $path = substr( $path, strlen( $origin_path ) );
        }
        
        $path = ltrim( $path, '/' );
        $ext  = pathinfo( $path, PATHINFO_EXTENSION );

        if ( $ext ) {
            // Already a file-like path (e.g., image.jpg or page.html)
            return ltrim( $path, '/' );
        }

        // Handle URLs with query strings (e.g., ?p=123).
        // These are typically ugly permalinks that shouldn't be tracked for deletion
        // as they don't map to valid static file paths.
        if ( ! empty( $query ) && empty( $path ) ) {
            // Return empty string to indicate this URL cannot be mapped to a static file.
            // The caller should handle this gracefully.
            return '';
        }

        // Directory-like URL => map to index.html
        if ( '' === $path ) {
            return 'index.html';
        }

        $path = rtrim( $path, '/' ) . '/index.html';
        return ltrim( $path, '/' );
    }

    /**
     * Compute the canonical public URL for a post/page/CPT regardless of its current status.
     * Falls back to sample permalink to avoid query-based links like ?page_id=123.
     *
     * @param \WP_Post $post
     * @return string Full URL
     */
    private function compute_post_canonical_url( $post ) : string {
        if ( ! is_object( $post ) ) {
            $post = get_post( (int) $post );
        }
        if ( ! $post ) {
            return home_url( '/' );
        }

        // If already published, the regular permalink is fine.
        if ( isset( $post->post_status ) && 'publish' === $post->post_status ) {
            $url = get_permalink( $post );
            if ( $url && ! is_wp_error( $url ) ) {
                return $url;
            }
        }

        // Use sample permalink for non-public statuses to get pretty structure.
        if ( function_exists( 'get_sample_permalink' ) ) {
            $sample = get_sample_permalink( (int) $post->ID, $post->post_name, $post->post_title );
            if ( is_array( $sample ) && ! empty( $sample[0] ) ) {
                $permalink = (string) $sample[0];
                $slug      = isset( $sample[1] ) ? (string) $sample[1] : (string) $post->post_name;
                // Replace common tokens with the slug.
                $permalink = str_replace( array( '%postname%', '%pagename%' ), $slug, $permalink );

                // Hierarchical pages: ensure full path built via get_page_uri.
                if ( is_page( $post ) || ( isset( $post->post_type ) && 'page' === $post->post_type ) ) {
                    if ( function_exists( 'get_page_uri' ) ) {
                        $uri = get_page_uri( (int) $post->ID );
                        if ( $uri ) {
                            // Ensure we replace the last segment with full hierarchy if token remained.
                            $permalink = preg_replace( '#/[^/]+/?$#', '/' . ltrim( $uri, '/' ) . '/', $permalink );
                        }
                    }
                }

                return $permalink;
            }
        }

        // Fallback: build from post_name (and page uri for hierarchical types) under home_url().
        $path = '';
        if ( function_exists( 'get_page_uri' ) && ( is_page( $post ) || ( isset( $post->post_type ) && 'page' === $post->post_type ) ) ) {
            $page_uri = get_page_uri( (int) $post->ID );
            $path     = '/' . ltrim( (string) $page_uri, '/' ) . '/';
        } else {
            $path = '/' . ltrim( (string) $post->post_name, '/' ) . '/';
        }
        return home_url( $path );
    }

    /**
     * Public helper: get canonical URL for post regardless of status.
     */
    public function post_to_canonical_url( $post ) : string {
        return $this->compute_post_canonical_url( $post );
    }

    /**
     * Public helper: map a post to its static file path using the canonical URL.
     */
    public function post_to_file_path( $post ) : string {
        $url = $this->compute_post_canonical_url( $post );
        return $this->url_to_static_file_path( $url );
    }

    /* ===================== Public API (wrappers) ====================== */

    /**
     * Public wrapper to record a deletion event from other Pro classes.
     *
     * @param array $args Same shape as internal record() expects.
     * @return void
     */
    public function add_record( array $args ) : void {
        $this->record( $args );
    }

    /**
     * Public wrapper to convert a site URL into a static file path.
     *
     * @param string $url
     * @return string
     */
    public function url_to_path( string $url ) : string {
        return $this->url_to_static_file_path( $url );
    }

    /**
     * Public API: remove records from the tracker table using simple criteria.
     * Supported keys: object_type, object_id, content_type, old_url, file_path, site_id, source
     *
     * @param array $criteria
     * @return void
     */
    public function remove_records( array $criteria ) : void {
        global $wpdb;
        $table = $this->table_name();

        // Always scope to current site unless explicitly provided.
        if ( ! isset( $criteria['site_id'] ) ) {
            $criteria['site_id'] = get_current_blog_id();
        }

        // Whitelist allowed columns.
        $allowed = [ 'object_type', 'object_id', 'content_type', 'old_url', 'file_path', 'site_id', 'source' ];
        $where   = [];
        foreach ( $allowed as $key ) {
            if ( array_key_exists( $key, $criteria ) && $criteria[ $key ] !== null && $criteria[ $key ] !== '' ) {
                $where[ $key ] = $criteria[ $key ];
            }
        }

        if ( empty( $where ) ) {
            return; // nothing to do
        }

        // Casts
        if ( isset( $where['object_id'] ) ) {
            $where['object_id'] = (int) $where['object_id'];
        }
        if ( isset( $where['site_id'] ) ) {
            $where['site_id'] = (int) $where['site_id'];
        }

        // Execute delete.
        // Ensure table exists before attempting delete to avoid errors on fresh installs.
        if ( ! $this->table_exists( $wpdb ) ) {
            $this->maybe_install();
            if ( ! $this->table_exists( $wpdb ) ) {
                return;
            }
        }
        $wpdb->delete( $table, $where );
    }

    /** Convenience: remove by object type/id. */
    public function remove_by_object( string $object_type, int $object_id ) : void {
        $this->remove_records([
            'object_type' => $object_type,
            'object_id'   => (int) $object_id,
        ]);
    }

    /** Convenience: remove all rows for a content type (scoped to site). */
    public function remove_by_content_type( string $content_type ) : void {
        $this->remove_records([
            'content_type' => $content_type,
        ]);
    }

    /** Convenience: remove by URL and its resolved file path. */
    public function remove_by_url( string $url ) : void {
        $path = $this->url_to_static_file_path( $url );
        // Try file_path match first
        $this->remove_records([
            'file_path' => $path,
        ]);
        // Also remove any rows keyed by old_url (belt and suspenders)
        $this->remove_records([
            'old_url' => $url,
        ]);
    }

    /* ===================== Handlers ====================== */

    public function handle_before_delete_post( int $post_id ) : void {
        $post = get_post( $post_id );
        if ( ! $post ) { return; }

        // Compute canonical pretty URL regardless of status.
        $url = $this->compute_post_canonical_url( $post );
        $file_path = $this->url_to_static_file_path( $url );
        $this->record([
            'old_url'      => $url,
            'file_path'    => $file_path,
            'content_type' => (string) $post->post_type,
            'object_id'    => (int) $post->ID,
            'object_type'  => 'post',
            'source'       => 'post_delete',
        ]);
    }

    public function handle_delete_attachment( int $attachment_id ) : void {
        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) { return; }

        $file_path = $this->url_to_static_file_path( $url );

        // Collect sizes in meta for reference.
        $meta = wp_get_attachment_metadata( $attachment_id );
        $sizes = [];
        if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $base = wp_get_upload_dir();
            $baseurl = isset( $base['baseurl'] ) ? trailingslashit( $base['baseurl'] ) : '';
            $basedir = isset( $base['basedir'] ) ? trailingslashit( $base['basedir'] ) : '';
            $file = isset( $meta['file'] ) ? $meta['file'] : '';
            $dir  = $file ? trailingslashit( dirname( $file ) ) : '';
            foreach ( $meta['sizes'] as $size ) {
                if ( isset( $size['file'] ) ) {
                    $size_url = $baseurl . $dir . $size['file'];
                    $sizes[] = [
                        'url'  => $size_url,
                        'path' => $this->url_to_static_file_path( $size_url ),
                    ];
                }
            }
        }

        $this->record([
            'old_url'      => $url,
            'file_path'    => $file_path,
            'content_type' => 'attachment',
            'object_id'    => (int) $attachment_id,
            'object_type'  => 'post',
            'source'       => 'media_delete',
            'meta'         => empty( $sizes ) ? null : [ 'sizes' => $sizes ],
        ]);
    }

    public function handle_delete_term( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ) : void {
        // $term may be term ID or WP_Term depending on WP version.
        $term_id = is_object( $term ) ? $term->term_id : (int) $term;
        $url = get_term_link( (int) $term_id, $taxonomy );
        if ( is_wp_error( $url ) ) { return; }

        $file_path = $this->url_to_static_file_path( $url );
        $this->record([
            'old_url'      => $url,
            'file_path'    => $file_path,
            'content_type' => (string) $taxonomy,
            'object_id'    => (int) $term_id,
            'object_type'  => 'term',
            'source'       => 'term_delete',
        ]);
    }

    public function handle_delete_user( int $user_id, $reassign, $user ) : void {
        // Author archive URL
        $url = get_author_posts_url( $user_id );
        if ( ! $url ) { return; }

        $file_path = $this->url_to_static_file_path( $url );
        $this->record([
            'old_url'      => $url,
            'file_path'    => $file_path,
            'content_type' => 'author',
            'object_id'    => (int) $user_id,
            'object_type'  => 'user',
            'source'       => 'user_delete',
        ]);
    }

    public function handle_transition_post_status( string $new_status, string $old_status, $post ) : void {
        if ( ! is_object( $post ) ) { return; }

        // Only react if moving from public to non-public
        $public_before = ( 'publish' === $old_status );
        $public_after  = ( 'publish' === $new_status );
        if ( $public_before && ! $public_after ) {
            $url = $this->compute_post_canonical_url( $post );
            if ( ! $url || is_wp_error( $url ) ) { return; }
            $file_path = $this->url_to_static_file_path( $url );
            $this->record([
                'old_url'      => $url,
                'file_path'    => $file_path,
                'content_type' => (string) $post->post_type,
                'object_id'    => (int) $post->ID,
                'object_type'  => 'post',
                'source'       => 'status_change',
            ]);
        } elseif ( ! $public_before && $public_after ) {
            // Reverse: non-public -> publish, remove any tracked rows for this post
            $this->remove_by_object( 'post', (int) $post->ID );
        }
    }
}
