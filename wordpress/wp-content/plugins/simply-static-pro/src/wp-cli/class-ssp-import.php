<?php

namespace simply_static_pro\commands;

use Simply_Static\Options;
use Simply_Static\Plugin;
use Simply_Static\Util;
use simply_static_pro\Build;
use simply_static_pro\Helper;
use simply_static_pro\Single;

class Import extends CLI_Command {

	protected $blog_id = 0;

	protected $options = null;

	protected $task_list = [];

	public function get_description() {
		return 'Import Settings';
	}

	public function get_command_name() {
		return 'import';
	}

	public function get_synopsis() {
		$synopsis = [];

		$synopsis[] = array(
			'type'        => 'assoc',
			'name'        => 'json',
			'description' => 'JSON settings string. Use this or file.',
			'optional'    => true,
			'repeating'   => false,
		);

		$synopsis[] = array(
			'type'        => 'assoc',
			'name'        => 'file',
			'description' => 'File path to JSON settings. Use this or json.',
			'optional'    => true,
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

			if ( empty( $options['file'] ) && empty( $options['json'] ) ) {
				throw new \Exception( 'No file or json provided' );
			}

			$filesystem = Helper::get_file_system();

			if ( ! empty( $options['json'] ) ) {
				$data = $options['json'];
			} else {
				if ( 0 !== strpos( $options['file'], 'http' ) ) {
					// Not an URL. Make sure it exists
					if ( ! $filesystem->exists( $options['file'] ) ) {
						throw new \Exception( 'File does not exist.' );
					}
				}

				$data = $filesystem->get_contents( $options['file'] );
			}

			$data = json_decode( $data, true );

			$settings = Options::instance();

			$settings->set_options( $data );
			$settings->save();

			// Restore blog id.
			if ( isset( $options['blog_id'] ) ) {
				$this->restore_blog();
			}

			\WP_CLI::success( 'Imported successfully.' );
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

}