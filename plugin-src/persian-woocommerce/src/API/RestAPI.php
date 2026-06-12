<?php

namespace PersianWooCommerce\API;

use WP_REST_Request;

abstract class RestAPI {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes();

	public function permission_callback( WP_REST_Request $request ): bool {
		return current_user_can( 'view_woocommerce_reports' );
	}

	/**
	 * @param bool        $success
	 * @param string|null $message
	 * @param array       $data
	 *
	 * @return no-return
	 */
	public static function response( bool $success, string $message = null, array $data = [] ) {

		echo json_encode( [
			'success' => $success,
			'message' => $message,
			'data'    => $data,
		] );

		die();
	}

}