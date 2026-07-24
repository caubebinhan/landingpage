<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gravity Forms compatibility for Form Entries.
 */
class GravityForms {

    public function __construct() {
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Clean up Gravity Forms payload for admin listing.
     */
    public function form_format_data( $posted ) {
        // Preserve already formatted output
        if ( is_string( $posted ) ) { return $posted; }

        // Only handle recognizable Gravity Forms payloads
        if ( ! is_array( $posted ) ) { return $posted; }
        if ( empty( $posted['gform_submit'] ) ) { return $posted; }

        $formatted_content = '<div class="sss-entry-data">';

        foreach ( (array) $posted as $key => $value ) {
            $k = (string) $key;
            if ( strpos( $k, 'gform' ) !== false ) { continue; }
            if ( strpos( $k, 'state_' ) !== false ) { continue; }
            if ( strpos( $k, 'is_submit' ) !== false ) { continue; }
            if ( strpos( $k, 'version_hash' ) !== false ) { continue; }

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
     * Fill entry meta using gform_submit parameter and RGFormsModel for title.
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) { return; }
        $decoded = json_decode( (string) $entry->posted, true );
        if ( empty( $decoded['gform_submit'] ) ) { return; }

        $form_id = absint( $decoded['gform_submit'] );
        $entry->form_id     = $form_id;
        $entry->form_plugin = 'gravityforms';

        if ( class_exists( '\\RGFormsModel' ) ) {
            $form = \RGFormsModel::get_form( $form_id );
            if ( $form && isset( $form->title ) ) {
                $entry->title = $form->title;
            } else {
                $entry->title = get_the_title( $form_id );
            }
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
if ( class_exists( __NAMESPACE__ . '\\GravityForms' ) ) {
    new GravityForms();
}
