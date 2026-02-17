<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function altm_render_processed_images_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $user_email = get_option('alt_magic_user_id');
    $website_url = get_site_url();
    
    // Check if the site is running on localhost
    $is_localhost = strpos(get_site_url(), 'localhost') !== false;
    
    // Get user email for purchase link
    $purchase_url = !empty($user_email) 
        ? 'https://altmagic.pro/?wp_email=' . urlencode($user_email) . '#pricing'
        : 'https://altmagic.pro/#pricing';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Credits Display Section -->
        <div class="account-info-container" style="margin: 10px 0; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
            <p id="account-info-text" style="font-size: 14px; color: #333; margin: 0;"><?php 
            $is_account_active = get_option('alt_magic_account_active');
            echo wp_kses_post($is_account_active ? 
            'You have <span class="credits-available-text">... credits</span> remaining in your account. <a target="_blank" href="' . esc_url($purchase_url) . '">Purchase credits in bulk.</a>' 
            : 'Account is not activated. Please go to <a href="' . esc_url(admin_url('admin.php?page=alt-magic')) . '">Account Settings</a> to activate your account.'); ?></p>
        </div>
        
        <script type="text/javascript">
            // Pass the localhost status to JavaScript
            var isLocalhost = <?php echo esc_js($is_localhost ? 'true' : 'false'); ?>;
            var altmProcessedImages = {
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                fetchCreditsNonce: '<?php echo esc_js(wp_create_nonce('altm_fetch_user_credits_nonce')); ?>',
                accountSettingsUrl: '<?php echo esc_js(admin_url('admin.php?page=alt-magic')); ?>'
            };
        </script>
        
        <div class="altm-processed-images-container">
            <div class="altm-loading" style="display: none;">
                <span class="spinner is-active" style="float: none; margin: 0 auto 10px; display: block;"></span>
                <p>Loading processed images...</p>
            </div>
            
            <div class="altm-summary-card" style="display: none;">
                <div class="altm-card-content">
                    <div class="altm-card-icon">
                        <span class="dashicons dashicons-images-alt2"></span>
                    </div>
                    <div class="altm-card-info">
                        <h2>Total Processed Images</h2>
                        <div class="altm-card-stats">
                            <span class="altm-total-count">0</span> images processed for <span class="altm-website-url"><?php echo esc_html($website_url); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="altm-processed-images-table-container" style="display: none;">
                <div class="altm-table-controls">
                    <div></div> <!-- Empty div for flex spacing -->
                    <div class="altm-search-control">
                        <label for="altm-search-input" class="screen-reader-text">Search Images</label>
                        <input type="text" id="altm-search-input" placeholder="Search by ID or URL..." class="regular-text">
                        <button type="button" id="altm-search-clear" class="button" style="display: none;">Clear</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped altm-processed-images-table">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th width="160">Image</th>
                            <th width="300">Alt Text</th>
                            <th width="200">Image Name</th>
                            <th width="150">Processed On</th>
                        </tr>
                    </thead>
                    <tbody id="altm-processed-images-list">
                        <!-- Images will be dynamically loaded here -->
                    </tbody>
                </table>
                
                <div class="altm-pagination-container">
                    <div class="altm-pagination">
                        <button type="button" class="button altm-pagination-prev" disabled>&laquo; Previous</button>
                        <div class="altm-pagination-numbers"></div>
                        <button type="button" class="button altm-pagination-next">Next &raquo;</button>
                    </div>
                    
                    <div class="altm-bottom-controls">
                        <div class="altm-per-page-control">
                            <label for="altm-per-page-bottom">Images per page:</label>
                            <select id="altm-per-page-bottom">
                                <option value="10">10</option>
                                <option value="15" selected>15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="altm-no-images-message" style="display: none;">
                <div class="altm-empty-state">
                    <div class="altm-empty-state-icon">
                        <span class="dashicons dashicons-images-alt2"></span>
                    </div>
                    <h2>No Processed Images Found</h2>
                    <p>You haven't processed any images with Alt Magic yet.</p>
                    <div class="altm-empty-state-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=alt-magic-bulk-generation')); ?>" class="button button-primary">Generate Alt Texts Now</a>
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="button">Go to Media Library</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Pagination variables
        var allImages = [];
        var filteredImages = [];
        var currentPage = 1;
        var imagesPerPage = 15;
        var totalPages = 0;
        var searchTimeout = null;
        
        // Helper function to decode HTML entities and fix escaped quotes
        function decodeText(text) {
            if (!text) return '';
            
            // First decode HTML entities
            var textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            var decoded = textarea.value;
            
            // First convert JSON-style escaped quotes to real quotes
            try {
                // Try to use JSON.parse to properly interpret escape sequences
                // Add quotes around the string and handle escaped quotes
                var jsonString = '"' + decoded.replace(/"/g, '\\"') + '"';
                decoded = JSON.parse(jsonString);
            } catch (e) {
                //console.log('JSON parse failed, using regex replacement instead', e);
                // Fallback to regex if JSON parsing fails
                decoded = decoded.replace(/\\+"/g, '"').replace(/\\+'/g, "'");
            }
            
            return decoded;
        }
        
        // Helper function to escape text for HTML attributes
        function escapeAttribute(text) {
            if (!text) return '';
            return text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        
        // Debug search input existence
        ////console.log('Search input exists:', $('#altm-search-input').length > 0);
        
        // Try to get saved preferences
        if (localStorage.getItem('altm_images_per_page')) {
            imagesPerPage = parseInt(localStorage.getItem('altm_images_per_page'));
            $('#altm-per-page-bottom').val(imagesPerPage);
        }
        
        // Show loading indicator
        $('.altm-loading').show();
        
        // Credits functionality
        function fetchAndDisplayCredits() {
            $('.credits-available-text').text('... credits');
            $.post(altmProcessedImages.ajaxUrl, {
                action: 'altm_fetch_user_credits',
                nonce: altmProcessedImages.fetchCreditsNonce
            }, function (response) {
                // Check for authentication errors
                if (response.success === false) {
                    // Use the message from backend, or default message
                    var errorMessage = response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.';
                    displayCreditsError(errorMessage);
                    return;
                }
                
                if (response.credits_available || response.credits_available == 0) {
                    var credits = parseInt(response.credits_available);
                    updateCreditsDisplay(credits);
                    clearCreditsError();
                } else {
                    displayCreditsError('Unable to fetch credits. Please try again.');
                }
            }).fail(function (xhr, status, error) {
                console.error('Credits fetch error:', error);
                displayCreditsError('Network error while fetching credits. Please check your connection.');
            });
        }
        
        function updateCreditsDisplay(credits) {
            if (credits !== null) {
                $('.credits-available-text').text(credits + ' credits');
                
                $('.credits-available-text').removeClass('credits-high credits-medium credits-low');
                
                if (credits == 0) {
                    $('.account-info-container').css({
                        'background-color': '#ffc4c0',
                        'color': '#a73931'
                    });
                    $('.credits-available-text').addClass('credits-low');
                } else if (credits < 500) {
                    $('.account-info-container').css({
                        'background-color': '#f8f9fa',
                        'color': '#333'
                    });
                    $('.credits-available-text').addClass('credits-medium');
                } else {
                    $('.account-info-container').css({
                        'background-color': '#f8f9fa',
                        'color': '#333'
                    });
                    $('.credits-available-text').addClass('credits-high');
                }
            }
        }
        
        function displayCreditsError(message) {
            var accountInfoContainer = $('.account-info-container');
            var accountInfoText = $('#account-info-text');
            
            accountInfoContainer.css({
                'background-color': '#fdeaea',
                'border-color': '#b70000'
            });
            
            accountInfoText.css({
                'color': '#b70000'
            });
            
            var accountSettingsUrl = altmProcessedImages.accountSettingsUrl || '/wp-admin/admin.php?page=alt-magic';
            accountInfoText.html(
                '<span style="font-weight: bold;">⚠️ </span>' +
                message +
                ' <a href="' + accountSettingsUrl + '">Go to Account Settings</a>'
            );
        }
        
        function clearCreditsError() {
            var accountInfoContainer = $('.account-info-container');
            accountInfoContainer.css({
                'background-color': '#f8f9fa',
                'border-color': '#ddd',
                'color': '#333'
            });
        }
        
        // Initialize credits display on page load
        fetchAndDisplayCredits();

        // Fetch processed images data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 30000, // 30 second timeout
            data: {
                action: 'altm_get_processed_images_data',
                nonce: '<?php echo esc_attr(wp_create_nonce('altm_get_processed_images_nonce')); ?>'
            },
            success: function(response) {
                //console.log('Fetched processed images data:', response);
                $('.altm-loading').hide();
                
                // Check for authentication errors
                if (response.success === false && response.status_code === 403) {
                    displayCreditsError('Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                    $('#altm-processed-images-table-container').hide();
                    $('#altm-no-images-message').show().find('.altm-empty-state-icon').addClass('error');
                    $('#altm-no-images-message').find('h2').text('Authentication Error');
                    $('#altm-no-images-message').find('p').text('Unable to load processed images due to authentication failure.');
                    return;
                }
                
                if (response && response.user_images_data && response.user_images_data.length > 0) {
                    // Get all images
                    var rawImages = response.user_images_data;
                    
                    // Sort images by updated_at date in descending order (newest first)
                    rawImages.sort(function(a, b) {
                        var dateA = new Date(a.updated_at || 0);
                        var dateB = new Date(b.updated_at || 0);
                        return dateB - dateA; // Descending order
                    });
                    
                    // Filter to keep only the most recent entry for each platform_image_id
                    // var seenPlatformIds = {};
                    // allImages = rawImages.filter(function(image) {
                    //     // If we haven't seen this platform_image_id yet, keep this entry
                    //     if (!seenPlatformIds[image.platform_image_id]) {
                    //         seenPlatformIds[image.platform_image_id] = true;
                    //         return true;
                    //     }
                    //     // Otherwise skip it (we already have a newer entry)
                    //     return false;
                    // });

                    allImages = rawImages;
                    
                    //console.log('Filtered images to keep only most recent entries. Original count:', rawImages.length, 'Filtered count:', allImages.length);
                    
                    filteredImages = [...allImages]; // Start with all images
                    
                    // Update summary card
                    $('.altm-total-count').text(allImages.length);
                    $('.altm-summary-card').show();
                    
                    // Initialize pagination
                    totalPages = Math.ceil(filteredImages.length / imagesPerPage);
                    updatePaginationUI();
                    renderCurrentPage();
                    
                    // Show the table only after data is loaded
                    $('#altm-processed-images-table-container').show();
                    $('#altm-no-images-message').hide();
                    
                    // Debug search input after content is shown
                    //console.log('Search input after loading:', $('#altm-search-input').length > 0);
                    
                    // Ensure search is ready
                    setTimeout(function() {
                        //console.log('Search input in timeout:', $('#altm-search-input').length > 0);
                        // Manually trigger a focus/blur to ensure the search is ready
                        $('#altm-search-input').focus().blur();
                    }, 500);
                } else {
                    // Show no images message
                    $('#altm-processed-images-table-container').hide();
                    $('#altm-no-images-message').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch processed images:', { xhr, status, error });
                $('.altm-loading').hide();
                $('#altm-processed-images-table-container').hide();
                $('#altm-no-images-message').show().find('.altm-empty-state-icon').addClass('error');
                $('#altm-no-images-message').find('h2').text('Error Loading Images');
                $('#altm-no-images-message').find('p').text('Error: ' + error);
                console.error('Error fetching processed images:', error);
            }
        });
        
        // Function to render current page
        function renderCurrentPage() {
            var imagesList = $('#altm-processed-images-list');
            imagesList.empty();
            
            if (filteredImages.length === 0) {
                // No matching results
                imagesList.html('<tr><td colspan="5" class="altm-no-results">No images found matching your search criteria.</td></tr>');
                return;
            }
            
            var startIndex = (currentPage - 1) * imagesPerPage;
            var endIndex = Math.min(startIndex + imagesPerPage, filteredImages.length);
            
            try {
                for (var i = startIndex; i < endIndex; i++) {
                    try {
                        var image = filteredImages[i];
                        var date = new Date(image.updated_at);
                        var formattedDate = date.toLocaleString();
                        
                        var row = $('<tr>');
                        row.append($('<td>').text(image.platform_image_id));
                        
                        // Helper function to check if string is a valid URL
                        function isValidUrl(string) {
                            if (!string) return false;
                            try {
                                var url = new URL(string);
                                return url.protocol === 'http:' || url.protocol === 'https:';
                            } catch (_) {
                                return false;
                            }
                        }
                        
                        // Handle image thumbnail
                        var imgHTML;
                        if (isLocalhost || !image.thumbnail_url || !isValidUrl(image.thumbnail_url)) {
                            // On localhost or invalid URL, get the image URL from WordPress using AJAX
                            var thumbnailId = 'altm-thumbnail-' + image.platform_image_id;
                            imgHTML = '<div id="' + thumbnailId + '" class="altm-loading-thumbnail"><span class="spinner is-active"></span></div>';
                            
                            // Store this in closure to ensure we have the correct reference
                            (function(currentPlatformId, currentThumbnailId) {
                                // Fetch the image URL using the platform_image_id
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    async: true,
                                    data: {
                                        action: 'altm_get_attachment_url',
                                        nonce: '<?php echo esc_attr(wp_create_nonce('altm_get_attachment_url_nonce')); ?>',
                                        attachment_id: currentPlatformId
                                    },
                                    success: function(response) {
                                        //console.log('Attachment URL response for ID ' + currentPlatformId + ':', response);
                                        var thumbnailElement = $('#' + currentThumbnailId);
                                        if (thumbnailElement.length) {
                                            if (response.success && response.data && response.data.url) {
                                                // Replace loading spinner with the actual image with inline error handler
                                                var imgHtml = '<img src="' + response.data.url + '" alt="Thumbnail" style="max-width: 100px; max-height: 100px;" onerror="this.onerror=null; this.outerHTML=\'<div class=\\\'altm-no-image\\\'>Not Available</div>\';">';
                                                thumbnailElement.replaceWith(imgHtml);
                                            } else {
                                                // Show placeholder if image not found
                                                thumbnailElement.replaceWith('<div class="altm-no-image">Not Available</div>');
                                            }
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Error fetching image URL for ID ' + currentPlatformId + ':', error);
                                        var thumbnailElement = $('#' + currentThumbnailId);
                                        if (thumbnailElement.length) {
                                            // Show placeholder on error
                                            thumbnailElement.replaceWith('<div class="altm-no-image">Not Available</div>');
                                        }
                                    }
                                });
                            })(image.platform_image_id, thumbnailId);
                        } else {
                            // For non-localhost with valid URL, use the thumbnail_url directly
                            imgHTML = '<img src="' + image.thumbnail_url + '" alt="Thumbnail" style="max-width: 100px; max-height: 100px;" onerror="this.onerror=null; this.outerHTML=\'<div class=\\\'altm-no-image\\\'>Not Available</div>\';">';
                        }
                        
                        row.append($('<td>').html(imgHTML));
                        
                        // Alt text (simple text display)
                        var altTextCell = $('<td>');
                        
                        // Process alt text to fix escaped quotes
                        var decodedAltText = decodeText(image.alt_text || '');
                        
                        // Display alt text as simple text, or "Not Generated" if empty
                        if (decodedAltText && decodedAltText.trim() !== '') {
                            altTextCell.html('<div class="altm-alt-text-display">' + decodedAltText + '</div>');
                        } else {
                            altTextCell.html('<div class="altm-alt-text-display altm-alt-text-not-generated">Not Generated</div>');
                        }
                        row.append(altTextCell);
                        
                        // Image Name column
                        var imageNameCell = $('<td>');
                        var imageName = image.image_new_name || 'Not Generated';
                        var imageNameClass = image.image_new_name ? 'altm-image-name-generated' : 'altm-image-name-not-generated';
                        imageNameCell.html('<span class="' + imageNameClass + '">' + imageName + '</span>');
                        row.append(imageNameCell);
                        
                        row.append($('<td>').text(formattedDate));
                        
                        imagesList.append(row);
                    } catch (e) {
                        console.error('Error processing image:', e, filteredImages[i]);
                    }
                }
            } catch (e) {
                console.error('Error rendering page:', e);
            }
        }
        
        // Function to update pagination UI
        function updatePaginationUI() {
            // Update prev/next buttons
            $('.altm-pagination-prev').prop('disabled', currentPage === 1);
            $('.altm-pagination-next').prop('disabled', currentPage === totalPages || totalPages === 0);
            
            // Generate page numbers
            var paginationNumbers = $('.altm-pagination-numbers');
            paginationNumbers.empty();
            
            if (totalPages === 0) {
                return; // No pages to display
            }
            
            // Determine which page numbers to show
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }
            
            // Add first page link if not in range
            if (startPage > 1) {
                paginationNumbers.append('<button type="button" class="button altm-page-number" data-page="1">1</button>');
                if (startPage > 2) {
                    paginationNumbers.append('<span class="altm-pagination-ellipsis">...</span>');
                }
            }
            
            // Add page numbers
            for (var i = startPage; i <= endPage; i++) {
                var pageButton = $('<button type="button" class="button altm-page-number" data-page="' + i + '">' + i + '</button>');
                if (i === currentPage) {
                    pageButton.addClass('altm-current-page');
                }
                paginationNumbers.append(pageButton);
            }
            
            // Add last page link if not in range
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationNumbers.append('<span class="altm-pagination-ellipsis">...</span>');
                }
                paginationNumbers.append('<button type="button" class="button altm-page-number" data-page="' + totalPages + '">' + totalPages + '</button>');
            }
        }
        
        // Pagination event handlers
        $(document).on('click', '.altm-pagination-prev', function() {
            if (currentPage > 1) {
                currentPage--;
                updatePaginationUI();
                renderCurrentPage();
            }
        });
        
        $(document).on('click', '.altm-pagination-next', function() {
            if (currentPage < totalPages) {
                currentPage++;
                updatePaginationUI();
                renderCurrentPage();
            }
        });
        
        $(document).on('click', '.altm-page-number', function() {
            var page = parseInt($(this).data('page'));
            if (page !== currentPage) {
                currentPage = page;
                updatePaginationUI();
                renderCurrentPage();
            }
        });
        
        // Per page change handler (bottom only now)
        $(document).on('change', '#altm-per-page-bottom', function() {
            imagesPerPage = parseInt($(this).val());
            localStorage.setItem('altm_images_per_page', imagesPerPage);
            currentPage = 1; // Reset to first page
            totalPages = Math.ceil(filteredImages.length / imagesPerPage);
            updatePaginationUI();
            renderCurrentPage();
        });
        
        // Search functionality - use event delegation
        $(document).on('input', '#altm-search-input', function() {
            var searchValue = $(this).val().toLowerCase().trim();
            //console.log('Search input value:', searchValue);
            
            // Show/hide clear button
            if (searchValue) {
                $('#altm-search-clear').show();
            } else {
                $('#altm-search-clear').hide();
            }
            
            // Debounce search to avoid excessive filtering
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                //console.log('Performing search for:', searchValue);
                
                if (searchValue === '') {
                    // Reset to all images
                    filteredImages = [...allImages];
                    //console.log('Reset to all images, count:', filteredImages.length);
                } else {
                    // Filter images based on search value
                    filteredImages = allImages.filter(function(image) {
                        // Convert everything to string and toLowerCase for consistent comparison
                        var platformId = String(image.platform_image_id || '').toLowerCase();
                        var imageId = String(image.image_id || '').toLowerCase();
                        var thumbnailUrl = String(image.thumbnail_url || '').toLowerCase();
                        var imageUrl = String(image.image_url || '').toLowerCase();
                        var altText = String(image.alt_text || '').toLowerCase();
                        
                        // Search by ID (both platform_image_id and image_id)
                        if (platformId.includes(searchValue)) return true;
                        if (imageId.includes(searchValue)) return true;
                        
                        // Search by URL/link
                        if (thumbnailUrl.includes(searchValue)) return true;
                        if (imageUrl.includes(searchValue)) return true;
                        
                        // Search by alt text
                        if (altText.includes(searchValue)) return true;
                        
                        return false;
                    });
                    //console.log('Filtered images count:', filteredImages.length);
                }
                
                // Reset to first page and update UI
                currentPage = 1;
                totalPages = Math.ceil(filteredImages.length / imagesPerPage);
                //console.log('Updated pagination: totalPages =', totalPages);
                updatePaginationUI();
                renderCurrentPage();
            }, 300); // 300ms delay for debouncing
        });
        
        // Clear search - use event delegation
        $(document).on('click', '#altm-search-clear', function() {
            $('#altm-search-input').val('');
            $('#altm-search-clear').hide();
            
            //console.log('Search cleared');
            
            // Reset to all images
            filteredImages = [...allImages];
            currentPage = 1;
            totalPages = Math.ceil(filteredImages.length / imagesPerPage);
            updatePaginationUI();
            renderCurrentPage();
        });
    });
    </script>
    
    <style type="text/css">
    .altm-processed-images-container {
        margin-top: 20px;
    }
    
    .altm-loading {
        text-align: center;
        margin: 40px 0;
    }
    
    /* Summary Card */
    .altm-summary-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e4e7;
    }
    
    .altm-card-content {
        display: flex;
        align-items: center;
    }
    
    .altm-card-icon {
        margin-right: 20px;
    }
    
    .altm-card-icon .dashicons {
        font-size: 48px;
        width: 48px;
        height: 48px;
        color: #2271b1;
    }
    
    .altm-card-info h2 {
        margin: 0 0 8px 0;
        font-size: 18px;
        color: #1d2327;
    }
    
    .altm-card-stats {
        font-size: 14px;
        color: #50575e;
    }
    
    .altm-total-count {
        font-size: 24px;
        font-weight: 600;
        color: #2271b1;
    }
    
    /* Thumbnail loading and error states */
    .altm-loading-thumbnail {
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f0f0f1;
        border-radius: 4px;
    }
    
    .altm-no-image {
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f0f0f1;
        color: #757575;
        font-size: 12px;
        text-align: center;
        padding: 10px;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    /* Table controls and pagination */
    .altm-table-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .altm-search-control {
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 400px;
        margin-left: auto; /* Push to the right */
    }
    
    #altm-search-input {
        width: 100%;
    }
    
    .altm-per-page-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .altm-pagination-container {
        margin-top: 20px;
    }
    
    .altm-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 15px;
    }
    
    .altm-bottom-controls {
        display: flex;
        justify-content: center;
        margin-top: 15px;
    }
    
    .altm-pagination-numbers {
        display: flex;
        align-items: center;
        gap: 5px;
        margin: 0 10px;
    }
    
    .altm-page-number {
        min-width: 30px;
        text-align: center;
    }
    
    .altm-page-number.altm-current-page {
        background-color: #2271b1;
        color: white;
        font-weight: 500;
    }
    
    .altm-pagination-ellipsis {
        margin: 0 5px;
    }
    
    /* Existing styles */
    .altm-processed-images-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    
    .altm-processed-images-table th {
        text-align: left;
        padding: 10px;
    }
    
    .altm-alt-text-display {
        padding: 8px;
        line-height: 1.5;
        word-wrap: break-word;
        color: #d97706;
    }
    
    .altm-alt-text-not-generated {
        color: #757575;
        font-style: italic;
    }
    
    @media screen and (max-width: 782px) {
        .altm-table-controls {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
    
    /* Empty state styling */
    .altm-empty-state {
        text-align: center;
        background: #fff;
        border-radius: 8px;
        padding: 40px;
        margin: 40px auto;
        max-width: 600px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e4e7;
    }
    
    .altm-empty-state-icon {
        margin-bottom: 20px;
    }
    
    .altm-empty-state-icon .dashicons {
        font-size: 64px;
        width: 64px;
        height: 64px;
        color: #007cba;
    }
    
    .altm-empty-state-icon.error .dashicons {
        color: #d94f4f;
    }
    
    .altm-empty-state h2 {
        font-size: 24px;
        margin-bottom: 15px;
        color: #1e1e1e;
    }
    
    .altm-empty-state p {
        font-size: 16px;
        color: #757575;
        margin-bottom: 25px;
    }
    
    .altm-empty-state-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    
    .altm-empty-state-actions .button {
        min-width: 160px;
        text-align: center;
    }
    
    .altm-no-results {
        padding: 20px;
        text-align: center;
        font-style: italic;
        color: #757575;
    }
    
    /* Image Name Column Styles */
    .altm-image-name-generated {
        color: #46b450;
        font-weight: 500;
    }
    
    .altm-image-name-not-generated {
        color: #757575;
        font-style: italic;
    }
    </style>
    <?php
} 