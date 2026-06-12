<?php

namespace PersianWooCommerce;


use PersianWooCommerce\Admin\Menu;
use PersianWooCommerce\API\ReportAPI;

class PersianWooCommerce {

	public function __construct() {
		new Menu();
		new ReportAPI();
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_alpine' ] );
	}

	public function enqueue_alpine() {
		$suffix = wp_scripts_get_suffix();
		wp_register_script( 'alpine', PW_URL . 'assets/js/alpine' . $suffix . '.js', [], '3.15.0', [ 'strategy' => 'defer' ] );
	}

}