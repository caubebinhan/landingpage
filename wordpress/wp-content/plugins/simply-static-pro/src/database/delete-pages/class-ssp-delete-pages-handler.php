<?php

namespace simply_static_pro\pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro-only: Hooks into various WordPress events and writes rows into
 * the Simply Static Pro deleted pages table via Deleted_Pages_Tracker.
 *
 * Covered events:
 *  - wp_trash_post: moving posts/pages/CPTs to trash
 *  - deactivated_plugin / deleted_plugin
 *  - switch_theme / deleted_theme
 *
 * Notes:
 *  - For plugin/theme events we add a row without a file_path so the current
 *    Delete_Tracked_Pages_Task will ignore deletion, but we retain a record
 *    that something structural changed (can be used by future logic).
 */
class Delete_Pages_Handler {
    /** @var Delete_Pages_Handler */
    private static $instance;

    /** @var Deleted_Pages_Tracker */
    private $tracker;

    /**
     * Get singleton instance.
     *
     * @return Delete_Pages_Handler
     */
    public static function get_instance() : Delete_Pages_Handler {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Ensure tracker class exists.
        if ( class_exists( '\\simply_static_pro\\pages\\Deleted_Pages_Tracker' ) ) {
            $this->tracker = Deleted_Pages_Tracker::get_instance();
        }
        $this->register_hooks();
    }

    private function register_hooks() : void {
        // Post moved to trash
        add_action( 'wp_trash_post', [ $this, 'handle_trash_post' ], 10, 1 );
        // Post restored from trash (reverse)
        add_action( 'untrash_post', [ $this, 'handle_untrash_post' ], 10, 1 );

        // Plugin lifecycle
        add_action( 'deactivated_plugin', [ $this, 'handle_deactivated_plugin' ], 10, 1 );
        add_action( 'deleted_plugin', [ $this, 'handle_deleted_plugin' ], 10, 1 );
        // Plugin activated (reverse)
        add_action( 'activated_plugin', [ $this, 'handle_activated_plugin' ], 10, 1 );

        // Theme lifecycle
        add_action( 'switch_theme', [ $this, 'handle_switch_theme' ], 10, 3 );
        add_action( 'deleted_theme', [ $this, 'handle_deleted_theme' ], 10, 1 );
    }

    /**
     * When a post is moved to trash, record it like a visibility removal.
     */
    public function handle_trash_post( int $post_id ) : void {
        if ( ! $this->tracker ) { return; }
        $post = get_post( $post_id );
        if ( ! $post ) { return; }
        // Use canonical permalink regardless of status to avoid query-style URLs.
        $url = $this->tracker->post_to_canonical_url( $post );
        if ( ! $url || is_wp_error( $url ) ) { return; }
        $file_path = $this->tracker->post_to_file_path( $post );
        $this->tracker->add_record([
            'old_url'      => $url,
            'file_path'    => $file_path,
            'content_type' => (string) $post->post_type,
            'object_id'    => (int) $post->ID,
            'object_type'  => 'post',
            'source'       => 'post_trash',
        ]);
    }

    /**
     * When a post is restored from trash, remove any tracked rows for this post.
     */
    public function handle_untrash_post( int $post_id ) : void {
        if ( ! $this->tracker ) { return; }
        $this->tracker->remove_by_object( 'post', (int) $post_id );
    }

    /**
     * When a plugin is deactivated, store a structural-change signal (no file_path).
     */
    public function handle_deactivated_plugin( string $plugin_basename ) : void {
        if ( ! $this->tracker ) { return; }
        $this->tracker->add_record([
            'old_url'      => home_url('/'),
            'file_path'    => '', // do not delete any file automatically
            'content_type' => 'plugin',
            'object_id'    => null,
            'object_type'  => 'plugin',
            'source'       => 'plugin_deactivate',
            'meta'         => [ 'plugin' => $plugin_basename ],
        ]);
    }

    /**
     * When a plugin is deleted, store a structural-change signal (no file_path).
     */
    public function handle_deleted_plugin( string $plugin_basename ) : void {
        if ( ! $this->tracker ) { return; }
        $this->tracker->add_record([
            'old_url'      => home_url('/'),
            'file_path'    => '',
            'content_type' => 'plugin',
            'object_id'    => null,
            'object_type'  => 'plugin',
            'source'       => 'plugin_delete',
            'meta'         => [ 'plugin' => $plugin_basename ],
        ]);
    }

    /**
     * When a plugin is activated, remove any pending plugin structural-change rows.
     */
    public function handle_activated_plugin( string $plugin_basename ) : void {
        if ( ! $this->tracker ) { return; }
        // Broadly remove plugin-related rows (scoped to site)
        $this->tracker->remove_by_content_type( 'plugin' );
    }

    /**
     * When the theme is switched, record a row (no file_path delete).
     *
     * @param string $new_name Theme name
     * @param WP_Theme $new_theme New theme object
     * @param WP_Theme $old_theme Old theme object
     */
    public function handle_switch_theme( $new_name, $new_theme, $old_theme ) : void {
        if ( ! $this->tracker ) { return; }
        // Treat switching to a theme as resolving prior theme-structure changes: remove theme rows (reverse)
        $this->tracker->remove_by_content_type( 'theme' );
        // Still record that a structural change occurred (optional, keep for auditing)
        $meta = [
            'new_stylesheet' => is_object( $new_theme ) ? $new_theme->get_stylesheet() : '',
            'old_stylesheet' => is_object( $old_theme ) ? $old_theme->get_stylesheet() : '',
        ];
        $this->tracker->add_record([
            'old_url'      => home_url('/'),
            'file_path'    => '',
            'content_type' => 'theme',
            'object_id'    => null,
            'object_type'  => 'theme',
            'source'       => 'theme_switch',
            'meta'         => $meta,
        ]);
    }

    /**
     * When a theme is deleted, record a row (no file_path delete).
     */
    public function handle_deleted_theme( string $stylesheet ) : void {
        if ( ! $this->tracker ) { return; }
        $this->tracker->add_record([
            'old_url'      => home_url('/'),
            'file_path'    => '',
            'content_type' => 'theme',
            'object_id'    => null,
            'object_type'  => 'theme',
            'source'       => 'theme_delete',
            'meta'         => [ 'stylesheet' => $stylesheet ],
        ]);
    }
}
