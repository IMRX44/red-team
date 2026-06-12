<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts the readme.txt change log to WordPress page
 */
class Persian_Woocommerce_Changelog {

	/**
	 * @var string the changelog page slug in WordPress
	 */
	public static string $page_slug = 'persian-wc-changelog';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
	}

	/**
	 * Registers the page, but doesn't add it to the menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public function register_admin_page(): void {

		add_submenu_page(
			'persian-wc-void',
			'لیست تغییرات ووکامرس فارسی',
			'لیست تغییرات ووکامرس فارسی',
			'manage_options',
			'persian-wc-changelog',
			[ $this, 'render_changelog_page' ]
		);
	}

	/**
	 * Reads the readme.txt and printout the changelog page
	 *
	 * @used-by register_admin_page
	 *
	 * @return void
	 */
	public function render_changelog_page(): void {
		$readme_file    = PW_DIR . '/readme.txt';
		$pw_page_url = 'admin.php?page=persian-wc-tools';

		echo '<div class="pw-changelog__container"><h1>لیست تغییرات  ';
		echo '<a style="text-decoration:none;" href="' . esc_url( $pw_page_url ) . '">افزونه ووکامرس فارسی</a>';
		echo '</h1>';

		if ( ! file_exists( $readme_file ) ) {
			echo '<p><strong>خطا:</strong> فایل readme.txt یافت نشد.</p></div>';

			return;
		}

		$contents = file_get_contents( $readme_file );
		// Using strpos to determine line of changelog
		$changelog_start = strpos( $contents, '== Changelog ==' );

		if ( $changelog_start === false ) {
			echo '<p><strong>خطا:</strong> لیست تغییراتی وجود ندارد.</p></div>';

			return;
		}

		$changelog = substr( $contents, $changelog_start );
		echo $this->convert_changelog_to_html( $changelog );

		echo '</div>';
	}

	/**
	 * Convert custom format in readme.txt to fancy html
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function convert_changelog_to_html( string $text ): string {
		$lines   = explode( "\n", $text );
		$html    = '<div class="pw-changelog" style="background:#fff; margin:25px 20px 10px 10px;padding:10px 20px 10px;border-radius:20px;shadow">';
		$ul_open = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( preg_match( '/^==\s*Changelog\s*==$/', $line ) ) {
				continue;
			} elseif ( preg_match( '/^= (.+) =$/', $line, $matches ) ) {
				// Close previous <ul> if open
				if ( $ul_open ) {
					$html    .= '</ul>';
					$ul_open = false;
				}
				$html    .= '<h2>نسخه ' . esc_html( $matches[1] ) . '</h2>';
				$html    .= '<ul  style="margin-block-end: 20px;">';
				$ul_open = true;
			} elseif ( strpos( $line, '*' ) === 0 ) {
				if ( ! $ul_open ) {
					$html    .= '<ul>';
					$ul_open = true;
				}
				$html .= '<li style="padding-inline: 10px;">' . esc_html( ltrim( $line, '* ' ) ) . '</li>';
			}
		}

		if ( $ul_open ) {
			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get the changelog page url
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::$page_slug );
	}

}


new Persian_Woocommerce_Changelog();