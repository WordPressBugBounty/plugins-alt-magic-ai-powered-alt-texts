<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function altm_render_image_renaming_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $fetch_credits_nonce = wp_create_nonce('altm_fetch_user_credits_nonce');
    $rename_image_nonce = wp_create_nonce('altm_rename_image_nonce');
    $save_settings_nonce = wp_create_nonce('alt_magic_save_settings');

    $is_account_active = get_option('alt_magic_account_active');
    
    // Get the AJAX URL using WordPress function to ensure compatibility with all configurations
    $ajax_url = admin_url('admin-ajax.php');
    
    // Enqueue the JavaScript file
    wp_enqueue_script('altm-image-renaming-script', plugin_dir_url(__FILE__) . '../scripts/altm-image-renaming-page-script.js', array('jquery'), '1.0.0', true);
    
    // Enqueue the CSS file (reuse the image processing CSS)
    wp_enqueue_style('altm-image-renaming-style', plugin_dir_url(__FILE__) . '../css/altm-image-processing-page.css', array(), '1.0.0');
    
    // Get user email for purchase link
    $user_email = get_option('alt_magic_user_id', '');
    $purchase_url = !empty($user_email) 
        ? 'https://altmagic.pro/?wp_email=' . urlencode($user_email) . '#pricing'
        : 'https://altmagic.pro/#pricing';
    
    // Localize script to pass PHP variables to JavaScript
    wp_localize_script('altm-image-renaming-script', 'altmImageRenaming', array(
        'ajaxUrl' => $ajax_url,
        'fetchCreditsNonce' => $fetch_credits_nonce,
        'renameImageNonce' => $rename_image_nonce,
        'saveSettingsNonce' => $save_settings_nonce,
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


        <div id="altm-image-renaming-tabs" class="nav-tab-wrapper">
            <a href="#bad-names" class="nav-tab nav-tab-active" data-tab="bad-names">Images with Bad Name <span id="bad-names-count" class="altm-tag orange">0</span></a>
            <a href="#all-images" class="nav-tab" data-tab="all-images">All Images <span id="all-images-count" class="altm-tag blue">0</span></a>
        </div>

        <div id="tab-content-bad-names" class="tab-content active">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <input type="text" id="search-bad-names" placeholder="Search by filename or ID..." style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1; display: flex; justify-content: flex-end;">
                        <div style="display: inline-flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; border: 1px solid #cfe5fa; background: linear-gradient(135deg, #f1f7ff 0%, #e7f1ff 100%);">
                            <span style="background: #fef3c7; color: #92400e; border: 1px solid #facc15; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase;">Coming soon</span>
                            <div>
                                <button class="button button-primary" id="bulk-rename-selected-bad-names" disabled>🔒 Rename selected images (<span class="selected-count">0</span>)</button>
                                <button class="button button-primary" id="bulk-rename-all-bad-names" disabled>🔒 Rename all (<span class="total-count">0</span>)</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <div style="margin-bottom: 10px; color: #666; font-size: 13px; text-align: center;">
                    <strong>Bad name patterns:</strong> screenshot, image, photo, IMG_*, DSC_*, dates, numbers only, etc.
                </div> -->
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-bad-names" /></th>
                        <th width="80">ID</th>
                        <th width="120" style="padding-right: 20px;">Image</th>
                        <th width="350" style="padding-left: 20px; padding-right: 20px;">Filename</th>
                        <th width="200">Actions</th>
                    </tr>
                </thead>
                <tbody id="bad-names-list">
                </tbody>
            </table>
            
            <div class="altm-pagination-container">
                <div class="altm-pagination-controls">
                    <div class="page-size-container">
                        <label for="page-size-bad-names">Images per page:</label>
                        <select id="page-size-bad-names">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-content-all-images" class="tab-content">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px; position: relative;">
                        <input type="text" id="search-images" placeholder="Search by filename or ID..." style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; height: 36px; box-sizing: border-box;">
                        <div style="position: relative;">
                            <button type="button" id="image-type-filter-btn" class="button" style="padding: 0; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 4px; display: flex; align-items: center; justify-content: center; height: 36px; width: 36px; min-width: 36px; box-sizing: border-box;">
                                <span class="dashicons dashicons-filter" id="image-type-filter-icon" style="font-size: 16px; width: 16px; height: 16px; line-height: 1; color: #787c82;"></span>
                            </button>
                            <div id="image-type-filter-dropdown" style="display: none; position: absolute; top: 100%; left: 0; margin-top: 4px; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 1000; min-width: 200px;">
                                <div style="padding: 8px 12px; border-bottom: 1px solid #eee; font-weight: 600; color: #333; font-size: 13px;">Image Type</div>
                                <label style="display: block; padding: 10px 12px; cursor: pointer; transition: background 0.2s;">
                                    <input type="radio" name="image-type-filter" value="" checked style="margin-right: 8px;">
                                    <span>All Images</span>
                                </label>
                                <label style="display: block; padding: 10px 12px; cursor: pointer; transition: background 0.2s;">
                                    <input type="radio" name="image-type-filter" value="featured" style="margin-right: 8px;">
                                    <span>Featured Images</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div style="flex: 1; display: flex; justify-content: flex-end;">
                        <div style="display: inline-flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; border: 1px solid #cfe5fa; background: linear-gradient(135deg, #f1f7ff 0%, #e7f1ff 100%);">
                            <span style="background: #fef3c7; color: #92400e; border: 1px solid #facc15; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase;">Coming soon</span>
                            <div>
                                <button class="button button-primary" id="bulk-rename-selected-all-images" disabled>🔒 Rename selected images (<span class="selected-count">0</span>)</button>
                                <button class="button button-primary" id="bulk-rename-all-all-images" disabled>🔒 Rename all (<span class="total-count">0</span>)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-all-images" /></th>
                        <th width="80">ID</th>
                        <th width="120" style="padding-right: 20px;">Image</th>
                        <th width="350" style="padding-left: 20px; padding-right: 20px;">Filename</th>
                        <th width="200">Actions</th>
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
                        </select>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Edit Filename Modal -->
    <div id="edit-filename-modal" class="altm-modal" style="display: none;">
        <div class="altm-modal-content">
            <div class="altm-modal-header">
                <h2 id="edit-modal-title">Edit Filename</h2>
                <span id="close-edit-modal" class="altm-modal-close">&times;</span>
            </div>
            
            <div class="altm-modal-body">
                <div class="altm-modal-image-preview">
                    <img id="edit-image-preview" src="" alt="Image preview" style="max-width: 100%; max-height: 150px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                </div>
                
                <label for="new-filename-input">New Filename:</label>
                <input type="text" id="new-filename-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; margin-bottom: 5px;" placeholder="Enter new filename...">
                <p style="margin: 0; color: #666; font-size: 12px;">Note: File extension will be preserved automatically</p>
            </div>
            
            <div class="altm-modal-footer">
                <button id="save-filename" class="button button-primary">Save</button>
                <button id="cancel-edit" class="button">Cancel</button>
            </div>
            
            <!-- Edit Progress -->
            <div id="edit-progress" style="display: none; margin-top: 20px; text-align: center;">
                <span class="spinner is-active"></span>
                <p>Updating filename...</p>
            </div>
        </div>
    </div>

    <!-- Bulk Processing Modal -->
    <div id="bulk-processing-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 500px; max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Renaming Images...</h3>
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
                            <tr style="display: none;" id="no-failed-images">
                                <td colspan="3" style="text-align: center; padding: 20px; color: #666; font-style: italic;">No failed images yet</td>
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
