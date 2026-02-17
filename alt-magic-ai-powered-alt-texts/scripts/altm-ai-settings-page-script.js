document.addEventListener('DOMContentLoaded', function () {
    const settings = document.querySelectorAll('.alt-magic-setting');

    // Tabs toggle (WP nav-tab)
    const tabButtons = document.querySelectorAll('.nav-tab');
    const tabContents = document.querySelectorAll('.altm-tab-content');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const target = this.getAttribute('data-target');
            tabButtons.forEach(b => b.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');
            tabContents.forEach(group => {
                if (group.id === target) {
                    group.style.display = 'table-row-group';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });



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

    function saveSettingValue(element, key, value) {
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
