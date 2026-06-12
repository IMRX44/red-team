<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controls the appearance of admin area
 *
 * @since 9.0.1
 */
class PW_Tools_Design {

	/**
	 * Selected admin area font
	 * 'none' value disables whole admin font feature
	 *
	 * @var string
	 */
	public string $admin_font_family = '';

	/**
	 * Path to font css file
	 * In this file the font face and variable is getting defined.
	 *
	 * @var string
	 */
	public string $admin_font_css_file = '';

	/**
	 * Path to admin css file
	 * This file set css selectors far the font family
	 *
	 * @var string
	 */
	public string $admin_css_file = '';

	/**
	 * Farsi numbers in admin area
	 *
	 * @var bool
	 */
	public bool $admin_font_fa_num = false;

	/**
	 * Admin login logo url
	 *
	 * @var string
	 */
	public string $admin_login_logo_url = '';

	/**
	 * Admin login template name
	 *
	 * @var string
	 */
	public string $admin_login_template = '';

	/**
	 * Execute appearance features
	 */
	public function __construct() {
		$this->init_admin_font();
		$this->init_admin_login_logo();
		$this->init_admin_login_template();
	}


	/**
	 * Apply out of box selected admin login template
	 *
	 * @return void
	 */
	public function init_admin_login_template() {
		$this->admin_login_template = PW()->get_options( 'admin_login_template', '' );

		if ( empty( $this->admin_login_template ) ) {
			return;
		}

		$method = 'enqueue_login_template_' . $this->admin_login_template;

		add_action( 'login_enqueue_scripts', [ $this, $method ] );
	}


	/**
	 * Apply login page settings
	 *
	 * @return void
	 */
	public function init_admin_login_logo() {
		$this->admin_login_logo_url = sanitize_text_field( PW()->get_options( 'admin_login_logo_url', '' ) );

		if ( empty( $this->admin_login_logo_url ) || ! filter_var( $this->admin_login_logo_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		add_action( 'login_enqueue_scripts', [ $this, 'get_login_logo_css' ] );
	}

	/**
	 * Generate custom css for login logo
	 *
	 * @return void
	 */
	public function get_login_logo_css() {
		echo <<<EOF
                <style>
                    #login h1 a, .login h1 a {
                        background-image: url($this->admin_login_logo_url);
                        height: 100px; 
                        width: 100%; 
                        background-size: contain;
                    }
                </style>
                EOF;
	}

	/**
	 * Initialize admin font feature
	 *
	 * @return void
	 */
	public function init_admin_font(): void {
		$this->admin_font_family = PW()->get_options( 'admin_font_family', 'iransans' );

		// 'none' Disables the font feature
		if ( $this->admin_font_family == 'none' ) {
			return;
		}

		// Check if farsi numbers are enabled to show
		if ( strpos( $this->admin_font_family, '-fanum' ) !== false ) {
			$this->admin_font_fa_num = true;
			$this->admin_font_family = str_replace( '-fanum', '', $this->admin_font_family );
		}

		$this->admin_css_file      = PW()->plugin_url( 'assets/fonts/admin-font.css' );
		$this->admin_font_css_file = $this->get_admin_font_css_file();

		// Admin area
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_font_scripts' ] );
		// Login page
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_admin_font_scripts' ] );
	}

	/**
	 * Load admin font related styles
	 *
	 * @return void
	 */
	public function enqueue_admin_font_scripts(): void {
		// This file sets font variable
		wp_enqueue_style( 'pw-admin-font-family', $this->admin_font_css_file, [], PW_VERSION );

		// This file applies the font family
		wp_enqueue_style( 'pw-admin-font', $this->admin_css_file, [ 'pw-admin-font-family' ], PW_VERSION );
	}

	/**
	 * Font css file is chosen based on options
	 *
	 * @return string
	 */
	public function get_admin_font_css_file(): string {
		$css_file_name = $this->admin_font_fa_num ? "{$this->admin_font_family}-fanum.css" : "{$this->admin_font_family}.css";
		$css_file_path = "{$this->admin_font_family}/{$css_file_name}";

		return PW()->plugin_url( "assets/fonts/{$css_file_path}" );
	}


	/**
	 * Read css file from url and return its content
	 *
	 * @param string $file_url
	 *
	 * @return string
	 */
	public function get_css_content_from_url( string $file_url = '' ): string {
		$response    = wp_remote_get( $file_url );
		$css_content = '';

		if ( ! is_wp_error( $response ) ) {
			$css_content = wp_remote_retrieve_body( $response );
		}

		return $css_content;
	}


	/**
	 * Login template enqueue handler
	 *
	 * @param string $template_name The name of the login template (e.g., 'mahan', 'shamim').
	 *
	 * @return string
	 */
	public function enqueue_login_template( string $template_name ): void {
		if ( empty( $template_name ) ) {
			return;
		}
		$file_url = PW()->plugin_url( "assets/css/login-templates/{$template_name}.css" );
		wp_enqueue_style( 'pw-admin-login-template-' . $template_name, $file_url, [], PW_VERSION );
	}

	/**
	 * Enqueue Mahan login template
	 *
	 * @return void
	 */
	public function enqueue_login_template_mahan(): void {
		$this->enqueue_login_template( 'mahan' );
	}


	/**
	 * Enqueue Shamim login template
	 *
	 * @return void
	 */
	public function enqueue_login_template_shamim(): void {
		$this->enqueue_login_template( 'shamim' );
	}
}

PW()->tools->design = new PW_Tools_Design();