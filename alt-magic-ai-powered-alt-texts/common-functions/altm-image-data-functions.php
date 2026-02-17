<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the primary post/page where an attachment image is used.
 * Strategy:
 * 1) Prefer post_parent if it points to a non-attachment, published post.
 * 2) Fallback: search published posts/pages/products by content containing any size URL of the image.
 * 3) Fallback: check featured image mapping (_thumbnail_id).
 * 4) Fallback: search postmeta containing the filename.
 * Returns the first suitable post ID found, or 0 if none.
 */
function altm_get_primary_parent_post($attachment_id) {

    altm_log('Getting primary parent post for attachment id: ' . $attachment_id);
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        altm_log('Attachment not found');
        return null;
    }

    // 1) Direct parent (no type restriction)
    $parent_id = (int) $attachment->post_parent;
    if ($parent_id) {
        $parent = get_post($parent_id);
        if ($parent && $parent->post_type !== 'attachment') {
            altm_log('Parent found: ' . $parent->ID . ' type: ' . $parent->post_type);
            return array('id' => (int)$parent->ID, 'type' => $parent->post_type);
        }
    }

    global $wpdb;

    // Prepare URLs for search (original + sizes) - using shared helper function
    $image_urls = altm_get_all_image_urls($attachment_id);
    altm_log('Image URLs: ' . print_r($image_urls, true));

    // 2) Search in post content for any of the URLs (no type restriction)
    altm_log('Searching in post content for any of the main and transformed URLs');
    foreach ($image_urls as $url) {
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','attachment') AND post_content LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($url) . '%'
        ));
        if ($post && isset($post->ID)) {
            altm_log('Post found: ' . $post->ID . ' type: ' . $post->post_type);
            return array('id' => (int)$post->ID, 'type' => $post->post_type);
        }
    }

    // 3) Featured image usage (no type restriction)
    altm_log('Searching in featured image usage');
    $featured = $wpdb->get_row($wpdb->prepare(
        "SELECT p.ID, p.post_type FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s LIMIT 1",
        $attachment_id
    ));
    if ($featured && isset($featured->ID)) {
        altm_log('Featured image found: ' . $featured->ID . ' type: ' . $featured->post_type);
        return array('id' => (int)$featured->ID, 'type' => $featured->post_type);
    }

    // 4) Search in post meta by filename (no type restriction)
    $file_path = get_attached_file($attachment_id);
    $filename = $file_path ? basename($file_path) : '';
    if ($filename) {
        altm_log('Searching in post meta by filename: ' . $filename);
        $meta_post = $wpdb->get_row($wpdb->prepare(
            "SELECT DISTINCT p.ID, p.post_type FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type NOT IN ('revision','nav_menu_item','attachment') AND pm.meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%'
        ));
        if ($meta_post && isset($meta_post->ID)) {
            return array('id' => (int)$meta_post->ID, 'type' => $meta_post->post_type);
        }
    }

    return null;
}

/**
 * Get image stats
 */
function altm_get_image_stats() {
    global $wpdb;

    // Query to get total number of images
    $total_images_query = $wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = %s 
        AND post_mime_type LIKE %s
    ", 'attachment', 'image/%');
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $total_images = $wpdb->get_var($total_images_query);

    // Query to get total number of images with missing alt text
    $images_with_missing_alt_query = $wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
    ", 'attachment', 'image/%');
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images_with_missing_alt = $wpdb->get_var($images_with_missing_alt_query);
  
    return array(
        'total_images' => $total_images,
        'images_with_missing_alt' => $images_with_missing_alt
    );
}

add_action('wp_ajax_altm_get_image_stats', 'altm_get_image_stats');

/**
 * Get images without alt texts
 */
function altm_get_image_without_alt_texts() {

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'get_image_without_alt_texts_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    global $wpdb;

    $images_without_alt_query = $wpdb->prepare("
        SELECT p.ID as attachment_id, 
               p.post_title,
               pm_file.meta_value as attached_file,
               pm_alt.meta_value as alt_text
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        AND (pm_alt.meta_value IS NULL OR pm_alt.meta_value = '')
        ORDER BY p.ID DESC
    ", 'attachment', 'image/%');
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images_without_alt = $wpdb->get_results($images_without_alt_query);
    
    // Add proper image URLs and extract filename
    foreach ($images_without_alt as $image) {
        // Get proper image URL using WordPress functions
        $image->image_url = wp_get_attachment_url($image->attachment_id);
        
        // Extract filename from attached_file path
        if (!empty($image->attached_file)) {
            $image->filename = basename($image->attached_file);
        } else {
            // Fallback: extract from URL
            $image->filename = basename(wp_parse_url($image->image_url, PHP_URL_PATH));
        }
        
        // Ensure alt_text is properly set
        $image->alt_text = $image->alt_text ?: '';
        
        // Get thumbnail URL for display
        $image->thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'thumbnail');
        if (!$image->thumbnail_url) {
            $image->thumbnail_url = $image->image_url;
        }
    }
    
    altm_log('Images without alt texts: ' . print_r($images_without_alt, true));

    //altm_log('Images without alt texts: ', $images_without_alt);
    wp_send_json($images_without_alt);
}

add_action('wp_ajax_altm_get_image_without_alt_texts', 'altm_get_image_without_alt_texts');

/**
 * Get all images data
 */
function altm_get_all_images_data() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'get_all_images_data_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    global $wpdb;

    $all_images_query = $wpdb->prepare("
        SELECT p.ID as attachment_id, p.guid as image_url
        FROM {$wpdb->posts} p
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s
    ", 'attachment', 'image/%');
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $all_images = $wpdb->get_results($all_images_query);
    
    // Fix any relative URLs
    foreach ($all_images as $image) {
        // Check if URL is relative or missing protocol
        if (!preg_match('/^https?:\/\//i', $image->image_url)) {
            // Replace with proper absolute URL
            $image->image_url = wp_get_attachment_url($image->attachment_id);
        }
    }

    //altm_log('All images: ' . print_r($all_images, true));
    wp_send_json($all_images);
}

add_action('wp_ajax_altm_get_all_images_data', 'altm_get_all_images_data');

/**
 * Get user credits data
 */
function altm_get_user_credits_data() {
    altm_log('Fetching user credits data...');
    $user_id = get_option('alt_magic_user_id');
    $api_key = get_option('alt_magic_api_key');
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL.'/user-details', array(
        'method'    => 'POST',
        'headers'   => array(
            'Content-Type' => 'application/json', 
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body'      => json_encode(array(
            'user_id' => $user_id,
            'site_url' => get_site_url()
        ))
    ));

    $data = json_decode(wp_remote_retrieve_body($response), true);
    altm_log('User credits data: ' . print_r($data['credits_available'], true));
    return $data;
}

/**
 * Fetch user credits
 */
function altm_fetch_user_credits() {
    $api_key = get_option('alt_magic_api_key');
    
    // Check if API key exists
    if (empty($api_key)) {
        wp_send_json(array(
            'success' => false,
            'message' => 'No Alt Magic account connected. Please connect your account in Account Settings.'
        ));
        return;
    }
    
    $data = altm_get_user_credits_data();
    
    // Check if the API returned an authentication error
    if (isset($data['success']) && $data['success'] === false) {
        // Pass through the error message from API
        wp_send_json($data);
        return;
    }
    
    wp_send_json($data);
}

add_action('wp_ajax_altm_fetch_user_credits', 'altm_fetch_user_credits');


/**
 * Get images with empty alt text
 */
function altm_get_images_with_empty_alt_text() {
    global $wpdb;

    $query = $wpdb->prepare("
        SELECT p.ID,
               pm_alt.meta_value as alt_text,
               pm_file.meta_value as attached_file
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        AND (pm_alt.meta_value IS NULL OR pm_alt.meta_value = '')
        ORDER BY p.ID DESC
    ", 'attachment', 'image/%');

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images = $wpdb->get_results($query);
    
    // Add proper image URL and filename data
    foreach ($images as $image) {
        // Get proper image URL using WordPress functions
        $image->image_url = wp_get_attachment_url($image->ID);
        
        // Get filename
        if (!empty($image->attached_file)) {
            $image->filename = basename($image->attached_file);
        } else {
            $image->filename = basename(wp_parse_url($image->image_url, PHP_URL_PATH));
        }
    }
    
    wp_send_json_success($images);
}
add_action('wp_ajax_altm_get_images_with_empty_alt_text', 'altm_get_images_with_empty_alt_text');

/**
 * Get images with short alt text
 */
function altm_get_images_with_short_alt_text() {
    global $wpdb;

    $query = $wpdb->prepare("
        SELECT p.ID,
               pm_alt.meta_value as alt_text,
               pm_file.meta_value as attached_file
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        AND pm_alt.meta_value IS NOT NULL 
        AND pm_alt.meta_value != ''
        AND CHAR_LENGTH(pm_alt.meta_value) < 20
        ORDER BY p.ID DESC
    ", 'attachment', 'image/%');

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images = $wpdb->get_results($query);
    
    // Add proper image URL and filename data
    foreach ($images as $image) {
        // Get proper image URL using WordPress functions
        $image->image_url = wp_get_attachment_url($image->ID);
        
        // Get filename
        if (!empty($image->attached_file)) {
            $image->filename = basename($image->attached_file);
        } else {
            $image->filename = basename(wp_parse_url($image->image_url, PHP_URL_PATH));
        }
    }
    
    wp_send_json_success($images);
}
add_action('wp_ajax_altm_get_images_with_short_alt_text', 'altm_get_images_with_short_alt_text');

/**
 * Get remaining images
 */
function altm_get_remaining_images() {
    global $wpdb;

    $query = $wpdb->prepare("
        SELECT p.ID,
               pm_alt.meta_value as alt_text,
               pm_file.meta_value as attached_file
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        AND pm_alt.meta_value IS NOT NULL 
        AND pm_alt.meta_value != ''
        AND CHAR_LENGTH(pm_alt.meta_value) >= 20
        ORDER BY p.ID DESC
    ", 'attachment', 'image/%');

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images = $wpdb->get_results($query);
    
    // Add proper image URL and filename data
    foreach ($images as $image) {
        // Get proper image URL using WordPress functions
        $image->image_url = wp_get_attachment_url($image->ID);
        
        // Get filename
        if (!empty($image->attached_file)) {
            $image->filename = basename($image->attached_file);
        } else {
            $image->filename = basename(wp_parse_url($image->image_url, PHP_URL_PATH));
        }
    }
    
    wp_send_json_success($images);
}
add_action('wp_ajax_altm_get_remaining_images', 'altm_get_remaining_images');

/**
 * Get all images
 */
function altm_get_all_images() {
    global $wpdb;

    $query = $wpdb->prepare("
        SELECT p.ID,
               pm_alt.meta_value as alt_text,
               pm_file.meta_value as attached_file
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s 
        ORDER BY p.ID DESC
    ", 'attachment', 'image/%');

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images = $wpdb->get_results($query);
    
    // Add proper image URL and filename data
    foreach ($images as $image) {
        // Get proper image URL using WordPress functions
        $image->image_url = wp_get_attachment_url($image->ID);
        
        // Get filename
        if (!empty($image->attached_file)) {
            $image->filename = basename($image->attached_file);
        } else {
            $image->filename = basename(wp_parse_url($image->image_url, PHP_URL_PATH));
        }
    }
    
    wp_send_json_success($images);
}
add_action('wp_ajax_altm_get_all_images', 'altm_get_all_images');


/**
 * Get the default bad name keywords array
 * 
 * This function centralizes all bad name keywords for easy management.
 * To add or remove keywords, simply modify this array.
 * 
 * @return array Array of bad name keywords
 */
function altm_get_bad_name_keywords() {
    return array(
        // Camera/device generated names
        'IMG_', 'DSC_', 'DSCN', 'DSCF', 'PIC_', 'PICT', 'CAM_', 'PHOTO_',
        
        // Generic descriptive words
        'screenshot', 'image', 'photo', 'picture', 'img', 'pics',
        'sample', 'thumbnail', 'thumb', 'test', 'default', 'tmp',
        'new', 'untitled', 'untitled-', 'copy', 'copy-', 'copy_of',
        
        // Stock image keywords
        'unsplash', 'pexels', 'pixabay', 'freepik', 'stock', 'istock', 'getty',
        'adobe', 'shutterstock', 'canva', 'depositphotos', 'dreamstime', '123rf',
        'vecteezy', 'flickr', 'rawpixel', 'picjumbo', 'stocksnap', 'kaboompics',
        'burst', 'resplash', 'scopio', 'pikwizard',
        'wallpaper', 'pattern', 'template', 'banner', 'hero', 'cover',
        'placeholder', 'free', 'sample', 'stockphoto', 'stockimage',
        
        // AI generated keywords
        'ai generated', 'midjourney', 'stability', 'openai', 'dalle', 'dreamlike',
        'ai-generated', 'ai_generated', 'ai_generated_', 'generated_',
        
        // Common generic terms
        'download', 'downloads', 'file', 'files',
        'attachment', 'attachments', 'media', 'medias'
    );
}

/**
 * Generate MySQL REGEXP pattern from bad name keywords array
 * 
 * @param array $keywords Array of bad name keywords
 * @return string MySQL REGEXP pattern
 */
function altm_generate_bad_name_regex($keywords) {
    // Split keywords into smaller groups to avoid MySQL regex limits
    $keyword_groups = array_chunk($keywords, 20); // Process 20 keywords at a time
    $regex_conditions = array();
    
    foreach ($keyword_groups as $group) {
        // Escape special regex characters and join with OR
        $escaped_keywords = array();
        foreach ($group as $keyword) {
            $escaped = preg_quote($keyword, '/');
            $escaped_keywords[] = $escaped;
        }
        
        $keywords_pattern = implode('|', $escaped_keywords);
        
        // Create regex pattern for this group
        $regex_conditions[] = "pm_file.meta_value REGEXP '(?i)(^|/)[^/]*(" . $keywords_pattern . ")[^/]*\\.[A-Za-z0-9]+$'";
    }
    
    // Join all conditions with OR
    return implode(' OR ', $regex_conditions);
}

/**
 * AJAX handler to get images with bad names for the image renaming page
 */
function altm_get_bad_name_images() {
    // Check nonce for security (reuse the same nonce as other list endpoints)
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_fetch_user_credits_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    global $wpdb;

    // Get bad name keywords and generate regex pattern
    $bad_name_keywords = altm_get_bad_name_keywords();
    $keywords_regex = altm_generate_bad_name_regex($bad_name_keywords);

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $offset = ($page - 1) * $per_page;

    // Build search conditions
    $search_conditions = '';
    $search_params = array('attachment', 'image/%');

    if (!empty($search)) {
        $search_conditions = " AND (p.ID LIKE %s OR p.post_title LIKE %s OR pm_file.meta_value LIKE %s)";
        $search_params[] = '%' . $search . '%';
        $search_params[] = '%' . $search . '%';
        $search_params[] = '%' . $search . '%';
    }

    // Bad name patterns using MySQL REGEXP against the filename part
    // Combine keyword patterns with numeric patterns
    $bad_name_regex = $keywords_regex . " OR " .
        // Numeric-only basenames with >=6 digits before the extension
        "pm_file.meta_value REGEXP '(^|/)[0-9]{6,}\\.[A-Za-z0-9]+$' OR " .
        // Any basename containing a sequence of >=7 digits anywhere (extension optional)
        "pm_file.meta_value REGEXP '(^|/)[^/]*[0-9]{7,}[^/]*(\\.[A-Za-z0-9]+)?$'";

    // Total count of bad names
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $search_conditions contains placeholders, $bad_name_regex is safely constructed
    $count_query = $wpdb->prepare(
        "
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s
        $search_conditions
        AND ($bad_name_regex)
        ",
        $search_params
    );
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $total = $wpdb->get_var($count_query);
    $total_pages = ceil(max(0, (int)$total) / max(1, $per_page));

    // Get bad name images with pagination
    $query_params = array_merge($search_params, array($per_page, $offset));
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $search_conditions contains placeholders, $bad_name_regex is safely constructed
    $images_query = $wpdb->prepare(
        "
        SELECT p.ID, p.post_title, pm_file.meta_value as filename
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s
        $search_conditions
        AND ($bad_name_regex)
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
        ",
        $query_params
    );
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $results = $wpdb->get_results($images_query);

    // Build response
    $images = array();
    if (!empty($results)) {
        foreach ($results as $row) {
            $image_url = wp_get_attachment_image_url($row->ID, 'medium');
            if (!$image_url) {
                $image_url = wp_get_attachment_image_url($row->ID, 'thumbnail');
            }
            if (!$image_url) {
                $image_url = wp_get_attachment_url($row->ID);
            }

            $images[] = array(
                'ID' => (int)$row->ID,
                'post_title' => $row->post_title,
                'filename' => basename($row->filename ?: ''),
                'image_url' => $image_url,
                'original_url' => wp_get_attachment_url($row->ID)
            );
        }
    }

    wp_send_json_success(array(
        'images' => $images,
        'total' => (int)$total,
        'pages' => (int)$total_pages
    ));
}
add_action('wp_ajax_altm_get_bad_name_images', 'altm_get_bad_name_images');

/**
 * AJAX handler to get all images for the image renaming page
 */
function altm_get_all_images_for_renaming() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_fetch_user_credits_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    global $wpdb;

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $type_filter = isset($_POST['type_filter']) ? sanitize_text_field(wp_unslash($_POST['type_filter'])) : '';

    $offset = ($page - 1) * $per_page;

    // Build search conditions
    $search_conditions = '';
    $search_params = array('attachment', 'image/%');
    
    if (!empty($search)) {
        $search_conditions = " AND (p.ID LIKE %s OR p.post_title LIKE %s OR pm_file.meta_value LIKE %s)";
        $search_params[] = '%' . $search . '%';
        $search_params[] = '%' . $search . '%';
        $search_params[] = '%' . $search . '%';
    }

    // Build type filter conditions
    $type_filter_conditions = '';
    $type_filter_joins = '';
    if ($type_filter === 'featured') {
        // Filter for featured images only - join with postmeta to find images used as featured images
        $type_filter_joins = " INNER JOIN {$wpdb->postmeta} pm_thumb ON pm_thumb.meta_key = '_thumbnail_id' AND pm_thumb.meta_value = CAST(p.ID AS CHAR)";
    }

    // Get total count
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $search_conditions, $type_filter_conditions, and $type_filter_joins contain placeholders and are safely constructed
    $count_query = $wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        $type_filter_joins
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s
        $search_conditions
        $type_filter_conditions
    ", $search_params);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $total = $wpdb->get_var($count_query);
    $total_pages = ceil($total / $per_page);

    // Get images with pagination
    $query_params = array_merge($search_params, array($per_page, $offset));
    
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $search_conditions, $type_filter_conditions, and $type_filter_joins contain placeholders and are safely constructed
    $images_query = $wpdb->prepare("
        SELECT DISTINCT p.ID, p.post_title, pm_file.meta_value as filename
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        $type_filter_joins
        WHERE p.post_type = %s 
        AND p.post_mime_type LIKE %s
        $search_conditions
        $type_filter_conditions
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ", $query_params);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
    $images = $wpdb->get_results($images_query);

    $processed_images = array();
    
    foreach ($images as $image) {
        $image_url = wp_get_attachment_image_src($image->ID, 'thumbnail');
        if (!$image_url) {
            $image_url = wp_get_attachment_url($image->ID);
            $thumbnail_url = $image_url;
        } else {
            $thumbnail_url = $image_url[0];
            $image_url = $image_url[0];
        }
        
        // Always get the original/full-size image URL
        $original_url = wp_get_attachment_url($image->ID);

        $processed_images[] = array(
            'ID' => $image->ID,
            'filename' => basename($image->filename ?: ''),
            'image_url' => $image_url,
            'original_url' => $original_url
        );
    }

    wp_send_json_success(array(
        'images' => $processed_images,
        'total' => intval($total),
        'pages' => intval($total_pages),
        'current_page' => intval($page)
    ));
}
add_action('wp_ajax_altm_get_all_images_for_renaming', 'altm_get_all_images_for_renaming');

/**
 * AJAX handler to get image usage information
 */
function altm_get_image_usage() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_fetch_user_credits_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    if (!isset($_POST['image_id'])) {
        wp_send_json_error(array('message' => 'Image ID is required.'));
        return;
    }

    $image_id = absint($_POST['image_id']);
    $attachment = get_post($image_id);
    
    if (!$attachment || $attachment->post_type !== 'attachment') {
        wp_send_json_error(array('message' => 'Invalid attachment ID.'));
        return;
    }

    global $wpdb;
    $usage = array();

    // Get the image filename and URL for searching
    $file_path = get_attached_file($image_id);
    $filename = $file_path ? basename($file_path) : '';
    $image_url = wp_get_attachment_url($image_id);
    
    // Get all image sizes URLs for comprehensive search
    $image_urls = array($image_url);
    $metadata = wp_get_attachment_metadata($image_id);
    if ($metadata && isset($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size => $data) {
            $size_url = wp_get_attachment_image_src($image_id, $size);
            if ($size_url) {
                $image_urls[] = $size_url[0];
            }
        }
    }

    // Search in posts content
    foreach ($image_urls as $url) {
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_type 
            FROM {$wpdb->posts} 
            WHERE post_content LIKE %s 
            AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
            AND post_status = 'publish'
            LIMIT 50
        ", '%' . $url . '%'));

        foreach ($posts as $post) {
            $edit_url = get_edit_post_link($post->ID);
            $usage[] = array(
                'id' => $post->ID,
                'title' => $post->post_title ?: 'Untitled',
                'type' => ucfirst($post->post_type),
                'edit_url' => $edit_url,
                'location' => 'Content'
            );
        }
    }

    // Search in post meta
    if ($filename) {
        $meta_posts = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT p.ID, p.post_title, p.post_type, pm.meta_key
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_value LIKE %s
            AND p.post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
            AND p.post_status = 'publish'
            LIMIT 30
        ", '%' . $filename . '%'));

        foreach ($meta_posts as $post) {
            $edit_url = get_edit_post_link($post->ID);
            $usage[] = array(
                'id' => $post->ID,
                'title' => $post->post_title ?: 'Untitled',
                'type' => ucfirst($post->post_type),
                'edit_url' => $edit_url,
                'location' => 'Meta (' . $post->meta_key . ')'
            );
        }
    }

    // Check if it's a featured image
    $featured_posts = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = '_thumbnail_id' 
        AND pm.meta_value = %s
        AND p.post_status = 'publish'
    ", $image_id));

    foreach ($featured_posts as $post) {
        $edit_url = get_edit_post_link($post->ID);
        $usage[] = array(
            'id' => $post->ID,
            'title' => $post->post_title ?: 'Untitled',
            'type' => ucfirst($post->post_type),
            'edit_url' => $edit_url,
            'location' => 'Featured Image'
        );
    }

    // Remove duplicates based on post ID
    $unique_usage = array();
    $seen_ids = array();
    
    foreach ($usage as $item) {
        $key = $item['id'] . '_' . $item['location'];
        if (!in_array($key, $seen_ids)) {
            $seen_ids[] = $key;
            $unique_usage[] = $item;
        }
    }

    wp_send_json_success(array(
        'usage' => $unique_usage,
        'total_usage' => count($unique_usage)
    ));
}
add_action('wp_ajax_altm_get_image_usage', 'altm_get_image_usage');


?>
