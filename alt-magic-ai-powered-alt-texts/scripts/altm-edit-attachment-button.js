jQuery(document).ready(function ($) {
    // Authentication error modal functions
    function createAuthErrorModal() {
        if ($('#altm-auth-error-modal').length) {
            return; // Modal already exists
        }
        
        var modalHtml = '<div id="altm-auth-error-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001;">' +
            '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 400px; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">' +
            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
            '<h3 style="margin: 0; color: #b70000;">⚠️ Authentication Error</h3>' +
            '<button id="close-auth-error-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; line-height: 1;">&times;</button>' +
            '</div>' +
            '<div style="margin-bottom: 20px; line-height: 1.6;">' +
            '<p id="auth-error-message" style="margin: 0;">Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.</p>' +
            '</div>' +
            '<div style="text-align: end;">' +
            '<button id="dismiss-auth-error" class="button" style="margin-right: 10px;">Dismiss</button>' +
            '<a href="' + (typeof altm_data !== 'undefined' && altm_data.account_settings_url ? altm_data.account_settings_url : '/wp-admin/admin.php?page=alt-magic') + '" class="button button-primary">Go to Account Settings</a>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(modalHtml);
        
        // Close modal handlers
        $(document).on('click', '#close-auth-error-modal, #dismiss-auth-error', function () {
            $('#altm-auth-error-modal').fadeOut(200);
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#altm-auth-error-modal', function (e) {
            if (e.target === this) {
                $('#altm-auth-error-modal').fadeOut(200);
            }
        });
    }
    
    function showAuthErrorModal(message) {
        createAuthErrorModal();
        var errorMessage = message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.';
        $('#auth-error-message').text(errorMessage);
        $('#altm-auth-error-modal').fadeIn(200);
    }
    
    // Add CSS for loader
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .loader {
                width: 20px;
                height: 20px;
                border: 3px solid #ec7b4e;
                border-bottom-color: transparent;
                border-radius: 50%;
                display: inline-block;
                box-sizing: border-box;
                animation: rotation 1s linear infinite;
                margin-left: 10px;
                vertical-align: middle;
            }

            @keyframes rotation {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }
            
            .altm-edit-attachment-button-container {
                margin-top: 10px;
            }
            
            #altm-generate-alt-text {
                background: linear-gradient(135deg, #ec7b4e 0%, #e56a3a 100%) !important;
                color: white !important;
                text-shadow: 0 -1px 1px rgba(0,0,0,0.1) !important;
                border: none !important;
                position: relative;
                overflow: hidden;
                font-weight: 500 !important;
                transition: all 0.3s ease;
                box-shadow: 0 2px 5px rgba(236, 123, 78, 0.3) !important;
                padding: 12px 22px !important;
                border-radius: 4px !important;
            }
            
            #altm-generate-alt-text:before {
                content: '\\f155'; /* WordPress dashicon for star-filled (magic wand) */
                font-family: dashicons;
                margin-right: 6px;
                vertical-align: bottom;
            }
            
            #altm-generate-alt-text:hover {
                background: linear-gradient(135deg, #f08a60 0%, #ec7b4e 100%) !important;
                box-shadow: 0 4px 8px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(-1px);
                cursor: pointer;
            }
            
            #altm-generate-alt-text:focus {
                box-shadow: 0 0 0 1px #fff, 0 0 0 3px #ec7b4e !important;
                outline: none;
            }
            
            #altm-generate-alt-text:active {
                background: linear-gradient(135deg, #d66c3c 0%, #c75e2f 100%) !important;
                box-shadow: 0 1px 2px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(1px);
            }
            
            #altm-success-message {
                background-color: #fef6f3;
                border-left: 4px solid #ec7b4e;
                padding: 6px 12px;
                margin-top: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 2px;
                color: #333;
                display: none;
                font-weight: bold;
            }
            
            #altm-spinner {
                display: inline-block;
                visibility: hidden;
            }
        `)
        .appendTo('head');

    //console.log('Alt Magic: Edit attachment script loaded');

    // Find the alt text textarea
    var altTextarea = $('#attachment_alt');

    if (altTextarea.length) {
        //console.log('Alt Magic: Alt text textarea found');

        // Get attachment ID from the URL
        var urlParams = new URLSearchParams(window.location.search);
        var attachmentId = urlParams.get('post');

        if (!attachmentId) {
            console.error('Alt Magic: Could not determine attachment ID');
            return;
        }

        //console.log('Alt Magic: Working with attachment ID: ' + attachmentId);

        // Create button container
        var buttonContainer = $('<div class="altm-edit-attachment-button-container"></div>');
        var generateButton = $('<button type="button" id="altm-generate-alt-text" data-attachment-id="' + attachmentId + '">Generate Alt Text</button>');
        var spinner = $('<span id="altm-spinner" class="loader" style="display: none;"></span>');
        var successMessage = $('<div id="altm-success-message">Alt text updated successfully!</div>');

        // Append everything
        buttonContainer.append(generateButton);
        buttonContainer.append(spinner);
        buttonContainer.append(successMessage);

        // Insert after the textarea
        altTextarea.after(buttonContainer);

        // Handle button click
        $('#altm-generate-alt-text').on('click', function () {
            //console.log('Alt Magic: Generate button clicked');
            var button = $(this);
            var attachmentId = button.data('attachment-id');

            // Disable button and show spinner
            button.prop('disabled', true).text('Generating...');
            $('#altm-spinner').show();
            $('#altm-success-message').hide();

            //console.log('Alt Magic: Sending AJAX request for attachment ID: ' + attachmentId);

            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'altm_generate_alt_text_ajax',
                    attachment_id: attachmentId,
                    nonce: altm_data.generate_alt_text_nonce,
                    source: 'image_details_page'
                },
                success: function (response) {
                    //console.log('Alt Magic: Received AJAX response', response);

                    // Re-enable button and hide spinner
                    button.prop('disabled', false).text('Generate Alt Text');
                    $('#altm-spinner').hide();

                    // Check for authentication errors first
                    if (response.success === false && response.status_code === 403) {
                        button.prop('disabled', false).text('Generate Alt Text');
                        $('#altm-spinner').hide();
                        showAuthErrorModal(response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                        return;
                    }
                    
                    if (response.success) {
                        //console.log('Alt Magic: Successfully generated alt text');
                        // Update the alt text field directly
                        var newAltText = response.data.alt_text;
                        $('#attachment_alt').val(newAltText);

                        // Check if we should update other fields
                        var moreOptions = response.data.more_options;

                        // Check and update title if option is set
                        // if (moreOptions && moreOptions.alt_magic_use_for_title == '1') {
                        //     $('#attachment_title').val(newAltText);
                        // }

                        // Check and update caption if option is set
                        if (moreOptions && moreOptions.alt_magic_use_for_caption == '1') {
                            $('#attachment_caption').val(newAltText);
                        }

                        // Check and update description if option is set
                        if (moreOptions && moreOptions.alt_magic_use_for_description == '1') {
                            $('#attachment_content').val(newAltText);
                        }

                        // Show success message
                        $('#altm-success-message').fadeIn();
                        setTimeout(function () {
                            $('#altm-success-message').fadeOut();
                        }, 3000);
                    } else {
                        console.error('Alt Magic: Failed to generate alt text', response);
                        alert('Failed to generate alt text: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function (xhr, textStatus, errorThrown) {
                    console.error('Alt Magic: AJAX error', { xhr: xhr, status: textStatus, error: errorThrown });

                    // Re-enable button and hide spinner
                    button.prop('disabled', false).text('Generate Alt Text');
                    $('#altm-spinner').hide();

                    // Show error
                    alert('An error occurred: ' + textStatus);
                }
            });
        });
    } else {
        console.error('Alt Magic: Alt text textarea (#attachment_alt) not found');
    }
}); 