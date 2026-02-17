<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include SEO keywords fetcher
require_once(plugin_dir_path(__FILE__) . 'altm-seo-keywords-fetcher.php');
// Include image renaming handler
require_once(plugin_dir_path(__FILE__) . 'altm-image-renaming-handler.php');

/**
 * Alt Magic Upload Handler
 * 
 * This file manages the junction logic for handling both alt text generation 
 * and image renaming during the upload process. It handles 4 different cases:
 * 
 * 1. Only auto renaming enabled
 * 2. Only auto alt text generation enabled  
 * 3. Both options enabled (uses combined API endpoint)
 * 4. Both options disabled (no processing)
 */

/**
 * Main upload handler - called during wp_handle_upload_prefilter
 * Handles filename renaming before WordPress processes the file
 * 
 * @param array $file The uploaded file array
 * @return array Modified file array with new name (if applicable)
 */
function altm_handle_upload_prefilter($file) {
    // Check if this is an image file
    if (!isset($file['type']) || strpos($file['type'], 'image/') !== 0) {
        return $file; // Not an image, skip processing
    }
    
    // Get user settings
    $auto_generate_alt = get_option('alt_magic_auto_generate', false);
    $auto_rename_upload = get_option('alt_magic_auto_rename_on_upload', false);
    
    
    // Case 1: Only auto renaming enabled
    if (!$auto_generate_alt && $auto_rename_upload) {
        altm_log('Case 1: Only auto-renaming enabled');
        return altm_handle_rename_only($file);
    }

    // Case 2: Only auto alt text generation enabled  
    if ($auto_generate_alt && !$auto_rename_upload) {
        altm_log('Case 2: Only auto alt-text generation enabled');
        // Alt text generation happens post-upload in add_attachment hook
        return $file;
    }
    
    // Case 3: Both options enabled - use combined API
    if ($auto_generate_alt && $auto_rename_upload) {
        altm_log('Case 3: Both auto-generation and auto-renaming enabled');
        return altm_handle_combined_processing($file);
    }

    // Case 4: Both options disabled - no processing needed
    if (!$auto_generate_alt && !$auto_rename_upload) {
        altm_log('Both auto-generation and auto-renaming disabled, skipping upload processing');
        return $file;
    }
    
    return $file;
}

/**
 * Main post-upload handler - called during add_attachment
 * Handles alt text generation after WordPress creates the attachment
 * 
 * @param int $attachment_id The attachment ID
 */
function altm_handle_add_attachment($attachment_id) {

    altm_log('Post-upload handler called for attachment: ' . $attachment_id);
    // Only process images
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment' || strpos($attachment->post_mime_type, 'image/') !== 0) {
        return;
    }
    
    // Override attachment title and caption with AI-generated filename
    altm_override_attachment_metadata($attachment_id);
    
    // Get user settings
    $auto_generate_alt = get_option('alt_magic_auto_generate', false);
    $auto_rename_upload = get_option('alt_magic_auto_rename_on_upload', false);
    

    // Case 1: Only auto renaming enabled - nothing to do post-upload
    if (!$auto_generate_alt && $auto_rename_upload) {
        altm_log('Case 1 post-upload: Only renaming was enabled, nothing to do');
        return;
    }
    
    // Case 2: Only auto alt text generation enabled
    if ($auto_generate_alt && !$auto_rename_upload) {
        altm_log('Case 2 post-upload: Generating alt text only');
        altm_generate_alt_text_only($attachment_id);
        return;
    }
    
    // Case 3: Both options enabled - retrieve and set alt text from transient
    if ($auto_generate_alt && $auto_rename_upload) {
        altm_log('Case 3 post-upload: Both were enabled, retrieving alt text from transient');
        altm_set_combined_alt_text($attachment_id);
        return;
    }

    // Case 4: Both options disabled - no processing needed
    if (!$auto_generate_alt && !$auto_rename_upload) {
        return;
    }
    
}


/**
 * Case 1: Handle rename only (no alt text generation)
 * 
 * @param array $file The uploaded file array
 * @return array Modified file array with new name
 */
function altm_handle_rename_only($file) {
    $original_name = $file['name'];

    altm_log('Rename only handler called for file: ' . $file['name']);

    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $temp_file_path = $file['tmp_name'];
    
    // Read rename context options first
    $rename_use_seo = get_option('alt_magic_rename_use_seo_keywords', 0);
    $rename_use_post = get_option('alt_magic_rename_use_post_title', 0);
    $rename_use_woocommerce_product_name = get_option('alt_magic_rename_use_woocommerce_product_name', 0);

    // Only fetch post context if at least one option requires it
    $post_context = altm_get_upload_post_context($rename_use_seo, $rename_use_post, $rename_use_woocommerce_product_name);
    
    // Determine if we have meaningful post context for source tracking
    $has_meaningful_context = is_array($post_context) && !empty($post_context['post_id']) && 
                             (!empty($post_context['post_title']) || !empty($post_context['seo_keywords']) || !empty($post_context['woocommerce_product_name']));
    
    $source = $has_meaningful_context ? 'auto_upload-with_post_context' : 'auto_upload-no_post_context';
    
    // Safely access post_context values for logging
    $log_post_id = is_array($post_context) ? ($post_context['post_id'] ?? '') : '';
    $log_post_title = is_array($post_context) ? ($post_context['post_title'] ?? '') : '';
    $log_seo = is_array($post_context) ? ($post_context['seo_keywords'] ?? '') : '';
    $log_woo = is_array($post_context) ? ($post_context['woocommerce_product_name'] ?? '') : '';
    
    altm_log("Auto upload rename context analysis - Post ID: {$log_post_id}, Title: '{$log_post_title}', SEO: '{$log_seo}', WooCommerce: '{$log_woo}', Has context: " . ($has_meaningful_context ? 'yes' : 'no') . ", Source: $source");
    
    // Generate AI filename with optional post context
    $ai_filename = altm_generate_ai_filename_from_temp_file($temp_file_path, $file['type'], $post_context, $original_name, $source);
    
    if (is_wp_error($ai_filename) || empty($ai_filename)) {
        altm_log('AI filename generation failed: ' . ($ai_filename ? $ai_filename->get_error_message() : 'Empty filename'));
        return $file;
    }
    
    // Ensure filename has proper extension
    if (pathinfo($ai_filename, PATHINFO_EXTENSION) !== $file_extension) {
        $ai_filename = pathinfo($ai_filename, PATHINFO_FILENAME) . '.' . $file_extension;
    }
    
    // CRITICAL: Make filename unique using WordPress built-in function
    // This prevents WordPress from auto-renaming after upload, which breaks URLs
    $upload_dir = wp_upload_dir();
    $ai_filename = wp_unique_filename($upload_dir['path'], $ai_filename);
    
    // Update the filename in the file array
    $file['name'] = $ai_filename;
    
    altm_log('Rename only: ' . $original_name . ' -> ' . $ai_filename);
    
    return $file;
}

/**
 * Get post context when uploading via post editor
 * 
 * @return array|false Post context array or false if not available
 */
function altm_get_upload_post_context($use_seo, $use_post, $use_woocommerce_product_name) {

    // If neither option is enabled, skip building context entirely
    if (!$use_seo && !$use_post && !$use_woocommerce_product_name) {
        return false;
    }
    // Try to get post ID from various sources
    $post_id = null;
    
    // Method 1: Check if we're in post editor via AJAX
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress media upload already verifies nonce before this hook
        if (isset($_POST['post_id']) && !empty($_POST['post_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress media upload already verifies nonce before this hook
            $post_id = absint($_POST['post_id']);
        }
    }
    
    // Method 2: Check if we're in post editor via referer
    if (!$post_id && isset($_SERVER['HTTP_REFERER'])) {
        $referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        if (preg_match('/post\.php\?post=(\d+)/', $referer, $matches)) {
            $post_id = absint($matches[1]);
        } elseif (preg_match('/post-new\.php/', $referer)) {
            // New post - check if there's a draft
            $drafts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            if (!empty($drafts)) {
                $post_id = $drafts[0]->ID;
            }
        }
    }
    
    if (!$post_id) {
        return false;
    }
    
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }
    
    // Get SEO keywords if enabled
    $seo_keywords = $use_seo ? altm_fetch_seo_keywords($post_id) : '';

    // Decide between post title and product name based on post type and settings
    $post_title = '';
    $woocommerce_product_name = '';
    if ($use_woocommerce_product_name && $post->post_type === 'product') {
        $woocommerce_product_name = $post->post_title;
        $post_title = '';
    } else {
        $post_title = $use_post ? $post->post_title : '';
        $woocommerce_product_name = '';
    }
    
    return array(
        'post_id' => $post_id,
        'post_title' => $post_title,
        'post_type' => $post->post_type,
        'seo_keywords' => $seo_keywords,
        'woocommerce_product_name' => $woocommerce_product_name
    );
}


/**
 * Case 2: Generate alt text only (post-upload)
 * 
 * @param int $attachment_id The attachment ID
 */
function altm_generate_alt_text_only($attachment_id) {
    altm_log('Generating alt text only for attachment: ' . $attachment_id);
    altm_generate_alt_text($attachment_id, 'auto_upload');
}

/**
 * Case 3: Handle both renaming and alt text generation using combined API
 * 
 * @param array $file The uploaded file array
 * @return array Modified file array with new name
 */
function altm_handle_combined_processing($file) {
    $original_name = $file['name'];
    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $temp_file_path = $file['tmp_name'];
    
    // Read alt text context options. We use the same options for both alt text generation and filename generation
    $combined_use_seo = get_option('alt_magic_use_seo_keywords', 0);
    $combined_use_post = get_option('alt_magic_use_post_title', 0);
    $combined_use_woocommerce_product_name = get_option('alt_magic_woocommerce_use_product_name', 0);

    // Only fetch post context if at least one option requires it
    $post_context = altm_get_upload_post_context($combined_use_seo, $combined_use_post, $combined_use_woocommerce_product_name);
    
    // Determine if we have meaningful post context for source tracking
    $has_meaningful_context = is_array($post_context) && !empty($post_context['post_id']) && 
                             (!empty($post_context['post_title']) || !empty($post_context['seo_keywords']) || !empty($post_context['woocommerce_product_name']));
    
    $source = $has_meaningful_context ? 'auto_upload-with_post_context' : 'auto_upload-no_post_context';
    
    // Safely access post_context values for logging
    $log_post_id = is_array($post_context) ? ($post_context['post_id'] ?? '') : '';
    $log_post_title = is_array($post_context) ? ($post_context['post_title'] ?? '') : '';
    $log_seo = is_array($post_context) ? ($post_context['seo_keywords'] ?? '') : '';
    $log_woo = is_array($post_context) ? ($post_context['woocommerce_product_name'] ?? '') : '';
    
    altm_log("Auto upload context analysis - Post ID: {$log_post_id}, Title: '{$log_post_title}', SEO: '{$log_seo}', WooCommerce: '{$log_woo}', Has context: " . ($has_meaningful_context ? 'yes' : 'no') . ", Source: $source");

    // Generate combined alt text and filename with optional post context
    $result = altm_generate_combined_alt_and_filename($temp_file_path, $file['type'], $post_context, $original_name, $source);
    
    if (is_wp_error($result)) {
        altm_log('Combined API call failed: ' . $result->get_error_message());
        return $file;
    }
    
    // Extract filename and alt text from result
    $ai_filename = $result['filename'];
    $alt_text = $result['alt_text'];
    
    if (empty($ai_filename)) {
        altm_log('No filename returned from combined API');
        return $file;
    }
    
    // Ensure filename has proper extension
    if (pathinfo($ai_filename, PATHINFO_EXTENSION) !== $file_extension) {
        $ai_filename = pathinfo($ai_filename, PATHINFO_FILENAME) . '.' . $file_extension;
    }
    
    // CRITICAL: Make filename unique using WordPress built-in function
    // This prevents WordPress from auto-renaming after upload, which breaks URLs
    $upload_dir = wp_upload_dir();
    $ai_filename = wp_unique_filename($upload_dir['path'], $ai_filename);
    
    // Update the filename in the file array
    $file['name'] = $ai_filename;
    
    // Store alt text for later use in add_attachment hook
    // Use the AI-generated filename (without extension) as the key for simple lookup
    $filename_without_ext = pathinfo($ai_filename, PATHINFO_FILENAME);
    $transient_key = 'altm_pending_alt_' . sanitize_key($filename_without_ext);
    
    set_transient($transient_key, $alt_text, 300); // 5 minutes expiry
    
    altm_log('Combined processing: ' . $original_name . ' -> ' . $ai_filename . ' with alt text: ' . $alt_text);
    altm_log('Stored alt text in transient with key: ' . $transient_key);
    
    return $file;
}


/**
 * Handle alt text setting for combined processing case
 * This is called during add_attachment for case 3
 * 
 * @param int $attachment_id The attachment ID
 * @param array $file The uploaded file data (if available)
 */
function altm_set_combined_alt_text($attachment_id, $file = null) {
    altm_log('Attempting to set alt text for attachment: ' . $attachment_id);
    
    // Get the attachment filename
    $attached_file = get_attached_file($attachment_id);
    if (!$attached_file) {
        altm_log('Could not get attached file for attachment: ' . $attachment_id);
        return;
    }
    
    $current_filename = basename($attached_file);
    $filename_without_ext = pathinfo($current_filename, PATHINFO_FILENAME);
    
    altm_log('Looking for alt text for filename: ' . $current_filename . ' (without ext: ' . $filename_without_ext . ')');
    
    // Look up the transient using the filename (without extension) as the key
    // This works even if WebP conversion changes the extension
    $transient_key = 'altm_pending_alt_' . sanitize_key($filename_without_ext);
    $alt_text = get_transient($transient_key);
    
    if ($alt_text) {
        altm_log('Found alt text in transient: ' . $transient_key);
        
        // Set alt text for the attachment
        $result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        
        // Verify it was actually saved
        $saved_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        altm_log('Update result: ' . ($result ? 'success' : 'failed') . ', Saved alt text: ' . $saved_alt);
        
        // DON'T delete transient yet - let the verification hook at priority 999 do it
        // This allows us to reapply if another plugin overwrites our alt text
        
        altm_log('Successfully set alt text for attachment ' . $attachment_id . ': ' . $alt_text);
    } else {
        altm_log('No alt text found in transient with key: ' . $transient_key);
    }
}

/**
 * Override attachment title and caption with AI-generated filename
 * This prevents WordPress from using EXIF UserComment or other metadata as title
 * 
 * @param int $attachment_id The attachment ID
 */
function altm_override_attachment_metadata($attachment_id) {
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        return;
    }
    
    // Get the current filename (which should be AI-generated if renaming was enabled)
    $attached_file = get_attached_file($attachment_id);
    if (!$attached_file) {
        return;
    }
    
    $filename = basename($attached_file);
    $filename_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    
    // Convert filename to a readable title
    $new_title = str_replace('-', ' ', $filename_without_ext);
    $new_title = ucwords($new_title);
    
    // Generate a clean slug
    $new_slug = sanitize_title($new_title);
    $new_slug = wp_unique_post_slug($new_slug, $attachment_id, 'attachment', 0, null);
    
    // Check if title needs updating
    if ($attachment->post_title !== $new_title) {
        altm_log("Overriding attachment title - Old: '{$attachment->post_title}' → New: '$new_title'");
        
        // Update the attachment post data
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_title' => $new_title,
            'post_name' => $new_slug
        ));
        
        altm_log("Attachment metadata updated - Title: '$new_title', Slug: '$new_slug'");
    }
    
    // Also log the EXIF data for debugging
    // Check if function exists (may not be loaded in all WordPress contexts)
    if (function_exists('wp_read_image_metadata')) {
        $exif_data = wp_read_image_metadata($attached_file);
        if ($exif_data) {
            altm_log("EXIF data from attachment file: " . print_r($exif_data, true));
        }
    } else {
        // Function not available - try to load it if we're in admin context
        if (is_admin() && file_exists(ABSPATH . 'wp-admin/includes/image.php')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            if (function_exists('wp_read_image_metadata')) {
                $exif_data = wp_read_image_metadata($attached_file);
                if ($exif_data) {
                    altm_log("EXIF data from attachment file: " . print_r($exif_data, true));
                }
            }
        }
    }
}

// Register hooks
add_filter('wp_handle_upload_prefilter', 'altm_handle_upload_prefilter', 10, 1);
add_action('add_attachment', 'altm_handle_add_attachment', 10, 1);

// Add a late hook to ensure alt text isn't overwritten by other plugins
add_action('add_attachment', 'altm_verify_and_reapply_alt_text', 999, 1);

/**
 * Verify and re-apply alt text if it was overwritten by another plugin
 * This runs at priority 999 (very late) to ensure our alt text sticks
 */
function altm_verify_and_reapply_alt_text($attachment_id) {
    // Only check if both features are enabled
    $auto_generate_alt = get_option('alt_magic_auto_generate', false);
    $auto_rename_upload = get_option('alt_magic_auto_rename_on_upload', false);
    
    if (!$auto_generate_alt || !$auto_rename_upload) {
        return;
    }
    
    altm_log('Running late verification hook (priority 999) for attachment: ' . $attachment_id);
    
    // Get the attachment filename
    $attached_file = get_attached_file($attachment_id);
    if (!$attached_file) {
        altm_log('Could not get attached file in verification hook');
        return;
    }
    
    $current_filename = basename($attached_file);
    $filename_without_ext = pathinfo($current_filename, PATHINFO_FILENAME);
    
    // Check if we have a pending alt text for this file
    $transient_key = 'altm_pending_alt_' . sanitize_key($filename_without_ext);
    $pending_alt_text = get_transient($transient_key);
    
    if ($pending_alt_text) {
        altm_log('Found pending alt text in verification hook: ' . $pending_alt_text);
        
        // Get the current alt text
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        altm_log('Current alt text in database: "' . $current_alt . '"');
        
        // If alt text is empty or different from what we set, reapply it
        if ($current_alt !== $pending_alt_text) {
            altm_log('Alt text was overwritten! Current: "' . $current_alt . '" Expected: "' . $pending_alt_text . '" - Reapplying...');
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $pending_alt_text);
            
            // Verify one more time
            $final_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            altm_log('Final alt text verification for attachment ' . $attachment_id . ': ' . $final_alt);
        } else {
            altm_log('Alt text verification passed for attachment ' . $attachment_id . ' - no overwrite detected');
        }
        
        // Clean up the transient now (at the very end)
        delete_transient($transient_key);
        altm_log('Cleaned up transient: ' . $transient_key);
    } else {
        altm_log('No pending alt text found in verification hook for key: ' . $transient_key);
    }
}


?>
