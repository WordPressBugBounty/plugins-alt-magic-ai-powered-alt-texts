<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook to enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'altm_custom_media_popup_button_script');

function altm_custom_media_popup_button_script() {
    //altm_log('Media library script added');
    // Only enqueue the script and style on the media popup page
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($screen->base === 'upload' || ($screen->base === 'post' && $post_id > 0 && get_post_type($post_id) === 'product')) {
            // Enqueue the JavaScript file
            wp_enqueue_script(
                'alt-magic-media-popup-button',
                plugin_dir_url(__FILE__) . '../scripts/altm-media-popup-button.js',
                array('jquery'),
                '1.0.0', // Specify the version number here
                true
            );

            // Localize the script with AJAX URL and nonce
            wp_localize_script(
                'alt-magic-media-popup-button',
                'altmMediaPopup',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'generate_alt_text_nonce' => wp_create_nonce('generate_alt_text_nonce'),
                    'account_settings_url' => admin_url('admin.php?page=alt-magic'),
                )
            );

            // Enqueue the CSS file
            wp_enqueue_style(
                'alt-magic-media-popup-button-css',
                plugin_dir_url(__FILE__) . '../css/altm-media-popup-button.css',
                array(),
                '1.0.0' // Specify the version number here
            );
        }
    }
}

/**
 * Add "Generate Alt Text" button to the edit attachment screen
 */
function altm_add_generate_button_to_edit_attachment() {
    // Enqueue the script for edit attachment page
    add_action('admin_enqueue_scripts', 'altm_enqueue_edit_attachment_script');
}
add_action('admin_init', 'altm_add_generate_button_to_edit_attachment');

/**
 * Enqueue script for edit attachment page
 */
function altm_enqueue_edit_attachment_script($hook) {
    global $post;
    
    // Only on attachment edit screen
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
    if ($hook === 'post.php' && isset($_GET['post']) && $action === 'edit') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
        $post_id = absint($_GET['post']);
        $post_type = get_post_type($post_id);
        $is_image = wp_attachment_is_image($post_id);
        
        if ($post_type !== 'attachment' || !$is_image) {
            return;
        }
        
        // Enqueue the script
        wp_enqueue_script(
            'alt-magic-edit-attachment-button',
            plugin_dir_url(__FILE__) . '../scripts/altm-edit-attachment-button.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize the script with data
        wp_localize_script(
            'alt-magic-edit-attachment-button',
            'altm_data',
            array(
                'generate_alt_text_nonce' => wp_create_nonce('generate_alt_text_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'account_settings_url' => admin_url('admin.php?page=alt-magic'),
            )
        );
    }
}

/**
 * The old inline script function is no longer needed,
 * as we now use an external JS file
 */
function altm_add_generate_alt_text_button_to_edit_page() {
    // This function is kept empty for backward compatibility
    // The functionality has been moved to the external JS file
}

/**
 * Add "Generate Alt Text" button to the media list table (grid and list view)
 */
function altm_add_generate_button_to_media_list() {
    // Add a button column to the media list table
    add_filter('manage_media_columns', function($columns) {
        $columns['altm_alt_text'] = 'Alt Text';
        $columns['altm_generate'] = 'Alt Magic';
        return $columns;
    });
    
    // Make the alt text column sortable
    add_filter('manage_upload_sortable_columns', function($columns) {
        $columns['altm_alt_text'] = 'alt_text';
        return $columns;
    });
    
    // Add sorting functionality by alt text
    add_action('pre_get_posts', function($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('orderby') === 'alt_text') {
            $query->set('meta_key', '_wp_attachment_image_alt');
            $query->set('orderby', 'meta_value');
        }
    });
    
    // Add custom CSS for the alt text column
    add_action('admin_head', function() {
        ?>
        <style type="text/css">
            .column-altm_alt_text {
                width: 20%; /* Set width for alt text column */
                overflow-wrap: break-word;
            }
            
            .column-altm_generate {
                width: 12%; /* Set width for alt magic column */
                text-align: center;
            }
            
            /* Alt text styling */
            .altm-alt-text-truncated {
                position: relative;
                cursor: pointer;
            }
            
            .altm-alt-text-full {
                display: none;
                position: absolute;
                top: 30px;
                left: 0;
                background: #fff;
                border: 1px solid #ddd;
                padding: 10px;
                width: 300px;
                z-index: 100;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
                text-align: left;
            }
            
            .altm-alt-text-truncated:hover .altm-alt-text-full {
                display: block;
            }
        </style>
        <?php
    });
    
    // Callback to display alt text in the custom column
    add_action('manage_media_custom_column', function($column_name, $post_id) {
        if ('altm_alt_text' === $column_name) {
            $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                // If alt text is long, show it truncated with a hover-to-view-full feature
                if (mb_strlen($alt_text) > 50) {
                    echo '<div class="altm-alt-text-truncated">';
                    echo esc_html(mb_substr($alt_text, 0, 50) . '...');
                    echo '<div class="altm-alt-text-full">' . esc_html($alt_text) . '</div>';
                    echo '</div>';
                } else {
                    echo esc_html($alt_text);
                }
            } else {
                echo '<span style="color:#999;font-style:italic;">No alt text</span>';
            }
        }
    }, 10, 2);
    
    // Add the button to the column
    add_action('manage_media_custom_column', function($column_name, $post_id) {
        if ($column_name !== 'altm_generate' || !wp_attachment_is_image($post_id)) {
            return;
        }
        
        echo '<button type="button" class="button button-primary altm-media-list-generate" data-id="' . esc_attr($post_id) . '">Generate Alt Text</button>';
        echo '<span class="loader altm-media-list-spinner" style="display: none;"></span>';
    }, 10, 2);
    
    // Enqueue the media list script
    add_action('admin_enqueue_scripts', 'altm_enqueue_media_list_script');
}
add_action('admin_init', 'altm_add_generate_button_to_media_list');

/**
 * Enqueue script for media list
 */
function altm_enqueue_media_list_script($hook) {
    if ($hook !== 'upload.php') {
        return;
    }
    
    // Enqueue the script
    wp_enqueue_script(
        'alt-magic-media-list-button',
        plugin_dir_url(__FILE__) . '../scripts/altm-media-list-button.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Localize the script with data
    wp_localize_script(
        'alt-magic-media-list-button',
        'altm_media_data',
        array(
            'generate_alt_text_nonce' => wp_create_nonce('generate_alt_text_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'account_settings_url' => admin_url('admin.php?page=alt-magic'),
        )
    );
}

// Enqueue scripts for attachment edit page
add_action('admin_enqueue_scripts', 'altm_enqueue_edit_attachment_scripts');

function altm_enqueue_edit_attachment_scripts($hook) {
    global $post;
    
    // Debug hook (limit noisy logs to relevant screens only)
    if ($hook === 'post.php' || $hook === 'upload.php') {
        altm_log('Alt Magic: Enqueuing scripts for hook: ' . $hook);
    }
    
    // Only on attachment edit screen or media library
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
    if (($hook === 'post.php' && isset($_GET['post']) && $action === 'edit') ||
        $hook === 'upload.php') {
        
        if ($hook === 'post.php') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce verification needed for conditional script loading in admin
            $post_id = absint($_GET['post']);
            $post_type = get_post_type($post_id);
            $is_image = wp_attachment_is_image($post_id);
            
            altm_log('Alt Magic: post.php - Post ID: ' . $post_id . ', Post Type: ' . $post_type . ', Is Image: ' . ($is_image ? 'Yes' : 'No'));
            
            if ($post_type !== 'attachment' || !$is_image) {
                altm_log('Alt Magic: Skipping - Not an image attachment');
                return;
            }
        }
        
        altm_log('Alt Magic: Enqueuing jQuery and admin styles');
        
        wp_enqueue_script('jquery');
        
        // Note: ajaxurl is already available in WordPress admin, no need to localize
        
    }
}

/**
 * Enqueue scripts for post editor pages
 */
function altm_enqueue_post_editor_scripts($hook) {
    // Only on post edit screen
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        // Enqueue the script
        wp_enqueue_script(
            'alt-magic-post-editor-button',
            plugin_dir_url(__FILE__) . '../scripts/altm-post-editor-button.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize the script with data
        wp_localize_script(
            'alt-magic-post-editor-button',
            'altm_post_data',
            array(
                'generate_alt_text_nonce' => wp_create_nonce('generate_alt_text_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'account_settings_url' => admin_url('admin.php?page=alt-magic'),
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'altm_enqueue_post_editor_scripts');
