<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function altm_render_image_processing_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $generate_alt_text_nonce = wp_create_nonce('generate_alt_text_nonce');
    $fetch_credits_nonce = wp_create_nonce('altm_fetch_user_credits_nonce');

    $is_account_active = get_option('alt_magic_account_active');
    
    // Get the AJAX URL using WordPress function to ensure compatibility with all configurations
    $ajax_url = admin_url('admin-ajax.php');
    
    // Enqueue the JavaScript file
    wp_enqueue_script('altm-image-processing-script', plugin_dir_url(__FILE__) . '../scripts/altm-image-processing-page-script.js', array('jquery'), '1.0.0', true);
    
    // Enqueue the CSS file
    wp_enqueue_style('altm-image-processing-style', plugin_dir_url(__FILE__) . '../css/altm-image-processing-page.css', array(), '1.0.0');
    
    // Get user email for purchase link
    $user_email = get_option('alt_magic_user_id', '');
    $purchase_url = !empty($user_email) 
        ? 'https://altmagic.pro/?wp_email=' . urlencode($user_email) . '#pricing'
        : 'https://altmagic.pro/#pricing';
    
    // Localize script to pass PHP variables to JavaScript
    wp_localize_script('altm-image-processing-script', 'altmImageProcessing', array(
        'ajaxUrl' => $ajax_url,
        'fetchCreditsNonce' => $fetch_credits_nonce,
        'generateAltTextNonce' => $generate_alt_text_nonce,
        'maxConcurrency' => get_option('alt_magic_max_concurrency', 3),
        'accountSettingsUrl' => admin_url('admin.php?page=alt-magic'),
        'hasApiKey' => !empty(get_option('alt_magic_api_key')),
        'userEmail' => get_option('alt_magic_user_id', '')
    ));
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Credits Display Section -->
        <div class="account-info-container" style="margin: 10px 0; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
            <p id="account-info-text" style="font-size: 14px; color: #333; margin: 0;"><?php 
            echo wp_kses_post($is_account_active ? 
            'You have <span class="credits-available-text">... credits</span> remaining in your account. <a target="_blank" href="' . esc_url($purchase_url) . '">Purchase credits in bulk.</a>' 
            : 'Account is not activated. Please go to <a href="' . esc_url(admin_url('admin.php?page=alt-magic')) . '">Account Settings</a> to activate your account.'); ?></p>
        </div>

        <div id="altm-image-processing-tabs" class="nav-tab-wrapper">
            <a href="#images-with-empty-alt" class="nav-tab nav-tab-active" data-tab="empty-alt">Images with Empty Alt <span id="empty-alt-count" class="altm-tag red">0</span></a>
            <a href="#images-with-short-alt" class="nav-tab" data-tab="short-alt">Images with Short Alt <span id="short-alt-count" class="altm-tag orange">0</span></a>
            <a href="#all-images" class="nav-tab" data-tab="all-images">All Images <span id="all-images-count" class="altm-tag blue">0</span></a>
        </div>

        <div id="tab-content-empty-alt" class="tab-content active">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <input type="text" id="search-empty-alt" placeholder="Search by ID or alt text..." style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button class="button button-primary" id="bulk-generate-selected-empty-alt" disabled>Generate for selected images (<span class="selected-count">0</span>)</button>
                        <button class="button button-primary" id="bulk-generate-all-empty-alt">Generate for all (<span class="total-count">0</span>)</button>
                    </div>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-empty-alt" /></th>
                        <th width="80">ID</th>
                        <th width="120" style="padding-right: 20px;">Image</th>
                        <th width="350" style="padding-left: 20px; padding-right: 20px;">Alt Text</th>
                        <th width="250">Actions</th>
                    </tr>
                </thead>
                <tbody id="empty-alt-images-list">
                </tbody>
            </table>
            <div class="altm-pagination-container">
                <div class="altm-pagination-controls">
                    <div class="page-size-container">
                        <label for="page-size-empty-alt">Images per page:</label>
                        <select id="page-size-empty-alt">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                                    <option value="500">500</option>
                                    <option value="all">All images</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div id="tab-content-short-alt" class="tab-content">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <input type="text" id="search-short-alt" placeholder="Search by ID or alt text..." style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button class="button button-primary" id="bulk-generate-selected-short-alt" disabled>Generate for selected images (<span class="selected-count">0</span>)</button>
                        <button class="button button-primary" id="bulk-generate-all-short-alt">Generate for all (<span class="total-count">0</span>)</button>
                    </div>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-short-alt" /></th>
                        <th width="80">ID</th>
                        <th width="120" style="padding-right: 20px;">Image</th>
                        <th width="350" style="padding-left: 20px; padding-right: 20px;">Alt Text</th>
                        <th width="250">Actions</th>
                    </tr>
                </thead>
                <tbody id="short-alt-images-list">
                </tbody>
            </table>
            <div class="altm-pagination-container">
                <div class="altm-pagination-controls">
                    <div class="page-size-container">
                        <label for="page-size-short-alt">Images per page:</label>
                        <select id="page-size-short-alt">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                                    <option value="500">500</option>
                                    <option value="all">All images</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div id="tab-content-all-images" class="tab-content">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <input type="text" id="search-all-images" placeholder="Search by ID or alt text..." style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button class="button button-primary" id="bulk-generate-selected-all-images" disabled>Generate for selected images (<span class="selected-count">0</span>)</button>
                        <button class="button button-primary" id="bulk-generate-all-all-images">Generate for all (<span class="total-count">0</span>)</button>
                    </div>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-all-images" /></th>
                        <th width="80">ID</th>
                        <th width="120" style="padding-right: 20px;">Image</th>
                        <th width="350" style="padding-left: 20px; padding-right: 20px;">Alt Text</th>
                        <th width="250">Actions</th>
                    </tr>
                </thead>
                <tbody id="all-images-list">
                </tbody>
            </table>
            <div class="altm-pagination-container">
                <div class="altm-pagination-controls">
                    <div class="page-size-container">
                        <label for="page-size-all-images">Images per page:</label>
                        <select id="page-size-all-images">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                                    <option value="500">500</option>
                                    <option value="all">All images</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Processing Modal -->
    <div id="bulk-processing-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 500px; max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Generating Alt Text...</h3>
                <button id="close-modal" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div>Progress: <span id="progress-text">0 of 0</span> images <span id="progress-spinner"></span></div>
                <div style="width: 100%; background-color: #f0f0f0; border-radius: 4px; margin-top: 5px;">
                    <div id="progress-bar-fill" style="width: 0%; height: 20px; background-color: #2271b1; border-radius: 4px; transition: width 0.3s;"></div>
                </div>
                <div style="text-align: center; margin-top: 5px;"><span id="progress-percentage">0%</span></div>
            </div>
            
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div style="background-color: #d1f2eb; padding: 4px 8px; border-radius: 4px; color: #0e7c55;">✓ Successful: <span id="success-count">0</span></div>
                <div style="background-color: #fdeaea; padding: 4px 8px; border-radius: 4px; color: #c53030;">✗ Failed: <span id="failed-count">0</span></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-weight: bold; margin-bottom: 10px;">Failed Images:</div>
                <div style="height: 120px; overflow-y: auto; border: 1px solid #ddd; background: #f9f9f9;">
                    <table id="failed-images-table" style="width: 100%; font-size: 12px; border-collapse: collapse;">
                        <thead style="background: #e9ecef; position: sticky; top: 0;">
                            <tr>
                                <th style="padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; width: 60px;">Image ID</th>
                                <th style="padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; width: 80px;">Link</th>
                                <th style="padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd;">Error</th>
                            </tr>
                        </thead>
                        <tbody id="failed-images-body">
                            <tr id="no-failed-images">
                                <td colspan="3" style="text-align: center; padding: 20px; color: #666; font-style: italic;">No failed images till now</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Completion Message (Hidden by default) -->
            <div id="completion-message" style="display: none; text-align: center; margin-bottom: 20px; padding: 20px; background: #d1f2eb; border-radius: 4px; border: 1px solid #a7f3d0;">
                <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
                <div style="font-size: 20px; font-weight: bold; color: #0e7c55; margin-bottom: 8px;">Processing Complete!</div>
                <div style="color: #0e7c55; font-size: 14px;">All images have been processed successfully</div>
            </div>
            
            <!-- Credits Depleted Message (Hidden by default) -->
            <div id="credits-depleted-message" style="display: none; text-align: center; margin-bottom: 20px; padding: 20px; background: #fdeaea; border-radius: 4px; border: 1px solid #f5c2c7;">
                <div style="font-size: 32px; margin-bottom: 15px;">⚠️</div>
                <div style="font-size: 20px; font-weight: bold; color: #b70000; margin-bottom: 8px;">Processing Stopped</div>
                <div style="color: #b70000; font-size: 14px; margin-bottom: 15px;">You've run out of credits. Purchase more to continue processing.</div>
                <a href="https://altmagic.pro/?wp_email=<?php echo esc_attr(get_option('alt_magic_user_id', '')); ?>#pricing" target="_blank" class="button button-primary" style="margin-top: 10px;">Purchase Credits</a>
            </div>
            
            <div style="text-align: center;">
                <button id="cancel-processing" class="button">Cancel Processing</button>
            </div>
        </div>
    </div>

    <!-- Authentication Error Modal -->
    <div id="altm-auth-error-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 400px; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #b70000;">⚠️ Authentication Error</h3>
                <button id="close-auth-error-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; line-height: 1;">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px; line-height: 1.6;">
                <p id="auth-error-message" style="margin: 0;">Connection to Alt Magic failed. Please check your API key by going to the Account Settings page.</p>
            </div>
            
            <div style="text-align: end;">
                <button id="dismiss-auth-error" class="button" style="margin-right: 10px;">Dismiss</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=alt-magic')); ?>" class="button button-primary">Go to Account Settings</a>
            </div>
        </div>
    </div>
    <?php
}
