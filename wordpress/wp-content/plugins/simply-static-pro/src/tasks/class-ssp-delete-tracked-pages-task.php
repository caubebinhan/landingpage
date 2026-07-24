<?php

namespace simply_static_pro;

use Simply_Static\Options;
use Simply_Static\Util;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro task: Delete files for items recorded by the Deleted Pages Tracker.
 *
 * Runs on Update and Full (Export) runs. Before discovering changes/fetching URLs,
 * it removes any files on the destination static site that correspond to content
 * deleted (or made non-public) in WordPress since the last export.
 */
class Delete_Tracked_Pages_Task extends \Simply_Static\Task {
    /**
     * Task name.
     *
     * @var string
     */
    public static $task_name = 'delete_tracked_pages';

    /**
     * Perform the task in batches.
     *
     * @return bool True when done; false when more to process.
     */
    public function perform() {
        // Run only on update or full exports (not on single/build).
        $generate_type = $this->options->get( 'generate_type' );
        if ( ! in_array( $generate_type, array( 'update', 'export' ), true ) ) {
            return true;
        }

        // Ensure tracker table exists; if not, nothing to do.
        global $wpdb;
        $table = $wpdb->prefix . 'simply_static_delete_pages';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return true;
        }

        $this->save_status_message( __( 'Deleting tracked pages/files from destination', 'simply-static-pro' ) );

        $batch_size = (int) apply_filters( 'ssp_delete_tracked_batch_size', 50 );
        if ( $batch_size < 1 ) { $batch_size = 50; }

        // Fetch a batch of rows to delete.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE site_id = %d ORDER BY id ASC LIMIT %d", get_current_blog_id(), $batch_size ), ARRAY_A );

        if ( empty( $rows ) ) {
            // Nothing left to delete.
            $this->save_status_message( __( 'No tracked deletions to process', 'simply-static-pro' ) );
            return true;
        }

        $options = get_option( 'simply-static' );
        $delivery = isset( $options['delivery_method'] ) ? $options['delivery_method'] : 'zip';
        $origin   = untrailingslashit( get_bloginfo( 'url' ) );

        $deleted_ok = 0;
        foreach ( $rows as $row ) {
            $file_path = isset( $row['file_path'] ) ? (string) $row['file_path'] : '';
            $meta_json = isset( $row['meta'] ) ? $row['meta'] : null;
            $this->delete_by_delivery( $delivery, $file_path, $origin, $options );

            // If media sizes are present, delete those as well (Local/GitHub/S3 where applicable).
            if ( ! empty( $meta_json ) ) {
                $meta = json_decode( $meta_json, true );
                if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                    foreach ( $meta['sizes'] as $size ) {
                        if ( isset( $size['path'] ) && is_string( $size['path'] ) ) {
                            $this->delete_by_delivery( $delivery, ltrim( (string) $size['path'], '/' ), $origin, $options );
                        }
                    }
                }
            }

            // Remove DB row after attempting deletion, regardless of outcome, to avoid reprocessing forever.
            $wpdb->delete( $table, [ 'id' => (int) $row['id'] ], [ '%d' ] );
            $deleted_ok++;

            // Clear variables to free memory.
            unset( $row, $file_path, $meta_json, $meta );
        }

        // Clear rows after loop.
        unset( $rows );

        // Progress message. Do not try to compute total synchronously (could be large); show batch progress.
        $this->save_status_message( sprintf( __( 'Deleted %d tracked pages/files', 'simply-static-pro' ), $deleted_ok ) );

        // Return false if we likely have more to process; true when finished.
        // Check quickly if there are any remaining rows.
        $remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE site_id = %d", get_current_blog_id() ) );
        return $remaining === 0;
    }

    /**
     * Delete a single file path according to the configured delivery method.
     *
     * @param string $delivery Delivery method slug.
     * @param string $file_path Relative path within static site (e.g., 'about/index.html' or 'wp-content/uploads/2025/01/img.jpg').
     * @param string $origin    Origin site URL.
     * @param array  $options   Simply Static options array.
     *
     * @return void
     */
    private function delete_by_delivery( string $delivery, string $file_path, string $origin, array $options ) : void {
        $file_path = ltrim( $file_path, '/\\' );

        // Skip empty paths.
        if ( empty( $file_path ) ) {
            Util::debug_log( '[DeleteTrackedPages] Skipping empty file path.' );
            return;
        }

        // Skip paths that contain query strings (e.g., ?p=123/index.html) as these are malformed.
        if ( strpos( $file_path, '?' ) !== false ) {
            Util::debug_log( '[DeleteTrackedPages] Skipping malformed path with query string: ' . $file_path );
            return;
        }

        // Skip paths that start with special characters that would be invalid file paths.
        $first_char = substr( $file_path, 0, 1 );
        if ( in_array( $first_char, array( '?', '#', '&' ), true ) ) {
            Util::debug_log( '[DeleteTrackedPages] Skipping path starting with invalid character: ' . $file_path );
            return;
        }

        switch ( $delivery ) {
            case 'local':
                $base = isset( $options['local_dir'] ) ? rtrim( (string) $options['local_dir'], "/\\" ) : '';
                if ( '' === $base ) { return; }
                $abs = trailingslashit( $base ) . $file_path;

                // Safety: ensure the resolved path stays within base directory.
                $real_base = realpath( $base );
                $real_abs  = $abs;
                if ( function_exists( 'realpath' ) ) {
                    $real_abs = realpath( $abs ) ?: $abs; // may not exist yet
                }
                if ( $real_base && strpos( (string) $real_abs, (string) $real_base ) !== 0 ) {
                    return; // outside base, skip
                }

                // Delete file if exists.
                if ( file_exists( $abs ) && is_file( $abs ) ) {
                    unlink( $abs );
                }

                // If it's an index.html, try to remove its directory as well if empty.
                if ( substr( $file_path, -10 ) === 'index.html' ) {
                    $dir = dirname( $abs );
                    // Also attempt to remove feed dir if applicable (e.g., feed/index.xml) when path suggests a directory page
                    $this->maybe_delete_empty_dir( $dir );
                }
                break;

            case 'github':
                if ( ! class_exists( '\\simply_static_pro\\Github_Repository' ) ) { return; }
                $github = Github_Repository::get_instance();
                // GitHub paths are repository-relative, no leading slash.
                $github->delete_file( $file_path, __( 'Deleted file via tracker', 'simply-static-pro' ) );
                // If directory page, also delete potential feed file.
                if ( substr( $file_path, -10 ) === 'index.html' ) {
                    $feed = rtrim( dirname( $file_path ), '/\\' ) . '/feed/index.xml';
                    $github->delete_file( ltrim( $feed, '/' ), __( 'Deleted feed via tracker', 'simply-static-pro' ) );
                }
                break;

            case 'cdn':
                if ( ! class_exists( '\\simply_static_pro\\Bunny_Updater' ) ) { return; }
                $bunny = Bunny_Updater::get_instance();
                $sub_directory = isset( $options['cdn_directory'] ) ? (string) $options['cdn_directory'] : '';
                $path = $sub_directory ? trailingslashit( untrailingslashit( $sub_directory ) ) . $file_path : $file_path;
                $bunny->delete_file( '/' . ltrim( $path, '/' ) );
                break;

            case 'aws-s3':
                if ( ! class_exists( '\\simply_static_pro\\S3_Client' ) ) { return; }
                $opts   = Options::instance();
                $bucket = $opts->get( 'aws_bucket' );
                $secret = $opts->get( 'aws_access_secret' );
                $key    = $opts->get( 'aws_access_key' );
                $region = $opts->get( 'aws_region' );

                $client = new S3_Client();
                $client->set_bucket( $bucket )
                       ->set_api_secret( $secret )
                       ->set_api_key( $key )
                       ->set_region( $region );

                $client->delete_file( $file_path );
                break;

            case 'simply-static-studio':
                if ( ! class_exists( '\\simply_static_pro\\Simply_Static_Studio_Updater' ) ) {
                    return;
                }
                Simply_Static_Studio_Updater::delete_file( '/' . ltrim( $file_path, '/' ) );
                break;

            // For zip, sftp, tiiny deployments we currently do not have a persistent destination to delete from at this stage.
            // We intentionally no-op to avoid unexpected side-effects. Future work can add targeted deletions for those providers.
            default:
                break;
        }
    }

    /**
     * Attempt to delete a directory if it becomes empty.
     */
    private function maybe_delete_empty_dir( string $dir ) : void {
        if ( ! is_dir( $dir ) ) { return; }
        $enable = apply_filters( 'ssp_delete_tracked_post_delete_cleanup', true, $dir );
        if ( ! $enable ) { return; }

        // Remove feed/ and the directory itself if empty.
        $entries = scandir( $dir );
        if ( is_array( $entries ) && count( array_diff( $entries, array('.', '..') ) ) === 0 ) {
            rmdir( $dir );
        }
    }
}
