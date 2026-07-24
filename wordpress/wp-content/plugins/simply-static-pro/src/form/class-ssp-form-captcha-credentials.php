<?php

namespace simply_static_pro;

/**
 * Detects and retrieves captcha credentials from popular form plugins.
 *
 * Supports:
 * - Contact Form 7 (ReCaptcha & Turnstile)
 * - WPForms (ReCaptcha & Turnstile)
 * - Fluent Forms (ReCaptcha)
 *
 * Provides a REST endpoint to retrieve detected credentials for the settings UI.
 */
class Form_Captcha_Credentials {
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
     * Constructor: registers REST endpoint.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register REST routes for credential detection.
     */
    public function register_rest_routes() {
        register_rest_route( 'simplystatic/v1', '/captcha/detect-credentials', array(
            'methods'             => 'GET',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => array( $this, 'detect_credentials' ),
        ) );
    }

    /**
     * Detect credentials from all supported form plugins.
     *
     * @return \WP_REST_Response
     */
    public function detect_credentials() {
        $credentials = array(
            'recaptcha' => array(),
            'turnstile' => array(),
        );

        // Detect from Contact Form 7
        $cf7_credentials = $this->detect_cf7_credentials();
        if ( ! empty( $cf7_credentials['recaptcha'] ) ) {
            $credentials['recaptcha'][] = $cf7_credentials['recaptcha'];
        }
        if ( ! empty( $cf7_credentials['turnstile'] ) ) {
            $credentials['turnstile'][] = $cf7_credentials['turnstile'];
        }

        // Detect from WPForms
        $wpforms_credentials = $this->detect_wpforms_credentials();
        if ( ! empty( $wpforms_credentials['recaptcha'] ) ) {
            $credentials['recaptcha'][] = $wpforms_credentials['recaptcha'];
        }
        if ( ! empty( $wpforms_credentials['turnstile'] ) ) {
            $credentials['turnstile'][] = $wpforms_credentials['turnstile'];
        }

        // Detect from Fluent Forms
        $fluentform_credentials = $this->detect_fluentform_credentials();
        if ( ! empty( $fluentform_credentials['recaptcha'] ) ) {
            $credentials['recaptcha'][] = $fluentform_credentials['recaptcha'];
        }

        return new \WP_REST_Response( array(
            'success'     => true,
            'credentials' => $credentials,
        ), 200 );
    }

    /**
     * Detect ReCaptcha and Turnstile credentials from Contact Form 7.
     *
     * Uses CF7's service classes when available to properly handle
     * constants, filters, and database options.
     *
     * @return array
     */
    private function detect_cf7_credentials() {
        $result = array(
            'recaptcha' => null,
            'turnstile' => null,
        );

        // Check if Contact Form 7 is active
        if ( ! class_exists( 'WPCF7' ) ) {
            return $result;
        }

        // Get ReCaptcha credentials using CF7's service class
        // This properly handles constants (WPCF7_RECAPTCHA_SITEKEY, WPCF7_RECAPTCHA_SECRET)
        // and filters (wpcf7_recaptcha_sitekey, wpcf7_recaptcha_secret)
        if ( class_exists( 'WPCF7_RECAPTCHA' ) ) {
            $recaptcha_service = \WPCF7_RECAPTCHA::get_instance();
            $site_key = $recaptcha_service->get_sitekey();
            
            if ( ! empty( $site_key ) ) {
                $secret_key = $recaptcha_service->get_secret( $site_key );
                
                if ( ! empty( $secret_key ) ) {
                    $result['recaptcha'] = array(
                        'source'     => 'Contact Form 7',
                        'site_key'   => $site_key,
                        'secret_key' => $secret_key,
                    );
                }
            }
        } else {
            // Fallback: read directly from option if service class not available
            $recaptcha_option = \WPCF7::get_option( 'recaptcha' );
            if ( is_array( $recaptcha_option ) && ! empty( $recaptcha_option ) ) {
                $site_key = array_key_first( $recaptcha_option );
                $secret_key = $recaptcha_option[ $site_key ] ?? '';
                
                if ( ! empty( $site_key ) && ! empty( $secret_key ) ) {
                    $result['recaptcha'] = array(
                        'source'     => 'Contact Form 7',
                        'site_key'   => $site_key,
                        'secret_key' => $secret_key,
                    );
                }
            }
        }

        // Get Turnstile credentials using CF7's service class
        if ( class_exists( 'WPCF7_Turnstile' ) ) {
            $turnstile_service = \WPCF7_Turnstile::get_instance();
            $site_key = $turnstile_service->get_sitekey();
            
            if ( ! empty( $site_key ) ) {
                $secret_key = $turnstile_service->get_secret( $site_key );
                
                if ( ! empty( $secret_key ) ) {
                    $result['turnstile'] = array(
                        'source'     => 'Contact Form 7',
                        'site_key'   => $site_key,
                        'secret_key' => $secret_key,
                    );
                }
            }
        } else {
            // Fallback: read directly from option if service class not available
            $turnstile_option = \WPCF7::get_option( 'turnstile' );
            if ( is_array( $turnstile_option ) && ! empty( $turnstile_option ) ) {
                $site_key = array_key_first( $turnstile_option );
                $secret_key = $turnstile_option[ $site_key ] ?? '';
                
                if ( ! empty( $site_key ) && ! empty( $secret_key ) ) {
                    $result['turnstile'] = array(
                        'source'     => 'Contact Form 7',
                        'site_key'   => $site_key,
                        'secret_key' => $secret_key,
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Detect ReCaptcha and Turnstile credentials from WPForms.
     *
     * @return array
     */
    private function detect_wpforms_credentials() {
        $result = array(
            'recaptcha' => null,
            'turnstile' => null,
        );

        // Get WPForms settings
        $wpforms_settings = get_option( 'wpforms_settings', array() );
        
        if ( empty( $wpforms_settings ) || ! is_array( $wpforms_settings ) ) {
            return $result;
        }

        // Get ReCaptcha credentials
        $recaptcha_site_key = isset( $wpforms_settings['recaptcha-site-key'] ) 
            ? trim( $wpforms_settings['recaptcha-site-key'] ) : '';
        $recaptcha_secret_key = isset( $wpforms_settings['recaptcha-secret-key'] ) 
            ? trim( $wpforms_settings['recaptcha-secret-key'] ) : '';

        if ( ! empty( $recaptcha_site_key ) && ! empty( $recaptcha_secret_key ) ) {
            $result['recaptcha'] = array(
                'source'     => 'WPForms',
                'site_key'   => $recaptcha_site_key,
                'secret_key' => $recaptcha_secret_key,
            );
        }

        // Get Turnstile credentials
        $turnstile_site_key = isset( $wpforms_settings['turnstile-site-key'] ) 
            ? trim( $wpforms_settings['turnstile-site-key'] ) : '';
        $turnstile_secret_key = isset( $wpforms_settings['turnstile-secret-key'] ) 
            ? trim( $wpforms_settings['turnstile-secret-key'] ) : '';

        if ( ! empty( $turnstile_site_key ) && ! empty( $turnstile_secret_key ) ) {
            $result['turnstile'] = array(
                'source'     => 'WPForms',
                'site_key'   => $turnstile_site_key,
                'secret_key' => $turnstile_secret_key,
            );
        }

        return $result;
    }

    /**
     * Detect ReCaptcha credentials from Fluent Forms.
     *
     * @return array
     */
    private function detect_fluentform_credentials() {
        $result = array(
            'recaptcha' => null,
        );

        // Get Fluent Forms ReCaptcha settings
        $fluentform_recaptcha = get_option( '_fluentform_reCaptcha_details', array() );
        
        if ( empty( $fluentform_recaptcha ) || ! is_array( $fluentform_recaptcha ) ) {
            return $result;
        }

        $site_key = isset( $fluentform_recaptcha['siteKey'] ) 
            ? trim( $fluentform_recaptcha['siteKey'] ) : '';
        $secret_key = isset( $fluentform_recaptcha['secretKey'] ) 
            ? trim( $fluentform_recaptcha['secretKey'] ) : '';

        if ( ! empty( $site_key ) && ! empty( $secret_key ) ) {
            $result['recaptcha'] = array(
                'source'     => 'Fluent Forms',
                'site_key'   => $site_key,
                'secret_key' => $secret_key,
            );
        }

        return $result;
    }
}
