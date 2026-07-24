<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use Simply_Static\Options;
use Simply_Static\Util;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Pro: The Events Calendar (TEC) Integration
 *
 * Moves TEC-specific URL normalization (semantic list URLs) out of the main plugin file
 * and into a dedicated integration that only runs when TEC is active.
 */
class The_Events_Calendar_Integration extends Integration {
	/**
	 * Integration ID.
	 * @var string
	 */
	protected $id = 'the-events-calendar';

	public function __construct() {
		$this->name = __( 'The Events Calendar', 'simply-static-pro' );
		$this->description = __( 'Support for events, archives, and assets from The Events Calendar.', 'simply-static-pro' );
		$this->active_by_default = true;

		add_action( 'admin_init', [ $this, 'maybe_activate_integration' ] );
		add_action( 'admin_init', [ $this, 'apply_default_tec_options' ] );
	}

	/**
	 * Run the integration logic: add URL normalization filters.
	 * Only executed when the integration is active.
	 */
	public function run() {
		add_filter( 'simply_static_pre_converted_url', [ $this, 'normalize_url' ], 10, 3 );
		add_filter( 'simply_static_converted_url', [ $this, 'normalize_url' ], 10, 3 );
		add_action( 'ss_dom_before_save', [ $this, 'patch_tec_dom' ], 10, 2 );
	}

	/**
	 * Apply recommended default TEC options for static sites:
	 * - disable Day view
	 * - disable the search/events bar
	 *
	 * Runs on admin_init when the dependency is active and integration can run.
	 */
	public function apply_default_tec_options() {
		// Ensure TEC is present and integration is active before touching options.
		if ( ! $this->dependency_active() || ! $this->is_active() ) {
			return;
		}

		// We rely on TEC helper functions when available; bail if not.
		if ( ! function_exists( 'tribe_get_option' ) || ! function_exists( 'tribe_update_option' ) ) {
			return;
		}

		// 1) Disable Day view by removing it from the enabled views list.
		$enabled_views = tribe_get_option( 'tribeEnableViews', [] );
		if ( is_array( $enabled_views ) && in_array( 'day', $enabled_views, true ) ) {
			$enabled_views = array_values( array_diff( $enabled_views, [ 'day' ] ) );
			tribe_update_option( 'tribeEnableViews', $enabled_views );
			Util::debug_log( 'TEC Integration: removed "day" from tribeEnableViews.' );
		}

		// 2) Disable the Events Bar (search).
		$disable_bar = (bool) tribe_get_option( 'tribeDisableTribeBar', false );
		if ( ! $disable_bar ) {
			tribe_update_option( 'tribeDisableTribeBar', true );
			Util::debug_log( 'TEC Integration: set tribeDisableTribeBar to true.' );
		}
	}

	/**
	 * Patch TEC front-end markup before saving static HTML.
	 *
	 * - Remove top bar elements not useful on static sites.
	 * - Ensure view selector items link to normalized, static-friendly URLs.
	 * - Add link into the hidden prev label span to point to normalized prev URL.
	 *
	 * @param string|\DOMDocument $static_page
	 * @param string               $url
	 * @return string|\DOMDocument
	 */
	public function patch_tec_dom( $static_page, $url ) {
		// Fast bail if HTML is empty or not a string/document
		$html = is_string( $static_page ) ? $static_page : ( $static_page instanceof \DOMDocument ? $static_page->saveHTML() : '' );
		if ( '' === trim( (string) $html ) ) {
			return $static_page;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );

		$changed = false;

		// 1) Remove TEC top bar elements
		$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-top-bar ") or contains(concat(" ", normalize-space(@class), " "), " tribe-events-header__top-bar ")]' );
		if ( $nodes && $nodes->length ) {
			foreach ( $nodes as $n ) {
				if ( $n->parentNode ) {
					$n->parentNode->removeChild( $n );
					$changed = true;
				}
			}
		}

		// 1b) Remove bottom navigation wrapper (pagination under list view)
		// Remove any element that contains the TEC bottom list navigation class.
		$bottom_navs = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " tribe-events-calendar-list-nav ")]' );
		if ( $bottom_navs && $bottom_navs->length ) {
			foreach ( $bottom_navs as $bn ) {
				if ( $bn->parentNode ) {
					$bn->parentNode->removeChild( $bn );
					$changed = true;
				}
			}
		}

		// Helper to normalize hrefs using existing filters
		$normalize = function ( $href ) use ( $static_page ) {
			if ( ! is_string( $href ) || '' === $href ) {
				return $href;
			}
			// Use Simply Static conversion filter to keep consistency with other URL rules
			return apply_filters( 'simply_static_converted_url', $href, $static_page, null );
		};

		// 2) View selector entries: ensure clickable anchors with normalized URLs
		$view_items = $xpath->query( '//ul[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-view-selector__list ")]/li' );
		if ( $view_items && $view_items->length ) {
			foreach ( $view_items as $li ) {
				// Find existing link/span element that acts as the clickable item
				$link = null;
				foreach ( [ 'a', 'span' ] as $tag ) {
					$found = $xpath->query( './/' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-view-selector__list-item-link ")]', $li );
					if ( $found && $found->length ) { $link = $found->item(0); break; }
				}
				if ( ! $link ) { continue; }

				$href = '';
				if ( 'a' === strtolower( $link->nodeName ) && $link->hasAttribute( 'href' ) ) {
					$href = $link->getAttribute( 'href' );
				} elseif ( $link->hasAttribute( 'data-url' ) ) {
					$href = $link->getAttribute( 'data-url' );
				} elseif ( $li->hasAttribute( 'data-url' ) ) {
					$href = $li->getAttribute( 'data-url' );
				}
				if ( $href ) {
					$href = $normalize( $href );
				}

				// If it's a span, replace it with an anchor to the normalized URL
				if ( 'span' === strtolower( $link->nodeName ) ) {
					$anchor = $dom->createElement( 'a' );
					// copy classes
					if ( $link->hasAttribute( 'class' ) ) { $anchor->setAttribute( 'class', $link->getAttribute( 'class' ) ); }
					if ( $href ) { $anchor->setAttribute( 'href', $href ); }
					// move children
					while ( $link->firstChild ) { $anchor->appendChild( $link->firstChild ); }
					$link->parentNode->replaceChild( $anchor, $link );
					$changed = true;
				} elseif ( 'a' === strtolower( $link->nodeName ) && $href ) {
					// Update href to normalized
					if ( $link->getAttribute( 'href' ) !== $href ) {
						$link->setAttribute( 'href', $href );
						$changed = true;
					}
				}
			}
		}

		// 3) Prev label span should contain a link to normalized prev URL
		$prev_spans = $xpath->query( '//span[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-nav__prev-label-plural ") and contains(concat(" ", normalize-space(@class), " "), " tribe-common-a11y-visual-hide ")]' );
		if ( $prev_spans && $prev_spans->length ) {
			foreach ( $prev_spans as $span ) {
				// Find closest preceding or ancestor anchor with prev link
				$parent_li = $span->parentNode;
				while ( $parent_li && 'li' !== strtolower( $parent_li->nodeName ) ) {
					$parent_li = $parent_li->parentNode;
				}
				$prev_link = null;
				if ( $parent_li ) {
					$found = $xpath->query( './/a[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-nav__prev ")]', $parent_li );
					if ( $found && $found->length ) { $prev_link = $found->item(0); }
				}
				if ( ! $prev_link ) {
					// fallback: search up to nav container
					$nav = $span->parentNode;
					while ( $nav && ! ( $nav->hasAttribute('class') && false !== strpos( ' ' . $nav->getAttribute('class') . ' ', ' tribe-events-c-nav ' ) ) ) {
						$nav = $nav->parentNode;
					}
					if ( $nav ) {
						$found = $xpath->query( './/a[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-nav__prev ")]', $nav );
						if ( $found && $found->length ) { $prev_link = $found->item(0); }
					}
				}
				if ( $prev_link && $prev_link->hasAttribute( 'href' ) ) {
					$href = $normalize( $prev_link->getAttribute( 'href' ) );
					if ( $href ) {
						// Insert an anchor inside the span (keeping the span for a11y class semantics)
						// Preserve original text if present
						$text = trim( $span->textContent );
						while ( $span->firstChild ) { $span->removeChild( $span->firstChild ); }
						$anchor = $dom->createElement( 'a', $text !== '' ? $text : 'Events' );
						$anchor->setAttribute( 'href', $href );
						$span->appendChild( $anchor );
						$changed = true;
					}
				}
			}
		}

		// 4) Remove TEC view-manager scripts that hijack links on static pages
		$scripts = $xpath->query( '//script[contains(@src, "/the-events-calendar/build/js/views/") or contains(@id, "tribe-events-views-v2")]' );
		if ( $scripts && $scripts->length ) {
			foreach ( $scripts as $s ) {
				if ( $s->parentNode ) { $s->parentNode->removeChild( $s ); $changed = true; }
			}
		}

		// 5) Strip JS-targeting data attributes from view selector links to avoid JS hooks
		$selector_links = $xpath->query( '//a[contains(concat(" ", normalize-space(@class), " "), " tribe-events-c-view-selector__list-item-link ")]' );
		if ( $selector_links && $selector_links->length ) {
			$attrs_to_remove = [ 'data-js', 'data-view', 'data-tribe-query', 'data-tribe' ];
			foreach ( $selector_links as $a ) {
				foreach ( $attrs_to_remove as $attr ) { if ( $a->hasAttribute( $attr ) ) { $a->removeAttribute( $attr ); $changed = true; } }
			}
		}

		if ( ! $changed ) {
			return $static_page;
		}

		return $dom->saveHTML();
	}

	/**
	 * Dependency check: detect The Events Calendar presence.
	 *
	 * @return bool
	 */
	public function dependency_active() {
		return class_exists( '\\Tribe\\Events\\Pro' )
			|| class_exists( '\\Tribe__Events__Main' )
			|| function_exists( 'tribe_get_events_link' )
			|| post_type_exists( 'tribe_events' );
	}

	/**
	 * Auto-activate the integration in saved settings when TEC is present.
	 * Does not override user settings if integrations array is not set.
	 */
	public function maybe_activate_integration() {
		if ( ! $this->dependency_active() ) {
			return;
		}
		$options      = Options::instance();
		$integrations = $options->get( 'integrations' );
		if ( is_array( $integrations ) && ! in_array( $this->id, $integrations, true ) ) {
			$integrations[] = $this->id;
			$options->set( 'integrations', array_values( array_unique( $integrations ) ) );
			$options->save();
			Util::debug_log( 'TEC Integration auto-activated due to active dependency.' );
			// Apply recommended defaults when we enable the integration automatically.
			$this->apply_default_tec_options();
		}
	}

	/**
	 * Normalize TEC list URLs to semantic form.
	 *
	 * Examples:
	 * - /events/?tribe_event_display=list&tribe_paged=4 -> /events/list/page/4/
	 * - /events/list/?eventDisplay=past -> /events/list/past
	 *
	 * @param string $url
	 * @param mixed  $static_page
	 * @param mixed  $extractor
	 * @return string
	 */
	public function normalize_url( $url, $static_page = null, $extractor = null ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return $url;
		}

		// Only normalize same-site URLs
		$site = wp_parse_url( home_url() );
		if ( empty( $site['host'] ) || 0 !== strcasecmp( $parsed['host'], $site['host'] ) ) {
			return $url;
		}

		$path  = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		$query = [];
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}

		// -----------------
		// Day view handling (map to month to avoid heavy daily pages)
		// -----------------
		$has_day_qs = false;
		$day_date   = '';
		if ( isset( $query['tribe_event_display'] ) && 'day' === $query['tribe_event_display'] ) {
			$has_day_qs = true;
		}
		if ( isset( $query['eventDisplay'] ) && 'day' === $query['eventDisplay'] ) {
			$has_day_qs = true;
		}
		// TEC also uses tribe-bar-date on list view to indicate a specific day
		if ( isset( $query['tribe-bar-date'] ) && is_string( $query['tribe-bar-date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $query['tribe-bar-date'] ) ) {
			$day_date = $query['tribe-bar-date'];
			$has_day_qs = true;
		}
		if ( isset( $query['eventDate'] ) && is_string( $query['eventDate'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $query['eventDate'] ) ) {
			$day_date = $query['eventDate'];
		}
		$path_has_day = ( false !== strpos( untrailingslashit( $path ), '/day' ) );

		// If we have a specific day, map it to the corresponding month URL: /month/YYYY-MM/
		if ( ( $has_day_qs && $day_date ) ) {
			$month_value = substr( $day_date, 0, 7 );
			$base_path = trailingslashit( preg_replace( '#/(list|day)/?.*$#', '/', trailingslashit( $path ) ) );
			$month_path = trailingslashit( $base_path ) . 'month/' . trailingslashit( $month_value );
			// Remove TEC-specific params
			unset( $query['tribe_event_display'], $query['tribe_paged'], $query['eventDate'], $query['eventDisplay'], $query['tribe-bar-date'] );
			$new_query = http_build_query( $query );
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '//';
			$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
			$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
			return $scheme . $parsed['host'] . $port . $month_path . ( $new_query ? '?' . $new_query : '' ) . $frag;
		}

		// If the path already contains /day/YYYY-MM-DD/, remap it to /month/YYYY-MM/
		if ( $path_has_day && preg_match( '#/day/(\d{4}-\d{2}-\d{2})/#', trailingslashit( $path ), $m ) ) {
			$month_value = substr( $m[1], 0, 7 );
			$base_path = trailingslashit( preg_replace( '#/day/.*$#', '/', trailingslashit( $path ) ) );
			$month_path = trailingslashit( $base_path ) . 'month/' . trailingslashit( $month_value );
			unset( $query['tribe_event_display'], $query['tribe_paged'], $query['eventDate'], $query['eventDisplay'], $query['tribe-bar-date'] );
			$new_query = http_build_query( $query );
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '//';
			$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
			$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
			return $scheme . $parsed['host'] . $port . $month_path . ( $new_query ? '?' . $new_query : '' ) . $frag;
		}

		// -----------------
		// Month view handling
		// -----------------
		$has_month_qs = false;
		$month_value  = '';
		if ( isset( $query['tribe_event_display'] ) && 'month' === $query['tribe_event_display'] ) {
			$has_month_qs = true;
		}
		if ( isset( $query['eventDisplay'] ) && 'month' === $query['eventDisplay'] ) {
			$has_month_qs = true;
		}
		if ( isset( $query['eventDate'] ) && is_string( $query['eventDate'] ) && preg_match( '/^\d{4}-\d{2}$/', $query['eventDate'] ) ) {
			$month_value = $query['eventDate'];
		}
		if ( $has_month_qs || ( false !== strpos( untrailingslashit( $path ), '/month' ) ) ) {
			$base_path = trailingslashit( preg_replace( '#/month/.*$#', '/', trailingslashit( $path ) ) );
			$month_path = trailingslashit( $base_path ) . 'month/';
			if ( $month_value ) {
				$month_path .= trailingslashit( $month_value );
			}
			unset( $query['tribe_event_display'], $query['tribe_paged'], $query['eventDate'], $query['eventDisplay'] );
			$new_query = http_build_query( $query );
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '//';
			$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
			$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
			return $scheme . $parsed['host'] . $port . $month_path . ( $new_query ? '?' . $new_query : '' ) . $frag;
		}

		// -----------------
		// Today view handling
		// -----------------
		$has_today_qs = false;
		if ( isset( $query['eventDisplay'] ) && 'today' === $query['eventDisplay'] ) {
			$has_today_qs = true;
		}
		if ( isset( $query['tribe_event_display'] ) && 'day' === $query['tribe_event_display'] && empty( $query['eventDate'] ) ) {
			// Interpret day without specific date as today
			$has_today_qs = true;
		}
		if ( $has_today_qs || ( false !== strpos( untrailingslashit( $path ), '/today' ) ) ) {
			$today_path = trailingslashit( preg_replace( '#/today/?.*$#', '/', trailingslashit( $path ) ) ) . 'today/';
			unset( $query['tribe_event_display'], $query['tribe_paged'], $query['eventDate'], $query['eventDisplay'] );
			$new_query = http_build_query( $query );
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '//';
			$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
			$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
			return $scheme . $parsed['host'] . $port . $today_path . ( $new_query ? '?' . $new_query : '' ) . $frag;
		}

		// -----------------
		// List view handling
		// -----------------
		$has_list_qs = false;
		$has_past_qs = false;
		$paged       = 0;

		if ( isset( $query['tribe_event_display'] ) && 'list' === $query['tribe_event_display'] ) {
			$has_list_qs = true;
		}
		if ( isset( $query['eventDisplay'] ) ) {
			if ( 'list' === $query['eventDisplay'] ) { $has_list_qs = true; }
			if ( 'past' === $query['eventDisplay'] ) { $has_past_qs = true; }
		}
		if ( isset( $query['tribe_paged'] ) && is_numeric( $query['tribe_paged'] ) ) {
			$paged = (int) $query['tribe_paged'];
		}

		// Also treat URLs that already include /list/ in the path
		$path_has_list = ( false !== strpos( untrailingslashit( $path ), '/list' ) );

		// If neither query nor path imply list view, nothing to do for this normalizer
		if ( ! $has_list_qs && ! $path_has_list && ! $has_past_qs ) {
			return $url;
		}

		// Remove TEC-specific params from query for final build
		unset( $query['tribe_event_display'], $query['tribe_paged'], $query['eventDate'], $query['eventDisplay'] );

		// Build semantic path
		$path = trailingslashit( $path );
		if ( $has_past_qs ) {
			// Normalize to list past: .../list/past/ (trailing slash)
			if ( $path_has_list ) {
				// Keep existing list base and append past
				$base = trailingslashit( preg_replace( '#/list/.*$#', '/list/', $path ) );
				$path = untrailingslashit( $base ) . '/past/';
			} else {
				// No list segment present; add list/past/
				$path = untrailingslashit( $path ) . '/list/past/';
			}
		} else {
			// Standard list URLs
			if ( ! $path_has_list ) {
				$path .= 'list/';
			}
			if ( $paged > 1 ) {
				$path = trailingslashit( $path ) . 'page/' . $paged . '/';
			}
		}

		$new_query = http_build_query( $query );
		$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '//';
		$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
		$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
		$new    = $scheme . $parsed['host'] . $port . $path . ( $new_query ? '?' . $new_query : '' ) . $frag;

		return $new;
	}
}
