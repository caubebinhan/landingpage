<?php

namespace simply_static_pro;

use Simply_Static\Options;
use Simply_Static\Util;
use Simply_Static\Page;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro task to discover changed URLs specifically for Update exports.
 *
 * This task is only meant to run before fetch_urls when the export type is "update".
 * It loads a pro-only crawler that is not registered globally and therefore cannot
 * run in other contexts.
 */
class Discover_Changes_Task extends \Simply_Static\Task {
	/**
	 * Task name.
	 *
	 * @var string
	 */
	public static $task_name = 'discover_changes';

	/**
	 * Perform the task.
	 *
	 * @return bool|\WP_Error
	 */
	public function perform() {
		// Safety: Run only on update exports.
		$generate_type = $this->options->get( 'generate_type' );
		if ( 'update' !== $generate_type ) {
			return true; // Nothing to do for other export types.
		}

		// Clear the URL queue first so we only fetch URLs added by the Detect Changes crawler.
		// This mirrors the behavior of a full export where the queue is reset before discovery.
		try {
			Page::query()->delete_all();
			$this->save_status_message( __( 'Cleared URL queue before detection', 'simply-static-pro' ) );
		} catch ( \Throwable $e ) {
		}

		$this->save_status_message( __( 'Discovering changed URLs', 'simply-static-pro' ) );

		// Include the base crawler class from Simply Static core.
		require_once SIMPLY_STATIC_PATH . 'src/crawler/class-ss-crawler.php';

  // Include our pro-only crawler (not globally registered anywhere).
  $pro_crawler_path = SIMPLY_STATIC_PRO_PATH . 'src/crawler/class-ssp-changes-crawler.php';
		if ( file_exists( $pro_crawler_path ) ) {
			require_once $pro_crawler_path;
		} else {
			// If for any reason the file is missing, continue gracefully.
			return true;
		}

		// Instantiate and run the crawler.
		$crawler_class = '\\simply_static_pro\\Crawler\\Changes_Crawler';
		if ( ! class_exists( $crawler_class ) ) {
			return true;
		}

		/** @var \simply_static_pro\Crawler\Changes_Crawler $crawler */
		$crawler = new $crawler_class();

		// Allow disabling via dependency_active / is_active semantics if needed.
		if ( method_exists( $crawler, 'dependency_active' ) && ! $crawler->dependency_active() ) {
			return true;
		}

		// Track URLs that would be processed in current export before/after.
		$archive_start_time = $this->options->get( 'archive_start_time' );
		$initial_count = Page::query()->where( 'last_checked_at < ? OR last_checked_at IS NULL', $archive_start_time )->count();

		$added = 0;
		if ( method_exists( $crawler, 'add_urls_to_queue' ) ) {
			$added = (int) $crawler->add_urls_to_queue();
		}

		// Added URLs are reflected in status message below

		// After discovery, compute the delta to show a meaningful message.
		$urls_for_current_export = Page::query()->where( 'last_checked_at < ? OR last_checked_at IS NULL', $archive_start_time )->count();
		$new_urls_for_export = max( 0, $urls_for_current_export - $initial_count );

		$this->save_status_message( sprintf( __( 'Added %d URLs via Detect Changes Crawler', 'simply-static-pro' ), $new_urls_for_export ) );

		// Hook for extensibility.
		do_action( 'ssp_after_discover_changes', $added, $new_urls_for_export );

		return true;
	}
}
