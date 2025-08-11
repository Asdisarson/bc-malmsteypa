# Changelog - Business Central to WooCommerce Sync

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Enhanced error handling and logging
- Improved database table structure with additional indexes
- Better WooCommerce version compatibility checks
- Enhanced security with improved nonce verification
- Comprehensive integration documentation

### Changed
- Refactored plugin structure for better maintainability
- Improved activation and deactivation processes
- Enhanced database table creation with better error handling
- Updated plugin metadata and branding

### Fixed
- Database table creation issues during activation
- WooCommerce dependency checking
- Plugin path constants usage
- Error handling during plugin activation

## [1.0.0] - 2024-01-15

### Added
- Initial release of Business Central to WooCommerce Sync plugin
- **Core Integration Features**
  - Automatic product synchronization from Microsoft Dynamics 365 Business Central
  - WooCommerce integration with draft product creation
  - Configurable sync intervals (hourly, daily, weekly)
  - Connection testing for Business Central API
  - Manual sync functionality
  - Comprehensive sync logging

- **Advanced B2B Features**
  - Pricelist management and synchronization
  - Customer-specific pricing system
  - Company management and assignment
  - Customer company synchronization
  - B2B pricing support

- **Integrated Phone Authentication**
  - Complete Dokobit phone authentication system
  - Company assignment and verification
  - Secure session management
  - No external dependencies required

- **Admin Interface**
  - User-friendly configuration panel
  - Company and user management
  - Sync status monitoring
  - Connection testing tools
  - Comprehensive settings management

- **Shortcode System**
  - `[bc_login_form]` - Main authentication form
  - `[bc_dokobit_login]` - Direct Dokobit authentication
  - `[bc_company_pricing]` - Company-specific pricing display
  - `[bc_customer_info]` - Customer information display
  - `[bc_dokobit_company]` - Company information display

- **Database Structure**
  - Sync logs table for tracking operations
  - Pricelists table for price management
  - Pricelist lines table for individual pricing
  - Customer companies table for assignments
  - Dokobit companies table for company management
  - Dokobit user phones table for authentication

- **Security Features**
  - Price protection for non-authenticated users
  - Purchase prevention for unauthorized access
  - Secure company verification
  - Session management and validation

### Technical Features
- WordPress 5.0+ compatibility
- WooCommerce 5.0+ integration
- PHP 7.4+ support
- GPL v2+ license
- Comprehensive error handling
- AJAX-powered admin interface
- Cron job integration for automated sync
- Internationalization support
- Responsive design support

### Integration Points
- Business Central REST API integration
- Azure AD authentication
- Dokobit phone authentication API
- WooCommerce hooks and filters
- WordPress plugin standards compliance

## Development Notes

### Version Numbering
- **Major Version**: Breaking changes or major feature additions
- **Minor Version**: New features or significant improvements
- **Patch Version**: Bug fixes and minor improvements

### Compatibility
- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

### Testing
- Tested with WordPress 6.4
- Tested with WooCommerce 8.0
- Tested with PHP 7.4, 8.0, 8.1, 8.2
- Tested with various themes and plugins

### Known Issues
- None reported in this version

### Future Roadmap
- Enhanced reporting and analytics
- Advanced pricing rules engine
- Multi-currency support
- Bulk import/export functionality
- Advanced user role management
- API rate limiting and optimization
- Enhanced mobile experience
- Multi-language support

---

**For detailed information about each version, see the [documentation](https://malmsteypa.is/business-central-sync).**

**Developed by [Malmsteypa](https://malmsteypa.is)**
