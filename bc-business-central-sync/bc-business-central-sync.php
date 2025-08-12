<?php

/**
 * Business Central to WooCommerce Sync Plugin
 *
 * A professional WordPress plugin that seamlessly integrates Microsoft Dynamics 365 
 * Business Central with WooCommerce, providing automated product synchronization,
 * customer-specific pricing, and integrated phone authentication for B2B operations.
 *
 * @link              https://malmsteypa.is
 * @since             1.0.0
 * @package           BC_Business_Central_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       Business Central to WooCommerce Sync
 * Plugin URI:        https://malmsteypa.is/business-central-sync
 * Description:       Professional B2B integration plugin that automatically syncs products from Microsoft Dynamics 365 Business Central to WooCommerce with customer-specific pricing, integrated phone authentication, and comprehensive company management.
 * Version:           1.0.0
 * Author:            Malmsteypa
 * Author URI:        https://malmsteypa.is
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bc-business-central-sync
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 * Network:           false
 * Update URI:        https://malmsteypa.is/business-central-sync
 * HPOS Compatible:   true
 */

// =============================================================================
// SECURITY CHECK
// =============================================================================

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// =============================================================================
// PLUGIN CONSTANTS
// =============================================================================

/**
 * Plugin version.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin minimum WordPress version.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_MIN_WP_VERSION', '5.0' );

/**
 * Plugin minimum WooCommerce version.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_MIN_WC_VERSION', '5.0' );

/**
 * Plugin minimum PHP version.
 * 
 * @since 1.0.0
 * @var string
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_MIN_PHP_VERSION', '7.4' );

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_init() {
	// Check system requirements
	if ( ! bc_business_central_sync_check_requirements() ) {
		return;
	}

	// Load the main plugin class
	require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync.php';
	
	// Initialize the plugin
	$plugin = new BC_Business_Central_Sync();
	$plugin->run();
}

// =============================================================================
// REQUIREMENT CHECKS
// =============================================================================

/**
 * Check if the system meets plugin requirements.
 *
 * @since 1.0.0
 * @return bool True if requirements are met, false otherwise.
 */
function bc_business_central_sync_check_requirements() {
	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), BC_BUSINESS_CENTRAL_SYNC_MIN_WP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bc_business_central_sync_wordpress_version_notice' );
		return false;
	}

	// Check PHP version
	if ( version_compare( PHP_VERSION, BC_BUSINESS_CENTRAL_SYNC_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bc_business_central_sync_php_version_notice' );
		return false;
	}

	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bc_business_central_sync_woocommerce_notice' );
		return false;
	}

	// Check WooCommerce version
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, BC_BUSINESS_CENTRAL_SYNC_MIN_WC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bc_business_central_sync_woocommerce_version_notice' );
		return false;
	}

	return true;
}

// =============================================================================
// ADMIN NOTICES
// =============================================================================

/**
 * Display WordPress version requirement notice.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_wordpress_version_notice() {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: Required WordPress version */
		esc_html__( 'Business Central Sync requires WordPress version %s or higher.', 'bc-business-central-sync' ),
		esc_html( BC_BUSINESS_CENTRAL_SYNC_MIN_WP_VERSION )
	);
	echo '</p></div>';
}

/**
 * Display PHP version requirement notice.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_php_version_notice() {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: Required PHP version */
		esc_html__( 'Business Central Sync requires PHP version %s or higher.', 'bc-business-central-sync' ),
		esc_html( BC_BUSINESS_CENTRAL_SYNC_MIN_PHP_VERSION )
	);
	echo '</p></div>';
}

/**
 * Display WooCommerce requirement notice.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'Business Central Sync requires WooCommerce to be installed and activated.', 'bc-business-central-sync' );
	echo '</p></div>';
}

/**
 * Display WooCommerce version requirement notice.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_woocommerce_version_notice() {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: Required WooCommerce version */
		esc_html__( 'Business Central Sync requires WooCommerce version %s or higher.', 'bc-business-central-sync' ),
		esc_html( BC_BUSINESS_CENTRAL_SYNC_MIN_WC_VERSION )
	);
	echo '</p></div>';
}

// =============================================================================
// ACTIVATION & DEACTIVATION HOOKS
// =============================================================================

/**
 * Plugin activation handler.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_activate() {
	// Check requirements before activation
	if ( ! bc_business_central_sync_check_requirements() ) {
		deactivate_plugins( BC_BUSINESS_CENTRAL_SYNC_BASENAME );
		wp_die( 
			esc_html__( 'Plugin activation failed due to unmet requirements.', 'bc-business-central-sync' ),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}

	require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-activator.php';
	BC_Business_Central_Sync_Activator::activate();
}

/**
 * Plugin deactivation handler.
 *
 * @since 1.0.0
 */
function bc_business_central_sync_deactivate() {
	require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-deactivator.php';
	BC_Business_Central_Sync_Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'bc_business_central_sync_activate' );
register_deactivation_hook( __FILE__, 'bc_business_central_sync_deactivate' );

// =============================================================================
// PLUGIN LAUNCH
// =============================================================================

// Initialize the plugin after WordPress is loaded
add_action( 'plugins_loaded', 'bc_business_central_sync_init' );
