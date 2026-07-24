<?php

namespace simply_static_pro\form\form_entries;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central loader for the Form Entries module (DB, Admin, REST).
 */
class Form_Handler {
	/** @var Form_Handler */
	private static $instance;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): Form_Handler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor; sets up module if allowed.
	 */
	private function __construct() {
		$options = get_option( 'simply-static' );

		if ( empty( $options['use_forms'] ) ) {
			return;
		}

		$base = trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/form/form-entries/';

		// Require DB/Admin files.
		$module_files = array(
			// DB layer
			'database/class-ssp-form-entries-model.php',
			'database/class-ssp-form-entries-query.php',
			'database/class-ssp-form-form-entry.php',
			'database/class-ssp-form-entries-installer.php',
			// Admin UI
			'admin/class-ssp-form-entries-admin.php',
			// REST layer (renamed files)
			'rest/class-ssp-form-rest.php',
			'rest/class-ssp-form-entries-rest.php',
		);

		foreach ( $module_files as $rel ) {
			$abs = $base . $rel;
			if ( file_exists( $abs ) ) {
				require_once $abs;
			}
		}

        // Load compatibility classes for popular form plugins
        // (these files self-bootstrap and attach their filters/actions when included).
        $compat_dir = $base . 'compatibility/';
        if ( is_dir( $compat_dir ) ) {
            foreach ( glob( $compat_dir . '*.php' ) as $compat_file ) {
                require_once $compat_file;
            }
        }

		// Ensure secret headers are allowed for CORS preflight when submitting from static site.
		add_filter( 'rest_allowed_cors_headers', function ( $headers ) {
			// New preferred header
			$headers[] = 'X-Simply-Static-Secret';
			// Legacy header for backward compatibility
			$headers[] = 'X-Simply-Static-Studio-Secret';

			return array_values( array_unique( $headers ) );
		} );

		// Initialize installer and admin if available.
		if ( class_exists( '\\simply_static_pro\\database\\form_entries\\Entries_Installer' ) ) {
			try {
				\simply_static_pro\database\form_entries\Entries_Installer::get_instance();
			} catch ( \Throwable $e ) {
				// Silent failure to avoid fatal during boot.
			}
		}

		if ( class_exists( '\\simply_static_pro\\database\\form_entries\\admin\\Entries_Admin' ) ) {
			try {
				\simply_static_pro\database\form_entries\admin\Entries_Admin::get_instance();
			} catch ( \Throwable $e ) {
				// Silent failure; admin UI optional.
			}
		}

		// Register REST routes.
		if ( class_exists( '\\simply_static_pro\\form\\form_entries\\rest\\Entries' ) ) {
			try {
				$entries_rest = new \simply_static_pro\form\form_entries\rest\Entries();
				$entries_rest->register_routes();
			} catch ( \Throwable $e ) {
				// Silent failure; REST optional if errors occur.
			}
		}

		if ( class_exists( '\\simply_static_pro\\form\\form_entries\\rest\\Settings' ) ) {
			try {
				$settings_rest = new \simply_static_pro\form\form_entries\rest\Settings();
				$settings_rest->register_routes();
			} catch ( \Throwable $e ) {
				// Silent failure.
			}
		}
	}
}
