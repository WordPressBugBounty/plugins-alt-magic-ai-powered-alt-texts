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
function altm_generate_ai_filename_from_temp_file($temp_file_path, $mime_type, $post_context = null, $original_filename = '', $source = 'missing', $attachment_id = 0) {
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
    
    $language_resolution = altm_resolve_rename_language($attachment_id, is_array($post_context) ? $post_context : array());

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
        'language' => $language_resolution['code'],
        'language_type' => 'code',
        'site_url' => get_site_url(),
        'purpose' => 'filename_generation',
        'wp_plugin_source' => $source
    );

    altm_log('Rename language code: ' . $language_resolution['code']);
    altm_log('Rename language source: ' . $language_resolution['source']);
    
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
    $language_resolution = altm_resolve_generation_language();
    $language_code = $language_resolution['code'];
    $rename_language_resolution = altm_resolve_rename_language(0, is_array($post_context) ? $post_context : array());
    $rename_use_seo = get_option('alt_magic_rename_use_seo_keywords', null);
    $rename_use_post = get_option('alt_magic_rename_use_post_title', null);
    $rename_language = $rename_language_resolution['code'];
    
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
 * Build an uploads URL for a metadata filename.
 *
 * WordPress size metadata normally stores only a basename, while some image
 * converter plugins store source filenames in top-level or relative forms.
 */
function altm_build_upload_url_from_metadata_file($directory, $filename) {
    if (empty($filename)) {
        return '';
    }

    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }

    $upload_dir = wp_upload_dir();
    $filename = ltrim(str_replace('\\', '/', $filename), '/');

    if (strpos($filename, '/') !== false) {
        return trailingslashit($upload_dir['baseurl']) . $filename;
    }

    $basedir = wp_normalize_path($upload_dir['basedir']);
    $directory = wp_normalize_path($directory);
    $relative_dir = trim(str_replace($basedir, '', $directory), '/');

    return trailingslashit($upload_dir['baseurl']) . ($relative_dir ? trailingslashit($relative_dir) : '') . $filename;
}

/**
 * Build an absolute uploads path for a metadata filename.
 */
function altm_build_upload_path_from_metadata_file($directory, $filename) {
    if (empty($filename) || preg_match('#^https?://#i', $filename)) {
        return '';
    }

    $upload_dir = wp_upload_dir();
    $filename = ltrim(str_replace('\\', '/', $filename), '/');

    if (strpos($filename, '/') !== false) {
        return trailingslashit(wp_normalize_path($upload_dir['basedir'])) . $filename;
    }

    return trailingslashit(wp_normalize_path($directory)) . $filename;
}

/**
 * Generate the renamed filename for a converter source entry.
 */
function altm_get_renamed_metadata_source_file($old_source_file, $old_reference_file, $new_reference_file) {
    if (empty($old_source_file) || empty($old_reference_file) || empty($new_reference_file)) {
        return $old_source_file;
    }

    $old_source_file = str_replace('\\', '/', $old_source_file);
    $source_pathinfo = pathinfo($old_source_file);
    $source_dir = isset($source_pathinfo['dirname']) && $source_pathinfo['dirname'] !== '.' ? trailingslashit($source_pathinfo['dirname']) : '';
    $source_base = isset($source_pathinfo['filename']) ? $source_pathinfo['filename'] : '';
    $source_ext = isset($source_pathinfo['extension']) && $source_pathinfo['extension'] !== '' ? '.' . $source_pathinfo['extension'] : '';

    $old_reference_base = pathinfo($old_reference_file, PATHINFO_FILENAME);
    $new_reference_base = pathinfo($new_reference_file, PATHINFO_FILENAME);

    if ($source_base !== '' && $old_reference_base !== '' && strpos($source_base, $old_reference_base) === 0) {
        return $source_dir . $new_reference_base . substr($source_base, strlen($old_reference_base)) . $source_ext;
    }

    if (basename($old_source_file) === basename($old_reference_file)) {
        return $source_dir . basename($new_reference_file);
    }

    // Fallback for converter files that keep a different suffix but still need to follow the new basename.
    return $source_dir . $new_reference_base . $source_ext;
}

/**
 * Track one old URL -> new URL replacement pair using synthetic size keys.
 */
function altm_add_metadata_source_reference_pair(&$old_image_urls, &$source_new_image_urls, $key, $old_url, $new_url) {
    if (empty($old_url) || empty($new_url) || $old_url === $new_url) {
        return;
    }

    if (in_array($old_url, $old_image_urls, true) && in_array($new_url, $source_new_image_urls, true)) {
        return;
    }

    $key = sanitize_key($key);
    if (empty($key)) {
        $key = 'source_' . md5($old_url);
    }

    if (isset($old_image_urls[$key]) || isset($source_new_image_urls[$key])) {
        $key .= '_' . substr(md5($old_url . $new_url), 0, 8);
    }

    $old_image_urls[$key] = $old_url;
    $source_new_image_urls[$key] = $new_url;
}

/**
 * Rename and update converter-provided metadata source files.
 */
function altm_update_metadata_sources(&$metadata_node, $directory, $old_reference_file, $new_reference_file, &$old_image_urls, &$source_new_image_urls, &$renamed_source_files, $wp_filesystem, $context_key) {
    if (empty($metadata_node['sources']) || !is_array($metadata_node['sources'])) {
        return;
    }

    foreach ($metadata_node['sources'] as $source_type => &$source_data) {
        if (empty($source_data['file']) || !is_string($source_data['file'])) {
            continue;
        }

        $old_source_file = $source_data['file'];
        $new_source_file = altm_get_renamed_metadata_source_file($old_source_file, $old_reference_file, $new_reference_file);

        if (empty($new_source_file) || $old_source_file === $new_source_file) {
            continue;
        }

        $old_source_url = altm_build_upload_url_from_metadata_file($directory, $old_source_file);
        $new_source_url = altm_build_upload_url_from_metadata_file($directory, $new_source_file);
        altm_add_metadata_source_reference_pair(
            $old_image_urls,
            $source_new_image_urls,
            'source_' . $context_key . '_' . sanitize_key($source_type),
            $old_source_url,
            $new_source_url
        );

        $old_source_path = altm_build_upload_path_from_metadata_file($directory, $old_source_file);
        $new_source_path = altm_build_upload_path_from_metadata_file($directory, $new_source_file);

        if ($old_source_path && $new_source_path && $old_source_path !== $new_source_path) {
            $source_map_key = wp_normalize_path($old_source_path);

            if (isset($renamed_source_files[$source_map_key])) {
                altm_log("Converter source metadata synced from previously renamed source - Context: $context_key");
            } elseif ($wp_filesystem && $wp_filesystem->exists($old_source_path)) {
                if ($wp_filesystem->move($old_source_path, $new_source_path, true)) {
                    $renamed_source_files[$source_map_key] = $new_source_path;
                    altm_log("Converter source renamed - Context: $context_key, Type: $source_type");
                } else {
                    altm_log("Failed to rename converter source - Context: $context_key, Path: $old_source_path");
                }
            } elseif ($wp_filesystem && $wp_filesystem->exists($new_source_path)) {
                $renamed_source_files[$source_map_key] = $new_source_path;
                altm_log("Converter source already present at renamed path - Context: $context_key");
            }
        }

        $source_data['file'] = $new_source_file;
    }
    unset($source_data);
}

/**
 * Update all references to an image
 */
function altm_update_image_references($attachment_id, $old_image_urls, $known_new_image_urls = array()) {
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

    if (!empty($known_new_image_urls) && is_array($known_new_image_urls)) {
        $new_image_urls = array_merge($new_image_urls, $known_new_image_urls);
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
                // Always update post content after image renaming.
                $post_ids = altm_update_post_content($old_relative_url, $new_relative_url);
                $updated_post_ids = array_merge($updated_post_ids, $post_ids);
                
                // Always update post excerpts after image renaming.
                $excerpt_ids = altm_update_post_excerpts($old_relative_url, $new_relative_url);
                $updated_excerpt_ids = array_merge($updated_excerpt_ids, $excerpt_ids);
                
                // Always update post meta after image renaming.
                $meta_ids = altm_update_post_meta($old_relative_url, $new_relative_url);
                $updated_meta_ids = array_merge($updated_meta_ids, $meta_ids);
                
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
			'sources' => isset($old_metadata['sources']) && is_array($old_metadata['sources']) ? $old_metadata['sources'] : array(),
			'sizes' => isset($old_metadata['sizes']) && is_array($old_metadata['sizes']) ? $old_metadata['sizes'] : array(),
		),
		'new_metadata' => array(
			'top_file' => isset($new_metadata['file']) ? $new_metadata['file'] : '',
			'original_image' => isset($new_metadata['original_image']) ? $new_metadata['original_image'] : '',
			'sources' => isset($new_metadata['sources']) && is_array($new_metadata['sources']) ? $new_metadata['sources'] : array(),
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

function altm_mark_rename_history_entry_undone($attachment_id, $history_index = 0, $undo_details = array()) {
	$rename_history = altm_get_rename_history($attachment_id);
	$refs_history = altm_get_rename_refs_history($attachment_id);
	$history_index = absint($history_index);
	$undo_data = array_merge(
		array(
			'undone' => true,
			'undone_at' => time(),
			'undone_by' => get_current_user_id(),
		),
		is_array($undo_details) ? $undo_details : array()
	);

	if (!empty($rename_history[$history_index]) && is_array($rename_history[$history_index])) {
		$rename_history[$history_index]['undone'] = true;
		$rename_history[$history_index]['undone_at'] = $undo_data['undone_at'];
		$rename_history[$history_index]['undone_by'] = $undo_data['undone_by'];
		$rename_history[$history_index]['undo_details'] = $undo_data;
		update_post_meta($attachment_id, '_altm_rename_history', altm_cap_history_array($rename_history, 10));
	}

	if (!empty($refs_history[$history_index]) && is_array($refs_history[$history_index])) {
		$refs_history[$history_index]['undone'] = true;
		$refs_history[$history_index]['undone_at'] = $undo_data['undone_at'];
		$refs_history[$history_index]['undone_by'] = $undo_data['undone_by'];
		$refs_history[$history_index]['undo_details'] = $undo_data;
		update_post_meta($attachment_id, '_altm_rename_refs_history', altm_cap_history_array($refs_history, 10));
	}
}

function altm_get_latest_undoable_rename_entry($rename_history) {
	if (empty($rename_history[0]) || !is_array($rename_history[0]) || !empty($rename_history[0]['undone'])) {
		return array();
	}

	return array(
		'index' => 0,
		'entry' => $rename_history[0],
		'refs_index' => 0,
	);
}

function altm_get_renamed_images_for_undo() {
	if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_rename_image_nonce')) {
		wp_send_json_error(array('message' => 'Invalid nonce.'));
		return;
	}

	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => 'Insufficient permissions.'));
		return;
	}

	if (function_exists('altm_is_wpml_active') && altm_is_wpml_active()) {
		wp_send_json_error(array('message' => 'Undo rename is not available when WPML is active.'));
		return;
	}

	global $wpdb;

	$offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
	$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
	$per_page = min(25, max(1, $per_page));
	$scan_limit = 100;
	$max_scans = 10;
	$items = array();
	$scans = 0;
	$has_more = false;
	$next_offset = $offset;

	while (!$has_more && $scans < $max_scans) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				ORDER BY pm.post_id DESC
				LIMIT %d OFFSET %d",
				'_altm_rename_history',
				'attachment',
				$scan_limit,
				$next_offset
			)
		);

		if (empty($rows)) {
			$has_more = false;
			break;
		}

		$scans++;

		foreach ($rows as $row_index => $row) {
			$current_row_offset = $next_offset + $row_index;
			$rename_history = maybe_unserialize($row->meta_value);
			$undoable_entry = altm_get_latest_undoable_rename_entry(is_array($rename_history) ? $rename_history : array());

			if (empty($undoable_entry['entry']) || !is_array($undoable_entry['entry'])) {
				continue;
			}

			$entry = $undoable_entry['entry'];
			$attachment_id = (int) $row->post_id;
			$current_file = get_attached_file($attachment_id);
			$current_filename = $current_file ? basename($current_file) : (!empty($entry['new_filename']) ? basename($entry['new_filename']) : '');
			$old_filename = !empty($entry['old_filename']) ? basename($entry['old_filename']) : '';

			if ($current_filename === '' || $old_filename === '') {
				continue;
			}

			if ($current_filename === $old_filename) {
				continue;
			}

			if (count($items) >= $per_page) {
				$has_more = true;
				$next_offset = $current_row_offset;
				break;
			}

			$items[] = array(
				'attachment_id' => $attachment_id,
				'current_filename' => $current_filename,
				'old_filename' => $old_filename,
			);
		}

		if (!$has_more) {
			$next_offset += count($rows);
		}

		if (count($rows) < $scan_limit) {
			break;
		}
	}

	wp_send_json_success(array(
		'items' => $items,
		'next_offset' => $next_offset,
		'has_more' => $has_more,
	));
}

function altm_get_history_metadata_file_list($metadata) {
	$files = array();

	if (!is_array($metadata)) {
		return $files;
	}

	if (!empty($metadata['top_file'])) {
		$files['full'] = basename($metadata['top_file']);
	}

	if (!empty($metadata['original_image'])) {
		$files['original_image'] = basename($metadata['original_image']);
	}

	if (!empty($metadata['sources']) && is_array($metadata['sources'])) {
		foreach ($metadata['sources'] as $source_type => $source_data) {
			if (!empty($source_data['file'])) {
				$files['source_' . sanitize_key($source_type)] = $source_data['file'];
			}
		}
	}

	if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
		foreach ($metadata['sizes'] as $size_name => $size_data) {
			if (!empty($size_data['file'])) {
				$files['size_' . $size_name] = $size_data['file'];
			}

			if (!empty($size_data['sources']) && is_array($size_data['sources'])) {
				foreach ($size_data['sources'] as $source_type => $source_data) {
					if (!empty($source_data['file'])) {
						$files['size_source_' . $size_name . '_' . sanitize_key($source_type)] = $source_data['file'];
					}
				}
			}
		}
	}

	return $files;
}

function altm_get_undo_file_pairs($directory, $latest_entry) {
	$pairs = array();
	$used_sources = array();

	if (empty($latest_entry['new_metadata']) || empty($latest_entry['old_metadata'])) {
		return $pairs;
	}

	$new_files = altm_get_history_metadata_file_list($latest_entry['new_metadata']);
	$old_files = altm_get_history_metadata_file_list($latest_entry['old_metadata']);

	foreach ($new_files as $key => $new_file) {
		if (empty($old_files[$key])) {
			continue;
		}

		$new_path = altm_build_upload_path_from_metadata_file($directory, $new_file);
		$old_path = altm_build_upload_path_from_metadata_file($directory, $old_files[$key]);

		if (!$new_path || !$old_path || $new_path === $old_path) {
			continue;
		}

		$normalized_new_path = wp_normalize_path($new_path);
		if (isset($used_sources[$normalized_new_path])) {
			altm_log('Undo skipped duplicate source file mapping for: ' . basename($new_path));
			continue;
		}

		$used_sources[$normalized_new_path] = true;

		$pairs[$normalized_new_path . '|' . wp_normalize_path($old_path)] = array(
			'from' => $new_path,
			'to' => $old_path,
			'key' => $key,
		);
	}

	return array_values($pairs);
}

function altm_restore_metadata_from_rename_history($current_metadata, $old_metadata) {
	$restored_metadata = is_array($current_metadata) ? $current_metadata : array();

	if (!empty($old_metadata['top_file'])) {
		$restored_metadata['file'] = $old_metadata['top_file'];
	}

	if (array_key_exists('original_image', $old_metadata)) {
		if (!empty($old_metadata['original_image'])) {
			$restored_metadata['original_image'] = $old_metadata['original_image'];
		} else {
			unset($restored_metadata['original_image']);
		}
	}

	if (array_key_exists('sources', $old_metadata)) {
		if (!empty($old_metadata['sources']) && is_array($old_metadata['sources'])) {
			$restored_metadata['sources'] = $old_metadata['sources'];
		} else {
			unset($restored_metadata['sources']);
		}
	}

	if (!empty($old_metadata['sizes']) && is_array($old_metadata['sizes'])) {
		$restored_metadata['sizes'] = $old_metadata['sizes'];
	}

	return $restored_metadata;
}

function altm_reverse_rename_references($refs_entry) {
	$updated_post_ids = array();
	$updated_excerpt_ids = array();
	$updated_meta_ids = array();

	if (empty($refs_entry['old_image_urls']) || empty($refs_entry['new_image_urls']) || !is_array($refs_entry['old_image_urls']) || !is_array($refs_entry['new_image_urls'])) {
		return array(
			'post_ids' => array(),
			'excerpt_ids' => array(),
			'meta_ids' => array(),
		);
	}

	foreach ($refs_entry['new_image_urls'] as $size => $new_url) {
		if (empty($refs_entry['old_image_urls'][$size])) {
			continue;
		}

		$old_url = $refs_entry['old_image_urls'][$size];
		$new_relative_url = altm_get_relative_url($new_url);
		$old_relative_url = altm_get_relative_url($old_url);

		if (!$new_relative_url || !$old_relative_url || $new_relative_url === $old_relative_url) {
			continue;
		}

		altm_log("Undo references for size: $size");
		$updated_post_ids = array_merge($updated_post_ids, altm_update_post_content($new_relative_url, $old_relative_url));
		$updated_excerpt_ids = array_merge($updated_excerpt_ids, altm_update_post_excerpts($new_relative_url, $old_relative_url));
		$updated_meta_ids = array_merge($updated_meta_ids, altm_update_post_meta($new_relative_url, $old_relative_url));
	}

	return array(
		'post_ids' => array_values(array_unique($updated_post_ids)),
		'excerpt_ids' => array_values(array_unique($updated_excerpt_ids)),
		'meta_ids' => array_values(array_unique($updated_meta_ids)),
	);
}

function altm_undo_image_rename() {
	altm_log('Undo image rename process started...');

	if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_rename_image_nonce')) {
		altm_log('Invalid undo image rename nonce.');
		wp_send_json_error(array('message' => 'Invalid nonce.'));
		return;
	}

	if (!current_user_can('upload_files')) {
		altm_log('Insufficient user permissions for undo image rename.');
		wp_send_json_error(array('message' => 'Insufficient permissions.'));
		return;
	}

	if (function_exists('altm_is_wpml_active') && altm_is_wpml_active()) {
		wp_send_json_error(array('message' => 'Undo rename is not available when WPML is active.'));
		return;
	}

	$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
	if (!$attachment_id) {
		wp_send_json_error(array('message' => 'Missing attachment ID.'));
		return;
	}

	$post = get_post($attachment_id);
	if (!$post || $post->post_type !== 'attachment') {
		wp_send_json_error(array('message' => 'Invalid attachment ID.'));
		return;
	}

	$rename_history = altm_get_rename_history($attachment_id);
	$undoable_entry = altm_get_latest_undoable_rename_entry($rename_history);
	if (empty($undoable_entry['entry']) || !is_array($undoable_entry['entry'])) {
		wp_send_json_error(array('message' => 'No rename history found for this image.'));
		return;
	}

	$latest_entry = $undoable_entry['entry'];
	$refs_history = altm_get_rename_refs_history($attachment_id);
	$refs_index = isset($undoable_entry['refs_index']) ? (int) $undoable_entry['refs_index'] : 0;
	$refs_entry = !empty($refs_history[$refs_index]) && is_array($refs_history[$refs_index]) && empty($refs_history[$refs_index]['undone']) ? $refs_history[$refs_index] : array();
	$directory = !empty($latest_entry['directory']) ? $latest_entry['directory'] : dirname((string) get_attached_file($attachment_id));

	if (empty($latest_entry['old_filename']) || empty($latest_entry['new_filename']) || empty($latest_entry['old_filepath']) || empty($latest_entry['new_filepath'])) {
		wp_send_json_error(array('message' => 'Rename history is incomplete for this image.'));
		return;
	}

	$current_file = get_attached_file($attachment_id);
	if ($current_file && basename($current_file) !== basename($latest_entry['new_filename'])) {
		wp_send_json_error(array('message' => 'This image has changed since the last rename. Undo cannot be applied safely.'));
		return;
	}

	global $wp_filesystem;
	require_once(ABSPATH . 'wp-admin/includes/file.php');

	if (!WP_Filesystem(false, dirname($latest_entry['new_filepath']))) {
		wp_send_json_error(array('message' => 'Failed to initialize filesystem.'));
		return;
	}

	$file_pairs = altm_get_undo_file_pairs($directory, $latest_entry);
	if (empty($file_pairs)) {
		$file_pairs[] = array(
			'from' => $latest_entry['new_filepath'],
			'to' => $latest_entry['old_filepath'],
			'key' => 'full',
		);
	}

	foreach ($file_pairs as $pair) {
		$from = $pair['from'];
		$to = $pair['to'];

		if (!$wp_filesystem->exists($from)) {
			altm_log('Undo rename source missing, skipping file move: ' . $from);
			continue;
		}

		if ($wp_filesystem->exists($to)) {
			wp_send_json_error(array('message' => 'Cannot undo rename because the original filename already exists: ' . basename($to)));
			return;
		}
	}

	foreach ($file_pairs as $pair) {
		$from = $pair['from'];
		$to = $pair['to'];

		if (!$wp_filesystem->exists($from)) {
			continue;
		}

		if (!$wp_filesystem->move($from, $to, true)) {
			wp_send_json_error(array('message' => 'Failed to restore file: ' . basename($from)));
			return;
		}

		altm_log('Undo file restored: ' . basename($from) . ' -> ' . basename($to));
	}

	update_post_meta($attachment_id, '_wp_attached_file', _wp_relative_upload_path($latest_entry['old_filepath']));

	$current_metadata = wp_get_attachment_metadata($attachment_id);
	$restored_metadata = altm_restore_metadata_from_rename_history($current_metadata, isset($latest_entry['old_metadata']) ? $latest_entry['old_metadata'] : array());
	wp_update_attachment_metadata($attachment_id, $restored_metadata);

	$post_update_data = array(
		'ID' => $attachment_id,
		'post_title' => isset($latest_entry['old_title']) ? $latest_entry['old_title'] : pathinfo($latest_entry['old_filename'], PATHINFO_FILENAME),
		'post_name' => isset($latest_entry['old_slug']) ? $latest_entry['old_slug'] : sanitize_title(pathinfo($latest_entry['old_filename'], PATHINFO_FILENAME)),
	);

	if (!empty($latest_entry['old_guid'])) {
		$post_update_data['guid'] = $latest_entry['old_guid'];
	}

	wp_update_post($post_update_data);
	$updated_posts = altm_reverse_rename_references($refs_entry);

	altm_mark_rename_history_entry_undone($attachment_id, isset($undoable_entry['index']) ? (int) $undoable_entry['index'] : 0, array(
		'restored_filename' => $latest_entry['old_filename'],
		'undone_filename' => $latest_entry['new_filename'],
		'updated_posts' => $updated_posts,
	));
	$remaining_undoable_entry = altm_get_latest_undoable_rename_entry(altm_get_rename_history($attachment_id));

	wp_cache_delete($attachment_id, 'posts');
	clean_post_cache($attachment_id);

	altm_log('Undo image rename finished for attachment ' . $attachment_id);

	wp_send_json_success(array(
		'message' => 'Image rename undone successfully.',
		'attachment_id' => $attachment_id,
		'old_filename' => $latest_entry['new_filename'],
		'new_filename' => $latest_entry['old_filename'],
		'can_undo_rename' => !empty($remaining_undoable_entry['entry']),
		'rename_history_label' => !empty($remaining_undoable_entry['entry']) ? 'Renamed' : 'Rename undone',
		'updated_posts' => $updated_posts,
	));
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
                $seo_keywords = $rename_use_seo ? altm_fetch_seo_keywords($primary_post_id) : '';
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
        
        $generated = altm_generate_ai_filename_from_temp_file($old_filepath, $mime_type, $post_context, $current_filename, $source, $attachment_id);
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
    $source_new_image_urls = array();
    
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
    
    // Handle image metadata, thumbnails, and converter-provided source files.
    if (wp_attachment_is_image($attachment_id)) {
        $new_metadata = is_array($old_metadata) ? $old_metadata : array();
        $renamed_thumbnail_map = array();
        $renamed_source_files = array();

        altm_update_metadata_sources(
            $new_metadata,
            $directory,
            $old_filename,
            $new_filename,
            $old_image_urls,
            $source_new_image_urls,
            $renamed_source_files,
            $wp_filesystem,
            'full'
        );
        
        if (!empty($old_metadata['sizes'])) {
            altm_log("Processing thumbnails - Found " . count($old_metadata['sizes']) . " thumbnail sizes");

            foreach ($old_metadata['sizes'] as $size_name => $size_data) {
                if (empty($size_data['file'])) {
                    continue;
                }

                $old_thumb_path = trailingslashit($directory) . $size_data['file'];
                $old_thumb_filename = isset($size_data['file']) ? $size_data['file'] : '';
                
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

                // Some WordPress size labels can point to the same physical file (for example
                // large and medium_large). If we already renamed that physical file for an earlier
                // size key, only the metadata needs to be updated here.
                if ($old_thumb_filename && isset($renamed_thumbnail_map[$old_thumb_filename])) {
                    $new_metadata['sizes'][$size_name]['file'] = $renamed_thumbnail_map[$old_thumb_filename];
                    altm_log("Thumbnail metadata synced from previously renamed sibling - Size: $size_name");
                } elseif ($wp_filesystem->exists($old_thumb_path)) {
                    // Rename thumbnail if it exists using WordPress Filesystem API
                    if ($wp_filesystem->move($old_thumb_path, $new_thumb_path, true)) {
                        $new_metadata['sizes'][$size_name]['file'] = $new_thumb_filename;
                        if ($old_thumb_filename) {
                            $renamed_thumbnail_map[$old_thumb_filename] = $new_thumb_filename;
                        }
                        altm_log("Thumbnail renamed - Size: $size_name");
                    } else {
                        altm_log("Failed to rename thumbnail - Size: $size_name, Path: $old_thumb_path");
                    }
                } elseif ($wp_filesystem->exists($new_thumb_path)) {
                    $new_metadata['sizes'][$size_name]['file'] = $new_thumb_filename;
                    if ($old_thumb_filename) {
                        $renamed_thumbnail_map[$old_thumb_filename] = $new_thumb_filename;
                    }
                    altm_log("Thumbnail already present at renamed path - Size: $size_name");
                }

                altm_update_metadata_sources(
                    $new_metadata['sizes'][$size_name],
                    $directory,
                    $old_thumb_filename,
                    $new_metadata['sizes'][$size_name]['file'],
                    $old_image_urls,
                    $source_new_image_urls,
                    $renamed_source_files,
                    $wp_filesystem,
                    $size_name
                );
            }
        } else {
            altm_log("Processing thumbnails - No thumbnail sizes found");
        }

        // Ensure the top-level metadata 'file' points to the new main file so WP builds correct srcset 'full' candidate
        altm_log("Ensuring top-level metadata 'file' points to the new main file");
        $relative_new_file = _wp_relative_upload_path($new_filepath);
        if (!empty($relative_new_file)) {
            $new_metadata['file'] = $relative_new_file;
        }

        // Handle WordPress 5.3+ scaled images
        if (!empty($old_metadata['original_image'])) {
            altm_log("Processing original image");
            $old_original_filename = basename($old_metadata['original_image']);
            $old_original_path = trailingslashit($directory) . $old_original_filename;
            $old_original_extension = pathinfo($old_original_filename, PATHINFO_EXTENSION);
            $new_original_base = pathinfo($new_filename, PATHINFO_FILENAME);
            $new_original_filename = $new_original_base . ($old_original_extension ? '.' . $old_original_extension : '');

            // Avoid overwriting the newly renamed main file when the original image uses the same extension.
            if ($new_original_filename === $new_filename) {
                $new_original_filename = $new_original_base . '-original' . ($old_original_extension ? '.' . $old_original_extension : '');
            }

            $new_original_path = trailingslashit($directory) . $new_original_filename;
            
            if ($wp_filesystem->exists($old_original_path)) {
                if ($wp_filesystem->move($old_original_path, $new_original_path, true)) {
                    $new_metadata['original_image'] = $new_original_filename;
                    altm_add_metadata_source_reference_pair(
                        $old_image_urls,
                        $source_new_image_urls,
                        'original_image',
                        altm_build_upload_url_from_metadata_file($directory, $old_original_filename),
                        altm_build_upload_url_from_metadata_file($directory, $new_original_filename)
                    );
                    altm_log("Original image renamed");
                } else {
                    altm_log("Failed to rename original image - Path: $old_original_path");
                }
            } elseif ($wp_filesystem->exists($new_original_path)) {
                $new_metadata['original_image'] = $new_original_filename;
                altm_log("Original image already present at renamed path");
            }
        }
        
        wp_update_attachment_metadata($attachment_id, $new_metadata);
        altm_log("Attachment metadata updated successfully");
    }
    
    // Update references in content and meta
    altm_log("Starting reference updates - Found " . count($old_image_urls) . " URL sizes to update");
	$reference_update_result = altm_update_image_references($attachment_id, $old_image_urls, $source_new_image_urls);
    
    // Clear caches
    wp_cache_delete($attachment_id, 'posts');
    clean_post_cache($attachment_id);
    
    // Log update options status
    $update_posts = 'enabled';
    $update_excerpts = 'enabled';
    $update_postmeta = 'enabled';
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
		'update_posts' => 1,
		'update_excerpts' => 1,
		'update_postmeta' => 1,
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
add_action('wp_ajax_altm_undo_image_rename', 'altm_undo_image_rename');
add_action('wp_ajax_altm_get_renamed_images_for_undo', 'altm_get_renamed_images_for_undo');
