# Simple Business Central Sync - No HPOS Complexity

## 🎯 What This Plugin Does

This plugin provides a **simple, lightweight** way to sync products and pricing from Microsoft Dynamics 365 Business Central to WooCommerce **without dealing with complex HPOS (High-Performance Order Storage) or order manipulation**.

## ✨ Key Features

### 🚀 **Simple Pricing System**
- **Price Adjustment on the Fly**: Product prices automatically adjust based on customer's company
- **No Order Complexity**: Works directly with product prices, no order manipulation needed
- **Customer Selector**: Dropdown on product pages to select company and see updated prices
- **Session-Based**: Remembers customer selection during browsing

### 🔄 **Easy Sync**
- **Product Sync**: Sync products from Business Central to WooCommerce
- **Pricelist Sync**: Sync customer-specific pricing
- **Company Sync**: Sync company information
- **Simple Admin Interface**: Clean, straightforward admin panel

### 💰 **How Pricing Works**
1. Customer visits product page
2. Selects their company from dropdown
3. Prices automatically update to show company-specific pricing
4. Cart and checkout prices reflect customer pricing
5. No complex order processing required

## 🛠️ Installation

1. **Upload Plugin**: Upload to `/wp-content/plugins/bc-business-central-sync/`
2. **Activate**: Activate through WordPress admin
3. **Configure**: Set up Business Central API credentials
4. **Sync**: Sync products and pricelists

## ⚙️ Configuration

### Business Central API Settings
- **API Base URL**: Your Business Central API endpoint
- **Company ID**: Your Business Central company identifier  
- **Client ID**: Azure AD application client ID
- **Client Secret**: Azure AD application client secret

### Database Tables (Auto-created)
- `bc_pricelists` - Stores pricelist information
- `bc_pricelist_lines` - Stores individual product prices
- `bc_dokobit_companies` - Stores company information

## 📱 How Customers Use It

### 1. **Browse Products**
- Customer visits any product page
- Sees standard pricing initially

### 2. **Select Company**
- Customer sees company selector dropdown
- Chooses their company from the list
- Clicks "Update Prices" button

### 3. **View Custom Pricing**
- Prices automatically update to show company-specific pricing
- Customer sees their special price and savings
- All prices throughout the site reflect their company pricing

### 4. **Shop Normally**
- Cart prices show customer pricing
- Checkout prices show customer pricing
- No additional complexity for the customer

## 🔧 Technical Details

### How It Works
- **Price Filters**: Uses WooCommerce price filters to adjust prices on the fly
- **Session Storage**: Stores customer selection in session and cookies
- **Database Queries**: Direct queries to pricelist tables for fast pricing
- **No Order Changes**: Works purely at the display level

### Performance Benefits
- **Fast**: No complex order processing
- **Lightweight**: Minimal database overhead
- **Scalable**: Works with any number of products and customers
- **Reliable**: Simple, straightforward implementation

## 🎨 Customization

### Styling
The plugin includes basic CSS that you can override:

```css
.bc-customer-selector {
    /* Customize customer selector appearance */
}

.bc-customer-pricing-info {
    /* Customize pricing display */
}

.bc-customer-price {
    /* Customize price display */
}
```

### Hooks
Available hooks for customization:

```php
// Customer selection changed
add_action( 'bc_customer_selected', 'my_custom_handler', 10, 1 );

// Price adjusted
add_filter( 'bc_customer_price', 'my_price_modifier', 10, 2 );
```

## 📊 Admin Features

### Simple Dashboard
- **Status Overview**: Shows system status and compatibility
- **Basic Settings**: API configuration and sync options
- **Quick Actions**: Test connection, sync products, sync pricelists
- **System Info**: WordPress, WooCommerce, and PHP versions

### Sync Management
- **Manual Sync**: Sync products and pricelists on demand
- **Connection Testing**: Test Business Central API connection
- **Status Monitoring**: Real-time sync status and results

## 🚫 What It Doesn't Do

- ❌ **No HPOS Complexity**: Doesn't deal with WooCommerce's new order storage system
- ❌ **No Order Manipulation**: Doesn't modify or create orders
- ❌ **No Complex Pricing Rules**: Simple company-based pricing only
- ❌ **No Advanced Features**: Focused on core functionality

## 🔍 Troubleshooting

### Common Issues

#### 1. **Prices Not Updating**
- Check if customer has selected a company
- Verify pricelist data is synced
- Check browser console for JavaScript errors

#### 2. **Company Selector Not Showing**
- Ensure companies are synced from Business Central
- Check if user has proper permissions
- Verify WooCommerce is active

#### 3. **Sync Not Working**
- Verify API credentials are correct
- Check Business Central API endpoint
- Ensure proper permissions on Azure AD app

### Debug Mode
Enable WordPress debug logging:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## 📈 Performance Tips

1. **Regular Sync**: Sync pricelists regularly for accurate pricing
2. **Cache Management**: Use caching plugins for better performance
3. **Database Optimization**: Regular database maintenance
4. **Monitor Usage**: Track sync frequency and performance

## 🔮 Future Enhancements

- **Bulk Customer Assignment**: Assign multiple customers to companies
- **Advanced Pricing Rules**: More complex pricing scenarios
- **Reporting**: Customer pricing analytics and reports
- **API Caching**: Improved API response caching

## 💡 Best Practices

1. **Regular Sync**: Keep product and pricing data current
2. **Customer Education**: Explain how the company selector works
3. **Testing**: Test with different customer accounts
4. **Monitoring**: Watch for sync errors and performance issues

## 🆘 Support

- **Documentation**: Check this README first
- **Admin Panel**: Use the built-in status checks
- **Debug Logs**: Check WordPress debug logs
- **API Testing**: Use the connection test feature

---

## 🎉 Summary

This plugin provides a **simple, effective** way to sync Business Central data to WooCommerce with **customer-specific pricing** that works **on the fly** without any complex order processing or HPOS compatibility issues.

**Perfect for businesses that want:**
- ✅ Simple product and pricing sync
- ✅ Customer-specific pricing
- ✅ Easy-to-use interface
- ✅ No technical complexity
- ✅ Fast, reliable performance

**Not suitable for businesses that need:**
- ❌ Complex order processing
- ❌ Advanced pricing rules
- ❌ HPOS-specific features
- ❌ Order manipulation

---

*For additional support, check the admin panel status section or review the debug logs.*
