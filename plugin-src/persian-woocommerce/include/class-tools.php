<?php

defined( 'ABSPATH' ) || exit;

class Persian_Woocommerce_Tools extends Persian_Woocommerce_Core {

	/**
	 * @var PW_Tools_Price
	 */
	public PW_Tools_Price $price;

	/**
	 * @var PW_Tools_DatePicker
	 */
	public PW_Tools_DatePicker $datepicker;

	/**
	 * @var PW_Tools_Checkout
	 */
	public PW_Tools_Checkout $checkout;

	/**
	 * @var PW_Tools_Design
	 */
	public PW_Tools_Design $design;

	public function __construct() {
		add_action( 'admin_init', [ $this, 'tools_save' ] );
		add_action( 'woocommerce_admin_field_file', [ $this, 'callback_file' ], 1, 10 );
		add_action( 'woocommerce_admin_field_select_image', [ $this, 'callback_select_image' ], 1, 11 );

		add_filter( 'woocommerce_admin_field_multi_select_states', [ $this, 'specific_states_field' ] );
	}


	/**
	 * Displays a dropdown which shows related image to the option
	 *
	 * @param array $args settings field args
	 */
	public function callback_select_image( array $args ): void {
		$value = $args['value'];
		?>
		<tr class="<?php echo esc_attr( $args['row_class'] ); ?> pw_select_image_row">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['title'] ); ?></label>
			</th>

			<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $args['type'] ) ); ?>">
				<div class="pw_select_image_container">
					<select id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['id'] ); ?>"
					        class="pw_select_image_select">
						<?php foreach ( $args['options'] as $key => $value_data ) : ?>
							<?php
							// Check if the option is the default one (empty)
							if ( is_array( $value_data ) ) {
								$label = $value_data['label'];
								$image = $value_data['image'];
							} else {
								$label = $value_data;
								$image = '';
							}
							?>
							<option value="<?php echo esc_attr( $key ); ?>"
							        data-image-attr="<?php echo esc_url( $image ); ?>"
								<?php selected( $value, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<img src="" id="selected_image" class="pw_selected_image"
					     style="display:none; width:300px; height:200px; transition: all 0.3s ease;"/>
				</div>
			</td>
		</tr>
		<?php
	}


	/**
	 * Add custom image file input to woocommerce fields
	 *
	 * @param array $value
	 *
	 * @returon void
	 */
	public function callback_file( array $value ): void {
		$defaults = [
			'button' => 'انتخاب تصویر',
		];
		$value    = wp_parse_args( $value, $defaults );
		$id       = md5( $value['id'] );
		$display  = ! empty( $value['value'] ) ? 'inline-block' : 'none';
		?>
		<tr class="<?php echo esc_attr( $value['row_class'] ); ?> pw_file_input_row"
		    data-uploader-id="<?php echo esc_attr( $id ); ?>">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>

			<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
				<img id="<?php echo esc_attr( $id ); ?>_image"
				     src="<?php echo esc_url( $value['value'] ); ?>"
				     class="pw_file_uploader_image">

				<input type="text" name="<?php echo esc_attr( $value['id'] ); ?>"
				       id="<?php echo esc_attr( $value['id'] ); ?>"
				       class="<?php echo esc_attr( $id ); ?>_input"
				       value="<?php echo esc_attr( $value['value'] ); ?>"
				       style="display:none;">

				<div class="pw_file_uploader_buttons">
					<a href="#" id="<?php echo esc_attr( $id ); ?>_upload_button" class="button">
						<?php echo esc_attr( $value['button'] ); ?>
					</a>

					<?php if ( ! empty( $value['value'] ) ): ?>
						<a href="#" id="<?php echo esc_attr( $id ); ?>_remove_button"
						   class="pw_file_uploader_remove_button"
						   style="--animation-delaydisplay: <?php echo $display; ?> ">
							حذف تصویر
						</a>
					<?php endif; ?>
				</div>
				<?php echo esc_html( $value['suffix'] ?? null ); ?><?php echo $value['desc']; // WPCS: XSS ok. ?>
			</td>
		</tr>
		<?php
	}

	public function tools_tabs( $current_tab = 'date', $current_section = '' ): array {
		$active = [
			'tab'     => '',
			'section' => '',
		];

		if ( empty( $current_tab ) ) {
			$current_tab = 'date';
		}

		$tabs = apply_filters( 'PW_Tools_tabs', [
			'date'     => 'تاریخ شمسی',
			'price'    => 'گزینه های قیمت',
			'checkout' => 'تسویه حساب',
			'design'   => 'ظاهر',
		] );

		$sections['fields'] = apply_filters( 'PW_Tools_sections', [] );

		$html_sections = [];

		echo '<div id="icon-themes" class="icon32"><br></div>';
		echo '<h2 class="nav-tab-wrapper">';

		foreach ( $tabs as $tab => $tab_name ) {

			$class = '';

			if ( $tab == $current_tab ) {
				$active['tab'] = $tab;
				$class         = 'nav-tab-active';
			}

			printf( "<a class='nav-tab %s' href='?page=persian-wc-tools&tab=%s'>%s</a>", esc_attr( $class ), esc_attr( $tab ), esc_attr( $tab_name ) );

			if ( $tab == $current_tab && isset( $sections[ $tab ] ) ) {
				foreach ( $sections[ $tab ] as $section => $section_name ) {

					$class = '';

					if ( $section == $current_section || ! count( $html_sections ) ) {
						$active['section'] = $section;
						$class             = 'current';
					}

					$html_sections[] = sprintf( "<li><a href='?page=persian-wc-tools&tab=%s&section=%s' class='%s'>%s</a></li>", $tab, $section, $class, $section_name );
				}
			}
		}

		echo '</h2>';

		if ( count( $html_sections ) ) {
			printf( '<ul class="subsubsub">%s</ul><br>', esc_html( implode( " | ", $html_sections ) ) );
		}

		return array_values( $active );
	}

	public function tools_sections() {

		$tools = [

			'date' => [
				[
					'title' => 'تاریخ شمسی وردپرس و ووکامرس',
					'type'  => 'title',
					'id'    => 'general_options',
				],
				[
					'title'   => 'تاریخ شمسی',
					'id'      => 'PW_Options[enable_jalali_datepicker]',
					'type'    => 'checkbox',
					'default' => 'yes',
					'desc'    => 'فعالسازی تاریخ شمسی در وردپرس و ووکامرس (محصولات، سفارشات، کوپن ها و گزارشات)<br>
<p><b>پیشنهاد:</b> برای کارکردن صحیح افزونه و عملکرد مناسب این ابزار، پیشنهاد می کنیم هیچ افزونه شمسی ساز دیگری را همزمان فعال نکنید.</p>',
				],
				[
					'type' => 'sectionend',
					'id'   => 'general_options',
				],
			],

			'design' => [
				[
					'title' => 'فونت مدیریت',
					'type'  => 'title',
					'id'    => 'admin_font_options',
				],
				[
					'title'   => 'خانواده فونت',
					'id'      => 'PW_Options[admin_font_family]',
					'default' => 'iransans',
					'type'    => 'select',
					'options' => [
						'none'            => 'غیرفعال',
						'vazirmatn'       => 'فونت وزیرمتن',
						'vazirmatn-fanum' => 'فونت وزیرمتن (اعداد فارسی)',
						'iransans'        => 'فونت ایران سنس',
						'iransans-fanum'  => 'فونت ایران سنس (اعداد فارسی)',
						'yekanbakh'       => 'فونت یکان بخ',
						'yekanbakh-fanum' => 'فونت یکان بخ (اعداد فارسی)',
					],
					'css'     => 'width:50%;min-width:300px;',
				],
				[
					'type' => 'sectionend',
					'id'   => 'admin_font_options',
				],
				[
					'title' => 'سفارشی سازی صفحه ورود',
					'type'  => 'title',
					'id'    => 'admin_login_options',
				],
				[
					'title'       => 'لوگو',
					'id'          => 'PW_Options[admin_login_logo_url]',
					'default'     => '',
					'type'        => 'file',
					'placeholder' => 'این فایل جایگزین لوگو وردپرس خواهد شد.',
				],
				[
					'title'   => 'قالب صفحه ورود',
					'id'      => 'PW_Options[admin_login_template]',
					'default' => '',
					'type'    => 'select_image',
					'options' => [
						''       => 'هیچ کدام',
						'mahan'  => [
							'label' => 'ماهان',
							'image' => PW()->plugin_url( 'assets/images/login/mahan.png' ), // @todo compress image
						],
						'shamim' => [
							'label' => 'شمیم',
							'image' => PW()->plugin_url( 'assets/images/login/shamim.png' ),
						],
					],

					'placeholder' => 'قالب های از پیش ساخته صفحه ورود مدیریت',
				],
				[
					'type' => 'sectionend',
					'id'   => 'admin_login_options',
				],

			],

			'price' => [
				[
					'title' => 'تماس بگیرید',
					'type'  => 'title',
					'id'    => 'call_for_price_options',
				],
				[
					'title'    => 'فعالسازی تماس برای قیمت',
					'desc'     => 'فعالسازی برچسب "تماس بگیرید" بجای قیمت در صورتی که قیمت محصول وارد نشده باشد',
					'desc_tip' => 'دقت کنید که قیمت 0 به معنای رایگان بودن محصول می باشد. قسمت قیمت را خالی بگذارید.',
					'id'       => 'PW_Options[enable_call_for_price]',
					'type'     => 'checkbox',
					'default'  => 'no',
				],
				[
					'title'   => 'برچسب در صفحه محصول',
					// 'desc' 	    => 'این مورد بجای قیمت محصول در صفحه محصول نمایش داده می شود. برای غیرفعال کردن خالی بگذارید.',
					// 'desc_tip'  => true,
					'id'      => 'PW_Options[call_for_price_text]',
					'default' => '<strong>تماس بگیرید</strong>',
					'type'    => 'textarea',
					'css'     => 'width:50%;min-width:300px;',
				],
				[
					'title'   => 'برچسب در قسمت آرشیو ها',
					// 'desc' 	    => 'این مورد بجای قیمت محصول در آرشیو ها نمایش داده می شود. برای غیرفعال کردن خالی بگذارید.',
					// 'desc_tip'  => true,
					'id'      => 'PW_Options[call_for_price_text_on_archive]',
					'default' => '<strong>تماس بگیرید</strong>',
					'type'    => 'textarea',
					'css'     => 'width:50%;min-width:300px;',
				],
				[
					'title'   => 'برچسب در صفحه اصلی',
					// 'desc' 	    => 'این مورد بجای قیمت محصول در صفحه اصلی نمایش داده می شود. برای غیرفعال کردن خالی بگذارید.',
					// 'desc_tip'  => true,
					'id'      => 'PW_Options[call_for_price_text_on_home]',
					'default' => '<strong>تماس بگیرید</strong>',
					'type'    => 'textarea',
					'css'     => 'width:50%;min-width:300px;',
				],
				[
					'title'   => 'برچسب در محصولات مرتبط',
					// 'desc' 	    => 'این مورد بجای قیمت محصول در محصولات مرتبط نمایش داده می شود. برای غیرفعال کردن خالی بگذارید.',
					// 'desc_tip'  => true,
					'id'      => 'PW_Options[call_for_price_text_on_related]',
					'default' => '<strong>تماس بگیرید</strong>',
					'type'    => 'textarea',
					'css'     => 'width:50%;min-width:300px;',
				],
				[
					'title'   => 'برچسب "فروش ویژه"',
					'desc'    => 'حذف برچسب فروش ویژه',
					'id'      => 'PW_Options[call_for_price_hide_sale_sign]',
					'default' => 'no',
					'type'    => 'checkbox',
				],
				[
					'type' => 'sectionend',
					'id'   => 'call_for_price_options',
				],

				[
					'title' => 'نمایش قیمت',
					'type'  => 'title',
					'id'    => 'price_option',
				],
				[
					'title'   => 'فارسی سازی قیمت ها',
					'desc'    => 'استفاده از اعداد فارسی در قیمت ها',
					'id'      => 'PW_Options[persian_price]',
					'default' => 'no',
					'type'    => 'checkbox',
				],
				[
					'title'   => 'قیمت محصولات متغیر',
					'desc'    => 'نحوه نمایش قیمت محصولات متغیر در صفحات لیست محصولات',
					'id'      => 'PW_Options[variable_price]',
					'default' => 'range',
					'type'    => 'select',
					'options' => [
						'range' => 'بازه قیمتی (پیشفرض)',
						'min'   => 'حداقل قیمت',
					],
				],
				[
					'type' => 'sectionend',
					'id'   => 'price_option',
				],

				[
					'title' => 'سایر',
					'type'  => 'title',
					'id'    => 'other_price_option',
				],
				[
					'title'   => 'حداقل مبلغ سفارش',
					'id'      => 'PW_Options[minimum_order_amount]',
					'default' => 0,
					'desc'    => 'در صورتی که قصد دارید برای امکان ثبت سفارشات در فروشگاه خود یک حداقل مبلغ مشخص کنید میتوانید مبلغ را در اینجا وارد نمایید در غیر اینصورت تنظیمات را دستکاری نکنید.',
					'type'    => 'number',
				],
				[
					'type' => 'sectionend',
					'id'   => 'other_price_option',
				],
			],

			'checkout' => [
				[
					'title' => 'استان ها و شهرها',
					'type'  => 'title',
					'id'    => 'address_options',
				],
				[
					'title'   => 'فروش به استان های',
					'id'      => 'PW_Options[allowed_states]',
					'default' => 'all',
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'css'     => 'width: 350px;',
					'options' => [
						'all'      => 'فروش به همه استان ها',
						'specific' => 'فروش به استان های خاص',
					],
				],
				[
					'title'   => 'استان های خاص',
					'desc'    => '',
					'id'      => 'PW_Options[specific_allowed_states]',
					'css'     => 'min-width: 350px;',
					'default' => '',
					'class'   => 'wc-enhanced-select',
					'type'    => 'multi_select_states',
				],
				[
					'title'   => 'فعالسازی شهرهای ایران',
					'id'      => 'PW_Options[enable_iran_cities]',
					'type'    => 'checkbox',
					'default' => 'yes',
					'desc'    => 'فعالسازی شهرهای ایران در صفحه تسویه حساب',
				],
				[
					'title'   => 'جابجایی فیلد استان و شهر',
					'id'      => 'PW_Options[flip_state_city]',
					'type'    => 'checkbox',
					'default' => 'no',
					'desc'    => 'در صورتی که گزینه "فعالسازی شهر های ایران" را انتخاب نمایید، در برخی قالب ها ممکن است فیلد شهر قبل از فیلد استان قرار بگیرد که با فعالسازی این گزینه میتوانید جایگاه آنها را با هم عوض نمایید.',
				],
				[
					'type' => 'sectionend',
					'id'   => 'address_options',
				],

				[
					'title' => 'کدپستی',
					'type'  => 'title',
					'id'    => 'postcode_options',
				],
				[
					'title'   => 'اعداد فارسی در کدپستی',
					'id'      => 'PW_Options[fix_postcode_persian_number]',
					'type'    => 'checkbox',
					'default' => 'yes',
					'desc'    => 'برای تبدیل اعداد فارسی به انگلیسی در کدپستی تیک بزنید.',
				],
				[
					'title'   => 'بررسی صحت کدپستی',
					'id'      => 'PW_Options[postcode_validation]',
					'type'    => 'checkbox',
					'default' => 'no',
					'desc'    => 'برای بررسی صحت کدپستی و ده رقمی بودن آن تیک بزنید.',
				],
				[
					'type' => 'sectionend',
					'id'   => 'postcode_options',
				],

				[
					'title' => 'تلفن همراه',
					'type'  => 'title',
					'id'    => 'phone_options',
				],
				[
					'title'   => 'اعداد فارسی در تلفن همراه',
					'id'      => 'PW_Options[fix_phone_persian_number]',
					'type'    => 'checkbox',
					'default' => 'yes',
					'desc'    => 'برای تبدیل اعداد فارسی به انگلیسی در تلفن همراه تیک بزنید.',
				],
				[
					'title'   => 'بررسی صحت تلفن همراه',
					'id'      => 'PW_Options[phone_validation]',
					'type'    => 'checkbox',
					'default' => 'no',
					'desc'    => 'برای بررسی صحت تلفن همراه و یازده رقمی بودن آن تیک بزنید.',
				],
				[
					'type' => 'sectionend',
					'id'   => 'phone_options',
				],

				[
					'title' => 'سایر',
					'type'  => 'title',
					'id'    => 'other_options',
				],
				[
					'title'   => 'حذف فیلدهای غیرضروری',
					'id'      => 'PW_Options[remove_extra_field_physical]',
					'type'    => 'checkbox',
					'default' => 'no',
					'desc'    => 'برای حذف فیلدهای غیرضروری از محصولات دانلودی ووکامرس تیک بزنید.',
				],
				[
					'type' => 'sectionend',
					'id'   => 'other_options',
				],
			],

		];

		return apply_filters( 'PW_Tools_settings', $tools );
	}

	public function tools_page() {
		global $pagenow;

		$settings = $this->tools_sections();
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'pw-admin-script' );
		wp_enqueue_media();
		?>

		<div class="wrap persian-woocommerce">
			<h2>ابزارهای ووکامرس فارسی</h2>
			<a href="<?php echo esc_url( Persian_Woocommerce_Changelog::get_page_url() ) ?>" target="_blank"
			   class="button button-primary float-left-buttons">تاریخچه تغییرات</a>
			<?php
			$updated = intval( $_GET['updated'] ?? 0 );

			if ( $updated ) {
				echo '<div class="updated" ><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
			}

			$current_tab     = sanitize_text_field( $_GET['tab'] ?? null );
			$current_section = sanitize_text_field( $_GET['section'] ?? null );

			[ $tab, $section ] = $this->tools_tabs( $current_tab, $current_section );
			?>

			<div id="poststuff">
				<form method="post" action="<?php admin_url( 'themes.php?page=persian-wc-tools' ); ?>">
					<?php
					wp_nonce_field( "persian-wc-tools" );

					if ( $pagenow == 'admin.php' && $_GET['page'] == 'persian-wc-tools' && isset( $settings[ $tab ] ) ) {
						WC_Admin_Settings::output_fields( empty( $section ) ? $settings[ $tab ] : $settings[ $tab ][ $section ] );
					}

					?>
					<p class="submit" style="clear: both;">
						<input type="submit" name="Submit" class="button-primary" value="ذخیره تنظیمات"/>
						<input type="hidden" name="pw-settings-submit" value="Y"/>
						<input type="hidden" name="pw-tab" value="<?php echo esc_attr( $tab ); ?>"/>
						<input type="hidden" name="pw-section" value="<?php echo esc_attr( $section ); ?>"/>
					</p>
				</form>
			</div>

		</div>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('.persian-woocommerce').on('click', '.select_all', function () {
                    jQuery(this).closest('td').find('select option').attr('selected', 'selected');
                    jQuery(this).closest('td').find('select').trigger('change');
                    return false;
                }).on('click', '.select_none', function () {
                    jQuery(this).closest('td').find('select option').removeAttr('selected');
                    jQuery(this).closest('td').find('select').trigger('change');
                    return false;
                });

                $('select#PW_Options\\[allowed_states\\]').change(function () {
                    if (jQuery(this).val() === 'specific') {
                        jQuery(this).parent().parent().next('tr').show();
                    } else {
                        jQuery(this).parent().parent().next('tr').hide();
                    }
                }).change();
            });
		</script>
		<?php
	}

	public function tools_save() {

		$page = sanitize_text_field( $_GET['page'] ?? null );

		if ( $page != 'persian-wc-tools' ) {
			return;
		}

		if ( isset( $_POST['pw-settings-submit'] ) && $_POST['pw-settings-submit'] == 'Y' ) {

			$settings = $this->tools_sections();
			$tab      = sanitize_text_field( $_POST['pw-tab'] );
			$section  = sanitize_text_field( $_POST['pw-section'] );

			check_admin_referer( 'persian-wc-tools' );

			do_action( 'PW_before_save_tools', $_POST, $settings, $tab, $section );

			WC_Admin_Settings::save_fields( empty( $section ) ? $settings[ $tab ] : $settings[ $tab ][ $section ] );

			do_action( 'PW_after_save_tools', $_POST, $settings, $tab, $section );

			$url_parameters = empty( $section ) ? $tab : $tab . '&section=' . $section;

			wp_redirect( admin_url( 'admin.php?page=persian-wc-tools&updated=1&tab=' . $url_parameters ) );
			exit;
		}
	}

	public function specific_states_field( $value ) {

		$selections = (array) PW()->get_options( 'specific_allowed_states' );
		?>
		<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
		</th>
		<td class="forminp">
			<select multiple="multiple" name="<?php echo esc_attr( $value['id'] ); ?>[]" style="width:350px"
			        data-placeholder="استان (ها) مورد نظر خود را انتخاب کنید ..." title="استان"
			        class="wc-enhanced-select">
				<?php
				foreach ( Persian_Woocommerce_Address::$states as $key => $val ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $selections ), true, false ) . '>' . esc_attr( $val ) . '</option>';
				}
				?>
			</select> <br/><a class="select_all button" href="#">انتخاب همه</a> <a
					class="select_none button" href="#">هیچکدام</a>
		</td>
		</tr><?php
	}
}

PW()->tools = new Persian_Woocommerce_Tools();

require_once 'tools/class-general.php';
require_once 'tools/class-price.php';
require_once 'tools/class-rank-math.php';
require_once 'tools/class-datepicker.php';
require_once 'tools/class-date.php';
require_once 'tools/class-checkout.php';
require_once 'tools/class-super-admin.php';
require_once 'tools/class-design.php';
require_once 'tools/class-forminator.php';