<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Discards accidental output before JSON AJAX responses.
 *
 * Some WordPress hooks/plugins can echo text during metadata or post updates.
 * jQuery then receives valid side effects with a non-JSON response body and
 * reports parsererror. Keep the AJAX response body owned by wp_send_json_*().
 */
function altm_clean_ajax_json_response_buffer($buffer_level, $context) {
    $unexpected_output = '';

    while (ob_get_level() > $buffer_level) {
        $buffer_contents = ob_get_clean();

        if (is_string($buffer_contents) && $buffer_contents !== '') {
            $unexpected_output .= $buffer_contents;
        }
    }

    if ($unexpected_output !== '') {
        $output_summary = trim(wp_strip_all_tags($unexpected_output));
        if (function_exists('altm_log')) {
            altm_log('Suppressed unexpected output before JSON response (' . $context . '): ' . substr($output_summary, 0, 500));
        }
    }
}

function altm_send_clean_json_error($data, $buffer_level, $context) {
    altm_clean_ajax_json_response_buffer($buffer_level, $context);
    wp_send_json_error($data);
}

function altm_send_clean_json_success($data, $buffer_level, $context) {
    altm_clean_ajax_json_response_buffer($buffer_level, $context);
    wp_send_json_success($data);
}

function altm_send_clean_json($data, $buffer_level, $context) {
    altm_clean_ajax_json_response_buffer($buffer_level, $context);
    wp_send_json($data);
}

/**
 * Generates alt text for an attachment via AJAX.
 */
function altm_generate_alt_text_ajax_handler() {
    $altm_json_buffer_level = ob_get_level();
    ob_start();

    altm_log('altm_generate_alt_text_ajax_handler called');

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        altm_send_clean_json_error(array('message' => 'Invalid nonce.'), $altm_json_buffer_level, 'generate_alt_text_ajax');
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        altm_send_clean_json_error(array('message' => 'Insufficient permissions.'), $altm_json_buffer_level, 'generate_alt_text_ajax');
        return;
    }

    // Check for the required POST parameter
    if (!isset($_POST['attachment_id'])) {
        altm_send_clean_json_error(array('message' => 'No attachment ID provided.'), $altm_json_buffer_level, 'generate_alt_text_ajax');
        return;
    }

    $attachment_id = absint($_POST['attachment_id']);

    // Sanitize and validate source parameter
    $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'unknown';
    $allowed_sources = array('image_processing_page', 'image_library_popup', 'image_details_popup', 'image_details_page', 'post_editor_popup', 'post_editor_block', 'bulk_generation', 'media_library', 'media_library_list', 'upload', 'unknown');
    if (!in_array($source, $allowed_sources, true)) {
        $source = 'unknown';
    }

    altm_log('Source: ' . $source);

    // Proceed to generate alt text
    $result = altm_generate_alt_text($attachment_id, $source);

    if (!is_array($result) || !isset($result[0])) {
        altm_log('Alt text generation returned an invalid result for attachment ID: ' . $attachment_id);
        altm_send_clean_json_error(array('message' => 'Failed to generate alt text. Please try again.'), $altm_json_buffer_level, 'generate_alt_text_ajax');
        return;
    }

    if ($result[0] === false) {
        // Check if we have additional error information (message and status code)
        $error_data = array('message' => $result[1]);
        if (isset($result[1]) && is_string($result[1])) {
            $error_data['error_code'] = $result[1];
        }

        // If we have a detailed error message (index 2) and status code (index 3)
        if (isset($result[2])) {
            $error_data['message'] = $result[2];
        }
        if (isset($result[3])) {
            $error_data['status_code'] = $result[3];
        }

        // For authentication errors, send with success: false and status_code
        if (isset($result[3]) && $result[3] == 403) {
            altm_send_clean_json(array(
                'success' => false,
                'message' => $error_data['message'],
                'error_code' => isset($error_data['error_code']) ? $error_data['error_code'] : '',
                'status_code' => 403
            ), $altm_json_buffer_level, 'generate_alt_text_ajax');
        } else {
            altm_send_clean_json_error($error_data, $altm_json_buffer_level, 'generate_alt_text_ajax');
        }
    } else {

        // Prepare response data
        $response_data = array(
            'alt_text' => $result[1],
            'more_options' => array(
                'alt_magic_use_for_title' => get_option('alt_magic_use_for_title'),
                'alt_magic_use_for_caption' => get_option('alt_magic_use_for_caption'),
                'alt_magic_use_for_description' => get_option('alt_magic_use_for_description')
            )
        );

        altm_send_clean_json_success($response_data, $altm_json_buffer_level, 'generate_alt_text_ajax');
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
 * Returns the next attachment IDs for query-based bulk generation without
 * starting generation. The browser advances this cursor before processing the
 * chunk so it can safely continue when a long-running generation request loses
 * its response at the web server or proxy.
 */
function altm_get_alt_text_bulk_query_chunk_ajax_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    $tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $cursor_id = isset($_POST['cursor_id']) ? absint(wp_unslash($_POST['cursor_id'])) : 0;
    $chunk_size = isset($_POST['chunk_size']) ? absint(wp_unslash($_POST['chunk_size'])) : 25;

    if (!in_array($tab, array('empty-alt', 'short-alt', 'all-images'), true)) {
        wp_send_json_error(array('message' => 'Invalid bulk generation tab.'));
        return;
    }

    $attachment_ids = altm_get_image_processing_chunk_ids($tab, $search, $chunk_size, $cursor_id);
    $normalized_chunk_size = min(50, max(1, $chunk_size));

    wp_send_json_success(array(
        'attachment_ids' => $attachment_ids,
        'next_cursor' => !empty($attachment_ids) ? min($attachment_ids) : 0,
        'has_more' => count($attachment_ids) === $normalized_chunk_size,
    ));
}
add_action('wp_ajax_altm_get_alt_text_bulk_query_chunk_ajax', 'altm_get_alt_text_bulk_query_chunk_ajax_handler');

function altm_generate_alt_text_bulk_query_ajax_handler() {
    altm_log('altm_generate_alt_text_bulk_query_ajax_handler called');

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'generate_alt_text_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    $tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $cursor_id = isset($_POST['cursor_id']) ? absint(wp_unslash($_POST['cursor_id'])) : 0;
    $chunk_size = isset($_POST['chunk_size']) ? absint(wp_unslash($_POST['chunk_size'])) : 25;

    if (!in_array($tab, array('empty-alt', 'short-alt', 'all-images'), true)) {
        wp_send_json_error(array('message' => 'Invalid bulk generation tab.'));
        return;
    }

    $attachment_ids = altm_get_image_processing_chunk_ids($tab, $search, $chunk_size, $cursor_id);

    if (empty($attachment_ids)) {
        wp_send_json_success(array(
            'results' => array(),
            'processed_ids' => array(),
            'next_cursor' => 0,
            'has_more' => false,
        ));
        return;
    }

    $results = altm_generate_alt_text_batch($attachment_ids);
    $next_cursor = min($attachment_ids);

    wp_send_json_success(array(
        'results' => $results,
        'processed_ids' => $attachment_ids,
        'next_cursor' => $next_cursor,
        'has_more' => count($attachment_ids) === min(50, max(1, $chunk_size)),
    ));
}
add_action('wp_ajax_altm_generate_alt_text_bulk_query_ajax', 'altm_generate_alt_text_bulk_query_ajax_handler');

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
    if (function_exists('altm_sync_alt_text_to_posts_safely')) {
        altm_sync_alt_text_to_posts_safely($attachment_id, $alt_text);
    } else {
        altm_update_alt_text_in_all_posts($attachment_id, $alt_text);
    }

    altm_log('Updation finished');

    wp_send_json_success(array(
        'message' => 'Alt text updated successfully.',
        'alt_text' => $alt_text
    ));
}
add_action('wp_ajax_altm_update_alt_text', 'altm_update_alt_text_simple_ajax_handler');

?>
