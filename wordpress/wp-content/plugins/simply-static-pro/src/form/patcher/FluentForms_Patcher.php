<?php

namespace simply_static_pro\form\patcher;

use DOMDocument;
use DOMXPath;

/**
 * Fluent Forms patcher for static export.
 * 
 * Strips Fluent Forms scripts for webhook connections.
 */
class FluentForms_Patcher {

	/**
	 * Check if Fluent Forms is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return is_plugin_active( 'fluentform/fluentform.php' );
	}

	/**
	 * Patch the HTML for Fluent Forms.
	 *
	 * @param string $static_page The HTML content.
	 * @return string The patched HTML content.
	 */
	public static function patch( $static_page ) {
		$html_content = is_string( $static_page ) ? $static_page : $static_page->saveHTML();
		if ( empty( $html_content ) ) {
			return $static_page;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		$removed = false;

		// Remove by exact IDs commonly used by Fluent Forms
		$targets = [
			'//script[@id="fluent-form-submission-js-extra"]',
			'//script[@id="fluent-form-submission-js"]',
			'//script[contains(@id, "fluentform")]',
			'//script[contains(@id, "fluent-form")]'
		];
		foreach ( $targets as $expr ) {
			$nodes = $xpath->query( $expr );
			if ( $nodes && $nodes->length > 0 ) {
				foreach ( $nodes as $node ) {
					$node->parentNode->removeChild( $node );
					$removed = true;
				}
			}
		}

		// Also remove any script tags that load Fluent Forms submission/init JS via src
		$srcNodes = $xpath->query( '//script[contains(@src, "fluentform") or contains(@src, "fluent-forms")]' );
		if ( $srcNodes && $srcNodes->length > 0 ) {
			foreach ( $srcNodes as $node ) {
				$node->parentNode->removeChild( $node );
				$removed = true;
			}
		}

		// Remove inline scripts that reference Fluent Forms bootstraps or admin-ajax
		$inlineNodes = $xpath->query( '//script[not(@src)]' );
		if ( $inlineNodes && $inlineNodes->length > 0 ) {
			foreach ( $inlineNodes as $node ) {
				$code = $node->textContent;
				if ( $code && ( stripos( $code, 'fluentform' ) !== false || stripos( $code, 'fluent-forms' ) !== false || stripos( $code, 'FluentForms' ) !== false || stripos( $code, 'admin-ajax.php' ) !== false ) ) {
					$node->parentNode->removeChild( $node );
					$removed = true;
				}
			}
		}

		if ( $removed ) {
			return $dom->saveHTML();
		}

		return $static_page;
	}
}
