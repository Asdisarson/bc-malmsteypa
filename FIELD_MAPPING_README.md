# Business Central to WooCommerce Field Mapping Guide

## Overview
This document provides comprehensive field mapping notes, gotchas, and implementation details for synchronizing data from Business Central to WooCommerce.

## Core Field Mappings

### SKU (Product Identifier)
**BC Field:** `number`  
**Woo Field:** `sku`  
**Critical:** WooCommerce requires SKU uniqueness across all products.

**Implementation Notes:**
- Use BC `number` as SKU (recommended approach)
- If you have duplicate item numbers across companies, prefix with company code: `{company_code}_{item_number}`
- Current implementation: `'sku' => $item['number'] ?? ''`

**Gotcha:** Duplicate SKUs will cause WooCommerce to reject the product creation/update.

### Product Name
**BC Field:** `displayName` (fallback: `description`)  
**Woo Field:** `name`  
**Implementation:** `'name' => $item['displayName'] ?? ($item['description'] ?? '')`

### Description
**BC Fields:** `description`, `additionalInformation`  
**Woo Field:** `description`  
**Current Implementation:** Uses only `description` field

**Recommendation:** Consider combining both fields for richer product descriptions:
```php
'description' => wp_kses_post(
    ($item['description'] ?? '') . 
    (!empty($item['additionalInformation']) ? "\n\n" . $item['additionalInformation'] : '')
)
```

### Price
**BC Field:** `unitPrice`  
**Woo Field:** `regular_price`  
**Current Implementation:** `'price' => isset($item['unitPrice']) ? (float)$item['unitPrice'] : null`

**Advanced Pricing Considerations:**
- BC supports price lists, customer-specific pricing, and multiple currencies
- For MVP: `unitPrice` is sufficient
- For production: Consider BC's newer Pricing API (Price Lists)
- Currency conversion may be needed if BC and Woo use different currencies

### Stock/Inventory
**BC Field:** `inventory`  
**Woo Field:** `stock_quantity`, `manage_stock`, `stock_status`  
**Current Implementation:** 
```php
if (!is_null($mapped['stock'])) {
    $product->set_manage_stock(true);
    $product->set_stock_quantity(max(0, $mapped['stock']));
    $product->set_stock_status($mapped['stock'] > 0 ? 'instock' : 'outofstock');
}
```

**Location/Warehouse Considerations:**
- BC `inventory` is read-only current stock across all locations
- If using BC locations/warehouses, you may need:
  - Aggregate across locations, OR
  - Pick a default location via separate endpoints
  - Use `item inventoryByLocation` endpoint (requires extension or custom queries)

### Product Status
**BC Field:** `blocked`  
**Woo Field:** `status`  
**Implementation:** `'active' => !empty($item['blocked']) ? false : true`

## Advanced Field Mappings

### Categories
**BC Field:** `itemCategoryId`  
**Woo Field:** Product categories  
**Implementation:** Not currently implemented

**Recommended Implementation:**
```php
// In map_item_to_wc function
if (!empty($item['itemCategoryId'])) {
    $category_name = $item['itemCategoryName'] ?? 'Uncategorized';
    $category_term = term_exists($category_name, 'product_cat');
    
    if (!$category_term) {
        $category_term = wp_insert_term($category_name, 'product_cat');
    }
    
    if (!is_wp_error($category_term)) {
        $mapped['category_ids'] = [$category_term['term_id']];
    }
}

// In upsert_product function
if (!empty($mapped['category_ids'])) {
    wp_set_object_terms($product_id, $mapped['category_ids'], 'product_cat');
}
```

### Images
**BC Entity:** `picture`  
**BC Fields:** `content@odata.mediaReadLink`  
**Woo Field:** `_thumbnail_id`, `_product_image_gallery`

**Current Implementation:** 
- Fetches pictures via `get_item_pictures()`
- Downloads binary content via `download_picture_stream()`
- First image becomes featured, rest become gallery

**Critical Gotcha:** The `picture` entity returns metadata including `content@odata.mediaReadLink`. You MUST call that URL (with proper authorization) to get the binary content.

**Performance Note:** Current implementation downloads images one by one. Consider batching for better performance.

## API Implementation Details

### Paging
**BC Response:** `@odata.nextLink`  
**Current Implementation:** ✅ Properly implemented
```php
while (!empty($page['@odata.nextLink'])) {
    $page = $client->list_items_next($page['@odata.nextLink']);
    $items = array_merge($items, $page['value'] ?? []);
}
```

### Incremental Sync
**BC Filter:** `lastModifiedDateTime gt {timestamp}`  
**Storage:** `bcc_settings['last_sync']`  
**Format:** ISO 8601 (e.g., `2025-08-19T00:00:00Z`)

**Current Implementation:**
```php
if (!$full && !empty($opts['last_sync'])) {
    $since = esc_sql($opts['last_sync']);
    $filter = "lastModifiedDateTime gt {$since}";
}
```

**Gotcha:** Ensure timestamp ends with `Z` for UTC timezone.

### Error Handling
**Current Implementation:** ✅ Log and continue per item
```php
try {
    $mapped = self::map_item_to_wc($item);
    $images = self::pull_pictures($client, $companyId, $item['id']);
    self::upsert_product($mapped, $images);
} catch (\Throwable $e) {
    error_log('[BCWoo] Item sync failed: ' . $e->getMessage());
    continue;
}
```

**Recommended Enhancement:** Add exponential backoff for HTTP 429/5xx errors:
```php
private static function make_request_with_retry($client, $companyId, $itemId, $max_retries = 3) {
    $attempt = 0;
    while ($attempt < $max_retries) {
        try {
            return $client->get_item_pictures($companyId, $itemId);
        } catch (Exception $e) {
            $attempt++;
            if ($attempt >= $max_retries) throw $e;
            
            $status_code = $this->extract_status_code($e->getMessage());
            if ($status_code == 429 || $status_code >= 500) {
                $delay = pow(2, $attempt) * 1000; // Exponential backoff in ms
                usleep($delay * 1000);
            } else {
                throw $e; // Don't retry client errors
            }
        }
    }
}
```

## Performance Optimizations

### Batch Processing
**Current Implementation:** ✅ 100 items per page
```php
$page = $client->list_items($companyId, $filter, 100);
```

**Recommendations:**
- Avoid regenerating thumbnails until after batch completion
- Consider implementing background processing for large syncs
- Cache category lookups to avoid repeated database queries

### Image Optimization
**Current Implementation:** Downloads images sequentially
**Enhancement Opportunity:** Batch image downloads for better performance

## Future Enhancements

### Variants Support
**BC Entity:** `itemVariants`  
**Woo Product Type:** Variable products  
**Status:** Not implemented

**Implementation Plan:**
1. Get simple products stable first
2. Implement variant mapping: `itemVariants` → Woo variable products
3. Handle variant-specific pricing and stock

### Advanced Pricing
**BC Features:** Price lists, customer-specific pricing, quantity breaks  
**Woo Features:** Sale prices, bulk pricing  
**Status:** Basic implementation only

**Future Implementation:**
- Map BC price lists to Woo sale prices
- Implement customer group pricing
- Handle quantity-based pricing tiers

## Configuration Requirements

### Required BC Settings
- `tenant_id`: Azure AD tenant identifier
- `client_id`: Azure AD application client ID
- `client_secret`: Azure AD application secret
- `company_id`: Business Central company identifier
- `bc_environment`: BC environment (Production/Sandbox)

### WooCommerce Requirements
- WooCommerce plugin activated
- Proper media upload permissions
- Sufficient memory for image processing

## Troubleshooting

### Common Issues
1. **Duplicate SKU errors:** Check for duplicate item numbers across companies
2. **Image import failures:** Verify OAuth token has media read permissions
3. **Sync timeouts:** Increase PHP execution time for large catalogs
4. **Memory issues:** Process in smaller batches or increase PHP memory limit

### Debug Mode
Enable WordPress debug logging to see detailed sync information:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Testing Recommendations

### Test Scenarios
1. **Small catalog sync** (< 100 items)
2. **Large catalog sync** (> 1000 items)
3. **Incremental sync** with modified items
4. **Image import** with various file types
5. **Error handling** with invalid data
6. **Performance testing** with realistic data volumes

### Validation Checklist
- [ ] All products sync without errors
- [ ] SKUs are unique across catalog
- [ ] Images import correctly
- [ ] Stock quantities match BC
- [ ] Prices are accurate
- [ ] Categories are properly mapped
- [ ] Incremental sync works correctly
- [ ] Error handling logs issues appropriately
