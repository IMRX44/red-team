<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;


/**
 * Class Persian_Woocommerce_Forminator
 *
 * Fix and integration with Forminator plugin
 *
 * @author  mahdiy
 * @package https://wordpress.org/plugins/persian-date/
 */
class Persian_Woocommerce_Forminator {

	public string $entry_table_name;
	public string $meta_table_name;

	public function __construct() {

		if ( is_plugin_inactive( 'forminator/forminator.php' ) ) {
			return;
		}

		add_action( 'forminator_loaded', [ $this, 'set_properties' ], 100 );
		add_action( 'forminator_form_after_handle_submit', [ $this, 'fix_date' ], 100, 2 );
		add_action( 'forminator_form_after_save_entry', [ $this, 'fix_date' ], 100, 2 );
	}

	/**
	 * Set properties which are dependent on forminator classes
	 *
	 * @return void
	 */
	function set_properties(): void {
		$this->entry_table_name = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY );
		$this->meta_table_name  = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY_META );
	}

	/**
	 * This method exists in Forminator_Form_Entry_Model::get_latest_entry_by_form_id, but it's ordering by date
	 *
	 * @param int $form_id
	 *
	 * @return int|null
	 */
	public function get_latest_entry_id( int $form_id ): ?int {
		global $wpdb;

		$sql          = "SELECT `entry_id` FROM {$this->entry_table_name} WHERE `form_id` = %d AND `is_spam` = 0 ORDER BY `entry_id` DESC";
		$prepared_sql = $wpdb->prepare( $sql, $form_id );

		return (int) $wpdb->get_var( $prepared_sql );
	}

	/**
	 * Work with forminator tables and fix the jalali date passed with date_i18n to gregorian
	 *
	 * @param int $form_id
	 *
	 * @return void
	 */
	public function fix_date( int $form_id ): void {
		global $wpdb;

		$now      = current_datetime()->format( 'Y-m-d H:i:s' );
		$entry_id = $this->get_latest_entry_id( $form_id );
		$wpdb->update( $this->entry_table_name, [ 'date_created' => $now ], [ 'entry_id' => $entry_id ] );
		$wpdb->update( $this->meta_table_name, [ 'date_created' => $now ], [ 'entry_id' => $entry_id ] );
	}


}

new Persian_Woocommerce_Forminator();