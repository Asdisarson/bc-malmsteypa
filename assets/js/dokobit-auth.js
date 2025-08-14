jQuery(document).ready(function($) {
    var checkInterval;
    var authToken;
    
    $('#dokobit-login-form').on('submit', function(e) {
        e.preventDefault();
        
        var phone = $('#dokobit-phone').val();
        var $form = $(this);
        var $message = $('#dokobit-message');
        var $authInfo = $('#dokobit-auth-info');
        var $submitBtn = $form.find('.dokobit-submit-btn');
        
        $message.hide().removeClass('error success');
        $authInfo.hide();
        
        $submitBtn.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: dokobit_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dokobit_initiate_login',
                phone: phone,
                nonce: dokobit_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    authToken = response.data.token;
                    $('#dokobit-control-code').text(response.data.control_code);
                    $authInfo.show();
                    checkAuthStatus();
                } else {
                    $message.addClass('error').text(response.data.message).show();
                    $submitBtn.prop('disabled', false).text('Login');
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.').show();
                $submitBtn.prop('disabled', false).text('Login');
            }
        });
    });
    
    function checkAuthStatus() {
        var attempts = 0;
        var maxAttempts = 60;
        
        checkInterval = setInterval(function() {
            attempts++;
            
            if (attempts > maxAttempts) {
                clearInterval(checkInterval);
                $('#dokobit-message').addClass('error').text('Authentication timeout. Please try again.').show();
                $('#dokobit-auth-info').hide();
                $('.dokobit-submit-btn').prop('disabled', false).text('Login');
                return;
            }
            
            $.ajax({
                url: dokobit_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dokobit_check_auth_status',
                    token: authToken,
                    nonce: dokobit_ajax.nonce
                },
                success: function(response) {
                    console.log('Auth status response:', response);
                    
                    if (response.success) {
                        if (response.data.status === 'authenticated') {
                            clearInterval(checkInterval);
                            $('#dokobit-message').addClass('success').text('Authentication successful! Redirecting...').show();
                            $('#dokobit-auth-info').hide();
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        clearInterval(checkInterval);
                        if (response.data && response.data.debug) {
                            console.error('Authentication failed. Debug info:', response.data.debug);
                        }
                        $('#dokobit-message').addClass('error').text(response.data.message).show();
                        $('#dokobit-auth-info').hide();
                        $('.dokobit-submit-btn').prop('disabled', false).text('Login');
                    }
                }
            });
        }, 2000);
    }
});


