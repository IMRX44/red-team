<?php

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_payment_gateways', function ( $methods ) {

	$methods[] = Persian_Woocommerce_Zibal::class;

	return array_filter( $methods, fn( $value ) => $value != 'WC_Zibal' );
}, 20 );

add_action( 'after_plugin_row_zibal-payment-gateway-for-woocommerce/index.php', function ( $plugin_file, $plugin_data, $status ) {
	echo '<tr class="inactive"><td>&nbsp;</td><td colspan="2">
        	<div class="notice inline notice-warning notice-alt"><p>افزونه «<strong>درگاه پرداخت زیبال برای فروشگاه ساز ووکامرس</strong>» درون بسته ووکامرس فارسی وجود دارد و نیاز به فعال سازی نیست. به صفحه <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">ووکامرس > پیکربندی > تسویه حساب</a> مراجعه کنید.</p></div>
        	</td>
        </tr>';
}, 10, 3 );

class Persian_Woocommerce_Zibal extends WC_Payment_Gateway {

	/**
	 * Maximum retries in zibal api connection
	 * @const int
	 */
	protected const MAX_RETRIES = 3;

	/**
	 * @var string
	 */
	protected string $merchant;

	/**
	 * @var string
	 */
	protected string $match_mobile_card;

	/**
	 * @var string
	 */
	protected string $non_iran_host;

	/**
	 * @var string
	 */
	protected string $success_massage;

	/**
	 * @var string
	 */
	protected string $failed_massage;

	public function __construct() {

		$this->id                 = 'wc_zibal';
		$this->method_title       = __( 'پرداخت زیبال', 'woocommerce' );
		$this->method_description = __( 'تنظیمات درگاه پرداخت زیبال برای افزونه فروشگاه ساز ووکامرس', 'woocommerce' );
		$this->icon               = apply_filters( 'woocommerce_ir_gateway_zibal_icon', PW()->plugin_url( 'assets/images/zibal.png' ) );
		$this->has_fields         = false;

		$this->init_form_fields();
		$this->init_settings();

		$this->title             = $this->settings['title'];
		$this->description       = $this->settings['description'];
		$this->merchant          = strval( $this->settings['merchantcode'] );
		$this->match_mobile_card = $this->get_option( 'match_mobile_card', 'no' );
		$this->non_iran_host     = $this->get_option( 'non_iran_host', 'no' );
		$this->success_massage   = $this->settings['success_massage'];
		$this->failed_massage    = $this->settings['failed_massage'];

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options',
		] );

		add_action( 'woocommerce_api_' . $this->id, [ $this, 'webhook' ] );

	}

	public function init_form_fields() {
		$this->form_fields = [
			'base_confing'      => [
				'title'       => __( 'تنظیمات پایه ای', 'woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'           => [
				'title'       => __( 'فعالسازی/غیرفعالسازی', 'woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'فعالسازی درگاه زیبال', 'woocommerce' ),
				'description' => __( 'برای فعالسازی درگاه پرداخت زیبال باید چک باکس را تیک بزنید', 'woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'title'             => [
				'title'       => __( 'عنوان درگاه', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce' ),
				'default'     => __( 'پرداخت امن زیبال', 'woocommerce' ),
				'desc_tip'    => true,
			],
			'description'       => [
				'title'       => __( 'توضیحات درگاه', 'woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce' ),
				'default'     => __( 'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'woocommerce' ),
			],
			'account_confing'   => [
				'title'       => __( 'تنظیمات حساب زیبال', 'woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'merchantcode'      => [
				'title'       => __( 'مرچنت کد', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'مرچنت کد درگاه زیبال - برای تست می‌توانید از مرچنت zibal استفاده کنید', 'woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'match_mobile_card' => [
				'title'       => 'تطبیق شماره موبایل با کارت',
				'type'        => 'checkbox',
				'label'       => 'تطبیق شماره موبایل با کارت',
				'description' => 'در صورت تمایل به تطبیق مالک شماره موبایل و مالک کارت تیک بزنید.',
				'default'     => 'no',
			],
			'non_iran_host'     => [
				'title'       => 'هاست خارج از ایران',
				'type'        => 'checkbox',
				'label'       => 'هاست خارج از ایران',
				'description' => 'در صورتی که هاست میزبانی شما خارج از ایران است، جهت اتصال بهتر تیک بزنید.',
				'default'     => 'no',
			],
			'payment_confing'   => [
				'title'       => __( 'تنظیمات عملیات پرداخت', 'woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'success_massage'   => [
				'title'       => __( 'پیام پرداخت موفق', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) زیبال استفاده نمایید.', 'woocommerce' ),
				'default'     => __( 'با تشکر از شما. سفارش شما با موفقیت پرداخت شد.', 'woocommerce' ),
			],
			'failed_massage'    => [
				'title'       => __( 'پیام پرداخت ناموفق', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید. این دلیل خطا از سایت زیبال ارسال می‌گردد.', 'woocommerce' ),
				'default'     => __( 'پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.', 'woocommerce' ),
			],
		];
	}

	/**
	 * Gateway is available when merchant code exists.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( empty( $this->merchant ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$callback_url = add_query_arg( [
			'wc_order' => $order_id,
			'secure'   => md5( $order_id . NONCE_SALT ),
		], WC()->api_request_url( $this->id ) );

		$mobile = $order->get_billing_phone();

		if ( ! empty( $mobile ) ) {

			$mobile = trim( str_replace( ' ', '', $mobile ) );
			$mobile = preg_replace( '/^(\+980|\+98|98|098|0098|0)(\d{10})$/', '0$2', $mobile );
			if ( strlen( $mobile ) == 10 ) {
				$mobile = '0' . $mobile;
			}
		}

		$products = [];

		foreach ( $order->get_items() as $item ) {

			$name       = $item->get_name();
			$qty        = $item->get_quantity();
			$products[] = "{$name} ({$qty})";

		}

		$products    = implode( ' - ', $products );
		$full_name   = $order->get_formatted_billing_full_name();
		$description = "خریدار: {$full_name} | محصولات: {$products}";

		$data = [
			'merchant'    => $this->merchant,
			'amount'      => $this->get_order_total_rial( $order ),
			'orderId'     => strval( $order->get_id() ),
			'callbackUrl' => $callback_url,
			'description' => $description,
			'mobile'      => $mobile,
			'reseller'    => 'woocommerce',
		];

		if ( $data['mobile'] && $this->match_mobile_card == 'yes' ) {
			$data['checkMobileWithCard'] = true;
		}

		$result = $this->send_request( 'request', $data );

		if ( is_array( $result ) && $result['result'] === 100 ) {
			return [
				'result'   => 'success',
				'redirect' => sprintf( 'https://gateway.zibal.%s/start/%s', $this->get_tld(), $result['trackId'] ),
			];
		}

		// The $result['result'] was not 100
		if ( is_array( $result ) ) {
			$message = 'تراکنش ناموفق بود - کد خطا: ' . $result["result"];
		} else {
			$message = 'اتصال به زیبال ناموفق بود.';
		}

		$note = sprintf( __( 'خطا در هنگام ارسال به بانک: %s', 'woocommerce' ), $message );
		$order->add_order_note( $note );
		$notice = sprintf( __( 'در هنگام اتصال به بانک خطای زیر رخ داده است: <br/>%s', 'woocommerce' ), $message );
		wc_add_notice( $notice, 'error' );

		return [];
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public function get_order_total_rial( WC_Order $order ): int {

		$currency = $order->get_currency();
		$amount   = intval( $order->get_total() );

		$currency = strtolower( $currency );

		if ( $currency == 'irt' ) {
			$amount *= 10;
		} elseif ( $currency == 'irht' ) {
			$amount *= 10_000;
		} elseif ( $currency == 'irhr' ) {
			$amount *= 1_000;
		}

		return $amount;
	}

	/**
	 * @param string $action
	 * @param array  $params
	 * @param int    $attempt
	 *
	 * @return ?array
	 */
	public function send_request( string $action, array $params, int $attempt = 0 ): ?array {

		try {

			$url = sprintf( 'https://gateway.zibal.%s/v1/%s', $this->get_tld(), $action );

			$curl = curl_init( $url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLINFO_HEADER_OUT, true );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $params ) );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json'
			] );

			$response = curl_exec( $curl );
			$error    = curl_error( $curl );

			curl_close( $curl );

			if ( $error ) {
				throw  new Exception( $error );
			}

			return json_decode( $response, true, 512, JSON_THROW_ON_ERROR );

		} catch ( Exception $e ) {

			error_log( 'PW Zibal Exception: ' . $e->getMessage() );

			if ( $attempt < self::MAX_RETRIES ) {
				return $this->send_request( $action, $params, $attempt + 1 );
			} else {
				return null;
			}

		}
	}

	public function webhook() {

		$order_id = intval( $_GET['wc_order'] ?? 0 );
		$success  = intval( $_GET['success'] ?? 0 );
		$track_id = sanitize_text_field( $_GET['trackId'] ?? 0 );
		$secure   = sanitize_text_field( $_GET['secure'] ?? 0 );

		if ( ! $order_id ) {

			$fault  = __( 'شماره سفارش وجود ندارد.', 'woocommerce' );
			$notice = wpautop( wptexturize( $this->failed_massage ) );
			$notice = str_replace( "{fault}", $fault, $notice );

			wc_add_notice( $notice, 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;

		}

		$hash = md5( $order_id . NONCE_SALT );

		if ( $secure != $hash ) {
			wp_die( 'کلید امنیتی معتبر نمی‌باشد.' );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order->needs_payment() ) {

			$track_id = $order->get_transaction_id();
			$notice   = wpautop( wptexturize( $this->success_massage ) );
			$notice   = str_replace( "{transaction_id}", $track_id, $notice );

			wc_add_notice( $notice );
			wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
			exit;
		}

		if ( $success !== 1 ) {
			$fault   = '';
			$message = 'تراکنش لغو شد.';
			$this->handle_failed_payment( $order, $message, $track_id, $fault );
			exit;
		}

		$data = [
			'merchant'           => $this->merchant,
			'trackId'            => $track_id,
			'dataOnDoubleVerify' => true,
		];

		$result = $this->send_request( 'verify', $data );

		if ( ! is_array( $result ) || ! isset( $result['result'] ) ) {
			// Zibal server connection failed
			$fault   = 'اتصال به زیبال ناموفق بود.';
			$message = 'تراکنش ناموفق بود.';
			$this->handle_failed_payment( $order, $message, $track_id, $fault );
			exit;
		}

		if ( in_array( $result['result'], [ 100, 201 ] ) &&
		     $result['amount'] == $this->get_order_total_rial( $order ) &&
		     $result['orderId'] == $order->get_id()
		) {
			// Successful payment
			$card_number = $result['cardNumber'] ?? '';
			$ref_number  = $result['refNumber'] ?? '';
			$this->handle_successful_payment( $order, $track_id, $card_number, $ref_number );
			exit;
		}

		// If none of the return code (100, 201) matched, handle the failed payment
		$fault   = $result['result'];
		$message = 'تراکنش ناموفق بود.';
		$this->handle_failed_payment( $order, $message, $track_id, $fault );
		exit;

	}

	private function get_tld(): string {
		return $this->non_iran_host == 'yes' ? 'io' : 'ir';
	}

	/**
	 * Handle failed payment by adding notices, order notes, and redirecting the user
	 *
	 * @param WC_Order $order    The WooCommerce order object
	 * @param string   $message  The failure message to be shown
	 * @param string   $track_id The transaction ID (if available)
	 * @param string   $fault    The failure reason or fault message
	 *
	 * @return void
	 */
	private function handle_failed_payment( WC_Order $order, string $message = '', string $track_id = '', string $fault = '' ): void {

		$track_id_display = ( $track_id && $track_id != 0 ) ? ( '<br/>توکن: ' . $track_id ) : '';
		$note             = sprintf( __( 'خطا در هنگام بازگشت از بانک: %s %s', 'woocommerce' ), $message, $track_id_display );
		$order->add_order_note( $note, 1 );

		$notice = wpautop( wptexturize( $this->failed_massage ) );
		$notice = str_replace( "{transaction_id}", $track_id_display, $notice );
		$notice = str_replace( "{fault}", $message, $notice );
		wc_add_notice( $notice, 'error' );

		do_action( 'WC_Zibal_Return_from_Gateway_Failed', $order->get_id(), $track_id, $fault );

		wp_safe_redirect( wc_get_checkout_url() );
	}

	/**
	 * Handle successful payment by updating the order and redirecting the user
	 *
	 * @param WC_Order $order       The WooCommerce order object
	 * @param string   $track_id    The transaction ID
	 * @param string   $card_number The card number used for payment
	 * @param string   $ref_number  The reference number for the transaction
	 *
	 * @return void
	 */
	private function handle_successful_payment( WC_Order $order, string $track_id = '', string $card_number = '', string $ref_number = '' ): void {

		$order->set_transaction_id( strval( $track_id ) );
		$order->update_meta_data( 'zibal_payment_card_number', $card_number );
		$order->update_meta_data( 'zibal_payment_ref_number', $ref_number );
		$order->save_meta_data();

		$order->payment_complete( $track_id );

		WC()->cart->empty_cart();

		$lines = [
			sprintf( __( 'پرداخت موفقیت آمیز بود.<br/> کد رهگیری: %s', 'woocommerce' ), $track_id ),
			sprintf( __( ' شماره کارت پرداخت کننده: %s', 'woocommerce' ), $card_number ),
			sprintf( __( ' شماره مرجع: %s', 'woocommerce' ), $ref_number ),
		];

		$note = implode( '<br/>', $lines );
		$order->add_order_note( $note, 1 );

		$notice = wpautop( wptexturize( $this->success_massage ) );
		$notice = str_replace( "{transaction_id}", $track_id, $notice );
		wc_add_notice( $notice );

		do_action( 'WC_Zibal_Return_from_Gateway_Success', $order->get_id(), $track_id );

		wp_safe_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
	}
}
