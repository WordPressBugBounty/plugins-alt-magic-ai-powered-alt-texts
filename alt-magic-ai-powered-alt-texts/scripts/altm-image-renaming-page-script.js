jQuery(document).ready(function ($) {
    // Tab management
    let activeTab = 'bad-names';

    // Track current credits
    let currentCredits = null;

    // All Images tab state
    let allImagesCurrentPage = 1;
    let allImagesTotalPages = 1;
    let allImagesPageSize = 25;
    let allImagesSearchTerm = '';
    let allImagesTypeFilter = '';

    // Bad Names tab state
    let badNamesCurrentPage = 1;
    let badNamesTotalPages = 1;
    let badNamesPageSize = 25;
    let badNamesSearchTerm = '';

    // Initialize the page
    init();

    function init() {
        fetchUserCredits();
        loadAllImages();
        loadBadNameImages();
        setupEventListeners();
        updateFilterDisplay();
    }

    function setupEventListeners() {
        // Tab switching
        $('.nav-tab').on('click', function (e) {
            e.preventDefault();
            switchTab($(this).data('tab'));
        });

        // Search functionality for All Images tab
        $('#search-images').on('input', debounce(function () {
            allImagesSearchTerm = $(this).val();
            allImagesCurrentPage = 1;
            loadAllImages();
        }, 300));

        // Image type filter button click handler
        $('#image-type-filter-btn').on('click', function (e) {
            e.stopPropagation();
            const dropdown = $('#image-type-filter-dropdown');
            dropdown.toggle();
        });

        // Close dropdown when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#image-type-filter-btn, #image-type-filter-dropdown').length) {
                $('#image-type-filter-dropdown').hide();
            }
        });

        // Image type filter radio change handler
        $('input[name="image-type-filter"]').on('change', function () {
            allImagesTypeFilter = $(this).val();
            allImagesCurrentPage = 1;
            updateFilterDisplay();
            loadAllImages();
            // Close dropdown after selection
            setTimeout(function () {
                $('#image-type-filter-dropdown').hide();
            }, 200);
        });

        // Search functionality for Bad Names tab
        $('#search-bad-names').on('input', debounce(function () {
            badNamesSearchTerm = $(this).val();
            badNamesCurrentPage = 1;
            loadBadNameImages();
        }, 300));

        // Page size change for All Images
        $('#page-size-all-images').on('change', function () {
            allImagesPageSize = parseInt($(this).val());
            allImagesCurrentPage = 1;
            loadAllImages();
        });

        // Page size change for Bad Names
        $('#page-size-bad-names').on('change', function () {
            badNamesPageSize = parseInt($(this).val());
            badNamesCurrentPage = 1;
            loadBadNameImages();
        });

        // Rename button click handler - direct rename without modal
        $(document).on('click', '.rename-image-btn', function () {
            const imageId = $(this).data('id');
            const $button = $(this);

            // Remove any existing error or success messages
            $button.parent().find('.altm-error-message, .altm-success-message').remove();

            // Check if we have credits before starting
            if (currentCredits !== null && currentCredits <= 0) {
                showNoCreditsModal('You don\'t have enough credits to rename images. Please purchase more credits to continue.');
                return;
            }

            performRename(imageId, $button);
        });

        // Edit button click handler - open edit modal
        $(document).on('click', '.edit-filename-btn', function () {
            const imageId = $(this).data('id');
            const filename = $(this).data('filename');
            const imageUrl = $(this).data('image-url');

            openEditModal({
                ID: imageId,
                filename: filename,
                image_url: imageUrl
            });
        });

        // Edit modal event handlers
        $('#close-edit-modal, #cancel-edit').on('click', closeEditModal);
        $('#save-filename').on('click', saveManualFilename);

        // Close modal on outside click
        $('#edit-filename-modal').on('click', function (e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Enter key handler for filename input
        $('#new-filename-input').on('keypress', function (e) {
            if (e.which === 13) { // Enter key
                saveManualFilename();
            }
        });

        // Checkbox event handlers
        setupCheckboxHandlers();

        // Bulk operation button handlers (temporarily disabled)
        setupBulkOperationHandlers();
        // Keep bulk buttons disabled without per-button notices
        $('#bulk-rename-selected-all-images, #bulk-rename-selected-bad-names, #bulk-rename-all-all-images, #bulk-rename-all-bad-names')
            .prop('disabled', true)
            .off('click').on('click', function (e) { e.preventDefault(); return false; });

        // Modal handlers
        setupModalHandlers();

        // Image hover and click handlers
        setupImageHoverHandlers();

        // WooCommerce rename product-name toggle (if present)
        const wooToggle = document.getElementById('rename-woo-use-product-name');
        if (wooToggle) {
            wooToggle.addEventListener('change', async function () {
                try {
                    const form = new URLSearchParams();
                    form.append('action', 'alt_magic_save_settings');
                    form.append('nonce', altmImageRenaming.saveSettingsNonce);
                    form.append('key', 'alt_magic_rename_use_woocommerce_product_name');
                    form.append('value', this.checked ? '1' : '0');

                    const resp = await fetch(altmImageRenaming.ajaxUrl, { method: 'POST', body: form });
                    // no-op on result; UI is already toggled
                } catch (e) {
                    console.error('Failed saving WooCommerce rename toggle', e);
                }
            });
        }
    }

    function debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Tab switching function
    function switchTab(tabName) {
        if (activeTab === tabName) return;

        // Update active tab
        activeTab = tabName;

        // Update tab appearance
        $('.nav-tab').removeClass('nav-tab-active');
        $('[data-tab="' + tabName + '"]').addClass('nav-tab-active');

        // Show/hide tab content
        $('.tab-content').removeClass('active');
        $('#tab-content-' + tabName).addClass('active');

        // Update filter display when switching to all-images tab
        if (tabName === 'all-images') {
            updateFilterDisplay();
        }
    }

    // Number formatting function
    function formatNumber(num) {
        if (num === null || num === undefined) return num;
        return parseInt(num).toLocaleString();
    }

    async function fetchUserCredits() {
        $('.credits-available-text').text('... credits');
        try {
            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'altm_fetch_user_credits',
                    'nonce': altmImageRenaming.fetchCreditsNonce
                })
            });

            const data = await response.json();

            // Check for authentication errors
            if (data.success === false) {
                // Use the message from backend, or default message
                var errorMessage = data.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.';
                displayCreditsError(errorMessage);
                return;
            }

            // Credits endpoint returns direct response, not wrapped in success object
            let credits = null;

            if (data.success !== undefined) {
                // Standard WordPress AJAX response format
                if (data.success && data.data && data.data.credits_available !== undefined) {
                    credits = parseInt(data.data.credits_available);
                }
            } else if (data.credits_available !== undefined) {
                // Direct API response format
                credits = parseInt(data.credits_available);
            }

            if (credits !== null) {
                updateCreditsDisplay(credits);
                // Clear any previous error messages
                clearCreditsError();
            } else {
                displayCreditsError('Unable to fetch credits. Please try again.');
            }
        } catch (error) {
            console.error('Error fetching user credits:', error);
            displayCreditsError('Network error while fetching credits. Please check your connection.');
        }
    }

    function displayCreditsError(message) {
        const accountInfoContainer = $('.account-info-container');
        const accountInfoText = $('#account-info-text');

        // Update the container styling for error
        accountInfoContainer.css({
            'background-color': '#fdeaea',
            'border-color': '#b70000'
        });

        accountInfoText.css({
            'color': '#b70000'
        });

        // Update the message
        const accountSettingsUrl = (typeof altmImageRenaming !== 'undefined' && altmImageRenaming.accountSettingsUrl)
            ? altmImageRenaming.accountSettingsUrl
            : '/wp-admin/admin.php?page=alt-magic';
        accountInfoText.html(
            '<span style="font-weight: bold;">⚠️ </span>' +
            message +
            ' <a href="' + accountSettingsUrl + '">Go to Account Settings</a>'
        );
    }

    function clearCreditsError() {
        const accountInfoContainer = $('.account-info-container');
        // Reset to default styling (will be updated by updateCreditsDisplay if needed)
        accountInfoContainer.css({
            'background-color': '#f8f9fa',
            'border-color': '#ddd',
            'color': '#333'
        });
    }

    function updateCreditsDisplay(credits) {
        if (credits !== null) {
            currentCredits = credits;
            $('.credits-available-text').text(formatNumber(credits) + ' credits');

            // Remove all credit classes first
            $('.credits-available-text').removeClass('credits-high credits-medium credits-low');

            // Update styling based on credits availability
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

    // No credits modal functions
    function showNoCreditsModal(customMessage) {
        // Remove any existing modal first
        $('#altm-no-credits-modal').remove();

        // Get user email from WordPress localized data or fallback
        const userEmail = altmImageRenaming.userEmail || '';
        const purchaseUrl = userEmail
            ? `https://altmagic.pro/?wp_email=${encodeURIComponent(userEmail)}#pricing`
            : 'https://altmagic.pro/#pricing';

        // Default message if none provided
        const message = customMessage || 'You don\'t have enough credits. Please purchase more credits to continue.';

        const modalHtml = `
            <div id="altm-no-credits-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 400px; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #b70000;">⚠️ No Credits Remaining</h3>
                        <button id="close-no-credits-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; line-height: 1;">&times;</button>
                    </div>
                    
                    <div style="margin-bottom: 20px; line-height: 1.6;">
                        <p style="margin: 0;">${message}</p>
                    </div>
                    
                    <div style="text-align: end;">
                        <button id="dismiss-no-credits" class="button" style="margin-right: 10px;">Dismiss</button>
                        <a href="${purchaseUrl}" target="_blank" class="button button-primary">Purchase Credits</a>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        $('#altm-no-credits-modal').fadeIn(200);
    }

    function hideNoCreditsModal() {
        $('#altm-no-credits-modal').fadeOut(200, function () {
            $(this).remove();
        });
    }

    // No credits modal close handlers
    $(document).on('click', '#close-no-credits-modal, #dismiss-no-credits', function () {
        hideNoCreditsModal();
    });

    // Close modal when clicking outside
    $(document).on('click', '#altm-no-credits-modal', function (e) {
        if (e.target === this) {
            hideNoCreditsModal();
        }
    });

    async function loadAllImages() {
        try {
            const list = $('#all-images-list');
            list.html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                '<span class="spinner is-active" style="float: none; margin-right: 8px;"></span>' +
                '<span style="color: #666;">Loading images...</span>' +
                '</td></tr>');

            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'altm_get_all_images_for_renaming',
                    'page': allImagesCurrentPage,
                    'per_page': allImagesPageSize,
                    'search': allImagesSearchTerm,
                    'type_filter': allImagesTypeFilter,
                    'nonce': altmImageRenaming.fetchCreditsNonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // Handle different response structures
                let images = [];
                let total = 0;
                let pages = 1;

                if (data.data) {
                    images = data.data.images || [];
                    total = data.data.total || 0;
                    pages = data.data.pages || 1;
                } else {
                    // Fallback if data is at root level
                    images = data.images || [];
                    total = data.total || 0;
                    pages = data.pages || 1;
                }

                displayImages(images, '#all-images-list');
                updateAllImagesPagination(total, pages);
                $('#all-images-count').text(formatNumber(total));
                $('#bulk-rename-all-all-images .total-count').text(formatNumber(total));
            } else {
                list.html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                    '<div style="color: #d63638; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">⚠️ </div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #d63638;">Error loading images</h3>' +
                    '<p style="margin: 0;">' + (data.data || 'Unknown error occurred. Please try again.') + '</p>' +
                    '</div>' +
                    '</td></tr>');
            }
        } catch (error) {
            console.error('Error loading images:', error);
            $('#all-images-list').html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                '<div style="color: #d63638; line-height: 1.6;">' +
                '<div style="font-size: 48px; margin-bottom: 16px;">🚫</div>' +
                '<h3 style="margin: 0 0 12px 0; color: #d63638;">Connection Error</h3>' +
                '<p style="margin: 0;">Unable to load images. Please check your connection and try again.</p>' +
                '</div>' +
                '</td></tr>');
        }
    }

    async function loadBadNameImages() {
        try {
            const list = $('#bad-names-list');
            list.html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                '<span class="spinner is-active" style="float: none; margin-right: 8px;"></span>' +
                '<span style="color: #666;">Loading images with bad names...</span>' +
                '</td></tr>');

            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'altm_get_bad_name_images',
                    'page': badNamesCurrentPage,
                    'per_page': badNamesPageSize,
                    'search': badNamesSearchTerm,
                    'nonce': altmImageRenaming.fetchCreditsNonce
                })
            });

            const data = await response.json();

            if (data.success) {
                // Handle different response structures
                let images = [];
                let total = 0;
                let pages = 1;

                if (data.data) {
                    images = data.data.images || [];
                    total = data.data.total || 0;
                    pages = data.data.pages || 1;
                } else {
                    // Fallback if data is at root level
                    images = data.images || [];
                    total = data.total || 0;
                    pages = data.pages || 1;
                }

                displayImages(images, '#bad-names-list', 'bad-names');
                updateBadNamesPagination(total, pages);
                $('#bad-names-count').text(formatNumber(total));
                $('#bulk-rename-all-bad-names .total-count').text(formatNumber(total));
            } else {
                list.html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                    '<div style="color: #d63638; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">⚠️ </div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #d63638;">Error loading bad name images</h3>' +
                    '<p style="margin: 0;">' + (data.data || 'Unknown error occurred. Please try again.') + '</p>' +
                    '</div>' +
                    '</td></tr>');
            }
        } catch (error) {
            console.error('Error loading bad name images:', error);
            $('#bad-names-list').html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                '<div style="color: #d63638; line-height: 1.6;">' +
                '<div style="font-size: 48px; margin-bottom: 16px;">🚫</div>' +
                '<h3 style="margin: 0 0 12px 0; color: #d63638;">Connection Error</h3>' +
                '<p style="margin: 0;">Unable to load images. Please check your connection and try again.</p>' +
                '</div>' +
                '</td></tr>');
        }
    }

    function displayImages(images, listSelector, tabType = 'all') {
        const list = $(listSelector);
        list.empty();

        if (!images || images.length === 0) {
            let placeholderHtml = '<tr><td colspan="5" style="text-align: center; padding: 40px;">' + getPlaceholderHtml(tabType) + '</td></tr>';
            list.append(placeholderHtml);
            return;
        }

        for (const image of images) {
            const filename = image.filename || 'No filename';

            const row = '<tr>' +
                '<td><input type="checkbox" class="image-checkbox" data-id="' + image.ID + '" /></td>' +
                '<td>' + image.ID + '</td>' +
                '<td style="padding-right: 20px;">' +
                '<div style="position: relative; display: inline-block;">' +
                '<img src="' + image.image_url + '" style="height: 100px; width: 100px; max-width: 100px; border-radius: 4px; object-fit: cover;" loading="lazy" />' +
                '<div class="image-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); border-radius: 4px; display: none; cursor: pointer; align-items: center; justify-content: center;" data-original-url="' + (image.original_url || image.image_url) + '">' +
                '<span style="color: white; font-size: 24px; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">🔗</span>' +
                '</div>' +
                '</div>' +
                '</td>' +
                '<td class="altm-filename-cell" data-filename="' + escapeHtml(filename) + '" style="padding-left: 20px; padding-right: 20px;">' +
                '<div style="padding: 8px 10px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 12px; font-family: monospace; background: linear-gradient(135deg, #f9f9f9 0%, #e8e8e8 100%); word-wrap: break-word; line-height: 1.4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">' + escapeHtml(filename) + '</div>' +
                '</td>' +
                '<td>' +
                '<button class="button rename-image-btn" data-id="' + image.ID + '" data-filename="' + escapeHtml(filename) + '" data-image-url="' + image.image_url + '">AI Rename</button> ' +
                '<button class="button edit-filename-btn" data-id="' + image.ID + '" data-filename="' + escapeHtml(filename) + '" data-image-url="' + image.image_url + '">Edit</button>' +
                '</td>' +
                '</tr>';
            list.append(row);
        }

        // Update selected count after displaying images
        updateSelectedCount(tabType);
    }

    function getPlaceholderHtml(tabType = 'all') {
        const searchTerm = tabType === 'bad-names' ? badNamesSearchTerm : allImagesSearchTerm;

        if (searchTerm) {
            return '<div style="color: #666; line-height: 1.6;">' +
                '<div style="font-size: 48px; margin-bottom: 16px;">🔍</div>' +
                '<h3 style="margin: 0 0 12px 0; color: #1d2327;">No images found</h3>' +
                '<p style="margin: 0 0 20px 0;">No images match your search criteria. Try a different search term.</p>' +
                '</div>';
        } else {
            if (tabType === 'bad-names') {
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">✅</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">Great news!</h3>' +
                    '<p style="margin: 0 0 20px 0;">No images with bad names found. All your images have good filenames!</p>' +
                    '</div>';
            } else {
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">📷</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">No images found</h3>' +
                    '<p style="margin: 0 0 20px 0;">There are currently no images in your media library.</p>' +
                    '</div>';
            }
        }
    }


    function updateAllImagesPagination(total, pages) {
        allImagesTotalPages = pages;
        const container = $('#tab-content-all-images .altm-pagination-container');

        // Remove existing pagination
        container.find('.altm-pagination').remove();

        // Don't show pagination if there's only 1 page or no pages
        if (pages <= 1) {
            // Show simple page info when there's only 1 page
            if (total > 0) {
                let pageInfoHtml = '<div class="altm-pagination">';
                pageInfoHtml += '<div class="altm-pagination-info">Showing 1 to ' + total + ' of ' + total + ' images</div>';
                pageInfoHtml += '</div>';

                container.find('.altm-pagination-controls').append(pageInfoHtml);
            }
            return;
        }

        let paginationHtml = '<div class="altm-pagination">';

        // Page info
        const startIndex = (allImagesCurrentPage - 1) * allImagesPageSize + 1;
        const endIndex = Math.min(allImagesCurrentPage * allImagesPageSize, total);
        paginationHtml += '<div class="altm-pagination-info">Showing ' + startIndex + ' to ' + endIndex + ' of ' + total + ' images</div>';

        // Page numbers
        paginationHtml += '<div class="altm-pagination-numbers">';

        // Calculate range of page numbers to show
        let startPage = Math.max(1, allImagesCurrentPage - 2);
        let endPage = Math.min(pages, allImagesCurrentPage + 2);

        // Always show first page if not in range
        if (startPage > 1) {
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="1" data-tab="all-images">1</button>';
            if (startPage > 2) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
        }

        // Show page numbers in range
        for (let i = startPage; i <= endPage; i++) {
            let activeClass = i === allImagesCurrentPage ? ' altm-pagination-page-active' : '';
            paginationHtml += '<button type="button" class="button altm-pagination-page' + activeClass + '" data-page="' + i + '" data-tab="all-images">' + i + '</button>';
        }

        // Always show last page if not in range
        if (endPage < pages) {
            if (endPage < pages - 1) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="' + pages + '" data-tab="all-images">' + pages + '</button>';
        }

        paginationHtml += '</div>';
        paginationHtml += '</div>';

        // Update only the pagination section, not the entire controls section
        container.find('.altm-pagination-controls').append(paginationHtml);

        // Add event listeners for pagination buttons
        addPaginationEventListeners();
    }

    function updateBadNamesPagination(total, pages) {
        badNamesTotalPages = pages;
        const container = $('#tab-content-bad-names .altm-pagination-container');

        // Remove existing pagination
        container.find('.altm-pagination').remove();

        // Don't show pagination if there's only 1 page or no pages
        if (pages <= 1) {
            // Show simple page info when there's only 1 page
            if (total > 0) {
                let pageInfoHtml = '<div class="altm-pagination">';
                pageInfoHtml += '<div class="altm-pagination-info">Showing 1 to ' + total + ' of ' + total + ' images</div>';
                pageInfoHtml += '</div>';

                container.find('.altm-pagination-controls').append(pageInfoHtml);
            }
            return;
        }

        let paginationHtml = '<div class="altm-pagination">';

        // Page info
        const startIndex = (badNamesCurrentPage - 1) * badNamesPageSize + 1;
        const endIndex = Math.min(badNamesCurrentPage * badNamesPageSize, total);
        paginationHtml += '<div class="altm-pagination-info">Showing ' + startIndex + ' to ' + endIndex + ' of ' + total + ' images</div>';

        // Page numbers
        paginationHtml += '<div class="altm-pagination-numbers">';

        // Calculate range of page numbers to show
        let startPage = Math.max(1, badNamesCurrentPage - 2);
        let endPage = Math.min(pages, badNamesCurrentPage + 2);

        // Always show first page if not in range
        if (startPage > 1) {
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="1" data-tab="bad-names">1</button>';
            if (startPage > 2) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
        }

        // Show page numbers in range
        for (let i = startPage; i <= endPage; i++) {
            let activeClass = i === badNamesCurrentPage ? ' altm-pagination-page-active' : '';
            paginationHtml += '<button type="button" class="button altm-pagination-page' + activeClass + '" data-page="' + i + '" data-tab="bad-names">' + i + '</button>';
        }

        // Always show last page if not in range
        if (endPage < pages) {
            if (endPage < pages - 1) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="' + pages + '" data-tab="bad-names">' + pages + '</button>';
        }

        paginationHtml += '</div>';
        paginationHtml += '</div>';

        // Update only the pagination section, not the entire controls section
        container.find('.altm-pagination-controls').append(paginationHtml);

        // Add event listeners for pagination buttons
        addPaginationEventListeners();
    }

    function addPaginationEventListeners() {
        // Page number buttons
        $('.altm-pagination-page').on('click', function () {
            let page = parseInt($(this).data('page'));
            let tab = $(this).data('tab');
            goToPage(page, tab);
        });
    }

    function goToPage(page, tab) {
        if (tab === 'bad-names') {
            // Validate page number for bad names tab
            if (page < 1 || page > badNamesTotalPages) {
                console.warn('Invalid page number for bad names:', page, 'Total pages:', badNamesTotalPages);
                return;
            }

            badNamesCurrentPage = page;
            loadBadNameImages();
        } else {
            // Validate page number for all images tab (default)
            if (page < 1 || page > allImagesTotalPages) {
                console.warn('Invalid page number for all images:', page, 'Total pages:', allImagesTotalPages);
                return;
            }

            allImagesCurrentPage = page;
            loadAllImages();
        }
    }

    // Authentication error modal functions
    function showAuthErrorModal(message) {
        const modal = $('#altm-auth-error-modal');
        const messageElement = $('#auth-error-message');

        // Set the error message - use consistent messaging
        const errorMessage = message && message.toLowerCase().includes('authentication')
            ? 'Connection to Alt Magic failed. Please check your API key in Account Settings.'
            : (message || 'Connection to Alt Magic failed. Please check your API key in Account Settings.');
        messageElement.text(errorMessage);

        // Show the modal
        modal.fadeIn(200);
    }

    function hideAuthErrorModal() {
        $('#altm-auth-error-modal').fadeOut(200);
    }

    // Close modal handlers
    $(document).on('click', '#close-auth-error-modal, #dismiss-auth-error', function () {
        hideAuthErrorModal();
    });

    // Close modal when clicking outside
    $(document).on('click', '#altm-auth-error-modal', function (e) {
        if (e.target === this) {
            hideAuthErrorModal();
        }
    });

    async function performRename(imageId, $button) {
        const originalText = $button.text();

        // Check if API key exists before making request
        if (!altmImageRenaming.hasApiKey) {
            showAuthErrorModal('No Alt Magic account found. Please connect your account in Account Settings.');
            return;
        }

        $button.text('Generating...').prop('disabled', true);

        try {
            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'altm_rename_image',
                    'attachment_id': imageId,
                    'nonce': altmImageRenaming.renameImageNonce
                })
            });

            const data = await response.json();

            // Check for authentication errors first
            if (data.success === false && data.status_code === 403) {
                $button.text(originalText).prop('disabled', false);
                showAuthErrorModal('Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                return;
            }

            if (data.success) {
                // Update credits from response if available
                if (data.data && data.data.credits_available !== undefined) {
                    updateCreditsDisplay(parseInt(data.data.credits_available));
                }

                $button.text('AI Rename').prop('disabled', false);

                // Update the filename in the current row if new filename is provided
                if (data.data && data.data.new_filename) {
                    let $row = $button.closest('tr');
                    let $filenameCell = $row.find('.altm-filename-cell');
                    $filenameCell.data('filename', data.data.new_filename);
                    $filenameCell.find('div').text(data.data.new_filename);
                }

                // Add green success icon on the right side of the edit button
                let $editButton = $button.closest('tr').find('.edit-filename-btn');
                let successHtml = '<span class="altm-success-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle;"><svg width="20" height="20" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 0C12.0333 0 9.13319 0.879735 6.66645 2.52796C4.19972 4.17618 2.27713 6.51886 1.14181 9.25975C0.00649941 12.0006 -0.290551 15.0166 0.288228 17.9264C0.867006 20.8361 2.29562 23.5088 4.3934 25.6066C6.49119 27.7044 9.16394 29.133 12.0737 29.7118C14.9834 30.2906 17.9994 29.9935 20.7403 28.8582C23.4812 27.7229 25.8238 25.8003 27.4721 23.3336C29.1203 20.8668 30 17.9667 30 15C30 11.0218 28.4197 7.20644 25.6066 4.3934C22.7936 1.58035 18.9783 0 15 0ZM12 22C11.8684 22.0008 11.7379 21.9755 11.6161 21.9258C11.4943 21.876 11.3834 21.8027 11.29 21.71L5.29001 15.65C5.19677 15.5568 5.12281 15.4461 5.07235 15.3242C5.02189 15.2024 4.99591 15.0719 4.99591 14.94C4.99591 14.6737 5.1017 14.4183 5.29001 14.23C5.47831 14.0417 5.73371 13.9359 6.00001 13.9359C6.26631 13.9359 6.5217 14.0417 6.71001 14.23L12 19.58L23.29 8.29C23.4783 8.1017 23.7337 7.99591 24 7.99591C24.2663 7.99591 24.5217 8.1017 24.71 8.29C24.8983 8.4783 25.0041 8.7337 25.0041 9C25.0041 9.2663 24.8983 9.5217 24.71 9.71L12.71 21.71C12.6166 21.8027 12.5058 21.876 12.3839 21.9258C12.2621 21.9755 12.1316 22.0008 12 22Z" fill="#00B612"/></svg></span>';
                $editButton.after(successHtml);

                // Remove success text after 2 seconds
                setTimeout(function () {
                    $editButton.parent().find('.altm-success-message').fadeOut('fast', function () {
                        $(this).remove();
                    });
                }, 2000);
            } else {
                console.error('Error renaming image:', data.data ? data.data.message : 'Unknown error');
                $button.text(originalText).prop('disabled', false);

                let errorMessage = 'Unable to rename the image.';
                if (data.data && data.data.message) {
                    errorMessage = data.data.message;
                }

                // Add error message on the right side of the edit button
                let $editButton = $button.closest('tr').find('.edit-filename-btn');
                let errorHtml = '<span class="altm-error-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle; color: #d63638; font-size: 11px;">Error: ' + errorMessage + '</span>';
                $editButton.after(errorHtml);

                setTimeout(function () {
                    $editButton.parent().find('.altm-error-message').fadeOut('slow', function () {
                        $(this).remove();
                    });
                }, 5000);
            }
        } catch (error) {
            console.error('Error renaming image:', error);
            $button.text(originalText).prop('disabled', false);

            // Add error message on the right side of the edit button
            let $editButton = $button.closest('tr').find('.edit-filename-btn');
            let errorHtml = '<span class="altm-error-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle; color: #d63638; font-size: 11px;">Error: Unable to rename the image.</span>';
            $editButton.after(errorHtml);

            setTimeout(function () {
                $editButton.parent().find('.altm-error-message').fadeOut('slow', function () {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    function openEditModal(image) {
        const modal = $('#edit-filename-modal');
        const previewImg = $('#edit-image-preview');
        const filenameInput = $('#new-filename-input');

        // Clear any existing error messages
        modal.find('.modal-error-message').hide();

        // Reset input border color
        filenameInput.css('border-color', '#ddd');

        // Set modal data
        modal.data('image-id', image.ID);
        modal.data('original-filename', image.filename);

        // Update modal title with image ID
        $('#edit-modal-title').html('Edit Filename <span style="font-size: 14px; color: #666; font-weight: normal;">(ID: ' + image.ID + ')</span>');

        // Populate modal content
        previewImg.attr('src', image.image_url || '');

        // Set filename without extension in input field
        const filenameWithoutExt = image.filename.replace(/\.[^/.]+$/, '');
        filenameInput.val(filenameWithoutExt);

        // Show modal with fade effect
        modal.fadeIn(200);
        setTimeout(() => {
            filenameInput.focus().select();
        }, 200);
    }

    function closeEditModal() {
        $('#edit-filename-modal').fadeOut(200, function () {
            $('#edit-progress').hide();
            $('#new-filename-input').val('').css('border-color', '#ddd');
            $('#edit-filename-modal .modal-error-message').hide();
        });
    }

    async function saveManualFilename() {
        const modal = $('#edit-filename-modal');
        const progress = $('#edit-progress');
        const filenameInput = $('#new-filename-input');
        const saveButton = $('#save-filename');

        const imageId = modal.data('image-id');
        const originalFilename = modal.data('original-filename');
        const newFilenameBase = filenameInput.val().trim();

        if (!newFilenameBase) {
            filenameInput.css('border-color', '#d63638').focus();
            // Reset border color after a delay
            setTimeout(() => {
                filenameInput.css('border-color', '#ddd');
            }, 3000);
            return;
        }

        // Get file extension from original filename
        const extension = originalFilename.split('.').pop();
        const newFilename = newFilenameBase + '.' + extension;

        // Check if filename actually changed
        if (newFilename === originalFilename) {
            filenameInput.css('border-color', '#ff8c00').focus();
            // Reset border color after a delay
            setTimeout(() => {
                filenameInput.css('border-color', '#ddd');
            }, 3000);
            return;
        }

        progress.show();
        saveButton.prop('disabled', true);

        try {
            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'altm_rename_image',
                    'attachment_id': imageId,
                    'new_filename': newFilename,
                    'nonce': altmImageRenaming.renameImageNonce
                })
            });

            // Read raw text first to gracefully handle non-JSON error responses
            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                console.error('Manual rename non-JSON response:', rawText);
                throw new Error(rawText.substring(0, 500));
            }

            console.log('Save manual filename request: ' + JSON.stringify(response));

            console.log('Save manual filename response: ' + JSON.stringify(data));

            // Check for authentication errors first
            if (data.success === false && data.status_code === 403) {
                progress.hide();
                saveButton.prop('disabled', false);
                closeEditModal();
                showAuthErrorModal('Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                return;
            }

            if (data.success) {
                // Update the filename in the table
                const $row = $('.edit-filename-btn[data-id="' + imageId + '"]').closest('tr');
                const $filenameCell = $row.find('.altm-filename-cell');
                $filenameCell.data('filename', data.data.new_filename);
                $filenameCell.find('div').text(data.data.new_filename);

                // Update button data attributes
                $row.find('.rename-image-btn, .edit-filename-btn').attr('data-filename', data.data.new_filename);

                closeEditModal();

                // Show success icon on the right side of edit button
                const $editButton = $row.find('.edit-filename-btn');
                let successHtml = '<span class="altm-success-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle;"><svg width="20" height="20" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 0C12.0333 0 9.13319 0.879735 6.66645 2.52796C4.19972 4.17618 2.27713 6.51886 1.14181 9.25975C0.00649941 12.0006 -0.290551 15.0166 0.288228 17.9264C0.867006 20.8361 2.29562 23.5088 4.3934 25.6066C6.49119 27.7044 9.16394 29.133 12.0737 29.7118C14.9834 30.2906 17.9994 29.9935 20.7403 28.8582C23.4812 27.7229 25.8238 25.8003 27.4721 23.3336C29.1203 20.8668 30 17.9667 30 15C30 11.0218 28.4197 7.20644 25.6066 4.3934C22.7936 1.58035 18.9783 0 15 0ZM12 22C11.8684 22.0008 11.7379 21.9755 11.6161 21.9258C11.4943 21.876 11.3834 21.8027 11.29 21.71L5.29001 15.65C5.19677 15.5568 5.12281 15.4461 5.07235 15.3242C5.02189 15.2024 4.99591 15.0719 4.99591 14.94C4.99591 14.6737 5.1017 14.4183 5.29001 14.23C5.47831 14.0417 5.73371 13.9359 6.00001 13.9359C6.26631 13.9359 6.5217 14.0417 6.71001 14.23L12 19.58L23.29 8.29C23.4783 8.1017 23.7337 7.99591 24 7.99591C24.2663 7.99591 24.5217 8.1017 24.71 8.29C24.8983 8.4783 25.0041 8.7337 25.0041 9C25.0041 9.2663 24.8983 9.5217 24.71 9.71L12.71 21.71C12.6166 21.8027 12.5058 21.876 12.3839 21.9258C12.2621 21.9755 12.1316 22.0008 12 22Z" fill="#00B612"/></svg></span>';
                $editButton.after(successHtml);

                setTimeout(function () {
                    $editButton.parent().find('.altm-success-message').fadeOut('fast', function () {
                        $(this).remove();
                    });
                }, 2000);

            } else {
                // Show error message in the modal
                const errorMsg = data.data ? data.data.message : 'Unknown error';
                showModalError('Error updating filename: ' + errorMsg);

                // Also show error message on the right side of the edit button
                const $editButton = $row.find('.edit-filename-btn');
                let errorHtml = '<span class="altm-error-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle; color: #d63638; font-size: 11px;">Error: ' + errorMsg + '</span>';
                $editButton.after(errorHtml);

                setTimeout(function () {
                    $editButton.parent().find('.altm-error-message').fadeOut('slow', function () {
                        $(this).remove();
                    });
                }, 5000);
            }
        } catch (error) {
            console.error('Error updating filename:', error);
            showModalError('An error occurred while updating the filename. Please try again.');

            // Also show error message on the right side of the edit button
            const $editButton = $row.find('.edit-filename-btn');
            let errorHtml = '<span class="altm-error-message" style="margin-left: 8px; display: inline-flex; align-items: center; vertical-align: -webkit-baseline-middle; color: #d63638; font-size: 11px;">Error: An error occurred while updating the filename.</span>';
            $editButton.after(errorHtml);

            setTimeout(function () {
                $editButton.parent().find('.altm-error-message').fadeOut('slow', function () {
                    $(this).remove();
                });
            }, 5000);
        } finally {
            progress.hide();
            saveButton.prop('disabled', false);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // Checkbox handling functions
    function setupCheckboxHandlers() {
        // Select all checkboxes
        $(document).on('change', '#select-all-all-images', function () {
            $('#all-images-list .image-checkbox').prop('checked', $(this).prop('checked'));
            updateSelectedCount('all-images');
        });

        $(document).on('change', '#select-all-bad-names', function () {
            $('#bad-names-list .image-checkbox').prop('checked', $(this).prop('checked'));
            updateSelectedCount('bad-names');
        });

        // Individual checkboxes
        $(document).on('change', '.image-checkbox', function () {
            const listId = $(this).closest('tbody').attr('id');
            const tabType = listId === 'bad-names-list' ? 'bad-names' : 'all-images';
            updateSelectedCount(tabType);
        });
    }

    function updateSelectedCount(tabType) {
        let listSelector, selectAllSelector, bulkButtonSelector;

        if (tabType === 'bad-names') {
            listSelector = '#bad-names-list';
            selectAllSelector = '#select-all-bad-names';
            bulkButtonSelector = '#bulk-rename-selected-bad-names';
        } else {
            listSelector = '#all-images-list';
            selectAllSelector = '#select-all-all-images';
            bulkButtonSelector = '#bulk-rename-selected-all-images';
        }

        const totalCheckboxes = $(listSelector + ' .image-checkbox').length;
        const checkedCheckboxes = $(listSelector + ' .image-checkbox:checked').length;

        // Update selected count but keep feature disabled
        $(bulkButtonSelector + ' .selected-count').text(checkedCheckboxes);
        $(bulkButtonSelector).prop('disabled', true);

        // Update select all checkbox
        if (totalCheckboxes === 0) {
            $(selectAllSelector).prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $(selectAllSelector).prop('indeterminate', false).prop('checked', true);
        } else if (checkedCheckboxes > 0) {
            $(selectAllSelector).prop('indeterminate', true);
        } else {
            $(selectAllSelector).prop('indeterminate', false).prop('checked', false);
        }
    }

    // Bulk operation handlers
    function setupBulkOperationHandlers() {
        // Bulk rename selected buttons
        $('#bulk-rename-selected-all-images').on('click', function () {
            const selectedIds = getSelectedImageIds('all-images');
            if (selectedIds.length > 0) {
                startBulkRename(selectedIds, 'all-images');
            }
        });

        $('#bulk-rename-selected-bad-names').on('click', function () {
            const selectedIds = getSelectedImageIds('bad-names');
            if (selectedIds.length > 0) {
                startBulkRename(selectedIds, 'bad-names');
            }
        });

        // Bulk rename all buttons
        $('#bulk-rename-all-all-images').on('click', function () {
            startBulkRename('all', 'all-images');
        });

        $('#bulk-rename-all-bad-names').on('click', function () {
            startBulkRename('all', 'bad-names');
        });
    }

    function getSelectedImageIds(tabType) {
        const listSelector = tabType === 'bad-names' ? '#bad-names-list' : '#all-images-list';
        const selectedIds = [];
        $(listSelector + ' .image-checkbox:checked').each(function () {
            selectedIds.push(parseInt($(this).data('id')));
        });
        return selectedIds;
    }

    function startBulkRename(imageIds, tabType) {
        // Check if we have credits before starting
        if (currentCredits !== null && currentCredits <= 0) {
            showNoCreditsModal('You don\'t have enough credits to start bulk renaming. Please purchase more credits to continue.');
            return;
        }

        let imagesToProcess = [];

        if (imageIds === 'all') {
            // Get all images from the current tab
            const listSelector = tabType === 'bad-names' ? '#bad-names-list' : '#all-images-list';
            $(listSelector + ' .image-checkbox').each(function () {
                imagesToProcess.push(parseInt($(this).data('id')));
            });
        } else {
            imagesToProcess = imageIds;
        }

        if (imagesToProcess.length === 0) {
            // This shouldn't happen due to button states, but just in case
            return;
        }

        // Start bulk processing directly
        processBulkRename(imagesToProcess);
    }

    let bulkProcessing = {
        active: false,
        cancelled: false,
        processed: 0,
        successful: 0,
        failed: 0,
        total: 0,
        failedImages: []
    };

    async function processBulkRename(imageIds) {
        // Reset processing state
        bulkProcessing = {
            active: true,
            cancelled: false,
            processed: 0,
            successful: 0,
            failed: 0,
            total: imageIds.length,
            failedImages: []
        };

        // Show modal
        showBulkProcessingModal();
        updateBulkProgress();

        // Process images with limited concurrency
        const maxConcurrency = 3;
        const chunks = [];
        for (let i = 0; i < imageIds.length; i += maxConcurrency) {
            chunks.push(imageIds.slice(i, i + maxConcurrency));
        }

        for (const chunk of chunks) {
            if (bulkProcessing.cancelled) break;

            const promises = chunk.map(imageId => processImageRename(imageId));
            await Promise.all(promises);
        }

        // Processing complete
        bulkProcessing.active = false;
        updateBulkProgress();

        // Change button text to "Close" when processing is complete
        $('#cancel-processing').text('Close');

        // Show completion message
        if (!bulkProcessing.cancelled) {
            $('#completion-message').show();
        }
    }

    async function processImageRename(imageId) {
        if (bulkProcessing.cancelled) return;

        try {
            const response = await fetch(altmImageRenaming.ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'altm_rename_image',
                    'attachment_id': imageId,
                    'nonce': altmImageRenaming.renameImageNonce
                })
            });

            const data = await response.json();

            // Check for authentication errors
            if (data.success === false && data.status_code === 403) {
                // Show authentication error modal and stop processing
                bulkProcessing.cancelled = true;
                bulkProcessing.active = false;
                showAuthErrorModal('Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                return;
            }

            if (data.success) {
                bulkProcessing.successful++;

                // Update credits from response if available
                if (data.data && data.data.credits_available !== undefined) {
                    updateCreditsDisplay(parseInt(data.data.credits_available));
                }
            } else {
                bulkProcessing.failed++;
                bulkProcessing.failedImages.push({
                    id: imageId,
                    error: data.data ? data.data.message : 'Unknown error'
                });
            }
        } catch (error) {
            bulkProcessing.failed++;
            bulkProcessing.failedImages.push({
                id: imageId,
                error: 'Network error: ' + error.message
            });
        }

        bulkProcessing.processed++;
        updateBulkProgress();
    }

    function showBulkProcessingModal() {
        $('#bulk-processing-modal').show();
        $('#completion-message').hide();
        $('#cancel-processing').text('Cancel Processing'); // Reset button text for new operation
        $('#failed-images-body').empty().append('<tr style="display: none;" id="no-failed-images"><td colspan="3" style="text-align: center; padding: 20px; color: #666; font-style: italic;">No failed images yet</td></tr>');
    }

    function updateBulkProgress() {
        const { processed, successful, failed, total, failedImages } = bulkProcessing;

        // Update progress text and bar
        $('#progress-text').text(`${processed} of ${total}`);
        $('#success-count').text(successful);
        $('#failed-count').text(failed);

        const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
        $('#progress-percentage').text(`${percentage}%`);
        $('#progress-bar-fill').css('width', `${percentage}%`);

        // Update spinner
        if (bulkProcessing.active && !bulkProcessing.cancelled) {
            if ($('#progress-spinner .spinner').length === 0) {
                $('#progress-spinner').show();
            }
        } else {
            $('#progress-spinner').hide();
        }

        // Update failed images table
        if (failedImages.length > 0) {
            $('#no-failed-images').hide();
            failedImages.forEach(item => {
                if ($(`#failed-row-${item.id}`).length === 0) {
                    const editUrl = `/wp-admin/post.php?post=${item.id}&action=edit`;
                    const row = `<tr id="failed-row-${item.id}">
                        <td style="padding: 6px 8px;">${item.id}</td>
                        <td style="padding: 6px 8px;"><a href="${editUrl}" target="_blank" style="color: #2271b1; text-decoration: none;">Edit</a></td>
                        <td style="padding: 6px 8px; word-break: break-word;">${escapeHtml(item.error)}</td>
                    </tr>`;
                    $('#failed-images-body').append(row);
                }
            });
        } else {
            $('#no-failed-images').show();
        }
    }

    function setupModalHandlers() {
        $('#close-modal, #cancel-processing').on('click', function () {
            if (bulkProcessing.active) {
                bulkProcessing.cancelled = true;
                bulkProcessing.active = false;
                // Change button text to "Close" when cancelled
                $('#cancel-processing').text('Close');
            }
            $('#bulk-processing-modal').hide();

            // Refresh both tabs after modal is closed
            refreshTabsAfterBulkOperation();
        });

        // Close modal on outside click
        $('#bulk-processing-modal').on('click', function (e) {
            if (e.target === this) {
                if (bulkProcessing.active) {
                    bulkProcessing.cancelled = true;
                    bulkProcessing.active = false;
                    // Change button text to "Close" when cancelled
                    $('#cancel-processing').text('Close');
                }
                $(this).hide();

                // Refresh both tabs after modal is closed
                refreshTabsAfterBulkOperation();
            }
        });
    }

    function refreshTabsAfterBulkOperation() {
        // Small delay to ensure modal is fully hidden before refreshing
        setTimeout(() => {
            loadAllImages();
            loadBadNameImages();
        }, 300);
    }

    function setupImageHoverHandlers() {
        // Image hover handlers - show/hide overlay on hover
        $(document).on('mouseenter', 'td div[style*="position: relative"]', function () {
            $(this).find('.image-overlay').css('display', 'flex');
        });

        $(document).on('mouseleave', 'td div[style*="position: relative"]', function () {
            $(this).find('.image-overlay').hide();
        });

        // Image overlay click handler - open image in new tab
        $(document).on('click', '.image-overlay', function (e) {
            e.preventDefault();
            const originalUrl = $(this).data('original-url');
            if (originalUrl) {
                window.open(originalUrl, '_blank');
            }
        });
    }

    function showModalError(message) {
        // Create or update error message in the edit modal
        const modal = $('#edit-filename-modal');
        let errorDiv = modal.find('.modal-error-message');

        if (errorDiv.length === 0) {
            errorDiv = $('<div class="modal-error-message" style="background: #fdeaea; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px;"></div>');
            modal.find('h3').after(errorDiv);
        }

        errorDiv.text(message).show();

        // Hide error message after 5 seconds
        setTimeout(() => {
            errorDiv.fadeOut('slow');
        }, 5000);
    }

    function updateFilterDisplay() {
        const filterIcon = $('#image-type-filter-icon');
        const filterBtn = $('#image-type-filter-btn');
        
        if (allImagesTypeFilter === 'featured') {
            filterIcon.css('color', '#2271b1');
            filterBtn.css({
                'background': '#e7f3ff',
                'border-color': '#2271b1'
            });
        } else {
            filterIcon.css('color', '#787c82');
            filterBtn.css({
                'background': '#fff',
                'border-color': '#ddd'
            });
        }
    }

});
