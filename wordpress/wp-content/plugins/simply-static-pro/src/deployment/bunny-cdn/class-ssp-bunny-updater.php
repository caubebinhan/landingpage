<?php

namespace simply_static_pro;

use Simply_Static\Options;
use Simply_Static\Util;
use WP_Error;

/**
 * Class to handle BunnyCDN updates.
 */
class Bunny_Updater {
	/**
	 * Contains a list of files.
	 *
	 * @var array
	 */
 public static $files = [];

 /**
  * Runtime counters for better debugging/summary.
  *
  * @var int
  */
 public static $queued = 0;
 public static $uploaded = 0;
 public static $failed = 0;
 public static $skipped = 0;

 /**
  * Reset runtime counters.
  *
  * @return void
  */
 public static function reset_counters() {
     self::$queued   = 0;
     self::$uploaded = 0;
     self::$failed   = 0;
     self::$skipped  = 0;
 }

 /**
  * Get current counters snapshot.
  *
  * @return array
  */
 public static function get_counters(): array {
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
	 * Returns instance of Bunny_Updater.
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
	 * Get current pull zone.
	 *
	 * @return bool|array
	 */
	public static function get_pull_zone() {
		$options = get_option( 'simply-static' );

  // Maybe use constant instead of options.
  if ( defined( 'SSP_BUNNYCDN' ) ) {
      $options = constant( 'SSP_BUNNYCDN' );
  }

		// Get pullzones.
  $response = wp_remote_get(
            'https://api.bunny.net/pullzone',
            array(
                'headers' => array(
                    'AccessKey'    => $options['cdn_api_key'],
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
            )
        );

		if ( ! is_wp_error( $response ) ) {
			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				$body       = wp_remote_retrieve_body( $response );
				$pull_zones = json_decode( $body );

				foreach ( $pull_zones as $pull_zone ) {
					if ( $pull_zone->Name === apply_filters( 'ssp_cdn_pull_zone', $options['cdn_pull_zone'] ) ) {
						return array(
							'name'       => $pull_zone->Name,
							'zone_id'    => $pull_zone->Id,
							'storage_id' => $pull_zone->StorageZoneId,
						);
					}
				}
			} else {
    $error_message = wp_remote_retrieve_response_message( $response );
    Util::debug_log( '[BunnyCDN] Pull zone request failed: ' . $error_message );

				return false;
			}
		} else {
   $error_message = $response->get_error_message();
   Util::debug_log( '[BunnyCDN] Pull zone request error: ' . $error_message );

			return false;
		}
	}

	/**
	 * Get current storage zone.
	 *
	 * @return bool|array
	 */
	public static function get_storage_zone() {
		$options = get_option( 'simply-static' );

  // Maybe use constant instead of options.
  if ( defined( 'SSP_BUNNYCDN' ) ) {
      $options = constant( 'SSP_BUNNYCDN' );
  }

		// Get storage zones.
  $response = wp_remote_get(
            'https://api.bunny.net/storagezone',
            array(
                'headers' => array(
                    'AccessKey'    => $options['cdn_api_key'],
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
            )
        );

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body          = wp_remote_retrieve_body( $response );
				$storage_zones = json_decode( $body );

				foreach ( $storage_zones as $storage_zone ) {
					if ( $storage_zone->Name === apply_filters( 'ssp_cdn_storage_zone', $options['cdn_storage_zone'] ) ) {
						return array(
							'name'       => $storage_zone->Name,
							'storage_id' => $storage_zone->Id,
							'password'   => $storage_zone->Password
						);
					}
				}
			} else {
    $error_message = wp_remote_retrieve_response_message( $response );
    Util::debug_log( '[BunnyCDN] Storage zone request failed: ' . $error_message );

				return false;
			}
		} else {
   $error_message = $response->get_error_message();
   Util::debug_log( '[BunnyCDN] Storage zone request error: ' . $error_message );

			return false;
		}
	}

	/**
	 * Upload multiple files to BunnyCDN using cURL multi.
	 *
	 * @param array $files Array of file paths to upload.
	 *
	 * @return array Responses for each file or WP_Error objects on failure.
	 */
 public static function upload_files( array $files ) {
        $options = get_option( 'simply-static' );

  // Maybe use constant instead of options.
  if ( defined( 'SSP_BUNNYCDN' ) ) {
      $options = constant( 'SSP_BUNNYCDN' );
  }

		// Get temp dir.
		$ss_options = Options::instance();
		$temp_dir   = $ss_options->get_archive_dir();

  $multi_handle = curl_multi_init();
  $handles      = [];
  $responses    = [];

  \Simply_Static\Util::debug_log( '[BunnyCDN] Starting multi-upload batch. Files in batch: ' . count( $files ) );

		foreach ( $files as $file_key => $file_path ) {
   if ( ! file_exists( $file_path ) ) {
                $responses[ $file_key ] = new WP_Error( 'file_not_found', __( 'The specified file does not exist.', 'simply-static-pro' ) );
                \Simply_Static\Util::debug_log( '[BunnyCDN] Skipping (file not found): ' . $file_path );
                self::$skipped++;
                continue;
            }

			// Convert filepath to relative path.
			$relative_path = str_replace( $temp_dir, '', $file_path );

   if ( ! empty( $ss_options->get( 'cdn_directory' ) ) ) {
                $relative_path = trailingslashit( $ss_options->get( 'cdn_directory' ) ) . $relative_path;
            }

            $url = 'https://' . $options['cdn_storage_host'] . '/' . $options['cdn_storage_zone'] . '/' . $relative_path;

   $headers = [
                'AccessKey: ' . $options['cdn_access_key'],
                'Content-Type: application/octet-stream',
            ];

            // Create and configure cURL handle
            $ch = curl_init();
            // Open file handle so we can close it later.
            $infile_handle = fopen( $file_path, 'rb' );
            curl_setopt_array( $ch, [
                CURLOPT_URL            => $url,
                CURLOPT_PUT            => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_INFILE         => $infile_handle,
                CURLOPT_INFILESIZE     => filesize( $file_path ),
                CURLOPT_TIMEOUT        => 60,
                // Include headers in the response so we can expose useful failure details (e.g., Request-Id, reason).
                CURLOPT_HEADER         => true,
            ] );

            curl_multi_add_handle( $multi_handle, $ch );
            // Keep meta so we can log per-file on completion.
            $handles[ $file_key ] = [
                'ch'   => $ch,
                'src'  => $file_path,
                'url'  => $url,
                'dest' => $relative_path,
            ];
        }

  // Execute multi-handle
  do {
      $status = curl_multi_exec( $multi_handle, $running );
  } while ( $status === CURLM_CALL_MULTI_PERFORM || $running );

  // Collect responses
  foreach ( $handles as $file_key => $meta ) {
      $ch            = $meta['ch'];
      $http_code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
      $raw_response  = curl_multi_getcontent( $ch );

      // Separate headers and body for richer error context.
      $header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
      $raw_headers = substr( $raw_response, 0, $header_size );
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
                  $name  = strtolower( trim( $parts[0] ) );
                  $value = trim( $parts[1] );
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
          \Simply_Static\Util::debug_log( '[BunnyCDN] Upload OK (' . $http_code . '): ' . $meta['src'] . ' => ' . $meta['url'] );
      } else {
          // Build a compact, helpful failure context for logs.
          $parts = [];

          if ( $http_code === 0 ) {
              // Transport-level failure.
              $errno = curl_errno( $ch );
              $err   = curl_error( $ch );
              $parts[] = 'cURL error ' . $errno . ': ' . $err;
          } else {
              // Reason phrase from status line, if available.
              if ( ! empty( $headers_map[':status-line'] ) ) {
                  $parts[] = $headers_map[':status-line'];
              }

              // Request ID if provided by BunnyCDN.
              $request_id = $headers_map['bunny-request-id']
                  ?? $headers_map['x-request-id']
                  ?? $headers_map['request-id']
                  ?? '';
              if ( $request_id ) {
                  $parts[] = 'request-id=' . $request_id;
              }

              // Try to extract a concise message from JSON body.
              $message_snippet = '';
              $is_json = false;
              $content_type = $headers_map['content-type'] ?? '';
              if ( stripos( $content_type, 'application/json' ) !== false || ( strlen( trim( $response_body ) ) && $response_body[0] === '{' ) ) {
                  $decoded = json_decode( $response_body, true );
                  if ( is_array( $decoded ) ) {
                      $is_json = true;
                      foreach ( [ 'Message', 'message', 'error', 'Error', 'errorMessage', 'Errors' ] as $k ) {
                          if ( isset( $decoded[ $k ] ) ) {
                              $val = is_string( $decoded[ $k ] ) ? $decoded[ $k ] : wp_json_encode( $decoded[ $k ] );
                              $message_snippet = $val;
                              break;
                          }
                      }
                      if ( empty( $message_snippet ) ) {
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
          \Simply_Static\Util::debug_log( '[BunnyCDN] Upload FAILED (' . $http_code . '): ' . $meta['src'] . ' => ' . $meta['url'] . ( ! empty( $parts ) ? ' | ' . implode( ' | ', $parts ) : '' ) );
      }

      curl_multi_remove_handle( $multi_handle, $ch );
      // Close the file handle we opened for this request.
      if ( isset( $infile_handle ) && is_resource( $infile_handle ) ) {
          fclose( $infile_handle );
      }
      curl_close( $ch );
  }

		// Close the multi-handle
		curl_multi_close( $multi_handle );

        \Simply_Static\Util::debug_log( '[BunnyCDN] Finished multi-upload batch. Success: ' . self::$uploaded . ' | Failed: ' . self::$failed . ' | Skipped: ' . self::$skipped );

        return $responses;
    }

	/**
	 * Collect a batch of files and process them via cURL multibatch.
	 *
	 * @param string $file_path path in local filesystem.
	 *
	 * @return void
	 */
 public static function add_file( string $file_path, $batch_size ) {
        // Always add file first, then check threshold to avoid missing the Nth file.
        self::$files[] = $file_path;
        self::$queued++;

        // If we reached or exceeded the batch size, upload now.
        if ( count( self::$files ) >= (int) $batch_size ) {
            \Simply_Static\Util::debug_log( '[BunnyCDN] Batch size reached. Uploading current batch of ' . count( self::$files ) . ' file(s)...' );
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
 public static function add_remaining_files() {
        // If there are files left, upload them.
        if ( ! empty( self::$files ) ) {
            \Simply_Static\Util::debug_log( '[BunnyCDN] Uploading remaining files in final batch: ' . count( self::$files ) );
            self::upload_files( self::$files );

            // Clean up the list after processing.
            self::$files = [];
        }
    }

	/**
	 * Delete file from BunnyCDN storage.
	 *
	 * @param string $path given path to delete.
	 *
	 * @return bool
	 */
	public static function delete_file( string $path ): bool {
		$options      = get_option( 'simply-static' );
		$storage_zone = self::get_storage_zone();

  // Maybe use constant instead of options.
  if ( defined( 'SSP_BUNNYCDN' ) ) {
      $options = constant( 'SSP_BUNNYCDN' );
  }

		$response = wp_remote_request(
			'https://' . $options['cdn_storage_host'] . '/' . $storage_zone['name'] . $path,
			array(
				'method'  => 'DELETE',
				'headers' => array( 'AccessKey' => $options['cdn_access_key'] ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				return true;
			} else {
				$error_message = wp_remote_retrieve_response_message( $response );
				Util::debug_log( $error_message );

				return false;
			}
		} else {
			$error_message = $response->get_error_message();
			Util::debug_log( $error_message );

			return false;
		}
	}

	/**
	 * Purge Zone Cache in BunnyCDN pull zone.
	 *
	 * @return bool
	 */
	public static function purge_cache(): bool {
		$options   = get_option( 'simply-static' );
		$pull_zone = self::get_pull_zone();

  // Maybe use constant instead of options.
  if ( defined( 'SSP_BUNNYCDN' ) ) {
      $options = constant( 'SSP_BUNNYCDN' );
  }

		$response = wp_remote_post(
			'https://api.bunny.net/pullzone/' . $pull_zone['zone_id'] . '/purgeCache',
			array(
				'headers' => array(
					'AccessKey' => $options['cdn_api_key'],
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
