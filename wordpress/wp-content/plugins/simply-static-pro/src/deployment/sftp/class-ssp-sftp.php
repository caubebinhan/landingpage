<?php

namespace simply_static_pro;

use Simply_Static\Options;
use Simply_Static\Util;

use phpseclib3\Crypt\PublicKeyLoader;

class SFTP {

	/**
	 * SFTP client.
	 *
	 * @var null
	 */
	protected $sftp = null;

	/**
	 * @var Options|null
	 */
	protected $options = null;

	public function __construct() {
		$this->options = Options::instance();
	}

	/**
	 * Upload the files inside of the folder.
	 *
	 * @param string $page_file_path Path to the folder with files to upload.
	 *
	 * @return true|\WP_Error
	 */
	public function upload( $page_file_path ) {
		$file_path      = $this->options->get_archive_dir() . $page_file_path;
		$folders        = explode( '/', $page_file_path );
		$filename       = array_pop( $folders );
		$sftp           = $this->get_sftp();
		$opened_folders = 0;
		// Current folders
		foreach ( $folders as $folder ) {
			$dirs = $sftp->rawlist( '.' );

			if ( empty( $dirs[ $folder ] ) ) {
				$sftp->mkdir( $folder );
			}

			$sftp->chdir( $folder );
			$opened_folders ++;
		}

		// SFTP UPLOAD
		$upload = $sftp->put( $filename, $file_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE );

		if ( $opened_folders ) {
			while ( $opened_folders > 0 ) {
				// Move to parent folder.
				$sftp->chdir( '..' );
				$opened_folders --;
			}
		}

		if ( ! $upload ) {
			return new \WP_Error( 'sftp_failed_upload', 'File not uploaded. ' . $sftp->getLastSFTPError() );
		}

		return true;
	}

	/**
	 * SFTP class positioned in the selected folder.
	 *
	 * @return false|\phpseclib3\Net\SFTP
	 */
	public function get_sftp() {
		if ( $this->sftp === null ) {
			$host   = $this->options->get( 'sftp_host' );
			$user   = $this->options->get( 'sftp_user' );
			$pass   = $this->get_pass();
			$port   = $this->options->get( 'sftp_port' );
			$folder = $this->options->get( 'sftp_folder' );

			if ( strpos( trim( $pass ), '-----BEGIN' ) === 0 ) {
				$pass = PublicKeyLoader::load( $pass );
			}

			if ( ! $port ) {
				$port = 22;
			}

			if ( strpos( $host, 'sftp://' ) === 0 ) {
				$host = str_replace( 'sftp://', '', $host );
			}

			$this->sftp = new \phpseclib3\Net\SFTP( $host, absint( $port ) );
			$login      = $this->sftp->login( $user, $pass );

			if ( ! $login ) {
				Util::debug_log( 'Not able to login to SFTP' );

				return false;
			}

			if ( $folder ) {
				$folder = trailingslashit( $folder );
				$this->sftp->chdir( $folder );
			}
		}

		return $this->sftp;
	}

	/**
	 * Get Password or Key to use for SFTP.
	 *
	 * @return mixed|null
	 */
	public function get_pass() {
		if ( defined( 'SSP_SFTP_KEY' ) && SSP_SFTP_KEY ) {
			return SSP_SFTP_KEY;
		}

		$key = $this->options->get( 'sftp_private_key' );

		if ( $key ) {
			return $key;
		}

		return $this->options->get( 'sftp_pass' );
	}

}
