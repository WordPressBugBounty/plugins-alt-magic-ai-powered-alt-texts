<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch processed images data from API
 */
function altm_get_processed_images_data() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_get_processed_images_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }
    
    $user_id = get_option('alt_magic_user_id');
    $api_key = get_option('alt_magic_api_key');
    
    // Check if API key exists
    if (empty($api_key)) {
        wp_send_json(array(
            'success' => false,
            'message' => 'No Alt Magic account connected. Please connect your account in Account Settings.',
            'status_code' => 403
        ));
        return;
    }

    $website_url = get_site_url();
    //$website_url = 'https://altmagic.pro';

    if(strpos($website_url, 'localhost') !== false){
        $website_url = '';
    }
    
    // API request
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/user-images-data', array(
        'method' => 'POST',
        'headers' => array(
                'Content-Type' => 'application/json', 
                'Authorization' => 'Bearer ' . $api_key
            ),
        'body' => json_encode(array(
            'user_id' => $user_id,
            'api_source' => 'wordpress',
            'website_url' => $website_url
        ))
    ));
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        altm_log('Error fetching processed images: ' . $error_message);
        wp_send_json_error(array('message' => 'API request failed: ' . $error_message));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    // Check for 403 authentication errors
    if ($response_code === 403) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Authentication failed';
        altm_log('API returned 403 Forbidden: ' . $error_message);
        wp_send_json(array(
            'success' => false,
            'message' => $error_message,
            'status_code' => 403
        ));
        return;
    }
    
    if ($response_code !== 200) {
        altm_log('API returned non-200 response code: ' . $response_code);
        wp_send_json_error(array('message' => 'API returned error code: ' . $response_code));
        return;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!$data) {
        altm_log('Failed to decode API response');
        wp_send_json_error(array('message' => 'Invalid API response'));
        return;
    }

    altm_log('Processed images data: ' . print_r($data, true));
    
    // Process alt text to prevent over-escaping of quotes
    if (isset($data['user_images_data']) && is_array($data['user_images_data'])) {
        foreach ($data['user_images_data'] as &$image) {
            if (isset($image['alt_text'])) {
                // Ensure we're working with the raw string, not an already-escaped version
                $image['alt_text'] = wp_kses($image['alt_text'], array());
            }
        }
        unset($image); // Break the reference
    }
    
    altm_log('Successfully fetched processed images data: ' . count($data['user_images_data']) . ' images found.');
    
    // Send JSON response with flags to prevent auto-escaping
    wp_send_json($data);
}
add_action('wp_ajax_altm_get_processed_images_data', 'altm_get_processed_images_data');


/**
 * Get attachment URL by ID
 * Used for getting image URLs on localhost environments
 */
function altm_get_attachment_url() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_get_attachment_url_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }
    
    // Validate required parameters
    if (!isset($_POST['attachment_id'])) {
        wp_send_json_error(array('message' => 'Missing attachment ID.'));
        return;
    }
    
    $attachment_id = absint($_POST['attachment_id']);
    
    // Check if the attachment exists and is an image
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        altm_log('Attachment not found for ID: ' . $attachment_id);
        wp_send_json_error(array(
            'message' => 'Attachment not found.',
            'attachment_id' => $attachment_id
        ));
        return;
    }
    
    // Check if it's an image
    if ($attachment->post_type !== 'attachment' || strpos($attachment->post_mime_type, 'image/') !== 0) {
        altm_log('Attachment is not an image. Type: ' . $attachment->post_type . ', Mime: ' . $attachment->post_mime_type);
        wp_send_json_error(array(
            'message' => 'Attachment is not an image.',
            'attachment_id' => $attachment_id,
            'post_type' => $attachment->post_type,
            'mime_type' => $attachment->post_mime_type
        ));
        return;
    }
    
    // Try different methods to get the URL
    $url = wp_get_attachment_url($attachment_id);
    
    if (!$url) {
        // Try various image sizes as fallbacks
        foreach (array('full', 'large', 'medium', 'thumbnail') as $size) {
            $image_src = wp_get_attachment_image_src($attachment_id, $size);
            if ($image_src && isset($image_src[0])) {
                $url = $image_src[0];
                break;
            }
        }
    }
    
    if ($url) {
        altm_log('Successfully retrieved URL for attachment ID: ' . $attachment_id . ' - ' . $url);
        wp_send_json_success(array(
            'url' => $url,
            'attachment_id' => $attachment_id
        ));
    } else {
        altm_log('Failed to get URL for attachment ID: ' . $attachment_id);
        wp_send_json_error(array(
            'message' => 'Attachment URL not found.',
            'attachment_id' => $attachment_id
        ));
    }
}
add_action('wp_ajax_altm_get_attachment_url', 'altm_get_attachment_url'); 