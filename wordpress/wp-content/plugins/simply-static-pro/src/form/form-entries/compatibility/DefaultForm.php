<?php

namespace simply_static_pro\form\form_entries\compatibility;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default/generic formatter for unknown form plugins.
 * Provides a sane HTML rendering and enriches Forms admin args.
 */
class DefaultForm {

    /**
     * Deeply flatten any value to a space-separated string of scalar leaves.
     */
    protected function flatten_to_string( $value ) : string {
        if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
            return trim( (string) $value );
        }
        $out = [];
        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                $s = $this->flatten_to_string( $v );
                if ( $s !== '' ) { $out[] = $s; }
            }
        } elseif ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $v ) {
                $s = $this->flatten_to_string( $v );
                if ( $s !== '' ) { $out[] = $s; }
            }
        }
        return trim( implode( ' ', $out ) );
    }

    public function __construct() {
        // Run late so plugin-specific formatters can handle their payload first.
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ], 99 );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Generic formatting of posted key/value pairs into HTML.
     *
     * @param array $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        // If already formatted by a specific integration, keep result.
        if ( is_string( $posted ) ) { return $posted; }

        if ( ! is_array( $posted ) ) { return ''; }

        $formatted_content = '<div class="sss-entry-data">';

        foreach ( $posted as $key => $value ) {
            $k = (string) $key;
            // Skip common internal fields
            if ( $k === '_wp_http_referer' || $k === '_wpnonce' ) { continue; }

            $string_val = $this->flatten_to_string( $value );
            if ( $string_val === '' ) { continue; }
            $formatted_content .= esc_html( $string_val ) . '<br>';
        }

        $formatted_content .= '</div>';
        return $formatted_content;
    }

    /**
     * Enrich Forms admin localization with recipient meta and defaults.
     */
    public function add_form_args( $args, $post_id ) {
        $meta = [ 'form_email_recipient' => get_post_meta( $post_id, 'form_email_recipient', true ) ];
        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
            $args['meta'] = array_merge( $args['meta'], $meta );
        } else {
            $args['meta'] = $meta;
        }

        $secret = '';
        if ( function_exists( 'ssp_get_shared_secret' ) ) {
            $secret = (string) ssp_get_shared_secret();
        } elseif ( defined( 'SSP_SHARED_SECRET' ) ) {
            $secret = (string) SSP_SHARED_SECRET;
        }

        $args['endpoint']  = get_rest_url( null, '/simplystatic/v1/entries' );
        $args['secret']    = $secret;
        $args['email']     = get_option( 'admin_email' );
        $args['site_name'] = get_bloginfo( 'name' );
        return $args;
    }
}

// Bootstrap
if ( class_exists( __NAMESPACE__ . '\\DefaultForm' ) ) {
    new DefaultForm();
}
