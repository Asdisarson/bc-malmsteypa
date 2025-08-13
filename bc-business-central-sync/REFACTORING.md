# BC Business Central Sync - Refactoring Documentation

## Overview

This document outlines the refactoring changes made to improve the plugin's architecture, maintainability, and code organization.

## New Directory Structure

```
bc-business-central-sync/
├── src/                          # Source code organized by responsibility
│   ├── Core/                     # Core plugin functionality
│   │   ├── class-bc-business-central-sync.php
│   │   ├── class-bc-business-central-sync-loader.php
│   │   ├── class-bc-business-central-sync-i18n.php
│   │   ├── class-bc-business-central-sync-activator.php
│   │   ├── class-bc-business-central-sync-deactivator.php
│   │   └── class-bc-plugin-core.php
│   ├── Features/                 # Business logic and feature classes
│   │   ├── class-bc-business-central-api.php
│   │   ├── class-bc-company-manager.php
│   │   ├── class-bc-customer-pricing.php
│   │   ├── class-bc-pricelist-manager.php
│   │   ├── class-bc-simple-pricing.php
│   │   ├── class-bc-woocommerce-manager.php
│   │   └── class-bc-woocommerce-pricing.php
│   ├── Admin/                    # Admin interface classes
│   │   └── class-bc-business-central-sync-admin.php
│   ├── Public/                   # Public-facing classes
│   │   └── class-bc-business-central-sync-public.php
│   ├── Database/                 # Database operations
│   │   ├── class-bc-database-migration.php
│   │   └── class-bc-dokobit-database.php
│   ├── Api/                      # External API integrations
│   │   ├── class-bc-dokobit-api.php
│   │   └── class-bc-dokobit-shortcode.php
│   └── Utils/                    # Utility and helper classes
│       ├── class-bc-hpos-compatibility.php
│       ├── class-bc-hpos-utils.php
│       └── class-bc-shortcodes.php
├── assets/                       # CSS, JavaScript, and images
│   ├── css/                      # Stylesheets
│   ├── js/                       # JavaScript files
│   └── images/                   # Image assets
├── templates/                    # Template files (partials)
├── tests/                        # Unit tests
├── includes/                     # Legacy includes (maintained for compatibility)
├── admin/                        # Legacy admin directory
├── public/                       # Legacy public directory
└── composer.json                 # Composer configuration for autoloading
```

## Key Changes

### 1. Organized Source Code
- **Core**: Essential plugin functionality, hooks, and initialization
- **Features**: Business logic and feature-specific implementations
- **Admin**: WordPress admin interface components
- **Public**: Frontend and public-facing functionality
- **Database**: Database operations and migrations
- **Api**: External API integrations
- **Utils**: Helper functions and utilities

### 2. Asset Organization
- All CSS, JS, and image files moved to `assets/` directory
- Organized by type (css, js, images)
- Easier to manage and optimize

### 3. Template Organization
- Admin and public partials moved to `templates/` directory
- Cleaner separation of presentation logic

### 4. Autoloader Implementation
- New `BC_Autoloader` class for automatic class loading
- Maintains backward compatibility with existing code
- PSR-4 compatible structure for future improvements

### 5. Composer Integration
- `composer.json` for dependency management
- PSR-4 autoloading configuration
- Development dependencies for testing

## Benefits of Refactoring

### Maintainability
- Clear separation of concerns
- Easier to locate specific functionality
- Reduced coupling between components

### Scalability
- Better structure for adding new features
- Organized codebase for team development
- Clear patterns for extending functionality

### Testing
- Dedicated `tests/` directory
- Better isolation of components
- Easier to write unit tests

### Standards Compliance
- PSR-4 autoloading structure
- Modern PHP development practices
- Better WordPress plugin architecture

## Migration Notes

### Backward Compatibility
- All existing functionality preserved
- Legacy directories maintained during transition
- Autoloader provides fallback to old structure

### File References
- Update any hardcoded file paths
- Use new autoloader for class loading
- Asset URLs should reference new `assets/` directory

### Development Workflow
- New code should use the new structure
- Gradually migrate existing code
- Update documentation and examples

## Next Steps

1. **Phase 2**: Implement dependency injection and service layer
2. **Phase 3**: Add comprehensive unit tests
3. **Phase 4**: Optimize autoloading and performance
4. **Phase 5**: Remove legacy directories and complete migration

## Support

For questions about the refactoring or new structure, please refer to:
- Plugin documentation
- WordPress coding standards
- PSR-4 autoloading specification
