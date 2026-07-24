<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Util;

// Set to true to enable logging.
define( 'NET_SFTP_LOGGING', true );

/**
 * Class which handles SFTP Deployment.
 */
class SFTP_Deploy_Task extends Simply_Static\Task {

	use Simply_Static\canProcessPages;
	use Simply_Static\canTransfer;

	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'sftp_deploy';

	/**
	 * Given start time for the export.
	 *
	 * @var string
	 */
	protected $start_time;

	/**
	 * Get SFTP
	 * @var null|SFTP
	 */
	protected $sftp = null;

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

		$options        = Options::instance();
		$this->options  = $options;
		$this->temp_dir = $options->get_archive_dir();
	}

	public function set_start_time() {
		$this->start_time = get_option( 'ssp_sftp_deploy_start_time' );

		if ( ! $this->start_time ) {
			$start = Util::formatted_datetime();
			update_option( 'ssp_sftp_deploy_start_time', $start );
			$this->start_time = $start;
		}

		return $this->start_time;
	}

	/**
	 * Push a batch of files from the temp dir to SFTP folders.
	 *
	 * @return boolean true if done, false if not done.
	 * @throws Exception When the GitHub API returns an error.
	 */
	public function perform(): bool {
		$this->get_start_time();

		$sftp = $this->get_sftp();

		if ( ! $sftp ) {
			$this->save_status_message( __( 'We could not authenticate with SFTP. Stopping SFTP upload.', 'simply-static-pro' ) );

			return true; // Returning TRUE to stop this task.
		}

		$done = $this->process_pages();

		if ( $done ) {
			// Maybe add 404.
			$this->add_404();

			do_action( 'ssp_finished_sftp_transfer', $this->temp_dir );
		}

		return $done;
	}

	/**
	 * Cleanup
	 *
	 * @return void
	 */
	public function cleanup() {
		// Removing cached time.
		delete_option( 'ssp_sftp_deploy_start_time' );

		self::delete_total_pages();
	}

	/**
	 * @param Page $static_page Page object.
	 *
	 * @return void
	 */
	protected function process_page( $static_page ) {
		$page_file_path   = $this->get_page_file_path( $static_page );
		$file_path        = $this->temp_dir . $page_file_path;
		$throttle_request = apply_filters( 'ssp_throttle_sftp_request', false );

		if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
			$upload = $this->sftp->upload( $page_file_path );

			if ( is_wp_error( $upload ) ) {
				throw new \Exception( $upload->get_error_message() );
			}

			// Maybe throttle request.
			if ( $throttle_request ) {
				sleep( 1 );
			}
		}

		do_action( 'ssp_file_transferred_to_sftp', $static_page, $this->temp_dir );
	}


	/**
	 * Maybe add a custom 404 page.
	 *
	 * @return void
	 */
	public function add_404() {
		$options = get_option( 'simply-static' );

		if ( $options['generate_404'] && realpath( $this->temp_dir . '404/index.html' ) ) {
			$this->get_sftp();
			$this->sftp->upload( '404/index.html' );
		}
	}

	/**
	 * @return false|SFTP|null
	 */
	public function get_sftp() {
		if ( $this->sftp === null ) {
			$this->sftp = new SFTP();

			return $this->sftp->get_sftp();
		}

		return $this->sftp;
	}

	protected function get_page_file_path( $static_page ) {
		return apply_filters( 'ss_get_page_file_path_for_transfer', $static_page->file_path, $static_page );
	}
}