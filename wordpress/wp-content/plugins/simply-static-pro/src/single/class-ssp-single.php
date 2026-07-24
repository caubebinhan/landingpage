<?php

namespace simply_static_pro;

use Algolia\AlgoliaSearch\SearchClient;
use Exception;
use Simply_Static\Page;
use Simply_Static\Plugin;
use Simply_Static\Options;
use Simply_Static\Url_Fetcher;
use Simply_Static\Util;
use DOMDocument;
use DOMXPath;

/**
 * Class to handle settings for single.
 */
class Single {

	protected $export_assets = null;

	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Single.
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
	 * Constructor for Single.
	 */
	public function __construct() {
		add_action( 'simply_static_schedule_single_export', array( $this, 'run_scheduled_single_export' ) );
		add_action( 'save_post', array( $this, 'schedule_single_export' ) );
		add_action( 'elementor/editor/after_save', array( $this, 'schedule_single_export' ) );
		add_action( 'publish_future_post', array( $this, 'schedule_future_single_export' ) );
		// AJAX handlers for single export actions.
		add_action( 'wp_ajax_apply_single', array( $this, 'apply_single' ) );
		add_action( 'wp_ajax_nopriv_apply_single', array( $this, 'apply_single' ) );
		add_filter( 'ss_static_pages', array( $this, 'filter_static_pages' ), 10, 2 );
		add_filter( 'ss_remaining_pages', array( $this, 'filter_remaining_pages' ), 10, 2 );
		add_filter( 'ss_total_pages_log', array( $this, 'filter_total_pages_log' ) );
		add_filter( 'ss_total_pages', array( $this, 'filter_total_pages' ) );
		add_action( 'ss_before_perform_archive_action', array( $this, 'clear_single' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );
		// Ensure scripts are available in Elementor editor as well.
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_elementor_scripts' ) );
		add_action( 'simply_static_child_page_found_on_url_before_save', array( $this, 'prepare_static_page' ), 20, 2 );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		// Ensure the script is also present in the Block Editor (Gutenberg) context.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_scripts' ) );
	}

	public function rest_api_init() {

		register_rest_route( 'simplystatic/v1', '/apply-single', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'maybe_apply_single' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );
	}

	public function maybe_apply_single() {

		// Respect global setting to disable Single Exports
		$opts = get_option( 'simply-static' );
		if ( is_array( $opts ) && isset( $opts['ss_use_single_exports'] ) && false === (bool) $opts['ss_use_single_exports'] ) {
			return json_encode( [
				'status'  => 403,
				'message' => __( 'Single Exports are disabled in Simply Static settings.', 'simply-static-pro' ),
			] );
		}

		$options = get_option( 'simply-static' );
		$targets = [];
		if ( isset( $options['ss_single_pages'] ) && is_array( $options['ss_single_pages'] ) && ! empty( $options['ss_single_pages'] ) ) {
			$targets = array_map( 'intval', $options['ss_single_pages'] );
		} else {
			// Fallback: use homepage if configured, else error as before.
			$homepage = get_option( 'page_on_front' );

			if ( ! $homepage ) {
				return json_encode( [
					'status'  => 404,
					'message' => __( 'Please select a homepage under WordPress Settings > Reading' )
				] );
			}

			$targets = [ (int) $homepage ];
		}

		// Prepare each selected page/post for single export.
		foreach ( $targets as $single_id ) {
			if ( $single_id && get_post_status( $single_id ) ) {
				$this->prepare_single_export( $single_id, false );
			}
		}

		// Start static export.
		$ss = Plugin::instance();
		$ss->run_static_export();

		// Exit now.
		$response = array( 'success' => true );
		print wp_json_encode( $response );
		exit;
	}

 public function schedule_single_export( $post_id ) {
		// Respect global setting to disable Single Exports
		$opts = get_option( 'simply-static' );
		if ( is_array( $opts ) && isset( $opts['ss_use_single_exports'] ) && false === (bool) $opts['ss_use_single_exports'] ) {
			return;
		}

  // Check if auto export is enabled, with defaults from Simply Static settings.
  $opts         = get_option( 'simply-static' );
  $default_on   = is_array( $opts ) && ! empty( $opts['ss_single_auto_export'] );
  $default_delay = is_array( $opts ) && isset( $opts['ss_single_auto_export_delay'] ) ? absint( $opts['ss_single_auto_export_delay'] ) : 3;
  $auto_export  = apply_filters( 'ssp_single_auto_export', $default_on );
  $export_delay = apply_filters( 'ssp_single_auto_export_delay', $default_delay );

  if ( ! $auto_export ) {
      return;
  }

  // Respect allowed post types selection for Auto Export
  $post_type = get_post_type( $post_id );
  if ( $post_type ) {
      $allowed = array();
      if ( is_array( $opts ) && isset( $opts['ss_single_auto_export_types'] ) && is_array( $opts['ss_single_auto_export_types'] ) ) {
          $allowed = $opts['ss_single_auto_export_types'];
      }
      if ( empty( $allowed ) ) {
          // Default to all public post types (excluding attachment, elementor_library, ssp-form)
          $types = get_post_types( array( 'public' => true ), 'names' );
          unset( $types['attachment'], $types['elementor_library'], $types['ssp-form'] );
          $allowed = array_values( $types );
      }
      /**
       * Filter the list of allowed post types for Auto Export (free-side filter for symmetry)
       *
       * @param array $allowed List of post type slugs.
       */
      $allowed = apply_filters( 'ss_auto_export_allowed_post_types', $allowed );
      /**
       * Filter the list of allowed post types for Auto Export (Pro).
       *
       * @param array $allowed List of post type slugs.
       */
      $allowed = apply_filters( 'ssp_auto_export_allowed_post_types', $allowed );

      if ( is_array( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
          return; // Post type not allowed for auto export
      }
  }

		// Don't schedule if we are on the post lists view.
		if ( isset( $_REQUEST['post_view'] ) && 'list' === $_REQUEST['post_view'] ) {
			return;
		}

		// Prevent schedule export if auto save.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't schedule the export if archive_creation_job is already running.
		if ( Plugin::instance()->get_archive_creation_job()->is_running() ) {
			return;
		}

		// Don't run if there is already a single export scheduled with the same Id.
		if ( ! wp_next_scheduled( 'simply_static_schedule_single_export' ) ) {
			wp_schedule_single_event( time() + $export_delay, 'simply_static_schedule_single_export', array( $post_id ) );
		}
	}

 public function schedule_future_single_export( $post_id ) {
        // Check if auto export is enabled, default from settings.
        $opts        = get_option( 'simply-static' );
        $default_on  = is_array( $opts ) && ! empty( $opts['ss_single_auto_export'] );
        $auto_export = apply_filters( 'ssp_single_auto_export', $default_on );

        if ( ! $auto_export ) {
            return;
        }

        // Respect allowed post types selection for future Auto Export
        $post_type = get_post_type( $post_id );
        if ( $post_type ) {
            $allowed = array();
            if ( is_array( $opts ) && isset( $opts['ss_single_auto_export_types'] ) && is_array( $opts['ss_single_auto_export_types'] ) ) {
                $allowed = $opts['ss_single_auto_export_types'];
            }
            if ( empty( $allowed ) ) {
                $types = get_post_types( array( 'public' => true ), 'names' );
                unset( $types['attachment'], $types['elementor_library'], $types['ssp-form'] );
                $allowed = array_values( $types );
            }
            $allowed = apply_filters( 'ss_auto_export_allowed_post_types', $allowed );
            $allowed = apply_filters( 'ssp_auto_export_allowed_post_types', $allowed );
            if ( is_array( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
                return;
            }
        }

		// Don't schedule if we are on the post lists view.
		if ( isset( $_REQUEST['post_view'] ) && 'list' === $_REQUEST['post_view'] ) {
			return;
		}

		// Prevent schedule export if auto save.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't schedule the export if archive_creation_job is already running.
		if ( Plugin::instance()->get_archive_creation_job()->is_running() ) {
			return;
		}

		if ( ! wp_next_scheduled( 'simply_static_schedule_single_export' ) ) {
			wp_schedule_single_event( time(), 'simply_static_schedule_single_export', array( $post_id ) );
		}
	}

	/**
	 * Prepare static page.
	 *
	 * @param object $child_page given child page.
	 * @param object $parent_page given parent page.
	 *
	 * @return void
	 */
	public function prepare_static_page( $child_page, $parent_page ) {
		if ( $child_page->post_id ) {
			return;
		}

		if ( ! $this->is_single_export_running() ) {
			return;
		}

		if ( ! Util::is_local_asset_url( $child_page->url ) ) {
			return;
		}

		if ( ! $this->should_export_assets() ) {
			return;
		}

		$child_page->post_id = $parent_page->post_id;
	}

	/**
	 * Is Single Export Running?
	 *
	 * @return bool
	 */
 protected function is_single_export_running() {
		$use_single = get_option( 'simply-static-use-single' );

		if ( empty( $use_single ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Should we also export assets?
	 *
	 * @return bool
	 */
 protected function should_export_assets() {
		if ( $this->export_assets === null ) {
			/**
			 * Control whether Single Export should include discovered assets (CSS/JS/Images).
			 *
			 * Developers can enable this globally or conditionally via this filter.
			 * Default: false.
			 *
			 * @param bool $include_assets
			 */
			$this->export_assets = (bool) apply_filters( 'ssp_single_export_assets', false );
		}

		return (bool) $this->export_assets;
	}

	/**
	 * Automatically run a static export after post is saved.
	 *
	 * @param int $post_id given post id.
	 *
	 * @return void
	 */
 public function run_scheduled_single_export( $post_id ) {
		$current_status = get_post_status( $post_id );

		if ( apply_filters( 'ssp_auto_export_status', 'publish' ) === $current_status ) {
			// Don't run if an export is already running.
			if ( Plugin::instance()->get_archive_creation_job()->is_running() ) {
				return;
			}

			$additional_urls = apply_filters( 'ssp_single_export_additional_urls', array_merge( $this->get_related_urls( $post_id ), $this->get_related_attachments( $post_id ) ) );

			// Update option for using a single post.
			update_option( 'simply-static-use-single', $post_id );

			// Add URls for static export.
			$post_url = get_permalink( $post_id );

			$this->add_url( $post_url, $post_id );
			$this->add_additional_urls( $additional_urls, $post_id );

			do_action( 'sch_before_run_single' );

			// Start static export.
			$ss = Plugin::instance();
			$ss->run_static_export();
		}
	}

	/**
	 * Enqueue scripts in WordPress.
	 *
	 * @return void
	 */
	public function add_admin_scripts( $hook ) {
		$allowed_hooks = [
			'post.php',
			'post-new.php'
		];

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

  // No jQuery dependency required; the script is vanilla JS.
  wp_enqueue_script( 'ssp-single-admin', SIMPLY_STATIC_PRO_URL . '/assets/ssp-single-admin.js', array(), SIMPLY_STATIC_PRO_VERSION, true );

		wp_localize_script(
			'ssp-single-admin',
			'ssp_single_ajax',
			array(
				'ajax_url'     => admin_url() . 'admin-ajax.php',
				'rest_url'     => rest_url(),
				'single_nonce' => wp_create_nonce( 'ssp-single' ),
				'redirect_url' => admin_url() . 'admin.php?page=simply-static',
				'rest_nonce'   => wp_create_nonce( 'wp_rest' )
			)
		);
	}

	/**
	 * Enqueue our admin script in the Block Editor context as well.
	 * This hook fires within the Gutenberg app shell and ensures the script
	 * is available even if admin_enqueue_scripts conditions miss.
	 */
	public function enqueue_block_editor_scripts() {
  // No jQuery dependency required; the script is vanilla JS.
  wp_enqueue_script( 'ssp-single-admin', SIMPLY_STATIC_PRO_URL . '/assets/ssp-single-admin.js', array(), SIMPLY_STATIC_PRO_VERSION, true );

		// Localize only if not already set to avoid overwriting.
		if ( ! wp_script_is( 'ssp-single-admin', 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			'ssp-single-admin',
			'ssp_single_ajax',
			array(
				'ajax_url'     => admin_url() . 'admin-ajax.php',
				'rest_url'     => rest_url(),
				'single_nonce' => wp_create_nonce( 'ssp-single' ),
				'redirect_url' => admin_url() . 'admin.php?page=simply-static',
				'rest_nonce'   => wp_create_nonce( 'wp_rest' )
			)
		);
	}

	/**
	 * Enqueue scripts inside Elementor editor context.
	 *
	 * @return void
	 */
 public function enqueue_elementor_scripts() {
        // Ensure we load after Elementor's editor scripts so hooks are available. No jQuery needed.
        $deps = array();
        if ( wp_script_is( 'elementor-editor', 'registered' ) || wp_script_is( 'elementor-editor', 'enqueued' ) ) {
            $deps[] = 'elementor-editor';
        }

        wp_enqueue_script( 'ssp-single-admin', SIMPLY_STATIC_PRO_URL . '/assets/ssp-single-admin.js', $deps, SIMPLY_STATIC_PRO_VERSION, true );

        wp_localize_script(
            'ssp-single-admin',
            'ssp_single_ajax',
            array(
                'ajax_url'     => admin_url() . 'admin-ajax.php',
                'rest_url'     => rest_url(),
                'single_nonce' => wp_create_nonce( 'ssp-single' ),
                'redirect_url' => admin_url() . 'admin.php?page=simply-static',
                'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
                // Expose minimal flags to allow UI decisions if needed later.
                'can_export'   => current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'single-export' ) ) ? 1 : 0
            )
        );
    }

	/**
	 * Generate single for static export.
	 *
	 * @return void
	 */
	public function apply_single() {
		// check nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ssp-single' ) ) {
			$response = array( 'message' => 'Security check failed.' );
			print wp_json_encode( $response );
			exit;
		}

		// Check for single id.
		if ( empty( $_POST['single_id'] ) ) {
			$response = array( 'success' => false );
			print wp_json_encode( $response );
			exit;
		}

		// Respect global setting to disable Single Exports on manual trigger
		$opts = get_option( 'simply-static' );
		if ( is_array( $opts ) && isset( $opts['ss_use_single_exports'] ) && false === (bool) $opts['ss_use_single_exports'] ) {
			$response = array( 'success' => false, 'message' => __( 'Single Exports are disabled in Simply Static settings.', 'simply-static-pro' ) );
			print wp_json_encode( $response );
			exit;
		}

		$single_id = esc_html( $_POST['single_id'] );

		// Prepare the export; assets inclusion is now controlled via the ssp_single_export_assets filter.
		$this->prepare_single_export( $single_id, false );

		// Start static export.
		$ss = Plugin::instance();
		$ss->run_static_export();

  // Webhook will be fired by centralized Webhook service on export completion (ss_after_cleanup)

		// Exit now.
		$response = array( 'success' => true );
		print wp_json_encode( $response );
		exit;
	}

	/**
	 * Prepare single exports by including additional URLs and files.
	 *
	 * @param int $single_id given post id.
	 *
	 * @return void
	 */
 public function prepare_single_export( $single_id, $assets ) {
		$additional_urls = apply_filters( 'ssp_single_export_additional_urls', array_merge( $this->get_related_urls( $single_id ), $this->get_related_attachments( $single_id ), SS_Multilingual::get_related_translations( $single_id ) ) );

		// Update option for using a single post.
		update_option( 'simply-static-use-single', $single_id );
		// Deprecated: assets setting is controlled via filter now; do not persist per-post option.

		// Add URls for static export.
		$post_url = get_permalink( $single_id );

		$this->add_url( $post_url, $single_id );
		$this->add_additional_urls( $additional_urls, $single_id );

		do_action( 'ssp_before_run_single' );
	}

	/**
	 * Get related URls to include in single export.
	 *
	 * @param int $single_id single post id.
	 *
	 * @return array
	 */
 public function get_related_urls( $single_id ) {
		$options      = get_option( 'simply-static' );
		$related_urls = array();

		// Skip related URLs?
		$skip_related_urls = apply_filters( 'ssp_skip_single_related_urls', false );

		if ( $skip_related_urls ) {
			return $related_urls;
		}


		// Include taxonomy archives based on selected taxonomies (UI-driven). Back-compat fallbacks to legacy booleans.
		$taxonomies = array();
		if ( isset( $options['ss_single_taxonomy_archives'] ) && is_array( $options['ss_single_taxonomy_archives'] ) ) {
			$taxonomies = array_filter( array_map( 'sanitize_key', $options['ss_single_taxonomy_archives'] ) );
		}
		// Backward compatibility: derive defaults from legacy toggles if new option not provided
		if ( empty( $taxonomies ) ) {
			$include_categories = isset( $options['ss_single_include_categories'] ) ? (bool) $options['ss_single_include_categories'] : true;
			$include_tags       = isset( $options['ss_single_include_tags'] ) ? (bool) $options['ss_single_include_tags'] : true;
			if ( $include_categories ) {
				$taxonomies[] = 'category';
			}
			if ( $include_tags ) {
				$taxonomies[] = 'post_tag';
			}
			$taxonomies = array_values( array_unique( $taxonomies ) );
		}

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				$terms = get_the_terms( $single_id, $tax );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$link = get_term_link( $term );
						if ( ! is_wp_error( $link ) && $link ) {
							$related_urls[] = $link;
						}
					}
				}
			}
		}

		// Include post type archive (and other archives controlled elsewhere) if enabled.
		$include_archives = isset( $options['ss_single_include_archives'] ) ? (bool) $options['ss_single_include_archives'] : true;
		if ( $include_archives ) {
			$post_type = get_post_type( $single_id );
			$archive_link = get_post_type_archive_link( $post_type );
			if ( $archive_link ) {
				$related_urls[] = $archive_link;
			}
		}

		// Get RSS Feed URLs if enabled.
		if ( isset( $options['add_feeds'] ) ) {
			$related_urls[] = get_bloginfo( 'rss2_url' );
			$related_urls[] = get_bloginfo( 'atom_url' );
			$related_urls[] = get_bloginfo( 'rss_url' );
			$related_urls[] = get_bloginfo( 'rdf_url' );
		}

  // Handle pagination. Options provide the default; filters ALWAYS have final say.
  // Default to option value (true when unset to preserve legacy behavior), then let the filter override.
  $pagination_default = isset( $options['ss_single_include_pagination'] ) ? (bool) $options['ss_single_include_pagination'] : true;
  /**
   * Control whether pagination URLs are included for Single Exports.
   *
   * IMPORTANT: Filters have the final say. The option value serves only as the default here
   * so existing code snippets using this filter continue to work regardless of UI settings.
   *
   * @param bool $include_pagination Default derived from option `ss_single_include_pagination`.
   */
  $use_pagination = apply_filters( 'ssp_single_export_pagination', $pagination_default );

		if ( $use_pagination ) {
			$pagination_urls = $this->get_pagination_urls( $single_id );

			if ( ! empty( $pagination_urls ) ) {
				$related_urls = array_merge( $related_urls, $pagination_urls );
			}
		}

		return $related_urls;
	}

	/**
	 * Get related URls to include in single export.
	 *
	 * @param int $single_id single post id.
	 *
	 * @return array
	 */
 public function get_related_attachments( $single_id ) {
        $urls = array();

        // Get the rendered HTML for the post/page.
        $response = Url_Fetcher::remote_get( get_permalink( $single_id ) );
        if ( is_wp_error( $response ) ) {
            return $urls;
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return $urls;
        }

        // Parse DOM safely.
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        $xpath = new DOMXPath( $dom );

        // Collect candidates from <img> and <picture><source> including lazy attributes
        $candidate_urls = array();

        // Helper closure to split a srcset into raw URLs
        $parse_srcset = function( $srcset_str ) {
            $list = array();
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $srcset_str ) ) ) as $entry ) {
                // entry can be "url 480w" or "url 2x"
                $parts = preg_split( '/\s+/', trim( $entry ) );
                if ( ! empty( $parts[0] ) ) {
                    $list[] = $parts[0];
                }
            }
            return $list;
        };

        // IMG elements
        $img_nodes = $xpath->query( '//img' );
        if ( $img_nodes ) {
            foreach ( $img_nodes as $img ) {
                // Standard and lazy attributes
                $attrs = array( 'src', 'data-src', 'data-lazy-src' );
                foreach ( $attrs as $attr ) {
                    if ( $img->hasAttribute( $attr ) ) {
                        $val = trim( $img->getAttribute( $attr ) );
                        if ( $val ) {
                            $candidate_urls[] = $val;
                        }
                    }
                }

                $srcset_attrs = array( 'srcset', 'data-srcset', 'data-lazy-srcset' );
                foreach ( $srcset_attrs as $attr ) {
                    if ( $img->hasAttribute( $attr ) ) {
                        $srcset = $img->getAttribute( $attr );
                        if ( $srcset ) {
                            $candidate_urls = array_merge( $candidate_urls, $parse_srcset( $srcset ) );
                        }
                    }
                }

                // From class wp-image-<id>
                $attachment_id = 0;
                if ( $img->hasAttribute( 'class' ) ) {
                    if ( preg_match( '/wp-image-(\d+)/', $img->getAttribute( 'class' ), $m ) ) {
                        $attachment_id = (int) $m[1];
                    }
                }

                // If we have an ID, expand all sizes for that attachment
                if ( $attachment_id > 0 ) {
                    $candidate_urls = array_merge( $candidate_urls, $this->get_all_image_size_urls( $attachment_id ) );
                }
            }
        }

        // <picture><source>
        $source_nodes = $xpath->query( '//picture/source' );
        if ( $source_nodes ) {
            foreach ( $source_nodes as $source ) {
                if ( $source->hasAttribute( 'srcset' ) ) {
                    $candidate_urls = array_merge( $candidate_urls, $parse_srcset( $source->getAttribute( 'srcset' ) ) );
                }
                if ( $source->hasAttribute( 'data-srcset' ) ) {
                    $candidate_urls = array_merge( $candidate_urls, $parse_srcset( $source->getAttribute( 'data-srcset' ) ) );
                }
            }
        }

        // If we still have only URLs, try mapping them back to attachment IDs to include all sizes as well
        $maybe_expand_all_sizes = (bool) apply_filters( 'ssp_single_include_all_image_sizes', true );
        if ( $maybe_expand_all_sizes && ! empty( $candidate_urls ) ) {
            $extra = array();
            foreach ( $candidate_urls as $u ) {
                if ( ! is_string( $u ) || $u === '' ) { continue; }
                if ( stripos( $u, 'data:' ) === 0 ) { continue; }
                if ( ! Util::is_local_url( $u ) ) { continue; }
                $aid = attachment_url_to_postid( $u );
                if ( $aid ) {
                    $extra = array_merge( $extra, $this->get_all_image_size_urls( $aid ) );
                }
            }
            if ( ! empty( $extra ) ) {
                $candidate_urls = array_merge( $candidate_urls, $extra );
            }
        }

        // Normalize, keep only local URLs, dedupe
        $normalized = array();
        foreach ( $candidate_urls as $u ) {
            if ( ! is_string( $u ) || $u === '' ) { continue; }
            // Remove any trailing punctuation that might come from parsing
            $u = trim( $u, " \t\r\n\0\x0B,;" );
            if ( stripos( $u, 'data:' ) === 0 ) { continue; }
            if ( ! Util::is_local_url( $u ) ) { continue; }
            $normalized[] = $u;
        }

        $normalized = array_values( array_unique( $normalized ) );
        /**
         * Filter the list of related attachment URLs for a Single Export.
         *
         * @param array $normalized URLs discovered and expanded to include image sizes.
         * @param int   $single_id  The post ID.
         */
        $normalized = apply_filters( 'ssp_single_related_attachment_urls', $normalized, $single_id );

        return $normalized;
    }

    /**
     * Build URLs for all intermediate sizes of an attachment, including the original.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $limit_sizes   Optional list of sizes to include; empty means include all from metadata.
     * @return array URLs
     */
    protected function get_all_image_size_urls( $attachment_id, $limit_sizes = array() ) {
        $urls = array();
        $attachment_id = (int) $attachment_id;
        if ( $attachment_id <= 0 ) {
            return $urls;
        }

        $meta = wp_get_attachment_metadata( $attachment_id );
        $full = wp_get_attachment_url( $attachment_id );
        if ( $full && Util::is_local_url( $full ) ) {
            $urls[] = $full;
        }

        if ( empty( $meta ) || empty( $meta['file'] ) ) {
            return array_values( array_unique( $urls ) );
        }

        // Determine base directory/URL from uploads
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['baseurl'] ) ) {
            $baseurl = trailingslashit( $uploads['baseurl'] );
            // $meta['file'] is like '2024/12/image.jpg' → base path dir is dirname
            $subdir = trailingslashit( ltrim( dirname( $meta['file'] ), '/\\' ) );

            // Limit sizes if requested; default to all in metadata
            $sizes_to_include = array();
            if ( ! empty( $limit_sizes ) ) {
                $sizes_to_include = array_fill_keys( array_map( 'sanitize_key', (array) $limit_sizes ), true );
            } else {
                // Optionally restrict by get_intermediate_image_sizes via filter
                $restrict_to_registered = (bool) apply_filters( 'ssp_single_restrict_to_registered_image_sizes', false );
                $registered = $restrict_to_registered ? array_map( 'sanitize_key', get_intermediate_image_sizes() ) : array();
            }

            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                foreach ( $meta['sizes'] as $size => $data ) {
                    if ( ! empty( $sizes_to_include ) && empty( $sizes_to_include[ $size ] ) ) {
                        continue;
                    }
                    if ( isset( $restrict_to_registered ) && $restrict_to_registered && ! in_array( $size, $registered, true ) ) {
                        continue;
                    }
                    if ( ! empty( $data['file'] ) ) {
                        $urls[] = $baseurl . $subdir . ltrim( $data['file'], '/\\' );
                    } else {
                        // Fallback via wp_get_attachment_image_src if file name missing
                        $src = wp_get_attachment_image_src( $attachment_id, $size );
                        if ( is_array( $src ) && ! empty( $src[0] ) && Util::is_local_url( $src[0] ) ) {
                            $urls[] = $src[0];
                        }
                    }
                }
            }
        }

        // Cap per-image list if requested
        $max = (int) apply_filters( 'ssp_single_max_sizes_per_image', 0 );
        if ( $max > 0 && count( $urls ) > $max ) {
            $urls = array_slice( $urls, 0, $max );
        }

        return array_values( array_unique( $urls ) );
    }

	/**
	 * Add single URL.
	 *
	 * @param string $url url to include.
	 * @param int $single_id current single id.
	 *
	 * @return void
	 */
 public function add_url( $url, $single_id ) {
		if ( Util::is_local_url( $url ) ) {
			Util::debug_log( 'Adding related URL to queue: ' . $url );

			$static_page = Page::query()->find_or_initialize_by( 'url', $url );
			$static_page->set_status_message( __( 'Related URL', 'simply-static-pro' ) );
			$static_page->post_id     = $single_id;
			$static_page->found_on_id = 0;
			$static_page->save();
		}
	}

	/**
	 * Add an additional file to the export.
	 *
	 * @param string $file_path given absolute file path.
	 * @param int $single_id given post id.
	 *
	 * @return void
	 */
 public function add_file( $file_path, $single_id ) {
		if ( file_exists( $file_path ) ) {
			if ( is_file( $file_path ) ) {
				$url = self::convert_path_to_url( $file_path );

				Util::debug_log( "File " . $file_path . ' exists; adding to queue as: ' . $url );

				$static_page = Page::query()->find_or_create_by( 'url', $url );
				$static_page->set_status_message( __( "Additional File", 'simply-static-pro' ) );
				$static_page->post_id     = $single_id;
				$static_page->found_on_id = 0;
				$static_page->save();
			} else {
				Util::debug_log( "Adding files from directory: " . $file_path );
				$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $file_path, \RecursiveDirectoryIterator::SKIP_DOTS ) );

				foreach ( $iterator as $file_name => $file_object ) {
					$url = self::convert_path_to_url( $file_name );

					Util::debug_log( "Adding file " . $file_name . ' to queue as: ' . $url );

					$static_page = Page::query()->find_or_initialize_by( 'url', $url );
					$static_page->set_status_message( __( "Additional Dir", 'simply-static-pro' ) );
					$static_page->post_id     = $single_id;
					$static_page->found_on_id = 0;
					$static_page->save();
				}
			}
		} else {
			Util::debug_log( "File doesn't exist: " . $file_path );
		}
	}

	/**
	 * Ensure the user-specified Additional URLs are in the DB.
	 *
	 * @param array $additional_urls array of additional urls.
	 * @param int $single_id Given single id.
	 *
	 * @return void
	 */
 public function add_additional_urls( $additional_urls, $single_id ) {
		foreach ( $additional_urls as $url ) {
			if ( Util::is_local_url( $url ) ) {
				Util::debug_log( 'Adding additional URL to queue: ' . $url );
				$static_page = Page::query()->find_or_initialize_by( 'url', $url );
				$static_page->set_status_message( __( 'Related URL', 'simply-static-pro' ) );
				$static_page->found_on_id = $single_id;
				$static_page->post_id     = $single_id;
				$static_page->save();
			}
		}
	}

	/**
	 * Update related URLs for a single post.
	 *
	 * @param int $single_id post id.
	 *
	 * @return void
	 */
 public function update_related_urls( $single_id ) {
		// set post to draft to exclude it from related URLs.
		wp_update_post( array( 'ID' => $single_id, 'post_status' => 'draft' ) );

		$related_urls = apply_filters( 'ssp_single_related_urls', array_merge( $this->get_related_urls( $single_id ), SS_Multilingual::get_related_translations( $single_id ) ) );

		// Update option for using a single post.
		update_option( 'simply-static-use-single', $single_id );

		// Add URls for static export.
		$this->add_additional_urls( $related_urls, $single_id );

		// Start static export.
		$ss = Plugin::instance();
		$ss->run_static_export();
	}

	/**
	 * Clear selected single after export.
	 *
	 * @param int    $blog_id Blog ID.
	 * @param string $action Action being performed (start, pause, resume, cancel).
	 *
	 * @return void
	 */
	public function clear_single( $blog_id = 0, $action = '' ) {
		// Don't clear single ID on pause or resume - we need it to continue the export
		if ( in_array( $action, array( 'pause', 'resume' ), true ) ) {
			return;
		}

		delete_option( 'simply-static-use-single' );
		delete_option( 'simply-static-single-export-assets' );
	}

	/**
	 * Filter static pages.
	 *
	 * @param array $results Results from database.
	 * @param string $archive_start_time timestamp.
	 *
	 * @return array
	 * @throws Exception Throws exception.
	 */
 public function filter_static_pages( $results, $archive_start_time ) {
		$use_single = get_option( 'simply-static-use-single' );

		if ( empty( $use_single ) ) {
			return $results;
		}

		$post_id = intval( $use_single );

		return Page::query()
		           ->where( 'last_checked_at < ? AND post_id = ?', $archive_start_time, $post_id )
		           ->find();
	}

	/**
	 * Filter remaining pages.
	 *
	 * @param array $results Results from database.
	 * @param string $archive_start_time timestamp.
	 *
	 * @return int|array
	 * @throws Exception Throws exception.
	 */
 public function filter_remaining_pages( $results, $archive_start_time ) {
		$use_single = get_option( 'simply-static-use-single' );

		if ( empty( $use_single ) ) {
			return $results;
		}

		$post_id = intval( $use_single );

		return Page::query()
		           ->where( 'last_checked_at < ? AND post_id = ?', $archive_start_time, $post_id )
		           ->count();
	}

	/**
	 * Filter total pages.
	 *
	 * @param array $results Results from the database.
	 *
	 * @return int|array
	 * @throws Exception Throws exception.
	 */
	public function filter_total_pages( $results ) {
		$use_single = get_option( 'simply-static-use-single' );

		if ( empty( $use_single ) ) {
			return $results;
		}

		$post_id = intval( $use_single );

		return Page::query()
		           ->where( 'post_id = ?', $post_id )
		           ->count();
	}

	/*
	 * Filter total pages by single ID for logging.
	 *
	 * @param array $query The current query for the log.
	 *
	 * @return array|null
	 */
	public function filter_total_pages_log( $query ) {
		$use_single = get_option( 'simply-static-use-single' );

		if ( empty( $use_single ) ) {
			return $query;
		}

		$post_id = intval( $use_single );

		return Page::query()
		           ->where( 'post_id = ?', $post_id )
		           ->order( 'http_status_code DESC' )
		           ->find();
	}

	/**
	 * Get pagination URLs.
	 *
	 * @param int $post_id given post id.
	 *
	 * @return array $all_pagination_urls the list of all pagination URLs.
	 */
	public function get_pagination_urls( $post_id ) {
		$all_pagination_urls = [];
		$posts_per_page      = get_option( 'posts_per_page' );
		$post_type_name      = get_post_type( $post_id );

		if ( 'post' === $post_type_name ) {
			// Pagination for all posts.
			$count_posts = wp_count_posts()->publish;
			$blog_page   = get_option( 'page_for_posts' );

			// Add a pagination url for each page.
			if ( $blog_page === 0 ) {
				$blog_page = get_option( 'page_on_front' );
			}

			// Calculate the number of pages
			$pages            = ceil( $count_posts / $posts_per_page );
			$pagination_links = [];

			for ( $i = 1; $i <= $pages; $i ++ ) {
				if ( $i === 1 ) {
					$pagination_links[] = trailingslashit( get_permalink( $blog_page ) );
				} else {
					$pagination_links[] = trailingslashit( get_permalink( $blog_page ) . 'page/' . $i );
				}
			}

			if ( ! empty( $pagination_links ) ) {
				$all_pagination_urls = array_merge( $all_pagination_urls, $pagination_links );
			}

			// Pagination for all categories.
			$categories                  = get_categories();
			$categories_pagination_links = [];

			foreach ( $categories as $category ) {
				// Calculate the number of pages
				$pages = (int) ceil( $category->count / max( 1, (int) $posts_per_page ) );

				$category_link = get_category_link( $category->term_id );
				if ( is_wp_error( $category_link ) || empty( $category_link ) ) {
					continue;
				}

				// Add base term link.
				$categories_pagination_links[] = trailingslashit( $category_link );

				// Add pagination URLs starting from page 2 to avoid /page/1.
				for ( $i = 2; $i <= $pages; $i ++ ) {
					$categories_pagination_links[] = trailingslashit( $category_link . 'page/' . $i );
				}
			}

			if ( ! empty( $categories_pagination_links ) ) {
				$all_pagination_urls = array_merge( $all_pagination_urls, $categories_pagination_links );
			}

			// Pagination for all tags.
			$tags                  = get_tags();
			$tags_pagination_links = [];

			foreach ( $tags as $tag ) {
				// Calculate the number of pages
				$pages = (int) ceil( $tag->count / max( 1, (int) $posts_per_page ) );

				$tag_link = get_tag_link( $tag->term_id );
				if ( is_wp_error( $tag_link ) || empty( $tag_link ) ) {
					continue;
				}

				// Add base term link.
				$tags_pagination_links[] = trailingslashit( $tag_link );

				// Add pagination URLs starting from page 2 to avoid /page/1.
				for ( $i = 2; $i <= $pages; $i ++ ) {
					$tags_pagination_links[] = trailingslashit( $tag_link . 'page/' . $i );
				}
			}

			if ( ! empty( $tags_pagination_links ) ) {
				$all_pagination_urls = array_merge( $all_pagination_urls, $tags_pagination_links );
			}

			// Pagination for author archives (posts only).
			$author_links = [];
			$authors      = get_users( array(
				'has_published_posts' => array( 'post' ),
				'fields'               => array( 'ID' ),
			) );
			if ( ! empty( $authors ) ) {
				foreach ( $authors as $author ) {
					$author_id   = is_object( $author ) ? $author->ID : (int) $author;
					$post_count  = (int) count_user_posts( $author_id, 'post', true );
					$pages       = (int) ceil( $post_count / max( 1, (int) $posts_per_page ) );
					$author_link = get_author_posts_url( $author_id );
					if ( empty( $author_link ) ) {
						continue;
					}
					// Base author archive URL.
					$author_links[] = trailingslashit( $author_link );
					// Pagination starting from page 2 to avoid /page/1.
					for ( $i = 2; $i <= $pages; $i ++ ) {
						$author_links[] = trailingslashit( $author_link . 'page/' . $i );
					}
				}
			}
			if ( ! empty( $author_links ) ) {
				$all_pagination_urls = array_merge( $all_pagination_urls, $author_links );
			}
		} else {
			// Pagination for custom post type.
			$count_posts  = wp_count_posts( $post_type_name )->publish;
			$archive_page = get_post_type_archive_link( $post_type_name );

			// Calculate the number of pages
			$pages            = ceil( $count_posts / $posts_per_page );
			$pagination_links = [];

			for ( $i = 1; $i <= $pages; $i ++ ) {
				if ( $i === 1 ) {
					$pagination_links[] = trailingslashit( $archive_page );
				} else {
					$pagination_links[] = trailingslashit( $archive_page . 'page/' . $i );
				}
			}

			if ( ! empty( $pagination_links ) ) {
				$all_pagination_urls = array_merge( $all_pagination_urls, $pagination_links );
			}

			// Get taxonomies of the custom post type.
			$taxonomies = get_object_taxonomies( $post_type_name, 'objects' );

			// Clean up taxonomies.
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->publicly_queryable ) {
					unset( $taxonomies[ $taxonomy->name ] );
				}

				// Remove builtin taxonomies.
				if ( $taxonomy->_builtin ) {
					unset( $taxonomies[ $taxonomy->name ] );
				}

				// Remove Builds.
				if ( $taxonomy->name === 'ssp-build' ) {
					unset( $taxonomies[ $taxonomy->name ] );
				}
			}

			// Handle custom taxonomies.
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {

					$terms = get_terms( array(
						'taxonomy'   => $taxonomy->name,
						'hide_empty' => false,
					) );

					if ( ! empty( $terms ) ) {
						$taxonomy_pagination_links = [];

						foreach ( $terms as $term ) {
							// Calculate the number of pages
							$pages = ceil( $term->count / $posts_per_page );

							$term_link = get_term_link( $term->term_id );

  					// Add base term link.
   					$taxonomy_pagination_links[] = trailingslashit( $term_link );

  					// Add pagination URLs starting from page 2 to avoid /page/1.
  					for ( $i = 2; $i <= $pages; $i ++ ) {
  						$taxonomy_pagination_links[] = trailingslashit( $term_link . 'page/' . $i );
  					}
  				}

  					if ( ! empty( $taxonomy_pagination_links ) ) {
  						$all_pagination_urls = array_merge( $all_pagination_urls, $taxonomy_pagination_links );
  					}

					}
				}
			}
		}

		// Dedupe and return.
		$all_pagination_urls = array_values( array_unique( $all_pagination_urls ) );
		return $all_pagination_urls;
	}

	/**
	 * Convert a directory path into a valid WordPress URL
	 *
	 * @param string $path The path to a directory or a file.
	 *
	 * @return string       The WordPress URL for the given path.
	 */
 public static function convert_path_to_url( $path ) {
		$url = $path;
		if ( stripos( $path, WP_PLUGIN_DIR ) === 0 ) {
			$url = str_replace( WP_PLUGIN_DIR, WP_PLUGIN_URL, $path );
		} elseif ( stripos( $path, WP_CONTENT_DIR ) === 0 ) {
			$url = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $path );
		} elseif ( stripos( $path, get_home_path() ) === 0 ) {
			$url = str_replace( untrailingslashit( get_home_path() ), Util::origin_url(), $path );
		}

		return $url;
	}
}
