<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Page;
use Simply_Static\Plugin;
use Simply_Static\Util;

/**
 * Class which handles ShortPixel task.
 *
 * This task is used if webp is in use.
 * It will go over images found in every HTML file and replace it with webp.
 *
 */
class Shortpixel_Change_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'shortpixel_change';

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
	protected $per_page = 5;

	/** @var null|Simply_Static\Url_Extractor */
	protected $extractor = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options       = Simply_Static\Options::instance();
		$this->options = $options;

		if ( $this->options->get( 'shortpixel_change_next_page' ) ) {
			$this->page = $this->options->get( 'shortpixel_change_next_page' );
		}
	}

	/**
	 * Get filtered par page.
	 *
	 * @return mixed|null
	 */
	protected function get_per_page() {
		return apply_filters( 'simply_static_shortpixel_change_per_page', $this->per_page );
	}

	public function get_total_pages() {
		global $wpdb;

		// Sum the total number of optimized entries across all attachments (not just rows)
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s", '_optimized_shortpixel' ), ARRAY_A );
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

	public function get_optimized_files() {
		global $wpdb;

		$offset = 0;

		if ( $this->page > 0 ) {
			$offset = $this->page - 1;
		}

		if ( $offset ) {
			$offset *= $this->per_page;
		}

		$queued_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT {$offset}, {$this->get_per_page()}", '_optimized_shortpixel' ), ARRAY_A );

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
			do_action( 'ssp_finished_shortpixel_change', $this );
			$this->options->destroy( 'shortpixel_change_next_page' );
			$this->options->save();
		} else {
			$this->options->set( 'shortpixel_change_next_page', $this->page + 1 );
			$this->options->save();
		}

		return $pages_processed >= $total_pages;
	}

	public function get_extractor() {
		if ( null == $this->extractor ) {
			$this->extractor = new Simply_Static\Url_Extractor( null ); // No need for page. Used for converting URLs..
		}

		return $this->extractor;
	}

	public function convert_url( $url ) {
		$extractor = $this->get_extractor();

		if ( ! is_callable( [ $extractor, 'convert_url' ] ) ) {
			return $url;
		}

		return $extractor->convert_url( $url );
	}

	/**
	 * Upload files to Shortpixel.
	 *
	 * @param string $destination_dir The directory to put the files.
	 *
	 * @return array
	 */
	public function download_static_files() {

		$queued_files    = $this->get_optimized_files();
		$total_pages     = $this->get_total_pages();
		$last_page       = $this->page > 0 ? $this->page - 1 : 0;
		$pages_processed = $last_page * $this->per_page;

		// Show how many items are left before processing this batch.
		$this->save_status_message( sprintf( __( 'Replacing optimized files with webp... Left: %d', 'simply-static-pro' ), max( 0, $total_pages - $pages_processed ) ) );

		$file_urls = [];
		$wp_content_url = home_url('/wp-content');

		foreach ( $queued_files as $meta ) {
			$files = maybe_unserialize( $meta['meta_value'] );

			if ( ! $files ) {
				continue;
			}

			foreach ( $files as $file_info ) {
				$file_url  = $file_info['img_url'];
				$file_path = str_replace( $wp_content_url, WP_CONTENT_DIR, $file_url );
				$file_path = str_replace( '/', DIRECTORY_SEPARATOR, $file_path );
				$webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path );

				if ( ! file_exists( $webp_path ) ) {
					continue;
				}

				$webp_url = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_url );

				$file_urls[] = [
					'img_url'  => $this->convert_url( $file_url ),
					'webp_url' => $this->convert_url( $webp_url )
				];
			}
		}

		if ( ! empty( $file_urls ) ) {
			$dir   = $this->options->get_archive_dir();
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $files as $file ) {
				if ( $file->isFile() && strtolower( $file->getExtension() ) === 'html' ) {
					$content  = file_get_contents( $file->getRealPath() );
					$modified = false;

					foreach ( $file_urls as $file_url ) {
						$new_content = str_replace( $file_url['img_url'], $file_url['webp_url'], $content );

						if ( $new_content !== $content ) {
							$content  = $new_content;
							$modified = true;
						}
					}

					if ( $modified ) {
						file_put_contents( $file->getRealPath(), $content );
					}
				}
			}
		}

		// Count processed items (actual URLs we attempted to replace)
		$processed_now = count( $file_urls );
		$pages_processed += $processed_now;
		\Simply_Static\Util::debug_log( 'ShortPixel change processed this batch: ' . $processed_now . ' items; total processed so far: ' . $pages_processed . ' of ' . $total_pages );

		// Show updated remaining items after processing this batch.
		$this->save_status_message( sprintf( __( 'Replacing optimized files with webp... Left: %d', 'simply-static-pro' ), max( 0, $total_pages - $pages_processed ) ) );

		return array( $pages_processed, $total_pages );
	}


}
