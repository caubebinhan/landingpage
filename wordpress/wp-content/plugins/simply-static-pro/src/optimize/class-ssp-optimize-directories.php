<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class which handles GitHub commits.
 */
class Optimize_Directories_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'optimize_directories';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options       = Simply_Static\Options::instance();
		$this->options = $options;
	}

	/**
	 * Perform action to run on commit task.
	 *
	 * @return bool
	 */
	public function perform() {
		$this->save_status_message( __( 'Replace Directories...', 'simply-static-pro' ) );

		$author_directory      = trim( $this->options->get( 'author_url' ) );
		$wp_content_directory  = trim( $this->options->get( 'wp_content_directory' ) );
		$wp_uploads_directory  = trim( $this->options->get( 'wp_uploads_directory' ) );
		$wp_plugins_directory  = trim( $this->options->get( 'wp_plugins_directory' ) );
		$wp_themes_directory   = trim( $this->options->get( 'wp_themes_directory' ) );
		$wp_includes_directory = trim( $this->options->get( 'wp_includes_directory' ) );
		$new_style             = trim( $this->options->get( 'theme_style_name' ) );
		$archive_dir           = untrailingslashit( $this->options->get_archive_dir() ) . '/';
		$plugin_names          = Filter::get_hashed_plugin_names();

		if ( $new_style && 'style' !== $new_style ) {
			$style_path     = $archive_dir . 'wp-content/themes/' . get_stylesheet() . '/style.css';
			$new_style_path = $archive_dir . 'wp-content/themes/' . get_stylesheet() . '/' . $new_style . '.css';

			if ( file_exists( $style_path ) ) {
				$renamed = rename( $style_path, $new_style_path );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $style_path, $new_style_path );
				}
			}
		}

		if ( $author_directory && 'author' !== $author_directory && '/' !== $author_directory ) {
			$source_dir = $archive_dir . 'author';
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $archive_dir . $author_directory );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $archive_dir . $author_directory );
				}
			}
		}

		if ( $plugin_names && $this->options->get( 'rename_plugin_directories' ) ) {
			foreach ( $plugin_names as $plugin_name => $hashed_name ) {
				$plugin_path = $archive_dir . 'wp-content/plugins/' . $plugin_name;
				$hashed_path = $archive_dir . 'wp-content/plugins/' . $hashed_name;

				if ( file_exists( $plugin_path ) ) {
					$renamed = rename( $plugin_path, $hashed_path );

					if ( ! $renamed ) {
						Alternate_Filesystem::rename( $plugin_path, $hashed_path );
					}
				}
			}
		}

		if ( $wp_plugins_directory && 'plugins' !== $wp_plugins_directory && '/' !== $wp_plugins_directory ) {
			$source_dir = $archive_dir . 'wp-content/plugins';
			$dest_dir   = $archive_dir . 'wp-content/' . $wp_plugins_directory;
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $dest_dir );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $dest_dir );
				}
			}
		}

		if ( $wp_themes_directory && 'themes' !== $wp_themes_directory && '/' !== $wp_themes_directory ) {
			$source_dir = $archive_dir . 'wp-content/themes';
			$dest_dir   = $archive_dir . 'wp-content/' . $wp_themes_directory;
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $dest_dir );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $dest_dir );
				}
			}
		}

		if ( $wp_uploads_directory && 'uploads' !== $wp_uploads_directory && '/' !== $wp_uploads_directory ) {
			$source_dir = $archive_dir . 'wp-content/uploads';
			$dest_dir   = $archive_dir . 'wp-content/' . $wp_uploads_directory;
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $dest_dir );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $dest_dir );
				}
			}
		}

		if ( $wp_content_directory && 'wp-content' !== $wp_content_directory && '/' !== $wp_content_directory ) {
			$source_dir = $archive_dir . 'wp-content';
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $archive_dir . $wp_content_directory );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $archive_dir . $wp_content_directory );
				}
			}
		}

		if ( $wp_includes_directory && 'wp-includes' !== $wp_includes_directory && '/' !== $wp_includes_directory ) {
			$source_dir = $archive_dir . 'wp-includes';
			if ( file_exists( $source_dir ) ) {
				$renamed = rename( $source_dir, $archive_dir . $wp_includes_directory );

				if ( ! $renamed ) {
					Alternate_Filesystem::rename( $source_dir, $archive_dir . $wp_includes_directory );
				}
			}
		}

		$this->save_status_message( __( 'Replaced Directories', 'simply-static-pro' ) );

		return true;
	}

}
