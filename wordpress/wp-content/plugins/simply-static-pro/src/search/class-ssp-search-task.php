<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Url_Fetcher;
use Simply_Static\Util;
use DOMDocument;
use DOMXPath;

/**
 * Class which handles Search indexing task.
 */
class Search_Task extends Simply_Static\Task {

	use Simply_Static\canProcessPages;

	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'search';

	/**
	 * Temp directory.
	 *
	 * @var string
	 */
 private string $temp_dir;

	/**
	 * Search instance.
	 *
	 * @var object
	 */
	private $search;

	/**
	 * Search type.
	 *
	 * @var string
	 */
 public $search_type;

 /**
  * Per-run counters for accurate Activity Log reporting.
  * We persist them in options to survive multiple task iterations.
  */
 private ?int $run_total = null;
 private int $run_processed = 0;


	/**
	 * Constructor
	 */
 public function __construct() {
        parent::__construct();

		$options    = Options::instance();
		$ss_options = get_option( 'simply-static' );

		$this->temp_dir    = $options->get_archive_dir();
		$this->search_type = $ss_options['search_type'] ?? 'fuse';

		$this->processing_column = 'last_checked_at';

		add_filter( 'ss_remote_get_args', function ( $args ) {
			$args['blocking'] = true;

			return $args;
		} );

        if ( 'algolia' === $this->search_type ) {
            $this->search = Search_Algolia::get_instance();
        } else {
            $this->search = Search_Fuse::get_instance();
        }
    }

	public function set_start_time() {
		$start_time = get_option( 'ssp_search_index_start_time' );

		if ( ! $start_time ) {

			$start_time = Util::formatted_datetime();
			update_option( 'ssp_search_index_start_time', $start_time );

			$this->start_time = $start_time;

			// First time.
			if ( 'fuse' === $this->search_type && 'update' !== $this->get_generate_type() ) {
				$this->search->delete_index();
			}

			if ( 'algolia' === $this->search_type && 'update' !== $this->get_generate_type() ) {
				$this->search->delete_index();
			}

			return;
		}

		$this->start_time = $start_time;
	}

	/**
	 * Add a batch of pages to the search index.
	 *
	 * @return boolean true if done, false if not done.
	 */
    public function perform() {
  // Start indexing task
		// We don't index results on build and single exports.
		$use_single = get_option( 'simply-static-use-single' );
		$use_build  = get_option( 'simply-static-use-build' );

  if ( ! empty( $use_build ) || ! empty( $use_single ) ) {
            
            return true;
        }

		// Optional: filter to bypass the indexing process.
		$bypass_index = apply_filters( 'ssp_bypass_index', false );

  if ( $bypass_index ) {
            
            return true;
        }

  // Ensure total pages cache for this task is reset so the denominator is correct
  // for the current run (prevents stale totals like "Indexed N of 1").
  self::delete_total_pages();

  // Initialize per-run counters used for Activity Log display.
  $this->init_run_counters();

  $done = $this->process_pages();

		// return true when done (no more pages).
		if ( $done ) {
   if ( 'fuse' === $this->search_type ) {
       $this->search->update_index_file( $this->temp_dir );
   }

            // Handle cleanup.
            delete_option( 'ssp_search_results' );
            delete_option( 'ssp_search_index_start_time' );

            // Clean total pages cache for this task at the end as well.
            self::delete_total_pages();

            // Cleanup per-run counters for next runs.
            delete_option( 'ssp_search_run_total' );
            delete_option( 'ssp_search_run_processed' );
            delete_option( 'ssp_search_run_started_at' );

            do_action( 'ssp_finished_search_index' );
        }

        return $done;
    }

	/**
	 * Process each page.
	 *
	 * @param Page $static_page object.
	 *
	 * @return void
	 */
    protected function process_page( $static_page ) {
  $index_item = $this->get_index_item( $static_page );

  if ( ! $index_item ) {

      return;
  }

  if ( 'fuse' === $this->search_type ) {
      $this->search->update_index( $index_item );
  }

        // Increment processed counter only when we had an indexable item for this page.
        $this->increment_run_processed();
    }

	/**
	 * Push static pages to Algolia.
	 *
	 * @param object $static_page static page object after crawling.
	 *
	 * @return array|bool
	 */
	public function get_index_item( $static_page ) {
  // Build index item for search providers
  $options    = get_option( 'simply-static' );
  $use_search = $options['use_search'] ?? false;

		// Check if search is active.
  if ( ! $use_search ) {

            return false;
        }

        // If it's a file, skip
        $path = parse_url( $static_page->url, PHP_URL_PATH );
        $ext  = pathinfo( $path, PATHINFO_EXTENSION );

		if ( $ext ) {
			Util::debug_log( 'Skipping file with extension: ' . $ext . ' - ' . $static_page->url );

			return false;
		}

		// Exclude from search index.
		$excludables = array( 'feed', 'comments', 'author' );

		if ( ! empty( $options['search_excludable'] ) ) {
			$excludables = explode( "\n", $options['search_excludable'] );

			// Remove files, feeds, comments and author archives from index.
			$excludables = apply_filters(
				'ssp_excluded_by_default',
				array_merge(
					$excludables,
					array(
						'feed',
						'comments',
						'author'
					)
				)
			);
		}

		if ( ! empty( $excludables ) ) {
			foreach ( $excludables as $excludable ) {
				// Check excludable URL patterns.
				$in_url = strpos( urldecode( $static_page->url ), $excludable );

				if ( false !== $in_url ) {
					Util::debug_log( 'Skipping URL with excludable pattern: ' . $excludable . ' - ' . $static_page->url );

					return false;
				}
			}
		}

		// Check if it's a full static export.
		$use_single = get_option( 'simply-static-use-single' );
		$use_build  = get_option( 'simply-static-use-build' );

		// Is build?
		if ( ! empty( $use_build ) ) {
			Util::debug_log( 'Skipping because this is a build export: ' . $static_page->url );

			return false;
		}
		// Is single?
		if ( ! empty( $use_single ) ) {
			Util::debug_log( 'Skipping because this is a single export: ' . $static_page->url );

			return false;
		}

		if ( 200 == $static_page->http_status_code ) {
			$response = Url_Fetcher::remote_get( $static_page->url );
			$html     = wp_remote_retrieve_body( $response );
			Util::debug_log( 'Processing page with 200 status code: ' . $static_page->url );

			if ( empty( $html ) ) {
				Util::debug_log( 'Skipping because HTML content is empty: ' . $static_page->url );

				return false;
			}

			// Create a new DOM document
			$dom = new DOMDocument();

			// Suppress errors from malformed HTML
			libxml_use_internal_errors( true );

			// Load the HTML, preserving whitespace and handling UTF-8
			$dom->preserveWhiteSpace = true;
			$dom->formatOutput       = false;

			// Load the HTML
			$utf8_html_string = htmlspecialchars_decode( htmlentities( $html, ENT_COMPAT, 'utf-8', false ) );

			// Check if the HTML string is empty to prevent ValueError
			if ( empty( $utf8_html_string ) ) {
				Util::debug_log( 'Empty HTML content for URL: ' . $static_page->url );

				return false;
			}

			$dom->loadHTML( $utf8_html_string );

			// Clear any errors
			libxml_clear_errors();

			// Create a DOMXPath object to query the DOM
			$xpath = new DOMXPath( $dom );

			// Get elements from settings.
			$title   = 'title';
			$body    = 'body';
			$excerpt = '.entry-content';

			if ( ! empty( $options['search_index_title'] ) ) {
				$title = $options['search_index_title'];
			}

			if ( ! empty( $options['search_index_content'] ) ) {
				$body = $options['search_index_content'];
			}

			if ( ! empty( $options['search_index_excerpt'] ) ) {
				$excerpt = $options['search_index_excerpt'];
			}

			// Filter dom for creating index entries.
			$title   = $this->get_selector_data( $title, $xpath, $dom );
			$body    = wp_strip_all_tags( $this->get_selector_data( $body, $xpath, $dom ) );
			$excerpt = wp_strip_all_tags( $this->get_selector_data( $excerpt, $xpath, $dom ) );

			// If no title found, use URL as fallback
			if ( '' === $title ) {
    $title = $static_page->url;
}

			// Get post ID
			$post_id     = '';
			$id_elements = $xpath->query( '//*[contains(@class, "ssp-id")]' );

			if ( $id_elements && $id_elements->length > 0 ) {
				$post_id = wp_strip_all_tags( $id_elements->item( 0 )->textContent );
			}

			// If no post ID found, use URL as fallback
   if ( '' === $post_id ) {
                $post_id = md5( $static_page->url );
            }

			// Multilingual.
			$language      = '';
			$link_elements = $xpath->query( '//link[@hreflang]' );

			if ( $link_elements ) {
				foreach ( $link_elements as $link ) {
					$hreflang = $link->getAttribute( 'hreflang' );
					$href     = $link->getAttribute( 'href' );

					if ( $static_page->url === $href && 'x-default' !== $hreflang ) {
						$language = $hreflang;
						break;
					}
				}
			}

   if ( '' !== $title && '' !== $post_id ) {
                // Build search entry.
                $index_item = array(
					'objectID' => $post_id,
					'title'    => wp_strip_all_tags( $title ),
					'content'  => $body,
					'excerpt'  => wp_trim_words( $excerpt, '20', '..' ),
					'path'     => apply_filters( 'ssp_search_result_path', str_replace( home_url(), '', $static_page->url ) ),
				);

				// Is Multilingual?
				if ( '' !== $language ) {
					$index_item['language'] = $language;
				}

				// Additional Path set?
				if ( ! empty( $options['relative_path'] ) ) {
					$index_item['path'] = apply_filters( 'ssp_search_result_path', $options['relative_path'] . str_replace( home_url(), '', $static_page->url ) );
				}

				$index_item = apply_filters( 'ssp_search_index_item', $index_item, $xpath );

                if ( 'algolia' === $this->search_type ) {
                    // Add or update data in Algolia with robust matching (objectID, then path fallback).
                    try {
                        $ok = $this->search->upsert_index_item( $index_item );
                        // No verbose counters/logging on success/failure
                    } catch ( Exception $e ) {
                        // Swallow and continue to avoid noisy logs
                    }
                }

                return $index_item;
            } else {
                // Skip when title or post ID is empty
            }
        } else {
            // Skip when status is not 200
        }

		return false;
	}

    /**
     * Initialize per-run counters, persisted in options so they remain correct across iterations.
     *
     * Run total counts only potentially indexable pages (no file extension and not excluded by patterns),
     * matching the Search task semantics.
     */
    private function init_run_counters() : void {
        $current_start = $this->get_start_time();
        $stored_start  = get_option( 'ssp_search_run_started_at' );

        if ( $stored_start !== $current_start ) {
            // New run: recompute totals and reset processed.
            $this->run_total     = $this->compute_indexable_total();
            $this->run_processed = 0;

            update_option( 'ssp_search_run_total', $this->run_total );
            update_option( 'ssp_search_run_processed', $this->run_processed );
            update_option( 'ssp_search_run_started_at', $current_start );
        } else {
            // Continue existing run.
            $this->run_total     = (int) get_option( 'ssp_search_run_total', 0 );
            $this->run_processed = (int) get_option( 'ssp_search_run_processed', 0 );
        }
    }

    /**
     * Compute how many pages are potentially indexable for the current run.
     * Uses the Search task's page selection query and filters out obvious non-indexable entries
     * without performing network requests.
     */
    private function compute_indexable_total() : int {
        try {
            $options     = get_option( 'simply-static' );
            $excludables = array( 'feed', 'comments', 'author' );
            if ( ! empty( $options['search_excludable'] ) ) {
                $lines       = explode( "\n", $options['search_excludable'] );
                $excludables = apply_filters( 'ssp_excluded_by_default', array_merge( $lines, $excludables ) );
            }

            $candidates = $this->get_pages_to_process_sql()->find();
            $count      = 0;
            foreach ( (array) $candidates as $p ) {
                // Skip obvious files (has extension in path)
                $path = parse_url( $p->url, PHP_URL_PATH );
                $ext  = pathinfo( $path, PATHINFO_EXTENSION );
                if ( $ext ) { continue; }

                // Skip excluded patterns
                $skip = false;
                foreach ( (array) $excludables as $ex ) {
                    if ( $ex !== '' && strpos( urldecode( $p->url ), $ex ) !== false ) { $skip = true; break; }
                }
                if ( $skip ) { continue; }

                $count++;
            }
            return $count;
        } catch ( \Throwable $e ) {
            // Fall back to the trait's total as a best effort.
            return (int) $this->get_total_pages( false );
        }
    }

    /**
     * Increment processed counter and persist it for cross-iteration accuracy.
     */
    private function increment_run_processed() : void {
        $this->run_processed++;
        if ( $this->run_total !== null && $this->run_processed > $this->run_total ) {
            $this->run_processed = $this->run_total; // cap
        }
        update_option( 'ssp_search_run_processed', $this->run_processed );
    }

	/**
	 * Get content from an element using a CSS selector
	 *
	 * @param string $selector CSS selector
	 * @param DOMXPath $xpath DOMXPath object
	 * @param DOMDocument $dom DOM document
	 *
	 * @return string Element content
	 */
	protected function get_selector_data( $selector, $xpath, $dom ) {
		if ( $this->is_meta_selector( $selector ) ) {
			return $this->get_meta_data( $selector, $xpath, $dom );
		}

		// Convert CSS selector to XPath
		$xpath_query = $this->css_to_xpath( $selector );

		// Query the DOM
		$elements = $xpath->query( $xpath_query );

		if ( $elements && $elements->length > 0 ) {
			// Get the first matching element
			$element = $elements->item( 0 );

			// Return its content
			return $element->textContent;
		}

		return '';
	}

	/**
	 * Get content from a meta tag
	 *
	 * @param string $selector Meta selector in format 'name|value' or 'property|value'
	 * @param DOMXPath $xpath DOMXPath object
	 * @param DOMDocument $dom DOM document
	 *
	 * @return string Meta content
	 */
	protected function get_meta_data( $selector, $xpath, $dom ) {
		$meta_array = array_filter( explode( '|', $selector ) );

		$attribute       = 'name';
		$attribute_value = $meta_array[0];
		$value_attr      = $meta_array[1];

		if ( $meta_array[0] === 'property' ) {
			$attribute       = 'property';
			$attribute_value = $meta_array[1];
			$value_attr      = 'content';
		}

		// Create XPath query for meta tag
		$query = "//meta[@{$attribute}='{$attribute_value}']";

		// Query the DOM
		$elements = $xpath->query( $query );

		if ( $elements && $elements->length > 0 ) {
			// Get the first matching element
			$element = $elements->item( 0 );

			// Return the value of the specified attribute
			return $element->getAttribute( $value_attr );
		}

		return '';
	}

	/**
	 * Convert a CSS selector to an XPath query
	 *
	 * @param string $selector CSS selector
	 *
	 * @return string XPath query
	 */
	protected function css_to_xpath( $selector ) {
		// Handle simple tag selectors
		if ( strpos( $selector, '.' ) === false ) {
			return '//' . $selector;
		}

		// Handle class selectors
		if ( strpos( $selector, '.' ) === 0 ) {
			// It's a class selector like '.entry-content'
			$class_name = substr( $selector, 1 );

			return "//*[contains(@class, '{$class_name}')]";
		}

		// Handle tag with class like 'div.content'
		$parts      = explode( '.', $selector );
		$tag_name   = $parts[0];
		$class_name = $parts[1];

		return "//{$tag_name}[contains(@class, '{$class_name}')]";
	}

	protected function is_meta_selector( $selector ) {
		$expanded = array_filter( explode( '|', $selector ) );

		return count( $expanded ) === 2;
	}

	/**
	 * Message to set when processed pages.
	 *
	 * @param integer $processed Number of pages processed.
	 * @param integer $total Number of total pages to process.
	 *
	 * @return string
	 */
    protected function processed_pages_message( $processed, $total ) {
        // Prefer our per-run counters for display to avoid DB timing/caching mismatches.
        $display_total     = $this->run_total !== null ? $this->run_total : (int) get_option( 'ssp_search_run_total', $total );
        $display_processed = $this->run_total !== null ? $this->run_processed : (int) get_option( 'ssp_search_run_processed', $processed );

        // Clamp
        if ( $display_total < 0 ) { $display_total = 0; }
        if ( $display_processed < 0 ) { $display_processed = 0; }
        if ( $display_total > 0 && $display_processed > $display_total ) { $display_processed = $display_total; }

        return sprintf( __( "Indexed %d of %d pages", 'simply-static-pro' ), $display_processed, $display_total );
    }
}
