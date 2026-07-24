<?php

namespace simply_static_pro;

use Simply_Static\Options;
use Simply_Static\Util;
use WP_Error;

/**
 * Class to handle Simply Static Studio updates.
 */
class Simply_Static_Studio_Updater {
    /**
     * Contains a list of files.
     *
     * @var array
     */
    public static $files = [];

    /**
     * Deployment counters.
     *
     * @var int
     */
    protected static int $queued = 0;
    protected static int $uploaded = 0;
    protected static int $failed = 0;
    protected static int $skipped = 0;

    /**
     * Reset deployment counters and pending file list.
     */
    public static function reset_counters() : void {
        self::$queued   = 0;
        self::$uploaded = 0;
        self::$failed   = 0;
        self::$skipped  = 0;
        self::$files    = [];
    }

    /**
     * Get current deployment counters.
     */
    public static function get_counters() : array {
        return [
            'queued'   => self::$queued,
            'uploaded' => self::$uploaded,
            'failed'   => self::$failed,
            'skipped'  => self::$skipped,
        ];
    }

	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Simply_Static_Studio_Updater.
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
	 * Upload multiple files to BunnyCDN using cURL multi.
	 *
	 * @param array $files Array of file paths to upload.
	 *
	 * @return array Responses for each file or WP_Error objects on failure.
	 */
 public static function upload_files( array $files ) {
        // Get temp dir.
        $options  = Options::instance();
        $temp_dir = $options->get_archive_dir();

        // Validate required constants safely to avoid notices when undefined.
        if ( ! defined( 'SSS_STORAGE_HOST' ) || ! defined( 'SSS_STORAGE_ZONE' ) || ! defined( 'SSS_ACCESS_KEY' ) ) {
            Util::debug_log( '[StaticStudio] Required constants missing for deployment - please contact support.' );
            return array_fill_keys( array_keys( $files ), new WP_Error( 'missing_constants', __( 'Static Studio deployment constants are missing.', 'simply-static-pro' ) ) );
        }

        $storage_host = constant( 'SSS_STORAGE_HOST' );
        $storage_zone = constant( 'SSS_STORAGE_ZONE' );
        $access_key   = constant( 'SSS_ACCESS_KEY' );

        $multi_handle = curl_multi_init();
        $handles      = [];
        $responses    = [];

        foreach ( $files as $file_key => $file_path ) {
            if ( ! file_exists( $file_path ) ) {
                // Missing file: record and count as skipped.
                $responses[ $file_key ] = new WP_Error( 'file_not_found', __( 'The specified file does not exist.', 'simply-static-pro' ) );
                self::$skipped++;
                continue;
            }

            // Convert filepath to relative path.
            $relative_path = str_replace( $temp_dir, '', $file_path );
            $url           = 'https://' . $storage_host . '/' . $storage_zone . '/' . ltrim( $relative_path, '/' );

            $headers = [
                'AccessKey: ' . $access_key,
                'Content-Type: application/octet-stream',
            ];

            // Create and configure cURL handle
            $ch            = curl_init();
            $infile_handle = fopen( $file_path, 'rb' );
            curl_setopt_array( $ch, [
                CURLOPT_URL            => $url,
                CURLOPT_PUT            => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_INFILE         => $infile_handle,
                CURLOPT_INFILESIZE     => filesize( $file_path ),
                CURLOPT_TIMEOUT        => 60,
                // Include headers in the response to expose helpful failure details.
                CURLOPT_HEADER         => true,
            ] );

            curl_multi_add_handle( $multi_handle, $ch );
            // Keep meta for logging on completion and closing handles.
            $handles[ $file_key ] = [
                'ch'     => $ch,
                'src'    => $file_path,
                'url'    => $url,
                'dest'   => $relative_path,
                'handle' => $infile_handle,
            ];

            // Count as queued once it is scheduled for upload.
            self::$queued++;
        }

        // Execute multi-handle
        do {
            $status = curl_multi_exec( $multi_handle, $running );
        } while ( $status === CURLM_CALL_MULTI_PERFORM || $running );

        // Collect responses
        foreach ( $handles as $file_key => $meta ) {
            $ch           = $meta['ch'];
            $http_code    = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $raw_response = curl_multi_getcontent( $ch );

            // Separate headers and body for richer error context.
            $header_size   = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
            $raw_headers   = substr( $raw_response, 0, $header_size );
            $response_body = substr( $raw_response, $header_size );

            // Parse headers into an associative array (case-insensitive keys).
            $headers_map = [];
            if ( $raw_headers ) {
                $lines = preg_split( "/\r?\n/", trim( $raw_headers ) );
                foreach ( $lines as $i => $line ) {
                    if ( $i === 0 ) {
                        // status line like HTTP/1.1 400 Bad Request
                        $headers_map[':status-line'] = $line;
                        continue;
                    }
                    $parts = explode( ':', $line, 2 );
                    if ( count( $parts ) === 2 ) {
                        $name               = strtolower( trim( $parts[0] ) );
                        $value              = trim( $parts[1] );
                        $headers_map[ $name ] = $value;
                    }
                }
            }

            if ( $http_code >= 200 && $http_code < 300 ) {
                $responses[ $file_key ] = [
                    'code' => $http_code,
                    'body' => $response_body,
                ];
                self::$uploaded++;
                Util::debug_log( '[StaticStudio] Upload OK (' . $http_code . '): ' . $meta['src'] . ' => ' . $meta['url'] );
            } else {
                // Build a compact, helpful failure context for logs.
                $parts = [];

                if ( $http_code === 0 ) {
                    // Transport-level failure.
                    $errno    = curl_errno( $ch );
                    $err      = curl_error( $ch );
                    $parts[]  = 'cURL error ' . $errno . ': ' . $err;
                } else {
                    // Reason phrase from status line, if available.
                    if ( ! empty( $headers_map[':status-line'] ) ) {
                        $parts[] = $headers_map[':status-line'];
                    }

                    // Request ID if provided by edge.
                    $request_id = $headers_map['bunny-request-id']
                        ?? $headers_map['x-request-id']
                        ?? $headers_map['request-id']
                        ?? '';
                    if ( $request_id ) {
                        $parts[] = 'request-id=' . $request_id;
                    }

                    // Try to extract a concise message from JSON body.
                    $message_snippet = '';
                    $content_type    = $headers_map['content-type'] ?? '';
                    if ( stripos( $content_type, 'application/json' ) !== false || ( strlen( trim( $response_body ) ) && $response_body[0] === '{' ) ) {
                        $decoded = json_decode( $response_body, true );
                        if ( is_array( $decoded ) ) {
                            foreach ( [ 'Message', 'message', 'error', 'Error', 'errorMessage', 'Errors' ] as $k ) {
                                if ( isset( $decoded[ $k ] ) ) {
                                    $val             = is_string( $decoded[ $k ] ) ? $decoded[ $k ] : wp_json_encode( $decoded[ $k ] );
                                    $message_snippet = $val;
                                    break;
                                }
                            }
                            if ( $message_snippet === '' ) {
                                $message_snippet = wp_json_encode( $decoded );
                            }
                        }
                    }

                    if ( $message_snippet === '' ) {
                        // Fallback to a trimmed body snippet.
                        $flat_body = trim( preg_replace( '/\s+/', ' ', $response_body ) );
                        if ( strlen( $flat_body ) > 300 ) {
                            $flat_body = substr( $flat_body, 0, 300 ) . '…';
                        }
                        if ( $flat_body !== '' ) {
                            $message_snippet = $flat_body;
                        }
                    }

                    if ( $message_snippet !== '' ) {
                        $parts[] = 'reason=' . $message_snippet;
                    }
                }

                $responses[ $file_key ] = new WP_Error(
                    'http_error',
                    sprintf( __( 'HTTP error %d', 'simply-static-pro' ), $http_code ) . ( ! empty( $parts ) ? ' | ' . implode( ' | ', $parts ) : '' )
                );
                self::$failed++;
                Util::debug_log( '[StaticStudio] Upload FAILED (' . $http_code . '): ' . $meta['src'] . ' => ' . $meta['url'] . ( ! empty( $parts ) ? ' | ' . implode( ' | ', $parts ) : '' ) );
            }

            curl_multi_remove_handle( $multi_handle, $ch );
            // Close file handle we opened above.
            if ( isset( $meta['handle'] ) && is_resource( $meta['handle'] ) ) {
                fclose( $meta['handle'] );
            }
            curl_close( $ch );
        }

        // Close the multi-handle
        curl_multi_close( $multi_handle );

        return $responses;
    }

	/**
	 * Collect a batch of files and process them via cURL multibatch.
	 *
	 * @param string $file_path path in local filesystem.
	 *
	 * @return void
	 */
	public static function batch_file( string $file_path, $batch_size ) {
		// Always add the current file first.
		self::$files[] = $file_path;

		// If we've reached or exceeded the batch size, upload and reset.
		if ( count( self::$files ) >= $batch_size ) {
			self::upload_files( self::$files );
			// Clean the list for the next iteration.
			self::$files = [];
		}
	}

	/**
	 * Add remaining files, even if they are fewer than the batch size.
	 *
	 * @return void
	 */
	public static function batch_remaining_files() {
		// If there are files left, upload them.
		if ( ! empty( self::$files ) ) {
			self::upload_files( self::$files );

			// Clean up the list after processing.
			self::$files = [];
		}
	}


	/**
	 * Delete file from Simply Static Studio.
	 *
	 * @param string $path given path to delete.
	 *
	 * @return bool
	 */
	public static function delete_file( string $path ): bool {
		// Exit if constant is not defined.
		if ( ! defined( 'SSS_STORAGE_HOST' ) || ! defined( 'SSS_STORAGE_ZONE' ) || ! defined( 'SSS_ACCESS_KEY' ) ) {
			Util::debug_log( '[StaticStudio] Required constants missing for deployment - please contact support.' );
			return false;
		}

		// Validate path to prevent accidental deletion of root/entire storage zone.
		$path = trim( $path );
		if ( empty( $path ) || $path === '/' || $path === '/*' ) {
			Util::debug_log( '[StaticStudio] Delete aborted: invalid or dangerous path "' . $path . '" - refusing to delete root.' );
			return false;
		}

		// Skip paths that contain query strings (e.g., ?p=123) as these are malformed static paths.
		if ( strpos( $path, '?' ) !== false ) {
			Util::debug_log( '[StaticStudio] Delete skipped: path contains query string "' . $path . '" - invalid static file path.' );
			return false;
		}

		// Skip paths that start with special characters that would be invalid file paths.
		$first_char = substr( ltrim( $path, '/' ), 0, 1 );
		if ( in_array( $first_char, array( '?', '#', '&' ), true ) ) {
			Util::debug_log( '[StaticStudio] Delete skipped: path starts with invalid character "' . $path . '".' );
			return false;
		}

		$url = 'https://' . constant( 'SSS_STORAGE_HOST' ) . '/' . constant( 'SSS_STORAGE_ZONE' ) . $path;

		Util::debug_log( '[StaticStudio] Attempting to delete: ' . $path );

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'headers' => array( 'AccessKey' => constant( 'SSS_ACCESS_KEY' ) ),
				'timeout' => 30,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $http_code, array( 200, 404 ), true ) ) {
				Util::debug_log( '[StaticStudio] Delete OK (' . $http_code . '): ' . $path );
				return true;
			} else {
				$error_message = wp_remote_retrieve_response_message( $response );
				$body = wp_remote_retrieve_body( $response );
				Util::debug_log( '[StaticStudio] Delete FAILED (' . $http_code . '): ' . $path . ' - ' . $error_message . ( $body ? ' | Body: ' . substr( $body, 0, 200 ) : '' ) );
				return false;
			}
		} else {
			$error_message = $response->get_error_message();
			Util::debug_log( '[StaticStudio] Delete ERROR: ' . $path . ' - ' . $error_message );
			return false;
		}
	}

	/**
	 * Add a file to Simply Static Studio.
	 *
	 * @param string $file_path given path to upload.
	 *
	 * @return bool
	 */
	public static function add_file( string $file_path ): bool {
		// Exit if constant is not defined.
  if ( ! defined( 'SSS_STORAGE_HOST' ) || ! defined( 'SSS_STORAGE_ZONE' ) || ! defined( 'SSS_ACCESS_KEY' ) ) {
            Util::debug_log( '[StaticStudio] Required constants missing for deployment - please contact support.' );

			return false;
		}

		$filesystem = Helper::get_file_system();
		$content    = $filesystem->get_contents( $file_path );

		// Get relative path for CDN.
		$home    = get_home_path();
		$to_path = str_replace( $home, '/', $file_path );

		// Convert given path to relative path.
  $response = wp_remote_request(
            'https://' . constant( 'SSS_STORAGE_HOST' ) . '/' . constant( 'SSS_STORAGE_ZONE' ) . $to_path,
            array(
                'method'  => 'PUT',
                'headers' => array( 'AccessKey' => constant( 'SSS_ACCESS_KEY' ) ),
                'body'    => $content,
            )
        );

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				return true;
			} else {
    $error_message = wp_remote_retrieve_response_message( $response );
    Util::debug_log( '[StaticStudio] ' . $error_message );

				return false;
			}
		} else {
   $error_message = $response->get_error_message();
   Util::debug_log( '[StaticStudio] ' . $error_message );

			return false;
		}
	}

	/**
	 * Purge cache on Simply Static Studio.
	 *
	 */
	public static function purge_cache() {
		// Exit if constant is not defined.
  if ( defined( 'SSS_PULL_ZONE' ) ) {
            $response = wp_remote_request(
                'https://api.static.studio/functions/v1/clear-cache',
                array(
                    'method' => 'POST',
                    'body'   => json_encode( array( 'pull_zone' => constant( 'SSS_PULL_ZONE' ) ) ),
                )
            );

			if ( ! is_wp_error( $response ) ) {
				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
                    Util::debug_log( '[StaticStudio] Successfully cleared the cache.' );
                } else {
                    $error_message = wp_remote_retrieve_response_message( $response );
                    Util::debug_log( '[StaticStudio] ' . $error_message );
                }
            } else {
                $error_message = $response->get_error_message();
                Util::debug_log( '[StaticStudio] ' . $error_message );
            }
        } else {
            Util::debug_log( '[StaticStudio] Required constants missing for clearing cache - please contact support.' );
        }
    }
}
