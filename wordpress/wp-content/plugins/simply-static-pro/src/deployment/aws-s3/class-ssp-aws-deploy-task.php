<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Util;

/**
 * Class which handles AWS Deployment.
 */
class AWS_Deploy_Task extends Simply_Static\Task {

	use Simply_Static\canTransfer;
	use Simply_Static\canProcessPages;


	protected $throttle_request = true;

	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'aws_deploy';

	/**
	 * Temp directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * @var null|S3_Client;
	 */
	protected $client = null;


	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options          = Options::instance();
		$this->options    = $options;
		$this->temp_dir   = $options->get_archive_dir();
		$this->start_time = $options->get( 'archive_start_time' );
		$this->client     = $this->get_client();
	}

	protected function get_page_file_path( $static_page ) {
		return apply_filters( 'ss_get_page_file_path_for_transfer', $static_page->file_path, $static_page );
	}

	/**
	 * Transfer directory to S3 bucket.
	 *
	 * @return boolean true if done, false if not done.
	 * @throws Exception When the GitHub API returns an error.
	 */
	public function perform(): bool {
		$use_single = get_option( 'simply-static-use-single' );
		$use_build  = get_option( 'simply-static-use-build' );

		if ( $this->get_generate_type() === 'update' || ( ! empty( $use_single ) || ! empty( $use_build ) ) ) {
			$this->throttle_request = apply_filters( 'ssp_throttle_do_request', false );

			$done = $this->process_pages();
		} else {
			$done = $this->transfer_directory( $this->temp_dir );
		}

		// return true when done (no more pages).
		if ( $done ) {
			do_action( 'ssp_finished_aws_transfer', $this->temp_dir );

			// Maybe clear Cloudfront cache.
			if ( $this->options->get( 'aws_distribution_id' ) ) {
				$this->client->invalidate();
			}

			// Maybe notify webhook.
			if ( $this->options->get( 'aws_webhook_url' ) ) {
				$this->notify_external_webhook();
			}
		}

		return $done;
	}

	/**
	 * Transfer directory to S3.
	 *
	 * @param string $directory The directory with the files to transfer.
	 *
	 * @return bool
	 * @throws Exception When the transfer fails.
	 */
	protected function transfer_directory( string $directory ) {
		$static_pages = Page::query()
		                    ->where( "file_path IS NOT NULL" )
		                    ->where( "file_path != ''" )
		                    ->where( "( last_transferred_at < ? OR last_transferred_at IS NULL )", $this->start_time )
		                    ->find();

		$pages_remaining = count( $static_pages );
		$total_pages     = Page::query()
		                       ->where( "file_path IS NOT NULL" )
		                       ->where( "file_path != ''" )
		                       ->count();

		$pages_processed = $total_pages - $pages_remaining;
		Util::debug_log( 'Total pages: ' . $total_pages . '; Pages remaining: ' . $pages_remaining );

		$subdirectory = $this->options->get( 'aws_subdirectory' );

		// Subdirectory?
		if ( $subdirectory ) {
			$this->client->transfer_directory( $this->temp_dir, $subdirectory );
		} else {
			$this->client->transfer_directory( $this->temp_dir );
		}

		while ( $static_page = array_shift( $static_pages ) ) {
			$page_file_path = $this->get_page_file_path( $static_page );
			$file_path      = $this->temp_dir . $page_file_path;

			if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
				do_action( 'ssp_file_transferred_to_aws', $static_page, $directory );
				$static_page->last_transferred_at = Util::formatted_datetime();
				$static_page->save();
			}
		}

		if ( $pages_processed >= $total_pages ) {
			$message = sprintf( __( 'Uploaded %d of %d pages/files', 'simply-static-pro' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );
		}

		return $pages_processed >= $total_pages;
	}

	protected function get_client() {
		if ( null === $this->client ) {
			$credentials = [
				'aws-bucket'        => $this->options->get( 'aws_bucket' ),
				'aws-region'        => $this->options->get( 'aws_region' ),
				'aws-access-key'    => $this->options->get( 'aws_access_key' ),
				'aws-access-secret' => $this->options->get( 'aws_access_secret' ),
			];

			// Maybe use constant instead of options.
			if ( defined( 'SSP_AWS' ) ) {
				$config = SSP_AWS;

				$credentials = [
					'aws-bucket'        => $config['aws_bucket'],
					'aws-region'        => $config['aws_region'],
					'aws-access-key'    => $config['aws_access_key'],
					'aws-access-secret' => $config['aws_access_secret'],
				];
			}

			$client = new S3_Client();

			$client->set_bucket( $credentials['aws-bucket'] )
			       ->set_api_secret( $credentials['aws-access-secret'] )
			       ->set_api_key( $credentials['aws-access-key'] )
			       ->set_region( $credentials['aws-region'] );

			$this->client = $client;
		}

		return $this->client;
	}


	protected function process_page( $static_page ) {
		$subdirectory   = $this->options->get( 'aws_subdirectory' );
		$page_file_path = $this->get_page_file_path( $static_page );
		$file_path      = $this->temp_dir . $page_file_path;

		if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {

			if ( $subdirectory ) {
				$subdirectory   = rtrim( $subdirectory, '/' );
				$page_file_path = trailingslashit( $subdirectory ) . $page_file_path;
			}

			$upload = $this->client->upload_file( $file_path, $page_file_path );

			// Maybe throttle request.
			if ( $this->throttle_request ) {
				sleep( 1 );
			}

			if ( ! $upload ) {
				throw new \Exception( __( 'Could not upload the file to AWS S3 Bucket', 'simply-static-pro' ) );
			}
		}

		do_action( 'ssp_file_transferred_to_aws', $static_page, $this->temp_dir );

	}

	/**
	 * Notify external Webhook after Simply Static finished static export.
	 *
	 * @return void
	 */
	public function notify_external_webhook() {
		$webhook_args = apply_filters( 'ssp_webhook_args', array() );
		wp_remote_post( esc_url( $this->options->get( 'aws_webhook_url' ) ), $webhook_args );
	}
}
