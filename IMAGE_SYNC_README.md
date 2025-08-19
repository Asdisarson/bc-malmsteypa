# BC → WC Product Image Sync

This document describes the Business Central to WooCommerce Product Image Sync functionality that has been integrated into the Business Central Connector plugin.

## Overview

The Product Image Sync feature automatically fetches product images from Microsoft Business Central and sets them as featured images (product images) in WooCommerce. This ensures that your WooCommerce products display the same images as your Business Central items.

## Features

- **Automatic Sync**: Images are automatically synced when products are created/updated from Business Central
- **Manual Sync**: Admin interface for manually syncing individual products or bulk operations
- **Smart Detection**: Automatically detects BC item pictures and downloads them
- **Media Library Integration**: Downloaded images are properly added to WordPress Media Library
- **Error Handling**: Comprehensive error logging and user feedback

## How It Works

1. **Product Creation/Update**: When a product is synced from Business Central, the image sync is automatically triggered
2. **BC API Call**: The system queries the Business Central API to retrieve the item's picture
3. **Image Download**: The picture is downloaded with proper authentication
4. **Media Library**: The image is added to WordPress Media Library
5. **Product Association**: The image is set as the product's featured image

## Configuration

### Required Settings

1. **Access Token**: Set in the plugin settings under "Access Token"
   - This should be a valid Business Central API access token
   - Can be obtained through OAuth 2.0 flow or manually configured

2. **Business Central Configuration**:
   - Tenant ID
   - Company ID
   - Base URL
   - Environment (Production/Sandbox)

### Access Token Sources

The plugin will try to get the access token from these sources in order:

1. Plugin settings (`bcc_settings['access_token']`)
2. WordPress constant (`BC_ACCESS_TOKEN`)
3. Transient storage (`bcc_access_token`)

## Usage

### Automatic Sync

Images are automatically synced when:
- Products are saved/updated in WordPress admin
- Products are synced from Business Central via the connector
- WooCommerce CRUD operations are processed

### Manual Sync

Use the "Image Sync" admin page to manually sync images:

1. **Single Product Sync**: Enter a product ID to sync its image
2. **BC Item Sync**: Enter a Business Central item number or ID to sync
3. **Bulk Sync**: Sync images for all products with BC metadata

## API Endpoints

The image sync uses these Business Central API endpoints:

- `GET /companies({companyId})/items({itemId})?$expand=picture`
- `GET /companies({companyId})/items?$filter=number eq 'ITEMNO'&$expand=picture`
- `GET /companies({companyId})/items({itemId})/picture({pictureId})/content`

## Error Handling

Common error scenarios and solutions:

- **Missing Access Token**: Configure the access token in plugin settings
- **Invalid BC Configuration**: Verify tenant ID and company ID
- **API Rate Limits**: Business Central may throttle requests; implement delays if needed
- **Image Not Found**: Some BC items may not have pictures

## Logging

All sync operations are logged with the prefix `[BC Image Sync]`. Check your WordPress error log for detailed information about sync operations.

## Performance Considerations

- **Bulk Operations**: Large numbers of products may take significant time
- **API Limits**: Respect Business Central API rate limits
- **Image Sizes**: Large images will increase sync time and storage usage

## Troubleshooting

### Images Not Syncing

1. Check that the access token is valid and not expired
2. Verify Business Central configuration (tenant ID, company ID)
3. Check error logs for specific error messages
4. Ensure products have BC metadata (`business_central_item_number` or `business_central_item_id`)

### Access Token Issues

1. **OAuth Flow**: Use the plugin's OAuth 2.0 integration to get fresh tokens
2. **Manual Token**: Obtain token from Azure Portal or Business Central admin
3. **Token Expiry**: Tokens typically expire after 1 hour; implement refresh logic if needed

### API Errors

1. **401 Unauthorized**: Check access token validity
2. **403 Forbidden**: Verify API permissions and scopes
3. **404 Not Found**: Check item numbers/IDs exist in Business Central
4. **429 Too Many Requests**: Implement rate limiting

## Development

### Hooks and Filters

- `bcc_after_product_sync`: Triggered after a product is synced from BC
- `BC_WC_Product_Image_Sync::sync_product_image()`: Public method for manual sync

### Customization

The image sync class can be extended to:
- Add custom image processing
- Implement different storage strategies
- Add custom error handling
- Integrate with other image management plugins

## Security

- Access tokens are stored securely in WordPress options
- All API calls use HTTPS
- User permissions are checked for admin operations
- Input validation and sanitization implemented

## Support

For issues or questions about the image sync functionality:

1. Check the WordPress error logs
2. Verify Business Central API access
3. Test with a single product first
4. Review the configuration settings

## Changelog

- **v1.0.0**: Initial release with automatic and manual image sync
- Integrated with existing Business Central Connector plugin
- Support for both item numbers and GUIDs
- Comprehensive error handling and logging
