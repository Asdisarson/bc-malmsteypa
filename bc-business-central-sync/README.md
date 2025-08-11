# Business Central to WooCommerce Sync Plugin

A professional WordPress plugin that seamlessly integrates Microsoft Dynamics 365 Business Central with WooCommerce, providing automated product synchronization, customer-specific pricing, and integrated phone authentication for B2B operations.

**Developed by [Malmsteypa](https://malmsteypa.is)**

## 🚀 Features

### Core Integration
- **Automatic Product Sync**: Fetches all products from Business Central via REST API
- **Draft Creation**: Creates products in WooCommerce as drafts for review
- **Smart Updates**: Updates existing products if they already exist
- **Scheduled Sync**: Configurable sync intervals (hourly, daily, weekly)
- **Connection Testing**: Built-in connection testing to verify API credentials
- **Manual Sync**: Manual sync button for immediate product synchronization
- **Sync Logging**: Comprehensive logging of all sync operations

### WooCommerce Integration
- **Seamless Integration**: Fully compatible with WooCommerce 5.0+
- **HPOS Compatible**: Full support for WooCommerce High-Performance Order Storage
- **Admin Interface**: User-friendly admin interface for configuration
- **Product Mapping**: Intelligent mapping of Business Central fields to WooCommerce
- **Inventory Management**: Sync inventory levels and product status

### Advanced B2B Features
- **Pricelist Management**: Syncs Business Central pricelists and customer company assignments
- **Customer-Specific Pricing**: Displays different prices based on customer's assigned pricelist
- **B2B Pricing Support**: Handles company-specific pricing for business customers
- **Company Management**: Built-in company and user phone management system

### Security & Authentication
- **Integrated Phone Authentication**: Built-in Dokobit phone authentication system
- **Secure Customer Access**: Only authenticated customers can view prices and make purchases
- **Session Management**: Secure session handling with company verification
- **No External Dependencies**: Everything is contained within this single plugin

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher  
- **PHP**: 7.4 or higher
- **Business Central**: Environment with API access
- **Azure AD**: Application for authentication
- **Dokobit API**: Credentials for phone authentication

## 🛠️ Installation

1. **Upload Plugin Files**
   - Upload the plugin files to `/wp-content/plugins/bc-business-central-sync/`
   - Or install via WordPress admin panel

2. **Activate Plugin**
   - Activate the plugin through the 'Plugins' menu in WordPress
   - Ensure WooCommerce is active

3. **Configure Settings**
   - Go to 'BC Sync' in the admin menu
   - Configure Business Central API settings
   - Set up Dokobit authentication

## ⚙️ Configuration

### Business Central API Settings

1. **API Base URL**
   - Format: `https://api.businesscentral.dynamics.com/v2.0/your-environment`
   - Your Business Central API base URL
   
2. **Company ID**
   - Your Business Central company ID
   
3. **Client ID**
   - Azure AD application client ID
   
4. **Client Secret**
   - Azure AD application client secret

### Sync Settings

- **Enable Sync**: Toggle automatic synchronization on/off
- **Sync Interval**: Choose between hourly, daily, or weekly sync
- **Sync Pricelists**: Enable/disable pricelist synchronization
- **Sync Customers**: Enable/disable customer company synchronization

## 🔐 Azure AD Application Setup

To use this plugin, you need to create an Azure AD application:

1. **Go to Azure Portal**
   - Navigate to Azure Active Directory > App registrations
   - Click "New registration"

2. **Configure Application**
   - Give it a name (e.g., "Business Central WooCommerce Sync")
   - Select "Accounts in this organizational directory only"
   - Click "Register"

3. **Get Credentials**
   - Note the Application (client) ID
   - Go to "Certificates & secrets" and create a new client secret
   - Note the client secret value

4. **Set Permissions**
   - Go to "API permissions" and add:
     - Microsoft Graph > Application permissions > Directory.Read.All
     - Business Central > Delegated permissions > Items.Read.All
   - Grant admin consent for the permissions

## 🔒 Business Central API Permissions

Ensure your Business Central environment allows:
- Read access to Items table
- API access for the Azure AD application
- Proper authentication flow setup

## 📱 Dokobit Phone Authentication

The plugin includes a complete Dokobit phone authentication system:

### Features
- **Phone-based Authentication**: Customers authenticate using registered phone numbers
- **Company Assignment**: Users are automatically linked to their assigned company
- **Secure Access**: Only authenticated customers can view prices and make purchases
- **Session Management**: Secure session handling with company verification

### Setup
1. **Get API Credentials**
   - Obtain Dokobit API key from [developers.dokobit.com](https://developers.dokobit.com)
   - Configure API endpoint and key in plugin settings

2. **Company Management**
   - Create companies through the admin interface
   - Link users to companies via phone numbers
   - Support for Iceland/Audkenni personal codes

## ⚡ HPOS (High-Performance Order Storage) Compatibility

The plugin is fully compatible with WooCommerce's HPOS system, providing improved performance and scalability:

### HPOS Benefits
- **Enhanced Performance**: Faster order queries and operations
- **Better Scalability**: Handles high-volume stores more efficiently
- **Improved Database**: Optimized order storage and retrieval
- **Future-Proof**: Ready for WooCommerce's next-generation architecture

### Compatibility Features
- **Automatic Detection**: Automatically detects HPOS status
- **Seamless Integration**: Works with both HPOS and traditional storage
- **Optimized Queries**: Uses HPOS-optimized methods when available
- **Fallback Support**: Maintains compatibility with older WooCommerce versions
- **Admin Notices**: Shows HPOS status and recommendations

### HPOS Requirements
- WooCommerce 7.0 or higher
- WordPress 5.0 or higher
- MySQL 5.7 or higher (for optimal performance)

### HPOS Status Monitoring
The plugin provides real-time HPOS status information:
- Current storage system in use
- Migration progress percentage
- Performance recommendations
- Compatibility status

## 🎯 Usage

### Manual Sync

1. Go to BC Sync admin page
2. Click "Sync Products Now" button
3. Confirm the action
4. Monitor the sync progress and results

### Pricelist Sync

1. Go to BC Sync admin page
2. Click "Sync Pricelists" button
3. This will sync:
   - All sales price lists from Business Central
   - Individual product pricing within each pricelist
   - Customer company assignments to pricelists

### Customer Company Sync

1. Go to BC Sync admin page
2. Click "Sync Customer Companies" button
3. This will sync customer assignments to specific pricelists

### Connection Testing

1. Configure your API settings
2. Click "Test Connection" button
3. Verify the connection is successful

### Automatic Sync

1. Enable automatic sync in settings
2. Choose your preferred sync interval
3. The plugin will automatically sync products according to the schedule

## 🔄 Product Mapping

The plugin maps the following Business Central fields to WooCommerce:

| Business Central | WooCommerce | Notes |
|------------------|-------------|-------|
| `displayName` | Product Name | Falls back to product number if empty |
| `description` | Product Description | Full product description |
| `description` | Short Description | Truncated to 20 words |
| `unitPrice` | Regular Price | Product selling price |
| `unitCost` | Custom Meta | Stored as `_bc_unit_cost` |
| `inventory` | Custom Meta | Stored as `_bc_inventory` |
| `blocked` | Custom Meta | Stored as `_bc_blocked` |
| `number` | Custom Meta | Stored as `_bc_product_number` |

## 📝 Shortcodes

The plugin provides several shortcodes for easy integration into your theme:

### Dokobit Authentication Shortcodes

#### `[bc_dokobit_login]`
Displays the Dokobit phone authentication form for secure customer login.

**Example:**
```
[bc_dokobit_login]
```

#### `[bc_dokobit_company]`
Shows the authenticated user's company information.

**Example:**
```
[bc_dokobit_company]
```

### Business Central Integration Shortcodes

#### `[bc_login_form]`
Displays the main login form with Dokobit phone authentication and standard WordPress login options.

**Attributes:**
- `title` - Custom title for the login form
- `description` - Custom description text

**Example:**
```
[bc_login_form title="Customer Login" description="Please authenticate to view pricing"]
```

#### `[bc_customer_info]`
Shows authenticated customer information including company details and customer number.

**Attributes:**
- `show_company` - Show/hide company information (true/false)
- `show_customer_number` - Show/hide customer number (true/false)
- `show_pricing_info` - Show/hide pricing information (true/false)

**Example:**
```
[bc_customer_info show_company="true" show_customer_number="true"]
```

#### `[bc_company_pricing]`
Displays company-specific pricing for a product (only visible to authenticated customers).

**Attributes:**
- `product_id` - Specific product ID (defaults to current product)
- `show_login_form` - Show login form if user not authenticated (true/false)

**Example:**
```
[bc_company_pricing product_id="123" show_login_form="true"]
```

### Usage Examples

**Product Page Integration:**
```
<!-- Show company pricing below product details -->
[bc_company_pricing]

<!-- Show login form if not authenticated -->
[bc_login_form]
```

**Customer Dashboard:**
```
<!-- Display customer information -->
[bc_customer_info]

<!-- Show login status -->
[bc_login_form]
```

**Landing Page:**
```
<!-- Main authentication form -->
[bc_login_form title="Welcome to Our B2B Store" description="Please authenticate to access company pricing"]
```

**Direct Dokobit Login:**
```
<!-- Standalone Dokobit authentication -->
[bc_dokobit_login]
```

## 🔧 Development

### Code Structure
```
bc-business-central-sync/
├── admin/                 # Admin interface files
├── includes/             # Core plugin classes
├── public/               # Public-facing functionality
├── languages/            # Internationalization files
├── bc-business-central-sync.php  # Main plugin file
└── README.md             # This file
```

### Key Classes
- `BC_Business_Central_Sync` - Main plugin class
- `BC_Business_Central_API` - Business Central API integration
- `BC_Dokobit_API` - Dokobit authentication API
- `BC_Pricelist_Manager` - Pricelist management
- `BC_WooCommerce_Manager` - WooCommerce integration

### Hooks and Filters
The plugin provides various hooks and filters for customization:
- `bc_sync_products_before` - Before product sync
- `bc_sync_products_after` - After product sync
- `bc_customer_authenticated` - When customer authenticates
- `bc_company_pricing_display` - Customize pricing display

## 🐛 Troubleshooting

### Common Issues

1. **WooCommerce Not Found**
   - Ensure WooCommerce is installed and activated
   - Check WooCommerce version compatibility (5.0+)

2. **API Connection Failed**
   - Verify Business Central API URL
   - Check Azure AD credentials
   - Ensure proper API permissions

3. **Authentication Issues**
   - Verify Dokobit API credentials
   - Check company and user setup
   - Clear browser cache and cookies

4. **Sync Not Working**
   - Check sync settings in admin
   - Verify cron jobs are running
   - Check error logs for details

### Debug Mode
Enable debug mode in WordPress to see detailed error messages:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## 📞 Support

For support and questions:
- **Website**: [malmsteypa.is](https://malmsteypa.is)
- **Documentation**: [malmsteypa.is/business-central-sync](https://malmsteypa.is/business-central-sync)
- **Email**: support@malmsteypa.is

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Business Central to WooCommerce product sync
- Dokobit phone authentication integration
- Customer-specific pricing system
- Company management functionality
- Comprehensive admin interface
- Shortcode support for easy integration

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 🙏 Acknowledgments

- Microsoft Dynamics 365 Business Central team
- Dokobit for phone authentication services
- WooCommerce community
- WordPress community

---

**Developed with ❤️ by [Malmsteypa](https://malmsteypa.is)**