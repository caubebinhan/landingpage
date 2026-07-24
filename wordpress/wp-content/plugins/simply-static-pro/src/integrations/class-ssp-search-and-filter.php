<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use DOMDocument;
use DOMXPath;

class SearchAndFilter_Integration extends Integration {

	/**
	 * Given plugin handler ID.
	 *
	 * @var string Handler ID.
	 */
	protected $id = 'search-and-filter';

	public function __construct() {
		$this->name        = __( 'Search and Filter', 'simply-static-pro' );
		$this->description = __( 'Integrates the popular Search and Filter plugin to be used on static sites.', 'simply-static-pro' );
	}

	/**
	 * @var null|\Simply_Static\Url_Extractor
	 */
	protected $extractor = null;

	/**
	 * Return if the dependency is active.
	 *
	 * @return boolean
	 */
	public function dependency_active() {
		return class_exists( 'Search_Filter' );
	}

	/**
	 * Run the integration.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'search-filter/frontend/front_url', array( $this, 'pass_static_url' ) );
		add_action( 'ss_dom_before_save', array( $this, 'patch_form_html' ), 20, 2 );
	}

	public function pass_static_url( $url ) {
		// Get static site URL from settings.
		$options = get_option( 'simply-static' );

		if ( $options['static_url'] ) {
			return untrailingslashit( $options['static_url'] );
		}

		return $url;
	}

	/**
	 * Replace HTML within DOM.
	 *
	 * @param string $static_page given HTML string.
	 * @param string $url given static URL.
	 *
	 * @return mixed
	 */
	public function patch_form_html( $static_page, $url ) {
		// Search and Filter compatibility.
		if ( is_plugin_active( 'search-filter/search-filter.php' ) ) {
			$options = get_option( 'simply-static' );
			$wp_url  = untrailingslashit( home_url() );

			// Check for Basic Auth.
			if ( ! empty( $options['http_basic_auth_username'] ) && ! empty( $options['http_basic_auth_password'] ) ) {
				$url_parts = parse_url( $wp_url );
				$wp_url    = $url_parts['scheme'] . '://' . $options['http_basic_auth_username'] . ':' . $options['http_basic_auth_password'] . '@' . $url_parts['host'];
			}

			// Get HTML content from DOM object if it's not already a string
			$html_content = is_string( $static_page ) ? $static_page : $static_page->saveHTML();

			// Check if HTML content is empty
			if ( empty( $html_content ) ) {
				return $static_page;
			}

			// Use PHP's native DOMDocument
			$dom = new DOMDocument();

			// Suppress errors from malformed HTML
			libxml_use_internal_errors( true );

			// Load the HTML
			$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

			// Clear any errors
			libxml_clear_errors();

			// Create a DOMXPath object to query the DOM
			$xpath = new DOMXPath( $dom );

			// Find the script tag with id="search-filter-api-url-js"
			$script_tags = $xpath->query( '//script[@id="search-filter-api-url-js"]' );

			if ( $script_tags && $script_tags->length > 0 ) {
				$script_tag = $script_tags->item(0);

				// Create a new script tag
				$new_script = $dom->createElement( 'script' );
				$new_script->setAttribute( 'id', 'search-filter-api-url-js' );
				$new_script->textContent = "window.searchAndFilterApiUrl = '" . $wp_url . "';";

				// Replace the old script tag with the new one
				$script_tag->parentNode->replaceChild( $new_script, $script_tag );

				// Get the updated HTML
				$updated_html = $dom->saveHTML();

				return $updated_html;
			}

			// If we couldn't find the script tag, return the original DOM
			return $static_page;
		}

		return $static_page;
	}
}
