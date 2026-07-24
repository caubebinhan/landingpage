<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fluent Forms compatibility for Form Entries.
 *
 * Ports logic from the Studio helper and updates hooks to the simply_static_* variants.
 */
class FluentForm {

    public function __construct() {
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Format the submitted data into HTML for the Entries screen.
     * Filters out Fluent Forms internal keys.
     *
     * @param array $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        // Preserve already formatted output
        if ( is_string( $posted ) ) { return $posted; }

        // Only handle Fluent payloads we can recognize
        if ( ! is_array( $posted ) ) { return $posted; }
        if ( empty( $posted['__fluent_form_embded_post_id'] ) ) { return $posted; }

        $formatted_content = '<div class="sss-entry-data">';

        foreach ( (array) $posted as $key => $value ) {
            $k = (string) $key;
            if ( strpos( $k, '_fluentform' ) !== false ) { continue; }
            if ( strpos( $k, '__fluent_form_embded_post_id' ) !== false ) { continue; }
            if ( strpos( $k, '_wp_http_referer' ) !== false ) { continue; }

            if ( $value ) {
                if ( is_array( $value ) ) {
                    $value_string = '';
                    foreach ( $value as $v ) {
                        if ( ! is_array( $v ) ) { $value_string .= $v . ' '; }
                    }
                    $formatted_content .= esc_html( trim( $value_string ) ) . '<br>';
                } else {
                    $formatted_content .= esc_html( (string) $value ) . '<br>';
                }
            }
        }

        $formatted_content .= '</div>';
        return $formatted_content;
    }

    /**
     * Populate FormEntry metadata from Fluent Forms submission payload.
     * Uses the embedded post id marker.
     *
     * @param FormEntry $entry
     * @return void
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) { return; }
        $data    = $entry->posted;
        $decoded = json_decode( (string) $data, true );
        if ( empty( $decoded['__fluent_form_embded_post_id'] ) ) {
            return;
        }

        $post_id           = absint( $decoded['__fluent_form_embded_post_id'] );
        $entry->form_id     = $post_id;
        // Use singular 'fluentform' to align with email routing map in Entries REST
        $entry->form_plugin = 'fluentform';
        $entry->title       = get_the_title( $post_id );
    }

    /**
     * Add endpoint/secret and email/site info to Forms admin localization.
     */
    public function add_form_args( $args, $post_id ) {
        $meta = [
            'form_email_recipient' => get_post_meta( $post_id, 'form_email_recipient', true ),
        ];
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

// Bootstrap immediately on load
if ( class_exists( __NAMESPACE__ . '\\FluentForm' ) ) {
    new FluentForm();
}
