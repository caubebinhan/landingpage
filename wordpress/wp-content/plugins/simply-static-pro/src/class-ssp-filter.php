<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class to handle settings for fuse.
 */
class Filter {
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
		add_filter( 'ss_settings_args', array( $this, 'modify_settings_args' ) );
		add_filter( 'simplystatic.archive_creation_job.task_list', array( $this, 'modify_task_list' ), 20, 2 );
		add_filter( 'simply_static_class_name', array( $this, 'check_class_name' ), 10, 2 );
		add_filter( 'simply_static_converted_url', array( $this, 'change_url' ), 20, 2 );
		add_filter( 'simply_static_change_url', array( $this, 'change_url' ), 20 );
		add_filter( 'simply_static_change_path', [ $this, 'change_path' ], 20 );
		add_filter( 'simply_static_decoded_text_in_script', array( $this, 'change_url' ), 20, 2 );
		add_filter( 'simply_static_decoded_urls_in_script', array( $this, 'change_url' ), 20, 2 );
		add_filter( 'simply_static_force_replaced_urls_body', array( $this, 'change_url' ), 20, 2 );
		add_filter( 'ssp_config_relative_dir', array( $this, 'change_url' ), 20 );
		add_filter( 'ss_get_page_file_path_for_transfer', array( $this, 'change_path_for_transfer' ), 20, 2 );
		add_filter( 'simply_static_content_before_save', array(
			$this,
			'run_body_content_optimization'
		), PHP_INT_MAX, 2 );
		add_action( 'simply_static_page_handler_request_after_hooks', array( $this, 'run_optimization' ) );

		// Register hooks to track previous export end time (moved from main plugin file)
		add_action( 'ss_archive_creation_job_before_start', array( $this, 'handle_before_start' ), 10, 2 );
		add_action( 'ss_completed', array( $this, 'handle_completed' ), 10, 1 );

		// Activate multilingual crawler if needed
		add_action( 'plugins_loaded', array( $this, 'activate_multilingual_crawler' ), 20 );

		// Migrate old directory settings that may contain "wp-content/" prefix
		$this->migrate_directory_settings();
	}

	/**
	 * Migrate old directory settings to new format.
	 *
	 * Previously, users could save subdirectory settings like "wp-content/addons".
	 * Now the UI shows wp_content_directory as a prefix, so subdirectory settings
	 * should only contain the suffix (e.g., "addons" not "wp-content/addons").
	 *
	 * This migration strips the content directory prefix from existing settings.
	 */
	protected function migrate_directory_settings() {
		$options = get_option( 'simply-static' );

		if ( ! is_array( $options ) ) {
			return;
		}

		$subdirectory_options = array(
			'wp_uploads_directory',
			'wp_plugins_directory',
			'wp_themes_directory',
		);

		// Get the current content directory (or default "wp-content")
		$content_dir = ! empty( $options['wp_content_directory'] ) ? trim( $options['wp_content_directory'] ) : 'wp-content';
		$content_dir_prefix = $content_dir . '/';

		$updated = false;

		foreach ( $subdirectory_options as $option_name ) {
			if ( empty( $options[ $option_name ] ) ) {
				continue;
			}

			$value = trim( $options[ $option_name ] );

			// Check if the value starts with the content directory prefix (e.g., "wp-content/" or "assets/")
			if ( strpos( $value, $content_dir_prefix ) === 0 ) {
				// Strip the content directory prefix
				$options[ $option_name ] = substr( $value, strlen( $content_dir_prefix ) );
				$updated = true;
			}
			// Also check for default "wp-content/" in case content_dir was changed but old values remain
			elseif ( $content_dir !== 'wp-content' && strpos( $value, 'wp-content/' ) === 0 ) {
				// Strip the "wp-content/" prefix
				$options[ $option_name ] = substr( $value, strlen( 'wp-content/' ) );
				$updated = true;
			}
		}

		if ( $updated ) {
			update_option( 'simply-static', $options );
		}
	}

	/**
	 * Change File Path to apply optimizations.
	 *
	 * @param string $url File path.
	 * @param Simply_Static\Page $page Page object.
	 *
	 * @return array|mixed|string|string[]
	 */
	public function change_path_for_transfer( $url, $page = null ) {
		$url = '/' . $url; // Adding '/' as this is file path without it. Need it for regex to work.
		$url = $this->change_url( $url, $page );
		if ( 0 === stripos( $url, '/' ) ) {
			$url = substr( $url, 1 );
		}

		return $url;
	}

	/**
	 *
	 * @param string $content Content.
	 * @param Simply_Static\Url_Extractor $extractor Extractor.
	 *
	 * @return string
  */
    public function run_body_content_optimization( $content, $extractor ) {
        // Normalize $content to a string to avoid deprecation notices when null is passed
        // to string functions like preg_replace(_callback) in PHP 8.1+.
        if ( null === $content ) {
            $content = '';
        } elseif ( ! is_string( $content ) ) {
            $content = (string) $content;
        }

        $options = Simply_Static\Options::instance();
        $find    = [];
        $replace = [];

		if ( $options->get( 'hide_version' ) ) {
			$find[]    = '/(\?|\&#038;|\&)ver=[0-9a-zA-Z\.\_\-\+]+(\&#038;|\&)/';
			$replace[] = '$1';

			$find[]    = '/(\?|\&#038;|\&)ver=[0-9a-zA-Z\.\_\-\+]+("|\')/';
			$replace[] = '$2';
		}

		//Remove the Generator link.
		if ( $options->get( 'hide_generator' ) ) {
			$find[]    = '/<meta[^>]*name=[\'"]generator[\'"][^>]*>/i';
			$replace[] = '';
		}

		//Remove WP prefetch domains that reveal the CMS.
		if ( $options->get( 'hide_prefetch' ) ) {
			$find[]    = '/<link[^>]*rel=[\'"]dns-prefetch[\'"][^>]*w.org[^>]*>/i';
			$replace[] = '';

			$find[]    = '/<link[^>]*rel=[\'"]dns-prefetch[\'"][^>]*wp.org[^>]*>/i';
			$replace[] = '';

			$find[]    = '/<link[^>]*rel=[\'"]dns-prefetch[\'"][^>]*wordpress.org[^>]*>/i';
			$replace[] = '';
		}

		if ( $options->get( 'disable_xmlrpc' ) ) {
			$find[]    = '/(<link[\s])rel=[\'"]pingback[\'"][\s]([^>]+>)/i';
			$replace[] = '';
		}

		if ( empty( $find ) ) {
			return $content;
		}

		$content = preg_replace( $find, $replace, $content );


		return $content;
	}

	/**
	 * Run optimizations on each page.
	 *
	 * @param Simply_Static\Page_Handler $handler Object.
	 *
	 * @return void
	 */
	public function run_optimization( $handler ) {
		$options = Simply_Static\Options::instance();

		if ( $options->get( 'hide_rsd' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		}

		if ( $options->get( 'hide_emotes' ) ) {
			// Disable emoji SVG URL
			add_filter( 'emoji_svg_url', '__return_false' );
			
			// Remove emoji detection script from head
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			
			// Remove emoji styles
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			
			// Remove emoji from feeds
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			
			// Remove TinyMCE emoji plugin
			add_filter( 'tiny_mce_plugins', [ $this, 'disable_emojis_tinymce' ] );
			
			// Remove emoji DNS prefetch
			add_filter( 'wp_resource_hints', [ $this, 'disable_emojis_remove_dns_prefetch' ], 10, 2 );
			
			// Disable emoji script loader completely (prevents twemoji.js and wp-emoji.js 404 errors)
			add_filter( 'wp_emoji_loader_script', '__return_empty_string' );
		}

		if ( $options->get( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		if ( $options->get( 'disable_embed' ) ) {
			// Remove the REST API endpoint.
			remove_action( 'rest_api_init', 'wp_oembed_register_route' );

			// Turn off oEmbed auto discovery.
			// Don't filter oEmbed results.
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result' );

			// Remove oEmbed discovery links.
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

			// Remove oEmbed-specific JavaScript from the front-end and back-end.
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		}

		if ( $options->get( 'disable_db_debug' ) ) {
			global $wpdb;
			$wpdb->hide_errors();
		}

		if ( $options->get( 'disable_wlw_manifest' ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

	}

	public function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' == $relation_type ) {
			/** This filter is documented in wp-includes/formatting.php */
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}

		return $urls;
	}

	/**
	 * Remove the TinyMCE emoji plugin.
	 *
	 * @param array $plugins Array of TinyMCE plugins.
	 * @return array Filtered array of plugins.
	 */
	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}

		return array();
	}


	public function change_path( $path ) {
		$path = $this->change_author( $path, true);
		$path = $this->change_wp_uploads( $path, true );
		$path = $this->change_theme_style( $path, true );
		$path = $this->change_theme_name( $path, true );
		$path = $this->change_wp_plugins( $path, true );
		$path = $this->change_wp_themes( $path, true );
		$path = $this->change_wp_content( $path, true );
		$path = $this->change_wp_includes( $path, true );

		return $path;
	}

	public function change_url( $url, $page = null ) {
		$url = $this->change_author( $url );
		$url = $this->change_wp_uploads( $url );
		$url = $this->change_theme_style( $url );
		$url = $this->change_theme_name( $url );
		$url = $this->change_wp_plugins( $url );
		$url = $this->change_wp_themes( $url );
		$url = $this->change_wp_content( $url );
		$url = $this->change_wp_includes( $url );

		return $url;
	}

	public static function get_hashed_theme_names() {
		$dirs = scandir( WP_CONTENT_DIR . '/themes' );

		$only_dirs = array_filter( $dirs, function ( $item ) {
			return is_dir( WP_CONTENT_DIR . '/themes' . DIRECTORY_SEPARATOR . $item ) && ! in_array( $item, [
					".",
					".."
				] );
		} );

		if ( ! $only_dirs ) {
			return [];
		}

		$mapped = [];
		foreach ( $only_dirs as $theme_name ) {
			$mapped[ $theme_name ] = substr( md5( $theme_name ), 10 );
		}

		return $mapped;
	}

	public static function get_hashed_plugin_names() {
		$dirs = scandir( WP_PLUGIN_DIR );

		$only_dirs = array_filter( $dirs, function ( $item ) {
			return is_dir( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $item ) && ! in_array( $item, [ ".", ".." ] );
		} );

		if ( ! $only_dirs ) {
			return [];
		}

		$mapped = [];
		foreach ( $only_dirs as $plugin_name ) {
			$mapped[ $plugin_name ] = substr( md5( $plugin_name ), 10 );
		}

		return $mapped;
	}

	protected function change_theme_style( $url ) {
		$options   = Simply_Static\Options::instance();
		$new_style = $options->get( 'theme_style_name' );

		if ( ! $new_style ) {
			return $url;
		}

		$style_path     = 'wp-content/themes/' . get_stylesheet() . '/style.css';
		$new_style_path = 'wp-content/themes/' . get_stylesheet() . '/' . $new_style . '.css';

		return $this->change_directory_in_url( $url, $style_path, $new_style_path );
	}

	protected function change_theme_name( $url ) {
		$options = Simply_Static\Options::instance();

		if ( ! $options->get( 'rename_theme_directories' ) ) {
			return $url;
		}

		$theme_names = self::get_hashed_theme_names();

		if ( ! $theme_names ) {
			return $url;
		}

		foreach ( $theme_names as $theme_name => $hashed_name ) {
			$theme_path  = 'wp-content/themes/' . $theme_name;
			$hashed_path = 'wp-content/themes/' . $hashed_name;
			$url         = $this->change_directory_in_url( $url, $theme_path, $hashed_path );
		}

		return $url;
	}

	protected function change_author( $url, $path = false ) {
		return $this->change_url_path( $url, 'author', 'author_url', $path );
	}

	protected function change_wp_uploads( $url, $path = false ) {
		return $this->change_wp_content_subdir( $url, 'wp-content/uploads', 'wp_uploads_directory', $path );
	}

	protected function change_wp_themes( $url, $path = false ) {
		return $this->change_wp_content_subdir( $url, 'wp-content/themes', 'wp_themes_directory', $path );
	}

	protected function change_wp_plugins( $url, $path = false ) {
		return $this->change_wp_content_subdir( $url, 'wp-content/plugins', 'wp_plugins_directory', $path );
	}

	/**
	 * Change a wp-content subdirectory path, respecting the wp_content_directory setting.
	 *
	 * This ensures that paths like wp-content/plugins are transformed to
	 * {wp_content_directory}/{wp_plugins_directory} (e.g., assets/addons).
	 *
	 * @param string $url The URL/path to transform.
	 * @param string $origin_path The original path to replace (e.g., 'wp-content/uploads').
	 * @param string $option_name The option name for the subdirectory (e.g., 'wp_uploads_directory').
	 * @param bool   $path Whether this is a file path (vs URL).
	 * @return string
	 */
	protected function change_wp_content_subdir( $url, $origin_path, $option_name, $path = false ) {
		$options      = Simply_Static\Options::instance();
		$subdir_value = $options->get( $option_name );

		if ( ! $subdir_value ) {
			return $url;
		}

		$subdir_value = trim( $subdir_value );

		// Get the wp_content_directory (defaults to 'wp-content' if not set)
		$content_dir = $options->get( 'wp_content_directory' );
		$content_dir = $content_dir ? trim( $content_dir ) : 'wp-content';

		// Build the full replacement path: {content_dir}/{subdir_value}
		$new_directory = $content_dir . '/' . $subdir_value;

		if ( $path ) {
			return $this->change_directory_in_path( $url, $origin_path, $new_directory );
		}

		return $this->change_directory_in_url( $url, $origin_path, $new_directory );
	}

	protected function change_wp_content( $url, $path = false ) {
		return $this->change_url_path( $url, 'wp-content', 'wp_content_directory', $path );
	}

	protected function change_wp_includes( $url, $path = false ) {
		return $this->change_url_path( $url, 'wp-includes', 'wp_includes_directory', $path );
	}

	protected function change_url_path( $url, $origin_path, $option_name, $path = false ) {
		$options = Simply_Static\Options::instance();
		$value   = $options->get( $option_name );

		if ( ! $value ) {
			return $url;
		}

		$new_directory = trim( $value );

		if ( $path ) {
			return $this->change_directory_in_path( $url, $origin_path, $new_directory );
		}

		return $this->change_directory_in_url( $url, $origin_path, $new_directory );
	}

	protected function change_directory_in_path( $url, $origin_directory, $new_directory ) {
		if ( '/' === DIRECTORY_SEPARATOR ) {
			return $this->change_path_for_transfer( $url );
		}

		$new_directory = trim( $new_directory );

		if ( ! $new_directory || DIRECTORY_SEPARATOR === $new_directory ) {
			return $url;
		}

		$new_directory = untrailingslashit( $new_directory );
		if ( DIRECTORY_SEPARATOR === $new_directory[0] ) {
			$new_directory = substr( $new_directory, '1' );
		}

		$origin_directory = untrailingslashit( $origin_directory );
		if ( DIRECTORY_SEPARATOR === $origin_directory[0] ) {
			$origin_directory = substr( $origin_directory, '1' );
		}

		if ( $origin_directory === $new_directory ) {
			return $url;
		}

		$regex      = "/\\\\" . $origin_directory . "\\\\/i";
		$convert_to = "\\\\{$new_directory}\\\\";
		$url        = preg_replace( $regex, $convert_to, $url );

		return $url;
	}

	protected function change_directory_in_url( $url, $origin_directory, $new_directory ) {
		$new_directory = trim( $new_directory );

		if ( ! $new_directory || '/' === $new_directory ) {
			return $url;
		}

		$new_directory = untrailingslashit( $new_directory );
		if ( '/' === $new_directory[0] ) {
			$new_directory = substr( $new_directory, '1' );
		}

		$origin_directory = untrailingslashit( $origin_directory );
		if ( '/' === $origin_directory[0] ) {
			$origin_directory = substr( $origin_directory, '1' );
		}

		if ( $origin_directory === $new_directory ) {
			return $url;
		}

		$regex      = "/\/" . addcslashes( $origin_directory, '/' ) . "\//i";
		$convert_to = "/{$new_directory}/";
		$url        = preg_replace( $regex, $convert_to, $url );

		// replace wp_json_encode'd urls, as used by WP's `concatemoji`.
		// e.g. {"concatemoji":"http:\/\/www.example.org\/wp-includes\/js\/wp-emoji-release.min.js?ver=4.6.1"}.
		$regex = addcslashes( "/" . $origin_directory . "/", "/" );
		$url   = str_replace( $regex, addcslashes( $convert_to, "/" ), $url );

		return $url;
	}


	/**
	 * Modify settings args for pro.
	 *
	 * @param array $args given list of arguments.
	 *
	 * @return array
	 */
	public function modify_settings_args( array $args ): array {
		$args['plan']   = 'pro';
		$args['builds'] = [];
		$terms          = get_terms(
			array(
				'taxonomy'   => 'ssp-build',
				'order'      => 'ASC',
				'hide_empty' => false,
			)
		);

		if ( $terms ) {
			$args['builds'] = wp_list_pluck( $terms, 'name', 'term_id' );
		}

		return $args;
	}

	/**
	 * Add tasks to Simply Static task list.
	 *
	 * @param array $task_list current task list.
	 * @param string $delivery_method current delivery method.
	 *
	 * @return array
	 */
	public function modify_task_list( $task_list, $delivery_method ) {
		// 404-only export short-circuit flag from Free plugin
		$only_404 = get_option( 'simply-static-404-only' );
		if ( ! empty( $only_404 ) ) {
			$task_list = array( 'setup' );
			$task_list[] = 'generate_404';
			switch ( $delivery_method ) {
				case 'zip':
					$task_list[] = 'create_zip_archive';
					break;
				case 'local':
					$task_list[] = 'transfer_files_locally';
					break;
				case 'simply-static-studio':
					$task_list[] = 'simply_static_studio_deploy';
					break;
				case 'github':
					$task_list[] = 'github_commit';
					break;
				case 'cdn':
					$task_list[] = 'bunny_deploy';
					break;
				case 'tiiny':
					$task_list[] = 'tiiny_deploy';
					break;
				case 'aws-s3':
					$task_list[] = 'aws_deploy';
					break;
				case 'sftp':
					$task_list[] = 'sftp_deploy';
					break;
			}
			$task_list[] = 'wrapup';
			return $task_list;
		}
		$options                   = get_option( 'simply-static' );
		$use_smart_crawl           = $options['smart_crawl'] ?? false;
		$use_search                = $options['use_search'] ?? false;
		$use_minify                = $options['use_minify'] ?? false;
		$use_shortpixel            = $options['shortpixel_enabled'] ?? false;
		$aws_empty                 = $options['aws_empty'] ?? false;
		$generate_404              = $options['generate_404'] ?? false;
		$change_wp_content         = $options['wp_content_directory'] ?? false;
		$change_wp_includes        = $options['wp_includes_directory'] ?? false;
		$optimize_directories_task = false;
		$use_single                = get_option( 'simply-static-use-single' );
		$use_build                 = get_option( 'simply-static-use-build' );


		if ( $change_wp_content && 'wp-content' !== $change_wp_content && '/' !== $change_wp_content ) {
			$optimize_directories_task = true;
		}

		if ( $change_wp_includes && 'wp-includes' !== $change_wp_includes && '/' !== $change_wp_includes ) {
			$optimize_directories_task = true;
		}

		// Reset original task list.
		$task_list = array( 'setup' );

		if ( class_exists( 'Simply_Static\Discover_Urls_Task' ) && $use_smart_crawl ) {
			// Only include discover_urls on full exports (exclude update, single, and build exports).
			$export_type = $options['generate_type'] ?? '';
			if ( ! $use_single && ! $use_build && 'update' !== $export_type ) {
				$task_list[] = 'discover_urls';
			}
		}

  // Insert pro-only deletion step before fetch_urls for both full and update exports.
  // Keep discover_changes only for update exports.
  $export_type_for_changes = $options['generate_type'] ?? '';
  $is_update               = ( 'update' === $export_type_for_changes );
  $is_full_export          = ( ! $use_single && ! $use_build && 'update' !== $export_type_for_changes );

  if ( $is_update || $is_full_export ) {
      // First delete items tracked as removed from WP
      $task_list[] = 'delete_tracked_pages';
  }

  if ( $is_update ) {
      // Then discover changed URLs to fetch (only meaningful for updates)
      $task_list[] = 'discover_changes';
  }

		$task_list[] = 'fetch_urls';

		// Add 404 task
		if ( $generate_404 ) {
			// Only on full exports.
			$use_single  = get_option( 'simply-static-use-single' );
			$use_build   = get_option( 'simply-static-use-build' );
			$export_type = $options['generate_type'];

			if ( ! $use_single && ! $use_build && 'update' !== $export_type ) {
				$task_list[] = 'generate_404';
			}
		}

		// Add search task.
		if ( $use_search ) {
			$task_list[] = 'search';
		}

		// Add minify task.
		if ( $use_minify ) {
			$task_list[] = 'minify';
		}


		if ( $use_shortpixel && ! $use_single && ! $use_build ) {
			$task_list[] = 'shortpixel';
			$task_list[] = 'shortpixel_download';

			if ( isset( $options['shortpixel_webp_enabled'] ) && $options['shortpixel_webp_enabled'] ) {
				$task_list[] = 'shortpixel_change';
			}
		}

		if ( $optimize_directories_task ) {
			$task_list[] = 'optimize_directories';
		}


		$task_list = apply_filters( 'ssp_tasks_before_delivery_methods', $task_list, $options, $delivery_method );

  // Add AWS S3 empty task only for full exports (not update, not single export, not build, not 404-only).
  $export_type = $options['generate_type'] ?? '';
  $only_404    = get_option( 'simply-static-404-only' );
  if ( $aws_empty && $delivery_method === 'aws-s3' && ! $use_single && ! $use_build && 'update' !== $export_type && empty( $only_404 ) ) {
      $task_list[] = 'aws_empty';
  }

		// Add deployment tasks.
		switch ( $delivery_method ) {
			case 'zip':
				$task_list[] = 'create_zip_archive';
				break;
			case 'local':
				$task_list[] = 'transfer_files_locally';
				break;
			case 'simply-static-studio':
				$task_list[] = 'simply_static_studio_deploy';
				break;
			case 'github':
				$task_list[] = 'github_commit';
				break;
			case 'cdn':
				$task_list[] = 'bunny_deploy';
				break;
			case 'tiiny':
				$task_list[] = 'tiiny_deploy';
				break;
			case 'aws-s3':
				$task_list[] = 'aws_deploy';
				break;
			case 'sftp':
				$task_list[] = 'sftp_deploy';
				break;
		}

		// Add wrapup task.
		$task_list[] = 'wrapup';

		return $task_list;
	}

	/**
	 * Modify task class name in Simply Static.
	 *
	 * @param string $class_name current class name.
	 * @param string $task_name current task name.
	 *
	 * @return string
	 */
	public function check_class_name( $class_name, $task_name ) {

		if ( 'github_commit' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'simply_static_studio_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'bunny_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'tiiny_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'search' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'minify' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'shortpixel_download' === $task_name ) {
			return 'simply_static_pro\\Shortpixel_Download_Task';
		}

		if ( 'shortpixel_change' === $task_name ) {
			return 'simply_static_pro\\Shortpixel_Change_Task';
		}

		if ( 'shortpixel' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'aws_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'aws_empty' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'optimize_directories' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name, '_' ) . '_Task';
		}

		if ( 'sftp_deploy' === $task_name ) {
			return 'simply_static_pro\\SFTP_Deploy_Task';
		}

		if ( 'multisite_queue' === $task_name ) {
			return 'simply_static_pro\\Multisite_Queue_Task';
		}

  // Map discover_changes to the Pro task class
  if ( 'discover_changes' === $task_name ) {
      return 'simply_static_pro\\Discover_Changes_Task';
  }

  // Map delete_tracked_pages to the Pro task class
  if ( 'delete_tracked_pages' === $task_name ) {
      return 'simply_static_pro\\Delete_Tracked_Pages_Task';
  }

		return $class_name;
	}


	/**
	 * Activate the Multilingual Crawler if any of the supported plugins are active.
	 *
	 * @return void
	 */
	public function handle_before_start( $blog_id, $job ) {
		// Capture existing end time before it is reset
		$opts = \Simply_Static\Options::instance();
		$prev_end = $opts->get( 'archive_end_time' );
		if ( ! empty( $prev_end ) ) {
			// Store as GMT string to match post_*_gmt comparisons
			$prev_end_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $prev_end ) );
			update_option( 'ssp_previous_export_end_gmt', $prev_end_gmt );
			\Simply_Static\Util::debug_log( 'Stored previous export end time (GMT): ' . $prev_end_gmt );
		}
	}

	public function handle_completed( $status ) {
		// At completion, persist the end time for next run
		$opts = \Simply_Static\Options::instance();
		$end = $opts->get( 'archive_end_time' );
		if ( ! empty( $end ) ) {
			$end_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $end ) );
			update_option( 'ssp_previous_export_end_gmt', $end_gmt );
			\Simply_Static\Util::debug_log( 'Updated previous export end time (GMT): ' . $end_gmt );
		}
	}

	public function activate_multilingual_crawler() {
		// Only run if Simply Static is active
		if ( ! function_exists( 'simply_static_run_plugin' ) ) {
			return;
		}

		// Check if any of the supported plugins are active
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$is_wpml_active = is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
		$is_polylang_active = is_plugin_active( 'polylang/polylang.php' ) || is_plugin_active( 'polylang-pro/polylang.php' );
		$is_translatepress_active = is_plugin_active( 'translatepress-multilingual/index.php' );

		// If none of the supported plugins are active, don't activate the crawler
		if ( ! $is_wpml_active && ! $is_polylang_active && ! $is_translatepress_active ) {
			return;
		}

		// Get options instance
		$options = \Simply_Static\Options::instance();

		// Get current active crawlers
		$crawlers = $options->get( 'crawlers' );

		// Respect user selections completely:
		// - If crawlers is an array and does NOT contain 'multilingual', treat as explicit opt-out and do not re-add.
		// - If crawlers is null or not an array, do not modify; fall back to default is_active logic.
		if ( is_array( $crawlers ) ) {
			if ( in_array( 'multilingual', $crawlers, true ) ) {
				\Simply_Static\Util::debug_log( 'Multilingual Crawler already present in user selection; leaving as-is.' );
			} else {
				\Simply_Static\Util::debug_log( 'Multilingual Crawler not in user selection; respecting opt-out and not adding.' );
			}
		} else {
			\Simply_Static\Util::debug_log( 'Crawlers option undefined or not an array; not modifying for Multilingual.' );
		}
	}

}
