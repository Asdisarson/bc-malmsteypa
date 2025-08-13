# OAuth Implementation for Business Central Integration

## Overview

This document describes the complete OAuth 2.0 authorization code flow implementation for Microsoft Business Central integration in your WordPress plugin.

## What Was Implemented

### 1. **OAuth Handler Class** (`src/Api/class-bc-oauth-handler.php`)
- **Complete OAuth 2.0 Flow**: Authorization code flow with redirects
- **Token Management**: Automatic access token refresh and storage
- **Security Features**: State parameter validation, nonce verification
- **AJAX Endpoints**: All OAuth operations via WordPress AJAX
- **Error Handling**: Comprehensive error handling and logging

### 2. **OAuth Settings Class** (`src/Admin/class-bc-oauth-settings.php`)
- **WordPress Settings API**: Proper integration with WordPress settings
- **Input Validation**: GUID format validation for client ID
- **Security**: Client secret validation and sanitization
- **Admin Menu**: Dedicated OAuth settings page

### 3. **Admin Interface** (`templates/bc-oauth-settings-admin-display.php`)
- **User-Friendly UI**: Clean, professional settings interface
- **Real-time Status**: Live OAuth status display
- **Interactive Actions**: Buttons for OAuth operations
- **Setup Instructions**: Step-by-step Azure configuration guide

### 4. **Integration with Existing Code**
- **Business Central API**: Updated to use OAuth handler
- **Autoloader**: New classes properly integrated
- **Main Plugin**: OAuth components initialized automatically

## OAuth Flow Architecture

```
User → WordPress Admin → OAuth Initiate → Microsoft Azure → Callback → Token Exchange → Success
```

### **Step-by-Step Flow:**

1. **User clicks "Start OAuth Authorization"** in WordPress admin
2. **Plugin generates state parameter** and stores it securely
3. **User redirected to Microsoft** with authorization request
4. **User consents** to permissions on Microsoft side
5. **Microsoft redirects back** to your callback URL
6. **Plugin validates state** and exchanges code for tokens
7. **Tokens stored securely** and user redirected back to admin
8. **Success message displayed** and OAuth status updated

## AJAX Endpoints

### **OAuth Initiate** (`bc_oauth_initiate`)
- **Purpose**: Start OAuth authorization flow
- **Method**: POST
- **Security**: Nonce verification required
- **Response**: Authorization URL for redirect

### **OAuth Callback** (`bc_oauth_callback`)
- **Purpose**: Handle Microsoft callback
- **Method**: GET (from Microsoft redirect)
- **Security**: State parameter validation
- **Response**: Redirect to admin with status message

### **OAuth Refresh** (`bc_oauth_refresh`)
- **Purpose**: Manually refresh access token
- **Method**: POST
- **Security**: Nonce verification required
- **Response**: Success/error message

### **OAuth Revoke** (`bc_oauth_revoke`)
- **Purpose**: Remove all stored tokens
- **Method**: POST
- **Security**: Nonce verification required
- **Response**: Success/error message

## Configuration

### **Required WordPress Options:**
- `bc_oauth_client_id`: Microsoft Azure application client ID
- `bc_oauth_client_secret`: Microsoft Azure application client secret

### **Automatic Configuration:**
- `bc_oauth_access_token`: Stored access token (auto-managed)
- `bc_oauth_refresh_token`: Stored refresh token (auto-managed)
- `bc_oauth_token_expires`: Token expiration timestamp (auto-managed)
- `bc_oauth_state`: OAuth state parameter (auto-managed)

## Security Features

### **State Parameter Validation**
- Unique state generated for each OAuth request
- 10-minute expiration for state parameters
- Prevents CSRF attacks and replay attacks

### **Nonce Verification**
- WordPress nonces for all AJAX operations
- Prevents unauthorized OAuth operations
- Time-limited security tokens

### **Token Security**
- Tokens stored in WordPress options (consider encryption for production)
- Automatic token refresh before expiration
- Secure token revocation capability

### **Input Validation**
- Client ID validated as GUID format
- Client secret length validation
- Proper sanitization of all inputs

## Microsoft Azure Setup

### **1. Create Azure Application**
1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to "Azure Active Directory" → "App registrations"
3. Click "New registration"
4. Enter application name
5. Select "Accounts in this organizational directory only"
6. Click "Register"

### **2. Configure Redirect URI**
1. In your app registration, go to "Authentication"
2. Click "Add a platform" → "Web"
3. Add redirect URI: `https://yourdomain.com/wp-admin/admin-ajax.php?action=bc_oauth_callback`
4. Save changes

### **3. Get Client Credentials**
1. Go to "Certificates & secrets"
2. Click "New client secret"
3. Add description and select expiration
4. Copy the generated secret value
5. Go to "Overview" and copy the Application (client) ID

### **4. Configure API Permissions**
1. Go to "API permissions"
2. Click "Add a permission"
3. Select "Business Central" → "Delegated permissions"
4. Select required permissions:
   - `Company.Read.All`
   - `Item.Read.All`
   - `Customer.Read.All`
   - `SalesOrder.Read.All`
5. Click "Add permissions"
6. Click "Grant admin consent" for your organization

## Usage Examples

### **Check OAuth Status**
```php
$oauth_handler = new BC_OAuth_Handler();
$status = $oauth_handler->get_status();

if ($status['configured'] && $status['authenticated']) {
    // OAuth is ready to use
    $access_token = $oauth_handler->get_access_token();
}
```

### **Use in Business Central API**
```php
$bc_api = new BC_Business_Central_API();
// The API automatically uses OAuth for authentication
$products = $bc_api->get_products();
```

### **Manual Token Refresh**
```php
$oauth_handler = new BC_OAuth_Handler();
$result = $oauth_handler->refresh_access_token();
```

## Error Handling

### **Common Error Scenarios:**
1. **Configuration Missing**: Client ID or secret not set
2. **Authentication Required**: OAuth flow not completed
3. **Token Expired**: Access token expired and refresh failed
4. **Invalid State**: OAuth callback state validation failed
5. **Microsoft Errors**: Azure-side authorization issues

### **Error Recovery:**
- Automatic token refresh attempts
- Clear error messages for users
- Fallback to manual OAuth flow
- Comprehensive logging for debugging

## Testing

### **Local Testing:**
1. Configure OAuth credentials in WordPress admin
2. Test OAuth initiation (should redirect to Microsoft)
3. Complete authorization flow
4. Verify tokens are stored
5. Test API calls with OAuth authentication

### **Production Testing:**
1. Deploy to production environment
2. Configure production Azure application
3. Test complete OAuth flow
4. Verify API integration works
5. Monitor token refresh and expiration

## Troubleshooting

### **OAuth Not Working:**
- Check Azure app configuration
- Verify redirect URI matches exactly
- Ensure API permissions are granted
- Check WordPress debug logs for errors

### **Token Issues:**
- Verify client credentials are correct
- Check token expiration settings
- Ensure refresh token is available
- Review OAuth scope configuration

### **Callback Errors:**
- Verify callback URL is accessible
- Check state parameter validation
- Ensure proper error handling
- Review redirect flow

## Performance Considerations

### **Token Management:**
- Tokens cached in WordPress options
- Automatic refresh with 5-minute buffer
- Minimal API calls for token operations
- Efficient state parameter handling

### **Security Balance:**
- Secure token storage
- Regular token refresh
- Proper error handling
- Comprehensive logging

## Future Enhancements

### **Potential Improvements:**
1. **Token Encryption**: Encrypt stored tokens for production
2. **Multiple Accounts**: Support for multiple Business Central environments
3. **Advanced Scopes**: Dynamic permission management
4. **Token Analytics**: Usage statistics and monitoring
5. **Webhook Support**: Real-time token refresh notifications

## Support

### **Getting Help:**
1. Check WordPress debug logs for detailed error messages
2. Verify Azure application configuration
3. Test OAuth flow step by step
4. Review this documentation for common issues
5. Check Microsoft Azure documentation for OAuth details

### **Useful Resources:**
- [Microsoft OAuth 2.0 Documentation](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)
- [WordPress AJAX Documentation](https://codex.wordpress.org/AJAX_in_Plugins)
- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/)

---

**Note**: This OAuth implementation provides a secure, professional-grade authentication system for your Business Central integration. It follows WordPress best practices and Microsoft OAuth standards while maintaining backward compatibility with your existing code.
