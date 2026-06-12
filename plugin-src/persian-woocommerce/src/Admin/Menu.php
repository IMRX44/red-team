<?php

namespace PersianWooCommerce\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 20 );
	}

	public function admin_menu() {
		$capability = apply_filters( 'persian-woocommerce/reports/menu_capability', 'manage_options' );

		add_menu_page( 'گزارشات', 'گزارشات', $capability, 'persian-woocommerce-revenue-report', null, PW_URL . 'assets/images/chart-bar.svg', '55.7' );

		$submenus = [
			10 => [
				'title'      => 'درآمد',
				'capability' => $capability,
				'slug'       => 'persian-woocommerce-revenue-report',
				'callback'   => function () {
					include PW_DIR . '/templates/reports/revenue.php';
				},
			],
			20 => [
				'title'      => 'انبار',
				'capability' => $capability,
				'slug'       => 'persian-woocommerce-stock-report',
				'callback'   => function () {
					include PW_DIR . '/templates/reports/stock.php';
				},
			],
			30 => [
				'title'      => 'مشتریان',
				'capability' => $capability,
				'slug'       => 'persian-woocommerce-customers-report',
				'callback'   => function () {
					include PW_DIR . '/templates/reports/customers.php';
				},
			],
			40 => [
				'title'      => 'پیکربندی',
				'capability' => $capability,
				'slug'       => 'persian-woocommerce-report-settings',
				'callback'   => function () {
					wp_safe_redirect( admin_url( 'admin.php?page=wc-admin&path=/analytics/settings' ) );
					exit;
				},
			]
		];

		$submenus = apply_filters( 'persian-woocommerce/reports/submenus', $submenus );

		foreach ( $submenus as $submenu ) {
			add_submenu_page( 'persian-woocommerce-revenue-report', $submenu['title'], $submenu['title'], $submenu['capability'], $submenu['slug'], $submenu['callback'] );
		}

	}


}
