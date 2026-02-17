<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates alt text for an attachment via AJAX.
 */
function altm_generate_alt_text_ajax_handler() {
    altm_log('altm_generate_alt_text_ajax_handler called');
    
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Check for the required POST parameter
    if (!isset($_POST['attachment_id'])) {
        wp_send_json_error(array('message' => 'No attachment ID provided.'));
        return;
    }

    $attachment_id = absint($_POST['attachment_id']);
    
    // Sanitize and validate source parameter
    $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'unknown';
    $allowed_sources = array('image_processing_page', 'image_library_popup', 'bulk_generation', 'media_library', 'upload', 'unknown');
    if (!in_array($source, $allowed_sources, true)) {
        $source = 'unknown';
    }

    altm_log('Source: ' . $source);

    // Proceed to generate alt text
    $result = altm_generate_alt_text($attachment_id, $source);

    if ($result[0] === false) {
        // Check if we have additional error information (message and status code)
        $error_data = array('message' => $result[1]);
        
        // If we have a detailed error message (index 2) and status code (index 3)
        if (isset($result[2])) {
            $error_data['message'] = $result[2];
        }
        if (isset($result[3])) {
            $error_data['status_code'] = $result[3];
        }
        
        // For authentication errors, send with success: false and status_code
        if (isset($result[3]) && $result[3] == 403) {
            wp_send_json(array(
                'success' => false,
                'message' => $error_data['message'],
                'status_code' => 403
            ));
        } else {
            wp_send_json_error($error_data);
        }
    } else {
                
        // Prepare response data
        $response_data = array(
            'alt_text' => $result[1],
            'more_options' => array(
                // 'alt_magic_use_for_title' => get_option('alt_magic_use_for_title'),
                'alt_magic_use_for_caption' => get_option('alt_magic_use_for_caption'),
                'alt_magic_use_for_description' => get_option('alt_magic_use_for_description')
            )
        );
        
        wp_send_json_success($response_data);
    }
}
add_action('wp_ajax_altm_generate_alt_text_ajax', 'altm_generate_alt_text_ajax_handler');

/**
 * Generates alt text for multiple attachments in parallel via AJAX.
 */
function altm_generate_alt_text_batch_ajax_handler() {
    altm_log('altm_generate_alt_text_batch_ajax_handler called');
    
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Check for the required POST parameter
    if (!isset($_POST['attachment_ids']) || !is_array($_POST['attachment_ids'])) {
        wp_send_json_error(array('message' => 'No attachment IDs provided.'));
        return;
    }

    $attachment_ids = array_map('intval', $_POST['attachment_ids']);
    
    if (empty($attachment_ids)) {
        wp_send_json_error(array('message' => 'No valid attachment IDs provided.'));
        return;
    }

    // Process multiple images in parallel using the function from altm-alt-text-generator.php
    $results = altm_generate_alt_text_batch($attachment_ids);
    
    wp_send_json_success($results);
}
add_action('wp_ajax_altm_generate_alt_text_batch_ajax', 'altm_generate_alt_text_batch_ajax_handler');

/**
 * Updates alt text for an attachment via AJAX (simple update without API call).
 */
function altm_update_alt_text_simple_ajax_handler() {
    altm_log('Manual alt text update called from Image Processing Page');
    
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Check for the required POST parameters
    if (!isset($_POST['attachment_id']) || !isset($_POST['alt_text'])) {
        wp_send_json_error(array('message' => 'Missing required parameters.'));
        return;
    }

    $attachment_id = absint($_POST['attachment_id']);
    $alt_text = sanitize_text_field(wp_unslash($_POST['alt_text']));

    // Update the alt text in attachment meta
    altm_log('Updating attachment alt text in Media Library');
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);    
    
    // Update the alt text in all posts/pages where this image is used
    altm_log('Updating attachment alt text in all posts/pages containing this image');
    altm_update_alt_text_in_all_posts($attachment_id, $alt_text);
    
    altm_log('Updation finished');     

    wp_send_json_success(array(
        'message' => 'Alt text updated successfully.',
        'alt_text' => $alt_text
    ));
}
add_action('wp_ajax_altm_update_alt_text', 'altm_update_alt_text_simple_ajax_handler');

?>
