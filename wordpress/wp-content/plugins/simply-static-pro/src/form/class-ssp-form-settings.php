<?php

namespace simply_static_pro;

use Simply_Static\Util;
use Simply_Static\Options;

/**
 * Class to handle admin for forms.
 */
class Form_Settings {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Form_Settings.
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
	 * Constructor for Form_Settings.
	 */
	public function __construct() {
		$options = get_option( 'simply-static' );

		// Run this settings class when either Forms or Comments are enabled,
		// since we also wire Turnstile verification for comments here.
		if ( empty( $options['use_forms'] ) && empty( $options['use_comments'] ) ) {
			return;
		}

		add_action( 'init', array( $this, 'add_forms_post_type' ) );
		add_action( 'save_post_ssp-form', array( $this, 'update_config' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 50 );
		add_filter( 'manage_ssp-form_posts_columns', array( $this, 'set_columns' ) );
		// Enforce our columns on the edit screen as well, overriding third-party columns (e.g., Yoast SEO).
		add_filter( 'manage_edit-ssp-form_columns', array( $this, 'set_columns' ), PHP_INT_MAX );
		add_action( 'manage_ssp-form_posts_custom_column', array( $this, 'set_columns_content' ), 10, 2 );
		add_filter( 'simply_static_class_name', array( $this, 'check_class_name' ), 30, 2 );
		add_filter( 'parent_file', array( $this, 'show_parent_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_form_settings_scripts' ) );
		add_filter( 'post_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_iframe_child_script' ), 5 );


		// Any form integrations using webhooks?
		$forms = get_posts( array(
			'post_type'  => 'ssp-form',
			'meta_query' => array(
				array(
					'key'   => 'form_type',
					'value' => 'webhook',
				),
			),
		) );

		if ( ! empty( $forms ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'add_webhook_scripts' ) );
		}

		// Initialize Captcha integration (Cloudflare Turnstile) in a dedicated class.
		// The class self-guards based on settings and wires its own hooks.
		$captcha_file = ( defined( 'SIMPLY_STATIC_PRO_PATH' )
			? SIMPLY_STATIC_PRO_PATH . '/src/form/class-ssp-form-captcha.php'
			: __DIR__ . '/class-ssp-form-captcha.php'
		);
		if ( file_exists( $captcha_file ) ) {
			require_once $captcha_file;
			if ( class_exists( __NAMESPACE__ . '\\Form_Captcha' ) ) {
				Form_Captcha::get_instance();
			}
		}

		// Initialize Google reCAPTCHA v3 integration in a dedicated class.
		// The class self-guards based on settings and wires its own hooks.
		$recaptcha_file = ( defined( 'SIMPLY_STATIC_PRO_PATH' )
			? SIMPLY_STATIC_PRO_PATH . '/src/form/class-ssp-form-recaptcha.php'
			: __DIR__ . '/class-ssp-form-recaptcha.php'
		);
		if ( file_exists( $recaptcha_file ) ) {
			require_once $recaptcha_file;
			if ( class_exists( __NAMESPACE__ . '\\Form_Recaptcha' ) ) {
				Form_Recaptcha::get_instance();
			}
		}
	}

	/**
	 * Enqueue child auto-resize script on ssp-form singular views.
	 * This posts the form page height to the parent so the iframe can resize.
	 */
	public function enqueue_iframe_child_script() {
		if ( function_exists( 'is_singular' ) && is_singular( 'ssp-form' ) ) {
			// Ensure jQuery is present for common form plugins that expect it
			// and provide a safe "$" alias (some themes/plugins enable noConflict).
			// Ensure jQuery is loaded (header group by default in WP)
			wp_enqueue_script( 'jquery' );

			// Also load jQuery Migrate to satisfy legacy plugin code paths (e.g., Fluent Forms inline initializers)
			if ( wp_script_is( 'jquery-migrate', 'registered' ) || wp_script_is( 'jquery-migrate', 'enqueued' ) ) {
				wp_enqueue_script( 'jquery-migrate' );
			}

			// Provide a minimal shim to guarantee $ is available when plugins run.
			// This runs right after jQuery and before other enqueued footer scripts.
			wp_add_inline_script(
				'jquery',
				'(function(w){var jq=w.jQuery||w.$; if(jq){w.jQuery=jq; w.$=w.$||jq; if(jq.noConflict){try{jq.noConflict(false);}catch(e){}} }})(window);'
			);

			wp_enqueue_script(
				'ssp-iframe-child',
				SIMPLY_STATIC_PRO_URL . '/assets/ssp-embed/ssp-iframe-child.js',
				( wp_script_is( 'jquery-migrate', 'enqueued' ) || wp_script_is( 'jquery-migrate', 'to_do' ) || wp_script_is( 'jquery-migrate', 'done' ) ) ? array(
					'jquery',
					'jquery-migrate'
				) : array( 'jquery' ),
				SIMPLY_STATIC_PRO_VERSION,
				true
			);
		}
	}


	public function add_form_settings_scripts() {
		$screen = get_current_screen();

		if ( 'ssp-form' !== $screen->id ) {
			return;
		}

  // Ensure React automatic JSX runtime is present for the Forms admin app.
        wp_enqueue_script( 'simplystatic-forms', SIMPLY_STATIC_PRO_URL . '/src/form/build/index.js', array(
            'wp-api',
            'wp-components',
            'wp-element',
            'wp-api-fetch',
            'wp-data',
            'wp-i18n',
            'wp-block-editor',
            'react',
            'react-dom',
            'react-jsx-runtime'
        ), SIMPLY_STATIC_PRO_VERSION, true );

		$post_id = get_the_id();

		$meta = array(
			'form_type'             => get_post_meta( $post_id, 'form_type', true ),
			'form_plugin'           => get_post_meta( $post_id, 'form_plugin', true ),
			'form_id'               => get_post_meta( $post_id, 'form_id', true ),
			'form_webhook'          => get_post_meta( $post_id, 'form_webhook', true ),
			'form_custom_headers'   => get_post_meta( $post_id, 'form_custom_headers', true ),
			'form_custom_css'       => get_post_meta( $post_id, 'form_custom_css', true ),
			'form_shortcode'        => get_post_meta( $post_id, 'form_shortcode', true ),
			// New: hidden input name support for "Other Plugin"
			'form_hidden_name'      => get_post_meta( $post_id, 'form_hidden_name', true ),
			// Re-introduce form_height so users can provide a manual minimum height (px) for embedded iframes
			'form_height'           => get_post_meta( $post_id, 'form_height', true ),
			// New: unit for the manual height setting (px, %, vh)
			'form_height_unit'      => ( get_post_meta( $post_id, 'form_height_unit', true ) ?: 'px' ),
			'form_success_message'  => get_post_meta( $post_id, 'form_success_message', true ),
			'form_error_message'    => get_post_meta( $post_id, 'form_error_message', true ),
			'form_use_redirect'     => get_post_meta( $post_id, 'form_use_redirect', true ),
			'form_disable_feedback' => get_post_meta( $post_id, 'form_disable_feedback', true ),
			'form_redirect_url'     => get_post_meta( $post_id, 'form_redirect_url', true )
		);

		// Compute default webhook endpoint and shared secret for auto-prefill.
		// Use canonical non-hyphen REST namespace for Forms service
		$entries_endpoint = rest_url( 'simplystatic/v1/entries' );
		$shared_secret    = function_exists( 'ssp_get_shared_secret' ) ? ssp_get_shared_secret() : ( defined( 'SSP_SHARED_SECRET' ) ? SSP_SHARED_SECRET : '' );
		$preferred_header = 'X-Simply-Static-Secret';
		$legacy_header    = 'X-Simply-Static-Studio-Secret';

		// Detect if the Studio helper plugin is active so we can gate Studio-only UI
		$is_studio = false;
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// Load the function if not available yet
			if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( function_exists( 'is_plugin_active' ) ) {
			$is_studio = is_plugin_active( 'simply-static-studio-helper/simply-static-studio-helper.php' );
		}

		$args = apply_filters( 'ssp_forms_args', array(
			'screen'          => 'simplystatic-forms',
			'meta'            => $meta,
			'post_id'         => $post_id,
			// Expose post title so the UI can edit it
			'post_title'      => (string) html_entity_decode( get_the_title( $post_id ) ),
			// Expose post status so the UI can decide whether to show Preview controls
			'post_status'     => get_post_status( $post_id ),
			// Expose the ssp-form permalink so the admin UI can open a live preview
			'permalink'       => get_permalink( $post_id ),
			// Auto-prefill helpers for webhook connections
			'endpoint'        => esc_url_raw( $entries_endpoint ),
			'secret'          => (string) $shared_secret,
			'preferredHeader' => $preferred_header,
			'legacyHeader'    => $legacy_header,
			// Flag for gating Studio-only settings in the React UI
			'isStudio'        => (bool) $is_studio,
		), $post_id );

  wp_localize_script( 'simplystatic-forms', 'forms', $args );

  // Make the Forms admin app translatable in JS via WP i18n JSON files
  if ( function_exists( 'wp_set_script_translations' ) ) {
      $languages_path = defined( 'SIMPLY_STATIC_PRO_PATH' )
          ? trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'languages'
          : plugin_dir_path( __FILE__ ) . '../../languages';
      wp_set_script_translations( 'simplystatic-forms', 'simply-static-pro', $languages_path );
  }

		// No longer hide the Form Height control; it is now available again as a manual override.

		// Enqueue WP Code Editor for Custom CSS linting (used in the React admin UI Preview sidebar)
		if ( function_exists( 'wp_enqueue_code_editor' ) ) {
			$editor_settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
			if ( $editor_settings ) {
				// Make settings available to the React app for CodeEditor component
				wp_add_inline_script( 'simplystatic-forms', 'window._SSP_CODE_EDITOR_SETTINGS = ' . wp_json_encode( $editor_settings ) . ';', 'before' );
				wp_enqueue_script( 'code-editor' );
				wp_enqueue_style( 'code-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}
		}

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'simplystatic-forms', 'simply-static-pro', SIMPLY_STATIC_PRO_PATH . '/languages' );
		}

		wp_enqueue_style( 'simplystatic-forms-style', SIMPLY_STATIC_PRO_URL . '/src/form/build/style-index.css', array( 'wp-components' ) );
	}

	/**
	 * Enqueue scripts for webhooks.
	 *
	 * @return void
	 */
	public function add_webhook_scripts() {
		// Do not load on embedded ssp-form single pages
		if ( function_exists( 'is_singular' ) && is_singular( 'ssp-form' ) ) {
			$post_id   = get_queried_object_id();
			$form_type = $post_id ? get_post_meta( $post_id, 'form_type', true ) : '';
			if ( $form_type === 'embedded' ) {
				return;
			}
		}

		// Core SSP public scripts for webhook + validation
		wp_enqueue_script( 'ssp-form-webhook-public', SIMPLY_STATIC_PRO_URL . '/assets/ssp-form-webhook-public.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
		wp_enqueue_script( 'ssp-form-validation', SIMPLY_STATIC_PRO_URL . '/assets/ssp-form-validation.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
	}

	/**
	 * Highlight parent menu when editing ssp form post.
	 *
	 * @param string $parent given parent.
	 *
	 * @return string
	 */
	public function show_parent_menu( $parent = '' ) {
		global $pagenow, $typenow;

		// If we're editing the form settings, we must be within the SS menu, so highlight that.
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && ( $typenow === 'ssp-form' ) ) {
			$parent = 'simply-static-generate';
		}

		return $parent;
	}

	/**
	 * Add submenu page for builds taxonomy.
	 *
	 * @return void
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'simply-static-generate',
			__( 'Form Connections', 'simply-static-pro' ),
			__( 'Form Connections', 'simply-static-pro' ),
			apply_filters( 'ss_user_capability', 'publish_pages', 'forms' ),
			'edit.php?post_type=ssp-form',
			false
		);
	}

	/**
	 * Create forms custom post type.
	 *
	 * @see register_post_type() for registering custom post types.
	 */
	public function add_forms_post_type() {
		$labels = array(
			'name'                  => _x( 'Form Connections', 'Post type general name', 'simply-static-pro' ),
			'singular_name'         => _x( 'Form Connection', 'Post type singular name', 'simply-static-pro' ),
			'menu_name'             => _x( 'Form Connections', 'Admin Menu text', 'simply-static-pro' ),
			'name_admin_bar'        => _x( 'Form Connection', 'Add New on Toolbar', 'simply-static-pro' ),
			'add_new'               => __( 'Add New', 'simply-static-pro' ),
			'add_new_item'          => __( 'Add New Form Connection', 'simply-static-pro' ),
			'new_item'              => __( 'New Form Connection', 'simply-static-pro' ),
			'edit_item'             => __( 'Edit Form Connection', 'simply-static-pro' ),
			'view_item'             => __( 'View Form Connection', 'simply-static-pro' ),
			'all_items'             => __( 'All Forms Connections', 'simply-static-pro' ),
			'search_items'          => __( 'Search Form Connections', 'simply-static-pro' ),
			'parent_item_colon'     => __( 'Parent Form Connections:', 'simply-static-pro' ),
			'not_found'             => __( 'No form connections found.', 'simply-static-pro' ),
			'not_found_in_trash'    => __( 'No form connections found in Trash.', 'simply-static-pro' ),
			'featured_image'        => _x( 'Form Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'archives'              => _x( 'Form Connection archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'simply-static-pro' ),
			'insert_into_item'      => _x( 'Insert into form connection', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'simply-static-pro' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this form connection', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'simply-static-pro' ),
			'filter_items_list'     => _x( 'Filter form connections list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'simply-static-pro' ),
			'items_list_navigation' => _x( 'Form Connections list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'simply-static-pro' ),
			'items_list'            => _x( 'Form Connections list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'simply-static-pro' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			// Expose CPT in REST so UI can publish/update via wp/v2 endpoints.
			'show_in_rest'       => true,
			'rest_base'          => 'ssp-form',
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array('title'),
		);

		register_post_type( 'ssp-form', $args );

		// We need to flush permalinks.
		flush_rewrite_rules();
	}

	/**
	 * Set column headers.
	 *
	 * @param array $columns array of columns.
	 *
	 * @return array
	 */
	public function set_columns( array $columns ): array {
		return [
			'title'       => __( 'Title', 'simply-static-pro' ),
			'form_type'   => esc_html__( 'Form Type', 'simply-static-pro' ),
			'form_plugin' => esc_html__( 'Form Plugin', 'simply-static-pro' ),
			'form_id'     => esc_html__( 'Form ID', 'simply-static-pro' ),
		];
	}

	/**
	 * Add content to registered columns.
	 *
	 * @param string $column name of the column.
	 * @param int $post_id current id.
	 *
	 * @return void
	 */
	public function set_columns_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'form_type':
				$form_type    = get_post_field( 'form_type', $post_id );
				$form_webhook = get_post_field( 'form_webhook', $post_id );

				if ( 'embedded' === $form_type ) {
					esc_html_e( 'Embedded', 'simply-static-pro' );
				} elseif ( 'webhook' === $form_type ) {
					echo 'Webhook ' . ( ! empty( $form_webhook ) ? ' (' . esc_url( $form_webhook ) . ')' : '' );
				} else {
					esc_html_e( 'No form type selected.', 'simply-static-pro' );
				}
				break;
			case 'form_plugin':
				$form_plugins = array(
					'cf7'             => 'Contact Form 7',
					'wp_forms'        => 'WP Forms',
					'gravity_forms'   => 'Gravity Forms',
					'elementor_forms' => 'Elementor Forms',
					'bricks_forms'    => 'Bricks Forms',
					'ws_form'         => 'WS Form',
					'fluent_forms'    => 'Fluent Forms',
					'kadence_forms'   => 'Kadence Forms',
					'forminator'      => 'Forminator',
					'other'           => 'Other Plugin',
				);

				$form_plugin_slug = get_post_field( 'form_plugin', $post_id );

				if ( ! empty( $form_plugin_slug ) && isset( $form_plugins[ $form_plugin_slug ] ) ) {
					echo esc_html( $form_plugins[ $form_plugin_slug ] );
				} else {
					esc_html_e( 'No form plugin selected', 'simply-static-pro' );
				}

				break;
			case 'form_id':
				echo esc_html( get_post_field( 'form_id', $post_id ) );
				break;
		}
	}

	/**
	 * Filter row actions for forms.
	 *
	 * @param array $actions list of actions.
	 * @param \WP_Post $post current post.
	 *
	 * @return array
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( 'ssp-form' === $post->post_type ) {
			unset( $actions['view'] );
		}

		return $actions;
	}

	/**
	 * Modify task class name in Simply Static.
	 *
	 * @param string $class_name current class name.
	 * @param string $task_name current task name.
	 *
	 * @return string
	 */
	public function check_class_name( $class_name, $task_name ) {
		if ( 'form_config' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		return $class_name;
	}

	/**
	 * Update form config if ssp-form post is saved.
	 *
	 *
	 * @return void
	 */
	public function update_config() {
		$this->create_config_file();
	}

	/**
	 * Create JSON file for forms config.
	 *
	 * @return string;
	 */
	public function create_config_file() {
		$filesystem = Helper::get_file_system();

		if ( ! $filesystem ) {
			return false;
		}

		// Get config file path.
		$upload_dir  = wp_upload_dir();
		$config_dir  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
		$config_file = $config_dir . 'forms.json';

		// Delete old index.
		if ( file_exists( $config_file ) ) {
			wp_delete_file( $config_file );
		}

		// Get static form configurations.
		$args      = array( 'numberposts' => - 1, 'post_type' => 'ssp-form', 'fields' => 'ids' );
		$ssp_forms = get_posts( $args );
		$forms     = array();

		// Replace WP Url with static URL.
		$regex = '/(https?:)?\/\/' . addcslashes( Util::origin_host(), '/' ) . '/i';

		switch ( Options::instance()->get( 'destination_url_type' ) ) {
			case 'absolute':
				$convert_to = Options::instance()->get_destination_url();
				break;
			case 'relative':
				// Adding \/? before end of regex pattern to convert url.com/ & url.com to relative path, ex. /path/.
				$regex      = '/(https?:)?\/\/' . addcslashes( Util::origin_host(), '/' ) . '\/?/i';
				$convert_to = trailingslashit( Options::instance()->get( 'relative_path' ) );
				break;
			default:
				// Offline mode.
				// Adding \/? before end of regex pattern to convert url.com/ & url.com to relative path, ex. /path/.
				$regex      = '/(https?:)?\/\/' . addcslashes( Util::origin_host(), '/' ) . '\/?/i';
				$convert_to = '/';
		}

		if ( ! empty( $ssp_forms ) ) {
			foreach ( $ssp_forms as $form_id ) {
				$form                        = new \stdClass();
				$form->form_type             = get_post_meta( $form_id, 'form_type', true );
				$form->form_plugin           = get_post_meta( $form_id, 'form_plugin', true );
				$form->form_id               = get_post_meta( $form_id, 'form_id', true );
				$form->form_webhook          = get_post_meta( $form_id, 'form_webhook', true );
				$form->form_custom_headers   = get_post_meta( $form_id, 'form_custom_headers', true );
				$form->form_success_message  = get_post_meta( $form_id, 'form_success_message', true );
				$form->form_error_message    = get_post_meta( $form_id, 'form_error_message', true );
				$form->form_use_redirect     = get_post_meta( $form_id, 'form_use_redirect', true );
				$form->form_disable_feedback = get_post_meta( $form_id, 'form_disable_feedback', true );
				$form->form_redirect_url     = preg_replace( $regex, $convert_to, html_entity_decode( get_post_meta( $form_id, 'form_redirect_url', true ) ) );
				// Include optional hidden input name if configured
				$form->form_hidden_name = get_post_meta( $form_id, 'form_hidden_name', true );
				// New: include the WP REST API base of the origin site so static pages can route
				// Turnstile-protected submissions through the proxy without relying on rewritten URLs.
				if ( function_exists( 'get_rest_url' ) ) {
					$rest_base = rtrim( (string) get_rest_url( null, '/' ), '/' ) . '/';
				} else {
					$rest_base = '';
				}
				$form->rest_base = $rest_base;

				$forms[] = $form;
			}
		}

		// Now create the json file.
		$json = wp_json_encode( $forms );

		// Check if directory exists.
		if ( ! is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir );
		}

		$filesystem->put_contents( $config_file, $json );

		return $config_file;
	}
}
