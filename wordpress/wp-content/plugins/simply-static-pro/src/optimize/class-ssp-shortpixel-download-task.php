<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Page;
use Simply_Static\Plugin;
use Simply_Static\Util;

/**
 * Class which handles ShortPixel task.
 */
class Shortpixel_Download_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'shortpixel_download';

	/**
	 * Current Page for SQL.
	 *
	 * @var int
	 */
	protected $page = 0;

	/**
	 * Per page for SQL.
	 *
	 * @var int
	 */
	protected $per_page = 10;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options       = Simply_Static\Options::instance();
		$this->options = $options;
	}

	/**
	 * Get filtered par page.
	 *
	 * @return mixed|null
	 */
	protected function get_per_page() {
		return apply_filters( 'simply_static_shortpixel_per_page', $this->per_page );
	}

	protected function get_allowed_content_types() {
		return [
			'image/jpeg',
			'image/avif',
			'image/png',
			'image/gif',
			'image/webp'
		];
	}

	/**
	 * @return int
	 */
	public function get_processed_count() {
		$count = get_transient( 'ssp_shortpixel_processed_count' );

		return $count ?: 0;
	}

	public function set_processed_count( $count ) {
		set_transient( 'ssp_shortpixel_processed_count', $count, 1 * HOUR_IN_SECONDS );
	}

	public function get_total_count() {
		$count = get_transient( 'ssp_shortpixel_total_count' );

		if ( false === $count ) {
			$count = $this->get_total_pages();

			set_transient( 'ssp_shortpixel_total_count', $count, 1 * HOUR_IN_SECONDS );
		}

		return $count;
	}

	public function get_total_pages() {
		global $wpdb;

		// Fetch all queued ShortPixel postmeta entries and sum their item counts.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s", '_queued_shortpixel' ), ARRAY_A );

		$total_items = 0;
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$meta = maybe_unserialize( $row['meta_value'] );
				if ( is_array( $meta ) ) {
					$total_items += count( $meta );
				}
			}
		}

		return $total_items;
	}

	public function get_queued_files() {
		global $wpdb;

		$offset = 0;

		if ( $this->page > 0 ) {
			$offset = $this->page - 1;
		}

		if ( $offset ) {
			$offset *= $this->per_page;
		}

		$queued_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT {$offset}, {$this->get_per_page()}", '_queued_shortpixel' ), ARRAY_A );

		return $queued_data;
	}

	/**
	 * Push a batch of files from the temp dir to DO spaces.
	 *
	 * @return boolean true if done, false if not done.
	 */
	public function perform(): bool {
		list( $pages_processed, $total_pages ) = $this->download_static_files();

		// return true when done (no more pages).
		if ( $pages_processed >= $total_pages ) {

			if ( ! empty( $this->get_queued_files() ) ) {
				return false; // Last check.
			}

			// Reset pagination for next run.
			$this->delete_transients();
			do_action( 'ssp_finished_shortpixel_download', $this );
		}

		return $pages_processed >= $total_pages;
	}


	/**
	 * Upload files to Shortpixel.
	 *
	 * @param string $destination_dir The directory to put the files.
	 *
	 * @return array
	 */
	public function download_static_files() {

		$queued_files    = $this->get_queued_files();
		$total_pages     = $this->get_total_count();
		$pages_processed = $this->get_processed_count();
		$left_pages      = $this->get_total_pages();

		// Show how many items are left before processing this batch.
		$this->save_status_message( sprintf( __( 'Downloading optimized files... Left: %d', 'simply-static-pro' ), $left_pages ) );

		/** @var Shortpixel $shortpixel */
		$shortpixel = Plugin::instance()->get_integration( 'shortpixel' );
		$file_urls  = [];
		foreach ( $queued_files as $meta ) {
			$files = maybe_unserialize( $meta['meta_value'] );

			if ( ! $files ) {
				continue;
			}

			foreach ( $files as $file_info ) {
				$file_urls[] = $file_info['img_url'];
			}
		}

		$processed_now = 0;
		if ( ! empty( $file_urls ) ) {
			$processed_now = (int) $shortpixel->download_files( $file_urls );
		}

		// Only count items that were actually processed (saved or dequeued)
		$pages_processed += $processed_now;
		$this->set_processed_count( $pages_processed );
		Util::debug_log( 'ShortPixel download processed this batch: ' . $processed_now . ' items; total processed so far: ' . $pages_processed . ' of ' . $total_pages );

		// Show updated remaining items after processing this batch.
		$this->save_status_message( sprintf( __( 'Downloading optimized files... Left: %d', 'simply-static-pro' ), max( 0, $total_pages - $pages_processed ) ) );

		return array( $pages_processed, $total_pages );
	}

	public function delete_transients() {
		delete_transient( 'ssp_shortpixel_processed_count' );
		delete_transient( 'ssp_shortpixel_total_count' );
	}
}
