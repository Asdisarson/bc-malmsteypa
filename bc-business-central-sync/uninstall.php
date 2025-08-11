<?php

/**
 * Uninstall BC Business Central Sync Plugin
 *
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data, options, and database tables.
 *
 * @package    BC_Business_Central_Sync
 * @since      1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user has permission to uninstall
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

// Define plugin constants if not already defined
if ( ! defined( 'BC_BUSINESS_CENTRAL_SYNC_PATH' ) ) {
	define( 'BC_BUSINESS_CENTRAL_SYNC_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Remove all plugin data
 */
function bc_business_central_sync_uninstall() {
	global $wpdb;

	// Remove all plugin options
	$plugin_options = array(
		'bc_sync_enabled',
		'bc_api_url',
		'bc_company_id',
		'bc_client_id',
		'bc_client_secret',
		'bc_sync_interval',
		'bc_last_sync',
		'bc_sync_pricelists',
		'bc_sync_customers',
		'bc_dokobit_api_endpoint',
		'bc_dokobit_api_key',
		'bc_last_companies_sync',
		'bc_last_customers_companies_sync',
		'bc_plugin_version',
		'bc_activation_date',
	);

	foreach ( $plugin_options as $option ) {
		delete_option( $option );
	}

	// Remove all plugin transients
	$transients = array(
		'bc_sync_in_progress',
		'bc_last_sync_status',
		'bc_api_connection_status',
		'bc_dokobit_connection_status',
	);

	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}

	// Remove all plugin user meta
	$user_meta_keys = array(
		'bc_customer_company_id',
		'bc_customer_number',
		'bc_authenticated_at',
		'bc_phone_number',
		'bc_personal_code',
	);

	$users = get_users( array( 'fields' => 'ID' ) );
	foreach ( $users as $user_id ) {
		foreach ( $user_meta_keys as $meta_key ) {
			delete_user_meta( $user_id, $meta_key );
		}
	}

	// Remove all plugin post meta
	$post_meta_keys = array(
		'_bc_product_number',
		'_bc_unit_cost',
		'_bc_inventory',
		'_bc_blocked',
		'_bc_last_sync',
		'_bc_sync_status',
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (" . implode( ',', array_fill( 0, count( $post_meta_keys ), '%s' ) ) . ")",
			$post_meta_keys
		)
	);

	// Remove all plugin database tables
	$tables = array(
		$wpdb->prefix . 'bc_sync_logs',
		$wpdb->prefix . 'bc_pricelists',
		$wpdb->prefix . 'bc_pricelist_lines',
		$wpdb->prefix . 'bc_customer_companies',
		$wpdb->prefix . 'bc_dokobit_companies',
		$wpdb->prefix . 'bc_dokobit_user_phones',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Clear any scheduled cron events
	wp_clear_scheduled_hook( 'bc_sync_products_cron' );
	wp_clear_scheduled_hook( 'bc_sync_pricelists_cron' );

	// Remove custom cron intervals
	remove_filter( 'cron_schedules', 'bc_add_cron_interval' );

	// Clear rewrite rules
	flush_rewrite_rules();

	// Log uninstall action
	error_log( 'BC Business Central Sync plugin uninstalled and all data removed.' );
}

// Execute uninstall function
bc_business_central_sync_uninstall();
