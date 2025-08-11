# Installation Guide - Business Central to WooCommerce Sync

This guide will walk you through the complete installation and setup process for the Business Central to WooCommerce Sync plugin.

## 📋 Prerequisites

Before installing the plugin, ensure you have:

- **WordPress 5.0+** installed and configured
- **WooCommerce 5.0+** installed and activated
- **PHP 7.4+** with the following extensions:
  - cURL
  - JSON
  - OpenSSL
  - MySQLi
- **Business Central** environment with API access
- **Azure AD** application credentials
- **Dokobit API** credentials (for phone authentication)

## 🚀 Installation Steps

### Step 1: Download and Upload Plugin

1. **Download the plugin** from your source
2. **Extract the ZIP file** to your local machine
3. **Upload to WordPress** using one of these methods:

#### Method A: WordPress Admin Panel (Recommended)
1. Go to **WordPress Admin → Plugins → Add New**
2. Click **Upload Plugin**
3. Choose the extracted plugin folder
4. Click **Install Now**
5. Click **Activate Plugin**

#### Method B: FTP/File Manager
1. Upload the `bc-business-central-sync` folder to `/wp-content/plugins/`
2. Go to **WordPress Admin → Plugins**
3. Find "Business Central to WooCommerce Sync" and click **Activate**

### Step 2: Verify Installation

After activation, you should see:
- A new **BC Sync** menu item in your WordPress admin
- No error messages in the activation process
- WooCommerce dependency check passed

## ⚙️ Configuration

### Step 1: Access Plugin Settings

1. Go to **WordPress Admin → BC Sync**
2. You'll see the main configuration page

### Step 2: Business Central API Configuration

#### Azure AD Application Setup

1. **Go to Azure Portal**
   - Navigate to [portal.azure.com](https://portal.azure.com)
   - Go to **Azure Active Directory → App registrations**

2. **Create New Application**
   - Click **New registration**
   - Name: `Business Central WooCommerce Sync`
   - Supported account types: **Accounts in this organizational directory only**
   - Click **Register**

3. **Get Application ID**
   - Copy the **Application (client) ID**
   - Paste it into the plugin's **Client ID** field

4. **Create Client Secret**
   - Go to **Certificates & secrets**
   - Click **New client secret**
   - Add description: `WooCommerce Sync Secret`
   - Choose expiration (recommend: 24 months)
   - Copy the **Value** (this is your client secret)
   - Paste it into the plugin's **Client Secret** field

5. **Configure API Permissions**
   - Go to **API permissions**
   - Click **Add a permission**
   - Select **Microsoft Graph**
   - Choose **Application permissions**
   - Select **Directory.Read.All**
   - Click **Add permissions**
   - Repeat for **Business Central** → **Delegated permissions** → **Items.Read.All**
   - Click **Grant admin consent for [Your Organization]**

#### Business Central Configuration

1. **Get API URL**
   - Your Business Central API base URL format:
     ```
     https://api.businesscentral.dynamics.com/v2.0/[environment]/[company]
     ```
   - Example: `https://api.businesscentral.dynamics.com/v2.0/production/CRONUS`

2. **Get Company ID**
   - In Business Central, go to **Company Information**
   - Note the **Company ID** value
   - This is usually the same as your company name

3. **Enter Details in Plugin**
   - **API Base URL**: Your Business Central API URL
   - **Company ID**: Your Business Central company ID
   - **Client ID**: Azure AD application ID
   - **Client Secret**: Azure AD client secret

### Step 3: Dokobit Phone Authentication Setup

#### Get Dokobit API Credentials

1. **Visit Dokobit Developers**
   - Go to [developers.dokobit.com](https://developers.dokobit.com)
   - Sign up for a developer account

2. **Create Application**
   - Create a new application
   - Note your **API Key**

3. **Configure in Plugin**
   - **Dokobit API Endpoint**: `https://developers.dokobit.com`
   - **Dokobit API Key**: Your API key from Dokobit

### Step 4: Sync Settings Configuration

1. **Enable Synchronization**
   - Check **Enable automatic synchronization**
   - Choose sync interval: **Hourly**, **Daily**, or **Weekly**

2. **Configure Sync Options**
   - **Sync Pricelists**: Enable to sync Business Central pricelists
   - **Sync Customer Companies**: Enable to sync customer assignments

3. **Save Settings**
   - Click **Save Changes**

## 🧪 Testing the Setup

### Step 1: Test Business Central Connection

1. In the plugin admin, click **Test Connection**
2. You should see: **Connection successful!**
3. If there's an error, check your API credentials

### Step 2: Test Dokobit Connection

1. Click **Test Dokobit Connection**
2. Verify the connection is successful
3. Check for any error messages

### Step 3: Manual Sync Test

1. Click **Sync Products Now**
2. Monitor the sync progress
3. Check for any error messages
4. Verify products appear in WooCommerce

## 🔧 Post-Installation Setup

### Step 1: Company Management

1. **Create Companies**
   - Go to **BC Sync → Companies**
   - Click **Add New Company**
   - Enter company details
   - Save the company

2. **Link Users to Companies**
   - Go to **BC Sync → User Phones**
   - Add user phone numbers
   - Assign users to companies

### Step 2: Pricelist Configuration

1. **Sync Pricelists**
   - Click **Sync Pricelists** in the main admin
   - Verify pricelists are imported

2. **Assign Customers to Pricelists**
   - Go to **BC Sync → Customer Companies**
   - Assign customers to appropriate pricelists

### Step 3: Frontend Integration

1. **Add Shortcodes to Pages**
   - Use `[bc_login_form]` for authentication
   - Use `[bc_company_pricing]` for company pricing
   - Use `[bc_customer_info]` for customer information

2. **Customize Display**
   - Modify CSS files for styling
   - Customize shortcode attributes as needed

## 🚨 Troubleshooting

### Common Installation Issues

#### Plugin Won't Activate
- **Check WooCommerce**: Ensure WooCommerce is installed and activated
- **PHP Version**: Verify PHP 7.4+ is installed
- **File Permissions**: Check folder permissions (755 for folders, 644 for files)

#### API Connection Failed
- **Check Credentials**: Verify all API credentials are correct
- **Network Access**: Ensure your server can reach Business Central APIs
- **Azure AD Permissions**: Verify admin consent was granted

#### Database Tables Not Created
- **Check Permissions**: Ensure WordPress has database write permissions
- **Deactivate/Reactivate**: Try deactivating and reactivating the plugin
- **Check Error Logs**: Look for PHP errors in your server logs

#### Dokobit Authentication Issues
- **API Key**: Verify your Dokobit API key is correct
- **Endpoint**: Ensure the API endpoint is correct
- **Rate Limits**: Check if you've exceeded API rate limits

### Debug Mode

Enable WordPress debug mode for detailed error information:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check the debug log at `/wp-content/debug.log`

## 📞 Getting Help

If you encounter issues during installation:

1. **Check Documentation**: Review this guide and the main README
2. **Enable Debug Mode**: See troubleshooting section above
3. **Check Error Logs**: Look for specific error messages
4. **Contact Support**: Reach out to our support team

### Support Contact Information

- **Website**: [malmsteypa.is](https://malmsteypa.is)
- **Documentation**: [malmsteypa.is/business-central-sync](https://malmsteypa.is/business-central-sync)
- **Email**: support@malmsteypa.is

## ✅ Installation Checklist

- [ ] WordPress 5.0+ installed
- [ ] WooCommerce 5.0+ installed and activated
- [ ] PHP 7.4+ with required extensions
- [ ] Plugin uploaded and activated
- [ ] Business Central API credentials configured
- [ ] Azure AD application set up with proper permissions
- [ ] Dokobit API credentials configured
- [ ] Connection tests successful
- [ ] Manual sync test completed
- [ ] Companies and users configured
- [ ] Pricelists synced
- [ ] Frontend shortcodes integrated
- [ ] Testing completed

## 🎯 Next Steps

After successful installation:

1. **Configure your first sync** to import products
2. **Set up company structure** for your B2B customers
3. **Customize the frontend** to match your site design
4. **Train your team** on using the admin interface
5. **Monitor sync logs** for any issues
6. **Set up automated sync** for ongoing operations

---

**Need help?** Check our [documentation](https://malmsteypa.is/business-central-sync) or [contact support](mailto:support@malmsteypa.is).

**Developed by [Malmsteypa](https://malmsteypa.is)**
