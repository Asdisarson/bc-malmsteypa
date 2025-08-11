<?php

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'bc_sync_enabled' );
delete_option( 'bc_api_url' );
delete_option( 'bc_company_id' );
delete_option( 'bc_client_id' );
delete_option( 'bc_client_secret' );
delete_option( 'bc_sync_interval' );
delete_option( 'bc_last_sync' );
delete_option( 'bc_sync_pricelists' );
delete_option( 'bc_sync_customers' );
delete_option( 'bc_dokobit_api_endpoint' );
delete_option( 'bc_dokobit_api_key' );
delete_option( 'bc_last_companies_sync' );
delete_option( 'bc_last_customers_companies_sync' );

// Clear scheduled events
wp_clear_scheduled_hook( 'bc_sync_products_cron' );

// Drop custom tables
global $wpdb;
$table_name = $wpdb->prefix . 'bc_sync_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

$pricelists_table = $wpdb->prefix . 'bc_pricelists';
$wpdb->query( "DROP TABLE IF EXISTS $pricelists_table" );

$pricelist_lines_table = $wpdb->prefix . 'bc_pricelist_lines';
$wpdb->query( "DROP TABLE IF EXISTS $pricelist_lines_table" );

$customer_companies_table = $wpdb->prefix . 'bc_customer_companies';
$wpdb->query( "DROP TABLE IF EXISTS $customer_companies_table" );

// Drop Dokobit tables
$dokobit_companies_table = $wpdb->prefix . 'bc_dokobit_companies';
$wpdb->query( "DROP TABLE IF EXISTS $dokobit_companies_table" );

$dokobit_user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
$wpdb->query( "DROP TABLE IF EXISTS $dokobit_user_phones_table" );

// Remove all product meta data related to Business Central
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} 
	WHERE meta_key IN ('_bc_product_number', '_bc_product_id', '_bc_last_sync', '_bc_unit_cost', '_bc_inventory', '_bc_blocked')"
);
