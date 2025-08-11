<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Business_Central_Sync_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( 'This plugin requires WooCommerce to be installed and activated.' );
		}

		// Create custom table for sync logs if needed
		global $wpdb;
		$table_name = $wpdb->prefix . 'bc_sync_logs';
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			product_id varchar(255) NOT NULL,
			bc_product_code varchar(255) NOT NULL,
			sync_status varchar(50) NOT NULL,
			sync_date datetime DEFAULT CURRENT_TIMESTAMP,
			error_message text,
			PRIMARY KEY  (id)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		// Create pricelists table
		$pricelists_table = $wpdb->prefix . 'bc_pricelists';
		$sql_pricelists = "CREATE TABLE $pricelists_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_pricelist_id varchar(255) NOT NULL,
			code varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			currency_code varchar(10) NOT NULL,
			last_modified datetime,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_pricelist_id (bc_pricelist_id),
			UNIQUE KEY code (code)
		) $charset_collate;";
		
		dbDelta( $sql_pricelists );
		
		// Create pricelist lines table
		$pricelist_lines_table = $wpdb->prefix . 'bc_pricelist_lines';
		$sql_pricelist_lines = "CREATE TABLE $pricelist_lines_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_line_id varchar(255) NOT NULL,
			pricelist_id mediumint(9) NOT NULL,
			item_id bigint(20) unsigned,
			bc_item_id varchar(255) NOT NULL,
			item_number varchar(100) NOT NULL,
			unit_price decimal(10,2) NOT NULL,
			currency_code varchar(10) NOT NULL,
			starting_date datetime,
			ending_date datetime,
			minimum_quantity decimal(10,2) DEFAULT 1,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_line_id (bc_line_id),
			KEY pricelist_id (pricelist_id),
			KEY item_id (item_id),
			KEY item_number (item_number)
		) $charset_collate;";
		
		dbDelta( $sql_pricelist_lines );
		
		// Create customer companies table
		$customer_companies_table = $wpdb->prefix . 'bc_customer_companies';
		$sql_customer_companies = "CREATE TABLE $customer_companies_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_customer_id varchar(255) NOT NULL,
			customer_number varchar(100) NOT NULL,
			customer_name varchar(255) NOT NULL,
			pricelist_id mediumint(9),
			bc_pricelist_id varchar(255),
			pricelist_code varchar(100),
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_customer_id (bc_customer_id),
			UNIQUE KEY customer_number (customer_number),
			KEY pricelist_id (pricelist_id)
		) $charset_collate;";
		
		dbDelta( $sql_customer_companies );
		
		// Create Dokobit companies table
		$dokobit_companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$sql_dokobit_companies = "CREATE TABLE $dokobit_companies_table (
			id int(11) NOT NULL AUTO_INCREMENT,
			company_name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		
		dbDelta( $sql_dokobit_companies );
		
		// Create Dokobit user phones table
		$dokobit_user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
		$sql_dokobit_user_phones = "CREATE TABLE $dokobit_user_phones_table (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			phone_number varchar(50) NOT NULL,
			personal_code varchar(20) DEFAULT NULL,
			company_id int(11) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_number (phone_number),
			KEY user_id (user_id),
			KEY company_id (company_id),
			KEY personal_code (personal_code)
		) $charset_collate;";
		
		dbDelta( $sql_dokobit_user_phones );
		
		// Set default options
		add_option( 'bc_sync_enabled', 'no' );
		add_option( 'bc_api_url', '' );
		add_option( 'bc_company_id', '' );
		add_option( 'bc_client_id', '' );
		add_option( 'bc_client_secret', '' );
		add_option( 'bc_sync_interval', 'daily' );
		add_option( 'bc_last_sync', '' );
		add_option( 'bc_sync_pricelists', 'no' );
		add_option( 'bc_sync_customers', 'no' );
		add_option( 'bc_dokobit_api_endpoint', 'https://developers.dokobit.com' );
		add_option( 'bc_dokobit_api_key', '' );
	}

}
