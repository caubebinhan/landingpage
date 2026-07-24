<?php

namespace simply_static_pro\form\patcher;

use DOMDocument;
use DOMXPath;

/**
 * Gravity Forms patcher for static export.
 * 
 * Strips GF scripts and ensures SSP scripts are present for webhook connections.
 */
class GF_Patcher {

	/**
	 * Check if Gravity Forms is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'GFForms' );
	}

	/**
	 * Patch the HTML for Gravity Forms.
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

		// Only proceed if GF markup exists
		$gfForms = $xpath->query( '//*[@class and contains(@class, "gform_wrapper")]' );
		if ( ! $gfForms || $gfForms->length === 0 ) {
			return $static_page;
		}

		$modified = false;

		// Remove GF scripts by id/src markers
		$scriptExprs = [
			'//script[contains(@id, "gform") or contains(@id, "gravityforms")]',
			'//script[contains(@src, "gform") or contains(@src, "gravityforms") or contains(@src, "gforms") or contains(@src, "gform_recaptcha")]'
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

		// Narrow removal: only inline scripts that look like GF bootstraps
		$inlineNodes = $xpath->query( '//script[not(@src)]' );
		if ( $inlineNodes && $inlineNodes->length ) {
			foreach ( $inlineNodes as $node ) {
				$code = $node->textContent;
				if ( $code ) {
					$lc = strtolower( $code );
					$matches_gf_boot = ( strpos( $lc, 'gform.initializeonloaded' ) !== false )
						|| ( strpos( $lc, 'gforminitdatepicker' ) !== false )
						|| ( strpos( $lc, 'gform.addfilter' ) !== false )
						|| ( strpos( $lc, 'gform.utils' ) !== false )
						|| ( strpos( $lc, 'gf_submitting_' ) !== false );
					if ( $matches_gf_boot ) {
						$node->parentNode->removeChild( $node );
						$modified = true;
					}
				}
			}
		}

		// Remove inline onclick handlers that reference GF globals
		$onclickNodes = $xpath->query( '//*[@onclick and (contains(@onclick, "gform") or contains(@onclick, "gf_submitting_"))]' );
		if ( $onclickNodes && $onclickNodes->length ) {
			foreach ( $onclickNodes as $node ) {
				$node->removeAttribute( 'onclick' );
				$modified = true;
			}
		}

		// Add minimal gform shim to avoid theme/inline references breaking
		$modified = self::add_gform_shim( $dom, $xpath ) || $modified;

		// Ensure SSP scripts present
		$modified = self::ensure_ssp_scripts( $dom, $xpath ) || $modified;

		if ( $modified ) {
			return $dom->saveHTML();
		}

		return $static_page;
	}

	/**
	 * Add a minimal gform shim to prevent JS errors.
	 *
	 * @param DOMDocument $dom The DOM document.
	 * @param DOMXPath $xpath The XPath object.
	 * @return bool Whether any modifications were made.
	 */
	private static function add_gform_shim( $dom, $xpath ) {
		$existingShim = $xpath->query( "//script[@id='ssp-gf-shim']" );
		if ( $existingShim && $existingShim->length > 0 ) {
			return false;
		}

		$shim = $dom->createElement( 'script' );
		$shim->setAttribute( 'id', 'ssp-gf-shim' );
		$shim->setAttribute( 'data-ssp', '1' );
		$shimCode = "(function(){try{window.gform=window.gform||{};\n" .
			"gform.addFilter=gform.addFilter||function(){};gform.removeFilter=gform.removeFilter||function(){};\n" .
			"gform.initializeOnLoaded=gform.initializeOnLoaded||function(){};\n" .
			"gform.utils=gform.utils||{};gform.utils.addAsyncFilter=gform.utils.addAsyncFilter||function(){};gform.utils.removeAsyncFilter=gform.utils.removeAsyncFilter||function(){};\n" .
			"window.gf_global=window.gf_global||{};}catch(e){}})();";
		$shim->appendChild( $dom->createTextNode( $shimCode ) );

		$heads = $dom->getElementsByTagName( 'head' );
		if ( $heads && $heads->length ) {
			$heads->item(0)->appendChild( $shim );
		} else {
			$bodies = $dom->getElementsByTagName( 'body' );
			if ( $bodies && $bodies->length ) {
				$bodies->item(0)->appendChild( $shim );
			} else {
				$htmls = $dom->getElementsByTagName( 'html' );
				if ( $htmls && $htmls->length ) {
					$newHead = $dom->createElement( 'head' );
					$newHead->appendChild( $shim );
					$htmls->item(0)->insertBefore( $newHead, $htmls->item(0)->firstChild );
				} elseif ( $dom->documentElement ) {
					$dom->documentElement->appendChild( $shim );
				} else {
					$dom->appendChild( $shim );
				}
			}
		}

		return true;
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
				} else {
					$htmls = $dom->getElementsByTagName( 'html' );
					if ( $htmls && $htmls->length ) {
						$newHead = $dom->createElement( 'head' );
						$newHead->appendChild( $script );
						$htmls->item(0)->insertBefore( $newHead, $htmls->item(0)->firstChild );
					} elseif ( $dom->documentElement ) {
						$dom->documentElement->appendChild( $script );
					} else {
						$dom->appendChild( $script );
					}
				}
			}
			$modified = true;
		}

		return $modified;
	}
}
