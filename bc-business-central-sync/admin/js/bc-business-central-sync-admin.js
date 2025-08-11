/**
 * Admin JavaScript for Business Central Sync.
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin
 */

jQuery(document).ready(function($) {
	
	// Test connection button
	$('#bc-test-connection').on('click', function() {
		var button = $(this);
		var originalText = button.text();
		
		button.prop('disabled', true).text('Testing...');
		showStatus('', '');
		
		$.ajax({
			url: bc_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'bc_test_connection',
				nonce: bc_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					showStatus(response.data.message, 'success');
				} else {
					showStatus(response.data.message, 'error');
				}
			},
			error: function() {
				showStatus('Connection test failed. Please try again.', 'error');
			},
			complete: function() {
				button.prop('disabled', false).text(originalText);
			}
		});
	});
	
	// Sync products button
	$('#bc-sync-products').on('click', function() {
		var button = $(this);
		var originalText = button.text();
		
		if (!confirm('This will sync all products from Business Central to WooCommerce as drafts. Continue?')) {
			return;
		}
		
		button.prop('disabled', true).text('Syncing...');
		showStatus('', '');
		showProgress(true);
		
		$.ajax({
			url: bc_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'bc_sync_products',
				nonce: bc_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					showStatus(response.data.message, 'success');
					// Reload page to show updated sync information
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					showStatus(response.data.message, 'error');
				}
			},
			error: function() {
				showStatus('Product sync failed. Please try again.', 'error');
			},
			complete: function() {
				button.prop('disabled', false).text(originalText);
				showProgress(false);
			}
		});
	});
	
	// Sync pricelists button
	$('#bc-sync-pricelists').on('click', function() {
		var button = $(this);
		var originalText = button.text();
		
		if (!confirm('This will sync all pricelists and pricelist lines from Business Central. Continue?')) {
			return;
		}
		
		button.prop('disabled', true).text('Syncing...');
		showStatus('', '');
		showProgress(true);
		
		$.ajax({
			url: bc_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'bc_sync_pricelists',
				nonce: bc_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					showStatus(response.data.message, 'success');
					// Reload page to show updated sync information
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					showStatus(response.data.message, 'error');
				}
			},
			error: function() {
				showStatus('Pricelist sync failed. Please try again.', 'error');
			},
			complete: function() {
				button.prop('disabled', false).text(originalText);
				showProgress(false);
			}
		});
	});
	
	// Sync customer companies button
	$('#bc-sync-customers').on('click', function() {
		var button = $(this);
		var originalText = button.text();
		
		if (!confirm('This will sync all customer company assignments from Business Central. Continue?')) {
			return;
		}
		
		button.prop('disabled', true).text('Syncing...');
		showStatus('', '');
		showProgress(true);
		
		$.ajax({
			url: bc_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'bc_sync_customers',
				nonce: bc_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					showStatus(response.data.message, 'success');
					// Reload page to show updated sync information
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					showStatus(response.data.message, 'error');
				}
			},
			error: function() {
				showStatus('Customer sync failed. Please try again.', 'error');
			},
			complete: function() {
				button.prop('disabled', false).text(originalText);
				showProgress(false);
			}
		});
	});
	
	// Show status message
	function showStatus(message, type) {
		var statusDiv = $('#bc-sync-status');
		var messageDiv = statusDiv.find('.bc-sync-message');
		
		if (message) {
			statusDiv.removeClass('success error').addClass(type).show();
			messageDiv.html(message);
		} else {
			statusDiv.hide();
		}
	}
	
	// Show/hide progress indicator
	function showProgress(show) {
		var progressDiv = $('#bc-sync-status .bc-sync-progress');
		if (show) {
			progressDiv.show();
		} else {
			progressDiv.hide();
		}
	}
	
	// Auto-save settings on change
	$('select[name="bc_sync_enabled"], select[name="bc_sync_interval"]').on('change', function() {
		$(this).closest('form').submit();
	});
	
});
