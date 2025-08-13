<?php

/**
 * Autoloader for BC Business Central Sync Plugin
 *
 * Handles loading classes from the new organized directory structure.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Autoloader {

    /**
     * Class mapping for the new directory structure.
     *
     * @var array
     */
    private static $class_map = [
        // Core classes
        'BC_Business_Central_Sync' => 'src/Core/class-bc-business-central-sync.php',
        'BC_Business_Central_Sync_Loader' => 'src/Core/class-bc-business-central-sync-loader.php',
        'BC_Business_Central_Sync_i18n' => 'src/Core/class-bc-business-central-sync-i18n.php',
        'BC_Business_Central_Sync_Activator' => 'src/Core/class-bc-business-central-sync-activator.php',
        'BC_Business_Central_Sync_Deactivator' => 'src/Core/class-bc-business-central-sync-deactivator.php',
        'BC_Plugin_Core' => 'src/Core/class-bc-plugin-core.php',
        
        // Feature classes
        'BC_Business_Central_API' => 'src/Features/class-bc-business-central-api.php',
        'BC_Company_Manager' => 'src/Features/class-bc-company-manager.php',
        'BC_Customer_Pricing' => 'src/Features/class-bc-customer-pricing.php',
        'BC_Pricelist_Manager' => 'src/Features/class-bc-pricelist-manager.php',
        'BC_Simple_Pricing' => 'src/Features/class-bc-simple-pricing.php',
        'BC_WooCommerce_Manager' => 'src/Features/class-bc-woocommerce-manager.php',
        'BC_WooCommerce_Pricing' => 'src/Features/class-bc-woocommerce-pricing.php',
        
        // Admin classes
        'BC_Business_Central_Sync_Admin' => 'src/Admin/class-bc-business-central-sync-admin.php',
        'BC_OAuth_Settings' => 'src/Admin/class-bc-oauth-settings.php',
        
        // Public classes
        'BC_Business_Central_Sync_Public' => 'src/Public/class-bc-business-central-sync-public.php',
        
        // Database classes
        'BC_Database_Migration' => 'src/Database/class-bc-database-migration.php',
        'BC_Dokobit_Database' => 'src/Database/class-bc-dokobit-database.php',
        
        // API classes
        'BC_Dokobit_API' => 'src/Api/class-bc-dokobit-api.php',
        'BC_Dokobit_Shortcode' => 'src/Api/class-bc-dokobit-shortcode.php',
        'BC_OAuth_Handler' => 'src/Api/class-bc-oauth-handler.php',
        
        // Utility classes
        'BC_HPOS_Compatibility' => 'src/Utils/class-bc-hpos-compatibility.php',
        'BC_HPOS_Utils' => 'src/Utils/class-bc-hpos-utils.php',
        'BC_Shortcodes' => 'src/Utils/class-bc-shortcodes.php',
    ];

    /**
     * Register the autoloader.
     *
     * @since 1.0.0
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes.
     *
     * @param string $class_name The class name to load.
     */
    public static function autoload($class_name) {
        // Check if we have a mapping for this class
        if (isset(self::$class_map[$class_name])) {
            $file_path = BC_BUSINESS_CENTRAL_SYNC_PATH . self::$class_map[$class_name];
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }

        // Fallback: try to load from includes directory for backward compatibility
        $fallback_path = BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/' . 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        if (file_exists($fallback_path)) {
            require_once $fallback_path;
            return;
        }
    }

    /**
     * Get the class map.
     *
     * @return array
     */
    public static function get_class_map() {
        return self::$class_map;
    }
}
