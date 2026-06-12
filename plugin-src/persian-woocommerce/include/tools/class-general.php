<?php

defined( 'ABSPATH' ) || exit;

class PW_Tools_General {

	public function __construct() {
		add_filter( 'pre_get_posts', [ $this, 'fix_arabic_characters' ] );

		if ( PW()->get_options( 'fix_postcode_persian_number', 'yes' ) != 'no' ) {
			add_filter( 'woocommerce_checkout_process', [ $this, 'checkout_process_postcode' ], 20, 1 );
		}

		if ( PW()->get_options( 'postcode_validation', 'no' ) != 'no' ) {
			add_filter( 'woocommerce_validate_postcode', [ $this, 'validate_postcode' ], 10, 3 );
		}

		if ( PW()->get_options( 'fix_phone_persian_number', 'yes' ) != 'no' ) {
			add_filter( 'woocommerce_checkout_process', [ $this, 'checkout_process_phone' ], 20, 1 );
		}
	}

	public function fix_arabic_characters( $query ) {

		if ( $query->is_search ) {
			$query->set( 's', str_replace( [ 'ك', 'ي', ], [ 'ک', 'ی' ], $query->get( 's' ) ) );
		}

		return $query;
	}

	function checkout_process_postcode() {

		if ( isset( $_POST['billing_postcode'] ) ) {
			$_POST['billing_postcode'] = self::en( sanitize_text_field( $_POST['billing_postcode'] ) );
		}

		if ( isset( $_POST['shipping_postcode'] ) ) {
			$_POST['shipping_postcode'] = self::en( sanitize_text_field( $_POST['shipping_postcode'] ) );
		}

		if ( PW()->get_options( 'phone_validation', 'no' ) != 'no' ) {
			add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_phone' ], 10, 3 );
		}
	}

	public function validate_postcode( $valid, $postcode, $country ): bool {

		if ( $country != 'IR' ) {
			return $valid;
		}

		return (bool) preg_match( '/^([0-9]{10})$/', $postcode );
	}

	public function checkout_process_phone() {

		if ( isset( $_POST['billing_phone'] ) ) {
			$_POST['billing_phone'] = self::en( sanitize_text_field( $_POST['billing_phone'] ) );
		}

		if ( isset( $_POST['shipping_phone'] ) ) {
			$_POST['shipping_phone'] = self::en( sanitize_text_field( $_POST['shipping_phone'] ) );
		}

	}

	public function validate_phone( $data, $errors ) {

		if ( ! empty( $data['billing_phone'] ) && ! (bool) preg_match( '/^(\+989|989|09)[0-9]{9}$/', $data['billing_phone'] ) ) {
			$errors->add( 'validation', '<b>تلفن همراه (صورتحساب)</b> وارد شده، معتبر نمی باشد.' );
		}

		if ( ! empty( $data['shipping_phone'] ) && ! (bool) preg_match( '/^(\+989|989|09)[0-9]{9}$/', $data['shipping_phone'] ) ) {
			$errors->add( 'validation', '<b>تلفن همراه (حمل و نقل)</b> وارد شده، معتبر نمی باشد.' );
		}

	}

	private static function en( $number ) {
		$persian        = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
		$arabic         = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
		$english_digits = range( 0, 9 );

		// Replace Persian numerals
		$number = str_replace( $persian, $english_digits, $number );

		// Replace Arabic numerals
		return str_replace( $arabic, $english_digits, $number );
	}

}

new PW_Tools_General();
