document.addEventListener('DOMContentLoaded', function () {
    // Number formatting function
    function formatNumber(num) {
        if (num === null || num === undefined) return num;
        return parseInt(num).toLocaleString();
    }

    var pluginUrl = altMagicSettings.pluginUrl;
    var apiKeyModal = document.getElementById('api-key-modal');
    var apiKeyInput = document.getElementById('alt_magic_api_key');

    // Function to show modal
    function showApiKeyModal() {
        if (apiKeyModal) {
            apiKeyModal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent body scroll

            // Show help video if no API key exists
            var apiKey = apiKeyInput ? apiKeyInput.value : '';
            var helpVideoContainer = document.getElementById('help-video-container');
            if (!apiKey && helpVideoContainer) {
                helpVideoContainer.style.display = 'block';
            }
        }
    }

    // Function to hide modal
    function hideApiKeyModal() {
        if (apiKeyModal) {
            apiKeyModal.style.display = 'none';
            document.body.style.overflow = ''; // Restore body scroll
        }
    }

    // Handle "Connect Existing Account" button click
    var connectExistingBtn = document.getElementById('connect-existing-account');
    if (connectExistingBtn) {
        connectExistingBtn.addEventListener('click', function () {
            showApiKeyModal();
        });
    }

    // Handle modal close button
    var modalCloseBtn = document.getElementById('api-key-modal-close');
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', function () {
            hideApiKeyModal();
        });
    }

    // Handle backdrop click to close modal
    var modalBackdrop = document.getElementById('api-key-modal-backdrop');
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', function () {
            hideApiKeyModal();
        });
    }

    // Handle Escape key to close modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && apiKeyModal && apiKeyModal.style.display === 'flex') {
            hideApiKeyModal();
        }
    });

    // Function to display user details (reusable for both flows)
    function displayUserDetails(userDetails, isDashboard) {
        var prefix = isDashboard ? 'dashboard-' : '';
        var userEmail = userDetails.email;
        var profilePictureElement = document.getElementById(prefix + 'profile-picture');
        var userNameElement = document.getElementById(prefix + 'user-name');
        var userEmailElement = document.getElementById(prefix + 'user-email');
        var creditsElement = document.getElementById(prefix + 'credits-available');
        var userDetailsContainer = isDashboard ?
            document.getElementById('dashboard-user-details') :
            document.getElementById('user-details');

        if (profilePictureElement && userNameElement && userEmailElement && creditsElement) {
            // Set profile picture
            if (userEmail.endsWith('@gmail.com') && userDetails.profile_picture) {
                profilePictureElement.style.backgroundImage = `url(${userDetails.profile_picture})`;
                profilePictureElement.style.backgroundSize = 'cover';
                profilePictureElement.textContent = '';
            } else {
                var firstLetter = userEmail.charAt(0).toUpperCase();
                profilePictureElement.style.backgroundImage = '';
                profilePictureElement.style.backgroundColor = '#673AB7';
                profilePictureElement.style.color = 'white';
                profilePictureElement.style.fontSize = '18px';
                profilePictureElement.textContent = firstLetter;
            }

            // Set user details
            userNameElement.textContent = userDetails.user_name || '';
            userEmailElement.textContent = userEmail || '';

            // Format credits for dashboard (show "X credits")
            if (isDashboard) {
                var credits = parseInt(userDetails.credits_available) || 0;
                creditsElement.textContent = formatNumber(credits) + ' credits';

                // Apply color class based on credits amount
                // Rule: Always green for 25 credits, green for >= 100, yellow for < 100 (except 25)
                creditsElement.classList.remove('credits-green', 'credits-yellow');
                if (credits === 25 || credits >= 100) {
                    creditsElement.classList.add('credits-green');
                } else {
                    creditsElement.classList.add('credits-yellow');
                }

                // Show dashboard link and buy credits link when verified
                var dashboardLink = document.getElementById('dashboard-link');
                if (dashboardLink) {
                    dashboardLink.style.display = 'block';
                }
                var buyCreditsLink = document.getElementById('buy-credits-link');
                if (buyCreditsLink) {
                    buyCreditsLink.style.display = 'inline';
                    // Update buy credits link with email parameter
                    var email = userDetails.email || altMagicSettings.userEmail;
                    buyCreditsLink.href = email
                        ? 'https://altmagic.pro/?wp_email=' + encodeURIComponent(email) + '#pricing'
                        : 'https://altmagic.pro/#pricing';
                    // Add prominent style when credits are below 24
                    buyCreditsLink.classList.remove('alt-magic-buy-credits-link-urgent');
                    if (credits < 24) {
                        buyCreditsLink.classList.add('alt-magic-buy-credits-link-urgent');
                    }
                }
            } else {
                var credits = parseInt(userDetails.credits_available) || 0;
                creditsElement.textContent = formatNumber(credits);
            }

            // Show user details container
            if (userDetailsContainer) {
                userDetailsContainer.style.display = 'block';
            }
        }

        // Set API key in dashboard display if it's dashboard view
        if (isDashboard) {
            var apiKeyDisplay = document.getElementById('dashboard-api-key-display');
            if (apiKeyDisplay && apiKeyInput) {
                apiKeyDisplay.value = apiKeyInput.value;
            }
        }
    }

    // Handle API key visibility toggle
    var toggleApiKeyBtn = document.getElementById('toggle-api-key-visibility');
    if (toggleApiKeyBtn) {
        toggleApiKeyBtn.addEventListener('click', function () {
            var apiKeyDisplay = document.getElementById('dashboard-api-key-display');
            var eyeIcon = document.getElementById('eye-icon');
            var eyeOffIcon = document.getElementById('eye-off-icon');

            if (apiKeyDisplay && eyeIcon && eyeOffIcon) {
                if (apiKeyDisplay.type === 'password') {
                    apiKeyDisplay.type = 'text';
                    eyeIcon.style.display = 'none';
                    eyeOffIcon.style.display = 'block';
                } else {
                    apiKeyDisplay.type = 'password';
                    eyeIcon.style.display = 'block';
                    eyeOffIcon.style.display = 'none';
                }
            }
        });
    }

    // Handle "Login with WordPress" button click
    var loginWithWordPressBtn = document.getElementById('login-with-wordpress');
    if (loginWithWordPressBtn) {
        loginWithWordPressBtn.addEventListener('click', function () {
            // Disable button and show loading state
            loginWithWordPressBtn.disabled = true;
            loginWithWordPressBtn.textContent = 'Signing in...';

            fetch(ajaxurl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'alt_magic_wp_auto_register',
                    'nonce': altMagicSettings.nonceWpRegister
                })
            })
                .then(response => response.json())
                .then(response => {
                    loginWithWordPressBtn.disabled = false;
                    loginWithWordPressBtn.textContent = 'Use WordPress Account';

                    if (response.success && response.data.api_key && response.data.user_id && response.data.user_details) {
                        // Update the API key input with the newly received API key
                        if (apiKeyInput) {
                            apiKeyInput.value = response.data.api_key;
                        }

                        // Set API key in dashboard display
                        var apiKeyDisplay = document.getElementById('dashboard-api-key-display');
                        if (apiKeyDisplay) {
                            apiKeyDisplay.value = response.data.api_key;
                        }

                        // Hide login options and welcome banner
                        var loginOptions = document.querySelector('.alt-magic-login-options');
                        if (loginOptions) {
                            loginOptions.style.display = 'none';
                        }
                        var welcomeBanner = document.querySelector('.alt-magic-welcome-banner');
                        if (welcomeBanner) {
                            welcomeBanner.style.display = 'none';
                        }

                        // Show dashboard user details container
                        var dashboardUserDetails = document.getElementById('dashboard-user-details');
                        if (dashboardUserDetails) {
                            dashboardUserDetails.style.display = 'block';
                        }

                        // Show connected account card
                        var connectedCard = document.getElementById('dashboard-connected-account-card');
                        if (connectedCard) {
                            connectedCard.style.display = 'block';
                        }

                        // Show verified status, hide unverified status
                        var verifiedStatus = document.getElementById('dashboard-api-key-verified');
                        if (verifiedStatus) {
                            verifiedStatus.style.display = 'flex';
                        }
                        var unverifiedStatus = document.getElementById('dashboard-api-key-unverified');
                        if (unverifiedStatus) {
                            unverifiedStatus.style.display = 'none';
                        }

                        // Show Account and Credits sections (these are hidden by default)
                        var accountSection = document.getElementById('dashboard-account-section');
                        if (accountSection) {
                            accountSection.style.display = 'block';
                        }
                        var creditsSection = document.getElementById('dashboard-credits-section');
                        if (creditsSection) {
                            creditsSection.style.display = 'block';
                        }

                        // Show Alt Magic Academy section
                        var academySection = document.getElementById('alt-magic-academy-section');
                        if (academySection) {
                            academySection.style.display = 'block';
                        }

                        // Display user details in dashboard
                        displayUserDetails(response.data.user_details, true);

                    } else {
                        var errorMessage = 'Registration failed. Please try again.';
                        if (!response.success && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        alert(errorMessage);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    loginWithWordPressBtn.disabled = false;
                    loginWithWordPressBtn.textContent = 'Use WordPress Account';
                    alert('Connection error. Please try again.');
                });
        });
    }

    // Check if API key exists and verify it
    var apiKey = apiKeyInput ? apiKeyInput.value : '';
    if (apiKey) {
        // Set API key in dashboard display
        var apiKeyDisplay = document.getElementById('dashboard-api-key-display');
        if (apiKeyDisplay) {
            apiKeyDisplay.value = apiKey;
        }

        // Hide login options and welcome banner if API key exists (they're already hidden in PHP, but just in case)
        var loginOptions = document.querySelector('.alt-magic-login-options');
        if (loginOptions) {
            loginOptions.style.display = 'none';
        }
        var welcomeBanner = document.querySelector('.alt-magic-welcome-banner');
        if (welcomeBanner) {
            welcomeBanner.style.display = 'none';
        }

        // Hide Alt Magic Academy section initially (will be shown after verification)
        var academySection = document.getElementById('alt-magic-academy-section');
        if (academySection) {
            academySection.style.display = 'none';
        }

        // Show the connected account card (will show empty account details if not verified)
        var connectedCard = document.getElementById('dashboard-connected-account-card');
        if (connectedCard) {
            connectedCard.style.display = 'block';
        }

        // Hide verified/unverified status initially (will be shown after verification)
        var verifiedStatus = document.getElementById('dashboard-api-key-verified');
        if (verifiedStatus) {
            verifiedStatus.style.display = 'none';
        }
        var unverifiedStatus = document.getElementById('dashboard-api-key-unverified');
        if (unverifiedStatus) {
            unverifiedStatus.style.display = 'none';
        }

        // Show loading indicator for modal (if it exists)
        var apiKeyStatus = document.getElementById('api-key-status');
        if (apiKeyStatus) {
            apiKeyStatus.innerHTML = '<p style="color: #666;">Verifying API key...</p>';
            apiKeyStatus.style.display = 'flex';
        }

        // Verify the API key (loader is already shown in PHP)
        verifyApiKey(apiKey);
    } else {
        // No API key - show help video if container exists
        var helpVideoContainer = document.getElementById('help-video-container');
        if (helpVideoContainer) {
            helpVideoContainer.style.display = 'block';
        }
    }

    var verifyApiKeyBtn = document.getElementById('verify-api-key');
    if (verifyApiKeyBtn) {
        verifyApiKeyBtn.addEventListener('click', function () {
            if (apiKeyInput) {
                var apiKey = apiKeyInput.value;
                verifyApiKey(apiKey);
            }
        });
    }

    function verifyApiKey(apiKey) {
        // Show loader in dashboard if it exists
        var verificationLoader = document.getElementById('api-key-verification-loader');
        if (verificationLoader) {
            verificationLoader.style.display = 'flex';
        }

        // Hide the connected account card while verifying
        var connectedCard = document.getElementById('dashboard-connected-account-card');
        if (connectedCard) {
            connectedCard.style.display = 'none';
        }

        // Update API key status to "Verifying..." when verification starts (for modal)
        var apiKeyStatus = document.getElementById('api-key-status');
        if (apiKeyStatus) {
            apiKeyStatus.innerHTML = '<p style="color: #666;">Verifying API key...</p>';
            apiKeyStatus.style.display = 'flex';
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                'action': 'alt_magic_verify_api_key',
                'api_key': apiKey,
                'nonce': altMagicSettings.nonceVerify
            })
        })
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data.message === 'API key is valid' && response.data.user_id) {
                    // Hide loader
                    var verificationLoader = document.getElementById('api-key-verification-loader');
                    if (verificationLoader) {
                        verificationLoader.style.display = 'none';
                    }

                    var statusHTML = `
                    <img src="${pluginUrl}../assets/altm-green-tick.svg" alt="Green Tick" style="width: 20px; height: 20px;">
                    <p style="color: #00B612; font-weight: bold;">API key is verified.</p>`;

                    var apiKeyStatus = document.getElementById('api-key-status');
                    if (apiKeyStatus) {
                        apiKeyStatus.innerHTML = statusHTML;
                        apiKeyStatus.style.display = 'flex';
                    }

                    // Update the verify button styling
                    var verifyButton = document.getElementById('verify-api-key');
                    if (verifyButton) {
                        verifyButton.style.backgroundColor = 'white';
                        verifyButton.style.color = '#f66e3c';
                    }

                    var helpVideoContainer = document.getElementById('help-video-container');
                    if (helpVideoContainer) {
                        helpVideoContainer.style.display = 'none';
                    }
                    var removeApiKeyContainer = document.getElementById('remove-api-key-container');
                    if (removeApiKeyContainer) {
                        removeApiKeyContainer.style.display = 'block';
                    }

                    // Hide login options and welcome banner
                    var loginOptions = document.querySelector('.alt-magic-login-options');
                    if (loginOptions) {
                        loginOptions.style.display = 'none';
                    }
                    var welcomeBanner = document.querySelector('.alt-magic-welcome-banner');
                    if (welcomeBanner) {
                        welcomeBanner.style.display = 'none';
                    }

                    // Show connected account card and display user details
                    var connectedCard = document.getElementById('dashboard-connected-account-card');
                    if (connectedCard) {
                        connectedCard.style.display = 'block';
                    }

                    // Show dashboard user details container
                    var dashboardUserDetails = document.getElementById('dashboard-user-details');
                    if (dashboardUserDetails) {
                        dashboardUserDetails.style.display = 'block';
                    }

                    // Show Alt Magic Academy section
                    var academySection = document.getElementById('alt-magic-academy-section');
                    if (academySection) {
                        academySection.style.display = 'block';
                    }

                    // Show verified status, hide unverified status
                    var verifiedStatus = document.getElementById('dashboard-api-key-verified');
                    if (verifiedStatus) {
                        verifiedStatus.style.display = 'flex';
                    }
                    var unverifiedStatus = document.getElementById('dashboard-api-key-unverified');
                    if (unverifiedStatus) {
                        unverifiedStatus.style.display = 'none';
                    }

                    // Show Account and Credits sections when verified
                    var accountSection = document.getElementById('dashboard-account-section');
                    if (accountSection) {
                        accountSection.style.display = 'block';
                    }
                    var creditsSection = document.getElementById('dashboard-credits-section');
                    if (creditsSection) {
                        creditsSection.style.display = 'block';
                    }

                    // Show dashboard link
                    var dashboardLink = document.getElementById('dashboard-link');
                    if (dashboardLink) {
                        dashboardLink.style.display = 'inline';
                    }

                    // Display user details in both dashboard and modal
                    displayUserDetails(response.data.user_details, true);
                    displayUserDetails(response.data.user_details, false);

                    // Close the modal after successful verification
                    hideApiKeyModal();
                } else {
                    // Hide loader on error
                    var verificationLoader = document.getElementById('api-key-verification-loader');
                    if (verificationLoader) {
                        verificationLoader.style.display = 'none';
                    }

                    // Hide the verified connected account card
                    var connectedCard = document.getElementById('dashboard-connected-account-card');
                    if (connectedCard) {
                        connectedCard.style.display = 'none';
                    }

                    // Show the unverified card instead
                    var unverifiedCard = document.getElementById('dashboard-unverified-card');
                    if (unverifiedCard) {
                        unverifiedCard.style.display = 'block';
                    }

                    // Set API key in unverified display
                    var unverifiedApiKeyDisplay = document.getElementById('unverified-api-key-display');
                    if (unverifiedApiKeyDisplay && apiKeyInput) {
                        unverifiedApiKeyDisplay.value = apiKeyInput.value;
                    }

                    // Hide login options and welcome banner
                    var loginOptions = document.querySelector('.alt-magic-login-options');
                    if (loginOptions) {
                        loginOptions.style.display = 'none';
                    }
                    var welcomeBanner = document.querySelector('.alt-magic-welcome-banner');
                    if (welcomeBanner) {
                        welcomeBanner.style.display = 'none';
                    }

                    // Show Alt Magic Academy section even when unverified
                    var academySection = document.getElementById('alt-magic-academy-section');
                    if (academySection) {
                        academySection.style.display = 'block';
                    }

                    var errorMessage = 'Invalid API key. Please try again.';
                    if (!response.success && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    //alert(errorMessage);

                    // Update modal status
                    if (apiKeyInput && apiKeyInput.value) {
                        var statusHTML = `
                        <div style="display: flex; align-items: center; gap: 4px; background-color:rgba(255, 116, 116, 0.14); padding: 4px; border-radius: 4px;">
                            <img src="${pluginUrl}../assets/altm-red-cross.svg" alt="Red Cross" style="width: 18px; height: 18px;">
                            <p style="color: #FF0000; font-weight: 500; margin: 0;">API key is not valid. Please disconnect account and create a new API key.</p>
                        </div>`;

                        var apiKeyStatus = document.getElementById('api-key-status');
                        if (apiKeyStatus) {
                            apiKeyStatus.innerHTML = statusHTML;
                            apiKeyStatus.style.display = 'flex';
                        }

                        var removeApiKeyContainer = document.getElementById('remove-api-key-container');
                        if (removeApiKeyContainer) {
                            removeApiKeyContainer.style.display = 'block';
                        }
                    } else {
                        var apiKeyStatus = document.getElementById('api-key-status');
                        if (apiKeyStatus) {
                            apiKeyStatus.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Hide loader on error
                var verificationLoader = document.getElementById('api-key-verification-loader');
                if (verificationLoader) {
                    verificationLoader.style.display = 'none';
                }

                // Show connected account card on error
                var connectedCard = document.getElementById('dashboard-connected-account-card');
                if (connectedCard) {
                    connectedCard.style.display = 'none';
                }

                // Show the unverified card
                var unverifiedCard = document.getElementById('dashboard-unverified-card');
                if (unverifiedCard) {
                    unverifiedCard.style.display = 'block';
                }

                // Set API key in unverified display
                var unverifiedApiKeyDisplay = document.getElementById('unverified-api-key-display');
                if (unverifiedApiKeyDisplay && apiKeyInput) {
                    unverifiedApiKeyDisplay.value = apiKeyInput.value;
                }

                // Show Alt Magic Academy section even on error
                var academySection = document.getElementById('alt-magic-academy-section');
                if (academySection) {
                    academySection.style.display = 'block';
                }

                alert('Connection error. Please try again.');

                if (apiKeyInput && apiKeyInput.value) {
                    var statusHTML = `
                    <div style="display: flex; align-items: center; gap: 4px; background-color:rgba(255, 116, 116, 0.14); padding: 4px; border-radius: 4px;">
                        <img src="${pluginUrl}../assets/altm-red-cross.svg" alt="Red Cross" style="width: 18px; height: 18px;">
                        <p style="color: #FF0000; font-weight: 500; margin: 0;">API key is not valid. Please disconnect account and create a new API key.</p>
                    </div>`;

                    var apiKeyStatus = document.getElementById('api-key-status');
                    if (apiKeyStatus) {
                        apiKeyStatus.innerHTML = statusHTML;
                        apiKeyStatus.style.display = 'flex';
                    }

                    var removeApiKeyContainer = document.getElementById('remove-api-key-container');
                    if (removeApiKeyContainer) {
                        removeApiKeyContainer.style.display = 'block';
                    }
                } else {
                    var apiKeyStatus = document.getElementById('api-key-status');
                    if (apiKeyStatus) {
                        apiKeyStatus.style.display = 'none';
                    }
                }
            })
            .finally(() => {
                //document.getElementById('spinner').style.display = 'none';
            });
    }

    // Function to handle API key removal
    function handleRemoveApiKey() {
        fetch(ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                'action': 'alt_magic_remove_api_key',
                'nonce': altMagicSettings.nonceRemove
            })
        }).then(function () {
            // Refresh the page after successful disconnect
            window.location.reload();
        });
    }

    // Handle remove API key button in modal
    var removeApiKeyBtn = document.getElementById('remove-api-key');
    if (removeApiKeyBtn) {
        removeApiKeyBtn.addEventListener('click', handleRemoveApiKey);
    }

    // Disconnect Confirmation Modal
    var disconnectModal = document.getElementById('disconnect-confirmation-modal');
    var disconnectModalBackdrop = document.getElementById('disconnect-modal-backdrop');
    var disconnectModalClose = document.getElementById('disconnect-modal-close');
    var confirmDisconnectBtn = document.getElementById('confirm-disconnect');
    var cancelDisconnectBtn = document.getElementById('cancel-disconnect');

    function showDisconnectModal() {
        if (disconnectModal) {
            disconnectModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function hideDisconnectModal() {
        if (disconnectModal) {
            disconnectModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Handle remove API key button in dashboard
    var dashboardRemoveApiKeyBtn = document.getElementById('dashboard-remove-api-key');
    if (dashboardRemoveApiKeyBtn) {
        dashboardRemoveApiKeyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showDisconnectModal();
        });
    }

    // Handle confirm disconnect
    if (confirmDisconnectBtn) {
        confirmDisconnectBtn.addEventListener('click', function () {
            hideDisconnectModal();
            handleRemoveApiKey();
        });
    }

    // Handle cancel disconnect
    if (cancelDisconnectBtn) {
        cancelDisconnectBtn.addEventListener('click', function () {
            hideDisconnectModal();
        });
    }

    // Handle modal close button
    if (disconnectModalClose) {
        disconnectModalClose.addEventListener('click', function () {
            hideDisconnectModal();
        });
    }

    // Handle backdrop click
    if (disconnectModalBackdrop) {
        disconnectModalBackdrop.addEventListener('click', function () {
            hideDisconnectModal();
        });
    }

    // Handle Escape key for disconnect modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && disconnectModal && disconnectModal.style.display === 'flex') {
            hideDisconnectModal();
        }
    });

    // API Key Video Modal
    var apiKeyVideoModal = document.getElementById('api-key-video-modal');
    var apiKeyVideoModalBackdrop = document.getElementById('api-key-video-modal-backdrop');
    var apiKeyVideoModalClose = document.getElementById('api-key-video-modal-close');
    var showApiKeyVideoBtn = document.getElementById('show-api-key-video');
    var apiKeyVideoIframe = document.getElementById('api-key-video-iframe');

    function showApiKeyVideoModal() {
        if (apiKeyVideoModal && apiKeyVideoIframe) {
            // Set the iframe source when opening the modal
            apiKeyVideoIframe.src = 'https://www.youtube.com/embed/shIN7PNR6NE?si=_1zwlM--0efWDa-e';
            apiKeyVideoModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function hideApiKeyVideoModal() {
        if (apiKeyVideoModal && apiKeyVideoIframe) {
            // Clear the iframe source to stop the video
            apiKeyVideoIframe.src = '';
            apiKeyVideoModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Handle show video button click
    if (showApiKeyVideoBtn) {
        showApiKeyVideoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showApiKeyVideoModal();
        });
    }

    // Handle modal close button
    if (apiKeyVideoModalClose) {
        apiKeyVideoModalClose.addEventListener('click', function () {
            hideApiKeyVideoModal();
        });
    }

    // Handle backdrop click
    if (apiKeyVideoModalBackdrop) {
        apiKeyVideoModalBackdrop.addEventListener('click', function () {
            hideApiKeyVideoModal();
        });
    }

    // Handle Escape key for video modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && apiKeyVideoModal && apiKeyVideoModal.style.display === 'flex') {
            hideApiKeyVideoModal();
        }
    });

    // Unverified API Key Toggle
    var toggleUnverifiedApiKeyBtn = document.getElementById('toggle-unverified-api-key-visibility');
    if (toggleUnverifiedApiKeyBtn) {
        toggleUnverifiedApiKeyBtn.addEventListener('click', function () {
            var unverifiedApiKeyDisplay = document.getElementById('unverified-api-key-display');
            var unverifiedEyeIcon = document.getElementById('unverified-eye-icon');
            var unverifiedEyeOffIcon = document.getElementById('unverified-eye-off-icon');

            if (unverifiedApiKeyDisplay && unverifiedEyeIcon && unverifiedEyeOffIcon) {
                if (unverifiedApiKeyDisplay.type === 'password') {
                    unverifiedApiKeyDisplay.type = 'text';
                    unverifiedEyeIcon.style.display = 'none';
                    unverifiedEyeOffIcon.style.display = 'block';
                } else {
                    unverifiedApiKeyDisplay.type = 'password';
                    unverifiedEyeIcon.style.display = 'block';
                    unverifiedEyeOffIcon.style.display = 'none';
                }
            }
        });
    }

    // Handle remove API key button in unverified card
    var unverifiedRemoveApiKeyBtn = document.getElementById('unverified-remove-api-key');
    if (unverifiedRemoveApiKeyBtn) {
        unverifiedRemoveApiKeyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showDisconnectModal();
        });
    }

    // Handle video link in unverified card
    var unverifiedShowVideoBtn = document.getElementById('unverified-show-api-key-video');
    if (unverifiedShowVideoBtn) {
        unverifiedShowVideoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showApiKeyVideoModal();
        });
    }

    // Academy Video Modal
    var academyVideoModal = document.getElementById('academy-video-modal');
    var academyVideoModalBackdrop = document.getElementById('academy-video-modal-backdrop');
    var academyVideoModalClose = document.getElementById('academy-video-modal-close');
    var academyVideoIframe = document.getElementById('academy-video-iframe');
    var academyVideoModalTitle = document.getElementById('academy-video-modal-title');

    function showAcademyVideoModal(videoUrl, videoTitle) {
        if (academyVideoModal && academyVideoIframe) {
            // Set the iframe source when opening the modal
            academyVideoIframe.src = videoUrl;
            if (academyVideoModalTitle && videoTitle) {
                academyVideoModalTitle.textContent = videoTitle;
            }
            academyVideoModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function hideAcademyVideoModal() {
        if (academyVideoModal && academyVideoIframe) {
            // Clear the iframe source to stop the video
            academyVideoIframe.src = '';
            academyVideoModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Handle modal close button
    if (academyVideoModalClose) {
        academyVideoModalClose.addEventListener('click', function () {
            hideAcademyVideoModal();
        });
    }

    // Handle backdrop click
    if (academyVideoModalBackdrop) {
        academyVideoModalBackdrop.addEventListener('click', function () {
            hideAcademyVideoModal();
        });
    }

    // Handle Escape key for academy video modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && academyVideoModal && academyVideoModal.style.display === 'flex') {
            hideAcademyVideoModal();
        }
    });

    // Function to fetch and render academy videos
    function fetchAndRenderAcademyVideos() {
        var videoGrid = document.querySelector('.alt-magic-video-grid');
        if (!videoGrid) return;

        // Show loading state
        videoGrid.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Loading videos...</p>';

        // Fetch videos from API
        fetch(altMagicSettings.apiBaseUrl + '/help-videos-wp', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.videos && data.videos.length > 0) {
                    // Clear the grid
                    videoGrid.innerHTML = '';

                    // Render each video card
                    data.videos.forEach((video, index) => {
                        var videoCard = createVideoCard(video, index);
                        videoGrid.appendChild(videoCard);
                    });

                    // Add "Coming Soon" card at the end
                    var comingSoonCard = createComingSoonCard();
                    videoGrid.appendChild(comingSoonCard);

                    // Attach click handlers to all video cards
                    attachVideoCardHandlers();
                } else {
                    // Show error or fallback message
                    videoGrid.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No videos available at the moment.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching academy videos:', error);
                // Show error message
                videoGrid.innerHTML = '<p style="text-align: center; color: #dc2626; padding: 20px;">Failed to load videos. Please try again later.</p>';
            });
    }

    // Function to create a video card element
    function createVideoCard(video, index) {
        var card = document.createElement('div');
        card.className = 'alt-magic-video-card';
        card.id = 'academy-video-card-' + index;
        card.setAttribute('data-video-url', video.link);
        card.style.cursor = 'pointer';

        // Extract YouTube video ID from the embed URL
        var videoId = extractYouTubeVideoId(video.link);
        var thumbnailUrl = videoId ? `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg` : '';

        card.innerHTML = `
            <div class="alt-magic-video-thumbnail">
                ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="${video.title}" />` : ''}
                <div class="alt-magic-video-play-overlay">
                    <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="32" cy="32" r="32" fill="rgba(0, 0, 0, 0.7)"/>
                        <path d="M26 20L44 32L26 44V20Z" fill="white"/>
                    </svg>
                </div>
            </div>
            <div class="alt-magic-video-info">
                <div class="alt-magic-video-content">
                    <h3 class="alt-magic-video-title">${video.title}</h3>
                    <p class="alt-magic-video-subtitle">${video.description}</p>
                </div>
            </div>
        `;

        return card;
    }

    // Function to create the "Coming Soon" card
    function createComingSoonCard() {
        var card = document.createElement('div');
        card.className = 'alt-magic-video-card alt-magic-video-card-coming-soon';

        card.innerHTML = `
            <div class="alt-magic-video-thumbnail alt-magic-video-thumbnail-coming-soon">
                <div class="alt-magic-coming-soon-content">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="alt-magic-coming-soon-icon">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 8V12" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 16H12.01" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="alt-magic-video-info">
                <div class="alt-magic-video-content">
                    <h3 class="alt-magic-video-title">More Tutorials Coming Soon</h3>
                    <p class="alt-magic-video-subtitle">Stay tuned for more helpful guides</p>
                </div>
            </div>
        `;

        return card;
    }

    // Function to extract YouTube video ID from various URL formats
    function extractYouTubeVideoId(url) {
        if (!url) return null;

        // Match patterns like:
        // https://www.youtube.com/embed/VIDEO_ID
        // https://www.youtube.com/watch?v=VIDEO_ID
        // https://youtu.be/VIDEO_ID
        var patterns = [
            /(?:youtube\.com\/embed\/|youtube\.com\/watch\?v=|youtu\.be\/)([^?&"'>]+)/,
            /^([a-zA-Z0-9_-]{11})$/ // Direct video ID
        ];

        for (var i = 0; i < patterns.length; i++) {
            var match = url.match(patterns[i]);
            if (match && match[1]) {
                return match[1];
            }
        }

        return null;
    }

    // Function to attach click handlers to all video cards
    function attachVideoCardHandlers() {
        var videoCards = document.querySelectorAll('.alt-magic-video-card:not(.alt-magic-video-card-coming-soon)');
        videoCards.forEach(function (card) {
            card.addEventListener('click', function () {
                var videoUrl = this.getAttribute('data-video-url');
                var videoTitle = this.querySelector('.alt-magic-video-title');
                var title = videoTitle ? videoTitle.textContent : 'Video Tutorial';
                showAcademyVideoModal(videoUrl, title);
            });
        });
    }

    // Fetch and render videos when the academy section becomes visible
    // Check if academy section exists and is visible, then fetch videos
    var academySection = document.getElementById('alt-magic-academy-section');
    if (academySection) {
        // Use MutationObserver to detect when the academy section becomes visible
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    var isVisible = academySection.style.display !== 'none';
                    if (isVisible && !academySection.dataset.videosLoaded) {
                        academySection.dataset.videosLoaded = 'true';
                        fetchAndRenderAcademyVideos();
                    }
                }
            });
        });

        observer.observe(academySection, {
            attributes: true,
            attributeFilter: ['style']
        });

        // Also check if it's already visible on page load
        if (academySection.style.display !== 'none' && getComputedStyle(academySection).display !== 'none') {
            academySection.dataset.videosLoaded = 'true';
            fetchAndRenderAcademyVideos();
        }
    }
});