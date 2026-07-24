<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Plugin;
use Simply_Static\Util;

/**
 * Class which handles GitHub commits.
 */
class Github_Commit_Task extends Simply_Static\Task {

	use Simply_Static\canProcessPages;
	use Simply_Static\canTransfer;

	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'github_commit';

	/**
	 * Temp directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Github db class.
	 *
	 * @var null|Github_Database
	 */
	protected $database = null;

	/**
	 * To hold blobls for current processing.
	 *
	 * @var array
	 */
	protected $blobs = [];

	/**
	 * GitHub Rate Limits
	 * @var null
	 */
	protected $rate_limits = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options        = Options::instance();
		$this->options  = $options;
		$this->temp_dir = $options->get_archive_dir();

		if ( ! empty( $this->options->get( 'github_batch_size' ) ) ) {
			$this->batch_size = $this->options->get( 'github_batch_size' );
		}

		$this->batch_size = apply_filters( 'ssp_github_tree_chunk_size', $this->batch_size ); // Shouldn't be higher than 1000: https://retool.com/blog/gotchas-git-github-api#resolution
	}

	/**
	 * Set Start Time
	 *
	 * @return void
	 */
	public function set_start_time() {
		$start_time = get_option( 'ssp_github_commit_start_time' );

		if ( ! $start_time ) {
			$start_time = Util::formatted_datetime();
			update_option( 'ssp_github_commit_start_time', $start_time );
		}

		$this->start_time = $start_time;
	}

	/**
	 * @return array|null
	 */
	protected function get_rate_limits() {
		if ( null === $this->rate_limits ) {
			$database          = $this->get_database();
			$this->rate_limits = $database->get_rate_limits();
		}

		return $this->rate_limits;
	}

	/**
	 * Return the Github Database.
	 *
	 * @return object|Github_Database|null
	 */
	protected function get_database() {
		if ( null === $this->database ) {
			$this->database = Github_Database::get_instance();
		}

		return $this->database;
	}

	protected function get_blobs() {
		return $this->blobs;
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
		return sprintf( __( "Committed %d of %d files", 'simply-static-pro' ), $processed, $total );
	}

	/**
	 * Push a batch of files from the temp dir to GitHub.
	 *
	 * @return boolean true if done, false if not done.
	 * @throws Exception When the GitHub API returns an error.
	 */
	public function perform(): bool {
		// Prepare default option state.
		$options    = get_option( 'simply-static' );
		$filesystem = Helper::get_file_system();
		$repository = Github_Repository::get_instance();

		// Prepare GitHub Database API.
		$this->database = $this->get_database();

		// Check if branch exists, if not, fail early.
		try {
			$api      = Plugin::instance()->get_integration( 'github' )->api( 'data' );
			$ref      = sprintf( 'heads/%s', $repository->branch );
			$response = $api->get_reference( $repository->user, $repository->repository, $ref );

			if ( empty( $response['object']['sha'] ) ) {
				$message = sprintf( __( 'GitHub branch "%s" not found in %s/%s. Export canceled.', 'simply-static-pro' ), $repository->branch, $repository->user, $repository->repository );
				Util::debug_log( $message );
				$this->save_status_message( $message, 'error' );

				return true; // Export done.
			}
		} catch ( \Exception $e ) {
			$message = sprintf( __( 'GitHub preflight failed: %s. Export canceled.', 'simply-static-pro' ), $e->getMessage() );
			Util::debug_log( $message );
			$this->save_status_message( $message, 'error' );

			return true; // Export done.
		}

		$done = $this->process_pages();

		// Handle rate limits.
		$rate_limit      = $this->get_rate_limits();
		$should_sleep    = get_option( 'ssp_github_should_sleep' );
		$remaining_pages = count( $this->get_pages_to_process() );

		if ( intval( $rate_limit['remaining'] ) < $remaining_pages && false === $should_sleep ) {
			// Calculate time to wait.
			$now     = time();
			$seconds = ( intval( $rate_limit['reset'] ) - $now );

			update_option( 'ssp_github_should_sleep', true );

			Util::debug_log( 'You exceeded the GitHub API rate limit. We need to wait for ' . $seconds . ' seconds.' );
			sleep( $seconds );
		}

		if ( false !== $should_sleep ) {
			$now     = time();
			$seconds = ( intval( $rate_limit['reset'] ) - $now );

			Util::debug_log( 'We are still waiting for the GitHub API rate limit to reset. We need to wait for ' . $seconds . '. seconds.' );
			sleep( $seconds );
		}

		$blobs = $this->get_blobs();

		// return true when done (no more pages).
		if ( ! empty( $blobs ) ) {
			try {
				// Create new tree with blobs.
				$tree = $this->database->create_tree( $blobs );

				// Check that tree is not empty to avoid empty commits.
				if ( is_array( $tree ) && ! empty( $tree ) && isset( $tree['tree-sha'] ) ) {
					// Now create a new commit with the tree.
					$commit_message = apply_filters( 'ssp_github_commit_message', 'Updated/Added ' . $this->options->get( 'archive_name' ) );
					$this->database->commit( $commit_message, $tree );
				}
			} catch ( \Exception $e ) {
				Util::debug_log( 'Could not create a tree. Error: ' . $e->getMessage() );
				Util::debug_log( 'Blobs: ' . print_r( $blobs, true ) );
				$this->save_status_message( 'An error occurred while committing files: ' . $e->getMessage() . "\n File info in debug log.", 'github_coomit_error' );
			}
		}

		if ( ! $done ) {
			return $done;
		}

		// 404 page?
		if ( isset( $options['generate_404'] ) && realpath( $this->temp_dir . '404/index.html' ) ) {
			$error_file_path    = $this->temp_dir . '404/index.html';
			$error_page_content = $filesystem->get_contents( $error_file_path );

			$repository->add_file( '404.html', $error_page_content, __( 'Added the 404 page.', 'simply-static-pro' ) );
		}

		// Notify GitHub and external Webhook.
		$this->notify_github();

		// Maybe notify webhook.
		if ( $this->options->get( 'github_webhook_url' ) ) {
			$this->notify_external_webhook();
		}

		do_action( 'ssp_finished_github_transfer', $this->temp_dir );

		return $done;
	}

	/**
	 * Cleanup before starting a new Github export.
	 *
	 * @return void
	 */
	public function cleanup() {
		// Handle cleanup.
		delete_option( 'ssp_github_should_sleep' );
		delete_option( 'ssp_github_commit_start_time' );
		delete_option( 'ssp_github_rate_limits' );

		self::delete_total_pages();
	}

	/**
	 * Get the folder path.
	 *
	 * @return string
	 */
	protected function get_folder_path() {
		$folder_path = $this->options->get( 'github_folder_path' );

		if ( ! $folder_path ) {
			return '';
		}

		return trailingslashit( $folder_path );
	}

	/**
	 * Process the page.
	 *
	 * @param Page $static_page Page object.
	 *
	 * @return void
	 */
	protected function process_page( $static_page ) {

		$rate_limit = $this->get_rate_limits();

		if ( $rate_limit['remaining'] <= 0 ) {
			throw new Simply_Static\Skip_Further_Processing_Exception( __( 'Rate limit was reached. We are skipping further processing until available again.', 'simply-static-pro' ) );
		}

		$filesystem         = \simply_static_pro\Helper::get_file_system();
		$page_file_path     = $this->get_page_file_path( $static_page );
		$file_path          = Util::combine_path( $this->temp_dir, $page_file_path );
		$throttle_request   = apply_filters( 'ssp_throttle_github_request', false );
		$github_folder_path = $this->get_folder_path();

		// Throttling active?
		$options = get_option( 'simply-static' );

		if ( ! empty( $options['github_throttle_requests'] ) ) {
			$throttle_request = true;
		}

		if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
			$content = $filesystem->get_contents( $file_path );

			// Prepare file for commit.
			$relative_path = str_replace( Util::normalize_slashes( $this->temp_dir ), $github_folder_path, $file_path );

			// Fixing possible empty spaces.
			$relative_path = str_replace( '//', '/', $relative_path );

			// Maybe throttle request.
			if ( $throttle_request ) {
				sleep( 1 );
			}

			$blob = $this->database->create_blob( $file_path, $relative_path, $content );

			if ( is_array( $blob ) ) {
				$this->blobs[] = $blob;
			}

			do_action( 'ssp_file_transferred_to_github', $static_page );
		}

	}

	/**
	 * Notify external Webhook after Simply Static finished static export.
	 *
	 * @return void
	 */
	public function notify_external_webhook() {
		$webhook_args = apply_filters( 'ssp_webhook_args', array() );
		wp_remote_post( esc_url( $this->options->get( 'github_webhook_url' ) ), $webhook_args );
	}

	/**
	 * Notify GitHub after Simply Static finished static export.
	 *
	 * @return void
	 */
	public function notify_github() {
		$options = get_option( 'simply-static' );

		// Maybe use constant instead of options.
		if ( defined( 'SSP_GITHUB' ) ) {
			$options = SSP_GITHUB;
		}

		if ( 'github' !== $options['delivery_method'] ) {
			return;
		}

		$user = $options['github_user'];
		if ( empty( $user ) ) {
			return;
		}

		$access_token = $options['github_personal_access_token'];
		if ( empty( $access_token ) ) {
			return;
		}

		$repository = $options['github_repository'];
		if ( empty( $repository ) ) {
			return;
		}

		$webhook = 'https://api.github.com/repos/' . $user . '/' . $repository . '/dispatches';

		$webhook_args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/vnd.github+json',
			),
			'body'    => wp_json_encode( array( 'event_type' => 'repository_dispatch' )
			)
		);

		wp_remote_post( esc_url( $webhook ), $webhook_args );
	}
}
