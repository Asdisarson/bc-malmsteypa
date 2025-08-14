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
});
