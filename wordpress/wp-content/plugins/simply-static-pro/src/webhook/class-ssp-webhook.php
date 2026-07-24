<?php

namespace simply_static_pro;

use Simply_Static\Options;

/**
 * Centralized webhook handler for all Simply Static export types.
 */
class Webhook {
    /** @var Webhook|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     * @return Webhook
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Fire after export cleanup (end of export lifecycle) for full/export/update/build/single
        add_action( 'ss_after_cleanup', [ $this, 'maybe_fire_after_cleanup' ] );
    }

    /**
     * Called on ss_after_cleanup to detect the export type that just ran and fire webhook accordingly.
     */
    public function maybe_fire_after_cleanup() {
        $export_type = $this->detect_export_type();
        if ( ! $export_type ) {
            return;
        }
        $context = [];
        if ( 'single' === $export_type ) {
            $single_id = get_option( 'simply-static-use-single' );
            if ( $single_id ) {
                $context['single_ids'] = [ (int) $single_id ];
            }
        } elseif ( 'build' === $export_type ) {
            $build_id = get_option( 'simply-static-use-build' );
            if ( $build_id ) {
                $context['build_id'] = (int) $build_id;
            }
        }
        $this->fire_webhook( $export_type, $context );
    }

    /**
     * Detect current/last export type using options used by core/pro.
     * @return string|null One of export|update|build|single
     */
    protected function detect_export_type() {
        $use_single = get_option( 'simply-static-use-single' );
        $use_build  = get_option( 'simply-static-use-build' );
        if ( ! empty( $use_single ) ) {
            return 'single';
        }
        if ( ! empty( $use_build ) ) {
            return 'build';
        }
        // For full/update check generate_type option
        if ( class_exists( '\Simply_Static\Options' ) ) {
            try {
                $options = Options::reinstance();
                return ( $options->get( 'generate_type' ) === 'update' ) ? 'update' : 'export';
            } catch ( \Throwable $e ) {
                // Fallback to export
            }
        }
        return 'export';
    }

    /**
     * Fire a webhook event if enabled in settings.
     * @param string $export_type export|update|build|single
     * @param array  $context Additional data like single_ids/build_id
     */
    public function fire_webhook( $export_type, array $context = [] ) {
        $settings = get_option( 'simply-static' );
        $url      = is_array( $settings ) && ! empty( $settings['ss_webhook_url'] ) ? esc_url_raw( $settings['ss_webhook_url'] ) : '';
        if ( empty( $url ) ) {
            return;
        }

        // Check enabled types
        $enabled = is_array( $settings ) && ! empty( $settings['ss_webhook_enabled_types'] ) && is_array( $settings['ss_webhook_enabled_types'] )
            ? array_map( 'sanitize_key', $settings['ss_webhook_enabled_types'] )
            : [ 'export', 'update', 'build', 'single' ];

        if ( ! in_array( $export_type, $enabled, true ) ) {
            return;
        }

        $payload = array_merge( [
            'event'       => 'simply_static_export',
            'site_url'    => home_url( '/' ),
            'export_type' => $export_type,
            'timestamp'   => current_time( 'mysql', true ),
            'success'     => true,
        ], $context );

        $args = [
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
        ];

        // New generic filter
        $args = apply_filters( 'ssp_webhook_request_args', $args, $url, $payload );
        // Legacy filter for Single Export only
        if ( 'single' === $export_type ) {
            $args = apply_filters( 'ssp_single_export_webhook_request_args', $args, $url, $payload );
        }

        wp_remote_post( $url, $args );

        do_action( 'ssp_after_webhook_fired', $export_type, $url, $payload );
        if ( 'single' === $export_type ) {
            do_action( 'ssp_after_run_single_webhook', isset( $context['single_ids'] ) ? (array) $context['single_ids'] : [], $url );
        }
    }
}
