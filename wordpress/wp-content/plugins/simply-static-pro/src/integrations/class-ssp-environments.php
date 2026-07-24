<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use Simply_Static\Options;
use Simply_Static\Plugin;
use Simply_Static\Util;

class Environments extends Integration {

	protected $id = 'environments';

	protected $active_by_default = false;

	public function __construct() {
		$this->name = __( 'Environments (Core)', 'simply-static-pro' );
		$this->description = __( 'Define multiple environments of Simply Static so you can easily change between saved configurations.', 'simply-static-pro' );
		$this->requires_ui_reload = true;
	}

	public function run() {
		add_filter( 'ss_settings_args', [ $this, 'add_settings_args'] );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( "update_option_simply-static", [ $this, 'update_option' ], 99 );
	}

	/**
	 * Updating the current version with the current options.
	 *
	 * @param array $old_value Old values.
	 *
	 * @return void
	 */
	public function update_option( $old_value ) {
		$this->update_current_version();
	}

	/**
	 * Setup Rest API endpoints.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		register_rest_route( 'simplystatic/v1', '/environment', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_environments' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'simplystatic/v1', '/environment', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_create_environment' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'simplystatic/v1', '/environment', array(
			'methods'             => 'PUT',
			'callback'            => [ $this, 'rest_change_environment' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'simplystatic/v1', '/environment', array(
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'rest_delete_environment' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return string|\WP_REST_Response|\WP_Error
	 */
	public function rest_get_environments( $request ) {

		return rest_ensure_response([
			'environments'        => $this->get_all_versions(),
			'current_environment' => $this->get_current_version(),
		]);
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return string|\WP_REST_Response|\WP_Error
	 */
	public function rest_delete_environment( $request ) {
		$version = wp_unslash($request->get_param( 'version' ) );

		if ( empty( $version ) ) {
			return new \WP_Error( 500, __( 'Version is required', 'simply-static-pro' ) );
		}

		$change = $this->delete_version( $version );

		if ( is_wp_error( $change ) ) {
			return $change;
		}

		return rest_ensure_response([
			'environments'        => $this->get_all_versions(),
			'current_environment' => $this->get_current_version(),
		]);
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return string|\WP_REST_Response|\WP_Error
	 */
	public function rest_change_environment( $request ) {
		$version = wp_unslash($request->get_param( 'version' ) );

		if ( empty( $version ) ) {
			return new \WP_Error( 500, __( 'Version is required', 'simply-static-pro' ) );
		}

		$change = $this->set_version( $version );

		if ( is_wp_error( $change ) ) {
			return $change;
		}

		return rest_ensure_response([
			'current_environment' => $this->get_current_version(),
		]);
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return string|\WP_REST_Response|\WP_Error
	 */
	public function rest_create_environment( $request ) {
		$title = sanitize_text_field( wp_unslash($request->get_param( 'title' ) ) );

		if ( empty( $title ) ) {
			return new \WP_Error( 500, __( 'Name is required', 'simply-static-pro' ) );
		}

		$version = $this->create_new_version( $title );

		if ( is_wp_error( $version ) ) {
			return $version;
		}

		if ( null === $this->get_current_version() ) {
			$this->set_current_version( $version );
		}

		return rest_ensure_response([
			'environments'        => $this->get_all_versions(),
			'current_environment' => $this->get_current_version(),
		]);
	}

	/**
	 * Filter the settings arguments with environment information.
	 *
	 * @param array $args Settings.
	 *
	 * @return mixed
	 */
	public function add_settings_args( $args ) {

		$args['environments']        = $this->get_all_versions();
		$args['current_environment'] = $this->get_current_version();

		return $args;
	}

	/**
	 * Get the Current version that is used.
	 *
	 * @return false|mixed|null
	 */
	public function get_current_version() {
		return get_option( 'simply_static_environment', null );
	}

	/**
	 * Set the Current version.
	 */
	public function set_current_version( $version ) {
		update_option( 'simply_static_environment', $version );
	}

	/**
	 * Get the current Version data.
	 *
	 * @return false|mixed|null
	 */
	public function get_current_version_data() {
		$current_version = $this->get_current_version();

		return $this->get_options_from_version( $current_version );
	}

	/**
	 * Get all the versions that are used.
	 *
	 * @return false|mixed|null
	 */
	public function get_all_versions() {
		return get_option( 'simply_static_environments', [] );
	}

	/**
	 * Get the options from version.
	 *
	 * @param $version
	 *
	 * @return false|mixed|null
	 */
	public function get_options_from_version( $version ) {
		return apply_filters( 'ss_get_options', get_option( Plugin::SLUG . '-' . $version, []  ) );
	}

	/**
	 * Update options for a version.
	 *
	 * @param string $version Version.
	 * @param array  $data Data to save.
	 *
	 * @return void
	 */
	public function update_options_from_version( $version, $data ) {
		update_option( Plugin::SLUG . '-' . $version, $data );
	}

	/**
	 * Sync persistent data such as integrations that need to be the same on all.
	 *
	 * @return void
	 */
	public function sync_persistent_data() {
		$persistent_settings = [
			'integrations'
		];

		$versions = $this->get_all_versions();
		$options  = Options::instance();
		$data     = $options->get_as_array();
		$persistent_data = [];

		foreach ( $persistent_settings as $setting ) {
			$persistent_data[ $setting ] = $data[ $setting ] ?? null;
		}

		foreach ( $versions as $version => $version_title ) {
			$version_data = $this->get_options_from_version( $version );

			foreach ( $persistent_data as $persistent_data_key => $persistent_data_value ) {
				if ( null === $persistent_data_value ) {
					continue;
				}

				$version_data[ $persistent_data_key ] = $persistent_data_value;
			}

			$this->update_options_from_version( $version, $version_data );
		}
	}

	/**
	 * Set a new version as new version with data.
	 *
	 * @param string $version Version.
	 *
	 * @return boolean|\WP_Error
	 */
	public function set_version( $version ) {
		$options  = Options::instance();
		$versions = $this->get_all_versions();

		if ( ! isset( $versions[ $version ] ) ) {
			return new \WP_Error( 404, __( 'Version does not exist', 'simply-static-pro' ) );
		}

		$version_data = $this->get_options_from_version( $version );

		$options->set_options( $version_data );

		remove_action( "update_option_simply-static", [ $this, 'update_option' ], 99 );

		$options->save();

		add_action( "update_option_simply-static", [ $this, 'update_option' ], 99 );

		$this->set_current_version( $version );

		return true;
	}

	/**
	 * Update the current version.
	 *
	 * @return false|void
	 */
	public function update_current_version() {
		// Not using Options::instance so we're sure we're not using cached data.
		$data     = get_option( Plugin::SLUG, [] );
		$versions = $this->get_all_versions();
		$version  = $this->get_current_version();

		if ( ! isset( $versions[ $version ] ) ) {
			return false;
		}

		$this->update_options_from_version( $version, $data );
		$this->sync_persistent_data();
	}

	/**
	 * Create a new version.
	 *
	 * If the sanitized title already exists, returns an error.
	 *
	 * @param $title
	 *
	 * @return string|\WP_Error
	 */
	public function create_new_version( $title ) {
		$options  = Options::instance();
		$data     = $options->get_as_array();
		$version  = sanitize_title( $title );
		$versions = $this->get_all_versions();

		if ( isset( $versions[ $version ] ) ) {
			return new \WP_Error( 'exists', __( 'Environment already exists with this name.', 'simply-static-pro' ) );
		}

		$versions[ $version ] = $title;
		update_option( 'simply_static_environments', $versions );
		$this->update_options_from_version( $version, $data );

		return $version;
	}

	public function delete_version( $version ) {
		$current_version = $this->get_current_version();
		$versions        = $this->get_all_versions();

		if ( ! isset( $versions[ $version ] ) ) {
			return new \WP_Error( 'exists', __( 'Version does not exist.', 'simply-static-pro' ) );
		}

		unset( $versions[ $version ] );

		delete_option( Plugin::SLUG . '-' . $version );

		if ( ! empty( $versions ) ) {
			update_option( 'simply_static_environments', $versions );
			$next_version = current( array_keys( $versions ) );
			if ( $current_version === $version ) {
				$this->set_version( $next_version );
			}
		} else {
			delete_option( 'simply_static_environment' );
			delete_option( 'simply_static_environments');
		}

		return true;
	}
}