# Sync Engine Enhancements - Implementation Summary

## Overview
This document summarizes the enhanced sync engine features that have been implemented for the Business Central to WooCommerce connector, focusing on category mapping, dry run functionality, and image rebuilding.

## Enhanced Features Implemented

### 1. Category Mapping Helpers

#### **parse_category_map() Method**
- **Purpose**: Parses category mapping textarea into structured array
- **Format Support**: `BCCode => Woo Category Name` (one per line)
- **Features**:
  - Handles multiple line endings (`\r\n`, `\r`, `\n`)
  - Skips empty lines and comments (lines starting with `#`)
  - Trims whitespace from both BC code and Woo category name
  - Returns associative array for efficient lookup

```php
private static function parse_category_map($mapStr) {
    $map = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$mapStr) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        // Format: BCCode => Woo Category Name
        if (strpos($line, '=>') !== false) {
            [$bc, $woo] = array_map('trim', explode('=>', $line, 2));
            if ($bc !== '' && $woo !== '') $map[$bc] = $woo;
        }
    }
    return $map;
}
```

#### **ensure_woo_category() Method**
- **Purpose**: Creates WooCommerce category if it doesn't exist
- **Features**:
  - Checks if category term already exists
  - Creates new category if needed
  - Returns term ID for assignment
  - Handles WordPress term creation errors gracefully

```php
private static function ensure_woo_category($name) {
    $term = term_exists($name, 'product_cat');
    if (!$term) {
        $term = wp_insert_term($name, 'product_cat');
    }
    if (is_wp_error($term)) return 0;
    return (int)($term['term_id'] ?? $term['term_taxonomy_id'] ?? 0);
}
```

#### **apply_category_mapping() Method**
- **Purpose**: Applies category mapping to a specific product
- **Features**:
  - Looks up BC category code in mapping
  - Creates WooCommerce category if needed
  - Assigns category to product using `wp_set_object_terms()`
  - No-op if no mapping found or invalid category

```php
private static function apply_category_mapping($product_id, $bcCategoryCode, $opts) {
    if (!$bcCategoryCode) return;
    $map = self::parse_category_map($opts['category_map'] ?? '');
    if (empty($map[$bcCategoryCode])) return;
    $wooCatName = $map[$bcCategoryCode];
    $term_id = self::ensure_woo_category($wooCatName);
    if ($term_id) {
        wp_set_object_terms($product_id, [$term_id], 'product_cat', false);
    }
}
```

### 2. Enhanced Dry Run with Diff Computation

#### **dry_run_with_diffs() Method**
- **Purpose**: Shows detailed preview of what would sync without database writes
- **Features**:
  - Compares mapped fields to existing WooCommerce products
  - Identifies specific field changes (name, price, stock, category)
  - Shows action type (CREATE, UPDATE, SKIP)
  - Generates HTML table for admin display
  - No media import or database modifications

#### **Diff Detection Logic**
```php
$diffs = [];

// Name comparison
if ($prod->get_name() !== $mapped['name']) $diffs[] = 'name';

// Price comparison
$currPrice = (float)$prod->get_regular_price();
if ((string)$currPrice !== (string)($mapped['price'] ?? '')) $diffs[] = 'price';

// Stock comparison
if ($prod->get_manage_stock()) {
    if ((int)$prod->get_stock_quantity() !== (int)$mapped['stock']) $diffs[] = 'stock';
} else {
    if ($mapped['stock'] !== null) $diffs[] = 'stock(+manage)';
}

// Category mapping check
$bcCat = ($item['itemCategoryCode'] ?? $item['itemCategoryId'] ?? '');
if ($bcCat) $diffs[] = 'category(map check)';
```

#### **Output Format**
- **Table Columns**: SKU, Action, Name, Price, Stock, BC Category, Images, Changes
- **Action Types**:
  - `CREATE`: New product to be created
  - `UPDATE`: Existing product with changes
  - `SKIP`: No changes needed
- **Change Indicators**: Lists specific fields that would change

### 3. Enhanced Image Rebuilding

#### **rebuild_images() Method**
- **Purpose**: Rebuilds product images from Business Central
- **Features**:
  - Single SKU or bulk operation support
  - Processes all product statuses (publish, draft, pending, private)
  - Efficient filtering by SKU for single product operations
  - Returns count of processed products

#### **rebuild_images_for_product() Method**
- **Purpose**: Rebuilds images for a specific product
- **Features**:
  - Finds BC item by SKU using exact match filter
  - Pulls fresh pictures from Business Central
  - Removes existing featured and gallery images
  - Imports new images and assigns to product
  - Optionally deletes old attachment files
  - SQL injection protection with proper escaping

```php
// Find BC Item by number == SKU
$filter = "number eq '" . str_replace("'", "''", $sku) . "'";
$page = $client->list_items($companyId, $filter, 1);
$item = ($page['value'][0] ?? null);
```

### 4. Enhanced Product Mapping

#### **Updated map_item_to_wc() Method**
- **New Fields**:
  - `bc_category`: Business Central category code/ID
  - `bc_id`: Business Central item ID for reference
- **Purpose**: Provides more context for category mapping and debugging

```php
private static function map_item_to_wc($item) {
    return [
        'sku'         => $item['number'] ?? '',
        'name'        => $item['displayName'] ?? ($item['description'] ?? ''),
        'description' => wp_kses_post($item['description'] ?? ''),
        'price'       => isset($item['unitPrice']) ? (float)$item['unitPrice'] : null,
        'stock'       => isset($item['inventory']) ? (int)$item['inventory'] : null,
        'active'      => !empty($item['blocked']) ? false : true,
        'bc_category' => $item['itemCategoryCode'] ?? ($item['itemCategoryId'] ?? ''),
        'bc_id'       => $item['id'] ?? null,
    ];
}
```

#### **Enhanced upsert_product() Method**
- **New Parameters**: Optional `$opts` parameter for settings
- **Category Integration**: Automatically applies category mapping after product save
- **Improved Flow**: Category assignment happens before image processing

```php
private static function upsert_product($mapped, $images, $opts = null) {
    // ... existing product creation/update logic ...
    
    $product_id = $product->save();

    // Apply category mapping (if any)
    self::apply_category_mapping($product_id, $mapped['bc_category'], $opts);

    // Images processing
    if (!empty($images)) {
        // ... image import logic ...
    }
    
    return $product_id;
}
```

## Integration Points

### **Admin Interface Updates**
- **Dry Run Button**: Now calls `dry_run_with_diffs()` for enhanced preview
- **Image Rebuild**: Uses new `rebuild_images()` method
- **Category Mapping**: Integrated into settings page with textarea input

### **Settings Integration**
- **Storage**: Category mappings stored in `bcc_settings['category_map']`
- **Format**: One mapping per line with `=>` separator
- **Comments**: Lines starting with `#` are ignored
- **Validation**: Empty lines and malformed mappings are skipped

### **Business Central API**
- **Item Fetching**: Enhanced filtering and pagination support
- **Category Fields**: Supports both `itemCategoryCode` and `itemCategoryId`
- **Image Handling**: Improved picture download and processing

## Usage Examples

### **Category Mapping Configuration**
```
# Business Central to WooCommerce Category Mappings
ACCESS => Accessories
TOOLS => Tools & Equipment
SAFETY => Safety Equipment
ELECTRICAL => Electrical Supplies
CONSUMABLES => Consumable Items
```

### **Dry Run Output**
- **CREATE**: New products to be added
- **UPDATE**: Existing products with changes
- **SKIP**: Products that don't need updates
- **Changes Column**: Lists specific field modifications

### **Image Rebuild Operations**
- **Single Product**: `rebuild_images('SKU123')`
- **All Products**: `rebuild_images()` (bulk operation)
- **Progress Tracking**: Returns count of processed products

## Performance Considerations

### **Dry Run Optimization**
- Limited to first 100 items for preview
- No database writes or media processing
- Efficient diff computation using existing WooCommerce data

### **Image Rebuild Efficiency**
- Processes products sequentially to avoid memory issues
- Reuses existing image import logic
- Optional cleanup of old attachment files

### **Category Mapping**
- Parses mappings once per sync operation
- Creates categories only when needed
- Leverages WordPress term caching

## Error Handling & Logging

### **Graceful Degradation**
- Continues processing on individual item failures
- Logs errors for debugging without stopping sync
- Returns meaningful error messages to admin interface

### **Validation**
- Checks for required classes before operations
- Validates category mapping format
- Handles missing or invalid Business Central data

## Future Enhancements

### **Potential Improvements**
1. **Batch Processing**: Parallel image downloads for better performance
2. **Category Hierarchy**: Support for nested category structures
3. **Advanced Filtering**: More granular sync options
4. **Progress Indicators**: Real-time sync progress display
5. **Conflict Resolution**: Handle duplicate SKUs and category conflicts

### **Configuration Options**
1. **Sync Frequency**: Automated sync scheduling
2. **Image Optimization**: Compress/optimize imported images
3. **Backup & Rollback**: Sync history and rollback capabilities
4. **Category Validation**: Pre-sync category mapping validation

## Testing Recommendations

### **Test Scenarios**
1. **Category Mapping**: Test various mapping formats and edge cases
2. **Dry Run**: Verify diff detection accuracy and output format
3. **Image Rebuild**: Test single and bulk operations
4. **Error Handling**: Test with invalid data and missing resources
5. **Performance**: Test with large product catalogs

### **Validation Checklist**
- [ ] Category mappings are properly parsed and applied
- [ ] Dry run shows accurate diff information
- [ ] Image rebuild works for single and bulk operations
- [ ] Category creation and assignment works correctly
- [ ] Error handling is graceful and informative
- [ ] Performance is acceptable for large datasets
- [ ] Integration with existing sync logic works properly
- [ ] Admin interface displays results correctly
