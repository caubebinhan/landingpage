<?php

namespace simply_static_pro;

use Simply_Static\Util;


/**
 * Class to handle settings for fuse.
 */
class Search_Fuse {
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
        $options    = get_option( 'simply-static' );
        $use_search = $options['use_search'] ?? false;

        if ( $use_search ) {
            add_action( 'ss_after_setup_task', array( $this, 'add_config' ) );
        }
    }

    /**
     * Updating local JSON index file.
     *
     * @param string $temp_dir given temp directory.
     *
     * @return false|void
     */
    public function update_index_file( $temp_dir ) {
        $filesystem = Helper::get_file_system();

        if ( ! $filesystem ) {
            return false;
        }

        // Check if it's a full static export.
        $use_single = get_option( 'simply-static-use-single' );
        $use_build  = get_option( 'simply-static-use-build' );

        if ( isset( $use_build ) && ! empty( $use_build ) || isset( $use_single ) && ! empty( $use_single ) ) {
            return;
        }

        $config_file = $this->get_index_path();

        // Move file to directory.
        $temp_config_dir = $temp_dir . 'wp-content/uploads/simply-static/configs/';

        // Ensure the directory exists
        if ( ! is_dir( $temp_config_dir ) ) {
            wp_mkdir_p( $temp_config_dir );
        }

        $destination = $temp_config_dir . 'fuse-index.json';
        $filesystem->copy( $config_file, $destination, true );
    }

    protected function get_index_path() {
        // Get config file path.
        $upload_dir = wp_upload_dir();
        $config_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
        $index_file = $config_dir . 'fuse-index.json';

        if ( ! is_dir( $config_dir ) ) {
            wp_mkdir_p( $config_dir );
        }

        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '[]' );
        }

        return $index_file;
    }

    protected function get_config_path() {
        // Get config file path.
        $upload_dir  = wp_upload_dir();
        $config_dir  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
        $config_file = $config_dir . 'fuse-config.json';

        if ( ! is_dir( $config_dir ) ) {
            wp_mkdir_p( $config_dir );
        }

        return $config_file;
    }

    public function delete_config() {
        $config_file = $this->get_config_path();

        // Delete old index.
        if ( file_exists( $config_file ) ) {
            wp_delete_file( $config_file );
        }
    }

    public function delete_index() {
        $config_file = $this->get_index_path();

        // Delete old index.
        if ( file_exists( $config_file ) ) {
            wp_delete_file( $config_file );
        }
    }

    public function update_index( $index_item ) {
        $filesystem = Helper::get_file_system();

        if ( ! $filesystem ) {
            return false;
        }

        $config_file = $this->get_index_path();
        $content     = [];

        if ( file_exists( $config_file ) ) {
            $file_content = $filesystem->get_contents( $config_file );

            if ( ! $file_content || trim( $file_content ) === '' ) {
                $content = [];
            } else {
                $decoded_content = json_decode( $file_content, true );
                if ( $decoded_content === null && json_last_error() !== JSON_ERROR_NONE ) {
                    // Invalid JSON, reset to empty array
                    $content = [];
                } else {
                    $content = $decoded_content;
                }
            }
        } else {
        }

        $found_index = false;

        // Primary match strategy: by objectID
        foreach ( $content as $index => $item ) {
            if ( isset( $item['objectID'] ) && $item['objectID'] === $index_item['objectID'] ) {
                $found_index = $index;
                break;
            }
        }

        // Optional fallback: match by path when objectID changed but URL stayed the same
        if ( false === $found_index ) {
            $enable_path_fallback = apply_filters( 'ssp_fuse_match_by_path', true, $index_item );
            if ( $enable_path_fallback && ! empty( $index_item['path'] ) ) {
                foreach ( $content as $index => $item ) {
                    if ( isset( $item['path'] ) && $item['path'] === $index_item['path'] ) {
                        $found_index = $index;
                        break;
                    }
                }
            }
        }

        if ( $found_index !== false ) {
            $content[ $found_index ] = $index_item;
        } else {
            $content[] = $index_item;
        }

        $filesystem->put_contents( $config_file, wp_json_encode( $content ) );
    }

    /**
     * Set up the index file and add it to Simply Static options.
     *
     * @return string|bool
     */
    public function add_config() {
        $filesystem = Helper::get_file_system();

        $config_file = $this->get_config_path();

        $options = get_option( 'simply-static' );

        // Maybe use constant instead of options.
        if ( defined( 'SSP_FUSE' ) ) {
            $options = constant( 'SSP_FUSE' );
        }

        $fuse_config = array(
                'selector'  => $options['fuse_selector'] ?? '',
                'threshold' => $options['fuse_threshold'] ?? '',
        );

        $filesystem->put_contents( $config_file, wp_json_encode( $fuse_config ) );

        return $config_file;
    }
}
