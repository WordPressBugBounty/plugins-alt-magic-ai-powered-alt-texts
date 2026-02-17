<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alt Magic Image Renaming Handler
 * 
 * This file contains all image renaming related functions including:
 * - AI filename generation (with and without context)
 * - File renaming operations
 * - Reference updates
 * - AJAX handlers for renaming operations
 */

/**
 * Generate AI filename from temporary uploaded file (before WordPress processes it)
 * This function works with temporary files during upload, before attachment ID exists
 * 
 * @param string $temp_file_path Path to temporary uploaded file
 * @param string $mime_type MIME type of the file
 * @param array $post_context Optional post context array for enhanced naming
 * @param string $original_filename Original filename of the uploaded file
 * @return string|WP_Error Generated filename or error
 */
function altm_generate_ai_filename_from_temp_file($temp_file_path, $mime_type, $post_context = null, $original_filename = '', $source = 'missing') {
    altm_log('Generating AI filename from temp file...');
    if (!file_exists($temp_file_path)) {
        altm_log('Temporary file not found.');
        return new WP_Error('temp_file_not_found', 'Temporary file not found');
    }
    
    // Prepare the API request
    $api_key = get_option('alt_magic_api_key');
    $user_id = get_option('alt_magic_user_id');
    if (empty($api_key) || empty($user_id)) {
        altm_log('API key or user ID not configured.');
        return new WP_Error('no_api_key', 'API key or user ID not configured');
    }
    
    // Get file extension
    $file_extension = pathinfo($temp_file_path, PATHINFO_EXTENSION);
    
    // Encode image as base64
    $image_content = base64_encode(file_get_contents($temp_file_path));
    $base64_image = 'data:image/' . $file_extension . ';base64,' . $image_content;

    altm_log('Post context: ' . print_r($post_context, true));
    
    // Prepare request body with optional post context
    $request_body = array(
        'image' => $base64_image,
        'image_type' => 'file',
        'image_url' => '',
        'user_id' => $user_id,
        'title' => $post_context['post_title'] ?? '',
        'context' => '',
        'file_extension' => $file_extension,
        'keywords' => $post_context['seo_keywords'] ?? '',
        'image_name' => !empty($original_filename) ? $original_filename : 'temp_upload_' . uniqid() . '.' . $file_extension,
        'image_id' => 0, // No attachment ID yet during upload
        'product_name' => $post_context['woocommerce_product_name'] ?? '',
        'language' => get_option('alt_magic_rename_language', 'en'),
        'language_type' => 'code',
        'site_url' => get_site_url(),
        'purpose' => 'filename_generation',
        'wp_plugin_source' => $source
    );
    
    // Make API request
    $args = array(
        'body' => wp_json_encode($request_body),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout' => 30,
        'blocking' => true,
        'httpversion' => '1.1',
        'sslverify' => false,
    );
    
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/image-name-generator-wp', $args);
    
    if (is_wp_error($response)) {
        return new WP_Error('api_request_failed', 'Failed to contact AI service: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    if ($response_code === 403) {
        // Handle 403 Forbidden (authentication errors)
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Authentication failed';
        return new WP_Error('authentication_error', $error_message, array('status_code' => 403));
    }
    
    if ($response_code !== 200) {
        return new WP_Error('api_error', 'AI service returned error: ' . $response_body);
    }
    
    if (!$response_data || !isset($response_data['image_name'])) {
        return new WP_Error('invalid_response', 'Invalid response from AI service');
    }
    
    $generated_filename = sanitize_file_name($response_data['image_name']);
    
    // Log the generation with context info
    if ($post_context && !empty($post_context['post_title'])) {
        altm_log('AI filename generated with context for post "' . $post_context['post_title'] . '": ' . $generated_filename);
    } else {
        altm_log('AI filename generated from temp file: ' . $generated_filename);
    }
    
    return $generated_filename;
}

/**
 * Generate both alt text and filename using combined API endpoint
 * 
 * @param string $temp_file_path Path to temporary uploaded file
 * @param string $mime_type MIME type of the file
 * @param array $post_context Optional post context array for enhanced processing
 * @param string $original_filename Original filename of the uploaded file
 * @return array|WP_Error Array with 'filename' and 'alt_text' keys, or WP_Error on failure
 */
function altm_generate_combined_alt_and_filename($temp_file_path, $mime_type, $post_context = null, $original_filename = '', $source = 'missing') {
    if (!file_exists($temp_file_path)) {
        return new WP_Error('temp_file_not_found', 'Temporary file not found');
    }
        
    // Get API key and user ID
    $api_key = get_option('alt_magic_api_key');
    $user_id = get_option('alt_magic_user_id');
    if (empty($api_key) || empty($user_id)) {
        return new WP_Error('no_api_key', 'API key or user ID not configured');
    }
    
    // Get file extension
    $file_extension = pathinfo($temp_file_path, PATHINFO_EXTENSION);
    
    // Encode image as base64 (same as alt text generator)
    $image_content = base64_encode(file_get_contents($temp_file_path));
    $base64_image = 'data:image/' . $file_extension . ';base64,' . $image_content;
    
    // Get settings
    $use_seo_keywords = get_option('alt_magic_use_seo_keywords', 0);
    $use_post_title = get_option('alt_magic_use_post_title', 0);
    // Prefer rename-specific Woo settings if set, else fall back to global alt-text setting
    $use_woocommerce_product_name = get_option('alt_magic_rename_use_woocommerce_product_name', null);
    if ($use_woocommerce_product_name === null) {
        $use_woocommerce_product_name = get_option('alt_magic_woocommerce_use_product_name', 0);
    }
    $alt_gen_type = get_option('alt_magic_alt_gen_type', 'default');
    $extra_prompt = get_option('alt_magic_extra_prompt', '');
    $language_code = get_option('alt_magic_language', 'en');
    $rename_use_seo = get_option('alt_magic_rename_use_seo_keywords', null);
    $rename_use_post = get_option('alt_magic_rename_use_post_title', null);
    $rename_language = get_option('alt_magic_rename_language', 'en');
    
    $effective_use_seo = isset($rename_use_seo) && $rename_use_seo !== null ? $rename_use_seo : $use_seo_keywords;
    $effective_use_post = isset($rename_use_post) && $rename_use_post !== null ? $rename_use_post : $use_post_title;
    $parent_post_title = ($effective_use_post && $post_context) ? $post_context['post_title'] : '';
    $seo_keywords = ($effective_use_seo && $post_context) ? $post_context['seo_keywords'] : '';
    $woocommerce_product_name = ($use_woocommerce_product_name && $post_context) ? $post_context['woocommerce_product_name'] : '';
    
    // Prepare request body with optional post context
    $request_body = array(
        'image' => $base64_image,
        'image_type' => 'file',
        'image_url' => '',
        'user_id' => $user_id,
        'title' => $parent_post_title,
        'context' => '',
        'file_extension' => $file_extension,
        'language' => $language_code,
        'keywords' => $seo_keywords,
        'image_name' => !empty($original_filename) ? $original_filename : 'temp_upload_' . uniqid() . '.' . $file_extension,
        'image_id' => 0, // No attachment ID yet during upload
        'product_name' => $woocommerce_product_name,
        'language_type' => 'code',
        'site_url' => get_site_url(),
        'alt_gen_settings_wp' => array(
            'alt_gen_type' => $alt_gen_type,
            'chatgpt_prompt_layer' => $extra_prompt
        ),
        'purpose' => 'alt_and_filename_generation',
        'wp_plugin_source' => $source,
        'rename_language' => $rename_language
    );
    
    // Make API request (same format as alt text generator)
    $args = array(
        'body' => wp_json_encode($request_body),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout' => 60, // Longer timeout for combined processing
        'blocking' => true,
        'httpversion' => '1.1',
        'sslverify' => false,
    );

    altm_log('Sending combined API request...');
    
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/combined-generator-wp', $args);
    
    if (is_wp_error($response)) {
        return new WP_Error('api_request_failed', 'Failed to contact AI service: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        return new WP_Error('api_error', 'AI service returned error: ' . $response_body);
    }
    
    $data = json_decode($response_body, true);
    if (!$data || !isset($data['image_name']) || !isset($data['alt_text'])) {
        return new WP_Error('invalid_response', 'Invalid response from AI service');
    }
    
    $result = array(
        'filename' => sanitize_file_name($data['image_name']),
        'alt_text' => sanitize_text_field($data['alt_text'])
    );
    
    // Log the generation with context info
    if ($post_context && !empty($post_context['post_title'])) {
        altm_log('Combined API response with context for post "' . $post_context['post_title'] . '" - Filename: ' . $result['filename'] . ', Alt text: ' . $result['alt_text']);
    } else {
        altm_log('Combined API response - Filename: ' . $result['filename'] . ', Alt text: ' . $result['alt_text']);
    }
    
    return $result;
}



/**
 * Update all references to an image
 */
function altm_update_image_references($attachment_id, $old_image_urls) {
    global $wpdb;
    
    altm_log("=== REFERENCE UPDATE OVERVIEW ===");
    altm_log("Processing attachment ID: $attachment_id");
    
    // Get new URLs after renaming
    $new_attachment_url = wp_get_attachment_url($attachment_id);
    $new_image_urls = array();
    
    if (wp_attachment_is_image($attachment_id)) {
        $new_image_urls['full'] = $new_attachment_url;
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $new_image_urls[$size_name] = wp_get_attachment_image_url($attachment_id, $size_name);
            }
        }
    } else {
        $new_image_urls['full'] = $new_attachment_url;
    }
    
    // Track all updated post IDs
    $updated_post_ids = array();
    $updated_excerpt_ids = array();
    $updated_meta_ids = array();
    
    // Update references for each size
    foreach ($old_image_urls as $size => $old_url) {
        if (isset($new_image_urls[$size])) {
            $new_url = $new_image_urls[$size];
            
            // Convert URLs to relative paths for more reliable matching
            $old_relative_url = altm_get_relative_url($old_url);
            $new_relative_url = altm_get_relative_url($new_url);
            
            altm_log("Processing size: $size");
            
            if ($old_relative_url && $new_relative_url && $old_relative_url !== $new_relative_url) {
                // Update post content if enabled
                if (get_option('alt_magic_update_posts', 1)) {
                    $post_ids = altm_update_post_content($old_relative_url, $new_relative_url);
                    $updated_post_ids = array_merge($updated_post_ids, $post_ids);
                } else {
                    altm_log("Post content updates disabled - skipping");
                }
                
                // Update post excerpts if enabled
                if (get_option('alt_magic_update_excerpts', 0)) {
                    $excerpt_ids = altm_update_post_excerpts($old_relative_url, $new_relative_url);
                    $updated_excerpt_ids = array_merge($updated_excerpt_ids, $excerpt_ids);
                } else {
                    altm_log("Post excerpt updates disabled - skipping");
                }
                
                // Update post meta if enabled
                if (get_option('alt_magic_update_postmeta', 1)) {
                    $meta_ids = altm_update_post_meta($old_relative_url, $new_relative_url);
                    $updated_meta_ids = array_merge($updated_meta_ids, $meta_ids);
                } else {
                    altm_log("Post meta updates disabled - skipping");
                }
                
            } else {
                altm_log("No URL changes needed for size: $size");
            }
        }
    }
    
    // Remove duplicates and return summary
    $updated_post_ids = array_unique($updated_post_ids);
    $updated_excerpt_ids = array_unique($updated_excerpt_ids);
    $updated_meta_ids = array_unique($updated_meta_ids);
    
    altm_log("=== REFERENCE UPDATE COMPLETE ===");
    altm_log("Updated posts: " . count($updated_post_ids) . ", excerpts: " . count($updated_excerpt_ids) . ", meta: " . count($updated_meta_ids));
    
    return array(
        'post_ids' => $updated_post_ids,
        'excerpt_ids' => $updated_excerpt_ids,
        'meta_ids' => $updated_meta_ids,
        'new_image_urls' => $new_image_urls
    );
}

/**
 * Convert full URL to relative path for more reliable matching
 */
function altm_get_relative_url($url) {
    if (empty($url)) return null;
    
    $upload_dir = wp_upload_dir();
    $upload_url = $upload_dir['baseurl'];
    
    // Remove the base upload URL to get relative path
    if (strpos($url, $upload_url) === 0) {
        return substr($url, strlen($upload_url));
    }
    
    // Handle URLs with different protocols or domains
    $parsed_url = wp_parse_url($url);
    if (isset($parsed_url['path'])) {
        $path = $parsed_url['path'];
        $upload_path = wp_parse_url($upload_url, PHP_URL_PATH);
        
        if ($upload_path && strpos($path, $upload_path) !== false) {
            return substr($path, strpos($path, $upload_path) + strlen($upload_path));
        }
    }
    
    return null;
}

/**
 * Update post content references
 */
function altm_update_post_content($old_url, $new_url) {
    global $wpdb;
    
    // Find posts containing the old URL
    $posts_query = $wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_content LIKE %s 
        AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
        AND post_status IN ('publish', 'draft', 'private', 'future')
    ", '%' . $wpdb->esc_like($old_url) . '%');
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $post_ids = $wpdb->get_col($posts_query);
    
    if (!empty($post_ids)) {
        // Prepare safe SQL for batch update with placeholders
        $id_placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
        
        // Merge old_url, new_url, and post_ids for prepare()
        $prepare_values = array_merge(array($old_url, $new_url), $post_ids);
        
        // Bulk update using SQL REPLACE
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_placeholders contains safe %d placeholders
        $update_query = $wpdb->prepare("
            UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s)
            WHERE ID IN ($id_placeholders)
        ", $prepare_values);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
        $wpdb->query($update_query);
        
        // Clear post caches
        foreach ($post_ids as $post_id) {
            clean_post_cache($post_id);
        }
        
        altm_log("Updated post content references in " . count($post_ids) . " posts");
    }
    
    return $post_ids;
}

/**
 * Update post excerpts references 
 */
function altm_update_post_excerpts($old_url, $new_url) {
    global $wpdb;
    
    // Find posts containing the old URL in excerpts
    $posts_query = $wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_excerpt LIKE %s 
        AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
        AND post_status IN ('publish', 'draft', 'private', 'future')
    ", '%' . $wpdb->esc_like($old_url) . '%');
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $post_ids = $wpdb->get_col($posts_query);
    
    if (!empty($post_ids)) {
        // Prepare safe SQL for batch update with placeholders
        $id_placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
        
        // Merge old_url, new_url, and post_ids for prepare()
        $prepare_values = array_merge(array($old_url, $new_url), $post_ids);
        
        // Bulk update using SQL REPLACE
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_placeholders contains safe %d placeholders
        $update_query = $wpdb->prepare("
            UPDATE {$wpdb->posts} 
            SET post_excerpt = REPLACE(post_excerpt, %s, %s)
            WHERE ID IN ($id_placeholders)
        ", $prepare_values);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
        $wpdb->query($update_query);
        
        // Clear post caches
        foreach ($post_ids as $post_id) {
            clean_post_cache($post_id);
        }
        
        altm_log("Updated post excerpt references in " . count($post_ids) . " posts");
    }
    
    return $post_ids;
}

/**
 * Update post meta references
 */
function altm_update_post_meta($old_url, $new_url) {
    global $wpdb;
    
    // Find meta fields containing the old URL
    $meta_query = $wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_value LIKE %s
        AND meta_key NOT IN ('_altm_original_filename', '_wp_attached_file', '_altm_original_image_data', '_altm_rename_history', '_altm_rename_refs_history')
    ", '%' . $wpdb->esc_like($old_url) . '%');
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $post_ids = $wpdb->get_col($meta_query);
    
    if (!empty($post_ids)) {
        // Prepare safe SQL for batch update with placeholders
        $id_placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
        
        // Merge old_url, new_url, LIKE pattern, and post_ids for prepare()
        $prepare_values = array_merge(
            array($old_url, $new_url),
            $post_ids,
            array('%' . $wpdb->esc_like($old_url) . '%')
        );
        
        // Update meta values
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_placeholders contains safe %d placeholders
        $update_query = $wpdb->prepare("
            UPDATE {$wpdb->postmeta} 
            SET meta_value = REPLACE(meta_value, %s, %s)
            WHERE post_id IN ($id_placeholders)
            AND meta_value LIKE %s
            AND meta_key NOT IN ('_altm_original_filename', '_wp_attached_file', '_altm_original_image_data', '_altm_rename_history', '_altm_rename_refs_history')

        ", $prepare_values);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
        $wpdb->query($update_query);
        
        // Clear meta caches
        foreach ($post_ids as $post_id) {
            wp_cache_delete($post_id, 'post_meta');
        }
        
        altm_log("Updated post meta references in " . count($post_ids) . " post meta fields");
    }
    
    return $post_ids;
}

/**
 * ===== Rename History Helpers =====
 * We persist three separate meta keys per attachment:
 * - _altm_original_image_data: Original state when first uploaded (stored once)
 * - _altm_rename_history: Each rename operation (capped at 10 entries)
 * - _altm_rename_refs_history: Where references were updated for each rename
 */
function altm_get_original_image_data($attachment_id) {
	$data = get_post_meta($attachment_id, '_altm_original_image_data', true);
	if (is_array($data)) {
		return $data;
	}
	
	// If data is corrupted or not an array, try to unserialize it
	if (is_string($data)) {
		$unserialized = maybe_unserialize($data);
		if (is_array($unserialized)) {
			altm_log("Successfully unserialized original image data for attachment $attachment_id");
			return $unserialized;
		}
	}
	
	altm_log("No valid original image data found for attachment $attachment_id");
	return array();
}

function altm_set_original_image_data($attachment_id, $data) {
	// Only set if not already exists (preserve original state)
	$existing = get_post_meta($attachment_id, '_altm_original_image_data', true);
	if (empty($existing)) {
		$result = update_post_meta($attachment_id, '_altm_original_image_data', $data);
		if ($result) {
			altm_log("Original image data stored for attachment $attachment_id");
		} else {
			altm_log("Failed to store original image data for attachment $attachment_id");
		}
	} else {
		altm_log("Original image data already exists for attachment $attachment_id");
	}
}

function altm_get_rename_history($attachment_id) {
	$history = get_post_meta($attachment_id, '_altm_rename_history', true);
	return is_array($history) ? $history : array();
}

function altm_get_rename_refs_history($attachment_id) {
	$history = get_post_meta($attachment_id, '_altm_rename_refs_history', true);
	return is_array($history) ? $history : array();
}

function altm_cap_history_array($history, $max = 10) {
	if (!is_array($history)) {
		return array();
	}
	if (count($history) > $max) {
		$history = array_slice($history, 0, $max);
	}
	return $history;
}

function altm_add_rename_history_entry($attachment_id, $entry) {
	$history = altm_get_rename_history($attachment_id);
	array_unshift($history, $entry);
	$history = altm_cap_history_array($history, 10);
	update_post_meta($attachment_id, '_altm_rename_history', $history);
}

function altm_add_rename_refs_history_entry($attachment_id, $entry) {
	$history = altm_get_rename_refs_history($attachment_id);
	array_unshift($history, $entry);
	$history = altm_cap_history_array($history, 10);
	update_post_meta($attachment_id, '_altm_rename_refs_history', $history);
}

function altm_build_original_image_data($attachment_id) {
    altm_log("Building original image data for attachment $attachment_id");
	$post = get_post($attachment_id);
	$attached_file = get_attached_file($attachment_id);
	$metadata = wp_get_attachment_metadata($attachment_id);
	
	$orig_data = array(
		'original_filename' => basename($attached_file),
		'original_filepath' => $attached_file,
		'original_title' => $post ? $post->post_title : '',
		'original_slug' => $post ? $post->post_name : '',
		'original_guid' => $post ? $post->guid : '',
		'original_metadata' => $metadata,
		'created_at' => time(),
		'created_by' => get_current_user_id(),
	);
    altm_log("Original image data built for attachment " . print_r($orig_data, true));
    return $orig_data;
}

function altm_build_rename_history_entry($attachment_id, $directory, $extension, $old_filename, $old_filepath, $new_filename, $new_filepath, $old_metadata, $new_metadata, $source = 'manual', $old_title = '', $old_slug = '', $old_guid = '') {
	$post = get_post($attachment_id);
	return array(
		'entry_id' => uniqid('altm_', true),
		'timestamp' => time(),
		'user_id' => get_current_user_id(),
		'source' => $source,
		'directory' => $directory,
		'extension' => $extension,
		'old_filename' => $old_filename,
		'old_filepath' => $old_filepath,
		'new_filename' => $new_filename,
		'new_filepath' => $new_filepath,
		'old_title' => $old_title,
		'old_slug' => $old_slug,
		'old_guid' => $old_guid,
		'new_title' => $post ? $post->post_title : '',
		'new_slug' => $post ? $post->post_name : '',
		'new_guid' => $post ? $post->guid : '',
		'old_metadata' => array(
			'top_file' => isset($old_metadata['file']) ? $old_metadata['file'] : '',
			'original_image' => isset($old_metadata['original_image']) ? $old_metadata['original_image'] : '',
			'sizes' => isset($old_metadata['sizes']) && is_array($old_metadata['sizes']) ? $old_metadata['sizes'] : array(),
		),
		'new_metadata' => array(
			'top_file' => isset($new_metadata['file']) ? $new_metadata['file'] : '',
			'original_image' => isset($new_metadata['original_image']) ? $new_metadata['original_image'] : '',
			'sizes' => isset($new_metadata['sizes']) && is_array($new_metadata['sizes']) ? $new_metadata['sizes'] : array(),
		),
	);
}

function altm_build_refs_history_entry($old_image_urls, $new_image_urls, $updated_posts, $options_snapshot, $redirection_info) {
	return array(
		'entry_id' => uniqid('altm_refs_', true),
		'timestamp' => time(),
		'old_image_urls' => $old_image_urls,
		'new_image_urls' => $new_image_urls,
		'updated_posts' => $updated_posts,
		'options' => $options_snapshot,
		'redirection' => $redirection_info,
	);
}


/**
 * Rename image file for AJAX request
 */
function altm_rename_image_file() {

    altm_log('Renaming image file process started...');

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_rename_image_nonce')) {
        altm_log('Invalid rename image nonce.');
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('upload_files')) {
        altm_log('Insufficient user permissions for uploading files.');
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
    $new_filename = '';
    if (isset($_POST['new_filename'])) {
        $new_filename = sanitize_file_name(wp_unslash($_POST['new_filename']));
    }
    
    if (!$attachment_id) {
        altm_log('Missing attachment ID for the image.');
        wp_send_json_error(array('message' => 'Missing required parameters.'));
        return;
    }
    
    // Get attachment post
    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') {
        altm_log('Invalid attachment ID for the image.');
        wp_send_json_error(array('message' => 'Invalid attachment ID.'));
        return;
    }
    
    // Capture old values IMMEDIATELY after getting the post object (before any processing)
    $old_title = $post->post_title;
    $old_slug = $post->post_name;
    $old_guid = $post->guid;
    
    // Get current file path
    $old_filepath = get_attached_file($attachment_id);
    if (!$old_filepath || !file_exists($old_filepath)) {
        altm_log('Original file not found for the image.');
        wp_send_json_error(array('message' => 'Original file not found.'));
        return;
    }

	// Store original image data at the earliest safe point (before any mutations)
	$original_data = altm_get_original_image_data($attachment_id);
	if (empty($original_data)) {
		$original_data = altm_build_original_image_data($attachment_id);
		altm_set_original_image_data($attachment_id, $original_data);
        altm_log("Original image data stored for attachment $attachment_id");
    }

    // Initialize source variable
    $source = 'image_renaming_page_manual';
    
    // Check if it's manual rename or AI rename. Manual rename means filename was provided.
    if (empty($new_filename)) {
        altm_log('Case of AI based rename.');
        $mime_type = get_post_mime_type($attachment_id);
        $current_filename = basename($old_filepath);

        $rename_use_seo = get_option('alt_magic_rename_use_seo_keywords', 0);
        $rename_use_post = get_option('alt_magic_rename_use_post_title', 0);
        $rename_use_woocommerce_product_name = get_option('alt_magic_rename_use_woocommerce_product_name', 0);

        // Fetch primary content post once if options are enabled
        if ($rename_use_seo || $rename_use_post || $rename_use_woocommerce_product_name) {

            $parent = altm_get_primary_parent_post($attachment_id);
            if ($parent && !empty($parent['id'])) {
                $primary_post_id = (int)$parent['id'];
                $primary_post_type = $parent['type'];
                altm_log('Primary post id: ' . $primary_post_id . ' type: ' . $primary_post_type);

                // Get SEO keywords (pass primary post id where possible)
                $seo_keywords = $use_seo_keywords ? altm_fetch_seo_keywords($primary_post_id) : '';
                altm_log('SEO keywords: ' . $seo_keywords);

                // If WooCommerce product context is enabled and parent is a product, prefer product_name and clear title
                if ($rename_use_woocommerce_product_name && $primary_post_type === 'product') {
                    $woocommerce_product_name = get_the_title($primary_post_id) ?: '';
                    $parent_post_title = '';
                } else {
                    $parent_post_title = $rename_use_post ? (get_the_title($primary_post_id) ?: '') : '';
                    $woocommerce_product_name = '';
                }
            } else {
                altm_log('No primary post id found');
                $seo_keywords = '';
                $parent_post_title = '';
                $woocommerce_product_name = '';
            }
        }
        
        $post_context = array(
            'post_id' => $primary_post_id,
            'post_title' => $parent_post_title,
            'post_type' => $primary_post_type,
            'seo_keywords' => $seo_keywords,
            'woocommerce_product_name' => $woocommerce_product_name
        );

        // Determine if we have meaningful post context
        $has_meaningful_context = !empty($primary_post_id) && 
                                 (!empty($parent_post_title) || !empty($seo_keywords) || !empty($woocommerce_product_name));
        
        $source = $has_meaningful_context ? 'image_renaming_page-with_post_context' : 'image_renaming_page-no_post_context';
        
        altm_log("Post context analysis - Post ID: $primary_post_id, Title: '$parent_post_title', SEO: '$seo_keywords', WooCommerce: '$woocommerce_product_name', Has context: " . ($has_meaningful_context ? 'yes' : 'no') . ", Source: $source");
        
        $generated = altm_generate_ai_filename_from_temp_file($old_filepath, $mime_type, $post_context, $current_filename, $source);
        if (is_wp_error($generated)) {
            altm_log('AI filename generation failed: ' . $generated->get_error_message());
            
            // Check if it's an authentication error (403)
            $error_data = $generated->get_error_data();
            if (isset($error_data['status_code']) && $error_data['status_code'] == 403) {
                wp_send_json(array(
                    'success' => false,
                    'message' => $generated->get_error_message(),
                    'status_code' => 403
                ));
            } else {
                wp_send_json_error(array('message' => 'AI filename generation failed: ' . $generated->get_error_message()));
            }
            return;
        }
        $new_filename = sanitize_file_name($generated);
    }
    
    $pathinfo = pathinfo($old_filepath);
    $directory = $pathinfo['dirname'];
    $old_filename = $pathinfo['basename'];
    $extension = $pathinfo['extension'];
    
    // Ensure new filename has correct extension
    $new_filename_info = pathinfo($new_filename);
    if (empty($new_filename_info['extension'])) {
        $new_filename .= '.' . $extension;
    } elseif ($new_filename_info['extension'] !== $extension) {
        altm_log('File extension cannot be changed. Skipping file rename...');
        wp_send_json_error(array('message' => 'File extension cannot be changed.'));
        return;
    }
    
    $new_filepath = trailingslashit($directory) . $new_filename;
    
    // Check if target file already exists
    if (file_exists($new_filepath) && $old_filepath !== $new_filepath) {
        altm_log('A file with that name already exists. Skipping file rename...');
        wp_send_json_error(array('message' => 'A file with that name already exists.'));
        return;
    }
    
    // Skip if filename hasn't changed
    if ($old_filename === $new_filename) {
        altm_log('Filename is already correct.');
        wp_send_json_success(array(
            'message' => 'Filename is already correct.',
            'old_filename' => $old_filename,
            'new_filename' => $new_filename
        ));
        return;
    }
    
    // Store original filename for potential rollback
    $original_filename = get_post_meta($attachment_id, '_altm_original_filename', true);
    if (empty($original_filename)) {
        altm_log('Original filename not found, adding it.');
        add_post_meta($attachment_id, '_altm_original_filename', $old_filename, true);
    }
    
    // Get old URLs before renaming for updating references
    $old_attachment_url = wp_get_attachment_url($attachment_id);
    $old_metadata = wp_get_attachment_metadata($attachment_id);
    $old_image_urls = array();
    
    // Collect all size URLs
    if (wp_attachment_is_image($attachment_id)) {
        $old_image_urls['full'] = $old_attachment_url;
        if (!empty($old_metadata['sizes'])) {
            foreach ($old_metadata['sizes'] as $size_name => $size_data) {
                $old_image_urls[$size_name] = wp_get_attachment_image_url($attachment_id, $size_name);
            }
        }
    }
    
    // Initialize WordPress Filesystem API
    global $wp_filesystem;
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    // Use direct filesystem access for plugin operations
    if (!WP_Filesystem(false, dirname($old_filepath))) {
        wp_send_json_error(array('message' => 'Failed to initialize filesystem.'));
        return;
    }
    
    // Attempt to rename the main file using WordPress Filesystem API
    if (!$wp_filesystem->move($old_filepath, $new_filepath, true)) {
        wp_send_json_error(array('message' => 'Failed to rename file on disk.'));
        return;
    }
    
    // Update WordPress metadata
    update_post_meta($attachment_id, '_wp_attached_file', _wp_relative_upload_path($new_filepath));
    
    // Update post title and slug to match new filename
    $new_title = pathinfo($new_filename, PATHINFO_FILENAME);
    // Convert hyphens to spaces for a more readable title
    $new_title = str_replace('-', ' ', $new_title);
    // Capitalize first letter of each word
    $new_title = ucwords($new_title);
    
    $new_slug = sanitize_title($new_title);
    
    // Ensure slug is unique
    $new_slug = wp_unique_post_slug($new_slug, $attachment_id, 'attachment', 0, null);
    
    altm_log("Title/Slug generation - Original: '$old_filename' → Title: '$new_title', Slug: '$new_slug'");
    
    // Prepare post update data
    $post_update_data = array(
        'ID' => $attachment_id,
        'post_title' => $new_title,
        'post_name' => $new_slug
    );
    
    // Update GUID only if the option is enabled
    if (get_option('alt_magic_update_guid', 0)) {
        $new_attachment_url = wp_get_attachment_url($attachment_id);
        $post_update_data['guid'] = $new_attachment_url;
        altm_log("GUID update enabled - updating GUID to: $new_attachment_url");
    } else {
        altm_log("GUID update disabled - keeping original GUID");
    }
    
    wp_update_post($post_update_data);
    altm_log("Post data updated - Title: '$new_title', Slug: '$new_slug'" . (isset($post_update_data['guid']) ? ", GUID: '{$post_update_data['guid']}'" : ""));
    
    // Handle thumbnails if this is an image
    if (wp_attachment_is_image($attachment_id) && !empty($old_metadata['sizes'])) {
        altm_log("Processing thumbnails - Found " . count($old_metadata['sizes']) . " thumbnail sizes");
        $new_metadata = $old_metadata;
        
        foreach ($old_metadata['sizes'] as $size_name => $size_data) {
            $old_thumb_path = trailingslashit($directory) . $size_data['file'];
            
            // Generate new thumbnail filename
            $size_pathinfo = pathinfo($size_data['file']);
            $size_extension = $size_pathinfo['extension'];
            
            // Extract the size suffix (e.g., "-289x300" from "Screenshot-2025-07-05-at-12.34.36-AM-289x300.png")
            $old_basename = pathinfo($old_filename, PATHINFO_FILENAME); // Remove extension from old filename
            $size_basename = $size_pathinfo['filename']; // Remove extension from size filename
            
            // Find the size suffix by removing the old filename from the size basename
            $size_suffix = str_replace($old_basename, '', $size_basename);
            
            // Create new thumbnail filename
            $new_basename = pathinfo($new_filename, PATHINFO_FILENAME); // Remove extension from new filename
            $new_thumb_filename = $new_basename . $size_suffix . '.' . $size_extension;
            
            altm_log("Thumbnail filename generation - Size suffix: '$size_suffix'");
            $new_thumb_path = trailingslashit($directory) . $new_thumb_filename;
            
            // Rename thumbnail if it exists using WordPress Filesystem API
            if ($wp_filesystem->exists($old_thumb_path)) {
                if ($wp_filesystem->move($old_thumb_path, $new_thumb_path, true)) {
                    $new_metadata['sizes'][$size_name]['file'] = $new_thumb_filename;
                    altm_log("Thumbnail renamed - Size: $size_name");
                } else {
                    altm_log("Failed to rename thumbnail - Size: $size_name, Path: $old_thumb_path");
                }
            }
        }
        // Ensure the top-level metadata 'file' points to the new main file so WP builds correct srcset 'full' candidate
        altm_log("Ensuring top-level metadata 'file' points to the new main file");
        $relative_new_file = _wp_relative_upload_path($new_filepath);
        if (!empty($relative_new_file)) {
            $new_metadata['file'] = $relative_new_file;
        }

        // Handle WordPress 5.3+ scaled images
        if (!empty($old_metadata['original_image'])) {
            altm_log("Processing scaled image");
            $old_original_path = trailingslashit($directory) . $old_metadata['original_image'];
            $new_original_filename = str_replace('-scaled.' . $extension, '-original.' . $extension, $new_filename);
            $new_original_path = trailingslashit($directory) . $new_original_filename;
            
            if ($wp_filesystem->exists($old_original_path)) {
                if ($wp_filesystem->move($old_original_path, $new_original_path, true)) {
                    $new_metadata['original_image'] = $new_original_filename;
                    altm_log("Scaled image renamed");
                } else {
                    altm_log("Failed to rename scaled image - Path: $old_original_path");
                }
            }
        }
        
        wp_update_attachment_metadata($attachment_id, $new_metadata);
        altm_log("Attachment metadata updated successfully");
    }
    
    // Update references in content and meta
    altm_log("Starting reference updates - Found " . count($old_image_urls) . " URL sizes to update");
	$reference_update_result = altm_update_image_references($attachment_id, $old_image_urls);
    
    // Clear caches
    wp_cache_delete($attachment_id, 'posts');
    clean_post_cache($attachment_id);
    
    // Log update options status
    $update_posts = get_option('alt_magic_update_posts', 1) ? 'enabled' : 'disabled';
    $update_excerpts = get_option('alt_magic_update_excerpts', 0) ? 'enabled' : 'disabled';
    $update_postmeta = get_option('alt_magic_update_postmeta', 1) ? 'enabled' : 'disabled';
    $update_guid = get_option('alt_magic_update_guid', 0) ? 'enabled' : 'disabled';
    
    altm_log("Image renamed: $old_filename → $new_filename (ID: $attachment_id) - Title: '$new_title', Slug: '$new_slug'");
    altm_log("Update options - Posts: $update_posts, Excerpts: $update_excerpts, PostMeta: $update_postmeta, GUID: $update_guid");
    
    // Create redirection if enabled (currently not open to users as more handling of the image sizes is needed)
    $redirection_enabled = get_option('alt_magic_enable_redirections', 0);
    altm_log("Redirections option: " . ($redirection_enabled ? 'enabled' : 'disabled'));

    if ($redirection_enabled) {
        altm_log("Adding redirection");
        $upload_dir = wp_upload_dir();
        $upload_subdir = str_replace($upload_dir['basedir'], '', $directory);
        $upload_subdir = trim($upload_subdir, '/');
        
        // Extract filename without extension for redirection function
        $old_filename_no_ext = pathinfo($old_filename, PATHINFO_FILENAME);
        $new_filename_no_ext = pathinfo($new_filename, PATHINFO_FILENAME);
        
        $redirection_result = altm_add_redirection(
            $old_filename_no_ext,
            $new_filename_no_ext,
            $extension,
            $upload_subdir ? $upload_subdir . '/' : '',
            true
        );
        
        if (is_wp_error($redirection_result)) {
            altm_log("Redirection creation failed: " . $redirection_result->get_error_message());
        }
    } else {
        altm_log("Redirections: disabled - skipping");
    }

	// Build and persist history entries
	$new_metadata = wp_get_attachment_metadata($attachment_id);
	$rename_entry = altm_build_rename_history_entry(
		$attachment_id,
		$directory,
		$extension,
		$old_filename,
		$old_filepath,
		$new_filename,
		$new_filepath,
		$old_metadata,
		$new_metadata,
		$source,
		$old_title,
		$old_slug,
		$old_guid
	);
	altm_add_rename_history_entry($attachment_id, $rename_entry);

	$options_snapshot = array(
		'update_posts' => (int) get_option('alt_magic_update_posts', 1),
		'update_excerpts' => (int) get_option('alt_magic_update_excerpts', 0),
		'update_postmeta' => (int) get_option('alt_magic_update_postmeta', 1),
		'update_guid' => (int) get_option('alt_magic_update_guid', 0),
		'enable_redirections' => (int) get_option('alt_magic_enable_redirections', 0),
	);

	// Build best-effort redirection info for history
	$upload_dir_hist = wp_upload_dir();
	$upload_subdir_hist = str_replace($upload_dir_hist['basedir'], '', $directory);
	$upload_subdir_hist = trim($upload_subdir_hist, '/');
	$sub_hist = $upload_subdir_hist ? '/' . $upload_subdir_hist . '/' : '/';
	$base_url_hist = rtrim($upload_dir_hist['baseurl'], '/');
	$old_url_hist = $base_url_hist . $sub_hist . $old_filename;
	$new_url_hist = $base_url_hist . $sub_hist . $new_filename;
	$redirection_info = array(
		'attempted' => (bool) $redirection_enabled,
		'old_url' => $old_url_hist,
		'new_url' => $new_url_hist,
	);

	$updated_posts = array(
		'post_ids' => $reference_update_result['post_ids'],
		'excerpt_ids' => $reference_update_result['excerpt_ids'],
		'meta_ids' => $reference_update_result['meta_ids'],
	);

	$refs_entry = altm_build_refs_history_entry(
		$old_image_urls,
		$reference_update_result['new_image_urls'],
		$updated_posts,
		$options_snapshot,
		$redirection_info
	);
	altm_add_rename_refs_history_entry($attachment_id, $refs_entry);

    
    wp_send_json_success(array(
        'message' => 'Image renamed successfully.',
        'old_filename' => $old_filename,
        'new_filename' => $new_filename,
        'attachment_id' => $attachment_id
    ));
}

// Register AJAX action
add_action('wp_ajax_altm_rename_image', 'altm_rename_image_file');