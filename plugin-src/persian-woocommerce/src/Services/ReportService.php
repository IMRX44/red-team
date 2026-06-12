<?php

namespace PersianWooCommerce\Services;

use Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore as OrdersDataStore;
use Automattic\WooCommerce\Internal\Admin\Settings;
use Hekmatinasser\Verta\Verta;
use Persian_Woocommerce_Address;

class ReportService {

	public string $valid_order_statuses;

	public string $query_date_column;

	public string $table_order_stats;

	public string $table_operational_data;

	public string $table_product_lookup;

	public string $table_product_meta_lookup;

	public function __construct() {
		global $wpdb;

		$this->table_order_stats         = $wpdb->prefix . 'wc_order_stats';
		$this->table_operational_data    = $wpdb->prefix . 'wc_order_operational_data';
		$this->table_product_lookup      = $wpdb->prefix . 'wc_order_product_lookup';
		$this->table_product_meta_lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		$this->valid_order_statuses = $this->get_valid_order_statuses();
		$this->query_date_column    = $this->get_query_date_column();
	}

	public function customer_summary( Verta $start_date, Verta $end_date ): array {
		global $wpdb;

		$customer_orders_summary               = "
	        WITH customer_order_summary AS (
	            SELECT customer_id, COUNT(order_id) AS order_count
	            FROM {$this->table_order_stats} AS wc_stats
	            WHERE customer_id > 0 AND wc_stats.{$this->query_date_column} BETWEEN %s AND %s AND status IN ({$this->valid_order_statuses})
	            GROUP BY customer_id
	        )
        ";
		$total_active_customers                = $wpdb->get_var( $wpdb->prepare(
			"{$customer_orders_summary} SELECT COUNT(customer_id) FROM customer_order_summary;",
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );
		$total_orders_for_all_active_customers = $wpdb->get_var( $wpdb->prepare(
			"{$customer_orders_summary} SELECT SUM(order_count) FROM customer_order_summary;",
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		return [
			'total_customers'     => intval( $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users};" ) ),
			'active_customers'    => intval( $total_active_customers ),
			'new_customers'       => intval( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->users} WHERE user_registered BETWEEN %s AND %s;",
				$start_date->formatGregorian( 'Y-m-d H:i:s' ),
				$end_date->formatGregorian( 'Y-m-d H:i:s' )
			) ) ),
			'avg_customer_orders' => $total_active_customers > 0 ? round( $total_orders_for_all_active_customers / $total_active_customers, 2 ) : 0.0,
		];
	}

	public function customer_chart( Verta $start_date, Verta $end_date, ?string $interval = 'day' ): array {
		global $wpdb;

		$default_structure = [
			'new_user' => 0,
			'orders'   => 0,
		];

		$daily_metrics = $this->generate_metrics_scaffolding( $start_date, $end_date, $interval, $default_structure );

		$daily_orders_sql     = "
			WITH daily_orders_cte AS (
			    SELECT 
			        DATE(wc_stats.{$this->query_date_column}) AS metric_date, 
			        COUNT(wc_stats.order_id) AS daily_orders
			    FROM 
			        {$this->table_order_stats} AS wc_stats
			    WHERE 
			        wc_stats.{$this->query_date_column} BETWEEN %s AND %s 
			        AND wc_stats.status IN ({$this->valid_order_statuses})
			    GROUP BY metric_date
			)
			SELECT metric_date, daily_orders
			FROM daily_orders_cte
			ORDER BY metric_date ASC; 
        ";
		$daily_orders_results = $wpdb->get_results( $wpdb->prepare(
			$daily_orders_sql,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		), OBJECT_K );

		$new_users_sql     = "
		    WITH new_users_daily_cte AS (
			    SELECT
			        DATE(user_registered) AS metric_date,
			        COUNT(ID) AS new_users
			    FROM {$wpdb->users}
			    WHERE user_registered BETWEEN %s AND %s
			    GROUP BY metric_date
			)
			SELECT metric_date, new_users
			FROM new_users_daily_cte
			ORDER BY metric_date ASC;
        ";
		$new_users_results = $wpdb->get_results( $wpdb->prepare(
			$new_users_sql,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		), OBJECT_K );

		if ( $daily_orders_results ) {

			foreach ( $daily_orders_results as $gregorian_day => $row ) {

				$jalali_key = $this->get_grouping_key( $gregorian_day, $interval );

				if ( isset( $daily_metrics[ $jalali_key ] ) ) {
					$daily_metrics[ $jalali_key ]['orders'] += intval( $row->daily_orders );
				}

			}

		}

		if ( $new_users_results ) {

			foreach ( $new_users_results as $gregorian_day => $row ) {

				$jalali_key = $this->get_grouping_key( $gregorian_day, $interval );

				if ( isset( $daily_metrics[ $jalali_key ] ) ) {
					$daily_metrics[ $jalali_key ]['new_user'] += intval( $row->new_users );
				}

			}

		}

		return $daily_metrics;
	}

	public function customer_users( Verta $start_date, Verta $end_date, ?int $per_page = 25, ?int $page = 1, ?string $orderby = 'order_count', ?string $order = 'DESC' ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$map_orderby_columns = [
			'order_count'     => 'cos.order_count',
			'total_spent'     => 'cos.total_spent',
			'last_order_date' => 'cos.last_order_date',
		];

		$orderby_sql_column  = $map_orderby_columns[ $orderby ] ?? $map_orderby_columns['last_order_date'];
		$order_sql_direction = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$total_items_sql = "
	        WITH customer_order_summary AS (
	            SELECT o.customer_id
	            FROM {$this->table_order_stats} o
	            INNER JOIN {$wpdb->users} u ON u.ID = o.customer_id
	            WHERE o.customer_id > 0
	              AND o.{$this->query_date_column} BETWEEN %s AND %s
	              AND o.status IN ({$this->valid_order_statuses})
	            GROUP BY o.customer_id
	        )
	        SELECT COUNT(*) FROM customer_order_summary;
	    ";
		$total_items     = $wpdb->get_var( $wpdb->prepare(
			$total_items_sql,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		$paginated_list_cte_sql = "
	        WITH customer_order_summary AS (
	            SELECT
	                o.customer_id,
	                COUNT(o.order_id) AS order_count,
	                SUM(o.total_sales) AS total_spent,
	                MAX(o.date_created) AS last_order_date
	            FROM {$this->table_order_stats} o
	            INNER JOIN {$wpdb->users} u ON u.ID = o.customer_id
	            WHERE o.customer_id > 0
	              AND o.{$this->query_date_column} BETWEEN %s AND %s
	              AND o.status IN ({$this->valid_order_statuses})
	            GROUP BY o.customer_id
	        )
	    ";

		$sql_query_string = "
		    {$paginated_list_cte_sql}
		    SELECT
		        u.ID AS user_id,
		        u.user_email AS email,
		
		       COALESCE(
				    MAX(CASE WHEN um.meta_key = 'first_name' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'billing_first_name' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'shipping_first_name' THEN NULLIF(TRIM(um.meta_value), '') END)
				) AS first_name,
				
				COALESCE(
				    MAX(CASE WHEN um.meta_key = 'last_name' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'billing_last_name' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'shipping_last_name' THEN NULLIF(TRIM(um.meta_value), '') END)
				) AS last_name,
				
				COALESCE(
				    MAX(CASE WHEN um.meta_key = 'billing_state' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'shipping_state' THEN NULLIF(TRIM(um.meta_value), '') END)
				) AS state,
				
				COALESCE(
				    MAX(CASE WHEN um.meta_key = 'billing_city' THEN NULLIF(TRIM(um.meta_value), '') END),
				    MAX(CASE WHEN um.meta_key = 'shipping_city' THEN NULLIF(TRIM(um.meta_value), '') END)
				) AS city,
		        cos.order_count,
		        cos.total_spent,
		        cos.last_order_date
		    FROM customer_order_summary cos
		    INNER JOIN {$wpdb->users} u ON u.ID = cos.customer_id
		    LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
		    GROUP BY u.ID
		    ORDER BY {$orderby_sql_column} {$order_sql_direction}
		    LIMIT %d OFFSET %d;
		";

		$active_customers_results = $wpdb->get_results(
			$wpdb->prepare(
				$sql_query_string,
				$start_date->formatGregorian( 'Y-m-d H:i:s' ),
				$end_date->formatGregorian( 'Y-m-d H:i:s' ),
				$per_page,
				$offset
			)
		);

		$customer_list = [];

		foreach ( $active_customers_results as $customer ) {

			$customer_list[] = [
				'user_id'         => intval( $customer->user_id ),
				'name'            => $customer->first_name,
				'last_name'       => $customer->last_name,
				'email'           => $customer->email,
				'province_city'   => trim( $this->get_state( $customer->state ) . ' - ' . $this->get_city( $customer->city ), ' -' ),
				'orders'          => intval( $customer->order_count ),
				'total_spent'     => floatval( $customer->total_spent ),
				'last_order_date' => $customer->last_order_date ? verta( $customer->last_order_date )->format( 'Y/m/d H:i' ) : null,
			];

		}

		return [
			'customers'  => $customer_list,
			'pagination' => [
				'total_items'  => intval( $total_items ),
				'total_pages'  => intval( ceil( $total_items / $per_page ) ),
				'per_page'     => $per_page,
				'current_page' => $page,
			],
		];
	}

	public function revenue_summary( Verta $start_date, Verta $end_date ): array {
		global $wpdb;

		$data_totals = [
			'order_count'         => 0,
			'gross_sales'         => 0.0,
			'net_sales'           => 0.0,
			'total_sales'         => 0.0,
			'avg_order_value'     => 0.0,
			'tax_amount'          => 0.0,
			'discount_amount'     => 0.0,
			'shipping_amount'     => 0.0,
			'refund_total'        => 0.0,
			'avg_daily_net_sales' => 0.0,
			'currency'            => get_woocommerce_currency_symbol(),
			'range'               => [
				'gregorian' => [ 'start_date' => $start_date, 'end_date' => $end_date ],
				'jalali'    => [
					'start_date' => $start_date->formatJalaliDatetime(),
					'end_date'   => $end_date->formatJalaliDatetime(),
				],
			],
		];

		$total_days = $start_date->diff( $end_date )->days + 1;

		$total_sql = "
	        WITH wc_prepared AS (
	            SELECT
	              wc_stats.order_id, wc_stats.parent_id, wc_stats.net_total, wc_stats.total_sales,
	              wc_stats.shipping_total, wc_stats.tax_total, op_data.discount_total_amount
	            FROM {$this->table_order_stats} AS wc_stats
	            INNER JOIN {$this->table_operational_data} AS op_data ON wc_stats.order_id = op_data.order_id
	            WHERE wc_stats.status IN ({$this->valid_order_statuses})
	              AND wc_stats.{$this->query_date_column} IS NOT NULL
	              AND wc_stats.{$this->query_date_column} BETWEEN %s AND %s
	        )
	        SELECT
	            SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END) AS total_order_count,
	            SUM(net_total) AS total_net_sales,
	            SUM(total_sales) AS total_sales,
	            SUM(CASE WHEN total_sales < 0 THEN total_sales ELSE 0 END) * -1 AS total_refund_amount,
	            SUM(net_total + (CASE WHEN discount_total_amount > 0 THEN discount_total_amount ELSE 0 END)) AS total_gross_sales,
	            SUM(tax_total) AS total_tax_amount,
	            SUM(discount_total_amount) AS total_discount_amount,
	            SUM(shipping_total) AS total_shipping_amount
	        FROM wc_prepared;
        ";

		$totals_row = $wpdb->get_row( $wpdb->prepare(
			$total_sql,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		if ( empty( $totals_row ) || $totals_row->total_order_count == 0 ) {
			return [];
		}

		$data_totals['order_count']     = intval( $totals_row->total_order_count );
		$data_totals['gross_sales']     = floatval( $totals_row->total_gross_sales );
		$data_totals['net_sales']       = floatval( $totals_row->total_net_sales );
		$data_totals['total_sales']     = floatval( $totals_row->total_sales );
		$data_totals['tax_amount']      = floatval( $totals_row->total_tax_amount );
		$data_totals['shipping_amount'] = floatval( $totals_row->total_shipping_amount );
		$data_totals['discount_amount'] = floatval( $totals_row->total_discount_amount );
		$data_totals['refund_total']    = floatval( $totals_row->total_refund_amount );

		if ( $data_totals['order_count'] > 0 ) {
			$data_totals['avg_order_value'] = round( $data_totals['net_sales'] / $data_totals['order_count'], 2 );
		}

		if ( $total_days > 0 ) {
			$data_totals['avg_daily_net_sales'] = round( $data_totals['net_sales'] / $total_days );
		}

		return $data_totals;
	}

	public function revenue_chart( Verta $start_date, Verta $end_date, ?string $interval = 'day' ): array {
		global $wpdb;

		$response_structure = [
			'net_sales'   => 0.0,
			'total_sales' => 0.0,
		];

		$data = $this->generate_metrics_scaffolding( $start_date, $end_date, $interval, $response_structure );

		$sql_chart_data = "
		    WITH wc_prepared AS (
		        SELECT
		            {$this->query_date_column} AS tehran_date,
		            wc_stats.net_total,
		            wc_stats.total_sales
		        FROM {$this->table_order_stats} AS wc_stats
		        WHERE wc_stats.status IN ({$this->valid_order_statuses})
		            AND wc_stats.{$this->query_date_column} IS NOT NULL
		            AND wc_stats.{$this->query_date_column} BETWEEN %s AND %s
		    )
		    SELECT 
		        tehran_date, 
		        SUM(net_total) AS daily_net_sales,
		        SUM(total_sales) AS daily_total_sales
		    FROM wc_prepared
		    GROUP BY tehran_date
		    ORDER BY tehran_date ASC;
		";

		$chart_results = $wpdb->get_results( $wpdb->prepare(
			$sql_chart_data,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		if ( empty( $chart_results ) ) {
			return $data;
		}

		foreach ( $chart_results as $row ) {

			$jalali_key = $this->get_grouping_key( $row->tehran_date, $interval );

			if ( isset( $data[ $jalali_key ] ) ) {

				$data[ $jalali_key ]['net_sales']   += floatval( $row->daily_net_sales );
				$data[ $jalali_key ]['total_sales'] += floatval( $row->daily_total_sales );

			}

		}

		return $data;
	}

	public function revenue_orders( Verta $start_date, Verta $end_date, ?string $interval = 'day', ?int $per_page = 25, ?int $page = 1 ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$empty_structure = [
			'order_count'     => 0,
			'items_count'     => 0,
			'gross_sales'     => 0.0,
			'net_sales'       => 0.0,
			'total_sales'     => 0.0,
			'tax_amount'      => 0.0,
			'discount_amount' => 0.0,
			'refund_amount'   => 0.0,
			'shipping_amount' => 0.0,
		];

		$response = [
			'orders'     => [],
			'pagination' => [
				'total_items'  => 0,
				'total_pages'  => 0,
				'current_page' => $page,
				'per_page'     => $per_page,
			],
		];

		$full_orders_scaffold = $this->generate_metrics_scaffolding(
			$start_date,
			$end_date,
			$interval,
			$empty_structure
		);

		$sql_daily_sales = "
	        WITH wc_prepared AS (
	             SELECT
	              DATE(wc_stats.{$this->query_date_column}) AS tehran_date,
	              wc_stats.order_id, wc_stats.parent_id, wc_stats.net_total, wc_stats.total_sales,
	              wc_stats.shipping_total, wc_stats.tax_total, wc_stats.num_items_sold, op_data.discount_total_amount
	            FROM {$this->table_order_stats} AS wc_stats
	            INNER JOIN {$this->table_operational_data} AS op_data ON wc_stats.order_id = op_data.order_id
	            WHERE wc_stats.status IN ({$this->valid_order_statuses})
	              AND wc_stats.{$this->query_date_column} IS NOT NULL
	              AND wc_stats.{$this->query_date_column} BETWEEN %s AND %s
	        )
	        SELECT
	            tehran_date,
	            SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END) AS daily_order_count,
	            SUM(net_total) AS daily_net_sales,
	            SUM(total_sales) AS daily_total_sales,
	            SUM(CASE WHEN total_sales < 0 THEN total_sales ELSE 0 END) * -1 AS daily_refund_amount,
	            SUM(net_total + (CASE WHEN discount_total_amount > 0 THEN discount_total_amount ELSE 0 END)) AS daily_gross_sales,
	            SUM(tax_total) AS daily_tax_amount,
	            SUM(discount_total_amount) AS daily_discount_amount,
	            SUM(shipping_total) AS daily_shipping_amount,
	            SUM(num_items_sold) AS daily_items_sold_count
	        FROM wc_prepared
	        GROUP BY tehran_date
	        ORDER BY tehran_date DESC;
	    ";

		$daily_sales_results = $wpdb->get_results( $wpdb->prepare(
			$sql_daily_sales,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		foreach ( $daily_sales_results as $row ) {

			$grouping_key = $this->get_grouping_key( $row->tehran_date, $interval );

			if ( isset( $full_orders_scaffold[ $grouping_key ] ) ) {

				$full_orders_scaffold[ $grouping_key ]['order_count']     += (int) $row->daily_order_count;
				$full_orders_scaffold[ $grouping_key ]['items_count']     += (int) $row->daily_items_sold_count;
				$full_orders_scaffold[ $grouping_key ]['gross_sales']     += (float) $row->daily_gross_sales;
				$full_orders_scaffold[ $grouping_key ]['net_sales']       += (float) $row->daily_net_sales;
				$full_orders_scaffold[ $grouping_key ]['total_sales']     += (float) $row->daily_total_sales;
				$full_orders_scaffold[ $grouping_key ]['tax_amount']      += (float) $row->daily_tax_amount;
				$full_orders_scaffold[ $grouping_key ]['discount_amount'] += (float) $row->daily_discount_amount;
				$full_orders_scaffold[ $grouping_key ]['refund_amount']   += (float) $row->daily_refund_amount;
				$full_orders_scaffold[ $grouping_key ]['shipping_amount'] += (float) $row->daily_shipping_amount;

			}

		}

		$full_orders_scaffold = array_reverse( $full_orders_scaffold, true );
		$total_items          = count( $full_orders_scaffold );

		$response['pagination']['total_items'] = $total_items;
		$response['pagination']['total_pages'] = (int) ceil( $total_items / $per_page );

		if ( $total_items <= 0 ) {
			return $response;
		}

		$response['orders'] = array_slice( $full_orders_scaffold, $offset, $per_page, true );

		return $response;
	}

	public function revenue_top_sellers( Verta $start_date, Verta $end_date ): array {
		global $wpdb;

		$data = [
			'products'   => [],
			'categories' => [],
		];

		$sql_products = "
	        WITH product_aggregation AS (
	            SELECT
	                lookup.product_id, SUM(lookup.product_qty) AS sold_quantity
	            FROM {$this->table_product_lookup} AS lookup
	            INNER JOIN {$this->table_order_stats} AS wc_stats ON lookup.order_id = wc_stats.order_id
	            WHERE wc_stats.{$this->query_date_column} BETWEEN %s AND %s
	              AND wc_stats.status IN ({$this->valid_order_statuses})
	            GROUP BY lookup.product_id
	        )
	        SELECT pa.product_id, posts.post_title AS title, pa.sold_quantity
	        FROM product_aggregation pa
	        INNER JOIN {$wpdb->posts} AS posts ON posts.ID = pa.product_id
	        WHERE posts.post_type = 'product'
	        ORDER BY pa.sold_quantity DESC
	        LIMIT 6;
        ";

		$product_results = $wpdb->get_results( $wpdb->prepare(
			$sql_products,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' ),
		) );

		foreach ( $product_results as $row ) {
			$product_id                      = intval( $row->product_id );
			$data['products'][ $product_id ] = [
				'name'  => $row->title,
				'link'  => get_post_permalink( $product_id ) ?: '#',
				'count' => intval( $row->sold_quantity ),
			];
		}

		$sql_categories = "
	        WITH category_aggregation AS (
	            SELECT term.term_id, SUM(lookup.product_qty) AS total_quantity
	            FROM {$this->table_product_lookup} AS lookup
	            INNER JOIN {$this->table_order_stats} AS wc_stats ON lookup.order_id = wc_stats.order_id
	            INNER JOIN {$wpdb->term_relationships} AS term_rel ON lookup.product_id = term_rel.object_id
	            INNER JOIN {$wpdb->term_taxonomy} AS term_tax ON term_rel.term_taxonomy_id = term_tax.term_taxonomy_id
	            INNER JOIN {$wpdb->terms} AS term ON term_tax.term_id = term.term_id
	            WHERE wc_stats.{$this->query_date_column} BETWEEN %s AND %s
	              AND term_tax.taxonomy = 'product_cat'
	              AND wc_stats.status IN ({$this->valid_order_statuses})
	            GROUP BY term.term_id
	        )
	        SELECT ca.term_id, ca.total_quantity, term.name AS term_name, term.slug AS term_slug
	        FROM category_aggregation ca
	        INNER JOIN {$wpdb->terms} AS term ON ca.term_id = term.term_id
	        ORDER BY ca.total_quantity DESC
	        LIMIT 6
        ";

		$category_results = $wpdb->get_results( $wpdb->prepare(
			$sql_categories,
			$start_date->formatGregorian( 'Y-m-d H:i:s' ),
			$end_date->formatGregorian( 'Y-m-d H:i:s' )
		) );

		foreach ( $category_results as $row ) {

			$term_slug                          = $row->term_slug;
			$permalink                          = get_term_link( $term_slug, 'product_cat' );
			$category_id                        = intval( $row->term_id );
			$data['categories'][ $category_id ] = [
				'name'  => $row->term_name,
				'link'  => is_wp_error( $permalink ) ? '#' : $permalink,
				'count' => intval( $row->total_quantity ),
			];

		}

		return $data;
	}

	public function stock_summary(): array {
		global $wpdb;

		$lowstock_threshold = intval( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		$totals_sql = "
	        WITH product_stock_data AS (
			    SELECT
			        l.stock_status,
			        l.stock_quantity,
			        pm_manage.meta_value AS manage_stock,
			        CAST(pm_price.meta_value AS DECIMAL(10,0)) AS price,
			        pm_low.meta_value AS low_stock_threshold
			    FROM {$this->table_product_meta_lookup} l
			    JOIN {$wpdb->posts} p ON l.product_id = p.ID
			    LEFT JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id AND pm_manage.meta_key = '_manage_stock'
			    LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
			    LEFT JOIN {$wpdb->postmeta} pm_low ON p.ID = pm_low.post_id AND pm_low.meta_key = '_low_stock_amount'
			    WHERE p.post_status = 'publish' AND p.post_type IN ('product', 'product_variation')
			)
			SELECT
			    SUM(CASE WHEN stock_status = 'instock' THEN 1 ELSE 0 END) AS instock_count,
			    SUM(CASE WHEN stock_status = 'outofstock' THEN 1 ELSE 0 END) AS outofstock_count,
			    SUM(CASE WHEN stock_status = 'onbackorder' THEN 1 ELSE 0 END) AS onbackorder_count,
			    SUM(CASE WHEN manage_stock = 'yes' AND stock_status = 'instock' AND stock_quantity <= IFNULL(low_stock_threshold, %d) THEN 1 ELSE 0 END) AS lowstock_count,
			    SUM(CASE WHEN manage_stock = 'yes' AND stock_status = 'instock' THEN stock_quantity * price ELSE 0 END) AS total_stock_value,
			    SUM(CASE WHEN manage_stock = 'yes' AND stock_status = 'instock' THEN stock_quantity ELSE 0 END) AS total_stock_units
			FROM product_stock_data;
        ";

		$totals = $wpdb->get_row( $wpdb->prepare( $totals_sql, $lowstock_threshold ) );

		if ( empty( $totals ) ) {
			return [];
		}

		return [
			'total_stock_value' => floatval( $totals->total_stock_value ?? 0.0 ),
			'total_stock_units' => intval( $totals->total_stock_units ?? 0 ),
			'instock_count'     => intval( $totals->instock_count ?? 0 ),
			'outofstock_count'  => intval( $totals->outofstock_count ?? 0 ),
			'onbackorder_count' => intval( $totals->onbackorder_count ?? 0 ),
			'lowstock_count'    => intval( $totals->lowstock_count ?? 0 ),
			'currency'          => get_woocommerce_currency_symbol(),
		];
	}

	public function stock_products( ?string $status = null, ?int $per_page = 25, ?int $page = 1 ): array {
		global $wpdb;
		$lowstock_threshold = intval( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
		$offset             = ( $page - 1 ) * $per_page;

		$cte_sql = "
	        WITH product_meta_data AS (
	            SELECT
	                p.ID, p.post_title, p.post_parent, p.post_type,
	                MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) AS manage_stock,
	                CAST(MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) AS DECIMAL(10, 0)) AS price,
	                CAST(MAX(CASE WHEN pm.meta_key = '_low_stock_amount' THEN pm.meta_value END) AS SIGNED) AS low_stock_threshold
	            FROM {$wpdb->posts} p
	            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	            WHERE p.post_status = 'publish' AND p.post_type IN ('product', 'product_variation')
	            GROUP BY p.ID
	        )
	    ";

		$where_clause = '';
		switch ( $status ) {
			case 'instock':
				$where_clause = "l.stock_status = 'instock'";
				break;
			case 'outofstock':
				$where_clause = "l.stock_status = 'outofstock'";
				break;
			case 'onbackorder':
				$where_clause = "l.stock_status = 'onbackorder'";
				break;
			case 'lowstock':
				$where_clause = "l.stock_status = 'instock' AND l.stock_quantity <= IFNULL(pmd.low_stock_threshold, {$lowstock_threshold})";
				break;
			default:
				$where_clause = "l.stock_status IN ('instock','outofstock','onbackorder')";
				break;
		}

		$total_items_sql = "{$cte_sql} SELECT COUNT(pmd.ID) FROM product_meta_data pmd INNER JOIN {$this->table_product_meta_lookup} l ON pmd.ID = l.product_id WHERE {$where_clause};";
		$total_items     = $wpdb->get_var( $total_items_sql );

		$sql_query_string = "
	        {$cte_sql}
	        SELECT
	            pmd.ID AS product_id, pmd.post_title AS name, pmd.post_parent AS parent_id,
	            CASE
	                WHEN pmd.post_type = 'product_variation' THEN 'variation'
	                WHEN EXISTS(SELECT 1 FROM {$wpdb->posts} WHERE post_parent = pmd.ID AND post_type = 'product_variation') THEN 'variable'
	                ELSE 'simple'
	            END AS product_type,
	            l.sku, l.stock_status, l.stock_quantity,
	            pmd.manage_stock, pmd.price, pmd.low_stock_threshold
	        FROM product_meta_data pmd
	        INNER JOIN {$this->table_product_meta_lookup} l ON pmd.ID = l.product_id
	        WHERE {$where_clause}
	        ORDER BY (CASE WHEN pmd.post_parent = 0 THEN pmd.ID ELSE pmd.post_parent END), pmd.post_parent, pmd.ID ASC
	        LIMIT %d OFFSET %d;
        ";
		$products         = $wpdb->get_results( $wpdb->prepare( $sql_query_string, $per_page, $offset ) );

		$product_list = [];

		foreach ( $products as $product ) {

			$is_managed = ( $product->manage_stock === 'yes' );
			$threshold  = $product->low_stock_threshold ?? $lowstock_threshold;

			$current_status = $product->stock_status;

			if ( $is_managed && $current_status === 'instock' && ( intval( $product->stock_quantity ) <= intval( $threshold ) ) ) {
				$current_status = 'lowstock';
			}

			$product_data = [
				'id'        => intval( $product->product_id ),
				'parent_id' => intval( $product->parent_id ),
				'type'      => $product->product_type,
				'name'      => $product->name,
				'sku'       => $product->sku ?: '',
				'manage'    => $is_managed,
				'status'    => $current_status,
				'permalink' => urldecode( get_permalink( $product->parent_id > 0 ? $product->parent_id : $product->product_id ) ),
				'edit_url'  => $this->get_product_edit_url( $product ),

			];

			if ( $product->product_type === 'variable' ) {

				$product_data['stock_count'] = null;
				$product_data['price']       = null;

			} else {

				$product_data['stock_count'] = intval( $product->stock_quantity );
				$product_data['price']       = floatval( $product->price );

			}

			$product_list[] = $product_data;

		}

		return [
			'products'   => $product_list,
			'pagination' => [
				'total_items'  => intval( $total_items ),
				'total_pages'  => intval( ceil( $total_items / $per_page ) ),
				'per_page'     => intval( $per_page ),
				'current_page' => intval( $page ),
			],
		];
	}

	public function stock_export( ?string $status = null ) {
		global $wpdb;

		$lowstock_threshold = intval( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		$cte_sql = "
        WITH product_meta_data AS (
            SELECT
                p.ID, p.post_title, p.post_parent, p.post_type,
                MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) AS manage_stock,
                CAST(MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) AS DECIMAL(10, 0)) AS price,
                CAST(MAX(CASE WHEN pm.meta_key = '_low_stock_amount' THEN pm.meta_value END) AS SIGNED) AS low_stock_threshold
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish' AND p.post_type IN ('product', 'product_variation')
            GROUP BY p.ID
        )
    ";

		$where_clause = '';
		switch ( $status ) {
			case 'instock':
				$where_clause = "l.stock_status = 'instock'";
				break;
			case 'outofstock':
				$where_clause = "l.stock_status = 'outofstock'";
				break;
			case 'onbackorder':
				$where_clause = "l.stock_status = 'onbackorder'";
				break;
			case 'lowstock':
				$where_clause = "l.stock_status = 'instock' AND l.stock_quantity <= IFNULL(pmd.low_stock_threshold, {$lowstock_threshold})";
				break;
			default:
				$where_clause = "l.stock_status IN ('instock','outofstock','onbackorder')";
				break;
		}

		$sql_query_string = "
        {$cte_sql}
        SELECT
            pmd.ID AS product_id, pmd.post_title AS name, pmd.post_parent AS parent_id,
            CASE
                WHEN pmd.post_type = 'product_variation' THEN 'variation'
                WHEN EXISTS(SELECT 1 FROM {$wpdb->posts} WHERE post_parent = pmd.ID AND post_type = 'product_variation') THEN 'variable'
                ELSE 'simple'
            END AS product_type,
            l.sku, l.stock_status, l.stock_quantity,
            pmd.manage_stock, pmd.price, pmd.low_stock_threshold
        FROM product_meta_data pmd
        INNER JOIN {$this->table_product_meta_lookup} l ON pmd.ID = l.product_id
        WHERE {$where_clause}
        ORDER BY (CASE WHEN pmd.post_parent = 0 THEN pmd.ID ELSE pmd.post_parent END), pmd.post_parent, pmd.ID ASC;
    ";

		$products = $wpdb->get_results( $sql_query_string );

		if ( empty( $products ) ) {
			wp_die( 'محصول با وضعیت انبار مد نظر شما یافت نشد.' );
		}

		$product_list = [];
		foreach ( $products as $product ) {
			$is_managed     = ( $product->manage_stock === 'yes' );
			$threshold      = $product->low_stock_threshold ?? $lowstock_threshold;
			$current_status = $product->stock_status;

			if ( $is_managed && $current_status === 'instock' && ( intval( $product->stock_quantity ) <= intval( $threshold ) ) ) {
				$current_status = 'lowstock';
			}

			$product_data   = [
				'id'          => intval( $product->product_id ),
				'parent_id'   => intval( $product->parent_id ),
				'type'        => $product->product_type,
				'name'        => $product->name,
				'sku'         => $product->sku ?: '',
				'manage'      => $is_managed ? 'Yes' : 'No',
				'status'      => $current_status,
				'permalink'   => urldecode( get_permalink( $product->parent_id > 0 ? $product->parent_id : $product->product_id ) ),
				'stock_count' => ( $product->product_type === 'variable' ) ? null : intval( $product->stock_quantity ),
				'price'       => ( $product->product_type === 'variable' ) ? null : floatval( $product->price ),
			];
			$product_list[] = $product_data;
		}

		$filename = 'stock-report-' . ( $status ? sanitize_key( $status ) . '-' : '' ) . verta()->timezone( 'Asia/Tehran' )->format( 'Y-m-d_H:i' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );

		$headers = [
			'شناسه',
			'شناسه والد',
			'نوع',
			'نام',
			'شناسه انبار',
			'مدیریت موجودی',
			'وضعیت',
			'لینک',
			'موجودی',
			'قیمت',
		];

		fputcsv( $output, $headers );

		foreach ( $product_list as $prod ) {
			fputcsv( $output, $prod );
		}

		fclose( $output );
		exit;
	}

	private function generate_metrics_scaffolding( Verta $start_date, Verta $end_date, string $interval, array $default_structure ): array {

		$scaffolding = [];


		$period_start = new \DateTime( $start_date->formatGregorian( 'Y-m-d H:i:s' ) );
		$period_end   = new \DateTime( $end_date->formatGregorian( 'Y-m-d H:i:s' ) );

		$date_period = new \DatePeriod( $period_start, new \DateInterval( 'P1D' ), $period_end );

		foreach ( $date_period as $date ) {

			$jalali_key                 = $this->get_grouping_key( $date->format( 'Y-m-d' ), $interval );
			$scaffolding[ $jalali_key ] = $default_structure;
		}

		return $scaffolding;
	}

	public function get_grouping_key( string $gregorian_date_str, string $interval ): string {
		$date              = verta( strtotime( $gregorian_date_str ) );
		$current_day_index = $date->dayOfWeek;

		switch ( $interval ) {
			case 'week':
				$week_start = $date->copy()->subDays( $current_day_index )->startDay();
				$week_end   = $week_start->copy()->addDays( 6 )->endDay();
				$key        = $week_end->format( 'Y/m/d' ) . ' - ' . $week_start->format( 'Y/m/d' );
				break;

			case 'month':
				$key = $date->format( 'F Y' );
				break;

			case 'quarter':
				$key = $date->format( 'Q Y' );
				break;

			case 'year':
				$key = $date->format( 'Y' );
				break;

			case 'day':
			default:
				$key = $date->format( 'Y-m-d' );
		}

		return $key;
	}

	public function get_valid_order_statuses(): string {
		global $wpdb;

		$registered   = wc_get_order_statuses();
		$unregistered = $this->get_unregistered_order_statuses();
		$all_statuses = array_merge( $registered, $unregistered );

		$formatted = Settings::get_order_statuses( $all_statuses );
		$all_slugs = array_keys( $formatted );

		$excluded_option = get_option( 'woocommerce_excluded_report_order_statuses', [] );
		$hard_exclusions = [ 'pending', 'failed', 'cancelled', 'auto-draft', 'trash', 'refunded' ];

		$excluded = array_unique( array_merge( $excluded_option, $hard_exclusions ) );
		$included = array_diff( $all_slugs, $excluded );

		$prefixed = array_map( function ( $status ) {
			return 'wc-' . $status;
		}, $included );

		if ( empty( $prefixed ) ) {
			return '';
		}

		$placeholders = implode( ',', array_fill( 0, count( $prefixed ), '%s' ) );

		return $wpdb->prepare( $placeholders, $prefixed );
	}

	public function get_unregistered_order_statuses(): array {
		$registered = wc_get_order_statuses();
		$all_synced = OrdersDataStore::get_all_statuses();

		$unregistered = array_diff( $all_synced, array_keys( $registered ) );

		if ( empty( $unregistered ) ) {
			return [];
		}

		$formatted = Settings::get_order_statuses(
			array_fill_keys( $unregistered, '' )
		);

		$keys = array_keys( $formatted );

		return array_combine( $keys, $keys );
	}

	public function get_query_date_column() {
		$column = get_option( 'woocommerce_date_type', 'date_paid' );

		return ! in_array( $column, [ 'date_created', 'date_paid', 'date_completed' ] ) ?: 'date_created';
	}

	public function get_product_edit_url( $product ): ?string {
		$product_id = $product->parent_id > 0 ? $product->parent_id : $product->product_id;

		if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
			return null;
		}

		$base_url = admin_url( 'post.php' );

		$edit_url = add_query_arg(
			[
				'post'   => $product_id,
				'action' => 'edit',
			],
			$base_url
		);

		return $edit_url;
	}

	public function get_state( ?string $state ): ?string {

		if ( is_numeric( $state ) && function_exists( 'PWS' ) ) {
			return PWS()::get_state( $state );
		}

		return Persian_Woocommerce_Address::$states[ $state ] ?? $state;
	}

	public function get_city( ?string $city ): ?string {

		if ( is_numeric( $city ) && function_exists( 'PWS' ) ) {
			return PWS()::get_city( $city );
		}

		return $city;
	}

	/**
	 * DEBUG SECTION
	 */
	public function debug_revenue( string $start_date, string $end_date, ?string $interval = 'day' ): array {
		global $wpdb;

		$revenue_defaults = [
			'order_count'    => 0,
			'net_total'      => 0.0,
			'shipping_total' => 0.0,
			'tax_total'      => 0.0,
			'discount_total' => 0.0,
		];

		$metrics_scaffolding = $this->generate_metrics_scaffolding( $start_date, $end_date, $interval, $revenue_defaults );

		$daily_summary_sql = "
        SELECT
            DATE(wc_stats.{$this->query_date_column}) as metric_date, 
            COUNT(wc_stats.order_id) as order_count,
            SUM(wc_stats.net_total) as net_total,
            SUM(wc_stats.shipping_total) as shipping_total,
            SUM(wc_stats.tax_total) as tax_total,
            SUM(op_data.discount_total_amount) as discount_total
        FROM {$this->table_order_stats} AS wc_stats
        INNER JOIN {$this->table_operational_data} AS op_data ON wc_stats.order_id = op_data.order_id
        WHERE
            wc_stats.status IN ({$this->valid_order_statuses})
            AND wc_stats.{$this->query_date_column} IS NOT NULL
            AND wc_stats.{$this->query_date_column} BETWEEN %s AND %s
        GROUP BY metric_date
        ORDER BY metric_date ASC
    ";

		$db_results = $wpdb->get_results( $wpdb->prepare( $daily_summary_sql, $start_date, $end_date ), OBJECT_K );

		if ( $db_results ) {

			foreach ( $db_results as $gregorian_day => $row ) {

				$jalali_key = $this->get_grouping_key( $gregorian_day, $interval );

				if ( isset( $metrics_scaffolding[ $jalali_key ] ) ) {

					$metrics_scaffolding[ $jalali_key ]['order_count']    += intval( $row->order_count );
					$metrics_scaffolding[ $jalali_key ]['net_total']      += floatval( $row->net_total );
					$metrics_scaffolding[ $jalali_key ]['shipping_total'] += floatval( $row->shipping_total );
					$metrics_scaffolding[ $jalali_key ]['tax_total']      += floatval( $row->tax_total );
					$metrics_scaffolding[ $jalali_key ]['discount_total'] += floatval( $row->discount_total );

				}

			}

		}

		return [
			'debug_range'         => [
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'interval'   => $interval,
			],
			'metrics_by_interval' => $metrics_scaffolding,
		];
	}
}
