# Business Central Connector - Enhanced Product Sync

## Overview

This enhanced version of the Business Central Connector implements a comprehensive MVP scope for synchronizing products from Microsoft Business Central to WooCommerce. The system provides both full and incremental sync capabilities with robust image handling and error management.

## MVP Scope Implementation

### 1. Core Data Mapping

| Business Central Field | WooCommerce Field | Description |
|----------------------|------------------|-------------|
| `number` | `_sku` | Item number becomes product SKU |
| `displayName` | `post_title` | Display name becomes product title |
| `description` | `post_content` | Full description becomes product content |
| `description` | `post_excerpt` | Truncated description becomes short description |
| `unitPrice` | `_regular_price` | Unit price becomes regular price |
| `inventory` | `_stock` | Inventory becomes stock quantity |
| `inventory` | `_manage_stock` | Inventory > 0 enables stock management |
| `itemCategoryId` | `product_cat` | Item category maps to WooCommerce category |
| `picture` | Featured Image + Gallery | Images become featured image and gallery |

### 2. Business Central API Integration

#### Endpoints Used
- **Items**: `/v2.0/{tenant}/{environment}/api/v2.0/companies({companyId})/items`
- **Categories**: `/companies({companyId})/itemCategories`
- **Pictures**: `/items({itemId})/picture` (expanded)

#### API Query Parameters
```php
$query_params = array(
    '$expand' => 'picture,itemCategory',
    '$select' => 'id,number,displayName,displayName2,description,unitPrice,inventory,itemCategoryId,itemCategoryCode,lastModifiedDateTime,@odata.etag,type,blocked,baseUnitOfMeasureCode,gtin,unitCost,priceIncludesTax,taxGroupCode,generalProductPostingGroupCode,inventoryPostingGroupCode'
);
```

### 3. Incremental Sync Support

#### Filtering
- Uses `$filter=lastModifiedDateTime gt {timestamp}` for incremental syncs
- Automatically stores last sync timestamp for next incremental run
- Recommends sync type based on previous sync history

#### Pagination
- Implements `_nextLink` paging for large datasets
- Configurable page size (default: 100 items per page)
- Safety limit of 1000 items per sync run

## Features

### Enhanced Product Sync
- **Full Sync**: Import all products from Business Central
- **Incremental Sync**: Import only changed products since last sync
- **Progress Tracking**: Real-time progress bar and status updates
- **Error Handling**: Comprehensive error logging and user feedback

### Image Management
- **Automatic Download**: Downloads images from Business Central API
- **Featured Image**: Sets primary image as featured image
- **Gallery Support**: Adds images to product gallery
- **Format Detection**: Automatically detects image format from MIME type
- **Error Recovery**: Graceful handling of image download failures

### Category Integration
- **Hierarchical Support**: Handles parent-child category relationships
- **Automatic Mapping**: Maps Business Central categories to WooCommerce
- **Metadata Storage**: Stores BC category IDs and codes for reference

### Inventory Management
- **Stock Control**: Automatically enables stock management for items with inventory
- **Status Handling**: Sets stock status based on inventory levels
- **Blocked Items**: Handles blocked items by setting them to draft status

## Installation & Setup

### 1. Plugin Installation
```bash
# Upload plugin files to wp-content/plugins/business-central-connector/
# Activate plugin in WordPress admin
```

### 2. Business Central Configuration
1. Navigate to **BC Connector > Settings**
2. Configure OAuth 2.0 credentials:
   - Tenant ID
   - Client ID
   - Client Secret
   - Company ID
   - Environment (Production/Sandbox)
3. Test connection

### 3. Enhanced Sync Setup
1. Navigate to **BC Connector > Enhanced Sync**
2. Review sync status and product counts
3. Choose sync type (Full or Incremental)
4. Start synchronization

## Usage

### Starting a Sync

#### Full Sync (Initial Setup)
1. Select "Full Sync" option
2. Click "Start Sync"
3. Monitor progress bar and status updates
4. Review results summary

#### Incremental Sync (Regular Updates)
1. Select "Incremental Sync" option
2. Verify last sync timestamp is available
3. Click "Start Sync"
4. Only changed products will be processed

### Monitoring Sync Progress
- **Progress Bar**: Visual indication of sync completion
- **Status Updates**: Real-time status information
- **Results Summary**: Detailed breakdown of sync results
- **Error Logging**: Comprehensive error reporting

### Sync Results
Each sync operation provides detailed results:
- Total items processed
- Products created
- Products updated
- Products skipped
- Error details (if any)
- Sync timestamp

## Technical Implementation

### Core Classes

#### Business_Central_Connector
- Main connector class with enhanced sync methods
- Handles OAuth 2.0 authentication
- Manages API requests and responses

#### Business_Central_Admin
- Admin interface management
- Enhanced sync page implementation
- Settings configuration

### Key Methods

#### `perform_enhanced_product_sync()`
- Orchestrates the entire sync process
- Handles pagination and data collection
- Manages sync result tracking

#### `upsert_product_with_mvp_scope()`
- Implements exact MVP scope mapping
- Creates or updates WooCommerce products
- Handles all data transformations

#### `process_product_images_mvp()`
- Downloads and processes product images
- Sets featured images and gallery
- Handles various image data structures

### Error Handling
- **API Errors**: Graceful handling of Business Central API failures
- **Image Errors**: Continues sync even if individual images fail
- **Data Validation**: Validates all incoming data before processing
- **Logging**: Comprehensive error logging for debugging

## Configuration Options

### Sync Settings
- **Page Size**: Number of items per API request (default: 100)
- **Safety Limits**: Maximum items per sync run (default: 1000)
- **Timeout Settings**: API request timeouts (default: 60 seconds)

### Image Settings
- **Download Timeout**: Image download timeout (default: 60 seconds)
- **Format Support**: JPEG, PNG, GIF, WebP, BMP, TIFF
- **Gallery Integration**: Automatic gallery management

## Troubleshooting

### Common Issues

#### Connection Problems
- Verify OAuth credentials
- Check network connectivity
- Validate Business Central environment settings

#### Sync Failures
- Review error logs in WordPress admin
- Check Business Central API permissions
- Verify product data structure

#### Image Issues
- Check Business Central image permissions
- Verify image format support
- Review download timeout settings

### Debug Information
- Enable WordPress debug logging
- Check Business Central API responses
- Review sync result details

## Performance Considerations

### Optimization Tips
- Use incremental sync for regular updates
- Monitor API rate limits
- Implement appropriate timeouts
- Use pagination for large datasets

### Resource Usage
- Image downloads consume bandwidth
- Large syncs may impact server performance
- Consider running syncs during off-peak hours

## Future Enhancements

### Planned Features
- **Variable Products**: Support for item variants
- **Bulk Operations**: Batch processing capabilities
- **Scheduled Syncs**: Automated sync scheduling
- **Advanced Filtering**: Custom sync criteria
- **Webhook Support**: Real-time sync triggers

### API Improvements
- **GraphQL Support**: Alternative to REST API
- **Batch Operations**: Multiple items per request
- **Delta Sync**: More efficient change detection

## Support & Documentation

### Resources
- WordPress Plugin Repository
- Business Central API Documentation
- WooCommerce Developer Documentation

### Contact
For support and feature requests, please refer to the plugin documentation or contact the development team.

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Compatibility**: WordPress 5.0+, WooCommerce 3.0+, Business Central v22+
