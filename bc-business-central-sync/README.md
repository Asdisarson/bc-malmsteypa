# Business Central to WooCommerce Sync

A professional WordPress plugin that seamlessly integrates Microsoft Dynamics 365 Business Central with WooCommerce, providing automated product synchronization, customer-specific pricing, and integrated phone authentication for B2B operations.

## Features

### 🏢 **Pricelist Management**
- **Fetch Pricelists**: Retrieve all existing and new pricelists from Business Central
- **Overwrite/Keep Options**: Choose whether to overwrite existing WooCommerce pricelists with BC data or keep the current versions
- **Force All Overwrite**: Bulk option to overwrite all pricelists with Business Central data
- **Manual Editing**: Edit pricelists within WordPress (edits do not sync back to BC)
- **Individual Control**: Select specific pricelists for overwrite or keep actions

### 🏭 **Company Management**
- **Sync Companies**: Retrieve all companies from Business Central
- **Pricelist Assignment**: Assign specific pricelists to each company
- **Company Statistics**: View comprehensive statistics including user counts and pricelist assignments
- **Flexible Management**: Add, edit, and remove companies with full control

### 👥 **Customer Management**
- **Company Assignment**: Assign each WooCommerce customer to a company
- **Phone Authentication**: Integrated phone number and personal code authentication
- **Bulk Operations**: Bulk assign customers to companies
- **Customer Statistics**: Track customer distribution across companies

### 💰 **Customer-Specific Pricing**
- **Dynamic Pricing**: Display product prices based on customer's company pricelist
- **WooCommerce Integration**: Seamless integration with cart, checkout, and order processes
- **Price Display**: Show special pricing information on product pages
- **Cart Integration**: Apply customer pricing throughout the shopping experience

## Installation

1. Upload the plugin files to the `/wp-content/plugins/bc-business-central-sync` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure Business Central API settings in the admin panel
4. Set up companies and assign pricelists
5. Configure customer company assignments

## Configuration

### Business Central API Settings
- **API Base URL**: Your Business Central API endpoint
- **Company ID**: Your Business Central company identifier
- **Client ID**: Azure AD application client ID
- **Client Secret**: Azure AD application client secret

### Database Tables
The plugin automatically creates the following database tables:
- `bc_pricelists` - Stores pricelist information
- `bc_pricelist_lines` - Stores individual product prices within pricelists
- `bc_company_pricelists` - Links companies to their assigned pricelists
- `bc_dokobit_companies` - Stores company information
- `bc_dokobit_user_phones` - Links users to companies via phone numbers

## Usage

### Pricelist Management
1. Navigate to **BC Sync > Pricelists**
2. Click **"Fetch Pricelists"** to retrieve from Business Central
3. Choose overwrite/keep actions for individual pricelists
4. Use **"Force Overwrite All"** for bulk updates
5. Edit pricelist names manually if needed

### Company Management
1. Navigate to **BC Sync > Companies**
2. Click **"Fetch Companies"** to sync from Business Central
3. Assign pricelists to companies using the dropdown
4. View company statistics and user counts
5. Manage company assignments as needed

### Customer Management
1. Navigate to **BC Sync > Customers**
2. Add new customers with company assignments
3. Use bulk operations to assign multiple customers to companies
4. Manage phone numbers and personal codes
5. View customer distribution across companies

### Customer Pricing
- Customer prices automatically display based on their company's pricelist
- Special pricing information shows on product pages
- Cart and checkout prices reflect customer-specific pricing
- Order totals use customer pricing throughout

## API Integration

### Business Central Endpoints
- **Pricelists**: `/salesPriceLists`
- **Pricelist Lines**: `/salesPriceLists/{id}/salesPriceListLines`
- **Companies**: `/customers`
- **Products**: `/items`

### Authentication
Uses Azure AD client credentials flow for secure API access.

## Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher (7.0+ recommended for HPOS)
- **PHP**: 7.4 or higher

## 🚀 HPOS Compatibility

This plugin is **100% compatible** with WooCommerce's High-Performance Order Storage (HPOS) system, providing enhanced performance and scalability for your e-commerce operations.

### HPOS Features
- **Automatic Detection**: Seamlessly detects and adapts to HPOS status
- **Performance Monitoring**: Real-time performance metrics and optimization
- **Migration Support**: Comprehensive guidance for HPOS migration
- **Future-Proof**: Built for WooCommerce's next-generation features

For detailed HPOS information, see [README_HPOS.md](README_HPOS.md) and [HPOS_MIGRATION_GUIDE.md](HPOS_MIGRATION_GUIDE.md).
- **MySQL**: 5.7 or higher

## Support

For support and documentation, visit [malmsteypa.is](https://malmsteypa.is)

## Changelog

### Version 1.1.0
- ✨ Added comprehensive pricelist management
- ✨ Added company management with pricelist assignments
- ✨ Added customer management and company assignments
- ✨ Added customer-specific pricing integration
- ✨ Enhanced database structure with new tables
- ✨ Added WooCommerce pricing filters and hooks

### Version 1.0.0
- 🎉 Initial release
- ✨ Basic Business Central integration
- ✨ Product synchronization
- ✨ Dokobit authentication
- ✨ HPOS compatibility

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please ensure all code follows WordPress coding standards.