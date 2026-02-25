<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the files
require_once(plugin_dir_path(__FILE__) . '../integrations-functions/altm-fetch-yoast-keywords.php');
require_once(plugin_dir_path(__FILE__) . '../integrations-functions/altm-fetch-rankmath-keywords.php');
require_once(plugin_dir_path(__FILE__) . '../integrations-functions/altm-fetch-squirrly-keywords.php');
require_once(plugin_dir_path(__FILE__) . '../integrations-functions/altm-fetch-seopress-keywords.php');
require_once(plugin_dir_path(__FILE__) . '../integrations-functions/altm-fetch-aiseo-keywords.php');
require_once(plugin_dir_path(__FILE__) . '../admin-functions/altm-supported-languages.php');
require_once(plugin_dir_path(__FILE__) . 'altm-seo-keywords-fetcher.php');


// Single alt text generation

//Generates alt text for a given attachment ID.
function altm_generate_alt_text($attachment_id, $source = 'missing') {
    altm_log('############################################');
    altm_log('Starting altm_generate_alt_text for attachment ID: ' . $attachment_id);
    
    global $altm_supported_languages;
    $attachment = get_post($attachment_id);
    $user_id = get_option('alt_magic_user_id');
    $api_key = get_option('alt_magic_api_key');
    $use_seo_keywords = get_option('alt_magic_use_seo_keywords', 0);
    $use_post_title = get_option('alt_magic_use_post_title', 0);
    $use_woocommerce_product_name = get_option('alt_magic_woocommerce_use_product_name', 0);
    // Fetch each option individually
    $language_code = get_option('alt_magic_language');
    $alt_gen_type = get_option('alt_magic_alt_gen_type', 'default');
    $extra_prompt = get_option('alt_magic_extra_prompt', '');
    //$language_name = isset($altm_supported_languages[$language_code]) ? $altm_supported_languages[$language_code] : 'English';


    // All logs
    altm_log('User ID: ' . $user_id);
    altm_log('Language code: ' . $language_code);
    altm_log('use_seo_keywords: ' . $use_seo_keywords);
    altm_log('use_post_title: ' . $use_post_title);
    altm_log('Attachment: ' . print_r($attachment, true));
    

    if (
        !$attachment ||
        $attachment->post_type !== 'attachment' ||
        strpos($attachment->post_mime_type, 'image/') !== 0 ||
        empty($user_id)
    ) {
        altm_log('Invalid attachment or missing user ID.');
        return false;
    }

    $image_url = wp_get_attachment_image_url($attachment_id, 'full');
    if (!$image_url) {
        altm_log('Failed to retrieve attachment URL.');
        return false;
    }

    //$image_url = set_url_scheme($image_url, 'https'); // Force HTTPS
    $file_extension = pathinfo($image_url, PATHINFO_EXTENSION);
    $image_name = substr(strrchr($image_url, '/'), 1);  

    // Image URL and file extension logs
    altm_log('Image URL: ' . $image_url);
    altm_log('File extension: ' . $file_extension);
    altm_log('Image name: ' . $image_name);
    
    // Fetch primary content post once if options are enabled
    if ($use_seo_keywords || $use_post_title || $use_woocommerce_product_name) {
        $parent = altm_get_primary_parent_post($attachment_id);
        if ($parent && !empty($parent['id'])) {
            $primary_post_id = (int)$parent['id'];
            $primary_post_type = $parent['type'];
            altm_log('Primary post id: ' . $primary_post_id . ' type: ' . $primary_post_type);

            // Get SEO keywords (pass primary post id where possible)
            $seo_keywords = $use_seo_keywords ? altm_fetch_seo_keywords($primary_post_id) : '';
            altm_log('SEO keywords: ' . $seo_keywords);

            // If WooCommerce product context is enabled and parent is a product, prefer product_name and clear title
            if ($use_woocommerce_product_name && $primary_post_type === 'product') {
                $woocommerce_product_name = get_the_title($primary_post_id) ?: '';
                $parent_post_title = '';
            } else {
                $parent_post_title = $use_post_title ? (get_the_title($primary_post_id) ?: '') : '';
                $woocommerce_product_name = '';
            }
            $source = $source . '-with_post_context';
        } else {
            altm_log('No primary post id found');
            $seo_keywords = '';
            $parent_post_title = '';
            $woocommerce_product_name = '';
            $source = $source . '-no_post_context';
        }
    }


    //get site visibility
    $site_visibility = get_option('alt_magic_private_site', 1);
    altm_log('Site visibility: ' . $site_visibility);



    // Request body
    $request_body = array(
        'image_type'      => 'url',
        'image_url'       => $image_url,
        'user_id'         => $user_id,
        'title'           => $parent_post_title,
        'context'         => '',
        'file_extension'  => $file_extension,
        'language'        => $language_code,
        'keywords'        => $seo_keywords,
        'image_name'      => $image_name,
        'image_id'        => $attachment_id,
        'product_name' => $woocommerce_product_name,
        'language_type'=> 'code',
        'site_url'        => get_site_url(),
        'alt_gen_settings_wp' => [
            'alt_gen_type' => $alt_gen_type,
            'chatgpt_prompt_layer' => $extra_prompt
        ],
        'wp_plugin_source' => $source
    );


    if ($site_visibility == 1 ) {

        altm_log('Site is private');
        $image_content = base64_encode( file_get_contents( get_attached_file( $attachment_id ) ) );
        $base64_image = 'data:image/' . $file_extension . ';base64,' . $image_content;

        $request_body['image'] = $base64_image;
        $request_body['image_type'] = 'file';
        $request_body['image_url'] = '';
    }

    $args = array(
        'body'        => wp_json_encode($request_body),
        'headers'     => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key // Add the auth header
        ),
        'timeout'     => 60,
        'blocking'    => true,
        'httpversion' => '1.1',
        'sslverify'   => false,
    );


    altm_log('Sending Alt Magic API request...');
    $response = wp_remote_post(ALT_MAGIC_API_BASE_URL.'/alt-generator-wp', $args); //add auth header with $api_key

    if (is_wp_error($response)) {
        altm_log('Alt Magic API request failed: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if ($response_code === 200 && isset($response_data['alt_text'])) {

        altm_log('Alt text response: ' . $response_data['alt_text']);
        $alt_text = sanitize_text_field($response_data['alt_text']);

        if (!empty($alt_text)) {

            $prepend_string = get_option('alt_magic_prepend_string', '');
            $append_string = get_option('alt_magic_append_string', '');


            if (!empty($prepend_string)) {
                $alt_text = $prepend_string . ' ' . $alt_text;
            }

            if (!empty($append_string)) {
                $alt_text = $alt_text . ' ' . $append_string;
            }

            altm_process_alt_settings($attachment_id, $alt_text);
            return [true, $alt_text];
        } else {
            altm_log("Alt Magic API returned empty alt text.");
            return [false, 'empty_alt_text'];
        }
    } else if ($response_code == 403) {
        // Handle 403 Forbidden responses
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Forbidden';
        altm_log("Alt Magic API returned 403 Forbidden: " . $error_message);
        
        // Check if it's a credits error or authentication error
        if (isset($response_data['message']) && $response_data['message'] == 'No credits remaining.') {
            return [false, 'no_credits', $error_message];
        } else {
            // Authentication or other 403 errors - pass through the message
            return [false, 'authentication_error', $error_message, 403];
        }
    } else {
        altm_log("Alt Magic API unexpected response: Code $response_code, Body: $response_body");
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'unexpected_response';
        return [false, 'unexpected_response', $error_message, $response_code];
    }
}

// Process alt text settings for post alt generation processing
function altm_process_alt_settings($attachment_id, $alt_text) {
    // Fetch each option individually
    //$use_for_title = get_option('alt_magic_use_for_title', 0);
    $use_for_caption = get_option('alt_magic_use_for_caption', 0);
    $use_for_description = get_option('alt_magic_use_for_description', 0);
    

    //altm_log('use_for_title: ' . $use_for_title);
    altm_log('use_for_caption: ' . $use_for_caption);
    altm_log('use_for_description: ' . $use_for_description);

    $attachment_value_updates = array();

    // if ($use_for_title == 1) {
    //     altm_log('Updating post title with: ' . $alt_text);
    //     $attachment_value_updates['post_title'] = $alt_text;
    // }
    if ($use_for_caption == 1) {
        altm_log('Updating post caption with: ' . $alt_text);
        $attachment_value_updates['post_excerpt'] = $alt_text;
    }
    if ($use_for_description == 1) {
        altm_log('Updating post description with: ' . $alt_text);
        $attachment_value_updates['post_content'] = $alt_text;
    }

    if (!empty($attachment_value_updates)) {
        $attachment_value_updates['ID'] = $attachment_id;
        wp_update_post($attachment_value_updates);
    }

    altm_log('Updating attachment alt text in Media Library');
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

    altm_log('Updating attachment alt text in all posts/pages containing this image');
    altm_update_alt_text_in_all_posts($attachment_id, $alt_text);

    altm_log('Updation finished'); 
}





// Bulk alt text generation

/**
 * Generate alt text for multiple images by sending an array of image objects to Alt Magic API
 */
function altm_generate_alt_text_batch($attachment_ids) {
    altm_log('Starting batch alt text generation for ' . count($attachment_ids) . ' images');
    
    $api_key = get_option('alt_magic_api_key');
    $user_id = get_option('alt_magic_user_id');
    
    if (empty($api_key) || empty($user_id)) {
        return array_fill_keys($attachment_ids, array(
            'success' => false,
            'message' => 'API key or user ID not configured'
        ));
    }

    // Get batch size limit from settings (max 10 as per API limit)
    $max_concurrency = get_option('alt_magic_max_concurrency', 5);
    // Ensure concurrency value is valid (1, 5, or 10)
    $valid_values = array(1, 5, 10);
    if (!in_array($max_concurrency, $valid_values)) {
        if ($max_concurrency <= 1) $max_concurrency = 1;
        elseif ($max_concurrency <= 5) $max_concurrency = 5;
        else $max_concurrency = 10;
    }
    $batch_size = $max_concurrency; // Use the concurrency value directly as batch size
    
    altm_log('Processing ' . count($attachment_ids) . ' images with batch size: ' . $batch_size);
    
    // Prepare array of image objects
    $all_image_data = array();
    $valid_attachment_ids = array();
    
    foreach ($attachment_ids as $attachment_id) {
        $image_data = altm_prepare_batch_image_data($attachment_id);
        
        if ($image_data === false) {
            continue; // Skip invalid attachments
        }
        
        $all_image_data[] = $image_data;
        $valid_attachment_ids[] = $attachment_id;
    }
    
    if (empty($all_image_data)) {
        return array();
    }
    
    // Split images into batches
    $image_batches = array_chunk($all_image_data, $batch_size);
    $attachment_id_batches = array_chunk($valid_attachment_ids, $batch_size);
    
    altm_log('Split into ' . count($image_batches) . ' batches of max ' . $batch_size . ' images each');
    
    $all_results = array();
    
    // Process each batch
    foreach ($image_batches as $batch_index => $images_batch) {
        $batch_attachment_ids = $attachment_id_batches[$batch_index];
        
        altm_log('Processing batch ' . ($batch_index + 1) . '/' . count($image_batches) . ' with ' . count($images_batch) . ' images');
        
        // Create the request body with proper structure
        $request_body = array(
            'user_id' => $user_id,
            'site_url' => get_site_url(),
            'images' => $images_batch
        );
        
        // Send batch request
        $args = array(
            'body'        => wp_json_encode($request_body),
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout'     => 120, // Increased timeout for batch processing
            'blocking'    => true,
            'httpversion' => '1.1',
            'sslverify'   => false,
        );

        $response = wp_remote_post(ALT_MAGIC_API_BASE_URL . '/bulk-alt-generator-wp', $args);

        if (is_wp_error($response)) {
            altm_log('Batch ' . ($batch_index + 1) . ' API request failed: ' . $response->get_error_message());
            // Mark all images in this batch as failed
            foreach ($batch_attachment_ids as $attachment_id) {
                $all_results[$attachment_id] = array(
                    'success' => false,
                    'message' => 'Batch request failed: ' . $response->get_error_message()
                );
            }
            continue; // Move to next batch
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        altm_log('Batch ' . ($batch_index + 1) . ' API Response Code: ' . $response_code);

        if ($response_code === 200 && isset($response_data['success']) && ($response_data['success'] === true || $response_data['success'] === 1)) {
            altm_log('Batch ' . ($batch_index + 1) . ' successful. Total processed: ' . $response_data['total_processed'] . ', Successful: ' . $response_data['successful'] . ', Failed: ' . $response_data['failed']);
            
            // Process each result from the batch response
            if (isset($response_data['results']) && is_array($response_data['results'])) {
                foreach ($response_data['results'] as $result) {
                    // Use image_id from the result instead of array index
                    if (isset($result['image_id'])) {
                        $attachment_id = intval($result['image_id']);
                        
                        // Only process if this attachment_id was in our current batch
                        if (in_array($attachment_id, $batch_attachment_ids)) {
                            if (isset($result['success']) && ($result['success'] === true || $result['success'] === 1) && isset($result['alt_text']) && !empty($result['alt_text'])) {
                                $alt_text = sanitize_text_field($result['alt_text']);
                                
                                // Apply prepend/append strings
                                $prepend_string = get_option('alt_magic_prepend_string', '');
                                $append_string = get_option('alt_magic_append_string', '');
                                
                                if (!empty($prepend_string)) {
                                    $alt_text = $prepend_string . ' ' . $alt_text;
                                }
                                
                                if (!empty($append_string)) {
                                    $alt_text = $alt_text . ' ' . $append_string;
                                }
                                
                                // Use altm_process_alt_settings to update alt text, caption, and description
                                // when the respective flags (alt_magic_use_for_caption, alt_magic_use_for_description) are enabled
                                altm_process_alt_settings($attachment_id, $alt_text);
                                
                                $all_results[$attachment_id] = array(
                                    'success' => true,
                                    'alt_text' => $alt_text,
                                    'credits_available' => isset($response_data['credits_available']) ? $response_data['credits_available'] : null,
                                    'final_generation_model' => isset($result['final_generation_model']) ? $result['final_generation_model'] : null
                                );
                            } else {
                                $error_message = isset($result['message']) ? $result['message'] : 'Failed to generate alt text';
                                $all_results[$attachment_id] = array(
                                    'success' => false,
                                    'message' => $error_message
                                );
                            }
                        }
                    }
                }
            }
        } else {
            // API request failed for this batch
            $error_message = 'API request failed';
            $is_credits_error = false;
            $include_credits_in_result = true;
            $stop_processing = false;

            // Handle 502/503/504 (server/timeout errors) — do NOT treat as out of credits
            if (in_array($response_code, array(502, 503, 504), true)) {
                $error_message = __('Generation timeout. Please try this image again.', 'alt-magic');
                $include_credits_in_result = false;
                altm_log('Batch ' . ($batch_index + 1) . ' received ' . $response_code . ' (server/timeout error), continuing with next batch');
            }
            // Handle 401 Unauthorized
            elseif ($response_code === 401) {
                $error_message = __('Authentication failed (401). Please check your API key in Account Settings.', 'alt-magic');
                $include_credits_in_result = false;
                $stop_processing = true;
                altm_log('Batch ' . ($batch_index + 1) . ' received 401 (unauthorized)');
            }
            // Handle 404 Not Found
            elseif ($response_code === 404) {
                $error_message = __('Service unavailable. Please contact support.', 'alt-magic');
                $include_credits_in_result = false;
                $stop_processing = true;
                altm_log('Batch ' . ($batch_index + 1) . ' received 404 (not found)');
            }
            // Handle other 4xx client errors (400, 405, etc. — 401, 403, 404 handled above)
            elseif ($response_code >= 400 && $response_code < 500 && ! in_array($response_code, array(401, 403, 404), true)) {
                $error_message = sprintf(
                    /* translators: %d is the HTTP status code */
                    __('Request failed. Please try again.', 'alt-magic'),
                    $response_code
                );
                if (isset($response_data['error']) && is_string($response_data['error'])) {
                    $error_message = $response_data['error'];
                }
                $include_credits_in_result = false;
                altm_log('Batch ' . ($batch_index + 1) . ' received ' . $response_code . ' (client error)');
            }
            // Handle other 5xx server errors (500, etc.)
            elseif ($response_code >= 500) {
                $error_message = sprintf(
                    /* translators: %d is the HTTP status code */
                    __('Server error. Please try again.', 'alt-magic'),
                    $response_code
                );
                if (isset($response_data['error']) && is_string($response_data['error'])) {
                    $error_message = $response_data['error'];
                }
                $include_credits_in_result = false;
                altm_log('Batch ' . ($batch_index + 1) . ' received ' . $response_code . ' (server error)');
            }
            // Handle 403 Forbidden (insufficient credits)
            elseif ($response_code === 403 && isset($response_data['error'])) {
                $error_message = $response_data['error'];
                $is_credits_error = true;
                $stop_processing = true;

                // Add credit information if available
                if (isset($response_data['credits_available']) && isset($response_data['credits_required'])) {
                    $credits_info = sprintf(
                        ' (Available: %d, Required: %d)',
                        $response_data['credits_available'],
                        $response_data['credits_required']
                    );
                    $error_message .= $credits_info;
                }
            }
            // Handle 200/403 with success false (e.g. API returned success: false with error message)
            elseif (($response_code === 200 || $response_code === 403) && isset($response_data['success']) && ($response_data['success'] === false || $response_data['success'] === 0)) {
                if (isset($response_data['error'])) {
                    $error_message = $response_data['error'];
                    if (isset($response_data['credits_available']) && isset($response_data['credits_required'])) {
                        $is_credits_error = true;
                        $stop_processing = true;
                        $credits_info = sprintf(
                            ' (Available: %d, Required: %d)',
                            $response_data['credits_available'],
                            $response_data['credits_required']
                        );
                        $error_message .= $credits_info;
                    }
                }
            }

            // Build result for each image in the batch (omit credits_available for non-credits errors so UI does not show "out of credits")
            foreach ($batch_attachment_ids as $attachment_id) {
                $result_entry = array(
                    'success' => false,
                    'message' => $error_message
                );
                if ($include_credits_in_result && isset($response_data['credits_available'])) {
                    $result_entry['credits_available'] = $response_data['credits_available'];
                }
                $all_results[$attachment_id] = $result_entry;
            }

            // Stop only for credits errors or fatal errors (401, 404)
            if ($is_credits_error || $stop_processing || ($response_code === 403) || (isset($response_data['error']) && is_string($response_data['error']) && strpos($response_data['error'], 'credits') !== false)) {
                if ($is_credits_error) {
                    altm_log('Stopping batch processing due to insufficient credits');
                } else {
                    altm_log('Stopping batch processing due to error (HTTP ' . $response_code . ')');
                }
                break;
            }
        }
    }
    
    return $all_results;
}

/**
 * Prepare image data for batch request (new structure)
 */
function altm_prepare_batch_image_data($attachment_id) {
    $attachment = get_post($attachment_id);
    
    if (!$attachment || $attachment->post_type !== 'attachment' || strpos($attachment->post_mime_type, 'image/') !== 0) {
        return false;
    }
    
    $use_seo_keywords = get_option('alt_magic_use_seo_keywords', 0);
    $use_post_title = get_option('alt_magic_use_post_title', 0);
    $use_woocommerce_product_name = get_option('alt_magic_woocommerce_use_product_name', 0);
    $alt_gen_type = get_option('alt_magic_alt_gen_type', 'default');
    $extra_prompt = get_option('alt_magic_extra_prompt', '');
    $site_visibility = get_option('alt_magic_private_site', 1);
    $language_code = get_option('alt_magic_language', 'en');

    $image_url = wp_get_attachment_url($attachment_id);
    $image_name = substr(strrchr($image_url, '/'), 1);
    $file_extension = pathinfo($image_url, PATHINFO_EXTENSION);

    // Resolve primary content post once if any context option is enabled
    $primary_post_id = 0;
    $primary_post_type = '';
    if ($use_seo_keywords || $use_post_title || $use_woocommerce_product_name) {
        $parent = altm_get_primary_parent_post($attachment_id);
        if ($parent && !empty($parent['id'])) {
            $primary_post_id = (int)$parent['id'];
            $primary_post_type = $parent['type'];
        }
    }

    // Get SEO keywords as array
    $keywords_array = array();
    if ($use_seo_keywords && $primary_post_id) {
        $keywords_array = altm_fetch_seo_keywords_array($primary_post_id);
    }
    
    // Get post title vs product name following the same rule as single generation
    $parent_post_title = '';
    $woocommerce_product_name = '';
    if ($primary_post_id) {
        if ($use_woocommerce_product_name && $primary_post_type === 'product') {
            $woocommerce_product_name = get_the_title($primary_post_id) ?: '';
            $parent_post_title = '';
        } else {
            $parent_post_title = $use_post_title ? (get_the_title($primary_post_id) ?: '') : '';
            $woocommerce_product_name = '';
        }
    }
    
    $image_data = array(
        'image' => '', // Empty for URL-based images
        'title' => $parent_post_title,
        'keywords' => $keywords_array,
        'image_name' => $image_name,
        'image_type' => 'url',
        'image_url' => $image_url,
        'alt_quality' => 'medium',
        'file_extension' => $file_extension,
        'image_id' => strval($attachment_id),
        'site_id' => get_option('alt_magic_user_id', ''), // Using user_id as site_id
        'product_name' => $woocommerce_product_name,
        'site_type' => 'wordpress',
        'alt_gen_settings_wp' => array(
            'alt_gen_type' => $alt_gen_type,
            'chatgpt_prompt_layer' => $extra_prompt
        ),
        'language_type' => 'code',
        'language' => $language_code
    );
    
    // Handle private sites with base64 encoding
    if ($site_visibility == 1) {
        $image_content = base64_encode( file_get_contents( get_attached_file( $attachment_id ) ) );
        $base64_image = 'data:image/' . $file_extension . ';base64,' . $image_content;

        $image_data['image'] = $base64_image;
        $image_data['image_type'] = 'file';
        $image_data['image_url'] = '';
    }
    
    return $image_data;
}


?>