<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Render plugin settings page
function alt_magic_render_settings_page() {

    // Enqueue the CSS file with a version number
    //altm_log('Enqueueing AI settings page CSS');
    wp_enqueue_style(
        'alt-magic-media-popup-button-css',
        esc_url(plugin_dir_url(__FILE__) . '../css/altm-ai-settings-page.css'),
        array(), // Dependencies
        '1.0.0'  // Version number
    );

    // Register and enqueue the JavaScript file
    wp_register_script(
        'alt-magic-settings-js',
        esc_url(plugin_dir_url(__FILE__) . '../scripts/altm-account-settings-page-script.js'),
        array('jquery'), // Dependencies
        '1.0.0', // Version number
        true // Load in footer
    );
    wp_enqueue_script('alt-magic-settings-js');

    // Get current WordPress user email
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    // Pass data to the JavaScript file
    wp_localize_script('alt-magic-settings-js', 'altMagicSettings', array(
        'pluginUrl' => esc_url(plugin_dir_url(__FILE__)),
        'nonceSave' => wp_create_nonce('alt_magic_save_api_key_nonce'),
        'nonceRemove' => wp_create_nonce('alt_magic_remove_api_key_nonce'),
        'nonceVerify' => wp_create_nonce('alt_magic_verify_api_key_nonce'),
        'nonceWpRegister' => wp_create_nonce('alt_magic_wp_auto_register_nonce'),
        'apiBaseUrl' => ALT_MAGIC_API_BASE_URL,
        'userEmail' => $user_email,
    ));

    $api_key = get_option('alt_magic_api_key');
    $is_verified = !empty($api_key);
    $plugin_version = defined('ALT_MAGIC_PLUGIN_VERSION') ? ALT_MAGIC_PLUGIN_VERSION : '1.6.2';

    ?>
    <div class="wrap">
        <h1>Account Settings</h1>
        <div class="alt-magic-dashboard-container">
            <?php if (empty($api_key)) : ?>
            <!-- Welcome Banner (Only shown when no API key - login needed) -->
            <div class="alt-magic-welcome-banner">
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/main-logo-big.svg'); ?>" alt="Alt Magic Logo" class="alt-magic-logo-icon" style="width: 48px; height: 48px;"/>
                <div class="alt-magic-welcome-content">
                    <h2 class="alt-magic-welcome-title">Welcome to Alt Magic! 🎉</h2>
                    <p class="alt-magic-dashboard-description">
                        Choose how you'd like to get started generating intelligent alt text for your product images. (v<?php echo esc_html($plugin_version); ?>)
                    </p>
                </div>
            </div>
            
            <!-- Two Column Layout (Only shown when no API key - login needed) -->
            <div class="alt-magic-login-options">
                <!-- Left Column: For New Customers -->
                <div class="alt-magic-login-option alt-magic-new-customers">
                    <div class="alt-magic-option-label alt-magic-label-green">New to Alt Magic</div>
                    <div class="alt-magic-option-content">
                        <h3 class="alt-magic-option-heading">Create Your Alt Magic Account</h3>
                        <p class="alt-magic-option-description">Automatically sign in with your WordPress email</p>
                        <div class="alt-magic-benefit">
                            <span class="alt-magic-benefit-text">🎁 Get 25 free image credits when you connect</span>
                        </div>
                        <button type="button" id="login-with-wordpress" class="alt-magic-button alt-magic-button-primary">
                            Continue with WordPress
                        </button>
                        <div class="alt-magic-secondary-option">
                            <span class="alt-magic-secondary-text">Want to use a different email?</span>
                            <a href="https://app.altmagic.pro/register" target="_blank" class="alt-magic-secondary-link">Create an account manually →</a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: For Existing Customers -->
                <div class="alt-magic-login-option alt-magic-existing-customers">
                    <div class="alt-magic-option-label alt-magic-label-blue">Already Using Alt Magic</div>
                    <div class="alt-magic-option-content">
                        <h3 class="alt-magic-option-heading">Connect Existing Alt Magic Account</h3>
                        <p class="alt-magic-option-description">Use your Alt Magic API key to connect this site to your existing account.</p>
                        <div class="alt-magic-integration-info">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="alt-magic-lock-icon">
                                <path d="M8 1C6.34 1 5 2.34 5 4V6H3C2.45 6 2 6.45 2 7V13C2 13.55 2.45 14 3 14H13C13.55 14 14 13.55 14 13V7C14 6.45 13.55 6 13 6H11V4C11 2.34 9.66 1 8 1ZM8 2.5C9.1 2.5 10 3.4 10 4.5V6H6V4.5C6 3.4 6.9 2.5 8 2.5ZM3 7H13V13H3V7ZM8 9C7.45 9 7 9.45 7 10C7 10.55 7.45 11 8 11C8.55 11 9 10.55 9 10C9 9.45 8.55 9 8 9Z" fill="currentColor"/>
                            </svg>
                            <span class="alt-magic-integration-text">Integrate using your existing Alt Magic API key or generate a new one.</span>
                        </div>
                        <button type="button" id="connect-existing-account" class="alt-magic-button alt-magic-button-secondary">
                            Connect Using Alt Magic API Key
                        </button>
                        <div class="alt-magic-video-help-link">
                            <a href="#" id="show-api-key-video" class="alt-magic-video-help-link-text">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="alt-magic-youtube-icon">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#FF0000"/>
                                </svg>
                                How to generate Alt Magic API key?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- User Details Section (Shown when API key exists or after successful login/registration) -->
            <div id="dashboard-user-details" class="alt-magic-dashboard-user-details" style="display: <?php echo !empty($api_key) ? 'block' : 'none'; ?>;">
                <?php if (!empty($api_key)) : ?>
                <!-- Loader shown while verifying API key -->
                <div id="api-key-verification-loader" class="alt-magic-verification-loader">
                    <div class="alt-magic-loader-spinner"></div>
                    <p class="alt-magic-loader-text">Verifying API key...</p>
                </div>
                <?php endif; ?>
                <div id="dashboard-connected-account-card" class="alt-magic-connected-account-card" style="display: none;">
                    <!-- Two Column Layout -->
                    <div class="alt-magic-account-grid">
                        <!-- Left Column -->
                        <div class="alt-magic-account-grid-left">
                    <!-- API Key Section -->
                            <div class="alt-magic-connected-section-compact">
                        <h2 class="alt-magic-connected-heading">API Key</h2>
                        <div class="alt-magic-api-key-display">
                            <input type="password" id="dashboard-api-key-display" class="alt-magic-api-key-display-input" readonly />
                            <button type="button" id="toggle-api-key-visibility" class="alt-magic-eye-toggle" aria-label="Toggle API key visibility">
                                <svg id="eye-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 3C5 3 1.73 7.11 1 10C1.73 12.89 5 17 10 17C15 17 18.27 12.89 19 10C18.27 7.11 15 3 10 3ZM10 15C7.24 15 5 12.76 5 10C5 7.24 7.24 5 10 5C12.76 5 15 7.24 15 10C15 12.76 12.76 15 10 15ZM10 7C8.34 7 7 8.34 7 10C7 11.66 8.34 13 10 13C11.66 13 13 11.66 13 10C13 8.34 11.66 7 10 7Z" fill="currentColor"/>
                                </svg>
                                <svg id="eye-off-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                    <path d="M2.71 3.16L1.29 1.75L15.29 15.75L13.88 17.16L10.29 13.57C9.5 13.82 8.78 13.92 8 13.92C3 13.92 0.27 9.81 0 6.92C0.5 5.5 1.5 4.25 2.71 3.16ZM10 2.92C15 2.92 18.27 7.03 19 9.92C18.5 11.34 17.5 12.59 16.29 13.68L14.88 12.27C15.5 11.5 16 10.75 16.29 9.92C15.56 7.03 12.29 2.92 7.29 2.92C6.5 2.92 5.78 3.02 5 3.27L3.59 1.86C4.5 1.5 5.5 1.25 6.5 1.25C11.5 1.25 14.77 5.36 15.5 8.25C15.21 9.08 14.71 9.83 14.09 10.6L12.68 9.19C12.85 8.92 13 8.65 13.09 8.25C12.36 5.36 9.09 1.25 4.09 1.25C3.09 1.25 2.09 1.5 1.18 1.86L2.59 3.27C3.5 3.02 4.22 2.92 5 2.92H10Z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                        <div id="dashboard-api-key-verified" class="alt-magic-api-key-verified" style="display: none;">
                            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/altm-green-tick.svg'); ?>" alt="Verified" class="alt-magic-verified-icon" />
                            <span class="alt-magic-verified-text">API key is verified</span>
                        </div>
                        <div id="dashboard-api-key-unverified" class="alt-magic-api-key-unverified" style="display: none;">
                            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/altm-red-cross.svg'); ?>" alt="Unverified" class="alt-magic-unverified-icon" />
                            <span class="alt-magic-unverified-text">API key is not valid. Please disconnect account and create a new API key.</span>
                        </div>
                    </div>

                    <!-- Account Section (Hidden when API key is not verified) -->
                        <div class="alt-magic-connected-section-compact" id="dashboard-account-section" style="display: none;">
                            <h2 class="alt-magic-connected-heading">Account</h2>
                                <div class="alt-magic-account-display-compact">
                            <div class="alt-magic-account-info-left">
                                <p class="profile-picture" id="dashboard-profile-picture"></p>
                                <div class="alt-magic-account-details">
                                    <h3 id="dashboard-user-name" class="alt-magic-account-name"></h3>
                                    <p id="dashboard-user-email" class="alt-magic-account-email"></p>
                                            <a href="https://app.altmagic.pro" target="_blank" class="alt-magic-dashboard-link-inline" id="dashboard-link" style="display: none;">Go to Alt Magic Dashboard →</a>
                                        </div>
                                    </div>
                                    <div class="alt-magic-account-disconnect">
                                        <a href="#" id="dashboard-remove-api-key" class="alt-magic-disconnect-link-compact">Remove Account</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="alt-magic-account-grid-right">
                            <!-- Credits Available Section (Hidden when API key is not verified) -->
                            <div class="alt-magic-connected-section-compact alt-magic-credits-section-large" id="dashboard-credits-section" style="display: none;">
                                <h2 class="alt-magic-connected-heading">Credits Available</h2>
                                <div class="alt-magic-credits-display-large">
                                    <span class="alt-magic-credits-badge-large" id="dashboard-credits-available"></span>
                                    <a href="https://altmagic.pro/#pricing" target="_blank" class="alt-magic-buy-credits-link" id="buy-credits-link" style="display: none;">Buy more credits →</a>
                                </div>
                                <p class="alt-magic-credits-description">Use credits to generate alt text and rename your images.<br><br>1 credit = 1 image alt text generation<br>1 credit = 1 image name generation</p>
                            </div>
                        </div>
                    </div>

                  
                </div>
                
                <!-- Unverified API Key Card (Shown when API key is not valid) -->
                <div id="dashboard-unverified-card" class="alt-magic-unverified-card" style="display: none;">
                    <div class="alt-magic-unverified-header">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="#ef4444" stroke-width="1.5" fill="none"/>
                            <path d="M12 8V12" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="12" cy="16" r="1" fill="#ef4444"/>
                        </svg>
                        <div>
                            <h3 class="alt-magic-unverified-title">API Key Not Valid</h3>
                            <p class="alt-magic-unverified-description">
                                The API key is not valid or has expired. Please remove and create a new one.
                            </p>
                        </div>
                    </div>
                    <div class="alt-magic-unverified-api-key-display">
                        <label class="alt-magic-unverified-label">Current API Key:</label>
                        <div class="alt-magic-api-key-display">
                            <input type="password" id="unverified-api-key-display" class="alt-magic-api-key-display-input" readonly />
                            <button type="button" id="toggle-unverified-api-key-visibility" class="alt-magic-eye-toggle" aria-label="Toggle API key visibility">
                                <svg id="unverified-eye-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 3C5 3 1.73 7.11 1 10C1.73 12.89 5 17 10 17C15 17 18.27 12.89 19 10C18.27 7.11 15 3 10 3ZM10 15C7.24 15 5 12.76 5 10C5 7.24 7.24 5 10 5C12.76 5 15 7.24 15 10C15 12.76 12.76 15 10 15ZM10 7C8.34 7 7 8.34 7 10C7 11.66 8.34 13 10 13C11.66 13 13 11.66 13 10C13 8.34 11.66 7 10 7Z" fill="currentColor"/>
                                </svg>
                                <svg id="unverified-eye-off-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                    <path d="M2.71 3.16L1.29 1.75L15.29 15.75L13.88 17.16L10.29 13.57C9.5 13.82 8.78 13.92 8 13.92C3 13.92 0.27 9.81 0 6.92C0.5 5.5 1.5 4.25 2.71 3.16ZM10 2.92C15 2.92 18.27 7.03 19 9.92C18.5 11.34 17.5 12.59 16.29 13.68L14.88 12.27C15.5 11.5 16 10.75 16.29 9.92C15.56 7.03 12.29 2.92 7.29 2.92C6.5 2.92 5.78 3.02 5 3.27L3.59 1.86C4.5 1.5 5.5 1.25 6.5 1.25C11.5 1.25 14.77 5.36 15.5 8.25C15.21 9.08 14.71 9.83 14.09 10.6L12.68 9.19C12.85 8.92 13 8.65 13.09 8.25C12.36 5.36 9.09 1.25 4.09 1.25C3.09 1.25 2.09 1.5 1.18 1.86L2.59 3.27C3.5 3.02 4.22 2.92 5 2.92H10Z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="alt-magic-unverified-actions">
                        <button type="button" id="unverified-remove-api-key" class="alt-magic-button-danger-full">
                            Remove Account
                        </button>
                        <a href="#" id="unverified-show-api-key-video" class="alt-magic-video-help-link-text alt-magic-video-help-centered">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="alt-magic-youtube-icon">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#FF0000"/>
                            </svg>
                            How to generate API key
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Alt Magic Academy Section -->
            <div class="alt-magic-academy-section" id="alt-magic-academy-section" style="display: none;">
                <div class="alt-magic-academy-header">
                    <div class="alt-magic-academy-title-wrapper">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="alt-magic-academy-icon">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#f66e3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <path d="M2 17L12 22L22 17" stroke="#f66e3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="#f66e3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div>
                            <h2 class="alt-magic-academy-heading">
                                Alt Magic Academy
                                <span class="alt-magic-academy-badge">new</span>
                            </h2>
                            <p class="alt-magic-academy-description">Learn how to get the most out of Alt Magic with these helpful tutorials</p>
                        </div>
                    </div>
                </div>
                
                <div class="alt-magic-video-grid">
                    <!-- Videos will be dynamically loaded from the API -->
                    <p style="text-align: center; color: #666; padding: 20px;">Loading videos...</p>
                </div>
            </div>
            
            <!-- Disconnect Confirmation Modal -->
            <div id="disconnect-confirmation-modal" class="alt-magic-modal" style="display: none;">
                <div class="alt-magic-modal-backdrop" id="disconnect-modal-backdrop"></div>
                <div class="alt-magic-modal-content alt-magic-modal-content-small">
                    <div class="alt-magic-modal-header">
                        <h2>Disconnect Account</h2>
                        <button type="button" class="alt-magic-modal-close" id="disconnect-modal-close" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alt-magic-modal-body">
                        <div class="alt-magic-disconnect-modal-content">
                            <div class="alt-magic-disconnect-modal-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="#dc2626" stroke-width="1.5" fill="none"/>
                                    <path d="M12 8V12" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="12" cy="16" r="1" fill="#dc2626"/>
                                </svg>
                            </div>
                            <h3 class="alt-magic-disconnect-modal-title">Are you sure you want to disconnect?</h3>
                            <p class="alt-magic-disconnect-modal-message">
                                Disconnecting your account will disable all Alt Magic features on your WordPress site. You can reconnect anytime.
                            </p>
                            <div class="alt-magic-disconnect-modal-actions">
                                <button type="button" id="confirm-disconnect" class="alt-magic-button alt-magic-button-danger">
                                    Yes, Disconnect Account
                                </button>
                                <button type="button" id="cancel-disconnect" class="alt-magic-button alt-magic-button-secondary">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Key Flow Modal (Hidden by default, shown when "Connect Existing Account" is clicked) -->
            <div id="api-key-modal" class="alt-magic-modal" style="display: none;">
                <div class="alt-magic-modal-backdrop" id="api-key-modal-backdrop"></div>
                <div class="alt-magic-modal-content">
                    <div class="alt-magic-modal-header">
                        <h2>Connect Using Alt Magic Account</h2>
                        <button type="button" class="alt-magic-modal-close" id="api-key-modal-close" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alt-magic-modal-body">
                        <div class="alt-magic-modal-content-wrapper">
                            <div class="alt-magic-api-key-section">
                                <h2 class="alt-magic-api-key-label">API Key</h2>
                                <div class="alt-magic-api-key-input-group">
                                    <input class="alt-magic-api-key-input" type="password" id="alt_magic_api_key" name="alt_magic_api_key" value="<?php echo esc_attr($api_key); ?>" placeholder="Enter your API key" />
                                    <button type="button" id="verify-api-key" class="alt-magic-verify-button">
                                        Verify
                                    </button>
                                </div>
                                <div id="api-key-status" class="alt-magic-api-key-status">
                        <?php if (false) : // Changed from $is_verified to false to prevent showing verified by default ?>
                            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/altm-green-tick.svg'); ?>" alt="Green Tick" style="width: 20px; height: 20px;">
                            <p style="color: #00B612; font-weight: bold; ">API key is verified.</p>
                        <?php endif; ?>
                                </div>
                                <p class="alt-magic-setting-sub-notice">Don't have an API key? You can generate your API key from your <a href="https://app.altmagic.pro/wordpress-plugin" target="_blank">Alt Magic WordPress Page</a></p>
                            </div>
                        
                            <div id="user-details" style="display: none;" class="alt-magic-user-details-section">
                                <div class="alt-magic-account-section">
                                    <h2 class="alt-magic-section-label">Account</h2>
                                    <div class="alt-magic-account-info">
                                <p class="profile-picture" id="profile-picture"></p>
                                <div>
                                    <h3 id="user-name" style="margin: 0;"></h3>
                                    <p id="user-email" style="margin: 0;"></p>
                                </div>
                            </div>
                                </div>
                                <div class="alt-magic-credits-section">
                                    <h2 class="alt-magic-section-label">Credits Available</h2>
                            <h3 class="credits-available-text" id="credits-available"></h3>
                                </div>
            </div>
            
            <div id="remove-api-key-container" class="remove-api-key-container" style="display: <?php echo !empty($api_key) ? 'block' : 'none'; ?>;">
                <p><button class="remove-api-key-button" type="button" id="remove-api-key">Remove API Key</button> (Removing your API key will disable all Alt Magic features in your WordPress site.)</p>
            </div>
            
            <div id="help-video-container" style="display: none; margin-bottom: 20px;">
                <h2>How to get your API Key?</h2>
                <p>Watch our video tutorial to learn how to get your API key.</p>
                                <div class="alt-magic-video-wrapper">
                                    <iframe width="560" height="315" 
                    src="https://www.youtube.com/embed/shIN7PNR6NE?si=_1zwlM--0efWDa-e" title="Generate API Key with Alt Magic Tutorial" 
                    frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>
                </iframe>
            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- YouTube Video Modal for API Key Generation -->
            <div id="api-key-video-modal" class="alt-magic-modal alt-magic-video-modal" style="display: none;">
                <div class="alt-magic-modal-backdrop" id="api-key-video-modal-backdrop"></div>
                <div class="alt-magic-modal-content alt-magic-video-modal-content">
                    <div class="alt-magic-modal-header">
                        <h2>How to Generate Alt Magic API Key</h2>
                        <button type="button" class="alt-magic-modal-close" id="api-key-video-modal-close" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alt-magic-modal-body">
                        <div class="alt-magic-video-wrapper">
                            <iframe id="api-key-video-iframe" width="100%" height="750" 
                                src="" 
                                title="Generate API Key with Alt Magic Tutorial" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                referrerpolicy="strict-origin-when-cross-origin" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- YouTube Video Modal for Academy Videos -->
            <div id="academy-video-modal" class="alt-magic-modal alt-magic-video-modal" style="display: none;">
                <div class="alt-magic-modal-backdrop" id="academy-video-modal-backdrop"></div>
                <div class="alt-magic-modal-content alt-magic-video-modal-content">
                    <div class="alt-magic-modal-header">
                        <h2 id="academy-video-modal-title">Video Tutorial</h2>
                        <button type="button" class="alt-magic-modal-close" id="academy-video-modal-close" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alt-magic-modal-body">
                        <div class="alt-magic-video-wrapper">
                            <iframe id="academy-video-iframe" width="100%" height="750" 
                                src="" 
                                title="Alt Magic Academy Video" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                referrerpolicy="strict-origin-when-cross-origin" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

// Save API key and user_id via AJAX
function alt_magic_save_api_key() {

    // Check nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'alt_magic_save_api_key_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }   

    if ( isset( $_POST['api_key'] ) && isset( $_POST['user_id'] ) ) {
        $api_key = sanitize_text_field( wp_unslash($_POST['api_key']) );
        $user_id = sanitize_text_field( wp_unslash($_POST['user_id']) );
        
        update_option( 'alt_magic_api_key', $api_key );
        update_option( 'alt_magic_user_id', $user_id );
        update_option( 'alt_magic_account_active', 1 );
    }
    wp_die();
}
add_action('wp_ajax_alt_magic_save_api_key', 'alt_magic_save_api_key');

// Remove API key via AJAX
function alt_magic_remove_api_key() {

    // Check nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'alt_magic_remove_api_key_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    delete_option('alt_magic_api_key');
    delete_option('alt_magic_user_id');
    update_option( 'alt_magic_account_active', 0 );
    wp_die();
}
add_action('wp_ajax_alt_magic_remove_api_key', 'alt_magic_remove_api_key');

// Verify API key via AJAX
function alt_magic_verify_api_key() {
    altm_log('alt_magic_verify_api_key called from php file');
    // Check nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'alt_magic_verify_api_key_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Get and sanitize input
    if (!isset($_POST['api_key'])) {
        wp_send_json_error(array('message' => 'API key is required.'));
        return;
    }

    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
    $domain = get_site_url(); 

    // Make API request to verify the key
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/verify-api-key', array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => wp_json_encode(array(
            'api_key' => $api_key,
            'domain' => $domain,
            'version' => 'new_auto_register'
        )),
        'timeout' => 30,
        'blocking' => true,
        'httpversion' => '1.1',
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'API request failed: ' . $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        wp_send_json_error(array('message' => 'Invalid API key.'));
        return;
    }

    $data = json_decode($response_body, true);
    
    if (!$data || !isset($data['message']) || $data['message'] !== 'API key is valid' || !isset($data['user_id'])) {
        wp_send_json_error(array('message' => 'Invalid API key.'));
        return;
    }

    // Save the API key and user data
    update_option('alt_magic_api_key', $api_key);
    update_option('alt_magic_user_id', $data['user_id']);
    update_option('alt_magic_account_active', 1);

    // Return the success response with user details
    wp_send_json_success($data);
}
add_action('wp_ajax_alt_magic_verify_api_key', 'alt_magic_verify_api_key');

// WordPress auto-register via AJAX
function alt_magic_wp_auto_register() {
    altm_log('alt_magic_wp_auto_register called from php file');
    
    // Check nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'alt_magic_wp_auto_register_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Get current user email and domain
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    $domain = get_site_url();

    altm_log('WordPress auto-register - User email: ' . $email . ', Domain: ' . $domain);

    if (empty($email)) {
        altm_log('WordPress auto-register - Error: User email is empty');
        wp_send_json_error(array('message' => 'User email is required.'));
        return;
    }

    // Make API request to auto-register
    altm_log('WordPress auto-register - Making API request to: ' . ALT_MAGIC_API_BASE_URL . '/wp-auto-register');
    
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/wp-auto-register', array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => wp_json_encode(array(
            'email' => $email,
            'domain' => $domain
        )),
        'timeout' => 30,
        'blocking' => true,
        'httpversion' => '1.1',
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        altm_log('WordPress auto-register - API request error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => 'API request failed: ' . $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    altm_log('WordPress auto-register - API response code: ' . $response_code);
    altm_log('WordPress auto-register - API response body: ' . $response_body);
    
    if ($response_code !== 200) {
        altm_log('WordPress auto-register - Error: Non-200 response code');
        wp_send_json_error(array('message' => 'Registration failed. Please try again.'));
        return;
    }

    $data = json_decode($response_body, true);
    
    if (!$data || !isset($data['api_key']) || !isset($data['user_id']) || !isset($data['user_details'])) {
        altm_log('WordPress auto-register - Error: Invalid response data structure');
        wp_send_json_error(array('message' => 'Invalid response from server. Please try again.'));
        return;
    }

    altm_log('WordPress auto-register - Success: API key received, user_id: ' . $data['user_id']);

    // Save the API key and user data
    update_option('alt_magic_api_key', $data['api_key']);
    update_option('alt_magic_user_id', $data['user_id']);
    update_option('alt_magic_account_active', 1);

    altm_log('WordPress auto-register - Options saved successfully');

    // Return the success response with user details
    wp_send_json_success($data);
}
add_action('wp_ajax_alt_magic_wp_auto_register', 'alt_magic_wp_auto_register');