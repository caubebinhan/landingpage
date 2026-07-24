<?php

namespace simply_static_pro;

use DOMDocument;
use DOMXPath;

/**
 * Class to handle patches for various form plugins.
 */
class Form_Patcher {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Form_Patcher.
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor for Form_Patcher.
	 */
 public function __construct() {
        $options = get_option( 'simply-static' );

        if ( empty( $options['use_forms'] ) ) {
            return;
        }

        add_action( 'ss_dom_before_save', array( $this, 'patch_form_html' ), 10, 2 );

        // Minimal, non-invasive Turnstile widget injection: runs after other patches.
        // Does not modify or remove third-party scripts; only adds a <div class="cf-turnstile"> inside forms.
        add_action( 'ss_dom_before_save', array( $this, 'inject_turnstile_widget' ), 99, 2 );

        // Google reCAPTCHA v3 widget injection: runs after other patches.
        // Adds a hidden input and inline script to execute reCAPTCHA on form submit.
        add_action( 'ss_dom_before_save', array( $this, 'inject_recaptcha_widget' ), 99, 2 );

		if ( ( defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' ) ) ) {
			add_action( 'init', array( $this, 'adapt_cf7_markup' ) );
		}
		
		// Gravity Forms: let SSP validation take over by dequeuing GF front-end assets on dynamic pages
		if ( class_exists( 'GFForms' ) ) {
			add_action( 'init', array( $this, 'adapt_gf_markup' ) );
		}

        if ( ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' ) ) ) {
            add_action( 'wp_footer', array( $this, 'hide_elementor_validation' ) );
        }
    }

    /**
     * Check if the current frontend context is a single ssp-form post configured as embedded.
     *
     * @return bool
     */
    private function is_embedded_ssp_form_context() : bool {
        if ( function_exists( 'is_singular' ) && is_singular( 'ssp-form' ) ) {
            $post_id = get_queried_object_id();
            if ( $post_id ) {
                $type = get_post_meta( (int) $post_id, 'form_type', true );
                return ( $type === 'embedded' );
            }
        }
        return false;
    }

    /**
     * Check if a given URL corresponds to a single ssp-form post that is configured as embedded.
     * Used during static export hooks where WP conditionals are not reliable.
     */
    private function is_embedded_ssp_form_url( $url ) : bool {
        if ( ! is_string( $url ) || $url === '' ) {
            return false;
        }
        // Collect all embedded ssp-form permalinks (ids only to keep it fast)
        $ids = get_posts( array( 'numberposts' => -1, 'post_type' => 'ssp-form', 'fields' => 'ids' ) );
        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return false;
        }
        foreach ( $ids as $pid ) {
            $type = get_post_meta( (int) $pid, 'form_type', true );
            if ( $type !== 'embedded' ) { continue; }
            $plink = get_permalink( (int) $pid );
            if ( $plink && is_string( $plink ) && rtrim( $plink, '/' ) === rtrim( $url, '/' ) ) {
                return true;
            }
        }
        return false;
    }

	/**
	 * Patch form HTML during static export.
	 *
	 * Delegates to individual patcher classes for each supported form plugin.
	 *
	 * @param string $static_page given HTML string.
	 * @param string $url given static URL.
	 *
	 * @return mixed
	 */
	public function patch_form_html( $static_page, $url ) {
		// Do not modify embedded ssp-form single pages during export
		if ( $this->is_embedded_ssp_form_url( $url ) ) {
			return $static_page;
		}

		// Load patcher classes
		$patcher_dir = dirname( __FILE__ ) . '/patcher/';
		
		// Fluent Forms patching
		if ( is_plugin_active( 'fluentform/fluentform.php' ) && $this->has_webhook_connection_for( 'fluentform' ) ) {
			require_once $patcher_dir . 'FluentForms_Patcher.php';
			$static_page = \simply_static_pro\form\patcher\FluentForms_Patcher::patch( $static_page );
		}

		// Gravity Forms patching
		if ( class_exists( 'GFForms' ) && $this->has_webhook_connection_for( 'gravityforms' ) ) {
			require_once $patcher_dir . 'GF_Patcher.php';
			$static_page = \simply_static_pro\form\patcher\GF_Patcher::patch( $static_page );
		}

		// Contact Form 7 patching
		if ( ( defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' ) ) && $this->has_webhook_connection_for( 'contact-form-7' ) ) {
			require_once $patcher_dir . 'CF7_Patcher.php';
			$static_page = \simply_static_pro\form\patcher\CF7_Patcher::patch( $static_page );
		}

		return $static_page;
	}

    /**
     * Check if there is at least one ssp-form connection configured as webhook for a given plugin.
     *
     * @param string $plugin_slug One of: 'gravityforms', 'fluentform', 'wpforms', 'contact-form-7', etc.
     * @return bool
     */
    private function has_webhook_connection_for( $plugin_slug ) {
        $args = array(
            'post_type'      => 'ssp-form',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => 'form_type',
                    'value' => 'webhook',
                ),
                array(
                    'key'   => 'form_plugin',
                    'value' => $plugin_slug,
                ),
            ),
        );
        $posts = get_posts( $args );
        return ! empty( $posts );
    }

 public function adapt_cf7_markup() {
        // Only dequeue CF7 when there is at least one CF7 webhook connection configured.
        // If all CF7 connections are Embedded, keep vendor assets intact on WP.
        if ( ! $this->has_webhook_connection_for( 'contact-form-7' ) ) {
            return;
        }
        // Do not apply CF7 adjustments on embedded ssp-form single pages.
        if ( $this->is_embedded_ssp_form_context() ) {
            return;
        }
        add_filter( 'wpcf7_load_js', '__return_false' );
        add_filter( 'wpcf7_load_css', '__return_false' );

		// Ensure CF7 doesn't perform its own HTML5 validation tooltips on static builds.
		add_filter( 'wpcf7_form_novalidate', '__return_true' );

		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_style( 'contact-form-7' );
	}


    /**
     * Gravity Forms: dequeue/deregister front-end assets so SSP validation/submit owns the flow.
  */
 public function adapt_gf_markup() {
        // Only dequeue GF when there is at least one GF webhook connection configured.
        // If all GF connections are Embedded, keep vendor assets intact on WP.
        if ( ! $this->has_webhook_connection_for( 'gravityforms' ) ) {
            return;
        }
        // Do not apply GF adjustments on embedded ssp-form single pages.
        if ( $this->is_embedded_ssp_form_context() ) {
            return;
        }
        // Dequeue on front-end when scripts/styles are enqueued.
        add_action( 'wp_enqueue_scripts', function () {
            // Dequeue GF scripts so SSP owns validation/submit, but KEEP GF styles for visual consistency.
            $scripts = [
                'gform_gravityforms',
				'gform_frontend',
				'gform_forms',
				'gform_conditional_logic',
				'gform_json',
				'gform_datepicker',
				'gform_recaptcha',
				'google-recaptcha',
			];
			foreach ( $scripts as $h ) { wp_dequeue_script( $h ); wp_deregister_script( $h ); }
			// Intentionally do not dequeue styles; we keep GF CSS on static export.
		}, 100 );
	}

    public function hide_elementor_validation() {
        // Do not alter Elementor messages on embedded ssp-form single pages.
        if ( $this->is_embedded_ssp_form_context() ) {
            return;
        }
        ?>
        <style>
            .elementor-message.elementor-message-danger {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Inject Cloudflare Turnstile widget markup inside supported forms without altering any other code.
     * This runs late in the ss_dom_before_save chain and is strictly additive.
     *
     * @param string|\DOMDocument $static_page
     * @param string               $url
     *
     * @return string|\DOMDocument
     */
    public function inject_turnstile_widget( $static_page, $url ) {
        // Do not inject into embedded ssp-form single pages during export
        if ( $this->is_embedded_ssp_form_url( $url ) ) {
            return $static_page;
        }
        // Read global settings
        $settings        = get_option( 'simply-static' );
        $captcha_service = isset( $settings['captcha_service'] ) ? $settings['captcha_service'] : 'turnstile';

        // Only proceed if Turnstile is selected (or no captcha service is set, defaulting to Turnstile)
        if ( $captcha_service !== 'turnstile' ) {
            return $static_page;
        }

        $site_key  = isset( $settings['cloudflare_turnstile_site_key'] ) ? trim( (string) $settings['cloudflare_turnstile_site_key'] ) : '';
        $protect   = ! empty( $settings['use_forms'] ) || ! empty( $settings['use_comments'] );
        // Optional Turnstile customization
        $theme_raw = isset( $settings['cloudflare_turnstile_theme'] ) ? trim( (string) $settings['cloudflare_turnstile_theme'] ) : '';
        $size_raw  = isset( $settings['cloudflare_turnstile_size'] ) ? trim( (string) $settings['cloudflare_turnstile_size'] ) : '';
        $allowed_themes = array( 'auto', 'light', 'dark' );
        $allowed_sizes  = array( 'normal', 'flexible', 'compact' );
        $theme = in_array( $theme_raw, $allowed_themes, true ) ? $theme_raw : 'auto';
        $size  = in_array( $size_raw, $allowed_sizes, true ) ? $size_raw : 'normal';

        if ( empty( $site_key ) || ! $protect ) {
            return $static_page;
        }

        // Normalize to DOMDocument
        $dom         = null;
        $html_string = null;

        if ( $static_page instanceof \DOMDocument ) {
            $dom = $static_page;
        } else {
            $html_string = is_string( $static_page ) ? $static_page : '';
            if ( $html_string === '' ) {
                return $static_page;
            }
            $dom = new DOMDocument();
            libxml_use_internal_errors( true );
            $dom->loadHTML( $html_string, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
            libxml_clear_errors();
        }

        $xpath    = new DOMXPath( $dom );
        $modified = false;

        // Supported forms + native comments form
        $form_xpaths = array(
            // CF7
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wpcf7 ")]/descendant::form',
            '//*[contains(concat(" ", normalize-space(@class), " "), " wpcf7-form ")]',
            // Gravity Forms
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " gform_wrapper ")]/descendant::form',
            // WPForms
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wpforms-container ")]/descendant::form',
            // Elementor Forms
            '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-form ")]',
            // WS Form
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wsf-form ")]/descendant::form',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " wsf-form ")]',
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " ws-form ")]/descendant::form',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " ws-form ")]',
            // Fluent Forms
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " frm-fluent-form ")]',
            // Forminator (custom forms)
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " forminator-custom-form ")]/descendant::form',
            '//*[self::form and (contains(concat(" ", normalize-space(@class), " "), " forminator-")
               or starts-with(@id, "forminator-module-") or starts-with(@id, "forminator-form-") )]',
            // Bricks
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " brxe-form ")]',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " brxe-brf-pro-forms ")]',
            // Kadence
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wp-block-kadence-form ")]/descendant::form',
            // Native comments
            '//*[@id="commentform" and self::form]'
        );

        // Collect unique form nodes
        $forms = array();
        foreach ( $form_xpaths as $expr ) {
            $nodes = $xpath->query( $expr );
            if ( $nodes && $nodes->length ) {
                foreach ( $nodes as $n ) {
                    $forms[ spl_object_hash( $n ) ] = $n;
                }
            }
        }

        if ( empty( $forms ) ) {
            return $static_page;
        }

        foreach ( $forms as $form ) {
            // Skip if the widget already exists inside this form
            $existing = ( new DOMXPath( $dom ) )->query( './/*[contains(concat(" ", normalize-space(@class), " "), " cf-turnstile ")]', $form );
            if ( $existing && $existing->length ) {
                continue;
            }

            // Build widget div
            $widget = $dom->createElement( 'div' );
            $widget->setAttribute( 'class', 'cf-turnstile' );
            $widget->setAttribute( 'data-sitekey', $site_key );
            // Add spacing so the submit button above has room
            $widget->setAttribute( 'style', 'margin-top:15px;' );
            // Apply optional UI settings
            if ( $theme ) {
                $widget->setAttribute( 'data-theme', $theme );
            }
            if ( $size ) {
                $widget->setAttribute( 'data-size', $size );
            }

            // Try to place before submit button
            $submit = ( new DOMXPath( $dom ) )->query( './/input[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="submit"] | .//button[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="submit"]', $form );
            if ( $submit && $submit->length ) {
                $btn = $submit->item( 0 );
                if ( $btn->parentNode ) {
                    $btn->parentNode->insertBefore( $widget, $btn );
                    $modified = true;
                    continue;
                }
            }

            // Fallback: append at end of form
            $form->appendChild( $widget );
            $modified = true;
        }

        if ( ! $modified ) {
            return $static_page;
        }

        // Return in the same type as received
        if ( $static_page instanceof \DOMDocument ) {
            return $dom;
        }

        return $dom->saveHTML();
    }

    /**
     * Inject Google reCAPTCHA v3 widget markup inside supported forms without altering any other code.
     * This runs late in the ss_dom_before_save chain and is strictly additive.
     *
     * @param string|\DOMDocument $static_page
     * @param string               $url
     *
     * @return string|\DOMDocument
     */
    public function inject_recaptcha_widget( $static_page, $url ) {
        // Do not inject into embedded ssp-form single pages during export
        if ( $this->is_embedded_ssp_form_url( $url ) ) {
            return $static_page;
        }

        // Read global settings
        $settings        = get_option( 'simply-static' );
        $captcha_service = isset( $settings['captcha_service'] ) ? $settings['captcha_service'] : 'turnstile';

        // Only proceed if reCAPTCHA v3 is selected
        if ( $captcha_service !== 'recaptcha_v3' ) {
            return $static_page;
        }

        $site_key = isset( $settings['recaptcha_site_key'] ) ? trim( (string) $settings['recaptcha_site_key'] ) : '';
        $protect  = ! empty( $settings['use_forms'] ) || ! empty( $settings['use_comments'] );

        if ( empty( $site_key ) || ! $protect ) {
            return $static_page;
        }

        // Normalize to DOMDocument
        $dom         = null;
        $html_string = null;

        if ( $static_page instanceof \DOMDocument ) {
            $dom = $static_page;
        } else {
            $html_string = is_string( $static_page ) ? $static_page : '';
            if ( $html_string === '' ) {
                return $static_page;
            }
            $dom = new DOMDocument();
            libxml_use_internal_errors( true );
            $dom->loadHTML( $html_string, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
            libxml_clear_errors();
        }

        $xpath    = new DOMXPath( $dom );
        $modified = false;

        // Supported forms + native comments form (same as Turnstile)
        $form_xpaths = array(
            // CF7
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wpcf7 ")]/descendant::form',
            '//*[contains(concat(" ", normalize-space(@class), " "), " wpcf7-form ")]',
            // Gravity Forms
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " gform_wrapper ")]/descendant::form',
            // WPForms
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wpforms-container ")]/descendant::form',
            // Elementor Forms
            '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-form ")]',
            // WS Form
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wsf-form ")]/descendant::form',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " wsf-form ")]',
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " ws-form ")]/descendant::form',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " ws-form ")]',
            // Fluent Forms
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " frm-fluent-form ")]',
            // Forminator (custom forms)
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " forminator-custom-form ")]/descendant::form',
            '//*[self::form and (contains(concat(" ", normalize-space(@class), " "), " forminator-")
               or starts-with(@id, "forminator-module-") or starts-with(@id, "forminator-form-") )]',
            // Bricks
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " brxe-form ")]',
            '//*[self::form and contains(concat(" ", normalize-space(@class), " "), " brxe-brf-pro-forms ")]',
            // Kadence
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " wp-block-kadence-form ")]/descendant::form',
            // Native comments
            '//*[@id="commentform" and self::form]'
        );

        // Collect unique form nodes
        $forms = array();
        foreach ( $form_xpaths as $expr ) {
            $nodes = $xpath->query( $expr );
            if ( $nodes && $nodes->length ) {
                foreach ( $nodes as $n ) {
                    $forms[ spl_object_hash( $n ) ] = $n;
                }
            }
        }

        if ( empty( $forms ) ) {
            return $static_page;
        }

        foreach ( $forms as $form ) {
            // Skip if the reCAPTCHA widget already exists inside this form
            $existing = ( new DOMXPath( $dom ) )->query( './/*[contains(concat(" ", normalize-space(@class), " "), " g-recaptcha-response ")]', $form );
            if ( $existing && $existing->length ) {
                continue;
            }

            // Also skip if there's already a hidden input for g-recaptcha-response
            $existing_input = ( new DOMXPath( $dom ) )->query( './/input[@name="g-recaptcha-response"]', $form );
            if ( $existing_input && $existing_input->length ) {
                continue;
            }

            // Build hidden input for reCAPTCHA token
            $hidden_input = $dom->createElement( 'input' );
            $hidden_input->setAttribute( 'type', 'hidden' );
            $hidden_input->setAttribute( 'name', 'g-recaptcha-response' );
            $hidden_input->setAttribute( 'class', 'g-recaptcha-response' );
            $hidden_input->setAttribute( 'data-sitekey', $site_key );

            // Add the hidden input to the form
            $form->appendChild( $hidden_input );
            $modified = true;
        }

        if ( ! $modified ) {
            return $static_page;
        }

        // Return in the same type as received
        if ( $static_page instanceof \DOMDocument ) {
            return $dom;
        }

        return $dom->saveHTML();
    }
}
