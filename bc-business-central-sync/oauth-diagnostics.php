<?php
/**
 * OAuth Diagnostic Script
 * 
 * This script helps identify and fix OAuth configuration issues
 * specifically related to "invalid_request" errors.
 */

// If running from web, show content type
if (!defined('WP_CLI') && !defined('ABSPATH')) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<style>body { font-family: monospace; } .error { color: red; } .warning { color: orange; } .success { color: green; }</style>";
}

echo "<h1>OAuth Configuration Diagnostics</h1>\n";

// Check if this is being run within WordPress
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    echo "<div class='error'>Error: This script must be run within WordPress context.</div>\n";
    echo "<p>Please copy this file to your WordPress root directory and access it via your browser, or run it via WP-CLI.</p>\n";
    exit;
}

echo "<h2>OAuth Configuration Status</h2>\n";

// Check OAuth settings
$client_id = get_option('bc_oauth_client_id', '');
$client_secret = get_option('bc_oauth_client_secret', '');
$tenant_id = get_option('bc_oauth_tenant_id', '');

echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>Setting</th><th>Status</th><th>Value/Notes</th></tr>\n";

// Client ID check
if (empty($client_id)) {
    echo "<tr><td>Client ID</td><td class='error'>Missing</td><td>Not configured</td></tr>\n";
} elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) {
    echo "<tr><td>Client ID</td><td class='success'>Valid</td><td>" . substr($client_id, 0, 8) . "...</td></tr>\n";
} else {
    echo "<tr><td>Client ID</td><td class='error'>Invalid Format</td><td>Should be a GUID format</td></tr>\n";
}

// Client Secret check
if (empty($client_secret)) {
    echo "<tr><td>Client Secret</td><td class='error'>Missing</td><td>Not configured</td></tr>\n";
} elseif (strlen($client_secret) < 16) {
    echo "<tr><td>Client Secret</td><td class='error'>Too Short</td><td>Only " . strlen($client_secret) . " characters</td></tr>\n";
} else {
    echo "<tr><td>Client Secret</td><td class='success'>Valid</td><td>" . strlen($client_secret) . " characters</td></tr>\n";
}

// Tenant ID check
if (empty($tenant_id)) {
    echo "<tr><td>Tenant ID</td><td class='warning'>Default</td><td>Using 'common' (multi-tenant)</td></tr>\n";
} elseif ($tenant_id === 'common') {
    echo "<tr><td>Tenant ID</td><td class='success'>Valid</td><td>Multi-tenant configuration</td></tr>\n";
} elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant_id)) {
    echo "<tr><td>Tenant ID</td><td class='success'>Valid</td><td>Single-tenant: " . substr($tenant_id, 0, 8) . "...</td></tr>\n";
} else {
    echo "<tr><td>Tenant ID</td><td class='error'>Invalid Format</td><td>Should be GUID or 'common'</td></tr>\n";
}

echo "</table>\n";

// Check OAuth URLs
echo "<h2>OAuth URLs</h2>\n";
$effective_tenant = !empty($tenant_id) ? $tenant_id : 'common';
$auth_url = 'https://login.microsoftonline.com/' . $effective_tenant . '/oauth2/v2.0/authorize';
$token_url = 'https://login.microsoftonline.com/' . $effective_tenant . '/oauth2/v2.0/token';
$redirect_uri = admin_url('admin-ajax.php?action=bc_oauth_callback');

echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>URL Type</th><th>URL</th></tr>\n";
echo "<tr><td>Authorization URL</td><td>" . htmlspecialchars($auth_url) . "</td></tr>\n";
echo "<tr><td>Token URL</td><td>" . htmlspecialchars($token_url) . "</td></tr>\n";
echo "<tr><td>Redirect URI</td><td>" . htmlspecialchars($redirect_uri) . "</td></tr>\n";
echo "</table>\n";

// Common issues and solutions
echo "<h2>Common OAuth 'invalid_request' Issues & Solutions</h2>\n";
echo "<ol>\n";

if (empty($client_id) || empty($client_secret)) {
    echo "<li class='error'><strong>Missing Credentials:</strong> Configure your Client ID and Client Secret in the OAuth Settings page.</li>\n";
}

if (!empty($client_id) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) {
    echo "<li class='error'><strong>Invalid Client ID Format:</strong> Client ID must be a GUID (e.g., 12345678-1234-1234-1234-123456789012).</li>\n";
}

if (!empty($tenant_id) && $tenant_id !== 'common' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant_id)) {
    echo "<li class='error'><strong>Invalid Tenant ID:</strong> Tenant ID must be a GUID or 'common' for multi-tenant apps.</li>\n";
}

echo "<li><strong>Redirect URI Mismatch:</strong> Ensure the redirect URI in your Azure app matches exactly: <code>" . htmlspecialchars($redirect_uri) . "</code></li>\n";
echo "<li><strong>App Registration:</strong> Verify your app is registered in the correct Azure AD tenant.</li>\n";
echo "<li><strong>API Permissions:</strong> Ensure your app has 'Dynamics 365 Business Central' API permissions granted.</li>\n";
echo "<li><strong>Public Client:</strong> If using 'common' tenant, ensure your app allows public client flows.</li>\n";
echo "</ol>\n";

// Recommendations
echo "<h2>Recommendations</h2>\n";
echo "<ul>\n";

if (empty($tenant_id) || $tenant_id === 'common') {
    echo "<li class='warning'><strong>Consider Single-Tenant:</strong> For Business Central integration, using a specific tenant ID is often more reliable than 'common'.</li>\n";
}

echo "<li><strong>Test Redirect URI:</strong> Manually test that the redirect URI is accessible: <a href='" . htmlspecialchars($redirect_uri) . "' target='_blank'>" . htmlspecialchars($redirect_uri) . "</a></li>\n";
echo "<li><strong>Check Azure Logs:</strong> Review sign-in logs in Azure AD for detailed error information.</li>\n";
echo "<li><strong>Enable Debug Mode:</strong> Turn on WordPress debug logging to capture detailed OAuth flow information.</li>\n";
echo "</ul>\n";

// Configuration summary
echo "<h2>Configuration Summary</h2>\n";
$issues = 0;

if (empty($client_id)) $issues++;
if (empty($client_secret)) $issues++;
if (!empty($client_id) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) $issues++;

if ($issues === 0) {
    echo "<div class='success'><strong>Configuration appears valid!</strong> If you're still experiencing issues, check the Azure app configuration and permissions.</div>\n";
} else {
    echo "<div class='error'><strong>Configuration issues found:</strong> Please fix the {$issues} issue(s) identified above before proceeding.</div>\n";
}

echo "<hr><p><em>Generated at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
