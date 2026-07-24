<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Forms compatibility for Form Entries (admin list formatting + meta enrichment).
 */
class ElementorForms {

    public function __construct() {
        // Populate saved entry meta based on Elementor payload
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        // Provide formatted HTML for entries list
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        // Enrich Forms admin args (endpoint/secret/email/site)
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Format the submitted data into HTML for the Entries screen.
     * Skips internal Elementor/WP fields and prints scalar values.
     *
     * @param array|string $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        // If a different integration already formatted, keep that.
        if ( is_string( $posted ) ) {
            return $posted;
        }

        if ( ! is_array( $posted ) ) {
            return '';
        }

        $skip_keys = [
            // Common WP internals
            '_wp_http_referer',
            '_wpnonce',
            // Captcha fields
            'cf-turnstile-response',
            'g-recaptcha-response',
            // Elementor internals (best effort)
            'form_id',
            'elementor_form_id',
            'element_id',
            'page_id',
            'post_id',
            'queried_id',
            'referrer',
        ];

        $formatted_content = '<div class="sss-entry-data">';

        foreach ( (array) $posted as $key => $value ) {
            $k = (string) $key;
            if ( in_array( $k, $skip_keys, true ) ) {
                continue;
            }

            if ( is_scalar( $value ) ) {
                $v = trim( (string) $value );
                if ( $v !== '' ) {
                    $formatted_content .= esc_html( $v ) . '<br>';
                }
                continue;
            }

            // Flatten simple arrays/objects into a readable string
            $flat = $this->flatten_to_string( $value );
            if ( $flat !== '' ) {
                $formatted_content .= esc_html( $flat ) . '<br>';
            }
        }

        $formatted_content .= '</div>';
        return $formatted_content;
    }

    /**
     * Populate FormEntry metadata from Elementor submission payload.
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
        if ( empty( $decoded ) || ! is_array( $decoded ) ) {
            return;
        }

        // Elementor commonly posts a hidden input named form_id (numeric)
        $form_id = 0;
        if ( isset( $decoded['elementor_form_id'] ) ) {
            $form_id = absint( $decoded['elementor_form_id'] );
        } elseif ( isset( $decoded['form_id'] ) ) {
            $form_id = absint( $decoded['form_id'] );
        } elseif ( isset( $decoded['post_id'] ) ) {
            $form_id = absint( $decoded['post_id'] );
        }

        if ( $form_id > 0 ) {
            $entry->form_id     = $form_id;
            $entry->form_plugin = 'elementor';
            $entry->title       = get_the_title( $form_id );
        } else {
            // Still tag plugin so UI shows origin even without a resolvable title
            $entry->form_plugin = 'elementor';
        }
    }

    /**
     * Add endpoint/secret and email/site info to Forms admin localization.
     * Mirrors other compat classes.
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
}

// Bootstrap
if ( class_exists( __NAMESPACE__ . '\\ElementorForms' ) ) {
    new ElementorForms();
}
