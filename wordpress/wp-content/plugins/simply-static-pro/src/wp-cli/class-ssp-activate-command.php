<?php

namespace simply_static_pro\commands;

use Simply_Static\Options;
use Simply_Static\Plugin;
use Simply_Static\Util;
use simply_static_pro\Build;
use simply_static_pro\Single;

class Activate_Command extends CLI_Command {

	protected $blog_id = 0;

	protected $options = null;

	protected $task_list = [];

	public function get_description() {
		return 'Activate the Pro license.';
	}

	public function get_command_name() {
		return 'activate';
	}

	public function get_synopsis() {
		$synopsis = [];

		$synopsis[] = array(
			'type'        => 'assoc',
			'name'        => 'license',
			'description' => 'License',
			'optional'    => false,
			'repeating'   => false,
		);

		if ( is_multisite() ) {
			$synopsis[] = array(
				'type'        => 'assoc',
				'name'        => 'blog_id',
				'description' => 'Blog ID. If empty, it\'ll use the first blog (sites) (Blog with lower ID).',
				'optional'    => true,
				'repeating'   => false,
			);
		}

		return $synopsis;
	}

	public function set_blog_id( $blog_id ) {
		if ( ! is_multisite() ) {
			$this->blog_id = get_current_blog_id();

			return;
		}

		switch_to_blog( $blog_id );
		$this->blog_id = $blog_id;
	}

	public function restore_blog() {
		if ( ! is_multisite() ) {
			return;
		}

		restore_current_blog();
	}

	public function __invoke( $args, $options ) {

		try {
			$this->set_blog_id( $options['blog_id'] ?? get_current_blog_id() );

			// Check if the Freemius SDK is loaded
			if ( ! class_exists('Freemius') ) {
				return;
			}

			if ( ! function_exists( 'ssp_fs' ) ) {
				return;
			}

			if ( empty( $options['license'] ) ) {
				throw new \Exception( __( 'License is required.', 'simply-static-pro' ) );
			}

			$plugin = Plugin::instance();
			remove_filter( 'http_request_args', array( $plugin, 'add_http_filters' ), 10 );

			$license = trim( $options['license'] );
			$ssp_fs  = ssp_fs();
			$result  = $ssp_fs->activate_migrated_license( $license );
			$error   = $result['error'] ?? '';

			add_filter( 'http_request_args', array( $plugin, 'add_http_filters' ), 10, 2 );

			if ( $error ) {
				throw new \Exception( $error );
			}

			// Restore blog id.
			if ( isset( $options['blog_id'] ) ) {
				$this->restore_blog();
			}

			\WP_CLI::success( __( 'License activated.', 'simply-static-pro' ) );
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}