<?php
/**
 * Database Fix Script for BC Business Central Sync
 * 
 * Run this script to manually create the missing database tables.
 * Place this file in your WordPress root directory and access it via browser.
 * 
 * WARNING: This script should only be used for development/testing.
 * Remove this file after use.
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

echo '<h1>BC Business Central Sync - Database Fix</h1>';

// Include the database migration class
require_once(plugin_dir_path(__FILE__) . 'includes/class-bc-database-migration.php');

try {
    // Force recreate all tables to fix any existing issues
    BC_Database_Migration::force_recreate_tables();
    
    echo '<p style="color: green;">✅ Database tables recreated successfully!</p>';
    
    // Check if tables exist
    global $wpdb;
    $tables_to_check = [
        $wpdb->prefix . 'bc_dokobit_companies',
        $wpdb->prefix . 'bc_pricelists', 
        $wpdb->prefix . 'bc_company_pricelists'
    ];
    
    echo '<h2>Table Status:</h2>';
    foreach ($tables_to_check as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        $status = $exists ? '✅ Exists' : '❌ Missing';
        echo "<p><strong>$table:</strong> $status</p>";
    }
    
    // Check database version
    $db_version = get_option('bc_business_central_sync_db_version', 'Not set');
    echo "<p><strong>Database Version:</strong> $db_version</p>";
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error creating tables: ' . esc_html($e->getMessage()) . '</p>';
    
    // Show detailed error information
    echo '<h2>Error Details:</h2>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}

echo '<p><a href="' . admin_url('admin.php?page=bc-business-central-sync') . '">Return to Plugin Admin</a></p>';
echo '<p><strong>Remember to delete this file after use!</strong></p>';
