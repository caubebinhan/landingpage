<?php

namespace simply_static_pro\form\form_entries\rest;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest {

	protected $route = '';

	protected $availableRoutes = [
		'getItem',
		'getItems',
		'createItem',
		'updateItem',
		'deleteItem',
	];

	public function register_routes() {
		add_action( 'rest_api_init', function () {
			foreach ( $this->availableRoutes as $route ) {
				$method = 'register' . ucfirst( $route ) . 'Route';
				if ( method_exists( $this, $method ) ) {
					$this->{$method}();
				}
			}
		} );
	}

	public function registerRoute( $methods, $callback, $permissionCallback = null, $route = null, $options = [] ) {
		$routePath = [ $this->route ];
		if ( $route ) {
			$routePath[] = $route;
		}

		if ( ! $permissionCallback ) {
			$permissionCallback = [ $this, 'verifyRequest' ];
		}

		$args = array(
			'methods'             => $methods,
			'show_in_index'       => false,
			'permission_callback' => $permissionCallback,
			'callback'            => $callback,
		);

		if ( ! empty( $options ) ) {
			$args = wp_parse_args( $options, $args );
		}

		$namespaces = [ 'simplystatic/v1', 'static-studio/v1' ];
		foreach ( $namespaces as $ns ) {
			register_rest_route( $ns, '/' . implode( '/', $routePath ), $args );
		}
	}

	public function registerGetItemRoute( $options = [] ) {
		$this->registerRoute( \WP_REST_Server::READABLE, [ $this, 'getItem' ], [
			$this,
			'verifyGetPermission'
		], '(?P<id>\d+)', $options );
	}

	public function registerGetItemsRoute( $options = [] ) {
		$this->registerRoute( \WP_REST_Server::READABLE, [ $this, 'getItems' ], [
			$this,
			'verifyGetItemsPermission'
		], null, $options );
	}

	public function registerCreateItemRoute( $options = [] ) {
		$this->registerRoute( \WP_REST_Server::CREATABLE, [ $this, 'createItem' ], [
			$this,
			'verifyCreateItemPermission'
		], null, $options );
	}

	public function registerDeleteItemRoute( $options = [] ) {
		$this->registerRoute( \WP_REST_Server::DELETABLE, [ $this, 'deleteItem' ], [
			$this,
			'verifyDeleteItemPermission'
		], '(?P<id>\d+)', $options );
	}

	public function registerUpdateItemRoute( $options = [] ) {
		$this->registerRoute( \WP_REST_Server::EDITABLE, [ $this, 'updateItem' ], [
			$this,
			'verifyUpdateItemPermission'
		], '(?P<id>\d+)', $options );
	}

	public function getItems( \WP_REST_Request $request ) {
	}

	public function getItem( \WP_REST_Request $request ) {
	}

	public function createItem( \WP_REST_Request $request ) {
	}

	public function deleteItem( \WP_REST_Request $request ) {
	}

	public function updateItem( \WP_REST_Request $request ) {
	}

	public function verifyGetItemsPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function verifyGetPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function verifyCreateItemPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function verifyDeleteItemPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function verifyUpdateItemPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function verifyRequest( \WP_REST_Request $request ) {
		return true;
	}
}
