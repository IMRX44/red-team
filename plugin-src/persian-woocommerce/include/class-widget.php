<?php

defined( 'ABSPATH' ) || exit;

class Persian_Woocommerce_Widget extends Persian_Woocommerce_Core {

	/**
	 * @var string The feed cache identifier
	 */
	public string $transient_key = 'woocommerce_ir_feed_cache';

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'widget_setup' ] );
	}

	public function widget_setup() {
		wp_add_dashboard_widget( 'persian_woocommerce_feed',
			'آخرین اخبار ووکامرس فارسی',
			[ $this, 'widget_render' ],
			[ $this, 'widget_settings' ] );
	}

	public function widget_render() {

		$widget_options = $this->widget_options();
		$cached_output  = get_transient( $this->transient_key );

		if ( false === $cached_output ) {

			ob_start();

			wp_widget_rss_output( [
				'url'          => 'https://woocommerce.ir/feed/',
				'title'        => 'آخرین اخبار ووکامرس فارسی',
				'meta'         => [ 'target' => '_new' ],
				'items'        => intval( $widget_options['posts_number'] ),
				'show_summary' => 0,
				'show_author'  => 0,
				'show_date'    => 1,
			] );

			$cached_output = ob_get_clean();
			set_transient( $this->transient_key, $cached_output, DAY_IN_SECONDS );
		}

		?>

		<div class="rss-widget">
			<?php echo $cached_output; ?>
			<div style="border-top: 1px solid #e7e7e7; padding-top: 8px !important; margin-top: 8px !important; font-size: 12px;">
				<img src="<?php echo esc_url( PW()->plugin_url( 'assets/images/feed.png' ) ); ?>" width="16" height="16">
				<a href="https://woosupport.ir" target="_new" title="خانه">وب سایت پشتیبان ووکامرس فارسی</a>
			</div>
		</div>

	<?php }

	public function widget_settings() {

		$options = $this->widget_options();

		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['widget_id'] ) && 'persian_woocommerce_feed' == $_POST['widget_id'] ) {
			$options['posts_number'] = intval( $_POST['posts_number'] );
			update_option( 'persian_woocommerce_feed', $options );
		}
		?>
		<p>
			<label for="posts_number">تعداد نوشته های قابل نمایش در ابزارک ووکامرس فارسی:
				<select id="posts_number" name="posts_number">
					<?php for ( $i = 3; $i <= 20; $i ++ ) {
						printf( '<option value="%d" %s>%d</option>', $i, selected( $options['posts_number'], $i, false ), $i );
					}
					?>
				</select>
			</label>
		</p>
		<?php
	}

	public function widget_options() {
		$defaults = [ 'posts_number' => 5 ];
		if ( ( ! $options = get_option( 'persian_woocommerce_feed' ) ) || ! is_array( $options ) ) {
			$options = [];
		}

		return array_merge( $defaults, $options );
	}
}

new Persian_Woocommerce_Widget();