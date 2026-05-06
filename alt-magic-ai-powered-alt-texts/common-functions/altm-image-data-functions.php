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

    $images_without_alt = altm_prepare_wpml_image_collection($images_without_alt, 'attachment_id');
    
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

    $all_images = altm_prepare_wpml_image_collection($all_images, 'attachment_id');

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

function altm_get_image_processing_query_request() {
    $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    if ($page < 1) {
        $page = 1;
    }

    $per_page = min(500, max(1, $per_page));

    return array(
        'page' => $page,
        'per_page' => $per_page,
        'search' => $search,
    );
}

function altm_get_wpml_translations_table_name() {
    static $table_name = null;

    if ($table_name !== null) {
        return $table_name;
    }

    if (!altm_is_wpml_active()) {
        $table_name = '';
        return $table_name;
    }

    global $wpdb;

    $candidate = $wpdb->prefix . 'icl_translations';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));
    $table_name = $exists === $candidate ? $candidate : '';

    return $table_name;
}

function altm_get_image_processing_filter_language_code() {
    if (!altm_is_wpml_active()) {
        return '';
    }

    $scope = altm_get_wpml_bulk_image_scope();

    if ($scope !== 'current_language') {
        return '';
    }

    $current_language = altm_get_wpml_current_language_data();

    return !empty($current_language['code']) ? $current_language['code'] : '';
}

function altm_get_image_processing_query_args($tab, $search = '', $cursor_id = 0) {
    global $wpdb;

    $tab = is_string($tab) ? $tab : '';
    $search = trim((string) $search);
    $cursor_id = absint($cursor_id);

    $base_joins = array();
    $base_wheres = array(
        'p.post_type = %s',
        'p.post_mime_type LIKE %s',
    );
    $params = array('attachment', 'image/%');

    if ($tab === 'empty-alt') {
        $base_joins[] = "LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'";
        $base_wheres[] = "(pm_alt.meta_value IS NULL OR pm_alt.meta_value = '')";
    } elseif ($tab === 'short-alt') {
        $base_joins[] = "INNER JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'";
        $base_wheres[] = "pm_alt.meta_value IS NOT NULL";
        $base_wheres[] = "pm_alt.meta_value != ''";
        $base_wheres[] = "CHAR_LENGTH(pm_alt.meta_value) < 20";
    } elseif ($tab === 'all-images') {
        $base_joins[] = "LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'";
    } else {
        return false;
    }

    if ($search !== '') {
        $base_wheres[] = "(CAST(p.ID AS CHAR) LIKE %s OR COALESCE(pm_alt.meta_value, '') LIKE %s)";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    if ($cursor_id > 0) {
        $base_wheres[] = 'p.ID < %d';
        $params[] = $cursor_id;
    }

    $translations_table = altm_get_wpml_translations_table_name();
    $wpml_language_code = altm_get_image_processing_filter_language_code();
    $wpml_join = '';
    $wpml_where = '';

    if ($translations_table !== '' && $wpml_language_code !== '') {
        $wpml_join = " INNER JOIN {$translations_table} wpml_tr ON wpml_tr.element_id = p.ID AND wpml_tr.element_type = 'post_attachment'";
        $wpml_where = 'wpml_tr.language_code = %s';
        $params[] = $wpml_language_code;
    }

    return array(
        'tab' => $tab,
        'joins' => $base_joins,
        'wheres' => $base_wheres,
        'params' => $params,
        'wpml_join' => $wpml_join,
        'wpml_where' => $wpml_where,
    );
}

function altm_get_image_processing_total_count($tab, $search = '') {
    global $wpdb;

    $query_args = altm_get_image_processing_query_args($tab, $search);
    if ($query_args === false) {
        return 0;
    }

    $joins = implode(' ', $query_args['joins']) . $query_args['wpml_join'];
    $where_parts = $query_args['wheres'];

    if ($query_args['wpml_where'] !== '') {
        $where_parts[] = $query_args['wpml_where'];
    }

    $where_sql = implode(' AND ', $where_parts);
    $count_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$joins} WHERE {$where_sql}";
    $count_params = $query_args['params'];

    if (!empty($count_params)) {
        $count_query = $wpdb->prepare($count_query, $count_params);
    }

    return (int) $wpdb->get_var($count_query);
}

function altm_prepare_image_processing_rows($rows) {
    if (empty($rows)) {
        return array();
    }

    $images = array();

    foreach ($rows as $row) {
        $image_url = wp_get_attachment_image_url($row->ID, 'medium');
        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($row->ID, 'thumbnail');
        }
        if (!$image_url) {
            $image_url = wp_get_attachment_url($row->ID);
        }

        $alt_text = isset($row->alt_text) ? (string) $row->alt_text : '';
        $attached_file = isset($row->attached_file) ? (string) $row->attached_file : '';
        $language_data = altm_get_wpml_attachment_language_data_for_list((int) $row->ID, isset($row->wpml_language_code) ? (string) $row->wpml_language_code : '');

        $images[] = array(
            'ID' => (int) $row->ID,
            'alt_text' => $alt_text,
            'image_url' => $image_url,
            'filename' => $attached_file !== '' ? basename($attached_file) : basename((string) wp_parse_url($image_url, PHP_URL_PATH)),
            'wpml_language_code' => $language_data['code'],
            'wpml_language_label' => $language_data['label'],
            'wpml_flag_url' => $language_data['flag_url'],
        );
    }

    return $images;
}

function altm_get_image_processing_page_response($tab, $page, $per_page, $search = '') {
    global $wpdb;

    $query_args = altm_get_image_processing_query_args($tab, $search);
    if ($query_args === false) {
        return new WP_Error('invalid_tab', __('Invalid image processing tab.', 'alt-magic'));
    }

    $joins = implode(' ', $query_args['joins']) . $query_args['wpml_join'];
    $where_parts = $query_args['wheres'];

    if ($query_args['wpml_where'] !== '') {
        $where_parts[] = $query_args['wpml_where'];
    }

    $where_sql = implode(' AND ', $where_parts);
    $total = altm_get_image_processing_total_count($tab, $search);
    $total_pages = $total > 0 ? (int) ceil($total / max(1, $per_page)) : 0;

    if ($total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
    }

    if ($page < 1) {
        $page = 1;
    }

    $offset = ($page - 1) * $per_page;

    $row_query = "
        SELECT DISTINCT p.ID,
               COALESCE(pm_alt.meta_value, '') AS alt_text,
               pm_file.meta_value AS attached_file" .
               ($query_args['wpml_join'] !== '' ? ', wpml_tr.language_code AS wpml_language_code' : '') . "
        FROM {$wpdb->posts} p
        " . implode(' ', $query_args['joins']) . "
        LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        {$query_args['wpml_join']}
        WHERE {$where_sql}
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ";

    $row_params = $query_args['params'];
    $row_params[] = $per_page;
    $row_params[] = $offset;
    $row_query = $wpdb->prepare($row_query, $row_params);
    $rows = $wpdb->get_results($row_query);

    return array(
        'images' => altm_prepare_image_processing_rows($rows),
        'total' => $total,
        'pages' => $total_pages,
        'page' => $page,
        'page_size' => $per_page,
    );
}

function altm_get_image_processing_chunk_ids($tab, $search = '', $chunk_size = 25, $cursor_id = 0) {
    global $wpdb;

    $chunk_size = min(50, max(1, (int) $chunk_size));
    $query_args = altm_get_image_processing_query_args($tab, $search, $cursor_id);
    if ($query_args === false) {
        return array();
    }

    $joins = implode(' ', $query_args['joins']) . $query_args['wpml_join'];
    $where_parts = $query_args['wheres'];

    if ($query_args['wpml_where'] !== '') {
        $where_parts[] = $query_args['wpml_where'];
    }

    $where_sql = implode(' AND ', $where_parts);
    $id_query = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        {$joins}
        WHERE {$where_sql}
        ORDER BY p.ID DESC
        LIMIT %d
    ";

    $params = $query_args['params'];
    $params[] = $chunk_size;
    $id_query = $wpdb->prepare($id_query, $params);

    return array_map('intval', $wpdb->get_col($id_query));
}

function altm_handle_image_processing_page_request($tab) {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_image_processing_list_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    $request = altm_get_image_processing_query_request();
    $response = altm_get_image_processing_page_response($tab, $request['page'], $request['per_page'], $request['search']);

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }

    wp_send_json_success($response);
}


/**
 * Get images with empty alt text
 */
function altm_get_images_with_empty_alt_text() {
    altm_handle_image_processing_page_request('empty-alt');
}
add_action('wp_ajax_altm_get_images_with_empty_alt_text', 'altm_get_images_with_empty_alt_text');

/**
 * Get images with short alt text
 */
function altm_get_images_with_short_alt_text() {
    altm_handle_image_processing_page_request('short-alt');
}
add_action('wp_ajax_altm_get_images_with_short_alt_text', 'altm_get_images_with_short_alt_text');

/**
 * Get remaining images
 */
function altm_get_remaining_images() {
    altm_handle_image_processing_page_request('all-images');
}
add_action('wp_ajax_altm_get_remaining_images', 'altm_get_remaining_images');

/**
 * Get all images
 */
function altm_get_all_images() {
    altm_handle_image_processing_page_request('all-images');
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

function altm_build_renaming_image_rows($results) {
    $images = array();

    if (empty($results)) {
        return $images;
    }

    foreach ($results as $row) {
        $image_url = wp_get_attachment_image_url($row->ID, 'medium');
        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($row->ID, 'thumbnail');
        }
        if (!$image_url) {
            $image_url = wp_get_attachment_url($row->ID);
        }

        $images[] = array(
            'ID' => (int) $row->ID,
            'post_title' => $row->post_title,
            'filename' => basename($row->filename ?: ''),
            'image_url' => $image_url,
            'original_url' => wp_get_attachment_url($row->ID),
        );
    }

    return array_values(altm_prepare_wpml_image_collection($images, 'ID'));
}

function altm_get_bad_name_regex_clause() {
    $bad_name_keywords = altm_get_bad_name_keywords();
    $keywords_regex = altm_generate_bad_name_regex($bad_name_keywords);

    return $keywords_regex . " OR " .
        "pm_file.meta_value REGEXP '(^|/)[0-9]{6,}\\.[A-Za-z0-9]+$' OR " .
        "pm_file.meta_value REGEXP '(^|/)[^/]*[0-9]{7,}[^/]*(\\.[A-Za-z0-9]+)?$'";
}

function altm_get_image_renaming_query_request() {
    $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $type_filter = isset($_POST['type_filter']) ? sanitize_text_field(wp_unslash($_POST['type_filter'])) : '';

    if ($page < 1) {
        $page = 1;
    }

    $per_page = min(500, max(1, $per_page));

    return array(
        'page' => $page,
        'per_page' => $per_page,
        'search' => $search,
        'type_filter' => $type_filter,
    );
}

function altm_get_image_renaming_query_args($tab, $search = '', $type_filter = '', $cursor_id = 0) {
    global $wpdb;

    $tab = is_string($tab) ? $tab : '';
    $search = trim((string) $search);
    $type_filter = trim((string) $type_filter);
    $cursor_id = absint($cursor_id);

    $joins = array(
        "LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'",
    );
    $wheres = array(
        'p.post_type = %s',
        'p.post_mime_type LIKE %s',
    );
    $params = array('attachment', 'image/%');

    if ($tab === 'bad-names') {
        $wheres[] = '(' . altm_get_bad_name_regex_clause() . ')';
    } elseif ($tab === 'all-images') {
        if ($type_filter === 'featured') {
            $joins[] = "INNER JOIN {$wpdb->postmeta} pm_thumb ON pm_thumb.meta_key = '_thumbnail_id' AND pm_thumb.meta_value = CAST(p.ID AS CHAR)";
        }
    } else {
        return false;
    }

    if ($search !== '') {
        $wheres[] = '(CAST(p.ID AS CHAR) LIKE %s OR p.post_title LIKE %s OR pm_file.meta_value LIKE %s)';
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($cursor_id > 0) {
        $wheres[] = 'p.ID < %d';
        $params[] = $cursor_id;
    }

    return array(
        'joins' => $joins,
        'wheres' => $wheres,
        'params' => $params,
    );
}

function altm_get_image_renaming_total_count($tab, $search = '', $type_filter = '') {
    global $wpdb;

    $query_args = altm_get_image_renaming_query_args($tab, $search, $type_filter);
    if ($query_args === false) {
        return 0;
    }

    $joins = implode(' ', $query_args['joins']);
    $where_sql = implode(' AND ', $query_args['wheres']);
    $count_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$joins} WHERE {$where_sql}";

    if (!empty($query_args['params'])) {
        $count_query = $wpdb->prepare($count_query, $query_args['params']);
    }

    return (int) $wpdb->get_var($count_query);
}

function altm_get_image_renaming_page_response($tab, $page, $per_page, $search = '', $type_filter = '') {
    global $wpdb;

    $query_args = altm_get_image_renaming_query_args($tab, $search, $type_filter);
    if ($query_args === false) {
        return new WP_Error('invalid_tab', __('Invalid image renaming tab.', 'alt-magic'));
    }

    $total = altm_get_image_renaming_total_count($tab, $search, $type_filter);
    $total_pages = $total > 0 ? (int) ceil($total / max(1, $per_page)) : 0;

    if ($total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
    }

    if ($page < 1) {
        $page = 1;
    }

    $offset = ($page - 1) * $per_page;
    $joins = implode(' ', $query_args['joins']);
    $where_sql = implode(' AND ', $query_args['wheres']);
    $row_query = "
        SELECT DISTINCT p.ID,
               p.post_title,
               pm_file.meta_value AS filename
        FROM {$wpdb->posts} p
        {$joins}
        WHERE {$where_sql}
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ";

    $row_params = $query_args['params'];
    $row_params[] = $per_page;
    $row_params[] = $offset;
    $row_query = $wpdb->prepare($row_query, $row_params);
    $rows = $wpdb->get_results($row_query);

    return array(
        'images' => altm_build_renaming_image_rows($rows),
        'total' => $total,
        'pages' => $total_pages,
        'current_page' => $page,
        'page_size' => $per_page,
    );
}

function altm_get_image_renaming_chunk_ids($tab, $search = '', $type_filter = '', $chunk_size = 25, $cursor_id = 0) {
    global $wpdb;

    $chunk_size = min(100, max(1, (int) $chunk_size));
    $query_args = altm_get_image_renaming_query_args($tab, $search, $type_filter, $cursor_id);
    if ($query_args === false) {
        return array();
    }

    $joins = implode(' ', $query_args['joins']);
    $where_sql = implode(' AND ', $query_args['wheres']);
    $id_query = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        {$joins}
        WHERE {$where_sql}
        ORDER BY p.ID DESC
        LIMIT %d
    ";

    $params = $query_args['params'];
    $params[] = $chunk_size;
    $id_query = $wpdb->prepare($id_query, $params);

    return array_map('intval', $wpdb->get_col($id_query));
}

function altm_get_bad_name_image_ids($search = '', $cursor_id = 0, $limit = 25) {
    return altm_get_image_renaming_chunk_ids('bad-names', $search, '', $limit, $cursor_id);
}

function altm_get_all_renaming_image_ids($search = '', $type_filter = '', $cursor_id = 0, $limit = 25) {
    return altm_get_image_renaming_chunk_ids('all-images', $search, $type_filter, $limit, $cursor_id);
}

function altm_handle_image_renaming_page_request($tab) {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_fetch_user_credits_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    $request = altm_get_image_renaming_query_request();
    $response = altm_get_image_renaming_page_response($tab, $request['page'], $request['per_page'], $request['search'], $request['type_filter']);

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }

    wp_send_json_success($response);
}

/**
 * AJAX handler to get images with bad names for the image renaming page
 */
function altm_get_bad_name_images() {
    altm_handle_image_renaming_page_request('bad-names');
}
add_action('wp_ajax_altm_get_bad_name_images', 'altm_get_bad_name_images');

/**
 * AJAX handler to get all images for the image renaming page
 */
function altm_get_all_images_for_renaming() {
    altm_handle_image_renaming_page_request('all-images');
}
add_action('wp_ajax_altm_get_all_images_for_renaming', 'altm_get_all_images_for_renaming');

function altm_get_bulk_rename_chunk_ids() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_fetch_user_credits_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
        return;
    }

    if (function_exists('altm_is_wpml_active') && altm_is_wpml_active()) {
        wp_send_json_error(array('message' => 'Bulk image renaming is not available when WPML is active.'));
        return;
    }

    $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'all-images';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $type_filter = isset($_POST['type_filter']) ? sanitize_text_field(wp_unslash($_POST['type_filter'])) : '';
    $cursor_id = isset($_POST['cursor_id']) ? absint(wp_unslash($_POST['cursor_id'])) : 0;
    $chunk_size = isset($_POST['chunk_size']) ? absint(wp_unslash($_POST['chunk_size'])) : 25;

    if ($tab === 'bad-names') {
        $ids = altm_get_bad_name_image_ids($search, $cursor_id, $chunk_size);
    } else {
        $ids = altm_get_all_renaming_image_ids($search, $type_filter, $cursor_id, $chunk_size);
    }

    $next_cursor = !empty($ids) ? end($ids) : 0;

    wp_send_json_success(array(
        'ids' => $ids,
        'next_cursor' => $next_cursor ? (int) $next_cursor : 0,
        'has_more' => count($ids) === min(100, max(1, $chunk_size)),
    ));
}
add_action('wp_ajax_altm_get_bulk_rename_chunk_ids', 'altm_get_bulk_rename_chunk_ids');

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
