<?php

namespace simply_static_pro\Crawler;

use Simply_Static\Util;
use Simply_Static\Options;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro-only Changes Crawler.
 *
 * Loaded exclusively by the Discover_Changes_Task and never registered globally.
 * Extend the core Crawler to reuse queueing logic.
 */
class Changes_Crawler extends \Simply_Static\Crawler\Crawler {
	/** @var string */
	protected $name = 'Detect Changes';
	/** @var string */
	protected $description = 'Detects recently changed URLs for update exports';
	/** @var string */
	protected $id = 'changes';

	/**
	 * Whether the external dependency for this crawler is active.
	 * For now, always true. Adjust if future dependencies arise.
	 */
	public function dependency_active() : bool {
		return true;
	}

	/**
	 * Build pagination URLs for a given base archive URL.
	 * Only generate pretty permalinks (e.g., /page/2/). Query-style pagination is excluded.
	 */
	private function build_paginated_urls( string $base, int $max_pages = 3 ) : array {
		$urls = [];
		for ( $p = 2; $p <= $max_pages; $p++ ) {
			// Pretty structure only: /page/2/
			$pretty = trailingslashit( $base ) . user_trailingslashit( 'page/' . $p, 'paged' );
			$urls[] = $pretty;
		}
		// Keep only local, unique, and exclude any query-based pagination (?paged=)
		$urls = array_values( array_unique( array_filter( $urls, function( $u ) {
			return is_string( $u ) && Util::is_local_url( $u ) && ( false === strpos( $u, '?paged=' ) );
		} ) ) );
		return $urls;
	}

	/**
	 * Maybe add a feed URL for a given base archive URL when feeds are enabled.
	 */
	private function maybe_add_feed( array &$urls, string $base ) : void {
		$options = Options::instance();
		if ( $options->get( 'add_feeds' ) ) {
			$feed_pretty = trailingslashit( $base ) . 'feed/';
			$feed_query  = add_query_arg( 'feed', 'rss2', $base );
			foreach ( [ $feed_pretty, $feed_query ] as $feed_url ) {
				if ( Util::is_local_url( $feed_url ) ) {
					$urls[] = $feed_url;
				}
			}
		}
	}

	/**
	 * Detect URLs changed since the last export baseline time and include related archives.
	 *
	 * @return array
	 */
	public function detect() : array {
		$urls = [];
		$options = Options::instance();
		// Prefer previous export's end time as baseline (GMT)
		$baseline = get_option( 'ssp_previous_export_end_gmt' );
		if ( empty( $baseline ) ) {
			// Fallback to current run's archive_start_time converted to GMT (may miss changes prior to clicking Start)
			$current_start = $options->get( 'archive_start_time' );
			$baseline = $current_start ? gmdate( 'Y-m-d H:i:s', strtotime( $current_start ) ) : '';
		}

		if ( empty( $baseline ) ) {
			Util::debug_log( 'Changes_Crawler: baseline time is empty; nothing to detect.' );
			return [];
		}

		Util::debug_log( 'Changes_Crawler: using baseline (GMT): ' . $baseline );

		// 1) Posts modified after baseline (GMT)
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		// Exclude attachments and a few internal types from the regular posts crawl
		unset( $post_types['attachment'] );
		$excluded_types = [ 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ];
		foreach ( $excluded_types as $ex ) {
			if ( isset( $post_types[ $ex ] ) ) {
				unset( $post_types[ $ex ] );
			}
		}

		$posts_query = [
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'date_query'     => [
				[
					'column'    => 'post_modified_gmt',
					'after'     => $baseline,
					'inclusive' => false,
				],
			],
		];

		$post_ids = get_posts( $posts_query );
		$post_urls = [];
		$related_urls = [];

		foreach ( $post_ids as $pid ) {
			$permalink = get_permalink( $pid );
			if ( $permalink && Util::is_local_url( $permalink ) ) {
				$post_urls[] = $permalink;
			}

			// Collect related URLs for each changed post
			$post_type = get_post_type( $pid );
			$post_obj  = get_post( $pid );

			$pag_depth  = (int) apply_filters( 'ssp_changes_crawler_pagination_depth', 3 );
			$auth_depth = (int) apply_filters( 'ssp_changes_crawler_author_pagination_depth', 2 );

			// Home page
			$home = trailingslashit( home_url( '/' ) );
			if ( Util::is_local_url( $home ) ) {
				$related_urls[] = $home;
				$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $home, $pag_depth ) );
				$this->maybe_add_feed( $related_urls, $home );
			}

			// Blog posts index page (if configured)
			if ( 'page' === get_option( 'show_on_front' ) ) {
				$posts_page_id = (int) get_option( 'page_for_posts' );
				if ( $posts_page_id ) {
					$blog = get_permalink( $posts_page_id );
					if ( $blog && Util::is_local_url( $blog ) ) {
						$related_urls[] = $blog;
						$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $blog, $pag_depth ) );
						$this->maybe_add_feed( $related_urls, $blog );
					}
				}
			}

			// Post type archive
			if ( $post_type && post_type_exists( $post_type ) ) {
				$pt_obj = get_post_type_object( $post_type );
				if ( $pt_obj && $pt_obj->has_archive ) {
					$pt_archive = get_post_type_archive_link( $post_type );
					if ( $pt_archive && Util::is_local_url( $pt_archive ) ) {
						$related_urls[] = $pt_archive;
						$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $pt_archive, $pag_depth ) );
						$this->maybe_add_feed( $related_urls, $pt_archive );
					}
				}
			}

			// Author archive
			if ( $post_obj && $post_obj->post_author ) {
				$author_url = get_author_posts_url( (int) $post_obj->post_author );
				if ( $author_url && Util::is_local_url( $author_url ) ) {
					$related_urls[] = $author_url;
					$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $author_url, $auth_depth ) );
					$this->maybe_add_feed( $related_urls, $author_url );
				}
			}

			// Date archives (year and month)
			$date_gmt = $post_obj ? $post_obj->post_date_gmt : '';
			$time = $date_gmt ? strtotime( $date_gmt ) : 0;
			if ( $time ) {
				$year_url  = get_year_link( (int) gmdate( 'Y', $time ) );
				$month_url = get_month_link( (int) gmdate( 'Y', $time ), (int) gmdate( 'm', $time ) );
				foreach ( [ $year_url, $month_url ] as $archive_url ) {
					if ( $archive_url && Util::is_local_url( $archive_url ) ) {
						$related_urls[] = $archive_url;
						$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $archive_url, 2 ) );
						$this->maybe_add_feed( $related_urls, $archive_url );
					}
				}
			}

			// Taxonomy term archives for assigned terms
			$taxes = get_object_taxonomies( $post_type, 'names' );
			foreach ( $taxes as $tax ) {
				$terms = wp_get_post_terms( $pid, $tax, [ 'fields' => 'all' ] );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_link = get_term_link( $term );
						if ( is_wp_error( $term_link ) ) { continue; }
						if ( $term_link && Util::is_local_url( $term_link ) ) {
							$related_urls[] = $term_link;
							$related_urls = array_merge( $related_urls, $this->build_paginated_urls( $term_link, 3 ) );
							$this->maybe_add_feed( $related_urls, $term_link );
						}
					}
				}
			}
		}

		Util::debug_log( sprintf( 'Changes_Crawler: detected %d modified posts since %s', count( $post_urls ), $baseline ) );

		// 2) Attachments uploaded or modified after baseline (GMT)
		$attachment_ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'date_query'     => [
				'relation' => 'OR',
				[
					'column'    => 'post_date_gmt',
					'after'     => $baseline,
					'inclusive' => false,
				],
				[
					'column'    => 'post_modified_gmt',
					'after'     => $baseline,
					'inclusive' => false,
				],
			],
		] );

		$media_urls = [];
		$uploads = wp_get_upload_dir();
		foreach ( $attachment_ids as $aid ) {
			$full = wp_get_attachment_url( $aid );
			if ( $full && Util::is_local_url( $full ) ) {
				$media_urls[] = $full;
			}

			$meta = wp_get_attachment_metadata( $aid );
			if ( is_array( $meta ) ) {
				$basefile = isset( $meta['file'] ) ? $meta['file'] : '';
				$baseurl = '';
				if ( $basefile && ! empty( $uploads['baseurl'] ) ) {
					$basedir_url = trailingslashit( $uploads['baseurl'] );
					$dir = '';
					$pos = strrpos( $basefile, '/' );
					if ( false !== $pos ) {
						$dir = substr( $basefile, 0, $pos + 1 );
					}
					$baseurl = $basedir_url . $dir;
				}
				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) && $baseurl ) {
					foreach ( $meta['sizes'] as $size ) {
						if ( ! empty( $size['file'] ) ) {
							$url = $baseurl . $size['file'];
							if ( Util::is_local_url( $url ) ) {
								$media_urls[] = $url;
							}
						}
					}
				}
			}
		}
		Util::debug_log( sprintf( 'Changes_Crawler: detected %d new media URLs since %s', count( $media_urls ), $baseline ) );

		// 3) Always include WordPress core sitemap index (if enabled in site)
		$sitemap = home_url( '/wp-sitemap.xml' );
		if ( $sitemap && Util::is_local_url( $sitemap ) ) {
			$related_urls[] = $sitemap;
		}

		// Merge and dedupe
		$all = array_values( array_unique( array_merge( $post_urls, $media_urls, $related_urls ) ) );
		Util::debug_log( sprintf( 'Changes_Crawler: total %d URLs to add (posts: %d, media: %d, related: %d)', count( $all ), count( $post_urls ), count( $media_urls ), count( $related_urls ) ) );

		return $all;
	}
}
