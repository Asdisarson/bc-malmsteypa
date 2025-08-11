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

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

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
 * The code that runs during plugin activation.
 * 
 * This action is documented in includes/class-bc-business-central-sync-activator.php
 * 
 * @since 1.0.0
 */
function activate_bc_business_central_sync() {
	require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-activator.php';
	BC_Business_Central_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * 
 * This action is documented in includes/class-bc-business-central-sync-deactivator.php
 * 
 * @since 1.0.0
 */
function deactivate_bc_business_central_sync() {
	require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-deactivator.php';
	BC_Business_Central_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bc_business_central_sync' );
register_deactivation_hook( __FILE__, 'deactivate_bc_business_central_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
function run_bc_business_central_sync() {
	$plugin = new BC_Business_Central_Sync();
	$plugin->run();
}

// Initialize the plugin
run_bc_business_central_sync();
