document.addEventListener('DOMContentLoaded', function () {
    const settings = document.querySelectorAll('.alt-magic-setting');
    const onboardingModal = document.querySelector('.altm-onboarding-modal');
    const onboardingBanner = onboardingModal ? onboardingModal.querySelector('.altm-onboarding-banner') : null;
    const openUndoRenameModalButton = document.getElementById('altm-open-undo-rename-modal');
    const undoRenameModal = document.getElementById('altm-undo-rename-modal');
    const closeUndoRenameModalButton = document.getElementById('altm-close-undo-rename-modal');
    const undoRenameResult = document.getElementById('altm-undo-rename-modal-result');
    const undoRenameTableBody = document.getElementById('altm-undo-rename-table-body');
    const loadMoreRenamedImagesButton = document.getElementById('altm-load-more-renamed-images');
    let undoRenameOffset = 0;
    let undoRenameLoading = false;

    // Tabs toggle (WP nav-tab)
    const tabButtons = document.querySelectorAll('.nav-tab');
    const tabContents = document.querySelectorAll('.altm-tab-content');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            activateTab(this.getAttribute('data-target'));
        });
    });

    const onboardingActions = document.querySelectorAll('.altm-onboarding-action');
    onboardingActions.forEach(action => {
        action.addEventListener('click', function (event) {
            const key = this.getAttribute('data-setting-key');
            const value = this.getAttribute('data-setting-value');
            const targetTab = this.getAttribute('data-target-tab');
            const destination = this.tagName === 'A' ? this.getAttribute('href') : '';
            const hideOnSuccess = this.getAttribute('data-hide-on-success') === '1';

            if (destination) {
                event.preventDefault();
            }

            if (targetTab) {
                activateTab(targetTab);
            }

            saveSettingValue(this, key, value, function () {
                if (onboardingBanner) {
                    onboardingBanner.dataset.onboardingDone = value;

                    const statusPill = onboardingBanner.querySelector('.altm-onboarding-banner__status-pill');
                    if (statusPill) {
                        statusPill.textContent = 'Completed';
                        statusPill.classList.remove('is-pending');
                        statusPill.classList.add('is-complete');
                    }
                }

                if (hideOnSuccess && onboardingModal) {
                    onboardingModal.style.display = 'none';
                }

                if (destination) {
                    window.location.href = destination;
                }
            });
        });
    });

    function activateTab(target) {
        tabButtons.forEach(b => {
            b.classList.remove('nav-tab-active');
            b.setAttribute('aria-selected', 'false');
        });
        tabContents.forEach(group => {
            group.style.display = group.id === target ? 'table-row-group' : 'none';
        });
        const activeButton = document.querySelector('.nav-tab[data-target="' + target + '"]');
        if (activeButton) {
            activeButton.classList.add('nav-tab-active');
            activeButton.setAttribute('aria-selected', 'true');
        }
    }

    settings.forEach(setting => {
        setting.addEventListener('change', function () {
            const key = this.name;
            let value;

            if (this.type === 'checkbox') {
                value = this.checked == true ? 1 : 0;
            } else {
                value = this.value;
            }

            // Special handling for alt_magic_private_site
            if (key === 'alt_magic_private_site' && value === 0) {
                // User is unchecking the private site option (claiming site is public)
                // We need to verify that images are actually accessible
                handlePrivateSiteToggle(this, key, value);
            } else {
                // Normal save flow for all other settings
                saveSettingValue(this, key, value);
            }
        });
    });

    if (openUndoRenameModalButton && undoRenameModal) {
        openUndoRenameModalButton.addEventListener('click', function () {
            openUndoRenameModal();
        });
    }

    if (closeUndoRenameModalButton) {
        closeUndoRenameModalButton.addEventListener('click', closeUndoRenameModal);
    }

    if (undoRenameModal) {
        undoRenameModal.addEventListener('click', function (event) {
            if (event.target.classList.contains('altm-undo-rename-modal__backdrop')) {
                closeUndoRenameModal();
            }
        });
    }

    if (loadMoreRenamedImagesButton) {
        loadMoreRenamedImagesButton.addEventListener('click', function () {
            fetchRenamedImagesForUndo(false);
        });
    }

    if (undoRenameTableBody) {
        undoRenameTableBody.addEventListener('click', function (event) {
            const button = event.target.closest('.altm-row-undo-rename-button');
            if (!button) {
                return;
            }

            const attachmentId = parseInt(button.getAttribute('data-attachment-id'), 10);
            const oldFilename = button.getAttribute('data-old-filename') || '';

            if (!attachmentId) {
                return;
            }

            if (!window.confirm('Undo the latest Alt Magic rename and restore "' + oldFilename + '"?')) {
                return;
            }

            undoLatestRename(attachmentId, button);
        });
    }

    function openUndoRenameModal() {
        undoRenameModal.classList.add('is-open');
        undoRenameModal.setAttribute('aria-hidden', 'false');
        undoRenameOffset = 0;
        if (undoRenameResult) {
            undoRenameResult.style.display = 'none';
        }
        fetchRenamedImagesForUndo(true);
    }

    function closeUndoRenameModal() {
        undoRenameModal.classList.remove('is-open');
        undoRenameModal.setAttribute('aria-hidden', 'true');
    }

    function fetchRenamedImagesForUndo(reset) {
        if (undoRenameLoading || !undoRenameTableBody) {
            return;
        }

        undoRenameLoading = true;

        if (reset) {
            undoRenameOffset = 0;
            undoRenameTableBody.innerHTML = '<tr><td colspan="4">Loading renamed images...</td></tr>';
        }

        const formData = new FormData();
        formData.append('action', 'altm_get_renamed_images_for_undo');
        formData.append('nonce', altMagicSettings.renameUndoNonce || '');
        formData.append('offset', undoRenameOffset);
        formData.append('per_page', 25);

        if (loadMoreRenamedImagesButton) {
            loadMoreRenamedImagesButton.disabled = true;
            loadMoreRenamedImagesButton.textContent = 'Loading...';
        }

        fetch(altMagicSettings.ajaxurl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const items = data.data && Array.isArray(data.data.items) ? data.data.items : [];
                    undoRenameOffset = data.data && data.data.next_offset ? parseInt(data.data.next_offset, 10) : undoRenameOffset;
                    renderUndoRenameRows(items, reset);

                    if (loadMoreRenamedImagesButton) {
                        loadMoreRenamedImagesButton.style.display = data.data && data.data.has_more ? 'inline-block' : 'none';
                    }
                } else {
                    const message = data.data && data.data.message ? data.data.message : 'Unable to undo rename.';
                    showUndoRenameMessage(message, 'error');
                    if (reset) {
                        undoRenameTableBody.innerHTML = '<tr><td colspan="4">Unable to load renamed images.</td></tr>';
                    }
                }
            })
            .catch(() => {
                showUndoRenameMessage('Unable to load renamed images. Please try again.', 'error');
                if (reset) {
                    undoRenameTableBody.innerHTML = '<tr><td colspan="4">Unable to load renamed images.</td></tr>';
                }
            })
            .finally(() => {
                undoRenameLoading = false;
                if (loadMoreRenamedImagesButton) {
                    loadMoreRenamedImagesButton.disabled = false;
                    loadMoreRenamedImagesButton.textContent = 'Load more';
                }
            });
    }

    function renderUndoRenameRows(items, reset) {
        if (reset) {
            undoRenameTableBody.innerHTML = '';
        }

        if (!items.length && undoRenameTableBody.children.length === 0) {
            undoRenameTableBody.innerHTML = '<tr><td colspan="4">No renamed images available to undo.</td></tr>';
            return;
        }

        items.forEach(item => {
            const row = document.createElement('tr');
            row.setAttribute('data-attachment-id', item.attachment_id);
            row.innerHTML =
                '<td>' + escapeHtml(String(item.attachment_id || '')) + '</td>' +
                '<td>' + escapeHtml(item.current_filename || '') + '</td>' +
                '<td>' + escapeHtml(item.old_filename || '') + '</td>' +
                '<td><button type="button" class="button altm-row-undo-rename-button" data-attachment-id="' + escapeHtml(String(item.attachment_id || '')) + '" data-old-filename="' + escapeHtml(item.old_filename || '') + '">Undo</button></td>';
            undoRenameTableBody.appendChild(row);
        });
    }

    function undoLatestRename(attachmentId, button) {
        const originalText = button.textContent;
        const formData = new FormData();
        formData.append('action', 'altm_undo_image_rename');
        formData.append('nonce', altMagicSettings.renameUndoNonce || '');
        formData.append('attachment_id', attachmentId);

        button.disabled = true;
        button.textContent = 'Undoing...';
        showUndoRenameMessage('Undoing latest rename...', 'info');

        fetch(altMagicSettings.ajaxurl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const row = button.closest('tr');
                    if (row) {
                        row.remove();
                    }
                    showUndoRenameMessage('Undo successful.', 'success');

                    if (undoRenameTableBody && undoRenameTableBody.children.length === 0) {
                        undoRenameTableBody.innerHTML = '<tr><td colspan="4">No renamed images available to undo.</td></tr>';
                    }
                } else {
                    const message = data.data && data.data.message ? data.data.message : 'Unable to undo rename.';
                    showUndoRenameMessage(message, 'error');
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(() => {
                showUndoRenameMessage('Unable to undo rename. Please try again.', 'error');
                button.disabled = false;
                button.textContent = originalText;
            });
    }

    function showUndoRenameMessage(message, type) {
        if (!undoRenameResult) {
            return;
        }

        undoRenameResult.textContent = message;
        undoRenameResult.className = 'altm-rename-undo-result is-' + type;
        undoRenameResult.style.display = 'block';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === undefined || value === null ? '' : String(value);
        return div.innerHTML;
    }

    function handlePrivateSiteToggle(element, key, value) {
        const originalCheckedState = element.checked;

        // Display "Checking accessibility..." message
        showMessage('<p style="color: blue; margin-top: 4px;">Checking if your images are accessible from the internet...</p>', element);

        const formData = new FormData();
        formData.append('action', 'alt_magic_check_image_accessibility');
        formData.append('nonce', altMagicSettings.nonce);

        fetch(altMagicSettings.ajaxurl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data.accessible) {
                    // Images are accessible, proceed with saving
                    showMessage('<p style="color: green; margin-top: 4px;">Images are accessible. Saving setting...</p>', element, 1500);
                    setTimeout(() => {
                        saveSettingValue(element, key, value);
                    }, 1500);
                } else {
                    // Images are not accessible, revert checkbox and show error
                    element.checked = !originalCheckedState;
                    const errorMsg = 'Image not accessible to our servers. Please keep this option enabled.';
                    showMessage('<p style="color: red; margin-top: 4px;">' + errorMsg + '</p>', element, 6000);
                }
            })
            .catch(error => {
                // On error, revert checkbox
                element.checked = !originalCheckedState;
                let errorMessage = 'Image not accessible to our servers. Please keep this option enabled.';

                showMessage('<p style="color: red; margin-top: 4px;">' + errorMessage + '</p>', element, 6000);
            });
    }

    function saveSettingValue(element, key, value, onSuccess) {
        // Display "Saving..." message
        showMessage('<p style="color: blue; margin-top: 4px;">Saving...</p>', element);

        const formData = new FormData();
        formData.append('action', 'alt_magic_save_settings');
        formData.append('nonce', altMagicSettings.nonce);
        formData.append('key', key);
        formData.append('value', value);

        fetch(altMagicSettings.ajaxurl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => {
                // Check if the response is ok (status 200-299)
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(data);
                    }
                    showMessage('<p style="color: green; margin-top: 4px;">' + data.data + '</p>', element, 2000);
                } else {
                    showMessage('<p style="color: red; margin-top: 4px;">Error: ' + data.data + '</p>', element, 4000);
                }
            })
            .catch(error => {
                let errorMessage = 'An error occurred. Please try again.';

                if (error.message.includes('HTTP 400')) {
                    errorMessage = 'Bad Request: Invalid data sent to server. Please refresh the page and try again.';
                } else if (error.message.includes('HTTP 403')) {
                    errorMessage = 'Access Denied: You do not have permission to perform this action.';
                } else if (error.message.includes('HTTP 500')) {
                    errorMessage = 'Server Error: Please try again later or contact support.';
                } else if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'Network Error: Please check your internet connection and try again.';
                }

                showMessage('<p style="color: red;">' + errorMessage + '</p>', element, 4000);
            });
    }

    function showMessage(message, element, timeout = 3000) {
        let messageContainer = element.parentElement.querySelector('.alt-magic-settings-message');
        if (!messageContainer) {
            messageContainer = document.createElement('div');
            messageContainer.className = 'alt-magic-settings-message';
            element.parentElement.appendChild(messageContainer);
        }
        messageContainer.innerHTML = message;
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, timeout);
    }
});
