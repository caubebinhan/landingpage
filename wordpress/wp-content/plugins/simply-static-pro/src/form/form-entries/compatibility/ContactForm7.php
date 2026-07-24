<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;
use WPCF7_ContactForm;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact Form 7 compatibility for Form Entries.
 *
 * Ports logic from the Studio helper and updates hooks to the simply_static_* variants.
 */
class ContactForm7 {

    public function __construct() {
        // Populate saved entry meta based on CF7 payload
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        // Provide formatted HTML for entries list
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        // Enrich Forms admin args (endpoint/secret/email/site)
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Format the submitted data into HTML for the Entries screen.
     *
     * @param array $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        $formatted_content = '';
        $formatted_content .= '<div class="sss-entry-data">';

        foreach ( (array) $posted as $key => $value ) {
            // Skip internal CF7 fields
            if ( strpos( (string) $key, '_wpcf7' ) !== false ) {
                continue;
            }

            if ( $value ) {
                if ( is_array( $value ) ) {
                    $value_object = $value;
                    $value_string = '';
                    foreach ( $value_object as $v_key => $v_val ) {
                        if ( ! is_array( $v_val ) ) {
                            $value_string .= $v_val . ' ';
                        }
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
     * Populate FormEntry metadata from CF7 submission payload.
     *
     * @param FormEntry $entry
     * @return void
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) {
            return;
        }
        $data    = $entry->posted;
        $decoded = json_decode( (string) $data, true );
        if ( empty( $decoded['_wpcf7'] ) ) {
            return;
        }

        $entry->form_id     = absint( $decoded['_wpcf7'] );
        $entry->form_plugin = 'cf7';
        $entry->title       = get_the_title( $entry->form_id );

        // Touch fields so columns are present; CF7 fields vary per form so nothing else to set here.
        if ( class_exists( '\\WPCF7_ContactForm' ) ) {
            // This is mostly for parity; not required for saving
            $form = WPCF7_ContactForm::get_instance( $entry->form_id );
            if ( $form && is_iterable( $form ) ) {
                foreach ( $form as $field ) {
                    if ( ! empty( $field['name'] ) && strpos( $field['name'], '_wpcf7' ) === false ) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Add endpoint/secret and email/site info to Forms admin localization.
     *
     * @param array $args
     * @param int   $post_id
     * @return array
     */
    public function add_form_args( $args, $post_id ) {
        // Merge meta for email recipient if configured
        $meta = [
            'form_email_recipient' => get_post_meta( $post_id, 'form_email_recipient', true ),
        ];
        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
            $args['meta'] = array_merge( $args['meta'], $meta );
        } else {
            $args['meta'] = $meta;
        }

        // Provide recommended endpoint/secret/email details
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
if ( class_exists( __NAMESPACE__ . '\\ContactForm7' ) ) {
    new ContactForm7();
}
