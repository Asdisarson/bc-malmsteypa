# Business Central to WooCommerce Sync Plugin

A WordPress plugin that automatically fetches products from Microsoft Dynamics 365 Business Central and adds them to WooCommerce as draft products for review and approval.

## Features

- **Automatic Product Sync**: Fetches all products from Business Central via REST API
- **Draft Creation**: Creates products in WooCommerce as drafts for review
- **Smart Updates**: Updates existing products if they already exist
- **Scheduled Sync**: Configurable sync intervals (hourly, daily, weekly)
- **Connection Testing**: Built-in connection testing to verify API credentials
- **Manual Sync**: Manual sync button for immediate product synchronization
- **Sync Logging**: Comprehensive logging of all sync operations
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce
- **Admin Interface**: User-friendly admin interface for configuration
- **Pricelist Management**: Syncs Business Central pricelists and customer company assignments
- **Customer-Specific Pricing**: Displays different prices based on customer's assigned pricelist
- **B2B Pricing Support**: Handles company-specific pricing for business customers
- **Integrated Phone Authentication**: Built-in Dokobit phone authentication system
- **Secure Customer Access**: Only authenticated customers can view prices and make purchases
- **Company Management**: Built-in company and user phone management system
- **No External Dependencies**: Self-contained solution with all authentication features

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Business Central environment with API access
- Azure AD application for authentication
- **Dokobit API credentials** (for phone authentication)

## Authentication & Security

### Integrated Dokobit Phone Authentication
The plugin includes a **complete Dokobit phone authentication system** built directly into the plugin:

- **Phone-based Authentication**: Customers authenticate using their registered phone numbers
- **Company Assignment**: Users are automatically linked to their assigned company
- **Secure Access**: Only authenticated customers can view prices and make purchases
- **Session Management**: Secure session handling with company verification
- **No External Dependencies**: Everything is contained within this single plugin

### Company Management System
- **Company Creation**: Admins can create and manage companies through the admin interface
- **User Phone Registration**: Link users to companies via phone numbers
- **Personal Code Support**: Supports Iceland/Audkenni personal codes
- **Bulk Management**: Easy management of multiple companies and users

### Price Protection
- **Hidden Prices**: Product prices are hidden for non-authenticated users
- **Purchase Prevention**: Non-authenticated users cannot add products to cart
- **Login Required**: Clear messaging about authentication requirements
- **Company Verification**: Ensures users are assigned to valid companies

### Customer Flow
1. **Guest Access**: Users see products but no prices
2. **Authentication**: Users login via integrated Dokobit phone authentication
3. **Company Verification**: System verifies user's company assignment
4. **Price Display**: Company-specific prices are shown
5. **Purchase Access**: Users can now add products to cart and checkout

## Installation

1. Upload the plugin files to the `/wp-content/plugins/bc-business-central-sync/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'BC Sync' in the admin menu to configure the plugin

## Configuration

### Business Central API Settings

1. **API Base URL**: Your Business Central API base URL
   - Format: `https://api.businesscentral.dynamics.com/v2.0/your-environment`
   
2. **Company ID**: Your Business Central company ID
   
3. **Client ID**: Azure AD application client ID
   
4. **Client Secret**: Azure AD application client secret

### Sync Settings

- **Enable Sync**: Toggle automatic synchronization on/off
- **Sync Interval**: Choose between hourly, daily, or weekly sync

## Azure AD Application Setup

To use this plugin, you need to create an Azure AD application:

1. Go to Azure Portal > Azure Active Directory > App registrations
2. Click "New registration"
3. Give it a name (e.g., "Business Central WooCommerce Sync")
4. Select "Accounts in this organizational directory only"
5. Click "Register"
6. Note the Application (client) ID
7. Go to "Certificates & secrets" and create a new client secret
8. Note the client secret value
9. Go to "API permissions" and add:
   - Microsoft Graph > Application permissions > Directory.Read.All
   - Business Central > Delegated permissions > Items.Read.All
10. Grant admin consent for the permissions

## Business Central API Permissions

Ensure your Business Central environment allows:
- Read access to Items table
- API access for the Azure AD application

## Usage

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

## Product Mapping

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
| `number` | Custom Meta | Stored as `_bc_product_number`

## Shortcodes

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