<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class which handles WPML task.
 */
class WPML_Task extends Simply_Static\Task {

	/**
	 * Array of languages.
	 *
	 * @var null|array
	 */
	protected $langs = null;

	/**
	 * Get the WPML langs.
	 *
	 * @return mixed|null
	 */
	public function get_langs() {
		if ( $this->langs === null ) {
			$this->langs = apply_filters( 'wpml_active_languages', [] );
		}

		return $this->langs;
	}

	/**
	 * Get the processed langs.
	 *
	 * @return array|mixed|null
	 */
	public function get_processed_langs() {
		$processed_langs = $this->options->get( 'wpml_processed_languages' );

		if ( ! $processed_langs ) {
			$processed_langs = [];
		}

		return $processed_langs;
	}

	/**
	 * Get the copied langs.
	 *
	 * @return array|mixed|null
	 */
	public function get_copied_langs() {
		$copied_langs = $this->options->get( 'wpml_copied_languages' );

		if ( ! $copied_langs ) {
			$copied_langs = [];
		}

		return $copied_langs;
	}

	/**
	 * Perform the task.
	 *
	 * @return bool
	 */
	public function perform() {

		try {
			$langs_to_process = $this->copy_langs();

			if ( ! $langs_to_process ) {
				$this->options->destroy( 'wpml_copied_languages' );
				$this->options->destroy( 'wpml_processed_languages' );
				$this->options->save();
				return true;
			}

			$files_to_copy          = $this->scan_wp_content();
			$includes_files_to_copy = $this->scan_wp_includes();
			$admin_files_to_copy    = $this->scan_wp_admin();

			if ( $includes_files_to_copy ) {
				$files_to_copy = array_merge( $files_to_copy, $includes_files_to_copy );
			}

			if ( $admin_files_to_copy ) {
				$files_to_copy = array_merge( $files_to_copy, $admin_files_to_copy );
			}

			$cleaned_files   = $this->remove_archive_path( $files_to_copy );
			$processed_paths = $this->get_processed_files();

			foreach ( $langs_to_process as $lang ) {
				$this->process_lang( $lang, $cleaned_files, $processed_paths );
			}
		} catch ( \Exception $e ) {
			$this->save_status_message( __( 'WPML Error while copying language files:' , 'simply-static-pro' ) . ' ' . $e->getMessage() . __( 'Continuing export without them...', 'simply-static-pro' ) );
			return true; // Returning true so we continue with export.
		}


		return false;
	}

	/**
	 * Remove archie path from the file to get relative path.
	 *
	 * @param array $files Array of file paths.
	 *
	 * @return string[]
	 */
	public function remove_archive_path( $files ) {
		$archive_folder = $this->options->get_archive_dir();

		return array_map( function( $file ) use ( $archive_folder ) {
			return DIRECTORY_SEPARATOR . ltrim( str_replace( $archive_folder, '', $file ), DIRECTORY_SEPARATOR );
		}, $files );
	}

	/**
	 * Process a language.
	 *
	 * @param string $lang Language code.
	 * @param array  $files All files to process for the language.
	 * @param array  $processed_files All processed files.
	 *
	 * @return void
	 */
	public function process_lang( $lang, $files, $processed_files ) {
		$processed_paths_for_lang = $this->get_lang_files( $lang, $processed_files );

		if ( count( $processed_paths_for_lang ) >= count( $files ) ) {
			$this->save_lang_as_processed( $lang );
			return;
		}

		$files_to_process = $this->get_files_to_process( $lang, $processed_paths_for_lang, $files );

		if ( ! $files_to_process ) {
			$this->save_lang_as_processed( $lang );
			return;
		}

		$this->insert_files( $files_to_process );

		$this->save_lang_as_processed( $lang );
	}

	/**
	 * Get only files that are yet to be processed.
 	 *
	 * @param string $lang Language code.
	 * @param array  $processed_files Array of file paths that are processed.
	 * @param array  $all_files All files from which we find non processed ones.
	 *
	 * @return string[]
	 */
	public function get_files_to_process( $lang, $processed_files, $all_files ) {
		$prepared_files = array_map( function( $file ) use ( $lang ) {
			return DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . ltrim( $file, DIRECTORY_SEPARATOR );
		}, $all_files );

		$to_process = array_diff( $prepared_files, $processed_files );

		return $to_process;
	}

	/**
	 * Save the language as a processed one.
	 *
	 * @param string $lang Language code.
	 *
	 * @return void
	 */
	public function save_lang_as_processed( $lang ) {
		$processed_langs = $this->get_processed_langs();
		$processed_langs[] = $lang;
		$this->options->set( 'wpml_processed_languages', $processed_langs );
		$this->options->save();

	}

	/**
	 * Copy langs and return langs to process.
	 *
	 * @return array
	 */
	public function copy_langs() {
		global $sitepress;

		$filesystem = Helper::get_file_system();

		if ( ! $filesystem ) {
			return [];
		}

		$default_code      = $sitepress->get_default_language();
		$url_Settings      = $sitepress->get_setting( "urls" );
		$langs             = $this->get_langs();
		$langs_to_process  = [];
		$processed_langs   = $this->get_processed_langs();
		$copied_langs      = $this->get_copied_langs();
		$wp_content_folder = $this->get_wp_content_name();
		$wp_content_dir    = $this->get_wp_content_dir();
		$wp_includes       = $this->get_wp_includes_name();
		$wp_includes_dir   = $this->get_wp_includes_dir();
		$wp_admin_dir      = $this->get_wp_admin_dir();

		foreach ( $langs as $lang ) {
			// If it's the default language and it's not using a directory as well, skip it.
			if ( $default_code === $lang['code'] && absint( $url_Settings['directory_for_default_language'] ) === 0 ) {
				continue;
			}

			if ( in_array( $lang['code'], $processed_langs, true  ) ) {
				continue;
			}

			$langs_to_process[] = $lang['code'];

			if ( in_array( $lang['code'], $copied_langs, true  ) ) {
				continue;
			}

			$this->copy_folder_to_lang( $lang['code'], $wp_content_folder, $wp_content_dir );
			$this->copy_folder_to_lang( $lang['code'], $wp_includes, $wp_includes_dir );
			$this->copy_folder_to_lang( $lang['code'], 'wp-admin',  $wp_admin_dir );

			$copied_langs[] = $lang['code'];
		}

		$this->options->set( 'wpml_copied_languages', $copied_langs );
		$this->options->save();

		return $langs_to_process;

	}

	/**
	 * Copy a folder for the provided language.
	 *
	 * @param string $lang Language code.
	 * @param string $folder_name Folder name to be used.
	 * @param string $dir Path to the directory to copy.
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function copy_folder_to_lang( $lang, $folder_name, $dir) {
		$archive_folder = rtrim( $this->options->get_archive_dir(), DIRECTORY_SEPARATOR );

		$lang_content_dir = $archive_folder . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $folder_name;

		if ( ! is_dir( $dir ) ) {
			// Such dir doesn't exist to copy it.
			return;
		}

		if ( ! is_dir( $lang_content_dir ) ) {
			@mkdir( $lang_content_dir, 0755, true );
		}

		$resp = copy_dir( $dir, $lang_content_dir );

		if ( is_wp_error( $resp ) ) {
			throw new \Exception( $resp->get_error_message() );
		}
	}

	/**
	 * Get the files from file paths related to the language.
	 *
	 * @param string   $lang Lang code. Example: en
	 * @param string[] $file_paths Array of file paths.
	 *
	 * @return array
	 */
	public function get_lang_files( $lang, $file_paths ) {
		$content_dir  = DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $this->get_wp_content_name() . DIRECTORY_SEPARATOR;
		$includes_dir = DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $this->get_wp_includes_name() . DIRECTORY_SEPARATOR;
		$admin_dir    = DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR;

		return array_filter( $file_paths, function ( $file_path ) use ( $content_dir, $includes_dir, $admin_dir ) {
			return strpos( $file_path, $content_dir ) === 0 || strpos( $file_path, $includes_dir ) === 0 || strpos( $file_path, $admin_dir ) === 0;
		});
	}

	/**
	 * Get the WP Content folder name.
	 *
	 * @return mixed|string
	 */
	public function get_wp_content_name() {
		$wp_content_folder     = 'wp-content';
		$content_folder_change = $this->options->get( 'wp_content_directory' );

		if ( $content_folder_change && $content_folder_change !== $wp_content_folder ) {
			$wp_content_folder = $content_folder_change;
		}

		return $wp_content_folder;
	}

	/**
	 * Get the WP Includes folder name.
	 *
	 * @return mixed|string
	 */
	public function get_wp_includes_name() {
		$wp_includes_folder    = 'wp-includes';
		$content_folder_change = $this->options->get( 'wp_includes_directory' );

		if ( $content_folder_change && $content_folder_change !== $wp_includes_folder ) {
			$wp_includes_folder = $content_folder_change;
		}

		return $wp_includes_folder;
	}

	/**
	 * Scan the WP Content.
	 *
	 * @return array|mixed
	 */
	public function scan_wp_content() {
		$content_dir = $this->get_wp_content_dir();
		return $this->scan_dir( $content_dir );
	}

	/**
	 * Scan the WP Includes.
	 *
	 * @return array|mixed
	 */
	public function scan_wp_includes() {
		$content_dir = $this->get_wp_includes_dir();
		return $this->scan_dir( $content_dir );
	}

	/**
	 * Scan the WP Admin.
	 *
	 * @return array|mixed
	 */
	public function scan_wp_admin() {
		$content_dir = $this->get_wp_admin_dir();
		return $this->scan_dir( $content_dir );
	}

	/**
	 * Get the WP Content dir path.
	 *
	 * @param string $lang Lang code.
	 *
	 * @return string
	 */
	public function get_wp_content_dir( $lang = '' ) {
		$wp_content_folder = $this->get_wp_content_name();
		$archive_folder    = $this->options->get_archive_dir();

		$lang_path = $lang ? $lang . DIRECTORY_SEPARATOR : '';

		return rtrim( $archive_folder, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $lang_path . $wp_content_folder;
	}

	/**
	 * Get the WP Includes dir path.
	 *
	 * @param string $lang Lang code.
	 *
	 * @return string
	 */
	public function get_wp_includes_dir( $lang = '' ) {
		$folder         = $this->get_wp_includes_name();
		$archive_folder = $this->options->get_archive_dir();

		$lang_path = $lang ? $lang . DIRECTORY_SEPARATOR : '';

		return rtrim( $archive_folder, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $lang_path . $folder;
	}

	/**
	 * Get the WP Admin dir path.
	 *
	 * @param string $lang Lang code.
	 *
	 * @return string
	 */
	public function get_wp_admin_dir( $lang = '' ) {
		$folder         = 'wp-admin';
		$archive_folder = $this->options->get_archive_dir();

		$lang_path = $lang ? $lang . DIRECTORY_SEPARATOR : '';

		return rtrim( $archive_folder, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $lang_path . $folder;
	}

	/**
	 * Insert the file paths.
	 *
	 * @param string[] $file_paths File paths.
	 *
	 * @return void
	 */
	public function insert_files( $file_paths ) {
		global $wpdb;
		$chunks = array_chunk( $file_paths, 250 );

		foreach ( $chunks as $chunk ) {
			$values = [];
			foreach ( $chunk as $file ) {
				$values[] = $wpdb->prepare("(%s,%s,'WPML',%s)", $file, home_url( $file ), current_time( 'mysql' ) );
			}
			$wpdb->query( "INSERT INTO {$wpdb->prefix}simply_static_pages (file_path, url, status_message, created_at) VALUES " . implode( ',', $values ) . ";" );
		}
	}

	/**
	 * Get processed files.
	 *
	 * @return array
	 */
	public function get_processed_files() {
		global $wpdb;

		$files = $wpdb->get_results( $wpdb->prepare( "SELECT file_path FROM {$wpdb->prefix}simply_static_pages WHERE status_message=%s", "WPML" ), ARRAY_A );
		return wp_list_pluck( $files, 'file_path' );
	}

	/**
	 * Scan the dir recursively.
	 *
	 * @param string   $dir File path.
	 * @param string[] $files Scanned files.
	 *
	 * @return array|mixed
	 */
	public function scan_dir( $dir, $files = [] ) {
		if ( is_dir( $dir ) ) {
			$content = scandir( $dir );

			foreach ( $content as $item ) {
				if ( in_array( $item, [ '.', '..' ], true  ) ) {
					continue;
				}

				$path = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $item;

				if ( is_dir( $path ) ) {
					$files = $this->scan_dir( $path, $files );
					continue;
				}

				if ( is_file( $path ) ) {
					$files[] = $path;
				}
			}
		}

		return $files;
	}
}