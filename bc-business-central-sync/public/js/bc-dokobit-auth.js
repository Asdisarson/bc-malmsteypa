/**
 * Dokobit Authentication JavaScript
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/public
 */

jQuery(document).ready(function($) {
	
	// Dokobit login form handling
	$('#bc-dokobit-login-form').on('submit', function(e) {
		e.preventDefault();
		
		var form = $(this);
		var phone = $('#bc-dokobit-phone').val();
		var messageDiv = $('#bc-dokobit-message');
		var authInfoDiv = $('#bc-dokobit-auth-info');
		var submitBtn = form.find('.bc-dokobit-submit-btn');
		
		if (!phone) {
			showMessage('Please enter a phone number.', 'error');
			return;
		}
		
		// Disable form and show loading
		submitBtn.prop('disabled', true).text('Sending...');
		messageDiv.hide();
		authInfoDiv.hide();
		
		// Initiate login
		$.ajax({
			url: bc_dokobit_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'bc_dokobit_initiate_login',
				phone: phone,
				nonce: bc_dokobit_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					// Show authentication info
					$('#bc-dokobit-control-code').text(response.data.control_code);
					authInfoDiv.show();
					
					// Start polling for status
					pollAuthStatus(response.data.token);
				} else {
					showMessage(response.data.message, 'error');
				}
			},
			error: function() {
				showMessage('Failed to initiate login. Please try again.', 'error');
			},
			complete: function() {
				submitBtn.prop('disabled', false).text('Login');
			}
		});
	});
	
	/**
	 * Poll authentication status
	 */
	function pollAuthStatus(token) {
		var pollInterval = setInterval(function() {
			$.ajax({
				url: bc_dokobit_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'bc_dokobit_check_auth_status',
					token: token,
					nonce: bc_dokobit_ajax.nonce
				},
				success: function(response) {
					if (response.success) {
						clearInterval(pollInterval);
						showMessage('Authentication successful! Redirecting...', 'success');
						
						// Redirect after short delay
						setTimeout(function() {
							window.location.href = response.data.redirect_url || '/';
						}, 1500);
					} else if (response.data.message === 'Authentication failed or pending') {
						// Continue polling
						return;
					} else {
						clearInterval(pollInterval);
						showMessage(response.data.message, 'error');
					}
				},
				error: function() {
					clearInterval(pollInterval);
					showMessage('Failed to check authentication status.', 'error');
				}
			});
		}, 2000); // Poll every 2 seconds
		
		// Stop polling after 5 minutes
		setTimeout(function() {
			clearInterval(pollInterval);
		}, 300000);
	}
	
	/**
	 * Show message
	 */
	function showMessage(message, type) {
		var messageDiv = $('#bc-dokobit-message');
		messageDiv.removeClass('bc-dokobit-message-success bc-dokobit-message-error')
				.addClass('bc-dokobit-message-' + type)
				.html(message)
				.show();
	}
	
	/**
	 * Phone number formatting
	 */
	$('#bc-dokobit-phone').on('input', function() {
		var value = $(this).val();
		
		// Remove all non-digit characters except +
		value = value.replace(/[^\d+]/g, '');
		
		// Ensure only one + at the beginning
		if (value.indexOf('+') > 0) {
			value = value.replace(/\+/g, '');
			value = '+' + value;
		}
		
		$(this).val(value);
	});
	
	/**
	 * Auto-format phone number on blur
	 */
	$('#bc-dokobit-phone').on('blur', function() {
		var value = $(this).val();
		
		if (value && !value.startsWith('+')) {
			// Add + if not present
			$(this).val('+' + value);
		}
	});
	
	/**
	 * Reset form on successful authentication
	 */
	function resetForm() {
		$('#bc-dokobit-phone').val('');
		$('#bc-dokobit-message').hide();
		$('#bc-dokobit-auth-info').hide();
	}
	
	// Reset form when page loads (in case of successful auth)
	resetForm();
	
});
