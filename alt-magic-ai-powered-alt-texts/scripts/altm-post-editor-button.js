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
            '<a href="' + (typeof altm_post_data !== 'undefined' && altm_post_data.account_settings_url ? altm_post_data.account_settings_url : '/wp-admin/admin.php?page=alt-magic') + '" class="button button-primary">Go to Account Settings</a>' +
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
            
            .altm-post-generate-button {
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
                float: right;
                margin-bottom: 10px !important;

            }
            
            .altm-post-generate-button:before {
                content: '\\f155'; /* WordPress dashicon for star-filled (magic wand) */
                font-family: dashicons;
                margin-right: 6px;
                vertical-align: bottom;
            }
            
            .altm-post-generate-button:hover {
                background: linear-gradient(135deg, #f08a60 0%, #ec7b4e 100%) !important;
                box-shadow: 0 4px 8px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(-1px);
            }
            
            .altm-post-generate-button:focus {
                box-shadow: 0 0 0 1px #fff, 0 0 0 3px #ec7b4e !important;
                outline: none;
            }
            
            .altm-post-generate-button:active {
                background: linear-gradient(135deg, #d66c3c 0%, #c75e2f 100%) !important;
                box-shadow: 0 1px 2px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(1px);
            }
            
            .altm-success-message {
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

    //console.log('Alt Magic: Post editor script loaded');

    // Create a MutationObserver to watch for changes in the DOM
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            // Check if the element with data-setting="alt" exists in media popup
            var altTextElement = $('[data-setting="alt"]');

            if (altTextElement.length && !$('#altm-media-popup-button').length) {
                //console.log('Alt Magic: Found alt text field in media popup');

                // Add a custom button below the element with data-setting="alt"
                var $button = $('<button id="altm-media-popup-button" class="button button-primary altm-post-generate-button">Generate Alt Text</button>');
                altTextElement.after($button);

                // Add spinner and message
                var $spinner = $('<span id="altm-spinner" class="loader" aria-hidden="true" style="display: none;"></span>');
                $button.after($spinner);

                var $message = $('<div id="altm-message" class="altm-success-message" style="display: none; margin-top: 12px; float: right; margin-right: 14px;" aria-live="polite">Alt text updated successfully</div>');
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

                // Get attachment ID from various sources
                let attachmentId = null;

                // Try URL parameter first
                attachmentId = getQueryParam('item');

                // If not found, try getting from media frame
                if (!attachmentId && wp.media && wp.media.frame) {
                    const selection = wp.media.frame.state().get('selection');
                    if (selection && selection.length) {
                        attachmentId = selection.first().get('id');
                    }
                }

                // Last resort - look for data attributes
                if (!attachmentId) {
                    const attachmentDetails = altTextElement.closest('.attachment-details');
                    if (attachmentDetails.length && attachmentDetails.data('id')) {
                        attachmentId = attachmentDetails.data('id');
                    }
                }

                //console.log('Alt Magic: Attachment ID found:', attachmentId);

                // Add click event for the custom button
                $('#altm-media-popup-button').on('click', function () {
                    if (!attachmentId) {
                        // One last attempt to get ID at click time
                        if (wp.media && wp.media.frame) {
                            const selection = wp.media.frame.state().get('selection');
                            if (selection && selection.length) {
                                attachmentId = selection.first().get('id');
                            }
                        }

                        if (!attachmentId) {
                            alert('No attachment selected.');
                            return;
                        }
                    }

                    //console.log('Alt Magic: Generating alt text for attachment ID:', attachmentId);

                    $spinner.show();
                    $message.hide();

                    // Disable button and update text
                    $(this).prop('disabled', true).text('Generating...');

                    // Make AJAX request - directly matching the approach from media-popup-button.js
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'altm_generate_alt_text_ajax',
                            attachment_id: attachmentId,
                            nonce: altm_post_data.generate_alt_text_nonce,
                            source: 'post_editor_popup'
                        },
                        success: function (response) {
                            //console.log('Alt Magic: Received AJAX response', response);

                            // Hide spinner
                            $spinner.hide();

                            // Re-enable button, revert text, and restore opacity
                            $('#altm-media-popup-button').prop('disabled', false)
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

                                //console.log('Alt Magic: Page type:', page_type);

                                function updateField(fieldType, altText) {
                                    const fieldId = page_type === 'media_library'
                                        ? `attachment-details-two-column-${fieldType}`
                                        : `attachment-details-${fieldType}`;

                                    const field = document.getElementById(fieldId);
                                    if (field) {
                                        field.value = altText;
                                        $(field).trigger('change');
                                    } else {
                                        // If field ID not found, try data-setting approach
                                        const settingField = $(`[data-setting="${fieldType}"]`);
                                        if (settingField.length) {
                                            settingField.val(altText).trigger('change');
                                        }
                                    }
                                }

                                // Update alt text
                                updateField('alt-text', altText);
                                altTextElement.val(altText).trigger('change');

                                // Check and update title if option is set
                                // if (moreOptions && moreOptions.alt_magic_use_for_title == '1') {
                                //     updateField('title', altText);
                                // }

                                // Check and update caption if option is set
                                if (moreOptions && moreOptions.alt_magic_use_for_caption == '1') {
                                    updateField('caption', altText);
                                }

                                // Check and update description if option is set
                                if (moreOptions && moreOptions.alt_magic_use_for_description == '1') {
                                    updateField('description', altText);
                                }

                                // Show success message
                                $message.fadeIn();
                                // Hide the message after 3 seconds
                                setTimeout(function () {
                                    $message.fadeOut();
                                }, 3000);
                            } else {
                                console.error('Error:', response.data || 'Unknown error');
                                alert('Failed to generate alt text. Please try again or contact chat support on app.altmagic.pro');
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            // Hide spinner
                            $spinner.hide();

                            // Re-enable button, revert text, and restore opacity
                            $('#altm-media-popup-button').prop('disabled', false)
                                .text('Generate Alt Text')
                                .css('opacity', '1');

                            console.error('AJAX Error:', textStatus, errorThrown);
                            alert('An error occurred while generating alt text. Please try again. Error: ' + textStatus);
                        }
                    });
                });

                // Update attachmentId if selection changes
                if (wp.media && wp.media.frame) {
                    wp.media.frame.on('selection:toggle', function () {
                        const selection = wp.media.frame.state().get('selection');
                        if (selection && selection.length) {
                            attachmentId = selection.first().get('id');
                            //console.log('Alt Magic: Updated attachment ID after selection change:', attachmentId);
                        }
                    });
                }
            }

            // Also check for Gutenberg alt text fields and other standard fields
            // Only if we haven't already processed this area
            scanForAltTextFields();
        });
    });

    // Initial scan for alt text fields when the page loads
    function scanForAltTextFields() {
        // Check for Gutenberg block editor alt text fields
        $('.block-editor-media-placeholder input[placeholder="Alt text (optional)"]').each(function () {
            addButtonToAltField($(this));
        });

        // Check for alt text fields in the image block settings
        $('.components-panel__body .components-form-token-field__input[placeholder="Alt text (optional)"]').each(function () {
            addButtonToAltField($(this));
        });

        // Standard alt text fields
        $('input[name="alt"], textarea[name="alt"]').each(function () {
            addButtonToAltField($(this));
        });
    }

    // Function to add a button to a Gutenberg alt text field
    function addButtonToAltField(altField) {
        // Skip if this is a media popup field (we handle those separately)
        if (altField.attr('data-setting') === 'alt') {
            return;
        }

        const container = altField.closest('.components-base-control') || altField.parent();

        // Create a unique ID for this button
        const buttonId = 'altm-btn-' + Math.random().toString(36).substring(2, 9);

        // Check if we've already added a button
        if (container.find('.altm-post-generate-button').length || altField.next('.altm-post-generate-button').length) {
            return;
        }

        // Create button elements
        const button = $('<button type="button" id="' + buttonId + '" class="button button-primary altm-post-generate-button">Generate Alt Text</button>');
        const spinner = $('<span class="loader" aria-hidden="true" style="display: none;"></span>');
        const message = $('<div class="altm-success-message" style="display: none; margin-top: 12px; float: right; margin-right: 14px;" aria-live="polite">Alt text updated successfully!</div>');

        // Try to find the image ID
        let imageId = null;

        // Look for closest image block with an image
        const imgContainer = altField.closest('.wp-block-image, .block-editor-media-placeholder').find('img');
        if (imgContainer.length) {
            const imgSrc = imgContainer.attr('src');
            // Extract ID from URL/class if possible
            const idMatch = imgSrc && imgSrc.match(/wp-image-(\d+)/);
            if (idMatch && idMatch[1]) {
                imageId = idMatch[1];
            } else {
                // Try to get from class
                const imgClasses = imgContainer.attr('class');
                const classMatch = imgClasses && imgClasses.match(/wp-image-(\d+)/);
                if (classMatch && classMatch[1]) {
                    imageId = classMatch[1];
                }
            }
        }

        // Only add button if we found an ID
        if (imageId) {
            //console.log('Alt Magic: Found image ID:', imageId);
            button.attr('data-image-id', imageId);

            // Add button click handler
            button.on('click', function () {
                const btn = $(this);
                const imgId = btn.attr('data-image-id');
                const spnr = btn.siblings('.loader');
                const msg = btn.siblings('.altm-success-message');
                const altInput = altField;

                // Disable button and show spinner
                btn.prop('disabled', true).text('Generating...');
                spnr.show();
                msg.hide();

                //console.log('Alt Magic: Generating alt text for image ID:', imgId);

                // Make AJAX request - same as media popup
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'altm_generate_alt_text_ajax',
                        attachment_id: imgId,
                        nonce: altm_post_data.generate_alt_text_nonce,
                        source: 'post_editor_block'
                    },
                    success: function (response) {
                        //console.log('Alt Magic: Received AJAX response', response);

                        // Re-enable button and hide spinner
                        btn.prop('disabled', false).text('Generate Alt Text');
                        spnr.hide();

                        // Check for authentication errors first
                        if (response.success === false && response.status_code === 403) {
                            showAuthErrorModal(response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                            return;
                        }

                        if (response.success) {
                            const newAltText = response.data.alt_text;

                            // Try multiple update strategies
                            // 1. Simple value setting
                            altInput.val(newAltText).trigger('change').trigger('input');

                            // 2. Direct DOM modification
                            if (altInput[0]) {
                                altInput[0].value = newAltText;

                                // Trigger React-friendly events
                                ['input', 'change'].forEach(function (eventType) {
                                    const event = new Event(eventType, { bubbles: true });
                                    altInput[0].dispatchEvent(event);
                                });
                            }

                            // 3. Show success message
                            msg.fadeIn();
                            setTimeout(function () {
                                msg.fadeOut();
                            }, 3000);
                        } else {
                            console.error('Alt Magic: Failed to generate alt text', response);
                            alert('Failed to generate alt text: ' + (response.data ? response.data.message : 'Unknown error'));
                        }
                    },
                    error: function (xhr, textStatus, errorThrown) {
                        console.error('Alt Magic: AJAX error', { xhr: xhr, status: textStatus, error: errorThrown });

                        // Re-enable button and hide spinner
                        btn.prop('disabled', false).text('Generate Alt Text');
                        spnr.hide();

                        // Show error
                        alert('An error occurred: ' + textStatus);
                    }
                });
            });

            // Add elements to the DOM
            altField.after(button);
            button.after(spinner);
            button.after(message);
        }
    }

    // Start observing the document
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Initial scan to find any existing fields
    scanForAltTextFields();

    // Handle media button clicks to catch the popup
    $('.wp-media-buttons').on('click', function () {
        //console.log('Alt Magic: Media button clicked');
        // Set timed checks to look for the popup after it opens
        setTimeout(function () { checkMediaPopup(); }, 500);
        setTimeout(function () { checkMediaPopup(); }, 1000);
        setTimeout(function () { checkMediaPopup(); }, 2000);
    });

    // Hook into WordPress media events if available
    if (typeof wp !== 'undefined' && wp.media && wp.media.events) {
        // This event fires when the media modal is opened
        wp.media.events.on('editor:frame-create', function () {
            //console.log('Alt Magic: Media frame created');
            setTimeout(function () { checkMediaPopup(); }, 700);
        });
    }

    // Simple function to check for media popup
    function checkMediaPopup() {
        // Look specifically for alt text field in media popup
        const altTextField = $('[data-setting="alt"]');

        if (altTextField.length && !$('#altm-media-popup-button').length) {
            //console.log('Alt Magic: Found alt text field in media popup');
            // Trigger the observer by adding a small temporary element
            const tempDiv = $('<div id="altm-trigger-element"></div>');
            altTextField.after(tempDiv);
            setTimeout(function () { tempDiv.remove(); }, 50);
        }
    }
}); 