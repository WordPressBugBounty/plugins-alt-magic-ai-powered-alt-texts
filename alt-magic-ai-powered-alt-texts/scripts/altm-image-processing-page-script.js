jQuery(document).ready(function ($) {
    // Configuration: Get max concurrency from WordPress settings (can be changed in AI Settings page)
    const MAX_CONCURRENCY = parseInt(altmImageProcessing.maxConcurrency) || 5;



    let tabData = {
        'empty-alt': { images: [], currentPage: 1, totalPages: 0, pageSize: 25, searchTerm: '', allImages: [] },
        'short-alt': { images: [], currentPage: 1, totalPages: 0, pageSize: 25, searchTerm: '', allImages: [] },
        'all-images': { images: [], currentPage: 1, totalPages: 0, pageSize: 25, searchTerm: '', allImages: [] }
    };
    let currentCredits = null; // Track current credits

    // Credits functionality
    function fetchAndDisplayCredits() {
        $('.credits-available-text').text('... credits');
        $.post(altmImageProcessing.ajaxUrl, {
            action: 'altm_fetch_user_credits',
            nonce: altmImageProcessing.fetchCreditsNonce
        }, function (response) {
            //console.log('Credits fetch response:', response);

            // Check for authentication errors
            if (response.success === false) {
                // Use the message from backend, or default message
                var errorMessage = response.message || 'Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.';
                displayCreditsError(errorMessage);
                return;
            }

            if (response.credits_available || response.credits_available == 0) {
                currentCredits = parseInt(response.credits_available);
                updateCreditsDisplay(currentCredits);
                // Clear any previous error messages
                clearCreditsError();
            } else {
                console.log('Failed to fetch credits:', response);
                displayCreditsError('Unable to fetch credits. Please try again.');
            }
        }).fail(function (xhr, status, error) {
            console.error('Credits fetch error:', error);
            displayCreditsError('Network error while fetching credits. Please check your connection.');
        });
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
        const accountSettingsUrl = (typeof altmImageProcessing !== 'undefined' && altmImageProcessing.accountSettingsUrl)
            ? altmImageProcessing.accountSettingsUrl
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
            $('.credits-available-text').text(credits + ' credits');

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

    function updateCreditsFromResponse(response) {
        // Handle both response formats: direct response.credits_available and response.data.credits_available
        let credits = null;
        if (response.credits_available || response.credits_available === 0) {
            credits = parseInt(response.credits_available);
        } else if (response.data && (response.data.credits_available || response.data.credits_available === 0)) {
            credits = parseInt(response.data.credits_available);
        }

        if (credits !== null) {
            currentCredits = credits;
            updateCreditsDisplay(currentCredits);

            // Stop bulk processing if credits are 0
            if (currentCredits <= 0 && bulkProcessing.isRunning) {
                bulkProcessing.shouldCancel = true;
                bulkProcessing.stoppedDueToCredits = true;
                // Don't start new requests, but let existing ones complete
                bulkProcessing.queue = [];
                // Processing will finalize naturally when queue is empty
            }
        }
    }

    // Initialize credits display on page load
    fetchAndDisplayCredits();

    // Tab switching functionality
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();

        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');

        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');

        // Show corresponding content
        let tabName = $(this).data('tab');
        $('#tab-content-' + tabName).addClass('active');

        // Load data for the tab if not already loaded
        if (tabData[tabName].images.length === 0) {
            loadTabData(tabName);
        } else {
            // Ensure pagination controls are visible even for cached data
            ensurePaginationControlsVisible(tabName);
            renderCurrentPage(tabName);
            updatePaginationUI(tabName);
        }
    });

    // Page size change handlers
    $('#page-size-empty-alt').on('change', function () {
        handlePageSizeChange('empty-alt', $(this).val());
    });

    $('#page-size-short-alt').on('change', function () {
        handlePageSizeChange('short-alt', $(this).val());
    });

    $('#page-size-all-images').on('change', function () {
        handlePageSizeChange('all-images', $(this).val());
    });

    // Search functionality with debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function handleSearch(tab, searchTerm) {
        // Safety check: ensure allImages exists
        if (!tabData[tab] || !tabData[tab].allImages) {
            return;
        }

        searchTerm = (searchTerm || '').toLowerCase().trim();
        tabData[tab].searchTerm = searchTerm;

        // Filter images based on search term
        if (searchTerm === '') {
            tabData[tab].images = tabData[tab].allImages.slice();
        } else {
            tabData[tab].images = tabData[tab].allImages.filter(function (image) {
                const id = (image.ID || '').toString();
                const altText = (image.alt_text || '').toLowerCase();
                return id.includes(searchTerm) || altText.includes(searchTerm);
            });
        }

        // Reset to first page and recalculate pagination
        tabData[tab].currentPage = 1;
        tabData[tab].totalPages = Math.ceil(tabData[tab].images.length / tabData[tab].pageSize);

        // Validate and fix current page
        validateAndFixCurrentPage(tab);

        // Re-render the list
        renderCurrentPage(tab);
        updatePaginationUI(tab);

        // Update selection UI
        updateSelectionUI(tab);
    }

    // Search input handlers with debounce
    const debouncedSearchEmptyAlt = debounce(function (searchValue) {
        handleSearch('empty-alt', searchValue);
    }, 300);

    const debouncedSearchShortAlt = debounce(function (searchValue) {
        handleSearch('short-alt', searchValue);
    }, 300);

    const debouncedSearchAllImages = debounce(function (searchValue) {
        handleSearch('all-images', searchValue);
    }, 300);

    $('#search-empty-alt').on('input', function () {
        debouncedSearchEmptyAlt($(this).val());
    });

    $('#search-short-alt').on('input', function () {
        debouncedSearchShortAlt($(this).val());
    });

    $('#search-all-images').on('input', function () {
        debouncedSearchAllImages($(this).val());
    });





    // Load initial tab data
    loadTabData('empty-alt');



    // Selection functionality
    function updateSelectionUI(tab) {
        let listId = '#' + tab + '-images-list';
        if (tab === 'empty-alt') listId = '#empty-alt-images-list';
        if (tab === 'short-alt') listId = '#short-alt-images-list';
        if (tab === 'all-images') listId = '#all-images-list';
        let selectAllId = '#select-all-' + tab;

        let totalCheckboxes = $(listId + ' .select-image').length;
        let checkedCheckboxes = $(listId + ' .select-image:checked').length;

        // Update select all checkbox
        if (checkedCheckboxes === 0) {
            $(selectAllId).prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $(selectAllId).prop('indeterminate', false).prop('checked', true);
        } else {
            $(selectAllId).prop('indeterminate', true);
        }

        // Update button states and counts
        $('#bulk-generate-selected-' + tab + ' .selected-count').text(checkedCheckboxes);
        $('#bulk-generate-selected-' + tab).prop('disabled', checkedCheckboxes === 0);

        // Update row styling
        $(listId + ' .select-image').each(function () {
            if ($(this).is(':checked')) {
                $(this).closest('tr').addClass('selected-row');
            } else {
                $(this).closest('tr').removeClass('selected-row');
            }
        });
    }

    // Select all functionality
    $(document).on('change', '#select-all-empty-alt, #select-all-short-alt, #select-all-all-images', function () {
        let tab = $(this).attr('id').replace('select-all-', '');
        let listId = '#' + tab + '-images-list';
        if (tab === 'empty-alt') listId = '#empty-alt-images-list';
        if (tab === 'short-alt') listId = '#short-alt-images-list';
        if (tab === 'all-images') listId = '#all-images-list';
        let isChecked = $(this).is(':checked');

        $(listId + ' .select-image').prop('checked', isChecked);
        updateSelectionUI(tab);
    });

    // Shift-click range selection support
    let lastChecked = {
        'empty-alt': null,
        'short-alt': null,
        'all-images': null
    };

    $(document).on('click', '.select-image', function (event) {
        let tab = $(this).closest('.tab-content').attr('id').replace('tab-content-', '');
        const $checkboxes = $('#tab-content-' + tab + ' .select-image');

        if (event.shiftKey && lastChecked[tab]) {
            const start = $checkboxes.index(this);
            const end = $checkboxes.index(lastChecked[tab]);
            if (start !== -1 && end !== -1) {
                const [from, to] = start < end ? [start, end] : [end, start];
                const shouldCheck = $(this).is(':checked');
                $checkboxes.slice(from, to + 1).prop('checked', shouldCheck);
            }
        }

        lastChecked[tab] = this;
        updateSelectionUI(tab);
    });

    // Individual checkbox fallback (non-shift interactions)
    $(document).on('change', '.select-image', function () {
        let tab = $(this).closest('.tab-content').attr('id').replace('tab-content-', '');
        updateSelectionUI(tab);
    });

    function renderCurrentPage(tab) {
        let listId = '#' + tab + '-images-list';
        if (tab === 'empty-alt') listId = '#empty-alt-images-list';
        if (tab === 'short-alt') listId = '#short-alt-images-list';
        if (tab === 'all-images') listId = '#all-images-list';


        let list = $(listId);
        list.empty();

        let data = tabData[tab];
        let images = data.images;

        // Show placeholder if no images
        if (images.length === 0) {
            let placeholderHtml = getPlaceholderHtml(tab);
            list.html('<tr><td colspan="5" style="text-align: center; padding: 40px;">' + placeholderHtml + '</td></tr>');
            updateSelectionUI(tab);

            // Show pagination controls even when no images
            $('#tab-content-' + tab + ' .altm-pagination-controls').show();
            return;
        }

        // Always ensure pagination controls are visible when there are images
        $('#tab-content-' + tab + ' .altm-pagination-controls').show();

        const startIndex = (data.currentPage - 1) * data.pageSize;
        const endIndex = Math.min(startIndex + data.pageSize, images.length);

        for (let i = startIndex; i < endIndex; i++) {
            const image = images[i];
            let altTextDisplay = image.alt_text ? image.alt_text : '<span style="color: #ff9999; font-style: italic;">Empty</span>';
            let row = '<tr>' +
                '<td><input type="checkbox" class="select-image" data-id="' + image.ID + '" /></td>' +
                '<td>' + image.ID + '</td>' +
                '<td style="padding-right: 20px;"><img src="' + image.image_url + '" style="height: 100px; width: 100px; max-width: 100px; border-radius: 4px; object-fit: cover;" loading="lazy" onerror="this.onerror=null; this.outerHTML=\'<div style=\\\'height: 100px; width: 100px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; color: #666; font-size: 12px;\\\'>Not Available</div>\';" /></td>' +
                '<td class="altm-alt-text-cell" data-alt-text="' + (image.alt_text ? image.alt_text.replace(/"/g, '&quot;') : '') + '" style="padding-left: 20px; padding-right: 20px;">' +
                '<div style="padding: 8px 10px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background: linear-gradient(135deg, #f9f9f9 0%, #e8e8e8 100%); word-wrap: break-word; line-height: 1.4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">' + altTextDisplay + '</div>' +
                '</td>' +
                '<td class="altm-actions-cell">' +
                '<div class="altm-button-group">' +
                '<button class="button generate-alt-text" data-id="' + image.ID + '">Generate alt text</button> ' +
                '<button class="button altm-edit-alt-text" data-id="' + image.ID + '">Edit</button>' +
                '</div>' +
                '</td>' +
                '</tr>';
            list.append(row);
        }

        // Update selection UI after rendering
        updateSelectionUI(tab);
    }

    function getPlaceholderHtml(tab) {
        switch (tab) {
            case 'empty-alt':
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">🎉</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">Great job! All images have alt text</h3>' +
                    '<p style="margin: 0 0 20px 0;">There are currently no images missing alt text in your media library.</p>' +
                    '</div>';

            case 'short-alt':
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">✨</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">Excellent! No short alt texts found</h3>' +
                    '<p style="margin: 0 0 20px 0;">All your images have well-optimized alt text.</p>' +
                    '</div>';

            case 'all-images':
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">📂</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">No images found</h3>' +
                    '<p style="margin: 0 0 20px 0;">There are currently no images in your media library.</p>' +
                    '</div>';

            default:
                return '<div style="color: #666; line-height: 1.6;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px;">📂</div>' +
                    '<h3 style="margin: 0 0 12px 0; color: #1d2327;">No images found</h3>' +
                    '<p style="margin: 0;">No images available in this category.</p>' +
                    '</div>';
        }
    }

    function updatePaginationUI(tab) {
        let container = $('#tab-content-' + tab + ' .altm-pagination-container');
        let data = tabData[tab];

        // Don't show pagination if there's only 1 page or no pages
        if (data.totalPages <= 1) {
            // Hide pagination numbers but keep page size selector
            container.find('.altm-pagination').remove();

            // Show simple page info when there's only 1 page
            if (data.images.length > 0) {
                let pageInfoHtml = '<div class="altm-pagination">';
                pageInfoHtml += '<div class="altm-pagination-info">Showing 1 to ' + data.images.length + ' of ' + data.images.length + ' images</div>';
                pageInfoHtml += '</div>';

                container.find('.altm-pagination').remove();
                container.find('.altm-pagination-controls').append(pageInfoHtml);
            }
            return;
        }

        let paginationHtml = '<div class="altm-pagination">';

        // Page info
        const startIndex = (data.currentPage - 1) * data.pageSize + 1;
        const endIndex = Math.min(data.currentPage * data.pageSize, data.images.length);
        paginationHtml += '<div class="altm-pagination-info">Showing ' + startIndex + ' to ' + endIndex + ' of ' + data.images.length + ' images</div>';

        // Page numbers
        paginationHtml += '<div class="altm-pagination-numbers">';

        // Calculate range of page numbers to show
        let startPage = Math.max(1, data.currentPage - 2);
        let endPage = Math.min(data.totalPages, data.currentPage + 2);

        // Always show first page if not in range
        if (startPage > 1) {
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="1">1</button>';
            if (startPage > 2) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
        }

        // Show page numbers in range
        for (let i = startPage; i <= endPage; i++) {
            let activeClass = i === data.currentPage ? ' altm-pagination-page-active' : '';
            paginationHtml += '<button type="button" class="button altm-pagination-page' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        // Always show last page if not in range
        if (endPage < data.totalPages) {
            if (endPage < data.totalPages - 1) {
                paginationHtml += '<span class="altm-pagination-ellipsis">...</span>';
            }
            paginationHtml += '<button type="button" class="button altm-pagination-page" data-page="' + data.totalPages + '">' + data.totalPages + '</button>';
        }

        paginationHtml += '</div>';
        paginationHtml += '</div>';

        // Update only the pagination section, not the entire controls section
        container.find('.altm-pagination').remove();
        container.find('.altm-pagination-controls').append(paginationHtml);

        // Add event listeners for pagination buttons
        addPaginationEventListeners(tab);
    }

    function addPaginationEventListeners(tab) {
        let container = '#tab-content-' + tab + ' .altm-pagination-container';

        // Page number buttons
        $(container + ' .altm-pagination-page').on('click', function () {
            let page = parseInt($(this).data('page'));
            goToPage(tab, page);
        });
    }

    function goToPage(tab, page) {
        // Validate page number
        if (page < 1 || page > tabData[tab].totalPages) {
            console.warn('Invalid page number:', page, 'for tab:', tab, 'Total pages:', tabData[tab].totalPages);
            return;
        }

        tabData[tab].currentPage = page;
        renderCurrentPage(tab);
        updatePaginationUI(tab);
    }

    function validateAndFixCurrentPage(tab) {
        let data = tabData[tab];

        // Ensure current page is within valid bounds
        if (data.currentPage < 1) {
            data.currentPage = 1;
        }

        if (data.currentPage > data.totalPages) {
            data.currentPage = data.totalPages;
        }

        // If total pages is 0, set current page to 1
        if (data.totalPages === 0) {
            data.currentPage = 1;
        }
    }

    function resolvePageSize(tab, newPageSize) {
        if (newPageSize === 'all') {
            const total = tabData[tab].images.length || tabData[tab].allImages.length || 1;
            return Math.max(1, total);
        }
        const parsed = parseInt(newPageSize, 10);
        return isNaN(parsed) ? 25 : parsed;
    }

    function handlePageSizeChange(tab, newPageSize) {
        tabData[tab].pageSize = resolvePageSize(tab, newPageSize);
        tabData[tab].currentPage = 1; // Reset to first page

        // Recalculate total pages based on new page size
        tabData[tab].totalPages = Math.ceil((tabData[tab].images.length || 0) / tabData[tab].pageSize || 1);

        // Validate and fix current page
        validateAndFixCurrentPage(tab);

        renderCurrentPage(tab);
        updatePaginationUI(tab);
    }

    function ensurePaginationControlsVisible(tab) {
        let container = $('#tab-content-' + tab + ' .altm-pagination-container');
        let controls = container.find('.altm-pagination-controls');

        // If controls don't exist, create them
        if (controls.length === 0) {
            let controlsHtml = '<div class="altm-pagination-controls">';
            controlsHtml += '<div class="page-size-container">';
            controlsHtml += '<label for="page-size-' + tab + '">Images per page:</label>';
            controlsHtml += '<select id="page-size-' + tab + '">';
            controlsHtml += '<option value="10">10</option>';
            controlsHtml += '<option value="25" selected>25</option>';
            controlsHtml += '<option value="50">50</option>';
            controlsHtml += '<option value="100">100</option>';
            controlsHtml += '<option value="500">500</option>';
            controlsHtml += '<option value="all">All images</option>';
            controlsHtml += '</select>';
            controlsHtml += '</div>';
            controlsHtml += '</div>';

            container.html(controlsHtml);

            // Re-attach event handler for the new page size selector
            $('#page-size-' + tab).on('change', function () {
                handlePageSizeChange(tab, $(this).val());
            });
        }

        // Ensure page size selector exists for all-images tab
        if (tab === 'all-images' && $('#page-size-all-images').length === 0) {
            ensurePaginationControlsVisible('all-images');
        }

        // Ensure controls are visible
        controls.show();
    }

    function loadTabData(tab) {
        let action = '';

        // Show a loading spinner row in the correct table body while fetching images,
        // similar to the image renaming page UI.
        (function showLoadingRowForTab(currentTab) {
            let listSelector = '';
            if (currentTab === 'empty-alt') listSelector = '#empty-alt-images-list';
            if (currentTab === 'short-alt') listSelector = '#short-alt-images-list';
            if (currentTab === 'all-images') listSelector = '#all-images-list';

            if (!listSelector) {
                return;
            }

            const $list = jQuery(listSelector);
            $list.html(
                '<tr>' +
                '<td colspan="5" style="text-align: center; padding: 40px;">' +
                '<span class="spinner is-active" style="float: none; margin-right: 8px;"></span>' +
                '<span style="color: #666;">Loading images...</span>' +
                '</td>' +
                '</tr>'
            );
        })(tab);

        switch (tab) {
            case 'empty-alt':
                action = 'altm_get_images_with_empty_alt_text';
                break;
            case 'short-alt':
                action = 'altm_get_images_with_short_alt_text';
                break;
            case 'all-images':
                action = 'altm_get_all_images';
                break;
        }

        if (action) {
            $.post(ajaxurl, { action: action }, function (response) {
                if (response.success) {
                    let images = response.data;
                    tabData[tab].allImages = images;
                    tabData[tab].images = images;
                    tabData[tab].totalPages = Math.ceil(images.length / tabData[tab].pageSize);
                    tabData[tab].currentPage = 1;

                    // Validate and fix current page
                    validateAndFixCurrentPage(tab);

                    if (tab === 'empty-alt') {
                        $('#empty-alt-count').text(images.length);
                        $('#bulk-generate-all-empty-alt .total-count').text(images.length);
                    }
                    if (tab === 'short-alt') {
                        $('#short-alt-count').text(images.length);
                        $('#bulk-generate-all-short-alt .total-count').text(images.length);
                    }
                    if (tab === 'all-images') {
                        $('#all-images-count').text(images.length);
                        $('#bulk-generate-all-all-images .total-count').text(images.length);
                    }

                    // Ensure pagination controls are visible and properly initialized
                    ensurePaginationControlsVisible(tab);

                    renderCurrentPage(tab);
                    updatePaginationUI(tab);

                    // Ensure pagination controls are visible
                    $('#tab-content-' + tab + ' .altm-pagination-controls').show();
                } else {
                    // On error, replace spinner row with an error message row
                    let listSelector = '';
                    if (tab === 'empty-alt') listSelector = '#empty-alt-images-list';
                    if (tab === 'short-alt') listSelector = '#short-alt-images-list';
                    if (tab === 'all-images') listSelector = '#all-images-list';

                    if (listSelector) {
                        const $list = jQuery(listSelector);
                        $list.html(
                            '<tr>' +
                            '<td colspan="5" style="text-align: center; padding: 40px;">' +
                            '<div style="color: #d63638; line-height: 1.6;">' +
                            '<div style="font-size: 48px; margin-bottom: 16px;">⚠️ </div>' +
                            '<h3 style="margin: 0 0 12px 0; color: #d63638;">Error loading images</h3>' +
                            '<p style="margin: 0;">' + (response.data || 'Unknown error occurred. Please try again.') + '</p>' +
                            '</div>' +
                            '</td>' +
                            '</tr>'
                        );
                    }
                }
            }).fail(function () {
                // Handle network / AJAX failures gracefully
                let listSelector = '';
                if (tab === 'empty-alt') listSelector = '#empty-alt-images-list';
                if (tab === 'short-alt') listSelector = '#short-alt-images-list';
                if (tab === 'all-images') listSelector = '#all-images-list';

                if (listSelector) {
                    const $list = jQuery(listSelector);
                    $list.html(
                        '<tr>' +
                        '<td colspan="5" style="text-align: center; padding: 40px;">' +
                        '<div style="color: #d63638; line-height: 1.6;">' +
                        '<div style="font-size: 48px; margin-bottom: 16px;">🚫</div>' +
                        '<h3 style="margin: 0 0 12px 0; color: #d63638;">Connection Error</h3>' +
                        '<p style="margin: 0;">Unable to load images. Please check your connection and try again.</p>' +
                        '</div>' +
                        '</td>' +
                        '</tr>'
                    );
                }
            });
        }
    }

    // Bulk processing variables
    let bulkProcessing = {
        isRunning: false,
        shouldCancel: false,
        totalItems: 0,
        processedCount: 0,
        successCount: 0,
        failedCount: 0,
        items: [],
        queue: [],
        stoppedDueToCredits: false
    };

    // Modal functions
    function showProcessingModal() {
        $('#bulk-processing-modal').show();
        $('#progress-spinner').show();
    }

    function hideProcessingModal() {
        $('#bulk-processing-modal').hide();
        resetProcessingState();
    }

    function resetProcessingState() {
        bulkProcessing = {
            isRunning: false,
            shouldCancel: false,
            totalItems: 0,
            processedCount: 0,
            successCount: 0,
            failedCount: 0,
            items: [],
            queue: [],
            activeRequests: 0,
            maxConcurrency: MAX_CONCURRENCY,
            stoppedDueToCredits: false
        };

        // Reset modal content
        $('#progress-text').text('0 of 0');
        $('#progress-bar-fill').css('width', '0%');
        $('#progress-percentage').text('0%');
        $('#success-count').text('0');
        $('#failed-count').text('0');
        $('#failed-images-body').empty();
        $('#no-failed-images').show();

        // Show spinner and hide completion messages
        $('#progress-spinner').show();
        $('#completion-message').hide();
        $('#credits-depleted-message').hide();

        // Reset button text
        $('#cancel-processing').text('Cancel Processing');
    }

    function updateProgress() {
        let percentage = Math.round((bulkProcessing.processedCount / bulkProcessing.totalItems) * 100);
        $('#progress-text').text(bulkProcessing.processedCount + ' of ' + bulkProcessing.totalItems);
        $('#progress-bar-fill').css('width', percentage + '%');
        $('#progress-percentage').text(percentage + '%');
        $('#success-count').text(bulkProcessing.successCount);
        $('#failed-count').text(bulkProcessing.failedCount);
    }

    function addFailedImage(imageId, message = '') {
        // Hide the "no failed images" message
        $('#no-failed-images').hide();

        // Get media library edit URL for the image
        let editUrl = '/wp-admin/upload.php?item=' + imageId;

        // Create table row for failed image
        let row = $('<tr style="border-bottom: 1px solid #eee;">' +
            '<td style="padding: 6px 8px;">' + imageId + '</td>' +
            '<td style="padding: 6px 8px;"><a href="' + editUrl + '" target="_blank" style="color: #2271b1; text-decoration: underline;">Image Link</a></td>' +
            '<td style="padding: 6px 8px; color: #c53030; font-size: 11px;">' + (message || 'Unable to process') + '</td>' +
            '</tr>');

        $('#failed-images-body').append(row);

        // Scroll to bottom to show newest failed image
        $('#failed-images-table').parent().scrollTop($('#failed-images-table').parent()[0].scrollHeight);
    }

    // Process images in batches for real-time progress updates
    async function processAllImages() {
        if (bulkProcessing.queue.length === 0) {
            finalizeBulkProcessing();
            return;
        }

        // Get batch size from WordPress settings
        const batchSize = parseInt(altmImageProcessing.maxConcurrency) || 3;
        const totalImages = bulkProcessing.queue.length;



        // Process images in batches
        for (let i = 0; i < totalImages; i += batchSize) {
            if (bulkProcessing.shouldCancel) {

                break;
            }

            const batch = bulkProcessing.queue.slice(i, i + batchSize);
            const batchAttachmentIds = batch.map(item => item.id);



            try {
                const response = await $.post(altmImageProcessing.ajaxUrl, {
                    action: 'altm_generate_alt_text_batch_ajax',
                    attachment_ids: batchAttachmentIds,
                    nonce: altmImageProcessing.generateAltTextNonce
                });

                if (response.success && response.data) {
                    // Process each result from the batch
                    const activeTab = $('.tab-content.active').attr('id').replace('tab-content-', '');
                    let hasCreditsError = false;

                    Object.keys(response.data).forEach(attachmentId => {
                        const result = response.data[attachmentId];
                        const imageId = parseInt(attachmentId);

                        if (result.success && result.alt_text) {
                            bulkProcessing.successCount++;

                            // Update the data in memory (both filtered and all images)
                            let image = tabData[activeTab].images.find(img => img.ID == imageId);
                            if (image) {
                                image.alt_text = result.alt_text;
                            }
                            let allImage = tabData[activeTab].allImages.find(img => img.ID == imageId);
                            if (allImage) {
                                allImage.alt_text = result.alt_text;
                            }

                            // Update credits from response (use the last valid credits count)
                            if (result.credits_available !== null) {
                                updateCreditsFromResponse({
                                    data: { credits_available: result.credits_available }
                                });
                            }
                        } else {
                            bulkProcessing.failedCount++;

                            // Check if failure is due to insufficient credits (not server errors like 503/timeout)
                            const errorMessage = result.message || 'Unable to process';
                            const isCreditsError = errorMessage.toLowerCase().includes('credit') ||
                                errorMessage.toLowerCase().includes('insufficient') ||
                                (typeof result.credits_available === 'number' && result.credits_available <= 0);
                            if (isCreditsError) {
                                bulkProcessing.stoppedDueToCredits = true;
                                bulkProcessing.shouldCancel = true;
                                hasCreditsError = true;
                            }

                            addFailedImage(imageId, errorMessage);
                        }

                        bulkProcessing.processedCount++;
                        updateProgress();
                    });

                    // Break the loop if any image failed due to credits
                    if (hasCreditsError) {
                        break;
                    }



                } else {
                    // Batch request failed, mark all images in this batch as failed
                    let errorMessage = 'Batch request failed';
                    let shouldStopProcessing = false;

                    // Check if it's an insufficient credits error
                    if (response.data) {
                        if (response.data.error) {
                            errorMessage = response.data.error;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        }

                        // Detect credit-related errors only (not server/timeout errors like 503)
                        const hasCreditsFields = typeof response.data.credits_available === 'number' && typeof response.data.credits_required === 'number';
                        const isCreditsError = (errorMessage.toLowerCase().includes('credit') ||
                            errorMessage.toLowerCase().includes('insufficient') ||
                            hasCreditsFields) && !errorMessage.toLowerCase().includes('temporarily unavailable') && !errorMessage.toLowerCase().includes('timeout');
                        if (isCreditsError) {
                            bulkProcessing.stoppedDueToCredits = true;
                            bulkProcessing.shouldCancel = true;
                            shouldStopProcessing = true;

                            // Add credit information to error message if available
                            if (hasCreditsFields) {
                                errorMessage = `Insufficient credits for this batch (Available: ${response.data.credits_available}, Required: ${response.data.credits_required})`;
                            }
                        }
                    }

                    batch.forEach(item => {
                        bulkProcessing.failedCount++;
                        bulkProcessing.processedCount++;
                        addFailedImage(item.id, errorMessage);
                        updateProgress();
                    });

                    // Break the loop if credits are insufficient
                    if (shouldStopProcessing) {
                        break;
                    }
                }
            } catch (error) {
                // Network error, mark all images in this batch as failed
                batch.forEach(item => {
                    bulkProcessing.failedCount++;
                    bulkProcessing.processedCount++;
                    addFailedImage(item.id, 'Request failed: ' + error.message);
                    updateProgress();
                });
            }

            // Small delay between batches to show progress
            if (i + batchSize < totalImages && !bulkProcessing.shouldCancel) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            // If we're stopping due to credits, mark remaining images as not processed
            if (bulkProcessing.shouldCancel && bulkProcessing.stoppedDueToCredits) {
                const processedSoFar = i + batchSize;
                if (processedSoFar < totalImages) {
                    // Mark remaining images as failed due to insufficient credits
                    for (let j = processedSoFar; j < totalImages; j++) {
                        const remainingItem = bulkProcessing.queue[j];
                        if (remainingItem) {
                            bulkProcessing.failedCount++;
                            bulkProcessing.processedCount++;
                            addFailedImage(remainingItem.id, 'Not processed - insufficient credits');
                            updateProgress();
                        }
                    }
                }
                break; // Exit the loop
            }
        }

        // Clear the queue and finalize
        bulkProcessing.queue = [];
        finalizeBulkProcessing();
    }

    function finalizeBulkProcessing() {
        // Calculate total processing time
        const totalTime = performance.now() - bulkProcessing.startTime;
        const seconds = (totalTime / 1000).toFixed(1);
        const minutes = (totalTime / 60000).toFixed(1);

        console.log(`🏁 Bulk processing completed in ${seconds}s (${minutes}min) | Processed: ${bulkProcessing.processedCount} images | Success: ${bulkProcessing.successCount} | Failed: ${bulkProcessing.failedCount}`);

        // Processing complete or cancelled
        bulkProcessing.isRunning = false;
        bulkProcessing.shouldCancel = true; // Ensure no new requests start

        // Hide spinner
        $('#progress-spinner').hide();

        // Show appropriate completion message based on how it stopped
        if (bulkProcessing.stoppedDueToCredits) {
            // Show credits depleted message
            $('#completion-message').hide();
            $('#credits-depleted-message').show();
        } else {
            // Show normal completion message
            $('#completion-message').show();
            $('#credits-depleted-message').hide();
        }

        // Change button text to "Done" when complete
        $('#cancel-processing').text('Done');

        // Clear any remaining queue
        bulkProcessing.queue = [];

        // Refresh the current page to show updates
        let activeTab = $('.tab-content.active').attr('id').replace('tab-content-', '');
        renderCurrentPage(activeTab);
    }

    function startBulkProcessing(items) {
        if (bulkProcessing.isRunning) {
            alert('Bulk processing is already running.');
            return;
        }

        // Check if API key exists before starting
        if (!altmImageProcessing.hasApiKey) {
            showAuthErrorModal('No Alt Magic account found. Please connect your account in Account Settings.');
            return;
        }

        // Check if we have credits before starting
        if (currentCredits !== null && currentCredits <= 0) {
            showNoCreditsModal('You don\'t have enough credits to start bulk processing. Please purchase more credits to continue.');
            return;
        }

        bulkProcessing.isRunning = true;
        bulkProcessing.shouldCancel = false;
        bulkProcessing.stoppedDueToCredits = false;
        bulkProcessing.totalItems = items.length;
        bulkProcessing.processedCount = 0;
        bulkProcessing.successCount = 0;
        bulkProcessing.failedCount = 0;
        bulkProcessing.items = items;
        bulkProcessing.queue = [...items]; // Copy items to queue
        bulkProcessing.startTime = performance.now();

        showProcessingModal();
        updateProgress();

        // Start processing all images in one batch request
        processAllImages();
    }

    // Load initial data for all tabs
    loadTabData('empty-alt');
    loadTabData('short-alt');
    loadTabData('all-images');


    $('#altm-image-processing-tabs a').on('click', function (e) {
        e.preventDefault();
        $('#altm-image-processing-tabs a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        let newTab = $(this).data('tab');
        $('#tab-content-' + newTab).addClass('active');

        // Reset selections when switching tabs
        updateSelectionUI(newTab);
    });

    $(document).on('click', '.altm-pagination-prev', function () {
        let tab = $(this).closest('.tab-content').attr('id').replace('tab-content-', '');
        if (tabData[tab].currentPage > 1) {
            tabData[tab].currentPage--;
            renderCurrentPage(tab);
            updatePaginationUI(tab);
        }
    });

    $(document).on('click', '.altm-pagination-next', function () {
        let tab = $(this).closest('.tab-content').attr('id').replace('tab-content-', '');
        if (tabData[tab].currentPage < tabData[tab].totalPages) {
            tabData[tab].currentPage++;
            renderCurrentPage(tab);
            updatePaginationUI(tab);
        }
    });

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

    // No credits modal functions
    function showNoCreditsModal(customMessage) {
        // Remove any existing modal first
        $('#altm-no-credits-modal').remove();

        // Get user email from WordPress localized data or fallback
        const userEmail = altmImageProcessing.userEmail || '';
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

    $(document).on('click', '.generate-alt-text', function () {
        let button = $(this);
        let attachmentId = button.data('id');

        // Remove any existing error message from the actions cell
        button.closest('.altm-actions-cell').find('.altm-error-message').remove();

        // Check if API key exists before making request
        if (!altmImageProcessing.hasApiKey) {
            showAuthErrorModal('No Alt Magic account found. Please connect your account in Account Settings.');
            return;
        }

        // Check if we have credits before starting
        if (currentCredits !== null && currentCredits <= 0) {
            showNoCreditsModal('You don\'t have enough credits to generate alt text. Please purchase more credits to continue.');
            return;
        }

        button.text('Generating...').prop('disabled', true);

        $.post(ajaxurl, {
            action: 'altm_generate_alt_text_ajax',
            attachment_id: attachmentId,
            source: 'image_processing_page',
            nonce: altmImageProcessing.generateAltTextNonce
        }, function (response) {
            //console.log('Response received:', response);

            // Check for authentication errors first
            if (response.success === false && response.status_code === 403) {
                // Check if it's an authentication error
                button.text('Generate alt text').prop('disabled', false);
                showAuthErrorModal('Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.');
                return;
            }

            if (response.success) {
                // Check if alt_text is actually generated
                if (response.data && response.data.alt_text && response.data.alt_text !== null) {
                    const altTextCell = button.closest('tr').find('.altm-alt-text-cell');
                    altTextCell.data('alt-text', response.data.alt_text);
                    altTextCell.html('<div style="padding: 8px 10px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background: linear-gradient(135deg, #f9f9f9 0%, #e8e8e8 100%); word-wrap: break-word; line-height: 1.4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">' + response.data.alt_text + '</div>');
                    // Also update the alt text in the source data to persist across pagination
                    let tab = button.closest('.tab-content').attr('id').replace('tab-content-', '');
                    let image = tabData[tab].images.find(img => img.ID == attachmentId);
                    if (image) {
                        image.alt_text = response.data.alt_text;
                    }
                    let allImage = tabData[tab].allImages.find(img => img.ID == attachmentId);
                    if (allImage) {
                        allImage.alt_text = response.data.alt_text;
                    }
                    // Update credits from response
                    updateCreditsFromResponse(response);
                    button.text('Generate alt text').prop('disabled', false);
                } else {
                    // Handle null alt_text as an error
                    console.log('Alt text is null');
                    button.text('Generate alt text').prop('disabled', false);

                    let errorHtml = '<div class="altm-error-message" style="color: #d63638; font-size: 11px; margin-top: 8px; line-height: 1.2; display: block;">Error: Unable to process the image.</div>';
                    button.closest('.altm-actions-cell').append(errorHtml);

                    setTimeout(function () {
                        button.closest('.altm-actions-cell').find('.altm-error-message').fadeOut('slow', function () {
                            $(this).remove();
                        });
                    }, 5000);
                }
            } else {
                //console.log('Error response:', response);
                button.text('Generate alt text').prop('disabled', false);

                let errorHtml = '<div class="altm-error-message" style="color: #d63638; font-size: 11px; margin-top: 8px; line-height: 1.2; display: block;">Error: Unable to process the image.</div>';
                button.closest('.altm-actions-cell').append(errorHtml);

                setTimeout(function () {
                    button.closest('.altm-actions-cell').find('.altm-error-message').fadeOut('slow', function () {
                        $(this).remove();
                    });
                }, 5000);
            }
        }).fail(function (xhr, status, error) {
            console.log('AJAX fail:', xhr, status, error);
            button.text('Generate alt text').prop('disabled', false);

            let errorHtml = '<div class="altm-error-message" style="color: #d63638; font-size: 11px; margin-top: 8px; line-height: 1.2; display: block;">Error: Unable to process the image.</div>';
            button.closest('.altm-actions-cell').append(errorHtml);

            setTimeout(function () {
                button.closest('.altm-actions-cell').find('.altm-error-message').fadeOut('slow', function () {
                    $(this).remove();
                });
            }, 5000);
        });
    });

    $('#bulk-generate-selected-empty-alt').on('click', function () {
        let selectedCheckboxes = $('#empty-alt-images-list .select-image:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one image to generate.');
            return;
        }

        let selectedItems = [];
        selectedCheckboxes.each(function () {
            selectedItems.push({ id: $(this).data('id') });
        });

        startBulkProcessing(selectedItems);
    });

    $('#bulk-generate-all-empty-alt').on('click', function () {
        let allItems = tabData['empty-alt'].images.map(img => ({ id: img.ID }));
        if (allItems.length === 0) {
            alert('No images found in this tab.');
            return;
        }
        startBulkProcessing(allItems);
    });

    $('#bulk-generate-selected-short-alt').on('click', function () {
        let selectedCheckboxes = $('#short-alt-images-list .select-image:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one image to generate.');
            return;
        }

        let selectedItems = [];
        selectedCheckboxes.each(function () {
            selectedItems.push({ id: $(this).data('id') });
        });

        startBulkProcessing(selectedItems);
    });

    $('#bulk-generate-all-short-alt').on('click', function () {
        let allItems = tabData['short-alt'].images.map(img => ({ id: img.ID }));
        if (allItems.length === 0) {
            alert('No images found in this tab.');
            return;
        }
        startBulkProcessing(allItems);
    });

    $('#bulk-generate-selected-all-images').on('click', function () {
        let selectedCheckboxes = $('#all-images-list .select-image:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one image to generate.');
            return;
        }

        let selectedItems = [];
        selectedCheckboxes.each(function () {
            selectedItems.push({ id: $(this).data('id') });
        });

        startBulkProcessing(selectedItems);
    });

    $('#bulk-generate-all-all-images').on('click', function () {
        let allItems = tabData['all-images'].images.map(img => ({ id: img.ID }));
        if (allItems.length === 0) {
            alert('No images found in this tab.');
            return;
        }
        startBulkProcessing(allItems);
    });

    // Modal event handlers
    $('#close-modal').on('click', function () {
        if (bulkProcessing.isRunning) {
            if (confirm('Are you sure you want to cancel the bulk processing?')) {
                bulkProcessing.shouldCancel = true;
                hideProcessingModal();
            }
        } else {
            hideProcessingModal();
        }
    });

    $('#cancel-processing').on('click', function () {
        if ($(this).text() === 'Done') {
            // If processing is complete, just close the modal
            hideProcessingModal();
        } else {
            // If processing is still running, ask for confirmation
            if (confirm('Are you sure you want to cancel the bulk processing?')) {
                bulkProcessing.shouldCancel = true;
                hideProcessingModal();
            }
        }
    });

    // Prevent modal from closing when clicking outside
    $('#bulk-processing-modal').on('click', function (e) {
        if (e.target === this) {
            // Don't close modal when clicking outside during processing
            if (bulkProcessing.isRunning) {
                return false;
            }
        }
    });

    function bulkGenerate(listId) {
        // This function is now deprecated, but keeping it for backwards compatibility
        console.log('bulkGenerate function called - this is deprecated');
    }

    // Edit Alt Text Modal functionality
    $(document).on('click', '.altm-edit-alt-text', function () {
        const imageId = $(this).data('id');
        const row = $(this).closest('tr');
        const altTextCell = row.find('.altm-alt-text-cell');
        const currentAltText = altTextCell.data('alt-text') || '';

        // Get the image URL from the row
        const imageElement = row.find('img');
        const imageUrl = imageElement.attr('src') || '';

        // Show the modal
        $('#altm-edit-alt-modal').remove(); // Remove any existing modal

        const modalHtml = `
            <div id="altm-edit-alt-modal" class="altm-modal">
                <div class="altm-modal-content">
                    <div class="altm-modal-header">
                        <h2>Edit Alt Text <span style="font-size: 14px; color: #666; font-weight: normal;">(ID: ${imageId})</span></h2>
                        <span class="altm-modal-close">&times;</span>
                    </div>
                    <div class="altm-modal-body">
                        <div class="altm-modal-image-preview">
                            <img src="${imageUrl}" alt="Image Preview" style="max-width: 100%; max-height: 150px; border-radius: 4px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        </div>
                        <label for="altm-edit-alt-textarea">Alt Text:</label>
                        <textarea id="altm-edit-alt-textarea" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${currentAltText}</textarea>
                        <input type="hidden" id="altm-edit-image-id" value="${imageId}">
                    </div>
                    <div class="altm-modal-footer">
                        <button class="button button-primary" id="altm-save-alt-text">Save</button>
                        <button class="button" id="altm-cancel-edit">Cancel</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        $('#altm-edit-alt-modal').fadeIn(200);
        $('#altm-edit-alt-textarea').focus();
    });

    // Close modal on X button or Cancel
    $(document).on('click', '.altm-modal-close, #altm-cancel-edit', function () {
        $('#altm-edit-alt-modal').fadeOut(200, function () {
            $(this).remove();
        });
    });

    // Close modal when clicking outside
    $(document).on('click', '#altm-edit-alt-modal', function (e) {
        if (e.target === this) {
            $(this).fadeOut(200, function () {
                $(this).remove();
            });
        }
    });

    // Save alt text
    $(document).on('click', '#altm-save-alt-text', function () {
        const imageId = $('#altm-edit-image-id').val();
        const newAltText = $('#altm-edit-alt-textarea').val();
        const button = $(this);

        button.text('Saving...').prop('disabled', true);

        // Directly update the WordPress alt text meta
        $.post(altmImageProcessing.ajaxUrl, {
            action: 'altm_update_alt_text',
            attachment_id: imageId,
            alt_text: newAltText,
            nonce: altmImageProcessing.generateAltTextNonce
        }, function (response) {
            // Update the alt text in the table
            const row = $('button.altm-edit-alt-text[data-id="' + imageId + '"]').closest('tr');
            const altTextCell = row.find('.altm-alt-text-cell');

            altTextCell.data('alt-text', newAltText);
            const displayText = newAltText ? newAltText : '<span style="color: #ff9999; font-style: italic;">Empty</span>';
            altTextCell.html('<div style="padding: 8px 10px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 13px; background: linear-gradient(135deg, #f9f9f9 0%, #e8e8e8 100%); word-wrap: break-word; line-height: 1.4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">' + displayText + '</div>');

            // Update the data in memory (both filtered and all images)
            const activeTab = row.closest('.tab-content').attr('id').replace('tab-content-', '');
            let image = tabData[activeTab].images.find(img => img.ID == imageId);
            if (image) {
                image.alt_text = newAltText;
            }
            let allImage = tabData[activeTab].allImages.find(img => img.ID == imageId);
            if (allImage) {
                allImage.alt_text = newAltText;
            }

            // Close modal
            $('#altm-edit-alt-modal').fadeOut(200, function () {
                $(this).remove();
            });

            // Show success message briefly
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Alt text updated successfully!</p></div>');
            $('.wrap h1').after(successMsg);
            setTimeout(function () {
                successMsg.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        }).fail(function () {
            alert('Error saving alt text. Please try again.');
            button.text('Save').prop('disabled', false);
        });
    });
}); 