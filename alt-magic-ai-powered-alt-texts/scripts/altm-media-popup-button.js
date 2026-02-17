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
            '<a href="' + (typeof altmMediaPopup !== 'undefined' && altmMediaPopup.account_settings_url ? altmMediaPopup.account_settings_url : '/wp-admin/admin.php?page=alt-magic') + '" class="button button-primary">Go to Account Settings</a>' +
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
                float: right;
                margin-top: 13px;
                margin-right: 10px;
            }

            @keyframes rotation {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }

            .attachment-details{
                overflow-x: clip;
            }
            
            .generate-alt-text-button {
                background: linear-gradient(135deg, #ec7b4e 0%, #e56a3a 100%) !important;
                color: white !important;
                text-shadow: 0 -1px 1px rgba(0,0,0,0.1) !important;
                border: none !important;
                position: relative;
                overflow: hidden;
                font-weight: 500 !important;
                transition: all 0.3s ease;
                box-shadow: 0 2px 5px rgba(236, 123, 78, 0.3) !important;
                padding: 6px 15px !important;
                border-radius: 4px !important;
                white-space: nowrap;
            }
            
            .generate-alt-text-button:before {
                content: '\\f155'; /* WordPress dashicon for star-filled (magic wand) */
                font-family: dashicons;
                margin-right: 6px;
                vertical-align: bottom;
            }
            
            .generate-alt-text-button:hover {
                background: linear-gradient(135deg, #f08a60 0%, #ec7b4e 100%) !important;
                box-shadow: 0 4px 8px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(-1px);
            }
            
            .generate-alt-text-button:focus {
                box-shadow: 0 0 0 1px #fff, 0 0 0 3px #ec7b4e !important;
                outline: none;
            }
            
            .generate-alt-text-button:active {
                background: linear-gradient(135deg, #d66c3c 0%, #c75e2f 100%) !important;
                box-shadow: 0 1px 2px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(1px);
            }
            
            #altMessage {
                background-color:rgb(245, 254, 243);
                border-left: 4px solid rgb(78, 236, 89);
                padding: 6px 12px;
                margin-top: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 2px;
                color: #333;
                font-weight: bold;
            }
                
        `)
        .appendTo('head');

    // Create a MutationObserver to watch for changes in the DOM
    //console.log('ready');
    var observer = new MutationObserver(function (mutations) {
        //console.log('mutations: ', mutations);
        //console.log('finding alt text element');
        mutations.forEach(function (mutation) {
            // Check if the element with data-setting="alt" exists
            //console.log('for mutation: ', mutation);
            var altTextElement = $('[data-setting="alt"]');


            if (altTextElement.length && !$('#customAltButton').length) {

                //console.log('Alt text element found, adding button');

                // Add a custom button below the element with data-setting="alt"
                var $button = $('<button id="customAltButton" class="button button-primary generate-alt-text-button">Generate Alt Text</button>');
                altTextElement.after($button);

                // Modify the spinner creation to use the new loader class
                var $spinner = $('<span id="altSpinner" class="loader" aria-hidden="true" style="display: none;"></span>');
                $button.after($spinner);

                // Add message element (hidden by default)
                var $message = $('<div id="altMessage" style="display: none; margin-top: 12px; float: right; margin-right: 14px;" aria-live="polite">Alt text updated successfully</div>');
                $button.after($message);

                // Remove the element with id="alt-text-description" if it exists
                $('#alt-text-description').remove();

                // Function to extract query parameters from the URL
                function getQueryParam(param) {
                    param = param.replace(/[[]/, "\\[").replace(/[\]]/, "\\]");
                    let regex = new RegExp("[\\?&]" + param + "=([^&#]*)");
                    let results = regex.exec(window.location.search);
                    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));

                }

                // Variable to store the attachment ID
                let attachmentId = getQueryParam('item');
                //console.log('Attachment ID from URL:', attachmentId);

                // Add click event for the custom button
                $('#customAltButton').on('click', function () {
                    if (!attachmentId) {
                        alert('No attachment selected.');
                        return;
                    }
                    //console.log('Final Attachment ID:', attachmentId);

                    $spinner.show();
                    $message.hide();

                    // Disable button and update text
                    $(this).prop('disabled', true).text('Generating...');

                    // Use the attachmentId for further processing
                    generateAltText({ id: attachmentId });
                });

                // Fallback: Fetch attachment ID from clicked element if not found in URL
                jQuery(document).on("click", "ul.attachments li.attachment", function () {
                    let e = jQuery(this);
                    if (e.attr("data-id")) {
                        attachmentId = parseInt(e.attr("data-id"), 10);
                        //console.log('Attachment ID from clicked element:', attachmentId);

                        // Optionally, you can trigger any necessary actions here
                    }
                });
            }
        });
    });

    // Start observing the media modal wrapper for changes
    // var target = document.querySelector('.media-modal');
    // if (target) {
    //     observer.observe(target, { childList: true, subtree: true });
    // }

    observer.observe(document.body, { childList: true, subtree: true });

    function generateAltText(attachment) {
        var attachmentId = attachment.id;

        // Disable button, change text, and reduce opacity
        $('#customAltButton').prop('disabled', true)
            .text('Generating...');

        $.ajax({
            url: altmMediaPopup.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'altm_generate_alt_text_ajax',
                attachment_id: attachmentId,
                nonce: altmMediaPopup.generate_alt_text_nonce,
                source: 'image_details_popup'
            },
            success: function (response) {
                // Hide spinner
                $('#altSpinner').hide();
                // Re-enable button, revert text, and restore opacity
                $('#customAltButton').prop('disabled', false)
                    .text('Generate Alt Text')
                    .css('opacity', '1');

                // Check for authentication errors first
                if (response.success === false && response.status_code === 403) {
                    showAuthErrorModal(response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                    return;
                }

                if (response.success) {
                    var altText = response.data.alt_text;
                    var moreOptions = response.data.more_options;
                    var page_type = 'media_library';

                    if (document.getElementById('attachment-details-alt-text')) {
                        page_type = 'product_page';
                    }

                    //console.log('page_type: ', page_type);

                    function updateField(fieldType, altText) {
                        const fieldId = page_type === 'media_library'
                            ? `attachment-details-two-column-${fieldType}`
                            : `attachment-details-${fieldType}`;
                        document.getElementById(fieldId).value = altText;
                    }

                    // Update alt text
                    updateField('alt-text', altText);

                    // Check and update title if option is set
                    // if (moreOptions.alt_magic_use_for_title == '1') {
                    //     updateField('title', altText);
                    // }

                    // Check and update caption if option is set
                    if (moreOptions.alt_magic_use_for_caption == '1') {
                        updateField('caption', altText);
                    }

                    // Check and update description if option is set
                    if (moreOptions.alt_magic_use_for_description == '1') {
                        updateField('description', altText);
                    }

                    // Show success message
                    $('#altMessage').fadeIn();
                    // Hide the message after 3 seconds
                    setTimeout(function () {
                        $('#altMessage').fadeOut();
                    }, 3000);
                } else {
                    console.error('Error:', response.data || 'Unknown error');
                    alert('Failed to generate alt text. Please try again or contact chat support on app.altmagic.pro');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Hide spinner
                $('#altSpinner').hide();
                // Re-enable button, revert text, and restore opacity
                $('#customAltButton').prop('disabled', false)
                    .text('Generate Alt Text')
                    .css('opacity', '1');

                console.error('AJAX Error:', textStatus, errorThrown);
                alert('An error occurred while generating alt text. Please try again. Error: ' + textStatus);
            }
        });
    }
});
