<?php

defined( 'ABSPATH' ) || exit;

class PW_Super_Admin {

	// @todo add custom url for blocking
	// @todo add notice in plugin, theme, core update pages
	private $blocked_url = [];

	public function __construct() {
		global $pagenow;

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'super-admin/super-admin.php' ) ) {
			return;
		}

		add_filter( 'PW_Tools_tabs', [ $this, 'tabs' ] );
		add_filter( 'PW_Tools_settings', [ $this, 'settings' ] );

		// Woocommerce.com - Not working and useful in IRAN
		if ( 'yes' === get_option( 'woocommerce_allow_tracking', 'no' ) ) {
			update_option( 'woocommerce_allow_tracking', 'no', 'yes' );
		}

		add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
		// Woocommerce.com

		if ( PW()->get_options( 'super_admin_boost_woo', 'yes' ) == 'yes' ) {

			add_action( 'admin_menu', function () {
				foreach ( get_post_types( '', 'names' ) as $post_type ) {
					remove_meta_box( 'postcustom', $post_type, 'normal' );
				}
			} );

			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/1.0/featured']                                        = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/2.0/featured']                                        = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/3.0/featured']                                        = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/1.0/search']                                          = '{}';
			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/1.0/categories']                                      = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom-extensions/3.0/promotions']                                      = '[]';
			$this->blocked_url['woocommerce.com/wp-json/helper/1.0/update-check-public']                                       = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom/obw-free-extensions/3.0/extensions.json']                        = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom/payment-gateway-suggestions/1.0/payment-method/promotions.json'] = '[]';
			$this->blocked_url['woocommerce.com/wp-json/wccom/payment-gateway-suggestions/1.0/suggestions.json']               = '[]';
		}

		if ( PW()->get_options( 'super_admin_boost_dashboard', 'yes' ) == 'yes' ) {
			$this->blocked_url['api.wordpress.org/core/browse-happy/1.1'] = '[]';
			$this->blocked_url['api.wordpress.org/core/serve-happy/1.0']  = '[]';
		}

		// 403 URLs in IRAN
		$this->blocked_url['my.elementor.com/api/v2/info']     = '[]';
		$this->blocked_url['my.elementor.com/api/v2/pro/info'] = '[]';
		$this->blocked_url['yithemes.com/latest-updates/feed'] = '[]';
		$this->blocked_url['yithemes.com/feed']                = '[]';
		$this->blocked_url['jetpack.wordpress.com/xmlrpc.php'] = '[]';
		$this->blocked_url['rankmath.com/wp-json/wp/v2/posts'] = '[]';

		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 1000, 3 );
	}

	public function tabs( array $tabs ): array {

		$tabs['super_admin'] = 'سوپر ادمین';

		return $tabs;
	}

	public function settings( array $settings ): array {

		$settings['super_admin'] = [
			[
				'title' => 'تنظیمات عمومی',
				'type'  => 'title',
				'id'    => 'super_admin_general',
			],
			[
				'title'   => 'افزایش سرعت ووکامرس',
				'id'      => 'PW_Options[super_admin_boost_woo]',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => 'بهبود سرعت هسته و سفارشات ووکامرس',
			],
			[
				'title'   => 'افزایش سرعت پیشخوان',
				'id'      => 'PW_Options[super_admin_boost_dashboard]',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => 'افزایش سرعت آنالیزهای پیشخوان وردپرس',
			],

			[
				'type' => 'sectionend',
				'id'   => 'super_admin_general',
			],
		];

		return $settings;
	}

	public function __return_null() {
		return null;
	}

	public function pre_http_request( $preempt, $parsed_args, $url ) {

		$url = trim( parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH ), '/' );

		if ( array_key_exists( $url, $this->blocked_url ) ) {
			return [
				'headers'       => [],
				'body'          => $this->blocked_url[ $url ],
				'response'      => [
					'code'    => 200,
					'message' => false,
				],
				'cookies'       => [],
				'http_response' => null,
			];
		}

		return $preempt;
	}
}

new PW_Super_Admin();
