<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/your-company/bc-business-central-sync
 * @since             1.0.0
 * @package           BC_Business_Central_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       Business Central to WooCommerce Sync
 * Plugin URI:        https://github.com/your-company/bc-business-central-sync
 * Description:       Fetches products from Business Central and adds them to WooCommerce as drafts for review and approval.
 * Version:           1.0.0
 * Author:            Your Company
 * Author URI:        https://your-company.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bc-business-central-sync
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BC_BUSINESS_CENTRAL_SYNC_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bc-business-central-sync-activator.php
 */
function activate_bc_business_central_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bc-business-central-sync-activator.php';
	BC_Business_Central_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bc-business-central-sync-deactivator.php
 */
function deactivate_bc_business_central_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bc-business-central-sync-deactivator.php';
	BC_Business_Central_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bc_business_central_sync' );
register_deactivation_hook( __FILE__, 'deactivate_bc_business_central_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bc-business-central-sync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bc_business_central_sync() {

	$plugin = new BC_Business_Central_Sync();
	$plugin->run();

}
run_bc_business_central_sync();
