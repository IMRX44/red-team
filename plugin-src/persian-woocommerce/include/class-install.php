<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'PW_Install' ) ) {
	return;
}

class PW_Install {

	public function __construct() {
		add_action( 'activated_plugin', [ $this, 'set_legacy_checkout_page' ], 10, 2 );
	}


	/**
	 * Set WooCommerce legacy (shortcode) checkout page on
	 * WooCommerce and Persian WooCommerce activation.
	 *
	 * @param string $plugin       Path to the plugin file relative to the Plugins directory.
	 * @param bool   $network_wide Whether to enable the plugin for all sites in the network
	 *                             or just the current site. Multisite only. Default false.
	 */
	public function set_legacy_checkout_page( string $plugin, bool $network_wide ): void {
		// Action will trigger based on these plugins
		$target_plugins = [ 'woocommerce/woocommerce.php', 'persian-woocommerce/woocommerce-persian.php' ];

		if ( ! in_array( $plugin, $target_plugins ) ) {
			return;
		}

		$checkout_page_id = wc_get_page_id( 'checkout' );

		if ( - 1 == $checkout_page_id ) {
			return;
		}

		$current_content = get_post_field( 'post_content', $checkout_page_id );

		if ( '' === $current_content ) {
			return;
		}

		// Check if the legacy shortcode is missing and replace the content
		if ( str_contains( $current_content, '[woocommerce_checkout]' ) ) {
			return;
		}

		// Define the new content (the shortcode to insert) and replace with checkout block
		$new_content     = '[woocommerce_checkout]';
		$updated_content = preg_replace( '/<!-- wp:woocommerce\/checkout -->(.*?)<!-- \/wp:woocommerce\/checkout -->/s', $new_content, $current_content );

		// Update the page to use the legacy shortcode
		wp_update_post( [
			'ID'           => $checkout_page_id,
			'post_content' => $updated_content,
		] );

	}

}

new PW_Install();
