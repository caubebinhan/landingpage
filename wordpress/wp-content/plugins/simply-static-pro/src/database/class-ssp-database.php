<?php

namespace simply_static_pro;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central manager for Simply Static Pro database-related features
 *
 * Responsibilities:
 *  - Load and initialize database feature modules (e.g., Deleted_Pages_Tracker).
 *  - Provide a single bootstrap point to keep simply-static-pro.php lean.
 *  - Serve as an extension point for future DB features.
 */
class Database {
    /** @var Database */
    private static $instance;

    /**
     * Get singleton instance.
     *
     * @return Database
     */
    public static function get_instance() : Database {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor — boot DB features.
     */
    private function __construct() {
        $this->register_features();
    }

    /**
     * Register and initialize database-backed features.
     *
     * Note: Keep requires here to avoid relying on a global autoloader.
     * Guard each include/initialization to avoid fatals if files are missing.
     */
    private function register_features() : void {
        $deleted_tracker_file = SIMPLY_STATIC_PRO_PATH . 'src/database/delete-pages/class-ssp-deleted-pages-tracker.php';
        if ( file_exists( $deleted_tracker_file ) ) {
            require_once $deleted_tracker_file;
            try {
                pages\Deleted_Pages_Tracker::get_instance();
            } catch ( \Throwable $e ) {
                // Fail silently to avoid breaking admin/front-end if something goes wrong during early boot.
            }
        }

        // Load the Delete Pages Handler which hooks various WP events and writes into the tracker table.
        $handler_file = SIMPLY_STATIC_PRO_PATH . 'src/database/delete-pages/class-ssp-delete-pages-handler.php';
        if ( file_exists( $handler_file ) ) {
            require_once $handler_file;
            try {
                pages\Delete_Pages_Handler::get_instance();
            } catch ( \Throwable $e ) {
                // Silent failure; handler is optional at boot time.
            }
        }
    }
}
