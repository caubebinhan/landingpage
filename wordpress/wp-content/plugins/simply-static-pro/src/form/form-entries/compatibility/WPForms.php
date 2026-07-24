<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPForms compatibility for Form Entries.
 */
class WPForms {

    public function __construct() {
        // Prevent WPForms welcome redirect on first activation (parity with helper)
        add_filter( 'pre_option_wpforms_activation_redirect', '__return_true' );

        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Format WPForms payload. Prefer nested wpforms.fields when present.
     *
     * @param array $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        // Preserve already formatted output from other handlers
        if ( is_string( $posted ) ) { return $posted; }

        if ( ! is_array( $posted ) ) { return $posted; }

        // Only handle when signature field is present
        if ( ! ( ! empty( $posted['wpforms'] ) && ! empty( $posted['wpforms']['fields'] ) && is_array( $posted['wpforms']['fields'] ) ) ) {
            return $posted; // let DefaultForm or others handle
        }

        $formatted_content = '';

        if ( ! empty( $posted['wpforms'] ) && ! empty( $posted['wpforms']['fields'] ) && is_array( $posted['wpforms']['fields'] ) ) {
            $formatted_content .= '<div class="sss-entry-data">';
            foreach ( $posted['wpforms']['fields'] as $value ) {
                if ( ! $value ) { continue; }
                if ( is_array( $value ) ) {
                    $value_string = '';
                    foreach ( $value as $v ) { if ( ! is_array( $v ) ) { $value_string .= $v . ' '; } }
                    $formatted_content .= esc_html( trim( $value_string ) ) . '<br>';
                } else {
                    $formatted_content .= esc_html( (string) $value ) . '<br>';
                }
            }
            $formatted_content .= '</div>';
        }

        return $formatted_content;
    }

    /**
     * Set WPForms id, plugin, and title.
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) { return; }
        $decoded = json_decode( (string) $entry->posted, true );
        if ( empty( $decoded['wpforms'] ) || empty( $decoded['wpforms']['id'] ) ) { return; }

        $form_id            = absint( $decoded['wpforms']['id'] );
        $entry->form_id     = $form_id;
        $entry->form_plugin = 'wpforms';

        // Title helpers if WPForms available, else fallback to post title
        if ( function_exists( 'wpforms_get_post_title' ) && function_exists( 'get_post' ) ) {
            $entry->title = wpforms_get_post_title( get_post( $form_id ) );
        } else {
            $entry->title = get_the_title( $form_id );
        }
    }

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
if ( class_exists( __NAMESPACE__ . '\\WPForms' ) ) {
    new WPForms();
}
