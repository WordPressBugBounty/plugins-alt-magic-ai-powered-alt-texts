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
            '<a href="' + (typeof altm_media_data !== 'undefined' && altm_media_data.account_settings_url ? altm_media_data.account_settings_url : '/wp-admin/admin.php?page=alt-magic') + '" class="button button-primary">Go to Account Settings</a>' +
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
    
    // Add CSS for loader and buttons
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
                margin-left: 5px;
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
            
            .column-altm_generate {
                width: 150px;
            }
            
            .altm-media-list-generate {
                background: linear-gradient(135deg, #ec7b4e 0%, #e56a3a 100%) !important;
                color: white !important;
                text-shadow: 0 -1px 1px rgba(0,0,0,0.1) !important;
                border: none !important;
                position: relative;
                overflow: hidden;
                font-weight: 500 !important;
                transition: all 0.3s ease;
                box-shadow: 0 2px 5px rgba(236, 123, 78, 0.3) !important;
                padding: 4px 8px !important;
                border-radius: 4px !important;
                white-space: nowrap;
            }
            
            .altm-media-list-generate:before {
                content: '\\f155'; /* WordPress dashicon for star-filled (magic wand) */
                font-family: dashicons;
                margin-right: 6px;
                vertical-align: bottom;
            }
            
            .altm-media-list-generate:hover {
                background: linear-gradient(135deg, #f08a60 0%, #ec7b4e 100%) !important;
                box-shadow: 0 4px 8px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(-1px);
            }
            
            .altm-media-list-generate:focus {
                box-shadow: 0 0 0 1px #fff, 0 0 0 3px #ec7b4e !important;
                outline: none;
            }
            
            .altm-media-list-generate:active {
                background: linear-gradient(135deg, #d66c3c 0%, #c75e2f 100%) !important;
                box-shadow: 0 1px 2px rgba(236, 123, 78, 0.4) !important;
                transform: translateY(1px);
            }
            
            .altm-update-flash {
                animation: flashUpdate 1.5s;
            }
            
            @keyframes flashUpdate {
                0% { background-color: rgba(70, 180, 80, 0.2); }
                100% { background-color: transparent; }
            }
        `)
        .appendTo('head');

    //console.log('Alt Magic: Media list script loaded');

    // Helper function to properly format alt text for display
    function formatAltTextForDisplay(altText) {
        if (!altText) return '<span style="color:#999;font-style:italic;">No alt text</span>';

        if (altText.length > 50) {
            return '<div class="altm-alt-text-truncated">' +
                $('<div>').text(altText.substring(0, 50) + '...').html() +
                '<div class="altm-alt-text-full">' + $('<div>').text(altText).html() + '</div>' +
                '</div>';
        } else {
            return $('<div>').text(altText).html();
        }
    }

    // Handle button click for media list generation buttons
    $('.altm-media-list-generate').on('click', function () {
        var button = $(this);
        var attachmentId = button.data('id');
        var spinner = button.next('.altm-media-list-spinner');

        // Disable button and show spinner
        button.prop('disabled', true).text('Generating...');
        //spinner.show();

        //console.log('Alt Magic: Generating alt text for attachment ID: ' + attachmentId);

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'altm_generate_alt_text_ajax',
                attachment_id: attachmentId,
                nonce: altm_media_data.generate_alt_text_nonce,
                source: 'media_library_list'
            },
            success: function (response) {
                //console.log('Alt Magic: Received AJAX response', response);

                // Re-enable button and hide spinner
                button.prop('disabled', false);
                spinner.hide();

                // Check for authentication errors first
                if (response.success === false && response.status_code === 403) {
                    showAuthErrorModal(response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                    return;
                }

                if (response.success) {
                    // Update the alt text cell in the media library table
                    if (response.data && response.data.alt_text) {
                        // Find the row containing this button
                        var row = button.closest('tr');

                        // Find the alt text cell (should be the column with class column-altm_alt_text)
                        var altTextCell = row.find('td.column-altm_alt_text');

                        if (altTextCell.length) {
                            // Update the content with the new alt text
                            var formattedAltText = formatAltTextForDisplay(response.data.alt_text);
                            altTextCell.html(formattedAltText);

                            // Add a flash effect to highlight the update
                            altTextCell.addClass('altm-update-flash');
                            setTimeout(function () {
                                altTextCell.removeClass('altm-update-flash');
                            }, 1500);
                        } else {
                            // The alt text column might be hidden via Screen Options
                            //console.log('Alt Magic: Alt text column not found - it might be hidden in Screen Options');

                            // Store the alt text as a data attribute on the row for potential future use
                            row.attr('data-alt-text', response.data.alt_text);

                            // If we're in list mode, we might be able to update the title column with a small indicator
                            var titleCell = row.find('.column-title');
                            if (titleCell.length) {
                                // Add a small indicator that alt text was updated
                                var altTextIndicator = $('<span class="altm-update-indicator" style="margin-left: 5px; color: #46b450;" title="Alt text was generated: ' + response.data.alt_text.replace(/"/g, '&quot;') + '">(Alt ✓)</span>');

                                // Remove any existing indicators
                                titleCell.find('.altm-update-indicator').remove();

                                // Find the .row-title element within the titleCell and append after it
                                var rowTitle = titleCell.find('.row-title');
                                if (rowTitle.length) {
                                    rowTitle.after(altTextIndicator);
                                } else {
                                    // If no .row-title, just append to the cell
                                    titleCell.append(altTextIndicator);
                                }
                            }
                        }
                    }

                    // Show temporary success state
                    button.text('✓ Done!');
                    setTimeout(function () {
                        button.text('Generate Alt Text');
                    }, 3000);
                } else {
                    // Show error
                    alert('Failed to generate alt text: ' + (response.data ? response.data.message : 'Unknown error'));
                    button.text('Generate Alt Text');
                }
            },
            error: function (xhr, textStatus, errorThrown) {
                console.error('Alt Magic: AJAX error', { xhr: xhr, status: textStatus, error: errorThrown });

                // Re-enable button and hide spinner
                button.prop('disabled', false);
                spinner.hide();

                // Show error
                alert('An error occurred: ' + textStatus);
                button.text('Generate Alt Text');
            }
        });
    });
});