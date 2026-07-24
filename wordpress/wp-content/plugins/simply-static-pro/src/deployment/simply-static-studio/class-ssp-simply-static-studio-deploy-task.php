<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;

/**
 * Class which handles Simply Static Studio deployments.
 */
class Simply_Static_Studio_Deploy_task extends Simply_Static\Task {

	use Simply_Static\canProcessPages;
	use Simply_Static\canTransfer;

	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'simply_static_studio_deploy';

	/**
	 * Temp directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	protected $cdn_path = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options = Options::instance();

		$this->options  = $options;
		$this->temp_dir = $options->get_archive_dir();
	}

	/**
	 * Copy a batch of files from the temp dir to the destination dir
	 *
	 * @return boolean true if done, false if not done.
  */
    public function perform() {
        // Reset counters at the beginning of a deployment run.
        Simply_Static_Studio_Updater::reset_counters();

        $done = $this->process_pages();

        if ( $done ) {
            Simply_Static_Studio_Updater::batch_remaining_files();

			if ( $this->options->get( 'destination_url_type' ) == 'absolute' ) {
				$destination_url = trailingslashit( $this->options->get_destination_url() );
				$message         = __( 'Destination URL:', 'simply-static-pro' ) . ' <a href="' . $destination_url . '" target="_blank">' . $destination_url . '</a>';
				$this->save_status_message( $message, 'destination_url' );
			}

			do_action( 'ssp_finished_static_studio_transfer', $this->temp_dir );

			// Maybe add 404.
			$this->add_404();

			// Clear cache.
			Simply_Static_Studio_Updater::purge_cache();

            // Summary logging.
            $counters = Simply_Static_Studio_Updater::get_counters();
            \Simply_Static\Util::debug_log( sprintf(
                '[StaticStudio] Deployment finished. Queued: %d | Uploaded: %d | Failed: %d | Skipped: %d',
                $counters['queued'],
                $counters['uploaded'],
                $counters['failed'],
                $counters['skipped']
            ) );
		}

		return $done;
	}

	/**
	 * @param Page $static_page Page object.
	 *
	 * @return void
	 */
	protected function process_page( $static_page ) {
		$page_file_path = $this->get_page_file_path( $static_page );
		$file_path      = $this->temp_dir . $page_file_path;

		if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
			Simply_Static_Studio_Updater::batch_file( $file_path, $this->batch_size );
		}

		do_action( 'ssp_file_transfered_to_static_studio', $static_page, $this->temp_dir );
	}

	/**
	 * Maybe add a custom 404 page.
	 *
	 * @return void
	 */
	public function add_404() {
		$filesystem = Helper::get_file_system();

		if ( $this->options->get( 'generate_404' ) && realpath( $this->temp_dir . '404/index.html' ) ) {
			// Rename and copy file.
			$src_error_file  = $this->temp_dir . '404/index.html';
			$dst_error_file  = $this->temp_dir . 'bunnycdn_errors/404.html';
			$error_directory = dirname( $dst_error_file );

			if ( ! is_dir( $error_directory ) ) {
				wp_mkdir_p( $error_directory );
				chmod( $error_directory, 0777 );
			}

			$filesystem->copy( $src_error_file, $dst_error_file, true );

			// Upload 404 template file.
			$error_file_path = realpath( $this->temp_dir . 'bunnycdn_errors/404.html' );
			$files           = [ $error_file_path ];

			if ( $error_file_path ) {
				Simply_Static_Studio_Updater::upload_files( $files );
			}
		}
	}
}
