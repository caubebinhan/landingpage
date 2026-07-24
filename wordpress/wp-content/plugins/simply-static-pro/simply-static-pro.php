<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name:       Simply Static Pro
 * Plugin URI:        https://patrickposner.dev
 * Description:       Enhances Simply Static with GitHub Integration, Forms, Comments and more.
 * Version:           2.1.6.1
 * Update URI:        https://api.freemius.com
 * Author:            Patrick Posner
 * Author URI:        https://patrickposner.dev
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simply-static-pro
 * Domain Path:       /languages
 */

define( 'SIMPLY_STATIC_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLY_STATIC_PRO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SIMPLY_STATIC_PRO_VERSION', '2.1.6.1' );

// load Freemius.
require_once( SIMPLY_STATIC_PRO_PATH . 'inc/setup.php' );

// Install and activate the free version if necessary.
add_action( 'init', function () {
	$options = get_option( 'simply-static' );

	if ( ! class_exists( 'Simply_Static' ) && current_user_can( 'activate_plugins' ) && ! isset( $options['core-installed'] ) ) {
		require_once SIMPLY_STATIC_PRO_PATH . 'src/class-ssp-plugin-installer.php';

		$installer = simply_static_pro\Plugin_Installer::get_instance();
		$installer->install_package_from_wp_org( 'simply-static' );

		// Then activate
		activate_plugin( 'simply-static/simply-static.php' );

		// Update option.
		$options['core-installed'] = true;
		update_option( 'simply-static', $options );
	}
} );

// Bootmanager for Simply Static Pro plugin.
if ( ! function_exists( 'ssp_run_plugin' ) ) {
	/**
	 * Retrieve the shared secret used for securing form webhooks.
	 * If it doesn't exist yet, generate and persist a new one.
	 *
	 * Also defines constants for backward compatibility:
	 * - SSP_SHARED_SECRET (preferred)
	 * - SSS_SECRET_KEY (legacy name used by Studio helper)
	 *
	 * @return string Secret value
	 */
	function ssp_get_shared_secret() {
		// Prefer a single option to store the secret.
		$secret = get_option( 'ssp_shared_secret' );

		// If helper already defined its constant/key, prefer reusing it to avoid breaking existing clients.
		if ( empty( $secret ) && defined( 'SSS_SECRET_KEY' ) && constant( 'SSS_SECRET_KEY' ) ) {
			$secret = (string) constant( 'SSS_SECRET_KEY' );
			update_option( 'ssp_shared_secret', $secret );
		}

		if ( empty( $secret ) ) {
			// Generate a 48-char pseudo-random secret.
			$bytes  = function_exists( 'random_bytes' ) ? random_bytes( 32 ) : wp_generate_password( 48, true, true );
			$secret = is_string( $bytes ) ? bin2hex( $bytes ) : (string) $bytes;
			$secret = substr( preg_replace( '/[^a-zA-Z0-9]/', '', $secret ), 0, 64 );
			if ( empty( $secret ) ) {
				$secret = wp_generate_password( 48, true, true );
			}
			update_option( 'ssp_shared_secret', $secret );
		}

		if ( ! defined( 'SSP_SHARED_SECRET' ) ) {
			define( 'SSP_SHARED_SECRET', $secret );
		}
		// Define legacy alias if not already.
		if ( ! defined( 'SSS_SECRET_KEY' ) ) {
			define( 'SSS_SECRET_KEY', $secret );
		}

		return $secret;
	}

	// Ensure secret exists as early as possible during boot.
	add_action( 'plugins_loaded', function () {
		ssp_get_shared_secret();
	}, 5 );
	/**
	 * Safely require a file from Simply Static core, trying multiple candidates and failing gracefully.
	 *
	 * @param string|array $relative_paths One or more relative paths under SIMPLY_STATIC_PATH.
	 *
	 * @return bool True on success, false if none of the candidates were found.
	 */
	function ssp_require_from_core( $relative_paths ) {
		if ( ! defined( 'SIMPLY_STATIC_PATH' ) ) {
			return false;
		}

		$candidates = (array) $relative_paths;

		foreach ( $candidates as $rel ) {
			$path = trailingslashit( SIMPLY_STATIC_PATH ) . ltrim( $rel, '/\\' );
			if ( file_exists( $path ) ) {
				require_once $path;

				return true;
			}
		}

		// Log and show an admin notice instead of fatally erroring out.
		$listed = implode( ', ', $candidates );
		error_log( '[Simply Static Pro] Missing core dependency. Tried: ' . $listed );

		add_action( 'admin_notices', function () use ( $listed ) {
			$message = sprintf(
			/* translators: 1: file candidates list */
				esc_html__( 'Simply Static Pro could not find a required file in Simply Static core (tried: %1$s). Please update Simply Static to the latest version.', 'simply-static-pro' ),
				esc_html( $listed )
			);
			echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
		} );

		return false;
	}

	// autoload files.
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	add_action( 'plugins_loaded', 'ssp_run_plugin' );

	/**
	 * Run plugin
	 *
	 * @return void
	 */
	function ssp_run_plugin() {
		if ( function_exists( 'simply_static_run_plugin' ) ) {
			// We need several classes from Simply Static core to integrate our jobs.
			// Try both current and legacy filenames to avoid fatal errors across versions.
			if ( ! ssp_require_from_core( array(
				'src/tasks/traits/class-ss-skip-further-processing-exception.php',
				'src/tasks/traits/class-skip-further-processing-exception.php',
			) ) ) {
				return; // Abort Pro boot safely.
			}

			if ( ! ssp_require_from_core( 'src/tasks/traits/trait-ss-can-process-pages.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/tasks/traits/trait-ss-can-transfer.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/tasks/class-ss-task.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/tasks/class-ss-fetch-urls-task.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/tasks/class-ss-discover-urls-task.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/class-ss-plugin.php' ) ) {
				return;
			}
			if ( ! ssp_require_from_core( 'src/class-ss-util.php' ) ) {
				return;
			}

			// Include Pro tasks.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/tasks/class-ssp-multisite-queue.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/tasks/class-ssp-delete-tracked-pages-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/tasks/class-ssp-discover-changes-task.php';

			// Handle Pagebuilder specifics
			require_once SIMPLY_STATIC_PRO_PATH . 'src/misc/class-ssp-builder-support.php';

			// Helper.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/class-ssp-helper.php';
			simply_static_pro\Helper::get_instance();

			// Filter.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/class-ssp-filter.php';
			simply_static_pro\Filter::get_instance();

			// Database.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/database/class-ssp-database.php';
			// Initialize DB features (deleted pages tracker/handler)
			if ( class_exists( '\simply_static_pro\Database' ) ) {
				\simply_static_pro\Database::get_instance();
			}

			// Form Entries (load only if Simply Static version is compatible).
			if ( ! defined( 'SSS_VERSION' ) || version_compare( SSS_VERSION, '1.0.36', '>=' ) ) {
				require_once SIMPLY_STATIC_PRO_PATH . 'src/form/form-entries/class-ssp-form-handler.php';

				if ( class_exists( '\\simply_static_pro\\form\\form_entries\\Form_Handler' ) ) {
					// Initialize Form Entries.
					\simply_static_pro\form\form_entries\Form_Handler::get_instance();
				}
			}

			// Deployment.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-repository.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-database.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-commit-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/bunny-cdn/class-ssp-bunny-updater.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/bunny-cdn/class-ssp-bunny-deploy-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/tiiny-host/class-ssp-tiiny-updater.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/tiiny-host/class-ssp-tiiny-deploy-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-s3-client.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-aws-deploy-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-aws-empty-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/sftp/class-ssp-sftp-deploy-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/sftp/class-ssp-sftp.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/simply-static-studio/class-ssp-simply-static-studio-updater.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/simply-static-studio/class-ssp-simply-static-studio-deploy-task.php';

			// Builds (conditionally load when enabled in Simply Static settings or when legacy terms exist).
			$ss_options = get_option( 'simply-static' );
			$use_builds = is_array( $ss_options ) && ! empty( $ss_options['ss_use_builds'] );

			// Fallback: if the site already has terms in the ssp-build taxonomy, load Build subsystem even if the option is off.
			if ( ! $use_builds ) {
				$has_build_terms = get_transient( 'simply_static_has_build_terms' );
				if ( false === $has_build_terms ) {
					global $wpdb;
					$has_build_terms = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'ssp-build' ) );
					set_transient( 'simply_static_has_build_terms', $has_build_terms, 5 * MINUTE_IN_SECONDS );
				}
				if ( $has_build_terms > 0 ) {
					$use_builds = true;
				}
			}

			if ( $use_builds ) {
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build-settings.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build-meta.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build.php';

				simply_static_pro\Build_Settings::get_instance();
				simply_static_pro\Build_Meta::get_instance();
				simply_static_pro\Build::get_instance();
			}

			// Single.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/single/class-ssp-single.php';

			simply_static_pro\Single::get_instance();

			// Webhook service (always available)
			require_once SIMPLY_STATIC_PRO_PATH . 'src/webhook/class-ssp-webhook.php';
			simply_static_pro\Webhook::get_instance();

			// Forms.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-settings.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-template-handler.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-meta.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-patcher.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-captcha-credentials.php';

			simply_static_pro\Form_Settings::get_instance();
			simply_static_pro\Form_Template_Handler::get_instance();
			simply_static_pro\Form_Meta::get_instance();
			simply_static_pro\Form_Patcher::get_instance();
			simply_static_pro\Form_Captcha_Credentials::get_instance();

			// Comments.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/comment/class-ssp-comment.php';
			simply_static_pro\Comment::get_instance();

			// Cors.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/cors/class-ssp-cors.php';

			simply_static_pro\CORS::get_instance();

			// iFrame.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/iframe/class-ssp-iframe.php';

			simply_static_pro\Iframe::get_instance();

			// Search.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-algolia.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-fuse.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-handler.php';

			simply_static_pro\Search_Algolia::get_instance();
			simply_static_pro\Search_Fuse::get_instance();
			simply_static_pro\Search_Handler::get_instance();

			if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
				require_once SIMPLY_STATIC_PRO_PATH . 'src/wp-cli/class-ssp-commands.php';
				new simply_static_pro\commands\Commands();
			}

			// Minifer.
			require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minify-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-interface.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-css.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-js.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-html.php';

			// Pro tasks
			require_once SIMPLY_STATIC_PRO_PATH . 'src/tasks/class-ssp-discover-changes-task.php';

			// Optimize
			require_once SIMPLY_STATIC_PRO_PATH . 'src/optimize/class-ssp-alternate-filesystem.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/optimize/class-ssp-optimize-directories.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/optimize/class-ssp-shortpixel-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/optimize/class-ssp-shortpixel-download-task.php';
			require_once SIMPLY_STATIC_PRO_PATH . 'src/optimize/class-ssp-shortpixel-change-task.php';

			// Misc
			require_once SIMPLY_STATIC_PRO_PATH . 'src/misc/class-ssp-basic-auth.php';

			new \simply_static_pro\Basic_Auth();

			add_action( 'ss_integrations_before_load', 'ssp_include_pro_integrations' );
			add_action( 'simply_static_integrations', 'ssp_register_integrations' );

			if ( defined( 'SIMPLY_STATIC_VERSION' ) && version_compare( SIMPLY_STATIC_VERSION, '3.5.2', '<' ) ) {
				add_action(
					'admin_notices',
					function () {
						$message = esc_html__( 'You need to update Simply Static to version 3.5.2 before continuing to use Simply Static Pro, as we made significant changes requiring an upgrade.', 'simply-static-pro' );
						echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
					}
				);
			}
		}
	}
}

/**
 * Include pro integrations.
 *
 * @return void
 */
function ssp_include_pro_integrations() {
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-multilingual.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-github.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-shortpixel.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-redirection.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-complianz.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-search-and-filter.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-environments.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-uam.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-nsg-seo-generator.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-the-events-calendar.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-multisite.php';
}

/**
 * Register integrations.
 *
 * @param array $integrations List of integrations.
 *
 * @return array
 */
function ssp_register_integrations( array $integrations ): array {
	$integrations['multilingual'] = simply_static_pro\SS_Multilingual::class;
	$integrations['github']       = simply_static_pro\Github::class;
	$integrations['shortpixel']   = simply_static_pro\Shortpixel::class;
	$integrations['complianz']    = simply_static_pro\Complianz_Integration::class;
	//$integrations['search-and-filter'] = simply_static_pro\SearchAndFilter_Integration::class;
	$integrations['redirection']         = simply_static_pro\Redirection_Integration::class;
	$integrations['environments']        = simply_static_pro\Environments::class;
	$integrations['ss-uam']              = simply_static_pro\UAM::class;
	$integrations['nsg-seo-generator']   = simply_static_pro\SS_Nsg_SEO_Generator::class;
	$integrations['the-events-calendar'] = simply_static_pro\The_Events_Calendar_Integration::class;
	$integrations['multisite']           = simply_static_pro\Multisite_Integration::class;

	return $integrations;
}

// Register pro crawlers
add_filter( 'simply_static_crawlers', 'ssp_register_crawler' );

/**
 * Register pro crawlers.
 *
 * @param array $crawlers List of crawlers.
 *
 * @return array
 */
function ssp_register_crawler( array $crawlers ): array {
	// Register the multilingual crawler
	if ( ! class_exists( 'simply_static_pro\\Crawler\\Multilingual_Crawler' ) ) {
		require_once SIMPLY_STATIC_PRO_PATH . 'src/crawler/class-ssp-multilingual-crawler.php';
	}
	// Register the NSG SEO Generator crawler
	if ( ! class_exists( 'simply_static_pro\\Crawler\\Nsg_SEO_Generator_Crawler' ) ) {
		require_once SIMPLY_STATIC_PRO_PATH . 'src/crawler/class-ssp-nsg-seo-generator-crawler.php';
	}
	// Register The Events Calendar crawler
	if ( ! class_exists( 'simply_static_pro\\Crawler\\The_Events_Calendar_Crawler' ) ) {
		require_once SIMPLY_STATIC_PRO_PATH . 'src/crawler/class-ssp-the-events-calendar-crawler.php';
	}

	// Add the crawlers to the list
	$crawlers[] = new simply_static_pro\Crawler\Multilingual_Crawler();
	$crawlers[] = new simply_static_pro\Crawler\Nsg_SEO_Generator_Crawler();
	$crawlers[] = new simply_static_pro\Crawler\The_Events_Calendar_Crawler();

	return $crawlers;
}


add_action( 'rest_api_init', 'ssp_rest_api_init' );

function ssp_rest_api_init() {
	register_rest_route( 'simplystatic/v1', '/shortpixel-restore', array(
		'methods'             => 'POST',
		'callback'            => function () {
			set_time_limit( 3600 );
			/** @var \simply_static_pro\Shortpixel $shortpixel */
			$shortpixel = \Simply_Static\Plugin::instance()->get_integration( 'shortpixel' );
			$shortpixel->restore_all_backups();

			return json_encode( [ 'status' => 200, 'message' => __( 'Restored backups', 'simple-static-pro' ) ] );
		},
		'permission_callback' => function () {
			return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'shortpixel-restore' ) );
		},
	) );
}
