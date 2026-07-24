<?php

namespace simply_static_pro\form\form_entries\compatibility;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Kadence Forms compatibility for Form Entries.
 *
 * Ensures submitted payloads are formatted as readable HTML and maps
 * metadata (form_id, form_plugin, title) for the Entries list.
 */
class KadenceForm {

    public function __construct() {
        add_action( 'simply_static_form_submitted_set_data', [ $this, 'form_set_data' ] );
        add_filter( 'simply_static_formatted_entry', [ $this, 'form_format_data' ] );
        add_filter( 'ssp_forms_args', [ $this, 'add_form_args' ], 10, 2 );
    }

    /**
     * Recursively flatten a value to a space-separated string of scalar leaves.
     * Objects/arrays will be traversed until scalars are found.
     *
     * @param mixed $value
     * @return string
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
            // Best effort: iterate public properties
            foreach ( get_object_vars( $value ) as $v ) {
                $s = $this->flatten_to_string( $v );
                if ( $s !== '' ) { $out[] = $s; }
            }
        }
        return trim( implode( ' ', $out ) );
    }

    /**
     * Format Kadence Forms payload into readable HTML.
     * Skips internal/technical fields. Prevents "[object Object]" by flattening values.
     *
     * @param array $posted
     * @return string
     */
    public function form_format_data( $posted ) {
        // If a previous formatter already produced an HTML string, keep it.
        if ( is_string( $posted ) ) { return $posted; }

        // Only handle Kadence payloads; otherwise, let other formatters run.
        if ( ! is_array( $posted ) ) { return $posted; }
        if ( empty( $posted['_kb_form_id'] ) ) { return $posted; }

        $formatted_content = '<div class="sss-entry-data">';

        foreach ( $posted as $key => $value ) {
            $k = (string) $key;
            // Skip typical internal fields
            if ( $k === '_wp_http_referer' || $k === '_wpnonce' || $k === '_kb_form_action' ) { continue; }
            // Keep _kb_form_id out of the display; it's used for mapping only
            if ( $k === '_kb_form_id' ) { continue; }

            if ( null === $value || $value === '' ) { continue; }

            $string_val = $this->flatten_to_string( $value );
            if ( $string_val === '' ) { continue; }

            // Prefer value-only lines for parity with other integrations.
            $formatted_content .= esc_html( $string_val ) . '<br>';
        }

        $formatted_content .= '</div>';
        return $formatted_content;
    }

    /**
     * Map Kadence form metadata onto the FormEntry model using the hidden _kb_form_id field.
     *
     * @param FormEntry $entry
     * @return void
     */
    public function form_set_data( $entry ) {
        if ( ! $entry instanceof FormEntry ) { return; }
        $decoded = json_decode( (string) $entry->posted, true );
        if ( empty( $decoded['_kb_form_id'] ) ) { return; }

        $form_id            = absint( $decoded['_kb_form_id'] );
        $entry->form_id     = $form_id;
        $entry->form_plugin = 'kadence';
        $entry->title       = get_the_title( $form_id );
    }

    /**
     * Add endpoint/secret and email/site info to Forms admin localization.
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
if ( class_exists( __NAMESPACE__ . '\\KadenceForm' ) ) {
    new KadenceForm();
}
