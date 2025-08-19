<?php
/**
 * Business Central Admin Class - Enhanced Version
 * 
 * This enhanced admin class provides:
 * - Dry Run preview button
 * - Rebuild Images action (by SKU or all)
 * - Category mapping UI (BC category → Woo category)
 * - Enhanced sync controls
 */

if (!defined('ABSPATH')) {
    exit;
}

class Business_Central_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Business Central Connector',
            'BC Connector',
            'manage_options',
            'business-central-connector',
            array($this, 'admin_page'),
            'dashicons-admin-generic',
            30
        );



        // Enhanced Product Sync subpage
        add_submenu_page(
            'business-central-connector',
            'BC Connector: Enhanced Sync',
            'Enhanced Sync',
            'manage_options',
            'business-central-connector-enhanced-sync',
            array($this, 'admin_enhanced_sync_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('bcc_settings_group', 'bcc_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'bcc_connection_section',
            'Connection Settings',
            array($this, 'connection_section_callback'),
            'business-central-connector'
        );
        
        // Add settings fields
        $fields = array(
            'base_url' => 'Base URL',
            'callback_url' => 'Callback URL',
            'tenant_id' => 'Tenant ID',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'company_id' => 'Company ID',
            'bc_environment' => 'BC Environment',
            'api_version' => 'API Version',
            'access_token' => 'Access Token',
            // Dokobit configuration
            'dokobit_api_base' => 'Dokobit API Base URL',
            'dokobit_api_key' => 'Dokobit API Key'
        );
        
        foreach ($fields as $field => $label) {
            add_settings_field(
                'bcc_' . $field,
                $label,
                array($this, 'field_callback'),
                'business-central-connector',
                'bcc_connection_section',
                array('field' => $field)
            );
        }

        // Category mapping section
        add_settings_section(
            'bcc_category_mapping_section',
            'Category Mapping (BC → Woo)',
            array($this, 'category_mapping_section_callback'),
            'business-central-connector'
        );
        
        add_settings_field(
            'bcc_category_map',
            'Category Mappings',
            array($this, 'category_mapping_field_callback'),
            'business-central-connector',
            'bcc_category_mapping_section'
        );
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) return;
        if (empty($_POST)) return;
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bcc_actions')) return;

        try {
            if (isset($_POST['bcc_run_full_sync'])) {
                if (class_exists('BCWoo_Sync')) {
                    BCWoo_Sync::run_full();
                    $this->notice('Full sync completed successfully.');
                } else {
                    $this->notice('BCWoo_Sync class not found. Please ensure the sync class is loaded.', 'error');
                }
            } elseif (isset($_POST['bcc_run_incremental_sync'])) {
                if (class_exists('BCWoo_Sync')) {
                    BCWoo_Sync::run_incremental();
                    $this->notice('Incremental sync completed successfully.');
                } else {
                    $this->notice('BCWoo_Sync class not found. Please ensure the sync class is loaded.', 'error');
                }
            } elseif (isset($_POST['bcc_dry_run'])) {
                if (class_exists('BCWoo_Sync')) {
                    $html = BCWoo_Sync::dry_run_with_diffs();
                    set_transient('bcc_dry_run_report', $html, 300);
                    $this->notice('Dry run completed. See report below.');
                } else {
                    $this->notice('BCWoo_Sync class not found. Please ensure the sync class is loaded.', 'error');
                }
            } elseif (isset($_POST['bcc_rebuild_images'])) {
                $sku = sanitize_text_field($_POST['sku'] ?? '');
                if (class_exists('BCWoo_Sync')) {
                    $count = BCWoo_Sync::rebuild_images($sku ?: null);
                    $this->notice('Rebuilt images for ' . intval($count) . ' product(s).');
                } else {
                    $this->notice('BCWoo_Sync class not found. Please ensure the sync class is loaded.', 'error');
                }
            } elseif (isset($_POST['bcc_refresh_token'])) {
                if (class_exists('BCWoo_Client')) {
                    try {
                        $client = new BCWoo_Client();
                        $client->refresh_token();
                        $this->notice('OAuth token refreshed successfully.');
                    } catch (\Throwable $e) {
                        $this->notice('Failed to refresh OAuth token: ' . esc_html($e->getMessage()), 'error');
                    }
                } else {
                    $this->notice('BCWoo_Client class not found. Please ensure the client class is loaded.', 'error');
                }
            }
        } catch (\Throwable $e) {
            $this->notice('Error: ' . esc_html($e->getMessage()), 'error');
        }
    }

    /**
     * Dry run preview - shows what would be synced without actually syncing
     */
    private function dry_run_preview() {
        try {
            $opts = get_option('bcc_settings', []);
            $companyId = $opts['company_id'] ?? '';
            if (!$companyId) {
                return '<div class="notice notice-error"><p>Company ID missing from settings.</p></div>';
            }

            // Get client and fetch items for preview
            if (!class_exists('BCWoo_Client')) {
                return '<div class="notice notice-error"><p>BCWoo_Client class not found.</p></div>';
            }

            $client = new BCWoo_Client();
            
            // Build filter for incremental preview
            $filter = null;
            if (!empty($opts['last_sync'])) {
                $since = esc_sql($opts['last_sync']);
                $filter = "lastModifiedDateTime gt {$since}";
            }

            // Fetch items (limit to first 50 for preview)
            $page = $client->list_items($companyId, $filter, 50);
            $items = $page['value'] ?? [];

            if (empty($items)) {
                return '<div class="notice notice-info"><p>No items found for incremental sync.</p></div>';
            }

            // Build preview table
            $html = '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr>';
            $html .= '<th>SKU</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Images</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($items as $item) {
                $sku = $item['number'] ?? 'N/A';
                $name = $item['displayName'] ?? ($item['description'] ?? 'N/A');
                $price = isset($item['unitPrice']) ? number_format($item['unitPrice'], 2) : 'N/A';
                $stock = isset($item['inventory']) ? intval($item['inventory']) : 'N/A';
                $status = !empty($item['blocked']) ? 'Blocked' : 'Active';
                $images = isset($item['picture']) ? count($item['picture']) : 0;

                $html .= '<tr>';
                $html .= '<td>' . esc_html($sku) . '</td>';
                $html .= '<td>' . esc_html($name) . '</td>';
                $html .= '<td>' . esc_html($price) . '</td>';
                $html .= '<td>' . esc_html($stock) . '</td>';
                $html .= '<td>' . esc_html($status) . '</td>';
                $html .= '<td>' . esc_html($images) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '<p><strong>Total items to sync:</strong> ' . count($items) . '</p>';
            
            if (!empty($filter)) {
                $html .= '<p><strong>Filter applied:</strong> ' . esc_html($filter) . '</p>';
            }

            return $html;

        } catch (\Throwable $e) {
            return '<div class="notice notice-error"><p>Dry run failed: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Rebuild images for products
     */
    private function rebuild_images($sku = null) {
        $count = 0;
        
        if ($sku) {
            // Rebuild for specific SKU
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id) {
                $this->rebuild_product_images($product_id);
                $count = 1;
            }
        } else {
            // Rebuild for all products
            $products = wc_get_products([
                'limit' => -1,
                'status' => 'publish',
                'return' => 'ids'
            ]);
            
            foreach ($products as $product_id) {
                $this->rebuild_product_images($product_id);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Rebuild images for a specific product
     */
    private function rebuild_product_images($product_id) {
        try {
            $product = wc_get_product($product_id);
            if (!$product) return;

            $sku = $product->get_sku();
            if (empty($sku)) return;

            // Get BC client and fetch images
            if (!class_exists('BCWoo_Client')) return;
            
            $opts = get_option('bcc_settings', []);
            $companyId = $opts['company_id'] ?? '';
            if (!$companyId) return;

            $client = new BCWoo_Client();
            
            // Find item by SKU
            $filter = "number eq '{$sku}'";
            $page = $client->list_items($companyId, $filter, 1);
            $items = $page['value'] ?? [];
            
            if (empty($items)) return;
            
            $item = $items[0];
            $images = $this->pull_pictures($client, $companyId, $item['id']);
            
            if (!empty($images)) {
                // Clear existing images
                $product->set_image_id(0);
                $product->set_gallery_image_ids([]);
                $product->save();
                
                // Import new images
                $attachment_ids = $this->import_images($images, $product_id);
                
                if (!empty($attachment_ids)) {
                    $product->set_image_id(array_shift($attachment_ids));
                    $product->set_gallery_image_ids($attachment_ids);
                    $product->save();
                }
            }
            
        } catch (\Throwable $e) {
            error_log('[BCC] Image rebuild failed for product ' . $product_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Pull pictures from BC (copied from BCWoo_Sync for compatibility)
     */
    private function pull_pictures($client, $companyId, $itemId) {
        try {
            $pics = $client->get_item_pictures($companyId, $itemId);
            $images = [];
            foreach (($pics['value'] ?? []) as $p) {
                $resp = $client->download_picture_stream($p);
                if ($resp) $images[] = $resp;
            }
            return $images;
        } catch (\Throwable $e) {
            error_log('[BCC] Failed to pull pictures: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Import images (copied from BCWoo_Sync for compatibility)
     */
    private function import_images($image_responses, $product_id) {
        $ids = [];
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        foreach ($image_responses as $i => $resp) {
            if (!$resp) continue;
            $body = wp_remote_retrieve_body($resp);
            $type = wp_remote_retrieve_header($resp, 'content-type');
            $ext  = ($type && strpos($type, '/') !== false) ? explode('/', $type)[1] : 'jpg';
            $filename = "bc-{$product_id}-" . ($i+1) . ".{$ext}";

            // Sideload to media library
            $tmp = wp_tempnam($filename);
            if (!$tmp) continue;
            file_put_contents($tmp, $body);

            $file = [
                'name' => $filename,
                'type' => $type ?: 'image/jpeg',
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => filesize($tmp),
            ];
            
            $attach_id = media_handle_sideload($file, 0, "Imported from Business Central");
            if (!is_wp_error($attach_id)) {
                $ids[] = $attach_id;
            } else {
                @unlink($tmp);
            }
        }
        return $ids;
    }

    /**
     * Category mapping section callback
     */
    public function category_mapping_section_callback() {
        echo '<p>Map Business Central item categories to WooCommerce product categories. One mapping per line: <code>BCCode => Woo Category Name</code></p>';
        echo '<p><strong>Example:</strong> <code>ACCESS => Accessories</code></p>';
    }

    /**
     * Category mapping field callback
     */
    public function category_mapping_field_callback() {
        $settings = get_option('bcc_settings', array());
        $value = isset($settings['category_map']) ? $settings['category_map'] : '';
        
        echo '<textarea name="bcc_settings[category_map]" rows="8" class="large-text code" placeholder="BCCode => WooCategory">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Enter one mapping per line. The format is: <code>BC Category Code => WooCommerce Category Name</code></p>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load scripts on main settings page and enhanced sync page
        $allowed_pages = array('business-central-connector', 'business-central-connector-enhanced-sync');
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (!in_array($current_page, $allowed_pages, true)) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'bcc-admin-js',
            BCC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            BCC_PLUGIN_VERSION,
            true
        );
        
        // Build OAuth authorization URL for JS redirect
        $settings = get_option('bcc_settings', array());
        $tenant_id = isset($settings['tenant_id']) ? $settings['tenant_id'] : '';
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $redirect_uri = isset($settings['callback_url']) ? $settings['callback_url'] : '';
        $auth_url = '';
        if (!empty($tenant_id) && !empty($client_id) && !empty($redirect_uri)) {
            $params = array(
                'client_id' => $client_id,
                'response_type' => 'code',
                'redirect_uri' => $redirect_uri,
                'scope' => 'https://api.businesscentral.dynamics.com/.default',
                'response_mode' => 'query'
            );
            $auth_url = 'https://login.microsoftonline.com/' . rawurlencode($tenant_id) . '/oauth2/v2.0/authorize?' . http_build_query($params);
        }
        
        wp_localize_script('bcc-admin-js', 'bcc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bcc_nonce'),
            'auth_url' => $auth_url
        ));
        
        wp_enqueue_style(
            'bcc-admin-css',
            BCC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BCC_PLUGIN_VERSION
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        $settings = get_option('bcc_settings', array());
        $connection_status = isset($settings['connection_status']) ? $settings['connection_status'] : 'disconnected';
        ?>
        <div class="wrap">
            <h1>Business Central Connector</h1>
            <?php
            if (isset($_GET['bcc_oauth'])) {
                $notice = sanitize_text_field($_GET['bcc_oauth']);
                if ($notice === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>Successfully connected via OAuth 2.0.</p></div>';
                } elseif ($notice === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>OAuth 2.0 connection failed. Please try again.</p></div>';
                }
            }
            ?>
            <div class="bcc-status-section">
                <h2>Connection Status</h2>
                <div class="bcc-status-indicator status-<?php echo esc_attr($connection_status); ?>">
                    <span class="status-text"><?php echo esc_html(ucfirst($connection_status)); ?></span>
                </div>
            </div>
            
            <div class="bcc-actions-section">
                <h2>Connection Management</h2>
                <div class="bcc-buttons">
                    <button type="button" class="button button-primary" id="bcc-setup-connection">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        Setup Connection
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-test-connection">
                        <span class="dashicons dashicons-admin-tools"></span>
                        Test Connection
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-refresh-connection">
                        <span class="dashicons dashicons-update"></span>
                        Refresh Status
                    </button>
                </div>
                <div id="bcc-message" class="bcc-message"></div>
                <p class="description">Product fetching from Business Central.</p>
            </div>

            <!-- Token Management Section -->
            <div class="bcc-actions-section">
                <h2>Authentication Management</h2>
                <form method="post" style="display:inline-block;margin-right:10px;">
                    <?php wp_nonce_field('bcc_actions'); ?>
                    <input type="hidden" name="bcc_refresh_token" value="1"/>
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        Refresh OAuth Token
                    </button>
                </form>
                <p class="description">Force refresh the OAuth token if you're experiencing authentication errors.</p>
            </div>

            <!-- Enhanced Sync Controls -->
            <div class="bcc-actions-section">
                <h2>Product Synchronization</h2>
                <form method="post" style="display:inline-block;margin-right:10px;">
                    <?php wp_nonce_field('bcc_actions'); ?>
                    <input type="hidden" name="bcc_run_full_sync" value="1"/>
                    <button type="submit" class="button button-primary">Run Full Import</button>
                </form>

                <form method="post" style="display:inline-block;margin-right:10px;">
                    <?php wp_nonce_field('bcc_actions'); ?>
                    <input type="hidden" name="bcc_run_incremental_sync" value="1"/>
                    <button type="submit" class="button button-secondary">Run Incremental</button>
                </form>

                <form method="post" style="display:inline-block;">
                    <?php wp_nonce_field('bcc_actions'); ?>
                    <input type="hidden" name="bcc_dry_run" value="1"/>
                    <button type="submit" class="button button-secondary">Preview Incremental (Dry Run)</button>
                </form>
            </div>

            <!-- Rebuild Images Section -->
            <div class="bcc-actions-section">
                <h2>Rebuild Images</h2>
                <form method="post" style="display:flex;gap:10px;align-items:center;">
                    <?php wp_nonce_field('bcc_actions'); ?>
                    <input type="hidden" name="bcc_rebuild_images" value="1"/>
                    <label>SKU (leave blank = ALL): <input type="text" name="sku" value="" class="regular-text" /></label>
                    <button type="submit" class="button button-secondary">Rebuild Images</button>
                </form>
            </div>

            <!-- Dry Run Report Display -->
            <?php if ($report = get_transient('bcc_dry_run_report')): ?>
                <div class="bcc-actions-section">
                    <h2>Dry Run Report</h2>
                    <?php echo $report; // Already escaped HTML table ?>
                </div>
                <?php delete_transient('bcc_dry_run_report'); ?>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('bcc_settings_group');
                do_settings_sections('business-central-connector');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }



    /**
     * Admin enhanced sync page callback
     */
    public function admin_enhanced_sync_page() {
        ?>
        <div class="wrap">
            <h1>BC Connector – Enhanced Product Sync</h1>
            <p>Enhanced product synchronization with Business Central using MVP scope and incremental sync support.</p>
            
            <!-- Debug info -->
            <div id="debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
                <h3>Debug Information</h3>
                <p><strong>JavaScript Loaded:</strong> <span id="js-status">Checking...</span></p>
                <p><strong>bcc_ajax Object:</strong> <span id="ajax-status">Checking...</span></p>
                <p><strong>Button Elements:</strong> <span id="button-status">Checking...</span></p>
            </div>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Check if JavaScript is loaded
                $('#js-status').text('Loaded ✓');
                
                // Check if bcc_ajax object exists
                if (typeof bcc_ajax !== 'undefined') {
                    $('#ajax-status').text('Available ✓');
                    console.log('bcc_ajax object:', bcc_ajax);
                } else {
                    $('#ajax-status').text('Missing ✗');
                    console.error('bcc_ajax object not found');
                }
                
                // Check if buttons exist
                var buttons = ['#bcc-start-enhanced-sync', '#bcc-simple-full-sync', '#bcc-simple-incremental-sync'];
                var foundButtons = [];
                buttons.forEach(function(selector) {
                    if ($(selector).length > 0) {
                        foundButtons.push(selector);
                    }
                });
                
                if (foundButtons.length > 0) {
                    $('#button-status').text('Found: ' + foundButtons.join(', ') + ' ✓');
                } else {
                    $('#button-status').text('None found ✗');
                }
                
                // Test button click
                $('#bcc-start-enhanced-sync').on('click', function() {
                    console.log('Enhanced sync button clicked');
                    alert('Enhanced sync button clicked - this means JavaScript is working!');
                });
                
                $('#bcc-simple-full-sync').on('click', function() {
                    console.log('Simple full sync button clicked');
                    alert('Simple full sync button clicked - this means JavaScript is working!');
                });
                
                $('#bcc-simple-incremental-sync').on('click', function() {
                    console.log('Simple incremental sync button clicked');
                    alert('Simple incremental sync button clicked - this means JavaScript is working!');
                });
            });
            </script>

            <div class="bcc-sync-status-section">
                <h2>Sync Status</h2>
                <div class="bcc-status-grid">
                    <div class="bcc-status-card">
                        <h3><span class="dashicons dashicons-clock"></span> Last Sync</h3>
                        <div id="bcc-last-sync-info" class="bcc-status-info">
                            <span class="bcc-loading">Loading...</span>
                        </div>
                    </div>
                    <div class="bcc-status-card">
                        <h3><span class="dashicons dashicons-products"></span> Total Products</h3>
                        <div id="bcc-total-products-info" class="bcc-status-info">
                            <span class="bcc-loading">Loading...</span>
                        </div>
                    </div>
                    <div class="bcc-status-card">
                        <h3><span class="dashicons dashicons-update"></span> Next Sync Type</h3>
                        <div id="bcc-next-sync-info" class="bcc-status-info">
                            <span class="bcc-loading">Loading...</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="button button-secondary" id="bcc-refresh-sync-status">
                    <span class="dashicons dashicons-update"></span>
                    Refresh Status
                </button>
            </div>

            <div class="bcc-actions-section">
                <h2>Product Synchronization</h2>
                <div class="bcc-sync-options">
                    <div class="bcc-sync-option">
                        <label>
                            <input type="radio" name="sync_type" value="full" checked>
                            <strong>Full Sync</strong> - Import all products from Business Central
                        </label>
                        <p class="description">Use for initial setup or when you want to refresh all data.</p>
                    </div>
                    <div class="bcc-sync-option">
                        <label>
                            <input type="radio" name="sync_type" value="incremental">
                            <strong>Incremental Sync</strong> - Import only changed products since last sync
                        </label>
                        <p class="description">Faster and more efficient for regular updates.</p>
                    </div>
                </div>
                
                <div class="bcc-buttons">
                    <button type="button" class="button button-primary" id="bcc-start-enhanced-sync">
                        <span class="dashicons dashicons-update"></span>
                        Start Enhanced Sync
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-stop-sync" disabled>
                        <span class="dashicons dashicons-controls-stop"></span>
                        Stop Sync
                    </button>
                </div>
                
                <div class="bcc-sync-progress" style="display: none;">
                    <div class="bcc-progress-bar">
                        <div class="bcc-progress-fill"></div>
                    </div>
                    <div class="bcc-progress-text">0% Complete</div>
                </div>
            </div>

            <div class="bcc-actions-section">
                <h2>Simple Sync (BCWoo_Sync)</h2>
                <p>Use the clean BCWoo_Sync class for direct, efficient synchronization.</p>
                
                <div class="bcc-buttons">
                    <button type="button" class="button button-primary" id="bcc-simple-full-sync">
                        <span class="dashicons dashicons-update"></span>
                        Full Sync
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-simple-incremental-sync">
                        <span class="dashicons dashicons-update"></span>
                        Incremental Sync
                    </button>
                </div>
                
                <div id="bcc-simple-sync-message" class="bcc-message" style="display: none;"></div>
            </div>

            <div class="bcc-mvp-scope-section">
                <h2>MVP Scope - What Gets Synced</h2>
                <div class="bcc-scope-grid">
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-tag"></span>
                        <strong>Item Number → SKU</strong>
                        <p>Business Central item number becomes WooCommerce SKU</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-edit"></span>
                        <strong>Display Name → Product Name</strong>
                        <p>BC display name becomes WooCommerce product title</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-text"></span>
                        <strong>Description → Content</strong>
                        <p>BC description becomes product content and excerpt</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-money-alt"></span>
                        <strong>Unit Price → Regular Price</strong>
                        <p>BC unit price becomes WooCommerce regular price</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <strong>Inventory → Stock Management</strong>
                        <p>BC inventory becomes WooCommerce stock quantity</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-category"></span>
                        <strong>Item Category → Woo Category</strong>
                        <p>BC item category maps to WooCommerce product category</p>
                    </div>
                    <div class="bcc-scope-item">
                        <span class="dashicons dashicons-format-image"></span>
                        <strong>Images → Featured + Gallery</strong>
                        <p>BC images become featured image and gallery</p>
                    </div>
                </div>
            </div>

            <div id="bcc-enhanced-message" class="bcc-message"></div>

            <div class="bcc-results-section">
                <h2>Sync Results</h2>
                <div id="bcc-enhanced-sync-results" class="bcc-output-content">
                    <p class="bcc-no-results">No sync results yet. Start a sync to see results here.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Connection section callback
     */
    public function connection_section_callback() {
        echo '<p>Configure your Business Central connection settings below:</p>';
    }
    
    /**
     * Field callback for text inputs
     */
    public function field_callback($args) {
        $settings = get_option('bcc_settings', array());
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        if ($field === 'base_url') {
            $value = 'https://api.businesscentral.dynamics.com/';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'callback_url') {
            $value = 'https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'api_version') {
            $value = 'v2.0';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'client_secret') {
            echo '<input type="password" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        } elseif ($field === 'dokobit_api_base') {
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://gateway-sandbox.dokobit.com" />';
            echo '<p class="description">Base URL of Dokobit API (e.g., sandbox or production gateway).</p>';
        } elseif ($field === 'dokobit_api_key') {
            echo '<input type="password" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">API key/token for Dokobit. Stored in WordPress options.</p>';
        } elseif ($field === 'access_token') {
            echo '<input type="password" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($field) . '" class="regular-text" />';
            echo '<p class="description">Access token for Business Central API.</p>';
        } else {
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $fields = array('base_url', 'callback_url', 'tenant_id', 'client_id', 'client_secret', 'company_id', 'bc_environment', 'api_version', 'access_token', 'dokobit_api_base', 'dokobit_api_key', 'category_map');
        
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }
        
        // Set default values
        $sanitized['base_url'] = 'https://api.businesscentral.dynamics.com/';
        $sanitized['callback_url'] = 'https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback';
        $sanitized['api_version'] = 'v2.0';
        
        return $sanitized;
    }

    /**
     * Display admin notice
     */
    private function notice($msg, $type = 'success') {
        add_action('admin_notices', function() use ($msg, $type) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($msg));
        });
    }
}
