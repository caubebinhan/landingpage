<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Util;

/**
 * Class to handle settings for fuse.
 */
class Helper {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Search_Settings.
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor for Search_Settings.
	 */
 public function __construct() {
        add_action( 'wp_head', array( $this, 'add_meta_tags' ) );
        add_action( 'wp_footer', array( $this, 'insert_post_id' ) );
        add_action( 'ss_after_setup_task', array( $this, 'add_configs' ), 99 );
    }

    // Builder detection moved to misc/Builder_Support for reuse.

	/**
	 * Add config URL path as meta tag.
	 *
	 * @return void
	 */
 public function add_meta_tags() {
        // Do not output our meta tags while a visual builder is active to avoid conflicts (e.g., Divi).
        if ( Builder_Support::is_builder_editing_context() ) {
            return;
        }
        // Skip adding meta tags?
        $skip_meta = apply_filters( 'ssp_skip_meta', false );

		if ( $skip_meta ) {
			return;
		}

		$options              = get_option( 'simply-static' );
		$comment_endpoint_url = esc_url( untrailingslashit( get_bloginfo( 'url' ) ) . '/wp-comments-post.php' );

		// Check for Basic Auth.
		if ( ! empty( $options['http_basic_auth_username'] ) && ! empty( $options['http_basic_auth_password'] ) ) {
			$url_parts            = parse_url( $comment_endpoint_url );
			$comment_endpoint_url = $url_parts['scheme'] . '://' . $options['http_basic_auth_username'] . ':' . $options['http_basic_auth_password'] . '@' . $url_parts['host'];
		}

		// Get the config path.
		$upload_dir = wp_upload_dir();
		$config_dir = apply_filters( 'ssp_config_dir', $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR );

		if ( defined( 'SSP_CONFIG_DIR' ) ) {
			$config_dir = SSP_CONFIG_DIR;
		}

		if ( is_dir( $config_dir ) ) {
			$replace = '';
			
			// Only use relative_path when destination is set to Relative Path.
			if ( isset( $options['destination_url_type'] ) && $options['destination_url_type'] === 'relative' && ! empty( $options['relative_path'] ) ) {
				$replace = untrailingslashit( $options['relative_path'] );
			}

 		// We might have to overwrite base URL based on optimization settings.
 		$base_url = untrailingslashit( $upload_dir['baseurl'] );

 		if ( ! empty( $options['wp_uploads_directory'] ) && $options['wp_uploads_directory'] !== 'uploads' ) {
 			// Build the full replacement path: {content_dir}/{uploads_dir}
 			$content_dir = ! empty( $options['wp_content_directory'] ) ? $options['wp_content_directory'] : 'wp-content';
 			$new_uploads_path = $content_dir . '/' . $options['wp_uploads_directory'];
 			$base_url = untrailingslashit( str_replace( 'wp-content/uploads', $new_uploads_path, $base_url ) );
 		}

			$config_relative_path = apply_filters( 'ssp_config_relative_dir', str_replace( get_site_url(), $replace, $base_url . '/simply-static/configs/' ) );
			$version = get_option( 'ssp_config_version', time() );
			?>
			<?php if ( is_dir( $config_dir ) ) : ?>
                <meta name="ssp-config-path" content="<?php echo esc_html( $config_relative_path ); ?>">
                <meta name="ssp-config-version" content="<?php echo esc_attr( $version ); ?>">
			<?php endif; ?>

			<?php if ( isset( $options['use_comments'] ) && $options['use_comments'] ) : ?>
                <meta name="ssp-comment-redirect-url" content="<?php echo esc_url( $options['comment_redirect'] ); ?>">
                <meta name="ssp-comment-endpoint"
                      content="<?php echo base64_encode( $comment_endpoint_url ); ?>">
			<?php endif; ?>
			<?php
		}
	}

	/**
	 * Add post id to each page.
	 *
	 * @return void
	 */
 public function insert_post_id() {
        // Avoid DOM injections when a visual builder is active (e.g., Divi frontend editor).
        if ( Builder_Support::is_builder_editing_context() ) {
            return;
        }
        ?>
        <span class="ssp-id" style="display:none"><?php echo esc_html( get_the_id() ); ?></span>
        <?php
    }

	/**
	 * Add configs to static export.
	 *
	 * @return void
	 */
	public function add_configs() {
		$upload_dir = wp_upload_dir();
		$config_dir = apply_filters( 'ssp_config_dir', $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR );


		if ( defined( 'SSP_CONFIG_DIR' ) ) {
			$config_dir = SSP_CONFIG_DIR;
		}

		if ( is_dir( $config_dir ) ) {
			// Update a cache-busting version for config files on each export setup.
			update_option( 'ssp_config_version', time() );

			$config_files = scandir( $config_dir );

			foreach ( $config_files as $config_file ) {
				if ( is_file( $config_dir . $config_file ) ) {
					$url = untrailingslashit( $upload_dir['baseurl'] ) . '/simply-static/configs/' . $config_file;
					Simply_Static\Util::debug_log( 'Adding config file to queue: ' . $url );
					$static_page = Simply_Static\Page::query()->find_or_initialize_by( 'url', $url );
					$static_page->set_status_message( __( 'Config File', 'simply-static-pro' ) );
					$static_page->found_on_id = 0;
					$static_page->save();
				}
			}
		}
	}

	/**
	 * Returns the global $wp_filesystem with credentials set.
	 * Returns null in case of any errors.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	public static function get_file_system() {
		global $wp_filesystem;

		$success = true;

		// Initialize the file system if it has not been done yet.
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';

			$constants = array(
				'hostname'    => 'FTP_HOST',
				'username'    => 'FTP_USER',
				'password'    => 'FTP_PASS',
				'public_key'  => 'FTP_PUBKEY',
				'private_key' => 'FTP_PRIKEY',
			);

			$credentials = array();

			// We provide credentials based on wp-config.php constants.
			// Reference https://developer.wordpress.org/apis/wp-config-php/#wordpress-upgrade-constants
			foreach ( $constants as $key => $constant ) {
				if ( defined( $constant ) ) {
					$credentials[ $key ] = constant( $constant );
				}
			}

			$success = WP_Filesystem( $credentials );
		}

		if ( ! $success || $wp_filesystem->errors->has_errors() ) {
			return null;
		}

		return $wp_filesystem;
	}

    public static function get_changed_url( $url ) {
        return apply_filters( 'simply_static_change_url', $url );
    }

    public static function get_changed_path( $path ) {
        return apply_filters( 'simply_static_change_path', $path );
    }
}
