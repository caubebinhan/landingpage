<?php

namespace simply_static_pro;

use Aws\Exception\MultipartUploadException;
use Aws\Result;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use Aws\S3\Transfer;
use Simply_Static\Util;

class S3_Client {

	/**
	 * API Version
	 *
	 * @var string
	 */
	protected $version = 'latest';

	protected $client = null;

	protected $api_key = null;

	protected $api_secret = null;

	protected $region = null;

	protected $bucket = null;

	protected $endpoint = null;

	public function set_param( $param, $value ) {

		$this->{$param} = $value;

		return $this;
	}

	public function set_endpoint( $endpoint ) {
		return $this->set_param( 'endpoint', $endpoint );
	}

	public function set_region( $region ) {
		return $this->set_param( 'region', $region );
	}

	public function set_api_secret( $api_secret ) {
		return $this->set_param( 'api_secret', $api_secret );
	}

	public function set_api_key( $api_key ) {
		return $this->set_param( 'api_key', $api_key );
	}

	public function set_bucket( $bucket ) {
		return $this->set_param( 'bucket', $bucket );
	}

	/**
	 * Get the Client object.
	 *
	 * @return S3Client
	 */
	public function get_client() {
		if ( null === $this->client ) {
			$credentials = $this->api_key && $this->api_secret ? [
				'key'    => $this->api_key,
				'secret' => $this->api_secret
			] : null;

			// Base config
			$config = [
				'region'  => $this->region,
				'version' => $this->version,
			];

			// If credentials are not null, set them on the config
			// An absent 'credentials' key will default to using the default provider chain
			// https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html#config_credentials
			if ( $credentials ) {
				$config['credentials'] = $credentials;
			}

			if ( $this->endpoint ) {
				$config['endpoint']                = $this->endpoint;
				$config['use_path_style_endpoint'] = false; // For Digital Ocean Spaces
			}

			$this->client = new S3Client( $config );
		}

		return $this->client;
	}

	/**
	 * Get the S3 authentication method.
	 * @return string
	 */
	public function get_auth_method(): string {
		return $this->api_key && $this->api_secret ? 'aws-iam-key' : 'aws-ec2';
	}

	public function get_files( $files = [], $token = '', $fetchAll = true ) {
		try {
			$params = [
				'Bucket' => $this->bucket
			];

			if ( $token ) {
				$params['ContinuationToken'] = $token;
			}

			$objects = $this->get_client()->ListObjectsV2( $params );

			if ( empty( $objects['Contents'] ) ) {
				return $files;
			}

			$files = array_merge( $files, wp_list_pluck( $objects['Contents'], 'Key' ) );

			if ( $fetchAll && $objects['IsTruncated'] && ! empty( $objects['NextContinuationToken'] ) ) {
				$files = $this->get_files( $files, $objects['NextContinuationToken'] );
				if ( is_wp_error( $files ) ) {
					throw new \Exception( $files->get_error_message() );
				}
			}

			return $files;
		} catch ( \Exception $exception ) {
			return new \WP_Error( $exception->getCode(), "Failed to list objects in $this->bucket with error: " . $exception->getMessage() );
		}
	}

	/**
	 * Delete file from S3 bucket.
	 *
	 * @param string $key given key for file reference.
	 *
	 * @return Result
	 */
	public function delete_file( $key ): Result {
		// Prevent calls to S3 with an empty key. Log and return a synthetic Result.
		$normalized_key = is_string( $key ) ? $key : (string) $key;
		if ( '' === trim( $normalized_key ) ) {
			Util::debug_log( 'AWS S3 DeleteObject: Attempted to delete object with empty key. Skipping request.' );
			return new Result(
				[
					'@metadata' => [
						'operation'   => 'DeleteObject',
						'statusCode'  => 0,
						'reason'      => 'empty_key_skipped',
					],
					'skipped'   => true,
				]
			);
		}

		try {
			return $this->get_client()->deleteObject(
				[
					'Key'    => $normalized_key,
					'Bucket' => $this->bucket,
				]
			);
		} catch ( \Throwable $e ) {
			// Log the error and return a Result object carrying error info to keep return type stable.
			Util::debug_log( 'AWS S3 DeleteObject Error: ' . $e->getMessage() );
			return new Result(
				[
					'@metadata' => [
						'operation'   => 'DeleteObject',
						'statusCode'  => 0,
						'exception'   => get_class( $e ),
					],
					'error' => $e->getMessage(),
				]
			);
		}
	}

	/**
	 * Uploading a local directory to Amazon S3
	 *
	 * @param string $path given directory path.
	 * @param string $subdirectory given subdirectory path.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function transfer_directory( string $path, string $subdirectory = '' ): void {
		if ( ! file_exists( $path ) || ! ( new \FilesystemIterator( $path ) )->valid() ) {
			throw new \Exception(
				"Failed to transfer directory to S3 bucket. No generated site found on {$path}.",
			);
		}

		$transfer_options = [
			'before' => function ( \Aws\Command $command ) {
				// Start a garbage collection cycle to close file handles opened by the SDK.
				// This problem is specific for OSX.
				// See: https://github.com/aws/aws-sdk-php/issues/749
				gc_collect_cycles();
			},
		];

		// Create a transfer object.
		if ( ! empty( $subdirectory ) ) {
			$manager = new Transfer( $this->get_client(), $path, "s3://{$this->bucket}/{$subdirectory}/", $transfer_options );
		} else {
			$manager = new Transfer( $this->get_client(), $path, "s3://{$this->bucket}/", $transfer_options );
		}

		// Perform the transfer synchronously.
		$manager->transfer();
	}

	/**
	 * Upload file to S3 bucket.
	 *
	 * @param string $file given file.
	 * @param string $file_path given file path.
	 *
	 * @return bool
	 *
	 */
	public function upload_file( $file, $file_path ) {
		$source = fopen( $file, 'rb' );

		$uploader = new ObjectUploader(
			$this->get_client(),
			$this->bucket,
			$file_path, // 'Key' or where to save the file on S3.
			$source,
			apply_filters( 'ssp_s3_acl_privilege', 'private' ),
		);

		$uploaded = false;
		do {
			try {
				$result = $uploader->upload();
				if ( $result["@metadata"]["statusCode"] == '200' ) {
					$uploaded = true;
				}
			} catch ( MultipartUploadException $e ) {
				rewind( $source );
				$uploader = new MultipartUploader( $this->get_client(), $source, [
					'state' => $e->getState(),
				] );
			}
		} while ( ! isset( $result ) );

		if ( ! $uploaded ) {
			Util::debug_log( '==== AWS FILE NOT UPLOADED ====' );
			Util::debug_log( 'File: ' . $file_path );
			Util::debug_log( $result );
		}

		fclose( $source );

		return $uploaded;
	}

	/**
	 * Invalidate CloudFront distribution.
	 *
	 * @return bool
	 */
	public function invalidate(): bool {
		$options = get_option( 'simply-static' );

		$credentials = $this->api_key && $this->api_secret ? [
			'key'    => $this->api_key,
			'secret' => $this->api_secret
		] : null;

		if ( ! empty( $options['aws_distribution_id'] ) ) {
			$cloud_front_client = new \Aws\CloudFront\CloudFrontClient( [
				'version'     => $this->version,
				'region'      => $this->region,
				'credentials' => $credentials
			] );

			try {
				$cloud_front_client->createInvalidation(
					apply_filters(
						'ssp_aws_cloudfront_invalidation_args',
						[
							'DistributionId'    => $options['aws_distribution_id'],
							'InvalidationBatch' => [
								'CallerReference' => $this->generate_random_string( 16 ),
								'Paths'           => [
									'Items'    => [ '/*' ],
									'Quantity' => 1
								]
							]
						]
					)
				);

			} catch ( \Aws\AwsException $e ) {
				Util::debug_log( $e->getAwsErrorMessage() );
			}
		}

		return true;
	}

	/**
	 * Generate a random string for invalidation.
	 *
	 * @param int $length given string length.
	 *
	 * @return string
	 */
	public function generate_random_string( $length = 10 ) {
		$characters    = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$char_length   = strlen( $characters );
		$random_string = '';

		for ( $i = 0; $i < $length; $i ++ ) {
			$random_string .= $characters[ wp_rand( 0, $char_length - 1 ) ];
		}

		return $random_string;
	}
}
