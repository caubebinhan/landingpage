<?php

namespace simply_static_pro;

/**
 * Cloudflare Turnstile (Captcha) integration for Simply Static Pro.
 *
 * Responsibilities:
 * - Enqueue the Turnstile browser script when enabled and a Site Key exists.
 * - Verify tokens for WordPress comments on the origin site.
 * - Provide REST endpoints to verify a token and an optional proxy that
 *   verifies then forwards submissions to an external webhook.
 *
 * The captcha widget markup is injected by Form_Patcher::inject_turnstile_widget()
 * during static export. This class is server/runtime only.
 */
class Form_Captcha {
    /** @var object|null */
    private static $instance = null;

    /**
     * @return object
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor: wires hooks conditionally based on global settings.
     */
    public function __construct() {
        $options = get_option( 'simply-static' );

        $captcha_service = isset( $options['captcha_service'] ) ? $options['captcha_service'] : 'turnstile';

        // Only initialize if Turnstile is selected (or no captcha service is set, defaulting to Turnstile)
        if ( $captcha_service !== 'turnstile' ) {
            return;
        }

        $use_forms    = ! empty( $options['use_forms'] );
        $use_comments = ! empty( $options['use_comments'] );
        $site_key     = isset( $options['cloudflare_turnstile_site_key'] ) ? trim( (string) $options['cloudflare_turnstile_site_key'] ) : '';
        $secret_key   = isset( $options['cloudflare_turnstile_secret_key'] ) ? trim( (string) $options['cloudflare_turnstile_secret_key'] ) : '';

        // Enqueue the Turnstile loader when either forms or comments are enabled and a Site Key is present.
        if ( ( $use_forms || $use_comments ) && ! empty( $site_key ) ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_turnstile_script' ) );
        }

        // Verify comments when comments feature is enabled and a Secret Key exists.
        if ( $use_comments && ! empty( $secret_key ) ) {
            add_filter( 'preprocess_comment', array( $this, 'verify_comment_captcha' ) );
        }

        // Expose REST endpoints when a Secret Key exists (for verification / proxy).
        if ( ! empty( $secret_key ) ) {
            add_action( 'rest_api_init', array( $this, 'register_turnstile_rest' ) );
        }
    }

    /**
     * Enqueue Cloudflare Turnstile script (frontend).
     */
    public function enqueue_turnstile_script() {
        $handle = 'ssp-cloudflare-turnstile';
        if ( ! wp_script_is( $handle, 'enqueued' ) && ! wp_script_is( $handle, 'registered' ) ) {
            wp_enqueue_script( $handle, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
            if ( function_exists( 'wp_script_add_data' ) ) {
                wp_script_add_data( $handle, 'async', true );
                wp_script_add_data( $handle, 'defer', true );
            }
        }
    }

    /**
     * Verify Cloudflare Turnstile token for WordPress comments.
     *
     * @param array $commentdata
     * @return array
     */
    public function verify_comment_captcha( $commentdata ) {
        $options = get_option( 'simply-static' );
        $secret  = isset( $options['cloudflare_turnstile_secret_key'] ) ? trim( (string) $options['cloudflare_turnstile_secret_key'] ) : '';
        if ( empty( $secret ) ) {
            return $commentdata;
        }

        $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        $ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        if ( ! $this->verify_turnstile_token( $token, $ip ) ) {
            wp_die( __( 'Captcha verification failed. Please try again.', 'simply-static-pro' ), 403 );
        }

        return $commentdata;
    }

    /**
     * Register REST routes for Turnstile verification and optional proxy submit.
     */
    public function register_turnstile_rest() {
        register_rest_route( 'simplystatic/v1', '/turnstile/verify', array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => function ( \WP_REST_Request $request ) {
                $token = (string) $request->get_param( 'cf-turnstile-response' );
                $ip    = $request->get_header( 'X-Forwarded-For' );
                if ( empty( $ip ) ) {
                    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
                }
                $ok = $this->verify_turnstile_token( $token, $ip );
                return new \WP_REST_Response( array( 'success' => (bool) $ok ), $ok ? 200 : 400 );
            },
        ) );

        register_rest_route( 'simplystatic/v1', '/turnstile/submit', array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => function ( \WP_REST_Request $request ) {
                $params     = $request->get_params();
                $token      = (string) ( $params['cf-turnstile-response'] ?? '' );
                $forward_to = (string) $request->get_param( 'forward_to' );
                $ip         = $request->get_header( 'X-Forwarded-For' );
                if ( empty( $ip ) ) {
                    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
                }

                // 1) Verify captcha
                if ( ! $this->verify_turnstile_token( $token, $ip ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[SSP] Turnstile submit: captcha_failed' );
                    }
                    return new \WP_REST_Response( array( 'success' => false, 'error' => 'captcha_failed' ), 400 );
                }

                // 2) Validate forward target (require https by default)
                $is_https = is_string( $forward_to ) && strpos( $forward_to, 'https://' ) === 0;
                $allowed  = apply_filters( 'ssp_turnstile_allowed_forward', $is_https, $forward_to, $params );
                if ( ! $forward_to || ! $allowed ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[SSP] Turnstile submit: forward_not_allowed url=' . $forward_to );
                    }
                    return new \WP_REST_Response( array( 'success' => false, 'error' => 'forward_not_allowed' ), 400 );
                }

                // 3) Prepare headers for forwarding to Entries endpoint.
                // Prefer passing through the client-provided secret headers; otherwise inject the stored secret.
                $preferred_header = 'X-Simply-Static-Secret';
                $legacy_header    = 'X-Simply-Static-Studio-Secret';
                $hdr_preferred    = $request->get_header( $preferred_header );
                $hdr_legacy       = $request->get_header( $legacy_header );
                $headers          = array();

                if ( ! empty( $hdr_preferred ) ) {
                    $headers[ $preferred_header ] = $hdr_preferred;
                }
                if ( ! empty( $hdr_legacy ) ) {
                    $headers[ $legacy_header ] = $hdr_legacy;
                }

                if ( empty( $headers[ $preferred_header ] ) && empty( $headers[ $legacy_header ] ) ) {
                    // Neither header provided by client; inject preferred header using stored secret
                    $secret = '';
                    if ( function_exists( 'ssp_get_shared_secret' ) ) {
                        $secret = (string) ssp_get_shared_secret();
                    } elseif ( defined( 'SSP_SHARED_SECRET' ) ) {
                        $secret = (string) SSP_SHARED_SECRET;
                    } elseif ( defined( 'SSS_SECRET_KEY' ) ) {
                        $secret = (string) SSS_SECRET_KEY;
                    }
                    if ( ! empty( $secret ) ) {
                        $headers[ $preferred_header ] = $secret;
                        // Optionally also set legacy header for absolute compatibility downstream
                        $headers[ $legacy_header ] = $secret;
                    }
                }

                // 4) Forward the request body (minus control params) to the Entries endpoint.
                unset( $params['forward_to'] );
                $resp = wp_remote_post( $forward_to, array(
                    'timeout' => 20,
                    'body'    => $params,
                    'headers' => $headers,
                ) );

                if ( is_wp_error( $resp ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[SSP] Turnstile submit: forward_failed ' . $resp->get_error_message() );
                    }
                    return new \WP_REST_Response( array( 'success' => false, 'error' => 'forward_failed', 'message' => $resp->get_error_message() ), 502 );
                }

                $status  = (int) wp_remote_retrieve_response_code( $resp );
                $body    = wp_remote_retrieve_body( $resp );
                $success = ( $status >= 200 && $status < 300 );

                // 5) Return upstream status/body so callers can see the precise outcome.
                if ( ! $success && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    $snippet = is_string( $body ) ? substr( $body, 0, 300 ) : '';
                    error_log( '[SSP] Turnstile submit: upstream_error status=' . $status . ' body_snippet=' . $snippet );
                }

                return new \WP_REST_Response( array( 'success' => $success, 'status' => $status, 'body' => $body ), $status ?: ( $success ? 200 : 502 ) );
            },
        ) );
    }

    /**
     * Call Cloudflare siteverify for a given token using the stored Secret key.
     *
     * @param string      $token
     * @param string|null $remote_ip
     * @return bool
     */
    private function verify_turnstile_token( $token, $remote_ip = null ) {
        $options = get_option( 'simply-static' );
        $secret  = isset( $options['cloudflare_turnstile_secret_key'] ) ? trim( (string) $options['cloudflare_turnstile_secret_key'] ) : '';

        if ( empty( $secret ) || empty( $token ) ) {
            return false;
        }

        $body = array(
            'secret'   => $secret,
            'response' => $token,
        );
        if ( ! empty( $remote_ip ) ) {
            $body['remoteip'] = $remote_ip;
        }

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'timeout' => 10,
            'body'    => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return false;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $json['success'] );
    }
}
