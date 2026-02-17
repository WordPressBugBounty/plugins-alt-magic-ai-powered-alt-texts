<?php

// This page is not in use anymore.
// It is replaced by the altm-image-processing-page.php page.

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Render plugin bulk generation page
function alt_magic_render_bulk_generation_page() {

    $image_stats = altm_get_image_stats();
    $is_account_active = get_option('alt_magic_account_active');
    
    // Enqueue the CSS file with a version number
    //altm_log('Enqueueing bulk generation page CSS');
    wp_enqueue_style(
        'alt-magic-media-popup-button-css',
        plugin_dir_url(__FILE__) . '../css/altm-bulk-generation-page.css',
        array(), // Dependencies
        '2.1.0'  // Version number - updated to force cache refresh
    );

    // Register and enqueue the JavaScript file
    wp_register_script(
        'alt-magic-bulk-generation-js',
        esc_url(plugin_dir_url(__FILE__) . '../scripts/altm-bulk-generation-page-script.js'),
        array('jquery'), // Dependencies
        '2.1.0', // Version number - updated to force cache refresh
        true // Load in footer
    );
    wp_enqueue_script('alt-magic-bulk-generation-js');

    // Get user email for purchase link
    $user_email = get_option('alt_magic_user_id', '');
    $purchase_url = !empty($user_email) 
        ? 'https://altmagic.pro/?wp_email=' . urlencode($user_email) . '#pricing'
        : 'https://altmagic.pro/#pricing';
    
    // Pass data to the JavaScript file
    wp_localize_script('alt-magic-bulk-generation-js', 'altMagicBulkGeneration', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bulk_image_alt_nonce'),
        'getImageWithoutAltTextsNonce' => wp_create_nonce('get_image_without_alt_texts_nonce'),
        'isAccountActive' => get_option('alt_magic_account_active') ? true : false,
        'imageCount' => esc_js($image_stats['images_with_missing_alt']),
        'userId' => esc_js(get_option('alt_magic_user_id'))
    ));

    // Generate a nonce for the AJAX request
    $bulk_image_alt_nonce = wp_create_nonce('bulk_image_alt_nonce');
    $get_image_without_alt_texts_nonce = wp_create_nonce('get_image_without_alt_texts_nonce');

    ?>
    <div class="wrap">

        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="main-content">

            <div class="info-cards-container">
                <div class="card card-total-images">
                    <div class="card-header">
                        <p>Total Images in Library</p>
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/altm-images-icon.svg'); ?>" alt="Total Images Icon" class="card-icon">
                    </div>
                    <h3><?php echo esc_html(number_format($image_stats['total_images'])); ?></h3>
                </div>
                <div class="card card-images-with-missing-alt">
                    <div class="card-header">
                        <p>Images with Missing Alt Text</p>
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/altm-missing-alt-icon.svg'); ?>" alt="Missing Alt Text Icon" class="card-icon">
                    </div>
                    <h3><?php echo esc_html(number_format($image_stats['images_with_missing_alt'])); ?></h3>
                </div>
            </div>

            <div class="bulk-generation-settings-container">
                <p>Note: This process will only generate alt text for images that are missing alt text.</p>
                <!-- <div class="bulk-generation-settings-header">
                    <h2>Bulk Generation Settings</h2>
                    <div class="bulk-generation-settings-actions">
                        <div class="bulk-generation-settings-action-item" style="margin-bottom: 20px;">
                            <label class="switch">
                                <input type="checkbox" id="overwrite-alt-text">
                                <span class="slider round"></span>
                            </label>
                            <p>Include images that already have alt text (overwrite existing alt text).</p>
                        </div>
                    </div>
                </div> -->
            </div>

            <div class="bulk-generation-actions-container">

                <!-- Add progress bar with percentage -->
                <div id="progress-bar-container" style="width: 50%; background-color: #c4c4c4; margin-top: 10px; border: 1px solid #ccc; position: relative; display: none; height: 30px; border-radius: 5px;">
                    <div id="progress-bar" style="width: 0%; height: 30px; background-color: #4caf50; position: absolute; top: 0; left: 0; border-radius: 5px;"></div>
                    <span id="progress-percentage" style="position: absolute; width: 100%; text-align: center; line-height: 30px; color: #fff;">0%</span>
                </div>

                <div id="processed-count" style="display: none;">Processed: 0 images</div>

                <!-- Add a note to inform the user not to close the page -->
                <div class="bulk-generation-settings-action-item" id="close-warning-note" style="margin-bottom: 0px; display: none;">
                    <p style="color: red; font-weight: bold;">Note: Do not close this page while the process is running, or it will stop.</p>
                </div>

                <div class="bulk-generation-settings-action-item">
                    <button class="bulk-generation-settings-action-button" id="generate-bulk-alt-texts">
                        Generate Alt Texts 
                    </button>
                    <button class="bulk-generation-settings-action-button" id="stop-bulk-alt-texts" style="display: none;">
                        Stop
                    </button>
                    <!-- <span id="image-count" style="margin-top: 32px;">[Scope: <?php echo esc_html($image_stats['images_with_missing_alt']); ?> image<?php echo esc_html($image_stats['images_with_missing_alt'] == 1 ? '' : 's'); ?>]</span> -->

                    <div id="spinner" style="display: none;"> 
                        <!-- Loading... -->
                    </div>
                </div>

                <div class="account-info-container">
                    <p id="account-info-text" style="font-size: 14px; color: #333;"><?php 
                    echo wp_kses_post($is_account_active ? 
                    'You have <span class="credits-available-text">... credits</span> remaining in your account. <a target="_blank" href="' . esc_url($purchase_url) . '">Purchase credits in bulk.</a>' 
                    : 'Account is not activated. Please go to <a href="' . esc_url(admin_url('admin.php?page=alt-magic')) . '">Account Settings</a> to activate your account.'); ?></p>
                </div>

            </div>

        </div>


    </div>

    <?php
}