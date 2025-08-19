# Business Central Authentication Troubleshooting Guide

## Error: HTTP 401 Authentication_InvalidCredentials

### **What This Error Means**
The HTTP 401 error with "Authentication_InvalidCredentials" indicates that the OAuth 2.0 access token has expired or is invalid. This is a common issue that occurs when:

1. **Token Expired**: OAuth tokens typically expire after 1 hour
2. **Invalid Credentials**: Client ID, Client Secret, or Tenant ID may be incorrect
3. **Token Cache Issue**: WordPress transient cache may be corrupted
4. **API Permissions**: The Azure AD app may not have the correct permissions

### **Immediate Solutions**

#### **1. Refresh OAuth Token (Recommended)**
- Go to **BC Connector → BC Connector** in your WordPress admin
- Click the **"Refresh OAuth Token"** button in the Authentication Management section
- This will force a new token request and clear the expired cache

#### **2. Manual Token Refresh via Code**
If the button doesn't work, you can manually refresh the token:

```php
// In WordPress admin or via WP-CLI
if (class_exists('BCWoo_Client')) {
    $client = new BCWoo_Client();
    $client->refresh_token();
    echo "Token refreshed successfully";
}
```

#### **3. Clear WordPress Cache**
Clear any caching plugins or transients:

```php
// Clear BC token cache
delete_transient('bcwoo_token');

// Clear other caches if using caching plugins
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}
```

### **Root Cause Analysis**

#### **Token Expiration**
- **Default Lifetime**: OAuth tokens expire after 1 hour (3600 seconds)
- **Buffer Time**: Our system caches tokens with a 60-second buffer before expiry
- **Automatic Refresh**: Enhanced client now automatically retries with token refresh on 401 errors

#### **Configuration Issues**
Check these settings in **BC Connector → Settings**:

1. **Tenant ID**: Must be a valid Azure AD tenant identifier
2. **Client ID**: Must match the Azure AD application registration
3. **Client Secret**: Must be valid and not expired
4. **Company ID**: Must be a valid Business Central company GUID

#### **Azure AD App Configuration**
Ensure your Azure AD app has:

1. **Correct Redirect URI**: `https://yourdomain.com/wp-admin/admin-ajax.php?action=bc_oauth_callback`
2. **API Permissions**: 
   - `https://api.businesscentral.dynamics.com/.default`
   - Delegated permissions for the scope
3. **Client Secret**: Not expired and properly configured

### **Enhanced Error Handling**

#### **Automatic Retry Logic**
The enhanced `BCWoo_Client` now includes:

```php
// Automatic retry with token refresh on 401 errors
if ($code === 401 && $retry) {
    // Clear token and try once more
    delete_transient('bcwoo_token');
    return $this->get($path, $query, false);
}
```

#### **Retry Configuration**
- **Smart Retry**: Single retry attempt with fresh token
- **Token Refresh**: Automatic on authentication failures
- **Efficient**: Minimal overhead with targeted retry logic

### **Prevention Strategies**

#### **1. Regular Token Monitoring**
- Monitor token expiration times
- Set up alerts for authentication failures
- Implement proactive token refresh

#### **2. Configuration Validation**
- Validate Azure AD app settings regularly
- Check client secret expiration dates
- Verify API permissions are correct

#### **3. Error Monitoring**
- Monitor WordPress error logs for BC authentication issues
- Set up health checks for the sync process
- Implement fallback authentication methods

### **Debugging Steps**

#### **Step 1: Check Current Token Status**
```php
// Check if token exists and its expiration
$token = get_transient('bcwoo_token');
if ($token) {
    echo "Token exists: " . substr($token, 0, 20) . "...";
} else {
    echo "No token cached";
}
```

#### **Step 2: Test Azure AD Connection**
```php
// Test basic OAuth flow
try {
    $client = new BCWoo_Client();
    $token = $client->refresh_token();
    echo "New token obtained: " . substr($token, 0, 20) . "...";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### **Step 3: Verify API Endpoint Access**
```php
// Test BC API access with fresh token
try {
    $client = new BCWoo_Client();
    $companies = $client->list_companies();
    echo "API access successful";
} catch (Exception $e) {
    echo "API access failed: " . $e->getMessage();
}
```

### **Common Configuration Mistakes**

#### **1. Incorrect Tenant ID Format**
- **Wrong**: `tenant.onmicrosoft.com`
- **Correct**: `12345678-1234-1234-1234-123456789012`

#### **2. Missing API Permissions**
- **Required**: `https://api.businesscentral.dynamics.com/.default`
- **Type**: Delegated permissions
- **Status**: Must be granted by admin

#### **3. Expired Client Secret**
- **Check**: Azure AD app registration
- **Action**: Generate new client secret
- **Update**: WordPress settings

#### **4. Wrong Environment**
- **Production**: `Production`
- **Sandbox**: `Sandbox`
- **Case sensitive**: Must match exactly

### **Advanced Troubleshooting**

#### **Network Issues**
- Check firewall settings
- Verify proxy configuration
- Test connectivity to `login.microsoftonline.com`

#### **WordPress Configuration**
- Check `wp-config.php` for HTTPS settings
- Verify `WP_HOME` and `WP_SITEURL` are correct
- Ensure proper SSL certificate configuration

#### **Server Requirements**
- **PHP Version**: 7.4 or higher
- **cURL Extension**: Must be enabled
- **OpenSSL**: Required for HTTPS requests
- **Memory Limit**: At least 256MB recommended

### **Getting Help**

#### **1. Check WordPress Error Logs**
```bash
# Common log locations
tail -f /var/log/wordpress/error.log
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

#### **2. Enable WordPress Debug**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### **3. Contact Support**
When reporting issues, include:
- Error message and timestamp
- WordPress version and PHP version
- BC Connector plugin version
- Azure AD app configuration details
- Error logs and debug information

### **Prevention Checklist**

- [ ] Monitor token expiration times
- [ ] Validate Azure AD app configuration monthly
- [ ] Check client secret expiration dates
- [ ] Verify API permissions are correct
- [ ] Test authentication flow regularly
- [ ] Monitor error logs for authentication issues
- [ ] Keep plugin and WordPress updated
- [ ] Document configuration changes
- [ ] Set up health check monitoring
- [ ] Implement automated token refresh if possible

### **Quick Fix Summary**

1. **Immediate**: Click "Refresh OAuth Token" button in admin
2. **Short-term**: Clear WordPress cache and retry
3. **Long-term**: Review and validate Azure AD configuration
4. **Prevention**: Monitor tokens and implement proactive refresh
