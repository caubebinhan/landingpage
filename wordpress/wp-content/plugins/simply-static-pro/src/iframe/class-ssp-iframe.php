<?php

namespace simply_static_pro;

use Simply_Static\Util;
use DOMDocument;
use DOMXPath;

/**
 * Class to handle iframe embeds.
 */
class Iframe {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Iframe.
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
	 * Constructor for Iframe.
	 */
	public function __construct() {
		add_action( 'ss_dom_before_save', array( $this, 'iframe_urls' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'iframe_custom_css' ), 10, 2 );
		add_action( 'ss_dom_before_save', array( $this, 'iframe_forms' ), 20, 2 );
		add_action( 'ss_dom_before_save', array( $this, 'add_preconnect_hints' ), 5, 2 );
	}

	/**
	 * Add preconnect and dns-prefetch hints for the WordPress domain.
	 *
	 * @param string|object $static_page given HTML string or DOMDocument.
	 * @param string        $url given static URL.
	 *
	 * @return mixed
	 */
	public function add_preconnect_hints( $static_page, $url ) {
		$options = get_option( 'simply-static' );

		// Only if forms or iframe URLs are used.
		if ( ! $options['use_forms'] && empty( $options['iframe_urls'] ) ) {
			return $static_page;
		}

		$wp_domain = home_url();

		if ( empty( $wp_domain ) ) {
			return $static_page;
		}

		// Get HTML content
		$html_content = is_string( $static_page ) ? $static_page : $static_page->saveHTML();

		if ( empty( $html_content ) ) {
			return $static_page;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$head = $dom->getElementsByTagName( 'head' )->item( 0 );

		if ( $head ) {
			// Preconnect
			$preconnect = $dom->createElement( 'link' );
			$preconnect->setAttribute( 'rel', 'preconnect' );
			$preconnect->setAttribute( 'href', $wp_domain );
			$preconnect->setAttribute( 'crossorigin', '' );
			$head->appendChild( $preconnect );

			// DNS Prefetch
			$dns_prefetch = $dom->createElement( 'link' );
			$dns_prefetch->setAttribute( 'rel', 'dns-prefetch' );
			$dns_prefetch->setAttribute( 'href', $wp_domain );
			$head->appendChild( $dns_prefetch );

			return $dom->saveHTML();
		}

		return $static_page;
	}

	/**
	 * Replace entire pages with iframe embeds.
	 *
	 * @param object $static_page given HTML string.
	 * @param string $url given static URL.
	 *
	 * @return mixed
	 */
	public function iframe_urls( $static_page, $url ) {
		// Get list of URls to proxy.
		$options = get_option( 'simply-static' );

		if ( ! empty( $options['iframe_urls'] ) ) {
			$urls = array_unique( Util::string_to_array( $options['iframe_urls'] ) );

			if ( in_array( $url, $urls ) ) {
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

				// Find the body tag
				$body = $dom->getElementsByTagName('body')->item(0);

				if ( $body ) {
					// Remove all child nodes from the body
					while ( $body->hasChildNodes() ) {
						$body->removeChild( $body->firstChild );
					}

     // Create a new iframe element
     $iframe = $dom->createElement( 'iframe' );
     $iframe->setAttribute( 'loading', 'lazy' );
     $iframe->setAttribute( 'fetchpriority', 'low' );
     $iframe->setAttribute( 'src', esc_url( $url ) );
     // Allow scrolling for larger embeds
     $iframe->setAttribute( 'scrolling', 'yes' );
     $iframe->setAttribute( 'style', 'width:100%;height:100vh;border:none;' );

     // Harden the iframe to isolate scripts/styles from the host page
     $iframe->setAttribute( 'sandbox', 'allow-forms allow-scripts allow-same-origin allow-popups' );
     $iframe->setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
     $iframe->setAttribute( 'title', 'Embedded form' );

     // Append the iframe to the body
     $body->appendChild( $iframe );

					// Get the updated HTML
					$updated_html = $dom->saveHTML();

					return $updated_html;
				}

				// If we couldn't find the body tag, return the original DOM
				return $static_page;
			}
		}

		return $static_page;
	}

	/**
	 * Add custom CSS to iframe embeds.
	 */
	public function iframe_custom_css() {
		// Get list of URls to proxy.
		$options = get_option( 'simply-static' );

		if ( empty( $options['iframe_urls'] ) || empty( $options['iframe_custom_css'] ) ) {
			return;
		}

		// Check if current page should be embedded as iframe.
		$iframe_urls = array_unique( Util::string_to_array( $options['iframe_urls'] ) );
		$current_url = get_permalink( get_the_ID() );

		if ( ! in_array( $current_url, $iframe_urls ) ) {
			return;
		}

		// Add custom css.
		?>
        <style>
            <?php echo $options['iframe_custom_css']; ?>
        </style>
		<?php
	}

	/**
	 * Replace forms with iframe embeds.
	 *
	 * @param string $static_page given HTML string.
	 * @param string $url given static URL.
	 *
	 * @return mixed
	 */
	public function iframe_forms( $static_page, $url ) {
		$options = get_option( 'simply-static' );

		// Skip if forms not used.
		if ( ! $options['use_forms'] ) {
			return $static_page;
		}

		// Get list of form integrations.
		$args      = array( 'numberposts' => - 1, 'post_type' => 'ssp-form', 'fields' => 'ids' );
  $ssp_forms = get_posts( $args );
  $forms     = array();

  if ( ! empty( $ssp_forms ) ) {
      foreach ( $ssp_forms as $form_id ) {
          $form              = new \stdClass();
          $form->form_type   = get_post_meta( $form_id, 'form_type', true );
          $form->form_plugin = get_post_meta( $form_id, 'form_plugin', true );
          $form->form_id     = get_post_meta( $form_id, 'form_id', true );
          $form->link        = get_permalink( $form_id );
          $form->post_id     = (int) $form_id;

				if ( $form->form_type === 'embedded' && ! empty( $form->form_id ) ) {
					$forms[] = $form;
				}
			}
		}

  if ( ! empty( $forms ) ) {
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

    // Track if we made any changes
    $changes_made = false;
    $host_script_injected = false;
    $max_px_min_height = 0; // track the largest configured minHeight in px among forms on this page

   foreach ( $forms as $form ) {
       // Build iframe id and src with id for responsive sizing
       $iframe_id   = 'ssp-form-' . ( isset( $form->post_id ) ? (int) $form->post_id : wp_rand( 1000, 9999 ) );
       $src_with_id = add_query_arg( 'ssp_iframe_id', rawurlencode( $iframe_id ), $form->link );
       // Read optional manual height and unit from form meta; if provided, use it as min/initial height
       $configured_height     = 0;
       $configured_height_unit = 'px';
       if ( isset( $form->post_id ) ) {
           $configured_height = (int) get_post_meta( (int) $form->post_id, 'form_height', true );
           $configured_height_unit = get_post_meta( (int) $form->post_id, 'form_height_unit', true );
           // sanitize unit against allowed set (limit to px, %, vh)
           $allowed_units = apply_filters( 'ssp_forms_allowed_height_units', array( 'px', '%', 'vh' ) );
           if ( ! in_array( $configured_height_unit, (array) $allowed_units, true ) ) {
               $configured_height_unit = 'px';
           }
           if ( $configured_height > 0 && 'px' === $configured_height_unit ) {
               $max_px_min_height = max( $max_px_min_height, $configured_height );
           }
       }
       $baseline_min_css = ( $configured_height > 0 ) ? ( (int) $configured_height . $configured_height_unit ) : '360px';

       // First, try to find a div with the form ID that has a form as a sibling
       $div_with_form = $xpath->query( '//div[@id="' . $form->form_id . '"]/following-sibling::form' );

				if ( $div_with_form && $div_with_form->length > 0 ) {
					// Get the div element
					$div = $xpath->query( '//div[@id="' . $form->form_id . '"]' )->item(0);

     if ( $div ) {
                        // Create a new iframe element
                        $iframe = $dom->createElement( 'iframe' );
                        $iframe->setAttribute( 'loading', 'lazy' );
                        $iframe->setAttribute( 'fetchpriority', 'low' );
                        $iframe->setAttribute( 'id', $iframe_id );
                        $iframe->setAttribute( 'src', esc_url( $src_with_id ) );
                        // Allow scrolling inside the iframe
                        $iframe->setAttribute( 'scrolling', 'yes' );
                        $iframe->setAttribute( 'style', 'display:block;width:100%;min-height:' . $baseline_min_css . ';height:' . $baseline_min_css . ';border:0;overflow:hidden;' );
                        // Isolate the iframe from host scripts/styles
                        $iframe->setAttribute( 'sandbox', 'allow-forms allow-scripts allow-same-origin allow-popups' );
                        $iframe->setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
                        $iframe->setAttribute( 'title', 'Embedded form' );

                        // Determine best replacement node: prefer the closest GF wrapper ancestor
                        $replacement_target = $div;
                        $ancestor = $div->parentNode;
                        $levels  = 0;
                        while ( $ancestor && $levels < 6 ) {
                            if ( $ancestor->nodeType === XML_ELEMENT_NODE ) {
                                $classAttr = $ancestor->attributes && $ancestor->attributes->getNamedItem('class') ? $ancestor->attributes->getNamedItem('class')->nodeValue : '';
                                if ( $classAttr && strpos( ' ' . $classAttr . ' ', ' gform_wrapper ' ) !== false ) {
                                    $replacement_target = $ancestor; break;
                                }
                            }
                            $ancestor = $ancestor->parentNode; $levels++;
                        }
                        // Replace target with iframe
                        $replacement_target->parentNode->replaceChild( $iframe, $replacement_target );

						// Also remove the form that follows
						$form_element = $div_with_form->item(0);
						if ( $form_element ) {
							$form_element->parentNode->removeChild( $form_element );
						}

						$changes_made = true;
					}
    } else {
                    // Try to find any element with the form ID
                    $element_with_id = $xpath->query( '//*[@id="' . $form->form_id . '"]' );

                    if ( $element_with_id && $element_with_id->length > 0 ) {
                        $element = $element_with_id->item(0);
                        $tag_name = $element->nodeName;

      // Create a new iframe element
      $iframe = $dom->createElement( 'iframe' );
      $iframe->setAttribute( 'loading', 'lazy' );
      $iframe->setAttribute( 'fetchpriority', 'low' );
      $iframe->setAttribute( 'id', $iframe_id );
      $iframe->setAttribute( 'src', esc_url( $src_with_id ) );
      // Allow scrolling inside the iframe
      $iframe->setAttribute( 'scrolling', 'yes' );
      $iframe->setAttribute( 'style', 'display:block;width:100%;min-height:' . $baseline_min_css . ';height:' . $baseline_min_css . ';border:0;overflow:hidden;' );
      $iframe->setAttribute( 'sandbox', 'allow-forms allow-scripts allow-same-origin allow-popups' );
      $iframe->setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
      $iframe->setAttribute( 'title', 'Embedded form' );

      // Replace the element or its closest GF wrapper ancestor with the iframe
      $replacement_target = $element;
      $ancestor = $element->parentNode; $levels = 0;
      while ( $ancestor && $levels < 6 ) {
        if ( $ancestor->nodeType === XML_ELEMENT_NODE ) {
            $classAttr = $ancestor->attributes && $ancestor->attributes->getNamedItem('class') ? $ancestor->attributes->getNamedItem('class')->nodeValue : '';
            if ( $classAttr && strpos( ' ' . $classAttr . ' ', ' gform_wrapper ' ) !== false ) {
                $replacement_target = $ancestor; break;
            }
        }
        $ancestor = $ancestor->parentNode; $levels++;
      }
      $replacement_target->parentNode->replaceChild( $iframe, $replacement_target );

						// If the element is not a form, also look for a form that follows it
						if ( $tag_name !== 'form' ) {
							$following_form = $xpath->query( '//following-sibling::form', $element );
							if ( $following_form && $following_form->length > 0 ) {
								$form_element = $following_form->item(0);
								if ( $form_element ) {
									$form_element->parentNode->removeChild( $form_element );
								}
							}
						}

						$changes_made = true;
					}
                }
            }

            // Direct replacement by Gravity Forms wrapper id if present and not already replaced
            // e.g., id="gform_wrapper_16" derived from GF form id
            $gf_wrapper_id = 'gform_wrapper_' . $form->form_id;
            $gf_wrapper_nodes = $xpath->query( '//*[@id="' . $gf_wrapper_id . '"]' );
            if ( $gf_wrapper_nodes && $gf_wrapper_nodes->length > 0 ) {
                $wrapper = $gf_wrapper_nodes->item(0);
                // Create iframe if we haven't already for this context
                $iframe = $dom->createElement( 'iframe' );
                $iframe->setAttribute( 'loading', 'lazy' );
                $iframe->setAttribute( 'fetchpriority', 'low' );
                $iframe->setAttribute( 'id', $iframe_id );
                $iframe->setAttribute( 'src', esc_url( $src_with_id ) );
                $iframe->setAttribute( 'scrolling', 'yes' );
                $iframe->setAttribute( 'style', 'display:block;width:100%;min-height:' . $baseline_min_css . ';height:' . $baseline_min_css . ';border:0;overflow:hidden;' );
                $iframe->setAttribute( 'sandbox', 'allow-forms allow-scripts allow-same-origin allow-popups' );
                $iframe->setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
                $iframe->setAttribute( 'title', 'Embedded form' );

                $wrapper->parentNode->replaceChild( $iframe, $wrapper );
                $changes_made = true;
            }

            if ( $changes_made ) {
                // Remove form plugin assets from the host (static) page to avoid JS errors and duplicate inits.
                // IMPORTANT: Limit scope here to Fluent Forms only. Gravity Forms cleanup is handled in Form_Patcher
                // to avoid double-removal that might strip unrelated scripts.
                try {
                    // Remove external script tags that reference Fluent Forms assets
                    $script_nodes = $xpath->query( '//script[contains(@src, "fluentform") or contains(@src, "fluent-forms") or contains(@src, "fluentforms") or contains(@src, "form-submission.js")]' );
                    if ( $script_nodes && $script_nodes->length ) {
                        for ( $i = $script_nodes->length - 1; $i >= 0; $i-- ) {
                            $node = $script_nodes->item( $i );
                            if ( $node && $node->parentNode ) {
                                $node->parentNode->removeChild( $node );
                            }
                        }
                    }

                    // Remove inline scripts that mention Fluent Forms globals
                    $inline_scripts = $xpath->query( '//script[not(@src) and (contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "fluentform") or contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "wpfluentform"))]' );
                    if ( $inline_scripts && $inline_scripts->length ) {
                        for ( $i = $inline_scripts->length - 1; $i >= 0; $i-- ) {
                            $node = $inline_scripts->item( $i );
                            if ( $node && $node->parentNode ) {
                                $node->parentNode->removeChild( $node );
                            }
                        }
                    }

                    // Remove related CSS links to avoid redundant styles in the host page (Fluent Forms only)
                    $css_nodes = $xpath->query( '//link[contains(@href, "fluentform") or contains(@href, "fluent-forms") or contains(@href, "fluentforms")]' );
                    if ( $css_nodes && $css_nodes->length ) {
                        for ( $i = $css_nodes->length - 1; $i >= 0; $i-- ) {
                            $node = $css_nodes->item( $i );
                            if ( $node && $node->parentNode ) {
                                $node->parentNode->removeChild( $node );
                            }
                        }
                    }
                } catch ( \Throwable $e ) {
                    // Fail silently; asset removal is a best-effort cleanup
                }

                // Inject host auto-resize script inline once, at the end of body or document
                if ( ! $host_script_injected ) {
                    $script_code = \file_get_contents( SIMPLY_STATIC_PRO_PATH . '/assets/ssp-embed/ssp-iframe-host.min.js' );
                    if ( false !== $script_code ) {
                        // Init with WP origin (from the first form link)
                        $first_form = reset( $forms );
                        $origin = '';
                        if ( $first_form && ! empty( $first_form->link ) ) {
                            $parts = wp_parse_url( $first_form->link );
                            if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
                                $origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
                            }
                        }
                        $init_opts = array(
                            'checkOrigin' => $origin ? $origin : '*',
                        );
                        if ( $max_px_min_height > 0 ) {
                            // Pass the max configured minHeight (in px only) to ensure iframes start tall enough
                            $init_opts['minHeight'] = (int) $max_px_min_height;
                        }
                        // Build JS init object literal safely
                        $parts = array();
                        $parts[] = "checkOrigin: '" . esc_js( $init_opts['checkOrigin'] ) . "'";
                        if ( isset( $init_opts['minHeight'] ) ) {
                            $parts[] = 'minHeight: ' . (int) $init_opts['minHeight'];
                        }
                        $init_code = 'window.SSP_EMBED && window.SSP_EMBED.init({ ' . implode( ', ', $parts ) . ' });';
                        
                        // Use a single <script> element that contains both the host library and the init snippet
                        // to avoid environments that might strip or reorder subsequent inline scripts.
                        // Use createTextNode to avoid any HTML entity transformation (e.g., preserving '&&').
                        $host_script = $dom->createElement( 'script' );
                        // Concatenate the host library and the init call without a leading semicolon
                        // to keep the injected markup clean and avoid validator warnings.
                        $host_script->appendChild( $dom->createTextNode( $script_code . "\n" . $init_code ) );
                        $host_script->setAttribute( 'type', 'text/javascript' );

                        // Append before closing body if present, else to document
                        $bodies = $dom->getElementsByTagName( 'body' );
                        if ( $bodies->length > 0 ) {
                            $body = $bodies->item(0);
                            $body->appendChild( $host_script );
                        } else {
                            $dom->appendChild( $host_script );
                        }
                    }
                }

                // Get the updated HTML
                $updated_html = $dom->saveHTML();
                return $updated_html;
            }
        }

		return $static_page;
	}
}
