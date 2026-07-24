<?php

namespace simply_static_pro\form\form_entries\rest;

use simply_static_pro\database\form_entries\models\FormEntry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Entries extends Rest {

    protected $route = 'entries';

    public function deleteItem( \WP_REST_Request $request ) {
        $id    = $request->get_param( 'id' );
        $entry = FormEntry::query()->find_by( 'id', $id );
        if ( $entry ) {
            FormEntry::query()->delete_by( 'id', $id );
        }
        return rest_ensure_response( [ 'success' => true ] );
    }

    public function getItems( \WP_REST_Request $request ) {
        $page    = $request->get_param( 'page' ) ?: 1;
        $perPage = $request->get_param( 'per_page' ) ?: 25;

        $entries = FormEntry::query()->limit( $perPage )->offset( ( $page - 1 ) * $perPage )->find();
        $total   = FormEntry::query()->count();

        $data = [];
        foreach ( $entries as $entry ) {
            $posted    = json_decode( $entry->posted, true );
            // Rename filter from simply_static_studio_* to simply_static_*
            $formatted = apply_filters( 'simply_static_formatted_entry', $posted );

            $data[] = [
                'id'         => $entry->id,
                'created_at' => $entry->created_at,
                'form_id'    => $entry->form_id,
                'form'       => $entry->form_plugin,
                'posted'     => $posted,
                'title'      => $entry->title,
                'formatted'  => $formatted,
            ];
        }

        return rest_ensure_response( [
            'data'  => $data,
            'total' => $total,
        ] );
    }

    public function verifyDeleteItemPermission( \WP_REST_Request $request ) {
        return $this->verifyAdminRequest( $request );
    }

    public function verifyGetItemsPermission( \WP_REST_Request $request ) {
        return $this->verifyAdminRequest( $request );
    }

    public function verifyAdminRequest( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return true;
    }

    public function verifyRequest( \WP_REST_Request $request ) {
        // Accept either the new or legacy shared secret header.
        $secret = '';
        if ( function_exists( 'ssp_get_shared_secret' ) ) {
            $secret = (string) ssp_get_shared_secret();
        } elseif ( defined( 'SSP_SHARED_SECRET' ) ) {
            $secret = (string) constant( 'SSP_SHARED_SECRET' );
        } elseif ( defined( 'SSS_SECRET_KEY' ) ) {
            $secret = (string) constant( 'SSS_SECRET_KEY' );
        }

        // If no secret configured, allow request (keeps behavior compatible with older setups).
        if ( empty( $secret ) ) {
            return true;
        }

        // Prefer new header, then fallback to legacy header.
        $secret_from_request = $request->get_header( 'X-Simply-Static-Secret' );
        if ( empty( $secret_from_request ) ) {
            $secret_from_request = $request->get_header( 'X-Simply-Static-Studio-Secret' );
        }

        if ( empty( $secret_from_request ) ) {
            return false;
        }
        if ( hash_equals( (string) $secret, (string) $secret_from_request ) ) {
            return true;
        }
        return false;
    }

    public function createItem( \WP_REST_Request $request ) {
        $posted = $request->get_body_params();
        $entry  = new FormEntry();

        // Setup data.
        $entry->posted = wp_json_encode( $posted );
        $this->set_data( $entry );
        $entry->save();

        // Send notification email(s) using internal logic, similar to Studio helper
        $this->handle_emails( $entry, $posted );

        // Fire an action after a form entry was created so other plugins can react.
        do_action( 'sss_form_submitted', $entry, $posted );

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Submitted successfully.', 'simply-static-pro' ),
        ] );
    }

    public function set_data( FormEntry $entry ) {
        // Rename action from simply_static_studio_* to simply_static_*
        do_action( 'simply_static_form_submitted_set_data', $entry );
    }

    /**
     * Prepare and send notification emails for a newly created entry.
     * Ported and adapted from Studio helper's Entries::handle_emails.
     *
     * @param FormEntry $entry
     * @param array     $posted
     * @return void
     */
    public function handle_emails( $entry, $posted ) {
        // Default recipient
        $sending_email = get_option( 'admin_email' );

        // Build formatted HTML content using the same filter used in the admin list
        $formatted = apply_filters( 'simply_static_formatted_entry', $posted );
        $site_name = get_bloginfo( 'name' );

        // Setup mail content
        $subject = sprintf( /* translators: %s: site name */ __( 'New submission on %s', 'simply-static-pro' ), $site_name );
        $message = '<h4>' . sprintf( __( 'New submission on <a href="%s">%s</a>', 'simply-static-pro' ), esc_url( get_bloginfo( 'url' ) ), esc_html( $site_name ) ) . '</h4>';

        if ( ! empty( $entry->form_id ) && ! empty( $entry->form_plugin ) ) {
            $message .= '<p>';
            $message .= '<b>' . esc_html__( 'Form ID:', 'simply-static-pro' ) . '</b> ' . esc_html( $entry->form_id ) . '<br>';
            $message .= '<b>' . esc_html__( 'Form Plugin:', 'simply-static-pro' ) . '</b> ' . esc_html( $entry->form_plugin ) . '<br>';
            $message .= '</p>';
        }

        $message .= '<p><b>' . esc_html__( 'Message:', 'simply-static-pro' ) . '</b><br>' . $formatted . '</p>';

        // Compute meta form ID used to match ssp-form posts, based on plugin
        $form_meta_id = '';
        switch ( (string) $entry->form_plugin ) {
            case 'wpforms':
                $form_meta_id = 'wpforms-form-' . $entry->form_id;
                break;
            case 'wsform':
                $form_meta_id = 'ws-form-' . $entry->form_id;
                break;
            case 'fluentform':
                $form_meta_id = 'fluentform_' . $entry->form_id;
                break;
            case 'gravityforms':
                $form_meta_id = 'gform_' . $entry->form_id;
                break;
            case 'cf7':
            case 'contactform7':
                // CF7 uses numeric post ID; keep empty meta id to fall back below
                $form_meta_id = '';
                break;
        }

        // Look up custom recipient from ssp-form configuration
        if ( ! empty( $form_meta_id ) ) {
            $args = array(
                'meta_query'     => array(
                    array(
                        'key'   => 'form_id',
                        'value' => $form_meta_id,
                    ),
                ),
                'post_type'      => 'ssp-form',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            );
            $form_configs = get_posts( $args );
            if ( ! empty( $form_configs ) ) {
                $ssp_form_email = get_post_meta( (int) $form_configs[0], 'form_email_recipient', true );
                if ( ! empty( $ssp_form_email ) ) {
                    $sending_email = $ssp_form_email;
                }
            }
        } else {
            // No derived meta id; allow external code to provide recipient
            $sending_email = apply_filters( 'sss_form_recipient_email', $sending_email, $entry, $posted );
        }

        /**
         * Let developers hook into the event before sending emails.
         * Mirrors helper behavior.
         */
        do_action( 'sss_form_submitted', $entry, $posted );

        // Prefer native wp_mail by default; allow switching via legacy filter name for compatibility
        $use_smtp = apply_filters( 'sss_use_smtp', true );

        if ( $use_smtp ) {
            $this->send_notification_email( $sending_email, $subject, $message );
        } else {
            do_action( 'ssp_send_notification', $sending_email, $subject, $message );
        }
    }

    /**
     * Send email using WordPress wp_mail with HTML headers.
     */
    protected function send_notification_email( $email, $subject, $message ) {
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        // Allow customization of headers or switching transport entirely
        $headers = apply_filters( 'ssp_notification_mail_headers', $headers, $email, $subject, $message );

        /**
         * Filter to strip HTML from email content.
         * Useful for Studio environments where HTML emails may not render properly.
         *
         * @param bool $strip_html Whether to strip HTML from the email content. Default false.
         */
        $strip_html = apply_filters( 'ssp_strip_html_from_email', false );

        if ( $strip_html ) {
            // Convert <br> tags to newlines before stripping HTML
            $message = preg_replace( '/<br\s*\/?>/i', "\n", $message );
            // Strip all remaining HTML tags
            $message = wp_strip_all_tags( $message );
            // Update headers to plain text
            $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
            $headers = apply_filters( 'ssp_notification_mail_headers', $headers, $email, $subject, $message );
        }

        wp_mail( $email, $subject, $message, $headers );
    }
}
