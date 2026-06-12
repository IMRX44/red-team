<?php

namespace PersianWooCommerce\API;

use PersianWooCommerce\Services\ReportService;
use WP_REST_Request;
use WP_REST_Server;

class ReportAPI extends RestAPI {

	public ReportService $service;

	public function __construct() {
		parent::__construct();

		$this->service = new ReportService();
	}

	public function register_routes() {
		/**
		 * Revenue
		 */
		register_rest_route( 'persian-woocommerce/reports', 'revenue/debug', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_debug_report' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => $this->date_args(),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'revenue/summary', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => $this->date_args(),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'revenue/orders', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->date_args(), $this->paginate_args() ),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'revenue/chart', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => $this->date_args(),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'revenue/top-sellers', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => $this->date_args(),
		] );

		/**
		 * Customers
		 */
		register_rest_route( 'persian-woocommerce/reports', 'customer/summary', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->date_args(), $this->paginate_args() ),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'customer/chart', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => $this->date_args(),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'customer/users', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->date_args(), $this->paginate_args(), $this->list_args() ),
		] );

		/**
		 * Stock
		 */
		register_rest_route( 'persian-woocommerce/reports', 'stock/summary', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->stock_args() ),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'stock/products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->stock_args(), $this->paginate_args() ),
		] );

		register_rest_route( 'persian-woocommerce/reports', 'stock/export', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_reports' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => array_merge( $this->stock_args() ),
		] );
	}

	public function permission_callback( WP_REST_Request $request ): bool {

		if ( ( 'yes' !== get_option( "woocommerce_custom_orders_table_enabled" ) ) ) {
			self::response( false, 'برای استفاده از گزارشات ووکامرس فارسی، قابلیت HPOS ووکامرس را فعال نمایید.' );
		}

		if ( 'yes' !== get_option( 'woocommerce_analytics_enabled' ) ) {
			update_option( 'woocommerce_analytics_enabled', 'yes' );
		}

		return parent::permission_callback( $request );
	}

	private function stock_args(): array {
		return [
			'status' => [
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Retrieve products with specific stock status.',
				'validate_callback' => fn( $value ) => in_array( $value, [
					'instock',
					'outofstock',
					'lowstock',
					'onbackorder',
				], true ),
				'sanitize_callback' => fn( $value ) => strtolower( sanitize_text_field( $value ) ),
			],
		];
	}

	private function date_args(): array {
		return [
			'from_date' => [
				'required'          => true,
				'type'              => 'integer',
				'description'       => 'Start date (Unix timestamp, Y-m-d 00:00:00)',
				'validate_callback' => fn( $value ) => is_numeric( $value ) && intval( $value ) > 0,
				'sanitize_callback' => fn( $value ) => intval( $value ),
			],
			'to_date'   => [
				'required'          => true,
				'type'              => 'integer',
				'description'       => 'End date (Unix timestamp, Y-m-d 23:59:59)',
				'validate_callback' => fn( $value ) => is_numeric( $value ) && intval( $value ) > 0,
				'sanitize_callback' => fn( $value ) => intval( $value ),
			],
			'interval'  => [
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Batch interval: day, week, month, quarter, year',
				'validate_callback' => fn( $value ) => in_array( $value, [
					'day',
					'week',
					'month',
					'quarter',
					'year',
				], true ),
				'sanitize_callback' => fn( $value ) => sanitize_text_field( $value ),
			],
		];
	}

	private function paginate_args(): array {
		return [
			'page'     => [
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
			],
			'per_page' => [
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 25,
				'sanitize_callback' => 'absint',
				'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
			],
		];
	}

	private function list_args(): array {
		return [
			'orderby' => [
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Sort collection by a specific attribute.',
				'validate_callback' => fn( $value ) => sanitize_text_field( $value ),
				'sanitize_callback' => fn( $value ) => sanitize_text_field( $value ),
			],
			'order'   => [
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Order sort attribute ascending or descending.',
				'validate_callback' => fn( $value ) => in_array( strtolower( $value ), [ 'asc', 'desc' ], true ),
				'sanitize_callback' => fn( $value ) => strtolower( sanitize_text_field( $value ) ),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	private function prepare_dates( WP_REST_Request $request ): array {
		$from_date = $request->get_param( 'from_date' );
		$to_date   = $request->get_param( 'to_date' );

		$timezone = wp_timezone_string();

		$from_date = verta()
			->timestamp( $from_date )
			->timezone( $timezone )
			->startDay();

		$to_date = verta()
			->timestamp( $to_date )
			->timezone( $timezone )
			->endDay();

		if ( $from_date->gt( $to_date ) ) {
			throw new \Exception( 'تاریخ شروع نباید پس از تاریخ پایان باشد.' );
		}

		return [ $from_date, $to_date ];
	}


	public function handle_debug_report( WP_REST_Request $request ) {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );
		$interval = sanitize_text_field( $request->get_param( 'interval' ) );

		$data = $this->service->debug_revenue( $start_date, $end_date, $interval );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.', [] );
		}

		self::response( true, '' );
	}

	public function handle_reports( WP_REST_Request $request ) {
		$allowed = [
			'revenue/summary',
			'revenue/top-sellers',
			'revenue/orders',
			'revenue/chart',

			'customer/summary',
			'customer/users',
			'customer/chart',

			'stock/summary',
			'stock/products',
			'stock/export',
		];

		$endpoint = str_replace( 'persian-woocommerce/reports/', '', trim( $request->get_route(), '/' ) );

		if ( ! in_array( $endpoint, $allowed, true ) ) {
			self::response( false, 'مسیر درخواستی شما وجود ندارد.' );
		}

		$method = str_ireplace( [ '-', '/' ], '_', $endpoint ) . '_report';

		if ( ! method_exists( $this, $method ) ) {
			self::response( false, 'شیوه اجرای عملیات دریافت اطلاعات وجود ندارد.' );
		}

		try {
			/** @var array $data */
			$data = $this->{$method}( $request );
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function revenue_summary_report( WP_REST_Request $request ): ?array {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );

		$data = $this->service->revenue_summary( $start_date, $end_date );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function revenue_orders_report( WP_REST_Request $request ): ?array {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );
		$interval = sanitize_text_field( $request->get_param( 'interval' ) );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$data = $this->service->revenue_orders( $start_date, $end_date, $interval, $per_page, $page );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function revenue_chart_report( WP_REST_Request $request ): ?array {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );
		$interval = sanitize_text_field( $request->get_param( 'interval' ) );

		$data = $this->service->revenue_chart( $start_date, $end_date, $interval );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function revenue_top_sellers_report( WP_REST_Request $request ): ?array {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );

		$data = $this->service->revenue_top_sellers( $start_date, $end_date );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function customer_summary_report( WP_REST_Request $request ) {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );
		$interval = sanitize_text_field( $request->get_param( 'interval' ) );

		$data = $this->service->customer_summary( $start_date, $end_date, $interval );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function customer_chart_report( WP_REST_Request $request ) {
		[ $start_date, $end_date ] = $this->prepare_dates( $request );
		$interval = sanitize_text_field( $request->get_param( 'interval' ) );

		$data = $this->service->customer_chart( $start_date, $end_date, $interval );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function customer_users_report( WP_REST_Request $request ) {

		[ $start_date, $end_date ] = $this->prepare_dates( $request );

		$per_page = intval( $request->get_param( 'per_page' ) );
		$page     = intval( $request->get_param( 'page' ) );
		$orderby  = $request->get_param( 'orderby' );
		$order    = $request->get_param( 'order' );

		$data = $this->service->customer_users( $start_date, $end_date, $per_page, $page, $orderby, $order );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function stock_summary_report( WP_REST_Request $request ) {
		$data = $this->service->stock_summary();

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $this->service->stock_summary() );
	}

	public function stock_products_report( WP_REST_Request $request ) {
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$data     = $this->service->stock_products( $status, $per_page, $page );

		if ( empty( $data ) ) {
			self::response( true, 'داده ای وجود ندارد.' );
		}

		self::response( true, '', $data );
	}

	public function stock_export_report( WP_REST_Request $request ) {
		$this->service->stock_export( $request->get_param( 'status' ) );
	}
}