<?php
/**
 * Uninstall Business Central Connector Plugin
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('bcc_settings');

// Remove any transients
delete_transient('bcc_connection_status');
delete_transient('bcc_api_token');

// Clean up any custom tables if they exist
global $wpdb;

// Remove any custom capabilities if they were added
$role = get_role('administrator');
if ($role) {
    $role->remove_cap('manage_business_central');
}

// Clear any cached data
wp_cache_flush();
