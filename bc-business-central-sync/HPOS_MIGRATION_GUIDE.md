# HPOS (High-Performance Order Storage) Migration Guide

## Overview

This guide provides comprehensive information about migrating your Business Central Sync plugin to be fully compatible with WooCommerce's High-Performance Order Storage (HPOS) system.

## What is HPOS?

HPOS (High-Performance Order Storage) is WooCommerce's new order storage system that replaces the traditional WordPress post-based storage with a dedicated, optimized database table. This provides:

- **Better Performance**: Faster order queries and operations
- **Improved Scalability**: Better handling of large numbers of orders
- **Enhanced Reliability**: Dedicated table structure for orders
- **Future-Proof**: Foundation for WooCommerce's next-generation features

## Requirements

- **WooCommerce**: Version 7.0 or higher
- **WordPress**: Version 5.0 or higher
- **PHP**: Version 7.4 or higher

## Migration Steps

### 1. Pre-Migration Checklist

Before enabling HPOS, ensure:

- [ ] WooCommerce is updated to version 7.0+
- [ ] All plugins are compatible with HPOS
- [ ] Database backup is created
- [ ] Test environment is available

### 2. Enable HPOS in WooCommerce

1. Go to **WooCommerce → Settings → Advanced → Features**
2. Find **High-Performance Order Storage**
3. Check the box to enable HPOS
4. Click **Save changes**

### 3. Plugin Compatibility Check

The Business Central Sync plugin automatically detects HPOS status and provides:

- Real-time HPOS status monitoring
- Performance metrics
- Migration recommendations
- Compatibility warnings

### 4. Order Migration

WooCommerce will automatically migrate existing orders to HPOS:

1. **Automatic Migration**: Orders are migrated in the background
2. **Progress Monitoring**: Check migration status in WooCommerce settings
3. **Rollback Option**: Can revert to traditional storage if needed

## Plugin Features

### HPOS Compatibility Class

The plugin includes a comprehensive HPOS compatibility class (`BC_HPOS_Compatibility`) that:

- Automatically detects HPOS status
- Provides HPOS-compatible order operations
- Handles order meta data correctly
- Manages order queries efficiently

### HPOS Utility Functions

Utility functions (`BC_HPOS_Utils`) provide:

- HPOS-compatible order meta operations
- Advanced order querying capabilities
- Performance monitoring
- Batch operations support

### Key Methods

```php
// Check HPOS status
$hpos_compatibility = new BC_HPOS_Compatibility();
$status = $hpos_compatibility->get_hpos_status();

// Get order meta (HPOS-compatible)
$meta_value = BC_HPOS_Utils::get_order_meta( $order_id, 'meta_key' );

// Update order meta (HPOS-compatible)
BC_HPOS_Utils::update_order_meta( $order_id, 'meta_key', 'value' );

// Get orders by meta (HPOS-compatible)
$orders = BC_HPOS_Utils::get_orders_by_meta( 'meta_key', 'value' );
```

## Admin Dashboard

The plugin provides a comprehensive HPOS status dashboard showing:

- **HPOS Status**: Enabled/Disabled indicator
- **Usage Percentage**: Percentage of orders using HPOS
- **Performance Metrics**: Query time, memory usage, cache hit rate
- **Recommendations**: Actionable migration advice
- **Migration Status**: Current migration progress

## Best Practices

### 1. Order Operations

Always use HPOS-compatible methods:

```php
// ✅ Good - HPOS compatible
$order = wc_get_order( $order_id );
$meta_value = $order->get_meta( 'key' );
$order->update_meta_data( 'key', 'value' );
$order->save();

// ❌ Avoid - Direct database access
global $wpdb;
$meta_value = $wpdb->get_var( $wpdb->prepare( 
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 
    $order_id, 'key' 
) );
```

### 2. Order Queries

Use WooCommerce's order query system:

```php
// ✅ Good - HPOS compatible
$orders = wc_get_orders( array(
    'limit'      => -1,
    'status'     => 'completed',
    'meta_query' => array(
        array(
            'key'     => '_bc_customer_number',
            'value'   => 'CUST001',
            'compare' => '=',
        ),
    ),
) );

// ❌ Avoid - Direct post queries
$orders = get_posts( array(
    'post_type' => 'shop_order',
    'meta_query' => array(
        array(
            'key'     => '_bc_customer_number',
            'value'   => 'CUST001',
            'compare' => '=',
        ),
    ),
) );
```

### 3. Meta Data Handling

Use order object methods for meta operations:

```php
// ✅ Good - HPOS compatible
$order = wc_get_order( $order_id );
$order->add_meta_data( 'key', 'value', true );
$order->save();

// ❌ Avoid - Direct meta functions
add_post_meta( $order_id, 'key', 'value', true );
```

## Troubleshooting

### Common Issues

#### 1. HPOS Not Available

**Problem**: HPOS option not visible in WooCommerce settings

**Solution**: 
- Update WooCommerce to version 7.0+
- Check if custom orders table exists
- Verify database permissions

#### 2. Migration Stuck

**Problem**: Order migration appears to be stuck

**Solution**:
- Check WooCommerce logs for errors
- Verify database connection
- Check server resources
- Consider manual migration

#### 3. Plugin Compatibility

**Problem**: Plugin shows HPOS compatibility warnings

**Solution**:
- Update plugin to latest version
- Check for plugin updates
- Review compatibility documentation

### Debug Information

Enable WordPress debug logging to troubleshoot issues:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs at: `wp-content/debug.log`

## Performance Monitoring

### Metrics to Track

- **Query Performance**: Order query execution time
- **Memory Usage**: Memory consumption during operations
- **Cache Efficiency**: Cache hit/miss ratios
- **Migration Progress**: HPOS adoption percentage

### Monitoring Tools

The plugin provides built-in performance monitoring:

```php
$hpos_status = $hpos_compatibility->get_hpos_status();
$performance = $hpos_status['performance_metrics'];

echo "Query Time: " . $performance['query_time'] . "ms\n";
echo "Memory Usage: " . size_format( $performance['memory_usage'] ) . "\n";
echo "Cache Hit Rate: " . $performance['cache_hit_rate'] . "%\n";
```

## Rollback Procedure

If you need to revert to traditional order storage:

1. **WooCommerce Settings**: Go to Advanced → Features
2. **Disable HPOS**: Uncheck the HPOS option
3. **Confirm Rollback**: WooCommerce will migrate orders back
4. **Monitor Progress**: Check migration status
5. **Verify Functionality**: Test order operations

## Support and Resources

### Documentation

- [WooCommerce HPOS Documentation](https://woocommerce.com/document/high-performance-order-storage/)
- [Plugin Documentation](https://malmsteypa.is/business-central-sync/docs)

### Support Channels

- **Plugin Support**: [Support Portal](https://malmsteypa.is/support)
- **WooCommerce Support**: [WooCommerce.com](https://woocommerce.com/support/)
- **Community**: [WordPress.org Forums](https://wordpress.org/support/)

### Migration Assistance

For complex migrations or enterprise support:

- **Professional Services**: [Contact Sales](https://malmsteypa.is/contact)
- **Migration Consulting**: [Migration Services](https://malmsteypa.is/migration)

## Conclusion

HPOS provides significant performance and scalability improvements for WooCommerce stores. The Business Central Sync plugin is fully compatible with HPOS and provides comprehensive tools for monitoring and managing the migration process.

By following this guide and using the plugin's built-in HPOS compatibility features, you can ensure a smooth transition to the new order storage system while maintaining all existing functionality.

## Changelog

### Version 1.0.0
- Initial HPOS compatibility implementation
- HPOS status dashboard
- Performance monitoring
- Migration recommendations
- Comprehensive utility functions

---

*For additional support or questions about HPOS migration, please contact our support team.*
