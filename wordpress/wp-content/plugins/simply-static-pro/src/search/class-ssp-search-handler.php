<?php
namespace simply_static_pro;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles search-related boot logic that must run within the Pro plugin lifecycle.
 *
 * Currently ensures the native WordPress search URL (s=test) is explicitly
 * enqueued during the Setup task so it will be fetched and exported.
 */
class Search_Handler {
	/** @var Search_Handler */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Search_Handler
	 */
	public static function get_instance() : Search_Handler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WP hooks/filters.
	 *
	 * @return void
	 */
 private function register_hooks() : void {
        // Ensure the native WordPress search URL (?s=test) is explicitly enqueued during Setup.
        add_filter( 'ss_setup_task_additional_urls', array( $this, 'ensure_native_search_url' ) );

        // When deploying to Local Directory, mirror Fuse search artifacts from archive to destination
        add_action( 'ss_before_finish_transferring_files_locally', array( __CLASS__, 'transfer_fuse_artifacts_to_local_dir' ), 10, 2 );

        // When deploying to external services, upload Fuse search artifacts after the main deployment finishes
        add_action( 'ssp_finished_static_studio_transfer', array( __CLASS__, 'transfer_fuse_artifacts_to_studio' ), 10, 1 );
        add_action( 'ssp_finished_bunnycdn_transfer', array( __CLASS__, 'transfer_fuse_artifacts_to_bunny' ), 10, 1 );
        add_action( 'ssp_finished_aws_transfer', array( __CLASS__, 'transfer_fuse_artifacts_to_s3' ), 10, 1 );
        add_action( 'ssp_finished_github_transfer', array( __CLASS__, 'transfer_fuse_artifacts_to_github' ), 10, 1 );
        add_action( 'ssp_finished_sftp_transfer', array( __CLASS__, 'transfer_fuse_artifacts_to_sftp' ), 10, 1 );

		// Initialize unified shortcode/asset controller for search UI (Fuse/Algolia)
		// Keeps type-specific classes focused on indexing/config only.
		// Ensure the class file is loaded (no composer autoload in this context)
		$shortcode_file = __DIR__ . '/class-ssp-search-shortcode.php';
		if ( file_exists( $shortcode_file ) ) {
			require_once $shortcode_file;
		}
		try {
			\simply_static_pro\Search_Shortcode::get_instance();
		} catch ( \Throwable $e ) {
			// Silently ignore if class not available
		}
	}

	/**
	 * Append the native WP search URL to the Additional URLs list during Setup.
	 *
	 * @param string $additional_urls Newline-separated list of URLs.
	 * @return string
	 */
 public function ensure_native_search_url( $additional_urls ) : string {
		if ( ! class_exists( '\\Simply_Static\\Util' ) ) {
			return is_string( $additional_urls ) ? $additional_urls : '';
		}

		$origin     = \Simply_Static\Util::origin_url();
		$search_url = add_query_arg( 's', 'test', trailingslashit( $origin ) );

		// Normalize to a newline-separated list (the option is a textarea string)
		$additional_urls = is_string( $additional_urls ) ? $additional_urls : '';
		$lines           = \Simply_Static\Util::string_to_array( $additional_urls );
		if ( ! in_array( $search_url, $lines, true ) ) {
			$lines[] = $search_url;
		}

		return implode( "\n", $lines );
    }

    /**
     * Copy Fuse search artifacts from archive to Local Directory destination.
     * Runs on ss_before_finish_transferring_files_locally.
     *
     * @param string $destination_dir Absolute Local Directory path.
     * @param string $archive_dir     Absolute archive (temp) path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_local_dir( $destination_dir, $archive_dir ) : void {
        try {
            // Read free plugin options to confirm search is enabled and Fuse is selected
            $ss_options  = get_option( 'simply-static' );
            $use_search  = isset( $ss_options['use_search'] ) ? (bool) $ss_options['use_search'] : false;
            $search_type = isset( $ss_options['search_type'] ) ? $ss_options['search_type'] : 'fuse';

            // Allow override via filter
            $enabled = apply_filters( 'ssp_fuse_copy_to_destination', ( $use_search && 'fuse' === $search_type ), $destination_dir );
            if ( ! $enabled ) {
                return;
            }

            $relative_dir = 'wp-content/uploads/simply-static/configs/';
            $source_dir   = trailingslashit( $archive_dir ) . $relative_dir;
            $dest_dir     = trailingslashit( $destination_dir ) . $relative_dir;

            if ( ! is_dir( $dest_dir ) ) {
                wp_mkdir_p( $dest_dir );
            }

            $files_to_copy = array( 'fuse-index.json', 'fuse-config.json' );
            foreach ( $files_to_copy as $basename ) {
                $src = $source_dir . $basename;
                $dst = $dest_dir . $basename;
                if ( file_exists( $src ) ) {
                    @copy( $src, $dst );
                }
            }
        } catch ( \Throwable $e ) {
            // no-op; avoid fataling the export on copy errors
        }
    }

    /**
     * Check if Fuse search is enabled and should transfer artifacts.
     *
     * @return bool
     */
    protected static function should_transfer_fuse_artifacts() : bool {
        $ss_options  = get_option( 'simply-static' );
        $use_search  = isset( $ss_options['use_search'] ) ? (bool) $ss_options['use_search'] : false;
        $search_type = isset( $ss_options['search_type'] ) ? $ss_options['search_type'] : 'fuse';

        return $use_search && 'fuse' === $search_type;
    }

    /**
     * Get paths to Fuse artifact files in the archive directory.
     *
     * @param string $archive_dir Archive directory path.
     * @return array Array of file paths that exist.
     */
    protected static function get_fuse_artifact_paths( $archive_dir ) : array {
        $relative_dir = 'wp-content/uploads/simply-static/configs/';
        $source_dir   = trailingslashit( $archive_dir ) . $relative_dir;

        $files = array();
        $artifacts = array( 'fuse-index.json', 'fuse-config.json' );

        foreach ( $artifacts as $basename ) {
            $file_path = $source_dir . $basename;
            if ( file_exists( $file_path ) ) {
                $files[] = $file_path;
            }
        }

        return $files;
    }

    /**
     * Upload Fuse search artifacts to Simply Static Studio after deployment.
     * Runs on ssp_finished_static_studio_transfer.
     *
     * @param string $archive_dir Archive directory path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_studio( $archive_dir ) : void {
        try {
            if ( ! self::should_transfer_fuse_artifacts() ) {
                return;
            }

            $files = self::get_fuse_artifact_paths( $archive_dir );
            if ( empty( $files ) ) {
                return;
            }

            if ( class_exists( '\\simply_static_pro\\Simply_Static_Studio_Updater' ) ) {
                Simply_Static_Studio_Updater::upload_files( $files );
                \Simply_Static\Util::debug_log( '[Search_Handler] Uploaded Fuse artifacts to Static Studio: ' . implode( ', ', $files ) );
            }
        } catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( '[Search_Handler] Error uploading Fuse artifacts to Static Studio: ' . $e->getMessage() );
        }
    }

    /**
     * Upload Fuse search artifacts to Bunny CDN after deployment.
     * Runs on ssp_finished_bunnycdn_transfer.
     *
     * @param string $archive_dir Archive directory path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_bunny( $archive_dir ) : void {
        try {
            if ( ! self::should_transfer_fuse_artifacts() ) {
                return;
            }

            $files = self::get_fuse_artifact_paths( $archive_dir );
            if ( empty( $files ) ) {
                return;
            }

            if ( class_exists( '\\simply_static_pro\\Bunny_Updater' ) ) {
                Bunny_Updater::upload_files( $files );
                \Simply_Static\Util::debug_log( '[Search_Handler] Uploaded Fuse artifacts to Bunny CDN: ' . implode( ', ', $files ) );
            }
        } catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( '[Search_Handler] Error uploading Fuse artifacts to Bunny CDN: ' . $e->getMessage() );
        }
    }

    /**
     * Upload Fuse search artifacts to AWS S3 after deployment.
     * Runs on ssp_finished_aws_transfer.
     *
     * @param string $archive_dir Archive directory path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_s3( $archive_dir ) : void {
        try {
            if ( ! self::should_transfer_fuse_artifacts() ) {
                return;
            }

            $files = self::get_fuse_artifact_paths( $archive_dir );
            if ( empty( $files ) ) {
                return;
            }

            $options = \Simply_Static\Options::instance();
            $subdirectory = $options->get( 'aws_subdirectory' );

            // Get S3 client credentials
            $credentials = [
                'aws-bucket'        => $options->get( 'aws_bucket' ),
                'aws-region'        => $options->get( 'aws_region' ),
                'aws-access-key'    => $options->get( 'aws_access_key' ),
                'aws-access-secret' => $options->get( 'aws_access_secret' ),
            ];

            // Maybe use constant instead of options
            if ( defined( 'SSP_AWS' ) ) {
                $config = SSP_AWS;
                $credentials = [
                    'aws-bucket'        => $config['aws_bucket'],
                    'aws-region'        => $config['aws_region'],
                    'aws-access-key'    => $config['aws_access_key'],
                    'aws-access-secret' => $config['aws_access_secret'],
                ];
            }

            if ( class_exists( '\\simply_static_pro\\S3_Client' ) ) {
                $client = new S3_Client();
                $client->set_bucket( $credentials['aws-bucket'] )
                       ->set_api_secret( $credentials['aws-access-secret'] )
                       ->set_api_key( $credentials['aws-access-key'] )
                       ->set_region( $credentials['aws-region'] );

                foreach ( $files as $file_path ) {
                    $relative_path = str_replace( trailingslashit( $archive_dir ), '', $file_path );
                    if ( $subdirectory ) {
                        $relative_path = trailingslashit( rtrim( $subdirectory, '/' ) ) . $relative_path;
                    }
                    $client->upload_file( $file_path, $relative_path );
                }
                \Simply_Static\Util::debug_log( '[Search_Handler] Uploaded Fuse artifacts to AWS S3: ' . implode( ', ', $files ) );
            }
        } catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( '[Search_Handler] Error uploading Fuse artifacts to AWS S3: ' . $e->getMessage() );
        }
    }

    /**
     * Upload Fuse search artifacts to GitHub after deployment.
     * Runs on ssp_finished_github_transfer.
     *
     * @param string $archive_dir Archive directory path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_github( $archive_dir ) : void {
        try {
            if ( ! self::should_transfer_fuse_artifacts() ) {
                return;
            }

            $files = self::get_fuse_artifact_paths( $archive_dir );
            if ( empty( $files ) ) {
                return;
            }

            $options = \Simply_Static\Options::instance();
            $folder_path = $options->get( 'github_folder_path' );
            $folder_path = $folder_path ? trailingslashit( $folder_path ) : '';

            if ( class_exists( '\\simply_static_pro\\Github_Repository' ) ) {
                $repository = Github_Repository::get_instance();
                $filesystem = Helper::get_file_system();

                foreach ( $files as $file_path ) {
                    $relative_path = str_replace( trailingslashit( $archive_dir ), '', $file_path );
                    $content = $filesystem->get_contents( $file_path );
                    if ( false !== $content ) {
                        $commit_path = $folder_path . $relative_path;
                        $repository->add_file( $commit_path, $content, __( 'Added Fuse search index.', 'simply-static-pro' ) );
                    }
                }
                \Simply_Static\Util::debug_log( '[Search_Handler] Uploaded Fuse artifacts to GitHub: ' . implode( ', ', $files ) );
            }
        } catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( '[Search_Handler] Error uploading Fuse artifacts to GitHub: ' . $e->getMessage() );
        }
    }

    /**
     * Upload Fuse search artifacts to SFTP server after deployment.
     * Runs on ssp_finished_sftp_transfer.
     *
     * @param string $archive_dir Archive directory path.
     * @return void
     */
    public static function transfer_fuse_artifacts_to_sftp( $archive_dir ) : void {
        try {
            if ( ! self::should_transfer_fuse_artifacts() ) {
                return;
            }

            $files = self::get_fuse_artifact_paths( $archive_dir );
            if ( empty( $files ) ) {
                return;
            }

            if ( class_exists( '\\simply_static_pro\\SFTP' ) ) {
                $sftp = new SFTP();

                foreach ( $files as $file_path ) {
                    $relative_path = str_replace( trailingslashit( $archive_dir ), '', $file_path );
                    $sftp->upload( $relative_path );
                }
                \Simply_Static\Util::debug_log( '[Search_Handler] Uploaded Fuse artifacts to SFTP: ' . implode( ', ', $files ) );
            }
        } catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( '[Search_Handler] Error uploading Fuse artifacts to SFTP: ' . $e->getMessage() );
        }
    }
}
