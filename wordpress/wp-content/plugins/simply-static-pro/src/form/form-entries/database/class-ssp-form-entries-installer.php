<?php

namespace simply_static_pro\database\form_entries;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates/updates the DB table for form entries.
 */
class Entries_Installer {
    /** @var Entries_Installer */
    private static $instance;

    public static function get_instance() : Entries_Installer {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'install' ] );
    }

    public function install() : void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'simply_static_form_entries';

        $sql = "CREATE TABLE {$table_name} (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          title varchar(255) DEFAULT '' NOT NULL,
          form_id varchar(255) DEFAULT '' NOT NULL,
          form_plugin varchar(255) DEFAULT '' NOT NULL,
          posted longtext NULL,
          created_at datetime NULL,
          updated_at datetime NULL,
          PRIMARY KEY  (id),
          KEY form_id (form_id),
          KEY form_plugin (form_plugin)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
