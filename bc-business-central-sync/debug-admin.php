<?php
/**
 * BC Sync Admin Menu
 * 
 * Simple, direct admin menu registration for Business Central Sync
 * Bypasses complex class system for reliable admin menu functionality
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add BC Sync admin menu - simple and direct
function bc_sync_admin_menu() {
	add_menu_page(
		'BC Sync',
		'BC Sync',
		'manage_woocommerce',
		'bc-business-central-sync',
		'bc_sync_admin_page',
		'dashicons-update',
		30
	);
	
	// Add submenus
	add_submenu_page(
		'bc-business-central-sync',
		'Pricelists',
		'Pricelists',
		'manage_woocommerce',
		'bc-pricelist-management',
		'bc_pricelist_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Companies',
		'Companies',
		'manage_woocommerce',
		'bc-company-management',
		'bc_company_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Customers',
		'Customers',
		'manage_woocommerce',
		'bc-customer-management',
		'bc_customer_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Dokobit Auth',
		'Dokobit Auth',
		'manage_woocommerce',
		'bc-dokobit-auth',
		'bc_dokobit_auth_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'User Management',
		'User Management',
		'manage_woocommerce',
		'bc-user-management',
		'bc_user_management_page'
	);
}

// BC Sync main page callback
function bc_sync_admin_page() {
	// Check permissions first
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the simple admin display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-simple-admin-display.php';
}

// BC Sync submenu callbacks
function bc_pricelist_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	echo '<div class="wrap">';
	echo '<h1>Pricelist Management</h1>';
	echo '<p>Pricelist management page - working!</p>';
	echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '">← Back to BC Sync</a></p>';
	echo '</div>';
}

function bc_company_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	echo '<div class="wrap">';
	echo '<h1>Company Management</h1>';
	echo '<p>Company management page - working!</p>';
	echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '">← Back to BC Sync</a></p>';
	echo '</div>';
}

function bc_customer_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	echo '<div class="wrap">';
	echo '<h1>Customer Management</h1>';
	echo '<p>Customer management page - working!</p>';
	echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '">← Back to BC Sync</a></p>';
	echo '</div>';
}

function bc_dokobit_auth_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	echo '<div class="wrap">';
	echo '<h1>Dokobit Authentication</h1>';
	echo '<p>Dokobit authentication page - working!</p>';
	echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '">← Back to BC Sync</a></p>';
	echo '</div>';
}

function bc_user_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the user management display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-user-management-admin-display.php';
}

// Hook the BC Sync admin menu
add_action( 'admin_menu', 'bc_sync_admin_menu' );
