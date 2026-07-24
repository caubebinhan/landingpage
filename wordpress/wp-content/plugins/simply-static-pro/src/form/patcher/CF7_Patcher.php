<?php

namespace simply_static_pro\form\patcher;

use DOMDocument;
use DOMXPath;

/**
 * Contact Form 7 patcher for static export.
 * 
 * Strips CF7 scripts and ensures SSP scripts are present for webhook connections.
 */
class CF7_Patcher {

	/**
	 * Check if CF7 is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' );
	}

	/**
	 * Patch the HTML for Contact Form 7 forms.
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

		// Only proceed if CF7 markup exists
		$cf7Forms = $xpath->query( '//*[@class and contains(@class, "wpcf7")]' );
		if ( ! $cf7Forms || $cf7Forms->length === 0 ) {
			return $static_page;
		}

		$modified = false;

		// Remove ALL CF7 scripts comprehensively (including swv validation modules, type="module", etc.)
		$scriptExprs = [
			'//script[contains(@id, "contact-form-7") or contains(@id, "wpcf7")]',
			'//script[contains(@src, "contact-form-7") or contains(@src, "wpcf7")]',
			'//script[@type="module" and contains(@src, "contact-form-7")]',
			'//script[contains(@src, "/swv/")]',
			'//script[contains(@id, "swv")]',
			'//script[@type="module" and (contains(@src, "wpcf7") or contains(@src, "/includes/js/"))]'
		];
		foreach ( $scriptExprs as $expr ) {
			$nodes = $xpath->query( $expr );
			if ( $nodes && $nodes->length ) {
				foreach ( $nodes as $node ) {
					$node->parentNode->removeChild( $node );
					$modified = true;
				}
			}
		}

		// Remove modulepreload and preload link hints for CF7 scripts (including swv)
		$linkExprs = [
			'//link[@rel="modulepreload" and contains(@href, "contact-form-7")]',
			'//link[@rel="preload" and contains(@href, "contact-form-7")]',
			'//link[@rel="modulepreload" and contains(@href, "/swv/")]',
			'//link[@rel="preload" and contains(@href, "/swv/")]'
		];
		foreach ( $linkExprs as $expr ) {
			$nodes = $xpath->query( $expr );
			if ( $nodes && $nodes->length ) {
				foreach ( $nodes as $node ) {
					$node->parentNode->removeChild( $node );
					$modified = true;
				}
			}
		}

		// Remove inline scripts that reference CF7 bootstraps
		$inlineNodes = $xpath->query( '//script[not(@src)]' );
		if ( $inlineNodes && $inlineNodes->length ) {
			foreach ( $inlineNodes as $node ) {
				$code = $node->textContent;
				if ( $code ) {
					$lc = strtolower( $code );
					$matches_cf7 = ( strpos( $lc, 'wpcf7' ) !== false )
						|| ( strpos( $lc, 'contact-form-7' ) !== false );
					if ( $matches_cf7 ) {
						$node->parentNode->removeChild( $node );
						$modified = true;
					}
				}
			}
		}

		// Remove CF7 form action attribute to prevent any native form submission attempts
		$cf7FormElements = $xpath->query( '//form[contains(@class, "wpcf7-form")]' );
		if ( $cf7FormElements && $cf7FormElements->length ) {
			foreach ( $cf7FormElements as $formEl ) {
				if ( $formEl->hasAttribute( 'action' ) ) {
					$action = $formEl->getAttribute( 'action' );
					if ( strpos( $action, 'contact-form-7' ) !== false || strpos( $action, 'wp-json' ) !== false ) {
						$formEl->removeAttribute( 'action' );
						$modified = true;
					}
				}
			}
		}

		// Ensure SSP scripts present: webhook + validation
		$modified = self::ensure_ssp_scripts( $dom, $xpath ) || $modified;

		if ( $modified ) {
			return $dom->saveHTML();
		}

		return $static_page;
	}

	/**
	 * Ensure SSP scripts are present in the DOM.
	 *
	 * @param DOMDocument $dom The DOM document.
	 * @param DOMXPath $xpath The XPath object.
	 * @return bool Whether any modifications were made.
	 */
	private static function ensure_ssp_scripts( $dom, $xpath ) {
		$modified = false;
		$verNode = $xpath->query( "//meta[@name='ssp-config-version']" );
		$verVal  = ( $verNode && $verNode->length ) ? $verNode->item(0)->getAttribute( 'content' ) : '';

		$scripts = [ 'ssp-form-webhook-public.js', 'ssp-form-validation.js' ];
		foreach ( $scripts as $fname ) {
			$existing = $xpath->query( "//script[contains(@src, '" . $fname . "')]" );
			if ( $existing && $existing->length > 0 ) {
				continue;
			}

			$script = $dom->createElement( 'script' );
			$src    = '/wp-content/plugins/simply-static-pro/assets/' . $fname;
			if ( ! empty( $verVal ) ) {
				$src .= '?ver=' . rawurlencode( (string) $verVal );
			}
			$script->setAttribute( 'src', $src );
			$script->setAttribute( 'data-ssp', '1' );

			$heads = $dom->getElementsByTagName( 'head' );
			if ( $heads && $heads->length ) {
				$heads->item(0)->appendChild( $script );
			} else {
				$bodies = $dom->getElementsByTagName( 'body' );
				if ( $bodies && $bodies->length ) {
					$bodies->item(0)->appendChild( $script );
				}
			}
			$modified = true;
		}

		return $modified;
	}
}
