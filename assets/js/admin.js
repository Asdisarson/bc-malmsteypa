jQuery(document).ready(function($) {
    
    // Setup Connection button
    $('#bcc-setup-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Checking...');
        
        // Check if all required fields are filled
        var requiredFields = ['tenant_id', 'client_id', 'client_secret', 'company_id', 'bc_environment'];
        var missingFields = [];
        
        requiredFields.forEach(function(field) {
            var value = $('input[name="bcc_settings[' + field + ']"]').val().trim();
            if (!value) {
                missingFields.push(field.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }));
            }
        });
        
        if (missingFields.length > 0) {
            showMessage('Please fill in the following required fields: ' + missingFields.join(', '), 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        // If we have an OAuth authorization URL, redirect user to Microsoft login/consent
        if (typeof bcc_ajax !== 'undefined' && bcc_ajax.auth_url) {
            showMessage('Redirecting to Microsoft for OAuth 2.0 consent...', 'info');
            window.location.href = bcc_ajax.auth_url;
            return;
        }

        showMessage('Please save your settings first so we can build the OAuth URL, then click Setup Connection again.', 'error');
        $button.prop('disabled', false).text(originalText);
    });
    
    // Test Connection button
    $('#bcc-test-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_test_connection',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data, 'success');
                    updateConnectionStatus('connected');
                } else {
                    showMessage(response.data, 'error');
                    updateConnectionStatus('failed');
                }
            },
            error: function() {
                showMessage('An error occurred while testing the connection.', 'error');
                updateConnectionStatus('failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Refresh Connection button
    $('#bcc-refresh-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Refreshing...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_refresh_connection',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateConnectionStatus(response.data.status);
                    showMessage('Connection status refreshed successfully.', 'success');
                } else {
                    showMessage('Failed to refresh connection status.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while refreshing the connection status.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Fetch Customers button (Test page)
    $('#bcc-fetch-customers').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_fetch_customers',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var count = (response.data && Array.isArray(response.data.value)) ? response.data.value.length : 0;
                    renderCustomersRaw(response.data);
                    showMessage('Fetched ' + count + ' customers.', 'success');
                } else if (response.success) {
                    renderCustomersRaw({});
                    showMessage('No customers returned.', 'info');
                } else {
                    showMessage('Failed to fetch customers: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while fetching customers.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                // Enable save button if we have output
                var hasData = $('#bcc-customers-output').text().trim().length > 2;
                $('#bcc-save-customers').prop('disabled', !hasData);
            }
        });
    });

    // Fetch Categories button
    $('#bcc-fetch-categories').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_fetch_categories',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var count = (response.data && Array.isArray(response.data.value)) ? response.data.value.length : 0;
                    renderJsonRaw('#bcc-categories-output', response.data);
                    showMessage('Fetched ' + count + ' categories.', 'success');
                } else if (response.success) {
                    renderJsonRaw('#bcc-categories-output', {});
                    showMessage('No categories returned.', 'info');
                } else {
                    showMessage('Failed to fetch categories: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while fetching categories.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                var hasData = $('#bcc-categories-output').text().trim().length > 2;
                $('#bcc-save-categories').prop('disabled', !hasData);
            }
        });
    });

    // Save Categories button
    $('#bcc-save-categories').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var raw = $('#bcc-categories-output').text();
        if (!raw) {
            showMessage('Nothing to save. Fetch categories first.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Saving...');
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_save_categories',
                nonce: bcc_ajax.nonce,
                payload: raw
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Categories saved. Created: ' + response.data.created + ', Updated: ' + response.data.updated, 'success');
                } else {
                    showMessage('Save failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while saving categories.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Fetch Items button
    $('#bcc-fetch-items').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_fetch_items',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var count = (response.data && Array.isArray(response.data.value)) ? response.data.value.length : 0;
                    renderJsonRaw('#bcc-items-output', response.data);
                    showMessage('Fetched ' + count + ' items.', 'success');
                } else if (response.success) {
                    renderJsonRaw('#bcc-items-output', {});
                    showMessage('No items returned.', 'info');
                } else {
                    showMessage('Failed to fetch items: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while fetching items.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                var hasData = $('#bcc-items-output').text().trim().length > 2;
                $('#bcc-save-items').prop('disabled', !hasData);
            }
        });
    });

    // Save Items button
    $('#bcc-save-items').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var raw = $('#bcc-items-output').text();
        if (!raw) {
            showMessage('Nothing to save. Fetch items first.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Saving...');
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_save_items',
                nonce: bcc_ajax.nonce,
                payload: raw
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Items saved. Created: ' + response.data.created + ', Updated: ' + response.data.updated, 'success');
                } else {
                    showMessage('Save failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while saving items.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Save Customers button
    $('#bcc-save-customers').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var raw = $('#bcc-customers-output').text();
        if (!raw) {
            showMessage('Nothing to save. Fetch customers first.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Saving...');
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_save_customers',
                nonce: bcc_ajax.nonce,
                payload: raw
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Saved. Woo: ' + response.data.woocommerce_saved + ', Dokobit: ' + response.data.dokobit_saved, 'success');
                } else {
                    showMessage('Save failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while saving.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Show message function
    function showMessage(message, type) {
        var $messageDiv = $('#bcc-message');
        var cssClass = 'bcc-message-' + type;
        
        $messageDiv.removeClass().addClass('bcc-message ' + cssClass).html(message).show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $messageDiv.fadeOut();
        }, 5000);
    }
    
    // Update connection status
    function updateConnectionStatus(status) {
        var $statusIndicator = $('.bcc-status-indicator');
        var $statusText = $('.status-text');
        
        $statusIndicator.removeClass().addClass('bcc-status-indicator status-' + status);
        $statusText.text(status.charAt(0).toUpperCase() + status.slice(1));
    }

    // Render the full JSON response (pretty-printed)
    function renderCustomersRaw(data) {
        renderJsonRaw('#bcc-customers-output', data);
    }

    function renderJsonRaw(selector, data) {
        var $out = $(selector);
        var pretty = JSON.stringify(data || {}, null, 2);
        $out.empty();
        var $pre = $('<pre class="bcc-json-output"/>').text(pretty);
        $out.append($pre);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"]/g, function(s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]);
        });
    }
    
    // Form validation
    $('form').on('submit', function(e) {
        var requiredFields = ['tenant_id', 'client_id', 'client_secret', 'company_id', 'bc_environment'];
        var missingFields = [];
        
        requiredFields.forEach(function(field) {
            var value = $('input[name="bcc_settings[' + field + ']"]').val().trim();
            if (!value) {
                missingFields.push(field.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }));
            }
        });
        
        if (missingFields.length > 0) {
            e.preventDefault();
            showMessage('Please fill in the following required fields: ' + missingFields.join(', '), 'error');
            return false;
        }
        
        showMessage('Settings saved successfully!', 'success');
    });
    
    // Auto-refresh connection status every 5 minutes (300 seconds)
    console.log('Setting up auto-refresh every 5 minutes (300,000 ms)');
    setInterval(function() {
        if ($('#bcc-test-connection').is(':visible')) {
            console.log('Auto-refreshing connection status...');
            $('#bcc-refresh-connection').click();
        }
    }, 300000); // 5 minutes = 300,000 milliseconds

    // Enhanced Product Sync functionality
    // Load sync status on page load
    if ($('#bcc-enhanced-sync-page').length || $('body').hasClass('toplevel_page_business-central-connector-enhanced-sync')) {
        loadEnhancedSyncStatus();
    }

    // Refresh sync status button
    $('#bcc-refresh-sync-status').on('click', function() {
        loadEnhancedSyncStatus();
    });

    // Start enhanced sync button
    $('#bcc-start-enhanced-sync').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        // Get selected sync type
        var syncType = $('input[name="sync_type"]:checked').val();
        var lastSyncTimestamp = $('#bcc-last-sync-info').data('timestamp') || '';
        
        if (syncType === 'incremental' && !lastSyncTimestamp) {
            showEnhancedMessage('No previous sync found. Please run a full sync first.', 'error');
            return;
        }
        
        if (!confirm('Start ' + syncType + ' sync? This will synchronize products from Business Central to WooCommerce.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Starting...');
        $('#bcc-stop-sync').prop('disabled', false);
        
        // Show progress bar
        $('.bcc-sync-progress').show();
        updateProgressBar(0, 'Starting sync...');
        
        // Start the sync
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_enhanced_product_sync',
                nonce: bcc_ajax.nonce,
                sync_type: syncType,
                last_sync_timestamp: lastSyncTimestamp
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    showEnhancedSyncResults(result);
                    showEnhancedMessage('Sync completed successfully!', 'success');
                    
                    // Refresh status
                    loadEnhancedSyncStatus();
                } else {
                    showEnhancedMessage('Sync failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showEnhancedMessage('An error occurred while performing the sync.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                $('#bcc-stop-sync').prop('disabled', true);
                $('.bcc-sync-progress').hide();
            }
        });
    });

    // Stop sync button
    $('#bcc-stop-sync').on('click', function() {
        // For now, this is a placeholder - in a real implementation,
        // you'd need to implement a way to stop long-running syncs
        showEnhancedMessage('Stop functionality not yet implemented. Sync will continue until completion.', 'info');
    });

    // Load enhanced sync status
    function loadEnhancedSyncStatus() {
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_get_sync_status',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateEnhancedSyncStatus(response.data);
                } else {
                    showEnhancedMessage('Failed to load sync status: ' + response.data, 'error');
                }
            },
            error: function() {
                showEnhancedMessage('An error occurred while loading sync status.', 'error');
            }
        });
    }

    // Update enhanced sync status display
    function updateEnhancedSyncStatus(data) {
        var lastSync = data.last_sync || 'Never';
        var totalProducts = data.total_products || 0;
        var nextSyncType = data.next_sync_recommended || 'full';
        
        $('#bcc-last-sync-info').html(lastSync === 'Never' ? '<span class="bcc-no-sync">Never</span>' : '<span class="bcc-sync-time">' + lastSync + '</span>');
        $('#bcc-total-products-info').html('<span class="bcc-product-count">' + totalProducts + '</span>');
        $('#bcc-next-sync-info').html('<span class="bcc-sync-type">' + (nextSyncType === 'incremental' ? 'Incremental' : 'Full') + '</span>');
        
        // Store timestamp for incremental sync
        if (data.last_sync && data.last_sync !== 'Never') {
            $('#bcc-last-sync-info').data('timestamp', data.last_sync);
        }
    }

    // Show enhanced sync results
    function showEnhancedSyncResults(result) {
        var $results = $('#bcc-enhanced-sync-results');
        var html = '<div class="bcc-sync-results">';
        
        html += '<h3>Sync Results</h3>';
        html += '<div class="bcc-results-summary">';
        html += '<p><strong>Sync Type:</strong> ' + (result.sync_type === 'incremental' ? 'Incremental' : 'Full') + '</p>';
        html += '<p><strong>Total Processed:</strong> ' + result.total_processed + ' items</p>';
        html += '<p><strong>Created:</strong> ' + result.created + ' products</p>';
        html += '<p><strong>Updated:</strong> ' + result.updated + ' products</p>';
        html += '<p><strong>Skipped:</strong> ' + result.skipped + ' products</p>';
        html += '<p><strong>Sync Time:</strong> ' + result.sync_timestamp + '</p>';
        html += '</div>';
        
        if (result.errors && result.errors.length > 0) {
            html += '<div class="bcc-sync-errors">';
            html += '<h4>Errors (' + result.errors.length + ')</h4>';
            html += '<ul>';
            result.errors.forEach(function(error) {
                html += '<li>' + escapeHtml(error) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $results.html(html);
    }

    // Update progress bar
    function updateProgressBar(percentage, text) {
        $('.bcc-progress-fill').css('width', percentage + '%');
        $('.bcc-progress-text').text(text || (percentage + '% Complete'));
    }

    // Show enhanced message
    function showEnhancedMessage(message, type) {
        var $messageDiv = $('#bcc-enhanced-message');
        var cssClass = 'bcc-message-' + type;
        
        $messageDiv.removeClass().addClass('bcc-message ' + cssClass).html(message).show();
        
        // Auto-hide after 8 seconds for longer operations
        setTimeout(function() {
            $messageDiv.fadeOut();
        }, 8000);
    }

    // New Image Sync functionality for Test Page
    // Sync images for fetched products
    $('#bcc-sync-images-for-fetched').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        // Check if we have fetched products
        var hasFetchedProducts = $('#bcc-items-output').text().trim().length > 2;
        if (!hasFetchedProducts) {
            showMessage('Please fetch products first before syncing images.', 'error');
            return;
        }
        
        if (!confirm('This will sync images for all currently fetched products. Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Syncing Images...');
        
        // Get the fetched products data and sync images for each
        var productsData = JSON.parse($('#bcc-items-output').text());
        var productsWithImages = 0;
        var totalProducts = productsData.value ? productsData.value.length : 0;
        
        if (totalProducts === 0) {
            showMessage('No products found to sync images for.', 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }
        
        // Count products with images
        productsData.value.forEach(function(product) {
            if (product.picture && Object.keys(product.picture).length > 0) {
                productsWithImages++;
            }
        });
        
        if (productsWithImages === 0) {
            showMessage('None of the fetched products have images in Business Central.', 'info');
            $button.prop('disabled', false).text(originalText);
            return;
        }
        
        showMessage('Starting image sync for ' + productsWithImages + ' products with images...', 'info');
        
        // For now, we'll just show a message since the actual sync happens when saving products
        // In the future, this could trigger a separate image-only sync process
        setTimeout(function() {
            showMessage('Image sync initiated. Images will be downloaded when you save the products.', 'success');
            $button.prop('disabled', false).text(originalText);
        }, 2000);
    });

    // Sync all product images (existing functionality)
    $('#bcc-sync-all-product-images').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        if (!confirm('This will attempt to sync images for all products with BC metadata. This may take a while. Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Syncing All Images...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_sync_all_images',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var message = 'Bulk image sync completed. Synced: ' + result.synced_count + ' / ' + result.total_products + ' products.';
                    if (result.errors && result.errors.length > 0) {
                        message += ' Errors: ' + result.errors.length;
                    }
                    showMessage(message, 'success');
                    
                    // Show detailed results in the products output
                    var output = 'Image Sync Results:\n';
                    output += 'Total products: ' + result.total_products + '\n';
                    output += 'Successfully synced: ' + result.synced_count + '\n';
                    if (result.errors && result.errors.length > 0) {
                        output += 'Errors:\n' + result.errors.join('\n');
                    }
                    $('#bcc-items-output').html('<pre>' + escapeHtml(output) + '</pre>');
                } else {
                    showMessage('Image sync failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while performing bulk image sync', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Legacy Image Sync functionality (for Image Sync page)
    // Sync single product image
    $('#bcc-sync-single-image').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var productId = $('#bcc-product-id').val().trim();
        
        if (!productId) {
            showImageSyncMessage('Please enter a product ID', 'error');
            return;
        }
        
        $button.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_sync_single_image',
                nonce: bcc_ajax.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    showImageSyncMessage(response.data, 'success');
                } else {
                    showImageSyncMessage(response.data, 'error');
                }
            },
            error: function() {
                showImageSyncMessage('An error occurred while syncing the image', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Sync image by BC item
    $('#bcc-sync-by-bc-item').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var bcItem = $('#bcc-bc-item').val().trim();
        
        if (!bcItem) {
            showImageSyncMessage('Please enter a BC item number or ID', 'error');
            return;
        }
        
        $button.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_sync_by_bc_item',
                nonce: bcc_ajax.nonce,
                bc_item: bcItem
            },
            success: function(response) {
                if (response.success) {
                    showImageSyncMessage(response.data, 'success');
                } else {
                    showImageSyncMessage(response.data, 'error');
                }
            },
            error: function() {
                showImageSyncMessage('An error occurred while syncing the image', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Sync all product images
    $('#bcc-sync-all-images').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        if (!confirm('This will attempt to sync images for all products with BC metadata. This may take a while. Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Syncing All...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_sync_all_images',
                nonce: bcc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var message = 'Bulk sync completed. Synced: ' + result.synced_count + ' / ' + result.total_products + ' products.';
                    if (result.errors && result.errors.length > 0) {
                        message += ' Errors: ' + result.errors.length;
                    }
                    showImageSyncMessage(message, 'success');
                    
                    // Show detailed results
                    var output = 'Sync Results:\n';
                    output += 'Total products: ' + result.total_products + '\n';
                    output += 'Successfully synced: ' + result.synced_count + '\n';
                    if (result.errors && result.errors.length > 0) {
                        output += 'Errors:\n' + result.errors.join('\n');
                    }
                    $('#bcc-image-sync-output').html('<pre>' + escapeHtml(output) + '</pre>');
                } else {
                    showImageSyncMessage(response.data, 'error');
                }
            },
            error: function() {
                showImageSyncMessage('An error occurred while performing bulk sync', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Show image sync message
    function showImageSyncMessage(message, type) {
        var $messageDiv = $('#bcc-image-sync-message');
        var cssClass = 'bcc-message-' + type;
        
        $messageDiv.removeClass().addClass('bcc-message ' + cssClass).html(message).show();
        
        // Auto-hide after 8 seconds for longer operations
        setTimeout(function() {
            $messageDiv.fadeOut();
        }, 8000);
    }

    // Simple Sync functionality using BCWoo_Sync class
    $('#bcc-simple-full-sync').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        if (!confirm('Start full sync? This will import all products from Business Central to WooCommerce.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Starting Full Sync...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_simple_sync',
                nonce: bcc_ajax.nonce,
                sync_type: 'full'
            },
            success: function(response) {
                if (response.success) {
                    showSimpleSyncMessage(response.data, 'success');
                } else {
                    showSimpleSyncMessage(response.data, 'error');
                }
            },
            error: function() {
                showSimpleSyncMessage('An error occurred while performing the sync.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    $('#bcc-simple-incremental-sync').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        if (!confirm('Start incremental sync? This will import only changed products since the last sync.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Starting Incremental Sync...');
        
        $.ajax({
            url: bcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcc_simple_sync',
                nonce: bcc_ajax.nonce,
                sync_type: 'incremental'
            },
            success: function(response) {
                if (response.success) {
                    showSimpleSyncMessage(response.data, 'success');
                } else {
                    showSimpleSyncMessage(response.data, 'error');
                }
            },
            error: function() {
                showSimpleSyncMessage('An error occurred while performing the sync.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Show simple sync message
    function showSimpleSyncMessage(message, type) {
        var $messageDiv = $('#bcc-simple-sync-message');
        var cssClass = 'bcc-message-' + type;
        
        $messageDiv.removeClass().addClass('bcc-message ' + cssClass).html(message).show();
        
        // Auto-hide after 8 seconds
        setTimeout(function() {
            $messageDiv.fadeOut();
        }, 8000);
    }
});
