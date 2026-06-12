<?php

defined( 'ABSPATH' ) || exit;

class Persian_Woocommerce_Notice {

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 10 );
		add_action( 'wp_ajax_pw_dismiss_notice', [ $this, 'dismiss_notice' ] );
		add_action( 'wp_ajax_pw_update_notice', [ $this, 'update_notice' ] );
	}

	public function admin_notices() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->is_dismiss( 'all' ) ) {
			return;
		}

		foreach ( $this->notices() as $notice ) {

			if ( ! $notice['condition'] || $this->is_dismiss( $notice['id'] ) ) {
				continue;
			}

			$dismissible    = $notice['dismiss'] ? 'is-dismissible' : '';
			$notice_id      = esc_attr( $notice['id'] );
			$notice_content = strip_tags( $notice['content'], '<p><a><b><img><ul><ol><li><input>' );
			$notice_type    = esc_attr( $notice['type'] ?? 'success' );

			printf( '<div class="notice pw_notice notice-%s %s" id="pw_%s"><p>%s</p></div>', $notice_type, $dismissible, $notice_id, $notice_content );

			break;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                jQuery(document.body).on('click', '.notice-dismiss', function () {

                    let notice = jQuery(this).closest('.pw_notice');
                    notice = notice.attr('id');

                    if (notice !== undefined && notice.indexOf('pw_') !== -1) {

                        notice = notice.replace('pw_', '');

                        jQuery.ajax({
                            url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ) ?>",
                            type: 'post',
                            data: {
                                notice: notice,
                                action: 'pw_dismiss_notice',
                                nonce: "<?php echo wp_create_nonce( 'pw_dismiss_notice' ); ?>"
                            }
                        });
                    }

                });

            });
		</script>
		<?php

		if ( get_transient( 'pw_update_notices' ) ) {
			return;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                jQuery.ajax({
                    url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ) ?>",
                    type: 'post',
                    data: {
                        action: 'pw_update_notice',
                        nonce: '<?php echo wp_create_nonce( 'pw_update_notice' ); ?>'
                    }
                });

            });
		</script>
		<?php
	}

	public function notices(): array {
		global $pagenow;

		$page = sanitize_text_field( $_GET['page'] ?? null );
		$tab  = sanitize_text_field( $_GET['tab'] ?? null );

		if ( wc_shipping_enabled() ) {
			$has_shipping = is_plugin_active( 'persian-woocommerce-shipping/woocommerce-shipping.php' );
		} else {
			$has_shipping = 1;
		}

		$pws_install_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce-shipping' );

		$gateland_install_url = self::get_plugin_action_url( 'gateland/gateland.php' );

		$notices = [
			[
				'id'        => '_gateland',
				'content'   => sprintf( '<b>افزونه درگاه پرداخت هوشمند گیت لند: </b> ۴۱ درگاه مستقیم و غیرمستقیم + درگاه‌های اعتباری، فقط در یک افزونه. <a href="%s" target="_blank"><input type="button" class="button button-primary" value="نصب سریع و رایگان از مخزن وردپرس"></a>',
					$gateland_install_url ),
				'condition' => $gateland_install_url,
				'dismiss'   => 2 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'tapin-orders',
				'content'   => '<b>فرایند ارسال سفارشات را هوشمند کنید! با یک کلیک سایت فروشگاه خود را به چندین شرکت حمل‌ متصل کنید و امور پستی خود را در همین صفحه انجام دهید.</b>
<ul>
<li>- صدور آنلاین بارکد رهگیری و فاکتور</li>
<li>- پیگیری وضعیت مرسولات</li>
</ul>
<a href="' . $pws_install_url . '" target="_blank">
<input type="button" class="button button-primary" value="نصب افزونه">
</a>',
				'condition' => $page == 'wc-orders' && ! $has_shipping,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'tapin-shipping',
				'content'   => '<b>محاسبه و مقایسه هزینه ارسال شرکت‌های حمل مختلف در یک پنل جامع:</b>
<ul>
<li>- فعالسازی ارسال با پست پیشتاز، پست ویژه و تیپاکس</li>
<li>- امکان پرداخت در محل در سراسر کشور (COD)</li>
<li>- پیامک خودکار به مشتری در هر مرحله از ارسال</li>
<li>- تعریف شرط ارسال رایگان بر اساس منطقه جغرافیایی یا سبد خرید مشتری</li>
</ul>
<a href="' . $pws_install_url . '" target="_blank">
<input type="button" class="button button-primary" value="ارسال حرفه‌ای">
</a>',
				'condition' => $page == 'wc-settings' && $tab == 'shipping' && ! $has_shipping,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'tapin-tools',
				'content'   => '
			<a href="https://hits.ir/tapin" target="_blank">
				<img src="' . PW()->plugin_url( 'assets/images/tapin.png' ) . '" style="width: 100%" alt="تاپین">
			</a>',
				'condition' => $page == 'persian-wc-tools' && ! $has_shipping,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'tapin-dashboard',
				'content'   => '<b>فروشگاه خود را به بهترین شرکت‌های حمل کشور متصل کنید و ارسالی بدون دردسر داشته باشید.</b>
<ul>
<li>- اضافه شدن چندین شرکت حمل و نقل به سبد خرید</li>
<li>- صدور آنلاین کد رهگیری و فاکتور</li>
<li>- جمع‌آوری مرسولات از درب فروشگاه</li>
<li>- اطلاع‌رسانی پیامکی به خریدار در هر مرحله</li>
</ul>
<a href="' . $pws_install_url . '" target="_blank">
<input type="button" class="button button-primary" value="شروع">
</a>',
				'condition' => $pagenow == 'index.php' && ! $has_shipping,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'persian-date',
				'content'   => sprintf( 'بنظر میرسه هنوز ووکامرس خودتو شمسی نکردی، از <a href="%s" target="_blank">اینجا</a> و فقط با یک کلیک وردپرس و ووکامرس‌تو شمسی کن :)', admin_url( 'admin.php?page=persian-wc-tools' ) ),
				'condition' => PW()->get_options( 'enable_jalali_datepicker', 'yes' ) !== 'yes',
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'pws',
				'content'   => sprintf( 'بنظر میرسه هنوز حمل و نقل (پست پیشتاز، تیپاکس، پیک موتوری و...) فروشگاه رو پیکربندی نکردید؟ <a href="%s" target="_blank">نصب افزونه حمل و نقل فارسی ووکامرس و پیکربندی.</a>', $pws_install_url ),
				'condition' => ! $has_shipping,
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
			[
				'id'        => 'pw_shipping_plugin',
				'content'   => sprintf( '<b>افزونه رایگان حمل و نقل ووکامرس: </b> به راحتی روش‌های حمل و نقل پست پیشتاز، تیپاکس و پیک موتوری را اضافه کنید و هزینه‌های ارسال را به صورت خودکار محاسبه کنید. <a href="%s" target="_blank">دانلود و نصب رایگان</a>.',
					$pws_install_url ),
				'condition' => ! $has_shipping && $page == 'wc-settings' && $tab == 'shipping',
				'dismiss'   => 6 * MONTH_IN_SECONDS,
			],
		];

		/*Todo: Remove license check in future version, When payment gateways are deprecated*/
		$gateway_license_url = esc_url( add_query_arg( [
			'page' => 'persian-wc-tools',
			'tab'  => 'gateway_license',
		], admin_url( 'admin.php' ) ) );

		$gateways = [
			'saman'         => [
				'name' => 'سامان',
				'file' => 'woocommerce-saman-bank/index.php',
			],
			'irankish'      => [
				'name' => 'ایران کیش',
				'file' => 'Woocommerce_IranKish/index.php',
			],
			'mabnacard_new' => [
				'name' => 'مبناکارت آریا',
				'file' => 'Woocommerce_MabnaCard_New/index.php',
			],
			'mellat'        => [
				'name' => 'به پرداخت ملت',
				'file' => 'Woocommerce_Mellat_new/index.php',
			],
			'melli_new'     => [
				'name' => 'سداد ملی جدید',
				'file' => 'Woocommerce_Melli_new/index.php',
			],
			'parsian_new'   => [
				'name' => 'پارسیان جدید',
				'file' => 'WooCommerce_Parsian_New_IPG/index.php',
			],
			'pasargad'      => [
				'name' => 'پاسارگاد',
				'file' => 'Woocommerce_Pasargad/index.php',
			],
		];

		foreach ( $gateways as $gateway_id => $gateway_info ) {

			if ( is_plugin_inactive( $gateway_info['file'] ) ) {
				continue;
			}

			$key   = PW()->get_options( 'gateway_license_key_' . $gateway_id, '' );
			$email = PW()->get_options( 'gateway_license_email_' . $gateway_id, '' );

			if ( ! empty( $key ) && ! empty( $email ) ) {
				continue;
			}

			$notices[] = [
				'id'        => 'license_' . $gateway_id,
				'content'   => sprintf(
					'افزونه <b>%s</b>: لطفا لایسنس خود را از <a href="%s" target="_blank">اینجا</a> وارد کنید.',
					esc_html( $gateway_info['name'] ),
					$gateway_license_url
				),
				'condition' => $page !== 'persian-wc-tools' && $tab !== 'gateway_license',
				'dismiss'   => 7 * DAY_IN_SECONDS,
				'type'      => 'error',
			];

		}


		$_notices = get_option( 'pw_notices', [] );

		foreach ( $_notices['notices'] ?? [] as $_notice ) {

			$_notice['condition'] = 1;

			$rules = $_notice['rules'];

			if ( isset( $rules['pagenow'] ) && $rules['pagenow'] != $pagenow ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['page'] ) && $rules['page'] != $page ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['tab'] ) && $rules['tab'] != $tab ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['active'] ) && is_plugin_inactive( $rules['active'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['inactive'] ) && is_plugin_active( $rules['inactive'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['has_shipping'] ) && $rules['has_shipping'] != $has_shipping ) {
				$_notice['condition'] = 0;
			}

			unset( $_notice['rules'] );

			array_unshift( $notices, $_notice );
		}

		return $notices;
	}

	public function dismiss_notice() {

		check_ajax_referer( 'pw_dismiss_notice', 'nonce' );

		$this->set_dismiss( sanitize_text_field( $_POST['notice'] ) );

		die();
	}

	public function update_notice() {

		$update = get_transient( 'pw_update_notices' );

		if ( $update ) {
			return;
		}

		set_transient( 'pw_update_notices', 1, HOUR_IN_SECONDS );

		check_ajax_referer( 'pw_update_notice', 'nonce' );

		$notices = wp_remote_get( 'https://woonotice.ir/pw.json', [ 'timeout' => 5, ] );
		$sign    = wp_remote_get( 'https://woohash.ir/pw.hash', [ 'timeout' => 5, ] );

		if ( is_wp_error( $notices ) || is_wp_error( $sign ) ) {
			die();
		}

		if ( ! is_array( $notices ) || ! is_array( $sign ) ) {
			die();
		}

		$notices = trim( $notices['body'] );
		$sign    = trim( $sign['body'] );

		if ( sha1( $notices ) !== $sign ) {
			die();
		}

		$notices = json_decode( $notices, JSON_OBJECT_AS_ARRAY );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			die();
		}

		foreach ( $notices['notices'] as &$_notice ) {

			$doc     = new DOMDocument();
			$content = strip_tags( $_notice['content'], '<p><a><b><img><ul><ol><li>' );
			$content = str_replace( [ 'javascript', 'java', 'script' ], '', $content );
			$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

			foreach ( $doc->getElementsByTagName( '*' ) as $element ) {

				$href  = null;
				$src   = null;
				$style = $element->getAttribute( 'style' );

				if ( $element->nodeName == 'a' ) {
					$href = $element->getAttribute( 'href' );
				}

				if ( $element->nodeName == 'img' ) {
					$src = $element->getAttribute( 'src' );
				}

				foreach ( $element->attributes as $attribute ) {
					$element->removeAttribute( $attribute->name );
				}

				if ( $href && filter_var( $href, FILTER_VALIDATE_URL ) ) {
					$element->setAttribute( 'href', $href );
					$element->setAttribute( 'target', '_blank' );
				}

				if ( $src && filter_var( $src, FILTER_VALIDATE_URL ) && strpos( $src, 'https://woonotice.ir' ) === 0 ) {
					$element->setAttribute( 'src', $src );
				}

				if ( $style ) {
					$element->setAttribute( 'style', $style );
				}
			}

			$_notice['content'] = $doc->saveHTML();
		}

		update_option( 'pw_notices', $notices );

		die();
	}

	public function set_dismiss( $notice_id ) {

		$notices = wp_list_pluck( $this->notices(), 'dismiss', 'id' );

		if ( isset( $notices[ $notice_id ] ) && $notices[ $notice_id ] ) {
			update_option( 'pw_dismiss_notice_' . $notice_id, time() + intval( $notices[ $notice_id ] ), 'yes' );
			update_option( 'pw_dismiss_notice_all', time() + DAY_IN_SECONDS );
		}
	}

	public function is_dismiss( $notice_id ): bool {
		return intval( get_option( 'pw_dismiss_notice_' . $notice_id ) ) >= time();
	}

	public static function get_plugin_action_url( $plugin ): ?string {

		if ( is_plugin_active( $plugin ) ) {
			return null;
		}

		if ( ! isset( get_plugins()[ $plugin ] ) ) {

			$plugin = strtok( $plugin, '/' );

			return wp_nonce_url(
				add_query_arg(
					[
						'action' => 'install-plugin',
						'plugin' => $plugin,
					],
					admin_url( 'update.php' )
				),
				'install-plugin_' . $plugin
			);
		}

		return wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=' . $plugin ),
			'activate-plugin_' . $plugin
		);
	}

}

new Persian_Woocommerce_Notice();