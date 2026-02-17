<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to get all images and format them into an array
function altm_handle_bulk_image_alt_generation() {

    altm_log('#############################');
    altm_log('Starting bulk generation');

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'bulk_image_alt_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    // Validate image_ids POST parameter exists
    if (!isset($_POST['image_ids'])) {
        wp_send_json_error(array('message' => 'Missing image_ids parameter.'));
        return;
    }

    $image_data = json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['image_ids']))), true); // Decode and sanitize JSON string
    $image_ids = array_map(function($image) {
        return array(
            'attachment_id' => absint($image['attachment_id']),
            'image_url' => esc_url_raw($image['image_url'])
        );
    }, $image_data);
    $images_data = array();

    altm_log('image_ids: ' . print_r($image_ids, true));

    foreach ($image_ids as $image) {   
        $result = altm_generate_alt_text($image['attachment_id']);
        if ($result[0] === true) {
            $alt_text = $result[1];
            $error = '';
            $status_code = null;
        } else {
            $alt_text = '';
            // Check if this is an authentication error (index 3 contains status code)
            if (isset($result[3]) && $result[3] === 403) {
                $error = isset($result[2]) ? $result[2] : $result[1];
                $status_code = 403;
            } else {
                $error = $result[1];
                $status_code = null;
            }
        }
        
        $image_data_item = array(
            'attachment_id' => $image['attachment_id'],
            'image_url' => $image['image_url'], // Include image_url in the response
            'processed' => $result[0],
            'alt_text' => $alt_text,
            'error' => $error
        );
        
        // Add status_code if it's an authentication error
        if ($status_code !== null) {
            $image_data_item['status_code'] = $status_code;
        }
        
        $images_data[] = $image_data_item;
    }

    echo wp_json_encode($images_data); // Use wp_json_encode() instead of json_encode()
    wp_die(); // Always include this in AJAX functions
}

add_action('wp_ajax_altm_handle_bulk_image_alt_generation', 'altm_handle_bulk_image_alt_generation');

?>
