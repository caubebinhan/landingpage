<?php

namespace simply_static_pro\commands;

use Simply_Static\Options;
use Simply_Static\Plugin;
use Simply_Static\Util;
use simply_static_pro\Build;
use simply_static_pro\Helper;
use simply_static_pro\Single;

class Export extends CLI_Command {

	protected $blog_id = 0;

	protected $options = null;

	protected $task_list = [];

	public function get_description() {
		return 'Export Settings';
	}

	public function get_command_name() {
		return 'export';
	}

	public function get_synopsis() {
		$synopsis = [];

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

			$settings = Options::instance();

			$data = $settings->get_as_array();

			$json = wp_json_encode( $data );

			$file_or_echo = $this->ask("Export to file (f) or output here (o)? [f/o]");

			if ( 'f' === $file_or_echo ) {
				$file = $this->export_to_file( $json );
				\WP_CLI::success( 'Settings exported to ' . $file );
			} else {
				\WP_CLI::line( $json );
			}

			// Restore blog id.
			if ( isset( $options['blog_id'] ) ) {
				$this->restore_blog();
			}
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	public function export_to_file( $setting_json ) {
		$filesystem = Helper::get_file_system();

		// Get config file path.
		$upload_dir  = wp_upload_dir();
		$config_dir  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
		$config_file = $config_dir . 'simply-static-settings.json';

		// Delete old index.
		if ( file_exists( $config_file ) ) {
			wp_delete_file( $config_file );
		}

		// Check if directory exists.
		if ( ! is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir );
		}

		$filesystem->put_contents( $config_file, $setting_json );

		return $config_file;
	}

	/**
	 * We are asking a question and returning an answer as a string.
	 *
	 * @param $question
	 *
	 * @return string
	 */
	protected function ask( $question ) {
		// Adding space to question and showing it.
		fwrite( STDOUT, $question . ' ' );

		return strtolower( trim( readline() ) );
	}
}