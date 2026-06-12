<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

final class Persian_Woocommerce_Zibal_Block extends AbstractPaymentMethodType {
	/**
	 * This property references to the payment method.
	 *
	 * @var string
	 */
	protected $name = 'wc_zibal';

	/**
	 * Initializes the payment method.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
	}

	/**
	 * Checks if payment method is active, or not!
	 *
	 * If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for the payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		wp_register_script(
			'pw-wc_zibal-block',
			PW()->plugin_url( 'assets/js/zibal-block.js' ),
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			],
			PW_VERSION,
			true
		);

		return [ 'pw-wc_zibal-block' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script client side.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'icon'        => PW()->plugin_url( '/assets/images/zibal.png' ),
			'supports'    => $this->get_supported_features(),
		];
	}


}