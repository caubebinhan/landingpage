<?php

namespace simply_static_pro\integrations\Github;

use Simply_Static\Util;

class API {

	/**
	 * API URL
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.github.com/';

	/**
	 * API Version
	 *
	 * @var string
	 */
	protected $api_version = '2022-11-28';

	/**
	 * API Token
	 *
	 * @var string
	 */
	protected $token = '';

	/**
	 * Set API Token.
	 *
	 * @param string $token Token.
	 *
	 * @return void
	 */
	public function set_token( $token ) {
		$this->token = $token;
	}

	/**
	 * Get Headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return [
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . $this->token,
			'X-GitHub-Api-Version' => $this->api_version
		];
	}

	/**
	 * Get URL
	 *
	 * @param string $resource Resource.
	 *
	 * @return string
	 */
	public function get_url( $resource ) {
		return trailingslashit( $this->api_url ) . $resource;
	}

	/**
	 * Sanitize a value for safe logging by omitting or truncating large payloads.
	 *
	 * - Any key named 'body' will be replaced with a compact placeholder showing length.
	 * - Any string longer than $max_len will be replaced with a placeholder showing length.
	 * - Arrays are traversed recursively.
	 *
	 * @param mixed $value
	 * @param int   $max_len
	 * @return mixed
	 */
	protected function sanitize_for_log( $value, $max_len = 2048 ) {
		if ( is_string( $value ) ) {
			$len = strlen( $value );
			if ( $len > $max_len ) {
				return '[omitted string; length=' . $len . ' bytes]';
			}
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$key = is_string( $k ) ? strtolower( $k ) : $k;
				if ( 'body' === $key ) {
					$len        = is_string( $v ) ? strlen( $v ) : 0;
					$value[$k]  = '[omitted body; length=' . $len . ' bytes]';
				} else {
					$value[$k] = $this->sanitize_for_log( $v, $max_len );
				}
			}
			return $value;
		}

		return $value;
	}

	/**
	 * Truncate a full log message to a safe maximum size.
	 *
	 * @param string $message
	 * @param int    $max_total
	 * @return string
	 */
	protected function truncate_log_message( $message, $max_total = 20000 ) {
		if ( ! is_string( $message ) ) {
			return $message;
		}
		$len = strlen( $message );
		if ( $len > $max_total ) {
			return substr( $message, 0, $max_total ) . '... [truncated]';
		}
		return $message;
	}

	/**
	 * Prepare response for usage.
	 *
	 * @param \WP_HTTP_Requests_Response|\WP_Error|null $response
	 * @param string $method Method name.
	 * @param array $args Arguments in request.
	 *
	 * @return array
	 */
	protected function prepare_response( $response, $method = null, $args = [], $resource = '' ) {

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		$limits = $this->update_rate_limits( $response );

		if ( $this->is_redirect_code( $code ) ) {
			$headers = wp_remote_retrieve_headers( $response );
			if ( ! empty( $headers['Location'] ) && $method ) {
				return $this->{$method}( $headers['Location'], $args );
			}

			throw new \Exception( __( 'Something went wrong with the request', 'simply-static-pro' ), $code );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $this->is_error_code( $code ) ) {
			if ( ! empty( $data['message'] ) ) {
				// Avoid decoding large request bodies to prevent memory exhaustion.
				if ( isset( $args['body'] ) ) {
					$body_length    = is_string( $args['body'] ) ? strlen( $args['body'] ) : 0;
					$args['body']   = '[omitted body; length=' . $body_length . ' bytes]';
				}

				// Remove headers from args.
				unset( $args['headers'] );

				// Log the response without heavy payloads.
				$safe_args = $this->sanitize_for_log( $args );
				$log_msg   = $data['message'] . " Data: \n" . print_r( [
					'method'   => $method,
					'resource' => $resource,
					'args'     => $safe_args,
				], true );
				Util::debug_log( $this->truncate_log_message( $log_msg ) );

				throw new \Exception( $data['message'], $code );
			}

			if ( ! empty( $data['error'] ) ) {

				// Avoid decoding large request bodies to prevent memory exhaustion.
				if ( isset( $args['body'] ) ) {
					$body_length    = is_string( $args['body'] ) ? strlen( $args['body'] ) : 0;
					$args['body']   = '[omitted body; length=' . $body_length . ' bytes]';
				}

				// Remove headers from args.
				unset( $args['headers'] );

				// Log the response without heavy payloads.
				$safe_args = $this->sanitize_for_log( $args );
				$err_part  = print_r( $data['errors'], true );
				$log_msg   = $err_part . " Data: \n" . print_r( [
					'method'   => $method,
					'resource' => $resource,
					'args'     => $safe_args,
				], true );
				Util::debug_log( $this->truncate_log_message( $log_msg ) );

				throw new \Exception( print_r( $data['errors'], true ), $code );
			}

			throw new \Exception( __( 'Something went wrong with the request. Code: ' . $code, 'simply-static-pro' ), $code );
		}

		return $data;
	}

	/**
	 * Updating rate limits after each call.
	 *
	 * @param $response
	 *
	 * @return array
	 */
	protected function update_rate_limits( $response ) {
		$headers = wp_remote_retrieve_headers( $response );
		$limit_headers = [
			'x-ratelimit-limit',
			'x-ratelimit-remaining',
			'x-ratelimit-used',
			'x-ratelimit-reset',
			'x-ratelimit-resource',
		];

		$limits  = [];

		foreach ( $limit_headers as $limit_key ) {
			if ( empty( $headers[ $limit_key ] ) ) {
				continue;
			}

			$key = trim( str_replace( 'x-ratelimit-', '', $limit_key ) );
			$limits[ $key ] = $headers[ $limit_key ];
		}

		update_option( 'simply_static_pro_github_rate_limits', $limits );

		return $limits;
	}

	/**
	 * Is this error code.
	 *
	 * @param string|int $code Code.
	 *
	 * @return bool
	 */
	protected function is_error_code( $code ) {
		return absint( $code ) >= 400;
	}

	/**
	 * Is this a redirect code.
	 *
	 * @param string|int $code Code.
	 *
	 * @return bool
	 */
	protected function is_redirect_code( $code ) {
		return absint( $code ) >= 300 && absint( $code ) < 400;
	}

	public function get( $resource, $headers = [] ) {

		$headers = wp_parse_args( $headers, $this->get_headers() );
		$args    = apply_filters(
			'ss_remote_get_args',
			array(
				'headers'     => $headers,
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0, // disable redirection.
				'blocking'    => true // do not execute code until this call is complete.
			)
		);

		$resp = wp_remote_get( $this->get_url( $resource ), $args );

		return $this->prepare_response( $resp, 'get', $args, $resource );
	}

	public function post( $resource, $body = [], $headers = [] ) {

		$headers = wp_parse_args( $headers, $this->get_headers() );
		$args    = apply_filters(
			'ss_remote_get_args',
			array(
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0, // disable redirection.
				'blocking'    => true // do not execute code until this call is complete.
			)
		);

		$resp = wp_remote_post( $this->get_url( $resource ), $args );

		return $this->prepare_response( $resp, 'post', $args, $resource );
	}

	public function patch( $resource, $body = [], $headers = [] ) {
		$headers = wp_parse_args( $headers, $this->get_headers() );
		$args    = apply_filters(
			'ss_remote_get_args',
			array(
				'method'      => 'PATCH',
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0, // disable redirection.
				'blocking'    => true // do not execute code until this call is complete.
			)
		);

		$resp = wp_remote_request( $this->get_url( $resource ), $args );

		$this->prepare_response( $resp, 'patch', $args, $resource );

		return true;
	}

	public function put( $resource, $body = [], $headers = [] ) {
		$headers = wp_parse_args( $headers, $this->get_headers() );
		$args    = apply_filters(
			'ss_remote_get_args',
			array(
				'method'      => 'PUT',
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0, // disable redirection.
				'blocking'    => true // do not execute code until this call is complete.
			)
		);

		$resp = wp_remote_request( $this->get_url( $resource ), $args );

		$this->prepare_response( $resp, 'put', $args, $resource );

		return true;
	}

	public function delete( $resource, $body = [], $headers = [] ) {
		$headers = wp_parse_args( $headers, $this->get_headers() );
		$data    = apply_filters(
			'ss_remote_get_args',
			array(
				'method'      => 'DELETE',
				'headers'     => $headers,
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0, // disable redirection.
				'blocking'    => true // do not execute code until this call is complete.
			)
		);

		if ( $body ) {
			$data['body'] = wp_json_encode( $body );
		}

		$resp = wp_remote_request( $this->get_url( $resource ), $data );

		$this->prepare_response( $resp, 'delete', $data, $resource );

		return true;
	}
}