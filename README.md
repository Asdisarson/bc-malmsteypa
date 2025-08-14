# Business Central Connector for WordPress

A WordPress plugin that provides seamless integration with Microsoft Business Central through its REST API.

## Features

- **Easy Setup**: Simple configuration interface in WordPress admin
- **Connection Management**: Test, refresh, and monitor connection status
- **Secure Storage**: All credentials are stored securely in WordPress options
- **Real-time Status**: Visual connection status indicators
- **Responsive Design**: Works on all devices and screen sizes

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Business Central account with API access
- Azure App Registration for OAuth2 authentication

## Installation

1. **Upload the Plugin**
   - Download the plugin files
   - Upload the `business-central-connector` folder to your `/wp-content/plugins/` directory
   - Or upload the plugin ZIP file through WordPress admin

2. **Activate the Plugin**
   - Go to **Plugins** > **Installed Plugins** in your WordPress admin
   - Find "Business Central Connector" and click **Activate**

3. **Configure the Plugin**
   - Go to **BC Connector** in your WordPress admin menu
   - Fill in your Business Central connection details
   - Save the settings

## Configuration

### Required Fields

- **Base URL**: Fixed to `https://api.businesscentral.dynamics.com/`
- **Callback URL**: Fixed to `https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback`
- **Tenant ID**: Your Azure AD tenant ID
- **Client ID**: Your Azure App Registration client ID
- **Client Secret**: Your Azure App Registration client secret
- **Company ID**: Your Business Central company ID
- **BC Environment**: Your Business Central environment (e.g., `production` or `sandbox`)
- **API Version**: Fixed to `v2.0`

### Azure App Registration Setup

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** > **App registrations**
3. Click **New registration**
4. Fill in the required details:
   - Name: `WordPress BC Connector`
   - Supported account types: `Accounts in this organizational directory only`
   - Redirect URI: `Web` - `https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback`
5. After creation, note down the **Application (client) ID** and **Directory (tenant) ID**
6. Go to **Certificates & secrets** and create a new client secret
7. Go to **API permissions** and add:
   - `Business Central` > `BusinessCentral.ReadWrite.All`
   - `Microsoft Graph` > `User.Read`

### OAuth 2.0 Authentication

The plugin uses **OAuth 2.0 Client Credentials flow** for authentication:

- **Automatic Token Management**: Tokens are automatically obtained and refreshed
- **Secure Storage**: Access tokens are stored securely with expiration handling
- **Scope**: Uses `https://api.businesscentral.dynamics.com/.default` scope
- **Token Endpoint**: `https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token`
- **Callback Handling**: Processes OAuth callbacks at the specified callback URL

## Usage

### Admin Interface

The plugin adds a **BC Connector** menu item to your WordPress admin menu with three main sections:

1. **Connection Status**: Shows current connection status with visual indicators
2. **Actions**: Three buttons for managing the connection:
   - **Setup Connection**: Provides setup instructions
   - **Test Connection**: Tests the current configuration
   - **Refresh Status**: Updates connection status
3. **Settings Form**: Configure all connection parameters

### Connection Status Indicators

- **Connected** (Green): Successfully connected to Business Central
- **Disconnected** (Red): No connection established
- **Failed** (Yellow): Connection attempt failed

### Testing the Connection

1. Fill in all required fields in the settings form
2. Save the settings
3. Click **Test Connection** to verify the configuration
4. The plugin will attempt to connect to Business Central and retrieve company information

## API Endpoints

The plugin is configured to work with Business Central v2.0 API endpoints:

- Base URL: `https://api.businesscentral.dynamics.com/`
- API Version: `v2.0`
- URL Structure: `https://api.businesscentral.dynamics.com/v2.0/{tenantId}/{bcEnvironment}/api/v2.0/{endpoint}`
- Default endpoint: `companies` (for testing connection)

## Security Features

- **OAuth 2.0 Authentication**: Implements Microsoft OAuth 2.0 client credentials flow
- **Token Management**: Automatic token refresh and secure storage
- **Nonce Verification**: All AJAX requests are protected with WordPress nonces
- **Capability Checks**: Only users with `manage_options` capability can access settings
- **Input Sanitization**: All user inputs are properly sanitized
- **Secure Storage**: Credentials and tokens are stored securely in WordPress options

## Troubleshooting

### Common Issues

1. **"Missing required connection parameters"**
   - Ensure all required fields are filled in
   - Check that Client Secret is not empty

2. **"Connection failed: API Error"**
   - Verify your Azure App Registration has correct permissions
   - Check that your Business Central environment is accessible
   - Ensure your company ID is correct

3. **"Unauthorized" error**
   - Make sure you're logged in as an administrator
   - Check that the plugin is properly activated

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Development

### File Structure

```
business-central-connector/
├── business-central-connector.php      # Main plugin file
├── includes/
│   ├── class-business-central-connector.php  # Core connector class
│   └── class-business-central-admin.php      # Admin interface class
├── assets/
│   ├── js/admin.js                    # Admin JavaScript
│   └── css/admin.css                  # Admin styles
└── README.md                          # This file
```

### Hooks and Filters

The plugin provides several WordPress hooks for customization:

- `bcc_before_api_request`: Fired before making API requests
- `bcc_after_api_request`: Fired after API requests complete
- `bcc_connection_status_changed`: Fired when connection status changes

### Extending the Plugin

To add custom functionality, you can extend the main classes or use WordPress hooks:

```php
// Example: Custom API endpoint
add_action('bcc_before_api_request', function($method, $endpoint) {
    // Custom logic before API request
}, 10, 2);
```

## Support

For support and feature requests, please:

1. Check the troubleshooting section above
2. Review the WordPress error logs
3. Verify your Business Central API access
4. Ensure all required fields are properly configured

## Changelog

### Version 1.0.0
- Initial release
- Basic connection management
- Admin interface with three action buttons
- Connection status monitoring
- Settings configuration form

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for WordPress integration with Microsoft Business Central.
