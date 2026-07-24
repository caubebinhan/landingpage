<?php

namespace simply_static_pro;

use Simply_Static\Util;
use Algolia\AlgoliaSearch\SearchClient;
use Exception;


/**
 * Class to handle settings for deployment.
 */
class Search_Algolia {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Contains new Index client.
	 *
	 * @var object
	 */
	public $index;

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
		$options     = get_option( 'simply-static' );
		$use_search  = $options['use_search'] ?? false;
		$search_type = $options['search_type'] ?? 'fuse';

	  if ( $use_search && 'algolia' === $search_type ) {
			// Maybe use constant instead of options. Use constant() accessor to avoid static analyzer complaints when undefined.
			if ( defined( 'SSP_ALGOLIA' ) ) {
				$options = constant( 'SSP_ALGOLIA' );
			}

			$app_id       = isset( $options['algolia_app_id'] ) ? trim( (string) $options['algolia_app_id'] ) : '';
			$admin_api_key = isset( $options['algolia_admin_api_key'] ) ? trim( (string) $options['algolia_admin_api_key'] ) : '';
			$index_name   = isset( $options['algolia_index'] ) ? trim( (string) $options['algolia_index'] ) : '';

			if ( $app_id && $admin_api_key && $index_name ) {
				try {
					$client      = SearchClient::create( $app_id, $admin_api_key );
					$this->index = $client->initIndex( $index_name );

					// Only keep config/indexing related hooks in this class.
					add_action( 'ss_after_setup_task', array( $this, 'add_config' ) );
				} catch ( \Throwable $e ) {
					$this->index = null;
				}
			}
		}
	}

	/**
	 * Clear Algolia index on full static export to prevent duplicates.
	 *
	 * @return void
	 */
  public function delete_index() {
		$use_single = get_option( 'simply-static-use-single' );
		$use_build  = get_option( 'simply-static-use-build' );

		if ( empty( $use_build ) && empty( $use_single ) ) {
			if ( empty( $this->index ) ) { return; }
			try {
				$this->index->clearObjects();
			} catch ( \Throwable $e ) {
			}
		}
  }

	/**
	 * Set up the index file and add it to Simply Static options.
	 *
	 * @return string|bool
	 */
	public function add_config() {
		$filesystem = Helper::get_file_system();

		if ( ! $filesystem ) {
			return false;
		}

		// Get config file path.
		$upload_dir  = wp_upload_dir();
		$config_dir  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
		$config_file = $config_dir . 'algolia.json';

		// Delete old index.
		if ( file_exists( $config_file ) ) {
			wp_delete_file( $config_file );
		}

		// Check if directory exists.
		if ( ! is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir );
		}

  $options = get_option( 'simply-static' );

  // Maybe use constant instead of options. Use constant() accessor to avoid static analyzer complaints when undefined.
  if ( defined( 'SSP_ALGOLIA' ) ) {
      $options = constant( 'SSP_ALGOLIA' );
  }

		// Save Algolia settings to config file.
		$algolia_config = array(
			'app_id'      => $options['algolia_app_id'],
			'api_key'     => $options['algolia_search_api_key'],
			'index'       => $options['algolia_index'],
			'selector'    => $options['algolia_selector'],
			'use_excerpt' => apply_filters( 'ssp_algolia_use_excerpt', true ),
		);

		$filesystem->put_contents( $config_file, wp_json_encode( $algolia_config ) );

		return $config_file;
	}

    /**
     * Upsert an Algolia record with robust matching strategy.
     *
     * Strategy:
     *  - Primary: update by objectID (fast path).
     *  - Fallback (optional): if not found and a path exists, search by exact path and
     *    delete any existing records for that path before saving the new object to avoid duplicates.
     *
     * Filters:
     *  - ssp_algolia_match_by_path (bool, default true): enable path fallback matching
     *  - ssp_algolia_path_attribute (string, default 'path'): which attribute holds the URL path
     *  - ssp_algolia_restrict_searchable_attributes (array|null): attributes list to restrict search to; defaults to array( $path_attr )
     *
     * @param array $index_item
     * @return bool
     */
    public function upsert_index_item( array $index_item ) : bool {
    			if ( empty( $this->index ) ) { return false; }

        // Ensure Algolia record doesn't exceed size limits (10KB hard limit by Algolia)
        $index_item = $this->enforce_size_limits( $index_item );

        $object_id = isset( $index_item['objectID'] ) ? (string) $index_item['objectID'] : '';
        $path_attr = apply_filters( 'ssp_algolia_path_attribute', 'path', $index_item );
        $path_val  = isset( $index_item[ $path_attr ] ) ? (string) $index_item[ $path_attr ] : '';

        // 1) Try to update by objectID first
        $found_by_object_id = false;
        if ( $object_id !== '' ) {
            try {
                // getObject will throw on 404/not found
                $this->index->getObject( $object_id );
                $found_by_object_id = true;
            } catch ( \Throwable $e ) {
                // Not found or API error; we'll fall back to path matching if enabled
            }
        }

  if ( $found_by_object_id ) {
            try {
                $this->index->saveObject( $index_item );
                return true;
            } catch ( \Throwable $e ) {
                // Continue to path fallback below
                \Simply_Static\Util::debug_log( 'Algolia upsert (by objectID) failed for objectID=' . $object_id . ' error=' . $e->getMessage() );
            }
        }

        // 2) Optional fallback: match by path and replace to prevent duplicates
        $enable_path_fallback = apply_filters( 'ssp_algolia_match_by_path', true, $index_item );
        if ( $enable_path_fallback && $path_val !== '' ) {
            // Build optional search params. Some indices may not have `path` in searchableAttributes.
            // If Algolia rejects restrictSearchableAttributes, retry without restriction.
            $restrict = apply_filters( 'ssp_algolia_restrict_searchable_attributes', null, $index_item );
            $params   = array();
            if ( is_array( $restrict ) && ! empty( $restrict ) ) {
                $params['restrictSearchableAttributes'] = $restrict;
            }

            $hits = array();
            try {
                $results = $this->index->search( $path_val, $params );
                $hits    = isset( $results['hits'] ) && is_array( $results['hits'] ) ? $results['hits'] : array();
            } catch ( \Throwable $e ) {
                // Retry without restrictSearchableAttributes.
                try {
                    $results = $this->index->search( $path_val, array() );
                    $hits    = isset( $results['hits'] ) && is_array( $results['hits'] ) ? $results['hits'] : array();
                } catch ( \Throwable $e2 ) {
                    $hits = array();
                }
            }

            $old_ids = array();
            foreach ( $hits as $hit ) {
                if ( isset( $hit[ $path_attr ] ) && (string) $hit[ $path_attr ] === $path_val ) {
                    $hit_id = isset( $hit['objectID'] ) ? (string) $hit['objectID'] : '';
                    if ( $hit_id !== '' && $hit_id !== $object_id ) {
                        $old_ids[] = $hit_id;
                    }
                }
            }

            if ( ! empty( $old_ids ) ) {
                try {
                    $this->index->deleteObjects( $old_ids );
                } catch ( \Throwable $e ) {
                    // Non-fatal; proceed to saveObject anyway
                    \Simply_Static\Util::debug_log( 'Algolia deleteObjects failed for path=' . $path_val . ' ids=' . implode( ',', $old_ids ) . ' error=' . $e->getMessage() );
                }
            }

            try {
                $this->index->saveObject( $index_item );
                return true;
            } catch ( \Throwable $e ) {
                // Fall through to final save below
                \Simply_Static\Util::debug_log( 'Algolia saveObject (after path cleanup) failed for path=' . $path_val . ' error=' . $e->getMessage() );
            }
        }

        // 3) Final attempt: just save the object (creates or overwrites by objectID)
    			try {
			$this->index->saveObject( $index_item );
			return true;
		} catch ( \Throwable $e ) {
            \Simply_Static\Util::debug_log( 'Algolia final saveObject failed for objectID=' . ( $index_item['objectID'] ?? '' ) . ' error=' . $e->getMessage() );
			return false;
		}
    }
    
    /**
     * Enforce Algolia record size limits (~10KB). Truncates content/excerpt as needed.
     *
     * Filters:
     *  - ssp_algolia_max_record_bytes (int, default 9500): Max total JSON bytes we allow before sending (leave headroom under 10KB)
     *  - ssp_algolia_max_content_bytes (int, default 8000): Hard cap for content field alone
     *  - ssp_algolia_max_excerpt_bytes (int, default 1000): Hard cap for excerpt field alone
     */
    private function enforce_size_limits( array $index_item ) : array {
        try {
            $max_total   = (int) apply_filters( 'ssp_algolia_max_record_bytes', 9500, $index_item );
            $max_content = (int) apply_filters( 'ssp_algolia_max_content_bytes', 8000, $index_item );
            $max_excerpt = (int) apply_filters( 'ssp_algolia_max_excerpt_bytes', 1000, $index_item );

            $content = isset( $index_item['content'] ) ? (string) $index_item['content'] : '';
            $excerpt = isset( $index_item['excerpt'] ) ? (string) $index_item['excerpt'] : '';

            // Hard cap individual fields first
            if ( $content !== '' && strlen( $content ) > $max_content ) {
                $index_item['content'] = $this->mb_strcut_safe( $content, $max_content );
            }
            if ( $excerpt !== '' && strlen( $excerpt ) > $max_excerpt ) {
                $index_item['excerpt'] = $this->mb_strcut_safe( $excerpt, $max_excerpt );
            }

            // Then ensure total JSON payload is under the limit
            $encoded = wp_json_encode( $index_item );
            if ( is_string( $encoded ) ) {
                $size = strlen( $encoded );
                if ( $size > $max_total ) {
                    // Reduce content first based on overflow
                    $overflow = $size - $max_total;
                    $content  = isset( $index_item['content'] ) ? (string) $index_item['content'] : '';
                    if ( $content !== '' ) {
                        $reduce_by = $overflow + 128; // some headroom
                        $new_len   = max( 0, strlen( $content ) - $reduce_by );
                        $index_item['content'] = $this->mb_strcut_safe( $content, $new_len );
                        $encoded = wp_json_encode( $index_item );
                        $size    = is_string( $encoded ) ? strlen( $encoded ) : $max_total;
                    }

                    // If still too large, trim excerpt too
                    if ( $size > $max_total ) {
                        $excerpt = isset( $index_item['excerpt'] ) ? (string) $index_item['excerpt'] : '';
                        if ( $excerpt !== '' ) {
                            $overflow2 = $size - $max_total;
                            $reduce_by = $overflow2 + 64;
                            $new_len   = max( 0, strlen( $excerpt ) - $reduce_by );
                            $index_item['excerpt'] = $this->mb_strcut_safe( $excerpt, $new_len );
                        }
                    }
                }
            }
        } catch ( \Throwable $e ) {
            // Never break indexing due to size enforcement logic
            \Simply_Static\Util::debug_log( 'Algolia size enforcement error: ' . $e->getMessage() );
        }

        return $index_item;
    }

    /**
     * Multibyte-safe byte-length limiter using mb_strcut if available.
     */
    private function mb_strcut_safe( string $str, int $max_bytes ) : string {
        if ( $max_bytes <= 0 ) { return ''; }
        if ( function_exists( 'mb_strcut' ) ) {
            return mb_strcut( $str, 0, $max_bytes, 'UTF-8' );
        }
        // Fallback to substr by bytes
        if ( strlen( $str ) <= $max_bytes ) { return $str; }
        return substr( $str, 0, $max_bytes );
    }
}
