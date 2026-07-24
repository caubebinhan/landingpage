<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WSForm compatibility for Form Entries.
 */
class WSForm {

    public function __construct() {
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Format entry data, skipping internal WSForm keys.
     */
    public function form_format_data( $posted ) {
        $formatted_content = '';
        $formatted_content .= '<div class="sss-entry-data">';

        foreach ( (array) $posted as $key => $value ) {
            $k = (string) $key;
            if ( strpos( $k, 'wsf_' ) !== false ) { continue; }

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
     * Derive WSForm form id and title from payload.
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) { return; }
        $decoded = json_decode( (string) $entry->posted, true );
        if ( empty( $decoded['wsf_form_id'] ) ) { return; }

        $form_id            = absint( $decoded['wsf_form_id'] );
        $entry->form_id     = $form_id;
        $entry->form_plugin = 'wsform';

        // Try WS Form API for label, else fallback to post title
        if ( function_exists( 'wsf_form_get_form_object' ) ) {
            $data = wsf_form_get_form_object( $form_id, false, false );
            if ( $data && isset( $data->label ) ) {
                $entry->title = $data->label;
                return;
            }
        }
        $entry->title = get_the_title( $form_id );
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
if ( class_exists( __NAMESPACE__ . '\\WSForm' ) ) {
    new WSForm();
}
