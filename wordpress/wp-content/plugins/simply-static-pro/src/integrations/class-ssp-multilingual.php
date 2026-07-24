<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use Simply_Static\Options;
use Simply_Static\Url_Fetcher;
use DOMDocument;
use DOMXPath;

class SS_Multilingual extends Integration {

	/**
	 * A string ID of integration.
	 *
	 * @var string
	 */
	protected $id = 'multilingual';

 public function __construct() {
        $this->name = __( 'Multilingual', 'simply-static-pro' );
        $this->description = __( 'Integrates WPML, Polylang and TranslatePress to work with Static WordPress.', 'simply-static-pro' );
    }

	public function dependency_active() {
		return is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ||
		       is_plugin_active( 'polylang/polylang.php' ) ||
		       is_plugin_active( 'polylang-pro/polylang.php' ) ||
		       is_plugin_active( 'translatepress-multilingual/index.php' );
	}

	/**
	 * Run the integration.
	 *
	 * @return void
	 */
	public function run() {
		// Generic multilingual hooks (work with all translation plugins).
		add_action( 'ss_match_tags', array( $this, 'find_translated_pages' ) );

		// WPML-specific hooks and code.
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			add_action( 'ss_after_cleanup', [ $this, 'clear_options' ] );
			add_filter( 'wpml_enqueue_browser_redirect_language', '__return_false' );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_public_scripts' ) );
			add_filter( 'ssp_tasks_before_delivery_methods', [ $this, 'add_wpml_task' ] );
			add_filter( 'simply_static_class_name', array( $this, 'check_class_name' ), 10, 2 );

			require_once 'wpml/class-ssp-wpml-task.php';
		}
	}

	public function clear_options() {
		$options = Options::instance();
		$options->destroy( 'wpml_copied_languages' );
		$options->destroy( 'wpml_processed_languages' );
		$options->save();
	}

	public function check_class_name( $class_name, $task_name ) {

		if ( 'wpml' === $task_name ) {
			return 'simply_static_pro\\WPML_Task';
		}

		return $class_name;
	}

	public function add_wpml_task( $tasks ) {
		if ( ! apply_filters( 'ssp_copy_directories_for_wpml', false ) ) {
			return $tasks;
		}

		$tasks[] = 'wpml';
		return $tasks;
	}

	/**
	 * Add translations from meta tags.
	 *
	 * @param array $match_tags list of matching tags for extraction.
	 *
	 * @return array
	 */
	public function find_translated_pages( array $match_tags ): array {
		$match_tags['link'] = array( 'href' );

		return $match_tags;
	}

	/**
	 * Enqueue scripts for geo redirects.
	 *
	 * @return void
	 */
	public function add_public_scripts() {
		$use_geo_redirect = apply_filters( 'ssp_use_geo_redirect', false );

		if ( $use_geo_redirect ) {
			wp_enqueue_script( 'ssp-wpml-geo', SIMPLY_STATIC_PRO_URL . '/assets/ssp-wpml-geo.js', array( 'jquery' ), SIMPLY_STATIC_PRO_VERSION, true );
		}
	}

	/**
	 * Get related translations of a page.
	 *
	 * @param int $single_id single post id.
	 *
	 * @return array
	 */
	public static function get_related_translations( int $single_id ): array {
		$related_translations = array();

		$response = Url_Fetcher::remote_get( get_permalink( $single_id ) );
		$html     = wp_remote_retrieve_body( $response );

		// Check if HTML content is empty
		if ( empty( $html ) ) {
			return $related_translations;
		}

		// Use PHP's native DOMDocument
		$dom = new DOMDocument();

		// Suppress errors from malformed HTML
		libxml_use_internal_errors( true );

		// Load the HTML
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		// Clear any errors
		libxml_clear_errors();

		// Create a DOMXPath object to query the DOM
		$xpath = new DOMXPath( $dom );

		// Find all link tags
		$link_tags = $xpath->query( '//link[@hreflang]' );

		if ( $link_tags ) {
			foreach ( $link_tags as $tag ) {
				if ( $tag->hasAttribute( 'hreflang' ) ) {
					$href = $tag->getAttribute( 'href' );
					$hreflang = $tag->getAttribute( 'hreflang' );

					if ( get_permalink( $single_id ) === $href && 'x-default' !== $hreflang ) {
						$related_translations[] = $href;
					}
				}
			}
		}

		return $related_translations;
	}

}
