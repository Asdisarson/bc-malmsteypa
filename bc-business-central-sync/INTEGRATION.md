# Integration Guide: Integrated Dokobit Authentication + Business Central Sync

This guide explains how to use the **integrated Dokobit phone authentication system** that comes built-in with the Business Central Sync plugin. No external plugins are required!

## Prerequisites

1. **Business Central Sync plugin** must be installed and configured
2. **WooCommerce** must be active
3. **Business Central API** must be configured with pricelist access
4. **Dokobit API credentials** for phone authentication

## Setup Steps

### 1. Configure Dokobit Authentication

In the WordPress admin, go to **BC Sync > Dokobit Auth**:

1. **Set API Endpoint**: Usually `https://developers.dokobit.com`
2. **Set API Key**: Your Dokobit API access token
3. **Test Connection**: Verify the API connection works
4. **Save Settings**: Store your configuration

### 2. Create Companies

Go to **BC Sync > Companies**:

1. **Add Company**: Create companies for your customers
2. **Company Names**: Use descriptive names (e.g., "Acme Corporation")
3. **Organization**: Group related companies together

### 3. Register User Phones

Go to **BC Sync > User Phones**:

1. **Select User**: Choose a WordPress user
2. **Enter Phone**: Add their phone number with country code
3. **Assign Company**: Link them to a specific company
4. **Personal Code**: Add if using Iceland/Audkenni system

### 4. Configure Business Central Sync

Set up the Business Central connection:
- API URL, Company ID, Client ID, Client Secret
- Sync pricelists and customer companies
- Verify product synchronization

## Implementation Examples

### Basic Product Page

Add these shortcodes to your `single-product.php` template:

```php
<?php
// Standard WooCommerce product display
woocommerce_content();

// Company pricing (only visible to authenticated customers)
echo do_shortcode('[bc_company_pricing]');

// Login form (if not authenticated)
if (!is_user_logged_in()) {
    echo do_shortcode('[bc_login_form]');
}
?>
```

### Customer Dashboard Page

Create a page with the customer information shortcode:

```php
<?php
// Customer dashboard content
echo do_shortcode('[bc_customer_info show_company="true" show_customer_number="true"]');

// Quick login form
echo do_shortcode('[bc_login_form]');
?>
```

### Landing Page

Use the login form shortcode for main authentication:

```php
<?php
echo do_shortcode('[bc_login_form 
    title="Welcome to Our B2B Store" 
    description="Please authenticate to access company pricing"
]');
?>
```

### Direct Dokobit Authentication

For standalone phone authentication:

```php
<?php
// Direct Dokobit login form
echo do_shortcode('[bc_dokobit_login]');

// Show company info for authenticated users
echo do_shortcode('[bc_dokobit_company]');
?>
```

## Custom Theme Integration

### Add to functions.php

```php
// Ensure shortcodes are loaded
add_action('init', function() {
    if (class_exists('BC_Shortcodes')) {
        BC_Shortcodes::init();
    }
    if (class_exists('BC_Dokobit_Shortcode')) {
        BC_Dokobit_Shortcode::init();
    }
});

// Add custom authentication checks
function is_customer_authenticated() {
    if (class_exists('BC_Customer_Pricing')) {
        $customer_pricing = new BC_Customer_Pricing();
        return $customer_pricing->is_user_authenticated();
    }
    return false;
}

// Custom price display
function display_customer_price($product_id) {
    if (is_customer_authenticated()) {
        $customer_pricing = new BC_Customer_Pricing();
        $customer_pricing->display_customer_pricing($product_id);
    } else {
        echo '<p class="login-required">Login required to see pricing</p>';
    }
}
```

### Custom WooCommerce Hooks

```php
// Hide add to cart button for non-authenticated users
add_action('woocommerce_after_shop_loop_item', function() {
    if (!is_customer_authenticated()) {
        echo '<p class="login-required">Login required to purchase</p>';
    }
});

// Show company pricing on product page
add_action('woocommerce_single_product_summary', function() {
    if (is_customer_authenticated()) {
        $customer_pricing = new BC_Customer_Pricing();
        $customer_pricing->display_customer_pricing(get_the_ID());
    }
}, 25);
```

## Security Considerations

### Price Protection
- All prices are hidden by default
- Only authenticated customers see prices
- Company verification prevents unauthorized access

### Purchase Prevention
- Non-authenticated users cannot add to cart
- Server-side validation ensures security
- Clear messaging about authentication requirements

### Session Management
- Secure session handling
- Company assignment verification
- Automatic logout on company changes

## Troubleshooting

### Common Issues

1. **Prices not showing**: Check if user is authenticated and assigned to company
2. **Login not working**: Verify Dokobit API configuration
3. **Company not found**: Ensure user has phone number and company assignment
4. **Shortcodes not working**: Check if classes are loaded

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Testing Checklist

- [ ] Dokobit API is configured and tested
- [ ] Business Central API connection is working
- [ ] Pricelists and customer companies are synced
- [ ] User has phone number and company assignment
- [ ] Shortcodes are displaying correctly
- [ ] Prices are hidden for non-authenticated users
- [ ] Purchase is prevented for non-authenticated users

## Support

For integration support:
1. Check plugin documentation
2. Verify Dokobit configuration
3. Test Business Central API connection
4. Review WordPress error logs
5. Contact plugin support team

## Migration from External Dokobit Plugin

If you were previously using the external Dokobit Phone Authentication plugin:

1. **Export Data**: Export companies and user phones from the old plugin
2. **Import Data**: Use the new admin interface to recreate the data
3. **Update Shortcodes**: Change `[dokobit_login]` to `[bc_dokobit_login]`
4. **Test Authentication**: Verify all users can still login
5. **Remove Old Plugin**: Deactivate and remove the external plugin

The integrated system provides the same functionality with better integration and no external dependencies.
