<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use Simply_Static\Plugin;
use Simply_Static\Util;
use Simply_Static\Options;

class Multisite_Integration extends Integration {

	protected $id = 'multisite';

	protected $always_active = true;

	/**
	 * Transient key for multisite lock reset notice (Network Admin fallback UI).
	 *
	 * @var string
	 */
	private $ms_lock_notice_key = 'ss_ms_lock_notice';

 public function __construct() {
        $this->name = __( 'Multisite', 'simply-static-pro' );
        $this->description = __( 'Allows queued multisite exports and management through network dashboard.', 'simply-static-pro' );
    }

	/**
	 * Run.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'ss_after_cleanup', [ $this, 'remove_from_queue' ], 10, 1 );
		add_action( 'ss_before_static_export', [ $this, 'add_to_queue' ], 10, 1 );
		add_action( 'ss_archive_creation_job_before_start_queue', [ $this, 'add_site_to_queue' ], 1 );
		add_filter( 'simplystatic.archive_creation_job.task_list', [ $this, 'filter_task_list' ], PHP_INT_MAX );
		add_filter( 'ss_rest_multisite_get_sites',  [ $this, 'filter_sites' ], 10, 1 );

		// Pro-only: REST endpoints and UI for multisite lock management.
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_action( 'admin_init', [ $this, 'maybe_handle_reset_multisite_lock' ] );
		add_action( 'network_admin_notices', [ $this, 'render_network_lock_notice' ] );
	}

	/**
	 * A Check if this integration can run.
	 * Example: Check if a plugin is activated in DB or a class exists.
	 *
	 * @return boolean
	 */
	public function can_run() {
		// Making sure, even if always active, to not run on single sites.
		if ( ! is_multisite() ) {
			return false;
		}

		return parent::can_run();
	}

	public function filter_sites( $sites ) {
		$queued = self::get_export_queue();

		if ( empty( $queued ) ) {
			return $sites;
		}

		foreach ( $sites as $index => $site ) {
			$site_id = absint( $site['id'] );

			if ( ! isset( $queued[ $site_id ] ) ) {
				continue;
			}

			if ( ! $site['running'] ) {
				continue;
			}

			if ( $queued[ $site_id ]['status'] === 'running' ) {
				continue;
			}

   $sites[ $index ]['status'] = __( 'Queued', 'simply-static-pro' );
		}

		return $sites;
	}

	public function filter_task_list( $task_list ) {
		$multisite_task = [ 'multisite_queue' ];

		return array_merge( $multisite_task, $task_list );
	}

	public function add_site_to_queue( $blog_id ) {
		if ( ! self::is_queue_enabled() ) {
			return;
		}

		self::queue_export( $blog_id );
	}

	public function add_to_queue() {
		if ( ! self::is_queue_enabled() ) {
			return;
		}

		self::queue_export( get_current_blog_id() );
	}

	public function remove_from_queue() {
		self::dequeue_export( get_current_blog_id() );
	}

	/**
	 * Check if the queue is enabled.
	 *
	 * @return bool
	 */
	public static function is_queue_enabled() {
		return is_multisite() && apply_filters( 'ss_multisite_queue_enabled', true );
	}

	/**
	 * Get the export queue to find out which sites are queued for export.
	 * Format:
	 *  [
	 *      blog_id => [
	 *          'site_id' => blog_id,
	 *          'time' => time(),
	 *          'status' => 'queued' || 'running'
	 *      ]
	 *  ]
	 * @return false|mixed
	 */
	public static function get_export_queue() {
		return get_site_option( Plugin::SLUG . 'multisite_export_queue', [] );
	}

	/**
	 * Save the export queue.
	 *
	 * @param array $queue Queue of exports.
	 *
	 * @return void
	 */
	public static function update_export_queue( $queue ) {
		update_site_option( Plugin::SLUG . 'multisite_export_queue', $queue );
	}

	/**
	 * Set a site to the export queue as running.
	 * If the site is not in the queue at all, it is added and updated.
	 *
	 * @param integer $blog_id Site ID.
	 *
	 * @return void
	 */
	public static function set_queued_export_as_running( $blog_id ) {
		$queue = self::get_export_queue();

		$blog_id = absint( $blog_id );

		if ( ! isset( $queue[ $blog_id ] ) ) {
			$export_data = [
				'site_id' => $blog_id,
				'time' => time(),
				'status' => 'running'
			];
		} else {
			$export_data = $queue[ $blog_id ];
		}

		$export_data['status'] = 'running';
		$queue[ $blog_id ] = $export_data;

		self::update_export_queue( $queue );
	}

	/**
	 * Queue a site for export.
	 * If the site is already in the queue, it is overwritten..
	 * If the site is not in the queue at all, it is added and updated.
	 *
	 * @param integer $blog_id Site ID.
	 *
	 * @return void
	 */
	public static function queue_export( $blog_id ) {
		$queue = self::get_export_queue();

		$export_data = [
			'site_id' => $blog_id,
			'time' => time(),
			'status' => 'queued'
		];

		$queue[ absint( $blog_id ) ] = $export_data;

		self::update_export_queue( $queue );
	}

	/**
	 * Remove a site from the export queue.
	 *
	 * @param integer $blog_id Site ID.
	 *
	 * @return void
	 */
	public static function dequeue_export( $blog_id ) {
		$queue = self::get_export_queue();
		unset( $queue[ absint( $blog_id ) ] );
		self::update_export_queue( $queue );
	}

	/**
	 * Check if the site can run the export.
	 * If the next export is the current site, it can run.
	 * If the next export is 0, it can run (no site queued).
	 *
	 * @param integer $blog_id Site ID.
	 *
	 * @return bool
	 */
	public static function can_run_export( $blog_id ) {
		return in_array( self::get_next_export(), [ 0, absint( $blog_id ) ], true );
	}

	/**
	 * Return if the queue is empty.
	 *
	 * @return bool
	 */
	public static function is_queue_empty() {
		$queue = self::get_export_queue();
		return empty( $queue );
	}

	/**
	 * Get the next site to export.
	 *
	 * @return int Site ID or 0 if the queue is empty.
	 */
	public static function get_next_export() {
		if ( self::is_queue_empty() ) {
			return 0;
		}
		$queue = self::get_export_queue();

		uasort( $queue, function ( $a, $b ) {
			return $a['time'] - $b['time'];
		} );

		$statuses = wp_list_pluck( $queue, 'status' );
		$running_site_id = array_search( 'running', $statuses );

		if ( $running_site_id ) {
			return absint( $running_site_id );
		}

		$next_site = current( $queue );

		return absint( $next_site['site_id'] );
	}

	public function rest_api_init() {
		if ( ! is_multisite() ) {
			return;
		}
		register_rest_route( 'simplystatic/v1', '/multisite/lock', array(
			'methods'  => 'GET',
			'callback' => [ $this, 'get_multisite_lock_status' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_network_options', 'multisite_lock_status' ) );
			},
		) );
		register_rest_route( 'simplystatic/v1', '/multisite/reset-lock', array(
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_reset_multisite_lock' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_network_options', 'multisite_lock_reset' ) );
			},
		) );
	}

	public function get_multisite_lock_status() {
		if ( ! is_multisite() ) {
			return \wp_send_json_success( [ 'is_multisite' => false ] );
		}
		$info = $this->get_current_multisite_lock_info();
		$can_reset     = current_user_can( apply_filters( 'ss_user_capability', 'manage_network_options', 'multisite_lock_reset' ) );
		$pro_installed = defined( 'SIMPLY_STATIC_PRO_VERSION' );
		$data = [
			'is_multisite'      => true,
			'lock_exists'       => (bool) $info['running_site_id'],
			'running_site_id'   => $info['running_site_id'],
			'running_site_url'  => $info['running_site_url'],
			'running_site_name' => $info['running_site_name'],
			'is_running_active' => $info['is_running_active'],
			'pro_installed'     => $pro_installed,
			'can_reset'         => (bool) $can_reset,
		];
		return \wp_send_json_success( $data );
	}

	public function rest_reset_multisite_lock() {
  if ( ! is_multisite() ) {
            return \wp_send_json_error( [ 'message' => __( 'Not a multisite installation.', 'simply-static-pro' ) ], 400 );
        }
        if ( ! current_user_can( apply_filters( 'ss_user_capability', 'manage_network_options', 'multisite_lock_reset' ) ) ) {
            return \wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'simply-static-pro' ) ], 403 );
        }
		$prev = $this->get_current_multisite_lock_raw();
		/**
		 * Fires before the multisite lock is reset.
		 *
		 * @param mixed $prev Previous stored value of the lock option(s).
		 */
		do_action( 'ss_before_multisite_lock_reset', $prev );
		$this->delete_multisite_lock_options();
		/**
		 * Fires after the multisite lock is reset.
		 *
		 * @param mixed $prev Previous stored value of the lock option(s).
		 */
		do_action( 'ss_multisite_lock_reset', $prev );
		Util::debug_log( 'Multisite export lock reset by user ID ' . get_current_user_id() . ' from IP ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) );
  return \wp_send_json_success( [ 'message' => __( 'Multisite export lock has been reset.', 'simply-static-pro' ) ] );
	}

	public function maybe_handle_reset_multisite_lock() {
		if ( ! is_admin() || ! function_exists( 'is_network_admin' ) || ! is_network_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'simply-static-settings' !== $page && 'simply-static-generate' !== $page ) {
			return;
		}
		$trigger = isset( $_GET['ss-reset-multisite-lock'] ) ? sanitize_text_field( wp_unslash( $_GET['ss-reset-multisite-lock'] ) ) : '';
		if ( '1' !== $trigger ) {
			return;
		}
  if ( ! current_user_can( apply_filters( 'ss_user_capability', 'manage_network_options', 'multisite_lock_reset' ) ) ) {
            set_transient( $this->ms_lock_notice_key, [ 'type' => 'error', 'msg' => __( 'You do not have permission to reset the multisite export lock.', 'simply-static-pro' ) ], MINUTE_IN_SECONDS );
            return;
        }
  $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
  if ( ! wp_verify_nonce( $nonce, 'ss-reset-ms-lock' ) ) {
      set_transient( $this->ms_lock_notice_key, [ 'type' => 'error', 'msg' => __( 'Security check failed. Please try again.', 'simply-static-pro' ) ], MINUTE_IN_SECONDS );
      return;
  }
		$prev = $this->get_current_multisite_lock_raw();
		do_action( 'ss_before_multisite_lock_reset', $prev );
		$this->delete_multisite_lock_options();
		do_action( 'ss_multisite_lock_reset', $prev );
		Util::debug_log( 'Multisite export lock reset via fallback by user ID ' . get_current_user_id() );
  set_transient( $this->ms_lock_notice_key, [ 'type' => 'success', 'msg' => __( 'Multisite export lock has been reset.', 'simply-static-pro' ) ], MINUTE_IN_SECONDS );
		$redirect_url = remove_query_arg( [ 'ss-reset-multisite-lock', '_wpnonce' ] );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_network_lock_notice() {
		if ( ! function_exists( 'is_network_admin' ) || ! is_network_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'simply-static-settings' !== $page && 'simply-static-generate' !== $page ) {
			return;
		}
		$stored = get_transient( $this->ms_lock_notice_key );
		if ( $stored ) {
			delete_transient( $this->ms_lock_notice_key );
			$type = isset( $stored['type'] ) ? $stored['type'] : 'info';
			$msg  = isset( $stored['msg'] ) ? $stored['msg'] : '';
			if ( $msg ) {
				echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		$info = $this->get_current_multisite_lock_info();
		if ( ! $info['running_site_id'] ) {
			return;
		}
		$reset_url = wp_nonce_url( add_query_arg( 'ss-reset-multisite-lock', '1' ), 'ss-reset-ms-lock' );
  $lock_text = sprintf(
            esc_html__( 'A multisite export lock is set by "%1$s" (%2$s).', 'simply-static-pro' ),
            $info['running_site_name'] ? $info['running_site_name'] : ( '#' . $info['running_site_id'] ),
            $info['running_site_url'] ? $info['running_site_url'] : ''
        );
        $active_text = $info['is_running_active'] ? esc_html__( 'An export process appears to be active on that site.', 'simply-static-pro' ) : esc_html__( 'No active export detected; the lock may be stale.', 'simply-static-pro' );
        ?>
        <div class="notice notice-warning">
            <p><strong><?php echo esc_html__( 'Simply Static – Multisite Export Lock', 'simply-static-pro' ); ?></strong></p>
            <p><?php echo $lock_text; ?><br /><?php echo $active_text; ?></p>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url( $info['activity_log_url'] ); ?>" target="_parent"><?php echo esc_html__( 'Open Activity Log', 'simply-static-pro' ); ?></a>
                <a class="button button-danger" style="margin-left:8px;" href="<?php echo esc_url( $reset_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset the multisite export lock?', 'simply-static-pro' ) ); ?>');"><?php echo esc_html__( 'Reset Multisite Export Lock', 'simply-static-pro' ); ?></a>
            </p>
        </div>
        <?php
	}

	private function get_current_multisite_lock_raw() {
		$keys = $this->get_multisite_lock_option_keys();
		$vals = [];
		foreach ( $keys as $key ) {
			$vals[ $key ] = get_site_option( $key, null );
		}
		return $vals;
	}

	private function get_current_multisite_lock_info() {
		$keys = $this->get_multisite_lock_option_keys();
		$lock_key = $keys[0];
		$val  = get_site_option( $lock_key, false );
		$running_site_id = 0;
		if ( is_array( $val ) && isset( $val['blog_id'] ) ) {
			$running_site_id = absint( $val['blog_id'] );
		} elseif ( is_numeric( $val ) ) {
			$running_site_id = absint( $val );
		}
		$running_site_url  = '';
		$running_site_name = '';
		$activity_log_url  = '';
		$is_running_active = false;
		if ( $running_site_id ) {
			$details = get_blog_details( $running_site_id );
			if ( $details ) {
				$running_site_url  = isset( $details->siteurl ) ? $details->siteurl : '';
				$running_site_name = isset( $details->blogname ) ? $details->blogname : '';
				$activity_log_url  = esc_url( get_admin_url( $running_site_id ) . 'admin.php?page=simply-static-generate' );
			}
			$job = Plugin::instance()->get_archive_creation_job();
			switch_to_blog( $running_site_id );
			$options = Options::reinstance();
			$job->set_options( $options );
			$is_running_active = $job->is_running();
			restore_current_blog();
		}
		return [
			'running_site_id'   => $running_site_id,
			'running_site_url'  => $running_site_url,
			'running_site_name' => $running_site_name,
			'activity_log_url'  => $activity_log_url,
			'is_running_active' => (bool) $is_running_active,
		];
	}

	private function get_multisite_lock_option_keys() {
		$base = Plugin::SLUG;
		$keys = [
			$base . '_multisite_export_running',
			$base . '_multisite_export_queue',
			$base . 'multisite_export_queue',
		];
		return apply_filters( 'ss_multisite_lock_option_keys', $keys );
	}

	private function delete_multisite_lock_options() {
		$keys = $this->get_multisite_lock_option_keys();
		foreach ( $keys as $key ) {
			delete_site_option( $key );
		}
	}
}