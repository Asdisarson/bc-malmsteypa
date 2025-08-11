# Integration Guide - Business Central to WooCommerce Sync

This guide provides comprehensive information on integrating the Business Central to WooCommerce Sync plugin with your WordPress theme, custom functionality, and third-party plugins.

## 🔌 Plugin Integration Points

### Hooks and Actions

The plugin provides various hooks for customization and integration:

#### Product Sync Hooks

```php
// Before product sync starts
do_action( 'bc_sync_products_before', $sync_data );

// After product sync completes
do_action( 'bc_sync_products_after', $sync_results );

// Before individual product sync
do_action( 'bc_sync_single_product_before', $product_data );

// After individual product sync
do_action( 'bc_sync_single_product_after', $product_id, $sync_status );

// Product sync error
do_action( 'bc_sync_product_error', $product_data, $error_message );
```

#### Authentication Hooks

```php
// Customer successfully authenticated
do_action( 'bc_customer_authenticated', $user_id, $company_id, $phone_number );

// Customer authentication failed
do_action( 'bc_customer_authentication_failed', $phone_number, $error_message );

// Customer logged out
do_action( 'bc_customer_logged_out', $user_id );

// Company assigned to customer
do_action( 'bc_company_assigned', $user_id, $company_id );
```

#### Pricing Hooks

```php
// Before displaying company pricing
do_action( 'bc_company_pricing_before', $product_id, $company_id );

// After displaying company pricing
do_action( 'bc_company_pricing_after', $product_id, $company_id, $pricing_data );

// Customize pricing display
apply_filters( 'bc_company_pricing_display', $pricing_html, $product_id, $company_id );

// Filter product price for company
apply_filters( 'bc_company_product_price', $price, $product_id, $company_id );
```

#### Pricelist Hooks

```php
// Before pricelist sync
do_action( 'bc_sync_pricelists_before', $sync_data );

// After pricelist sync
do_action( 'bc_sync_pricelists_after', $sync_results );

// Pricelist updated
do_action( 'bc_pricelist_updated', $pricelist_id, $pricelist_data );
```

#### HPOS (High-Performance Order Storage) Hooks

```php
// Order properties updated (HPOS specific)
do_action( 'bc_order_updated_props', $order, $changes );

// Order properties created (HPOS specific)
do_action( 'bc_order_created_props', $order, $changes );

// HPOS status changed
do_action( 'bc_hpos_status_changed', $old_status, $new_status );
```

### Filters

#### Data Filtering

```php
// Filter product data before sync
apply_filters( 'bc_product_data_before_sync', $product_data, $bc_product );

// Filter product data after sync
apply_filters( 'bc_product_data_after_sync', $product_data, $product_id );

// Filter company data
apply_filters( 'bc_company_data', $company_data, $company_id );

// Filter customer data
apply_filters( 'bc_customer_data', $customer_data, $customer_id );
```

#### Display Filtering

```php
// Filter authentication form display
apply_filters( 'bc_auth_form_display', $form_html, $form_type );

// Filter company information display
apply_filters( 'bc_company_info_display', $company_html, $company_id );

// Filter pricing display
apply_filters( 'bc_pricing_display', $pricing_html, $product_id, $company_id );
```

## 🎨 Theme Integration

### Adding Authentication to Your Theme

#### Method 1: Using Shortcodes

The easiest way to integrate authentication:

```php
// In your theme template files
echo do_shortcode( '[bc_login_form title="Customer Login"]' );
echo do_shortcode( '[bc_company_pricing]' );
echo do_shortcode( '[bc_customer_info]' );
```

#### Method 2: Direct Function Calls

For more control, use the plugin's functions directly:

```php
// Check if user is authenticated
if ( function_exists( 'bc_is_customer_authenticated' ) && bc_is_customer_authenticated() ) {
    $company_id = bc_get_customer_company_id();
    $company_name = bc_get_company_name( $company_id );
    
    echo '<div class="customer-info">';
    echo '<p>Welcome, ' . esc_html( $company_name ) . '</p>';
    echo '</div>';
} else {
    echo '<div class="login-required">';
    echo '<p>Please <a href="#" class="bc-login-trigger">login</a> to view pricing.</p>';
    echo '</div>';
}
```

#### Method 3: Template Overrides

Create custom templates in your theme:

```php
// In your theme's functions.php
function my_theme_bc_integration() {
    if ( function_exists( 'bc_is_customer_authenticated' ) ) {
        // Add custom authentication UI
        add_action( 'woocommerce_before_single_product', 'my_custom_auth_display' );
        add_action( 'woocommerce_before_add_to_cart_button', 'my_custom_pricing_display' );
    }
}
add_action( 'after_setup_theme', 'my_theme_bc_integration' );

function my_custom_auth_display() {
    if ( ! bc_is_customer_authenticated() ) {
        echo '<div class="bc-auth-notice">';
        echo '<p>Please authenticate to view company pricing.</p>';
        echo do_shortcode( '[bc_dokobit_login]' );
        echo '</div>';
    }
}

function my_custom_pricing_display() {
    if ( bc_is_customer_authenticated() ) {
        global $product;
        $company_id = bc_get_customer_company_id();
        $company_price = bc_get_company_product_price( $product->get_id(), $company_id );
        
        if ( $company_price ) {
            echo '<div class="company-pricing">';
            echo '<p><strong>Your Price:</strong> ' . wc_price( $company_price ) . '</p>';
            echo '</div>';
        }
    }
}
```

### Custom CSS Integration

#### Basic Styling

```css
/* Authentication form styling */
.bc-auth-form {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.bc-auth-form input[type="tel"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    margin-bottom: 15px;
}

.bc-auth-form button {
    background: #007cba;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.bc-auth-form button:hover {
    background: #005a87;
}

/* Company pricing display */
.company-pricing {
    background: #e8f5e8;
    border: 1px solid #28a745;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.company-pricing p {
    margin: 0;
    color: #155724;
    font-weight: bold;
}

/* Authentication notice */
.bc-auth-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
    color: #856404;
}
```

#### Advanced Styling

```css
/* Responsive design */
@media (max-width: 768px) {
    .bc-auth-form {
        padding: 15px;
        margin: 15px 0;
    }
    
    .bc-auth-form input[type="tel"] {
        font-size: 16px; /* Prevents zoom on iOS */
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .bc-auth-form {
        background: #2d3748;
        border-color: #4a5568;
        color: #e2e8f0;
    }
    
    .bc-auth-form input[type="tel"] {
        background: #4a5568;
        border-color: #718096;
        color: #e2e8f0;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .bc-auth-form {
        border-width: 2px;
    }
    
    .bc-auth-form button {
        border: 2px solid currentColor;
    }
}
```

## 🔧 Custom Functionality

### Creating Custom Shortcodes

```php
// In your theme's functions.php or custom plugin
function my_custom_bc_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'product_id' => get_the_ID(),
        'show_login' => 'true'
    ), $atts );
    
    if ( ! function_exists( 'bc_is_customer_authenticated' ) ) {
        return '<p>Business Central Sync plugin not active.</p>';
    }
    
    $output = '';
    
    if ( bc_is_customer_authenticated() ) {
        $company_id = bc_get_customer_company_id();
        $company_price = bc_get_company_product_price( $atts['product_id'], $company_id );
        
        if ( $company_price ) {
            $output .= '<div class="custom-company-pricing">';
            $output .= '<h3>Special Company Pricing</h3>';
            $output .= '<p class="price">' . wc_price( $company_price ) . '</p>';
            $output .= '</div>';
        }
    } elseif ( 'true' === $atts['show_login'] ) {
        $output .= '<div class="custom-login-prompt">';
        $output .= '<p>Login to see your company pricing.</p>';
        $output .= do_shortcode( '[bc_dokobit_login]' );
        $output .= '</div>';
    }
    
    return $output;
}
add_shortcode( 'my_company_pricing', 'my_custom_bc_shortcode' );
```

#### HPOS-Compatible Order Operations

```php
// Working with orders in an HPOS-compatible way
function my_hpos_compatible_order_function( $order_id ) {
    if ( ! class_exists( 'BC_HPOS_Utils' ) ) {
        return false;
    }
    
    // Get order meta using HPOS utilities
    $customer_number = BC_HPOS_Utils::get_order_meta( $order_id, '_bc_customer_number' );
    
    if ( $customer_number ) {
        // Update order meta using HPOS utilities
        BC_HPOS_Utils::update_order_meta( $order_id, '_bc_customer_status', 'verified' );
        
        // Get orders by meta value
        $related_orders = BC_HPOS_Utils::get_orders_by_meta( '_bc_customer_number', $customer_number );
        
        // Batch update multiple orders
        if ( ! empty( $related_orders ) ) {
            BC_HPOS_Utils::batch_update_order_meta( $related_orders, '_bc_customer_verified', 'yes' );
        }
    }
    
    return true;
}

// Check HPOS status
function check_my_store_hpos_status() {
    if ( class_exists( 'BC_HPOS_Utils' ) ) {
        $status = BC_HPOS_Utils::get_hpos_status();
        
        if ( $status['enabled'] ) {
            echo 'HPOS is enabled with ' . $status['usage_percentage'] . '% usage.';
        } else {
            echo 'HPOS is available but not enabled.';
        }
    }
}
```

### Custom AJAX Handlers

```php
// Handle custom AJAX requests
function my_custom_bc_ajax_handler() {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['nonce'], 'my_custom_bc_action' ) ) {
        wp_die( 'Security check failed' );
    }
    
    // Check if user is authenticated
    if ( ! function_exists( 'bc_is_customer_authenticated' ) || ! bc_is_customer_authenticated() ) {
        wp_send_json_error( 'User not authenticated' );
    }
    
    $company_id = bc_get_customer_company_id();
    $product_id = intval( $_POST['product_id'] );
    
    // Get company pricing
    $pricing_data = bc_get_company_product_pricing( $product_id, $company_id );
    
    wp_send_json_success( $pricing_data );
}
add_action( 'wp_ajax_my_custom_bc_action', 'my_custom_bc_ajax_handler' );
add_action( 'wp_ajax_nopriv_my_custom_bc_action', 'my_custom_bc_ajax_handler' );
```

### Custom Widgets

```php
// Create a custom widget for company information
class BC_Company_Info_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'bc_company_info_widget',
            'Company Information',
            array( 'description' => 'Display authenticated customer company information' )
        );
    }
    
    public function widget( $args, $instance ) {
        if ( ! function_exists( 'bc_is_customer_authenticated' ) || ! bc_is_customer_authenticated() ) {
            return;
        }
        
        echo $args['before_widget'];
        
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }
        
        $company_id = bc_get_customer_company_id();
        $company_name = bc_get_company_name( $company_id );
        $customer_number = bc_get_customer_number();
        
        echo '<div class="company-info-widget">';
        echo '<p><strong>Company:</strong> ' . esc_html( $company_name ) . '</p>';
        if ( $customer_number ) {
            echo '<p><strong>Customer #:</strong> ' . esc_html( $customer_number ) . '</p>';
        }
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" 
                   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" 
                   value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }
    
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        return $instance;
    }
}

// Register the widget
function register_bc_company_info_widget() {
    register_widget( 'BC_Company_Info_Widget' );
}
add_action( 'widgets_init', 'register_bc_company_info_widget' );
```

## 🔌 Third-Party Plugin Integration

### WooCommerce Extensions

#### WooCommerce Subscriptions

```php
// Integrate with WooCommerce Subscriptions
function bc_woocommerce_subscriptions_integration() {
    if ( class_exists( 'WC_Subscriptions' ) ) {
        // Add company pricing to subscription products
        add_filter( 'woocommerce_subscription_price', 'bc_subscription_company_price', 10, 2 );
        add_filter( 'woocommerce_subscription_regular_price', 'bc_subscription_company_regular_price', 10, 2 );
    }
}
add_action( 'init', 'bc_woocommerce_subscriptions_integration' );

function bc_subscription_company_price( $price, $subscription ) {
    if ( function_exists( 'bc_is_customer_authenticated' ) && bc_is_customer_authenticated() ) {
        $company_id = bc_get_customer_company_id();
        $product_id = $subscription->get_product_id();
        $company_price = bc_get_company_product_price( $product_id, $company_id );
        
        if ( $company_price ) {
            return $company_price;
        }
    }
    
    return $price;
}

function bc_subscription_company_regular_price( $price, $subscription ) {
    return bc_subscription_company_price( $price, $subscription );
}
```

#### WooCommerce Memberships

```php
// Integrate with WooCommerce Memberships
function bc_woocommerce_memberships_integration() {
    if ( class_exists( 'WC_Memberships' ) ) {
        // Check membership status for company pricing access
        add_filter( 'bc_company_pricing_access', 'bc_membership_pricing_access', 10, 2 );
    }
}
add_action( 'init', 'bc_woocommerce_memberships_integration' );

function bc_membership_pricing_access( $has_access, $company_id ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    
    $user_id = get_current_user_id();
    
    // Check if user has active membership
    if ( wc_memberships_is_user_active_member( $user_id ) ) {
        return true;
    }
    
    return $has_access;
}
```

### Other WordPress Plugins

#### Contact Form 7

```php
// Integrate with Contact Form 7
function bc_contact_form_7_integration() {
    if ( class_exists( 'WPCF7' ) ) {
        // Add company information to form submissions
        add_action( 'wpcf7_before_send_mail', 'bc_cf7_add_company_info' );
    }
}
add_action( 'init', 'bc_contact_form_7_integration' );

function bc_cf7_add_company_info( $cf7 ) {
    if ( function_exists( 'bc_is_customer_authenticated' ) && bc_is_customer_authenticated() ) {
        $company_id = bc_get_customer_company_id();
        $company_name = bc_get_company_name( $company_id );
        $customer_number = bc_get_customer_number();
        
        // Add company info to form data
        $cf7->company_name = $company_name;
        $cf7->customer_number = $customer_number;
    }
}
```

#### Elementor

```php
// Integrate with Elementor
function bc_elementor_integration() {
    if ( class_exists( '\Elementor\Plugin' ) ) {
        // Add custom widgets
        add_action( 'elementor/widgets/register', 'bc_register_elementor_widgets' );
    }
}
add_action( 'init', 'bc_elementor_integration' );

function bc_register_elementor_widgets( $widgets_manager ) {
    // Include your custom Elementor widget class
    require_once( get_template_directory() . '/widgets/bc-company-pricing-widget.php' );
    $widgets_manager->register( new \BC_Company_Pricing_Elementor_Widget() );
}
```

## 📱 Mobile and Responsive Integration

### Touch-Friendly Authentication

```css
/* Mobile-optimized authentication */
@media (max-width: 768px) {
    .bc-auth-form input[type="tel"] {
        font-size: 16px; /* Prevents zoom on iOS */
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .bc-auth-form button {
        width: 100%;
        padding: 15px;
        font-size: 16px;
        min-height: 44px; /* Touch-friendly button size */
    }
    
    .bc-auth-form .form-row {
        margin-bottom: 20px;
    }
}
```

### Progressive Web App Support

```php
// Add PWA support for authentication
function bc_pwa_integration() {
    // Add manifest link
    add_action( 'wp_head', 'bc_add_pwa_manifest' );
    
    // Add service worker
    add_action( 'wp_footer', 'bc_add_service_worker' );
}
add_action( 'init', 'bc_pwa_integration' );

function bc_add_pwa_manifest() {
    echo '<link rel="manifest" href="' . get_template_directory_uri() . '/manifest.json">';
}

function bc_add_service_worker() {
    echo '<script>';
    echo 'if ("serviceWorker" in navigator) {';
    echo '  navigator.serviceWorker.register("' . get_template_directory_uri() . '/sw.js");';
    echo '}';
    echo '</script>';
}
```

## 🔒 Security Considerations

### Nonce Verification

```php
// Always verify nonces for AJAX requests
function bc_secure_ajax_handler() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_secure_action' ) ) {
        wp_send_json_error( 'Security check failed' );
    }
    
    // Your secure code here
}
```

### Capability Checks

```php
// Check user capabilities before sensitive operations
function bc_capability_check() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Insufficient permissions' );
    }
    
    // Your admin code here
}
```

### Data Sanitization

```php
// Always sanitize and validate data
function bc_sanitize_data( $data ) {
    $sanitized = array();
    
    if ( isset( $data['company_name'] ) ) {
        $sanitized['company_name'] = sanitize_text_field( $data['company_name'] );
    }
    
    if ( isset( $data['phone_number'] ) ) {
        $sanitized['phone_number'] = sanitize_text_field( $data['phone_number'] );
    }
    
    return $sanitized;
}
```

## 📊 Performance Optimization

### Caching Integration

```php
// Integrate with caching plugins
function bc_caching_integration() {
    // Clear cache when company data changes
    add_action( 'bc_company_updated', 'bc_clear_company_cache' );
    add_action( 'bc_pricelist_updated', 'bc_clear_pricing_cache' );
}
add_action( 'init', 'bc_caching_integration' );

function bc_clear_company_cache() {
    if ( function_exists( 'w3tc_flush_all' ) ) {
        w3tc_flush_all(); // W3 Total Cache
    }
    
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush(); // WP Super Cache
    }
    
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain(); // WP Rocket
    }
}
```

### Database Optimization

```php
// Optimize database queries
function bc_optimize_queries() {
    // Add database indexes
    add_action( 'bc_plugin_activated', 'bc_add_database_indexes' );
}
add_action( 'init', 'bc_optimize_queries' );

function bc_add_database_indexes() {
    global $wpdb;
    
    // Add indexes for better performance
    $wpdb->query( "ALTER TABLE {$wpdb->prefix}bc_pricelist_lines ADD INDEX idx_product_company (item_id, pricelist_id)" );
    $wpdb->query( "ALTER TABLE {$wpdb->prefix}bc_customer_companies ADD INDEX idx_customer_pricelist (bc_customer_id, pricelist_id)" );
}
```

## 🧪 Testing and Debugging

### Debug Mode

```php
// Enable debug mode for development
function bc_debug_mode() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // Add debug information
        add_action( 'wp_footer', 'bc_debug_info' );
    }
}
add_action( 'init', 'bc_debug_mode' );

function bc_debug_info() {
    if ( function_exists( 'bc_is_customer_authenticated' ) ) {
        echo '<!-- BC Debug Info: ';
        echo 'Authenticated: ' . ( bc_is_customer_authenticated() ? 'Yes' : 'No' );
        if ( bc_is_customer_authenticated() ) {
            echo ', Company: ' . bc_get_customer_company_id();
        }
        echo ' -->';
    }
}
```

### Error Logging

```php
// Custom error logging
function bc_log_error( $message, $context = array() ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'BC Plugin Error: ' . $message . ' Context: ' . print_r( $context, true ) );
    }
}

// Usage
add_action( 'bc_sync_product_error', function( $product_data, $error_message ) {
    bc_log_error( $error_message, array( 'product' => $product_data ) );
}, 10, 2 );
```

## 📚 Best Practices

### Code Organization

1. **Separate Concerns**: Keep business logic separate from presentation
2. **Use Hooks**: Leverage WordPress hooks instead of modifying plugin files
3. **Error Handling**: Always handle errors gracefully
4. **Performance**: Cache expensive operations and optimize database queries
5. **Security**: Verify nonces, check capabilities, and sanitize data

### Maintenance

1. **Regular Updates**: Keep the plugin and WordPress updated
2. **Backup**: Regularly backup your database and files
3. **Testing**: Test integrations after updates
4. **Monitoring**: Monitor error logs and performance
5. **Documentation**: Document custom integrations

---

**Need help with integration?** Check our [documentation](https://malmsteypa.is/business-central-sync) or [contact support](mailto:support@malmsteypa.is).

**Developed by [Malmsteypa](https://malmsteypa.is)**
