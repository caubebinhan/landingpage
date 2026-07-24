<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Page;
use Simply_Static\Plugin;
use Simply_Static\Util;

/**
 * Class which handles ShortPixel task.
 */
class Shortpixel_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'shortpixel';

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
	 * Temp directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options       = Simply_Static\Options::instance();
		$this->options = $options;

		if ( $this->options->get( 'shortpixel_next_page' ) ) {
			$this->page = $this->options->get( 'shortpixel_next_page' );
		}

		$this->temp_dir = $options->get_archive_dir();

		// Initialize total candidates at the beginning of the ShortPixel upload flow if not set.
		$existing_total = $this->options->get( 'shortpixel_total_candidates' );
		if ( $existing_total === null || $existing_total === false ) {
			$this->options->set( 'shortpixel_total_candidates', $this->calculate_total_candidates() );
			$this->options->set( 'shortpixel_queued_total', 0 );
			$this->options->save();
		}
	}

	/**
	 * Get filtered par page.
	 *
	 * @return mixed|null
	 */
	protected function get_per_page() {
		return apply_filters( 'simply_static_shortpixel_per_page', $this->per_page );
	}

	/**
	 * Calculate how many files actually need to be queued (not optimized and not queued yet).
	 * Warning: Iterates through all candidate Pages once per run.
	 */
	protected function calculate_total_candidates(): int {
		$counter = 0;
		$pages = Page::query()
			->where( "file_path IS NOT NULL" )
			->where( "file_path != ''" )
			->where( "content_type IN ('" . implode( "','", $this->get_allowed_content_types() ) . "')" )
			->find();

		/** @var Shortpixel $shortpixel */
		$shortpixel = Plugin::instance()->get_integration( 'shortpixel' );
		foreach ( $pages as $page ) {
			if ( ! $shortpixel->is_optimized( $page->url ) && ! $shortpixel->is_queued( $page->url ) ) {
				$counter++;
			}
		}
		return $counter;
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

	public function get_total_pages() {
		// Use the precomputed total candidates (actual files needing queueing),
		// falling back to old behavior if not available.
		$total = $this->options->get( 'shortpixel_total_candidates' );
		if ( is_numeric( $total ) ) {
			return (int) $total;
		}

		$total_pages = Page::query()
		                   ->where( "file_path IS NOT NULL" )
		                   ->where( "file_path != ''" )
		                   ->where( "content_type IN ('" . implode( "','", $this->get_allowed_content_types() ) . "')" )
		                   ->count();

		return $total_pages;
	}

	public function get_pages() {
		$offset = 0;

		if ( $this->page > 0 ) {
			$offset = $this->page - 1;
		}

		if ( $offset ) {
			$offset *= $this->per_page;
		}

		$pages = Page::query()
		             ->where( "file_path IS NOT NULL" )
		             ->where( "file_path != ''" )
		             ->where( "content_type IN ('" . implode( "','", $this->get_allowed_content_types() ) . "')" )
		             ->limit( $this->get_per_page() )
		             ->offset( $offset )
		             ->find();

		return $pages;
	}

	/**
	 * Push a batch of files from the temp dir to DO spaces.
	 *
	 * @return boolean true if done, false if not done.
	 */
	public function perform(): bool {
		try {
			list( $pages_processed, $total_pages ) = $this->upload_static_files( $this->temp_dir );

			$message = sprintf( __( 'Uploaded %d of %d pages/files to Shortpixel', 'simply-static-pro' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );

			// return true when done (no more pages).
			if ( $pages_processed >= $total_pages ) {
				$this->delete_page_info();

				do_action( 'ssp_finished_shortpixel_upload', $this );
			}

			return $pages_processed >= $total_pages;
		} catch ( \Exception $e ) {
			$this->save_status_message( $e->getMessage(), static::$task_name . '-error' );
		}

		return true;
	}


	/**
	 * Upload files to Shortpixel.
	 *
	 * @param string $destination_dir The directory to put the files.
	 *
	 * @return array
	 */
	public function upload_static_files( string $destination_dir ): array {
		$static_pages    = $this->get_pages();
		$total_pages     = $this->get_total_pages();
		$queued_total    = (int) $this->options->get( 'shortpixel_queued_total' );

		Util::debug_log( 'ShortPixel total candidates: ' . $total_pages . '; queued so far: ' . $queued_total );

		/** @var Shortpixel $shortpixel */
		$shortpixel = Plugin::instance()->get_integration( 'shortpixel' );
		$pages      = array_filter( $static_pages, function ( $page ) use ( $shortpixel ) {
			return ! $shortpixel->is_optimized( $page->url ) && ! $shortpixel->is_queued( $page->url );
		} );

		$queued_now = 0;
		if ( ! empty( $pages ) ) {
			$files = [];
			foreach ( $pages as $page ) {
				$files[] = [
					'page' => $page,
					'url'  => $page->url,
					'path' => $destination_dir . $page->file_path
				];
			}
			$shortpixel->queue_files( $files );
			$queued_now = count( $files );
		}

		// Track how many were actually queued this run.
		$queued_total += $queued_now;
		$this->options->set( 'shortpixel_queued_total', $queued_total );
		$this->options->save();

		// Advance page for next batch.
		$this->increase_page();

		// If there are no more static pages in this batch, we can safely mark as done
		// (even if nothing needed queuing) to avoid infinite looping.
		$done = empty( $static_pages );
		return array( $done ? $total_pages : $queued_total, $total_pages );
	}

	public function delete_page_info() {
		$this->options->destroy( 'shortpixel_next_page' );
		$this->options->destroy( 'shortpixel_total_candidates' );
		$this->options->destroy( 'shortpixel_queued_total' );
		$this->options->save();
	}

	public function increase_page() {
		$next_page = $this->page + 1;
		$this->options->set( 'shortpixel_next_page', $next_page );
		$this->options->save();
	}


}
