<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to get all image URLs (original + all sizes)
 * This is shared logic used by both altm_get_primary_parent_post and altm_update_alt_text_in_all_posts
 */
function altm_get_all_image_urls($attachment_id) {
    $image_urls = array();
    $image_url = wp_get_attachment_url($attachment_id);
    
    if ($image_url) {
        $image_urls[] = $image_url;
        
        // Get all image size URLs
        $metadata = wp_get_attachment_metadata($attachment_id);
        if ($metadata && isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $data) {
                $size_src = wp_get_attachment_image_src($attachment_id, $size);
                if ($size_src && !empty($size_src[0])) {
                    $image_urls[] = $size_src[0];
                }
            }
        }
    }
    
    return array_unique($image_urls);
}

/**
 * Helper function to update alt text in Etch component templates
 * This updates the component definition (e.g., ID 6016) to use a dynamic alt prop
 */
function altm_update_etch_component_alt($component_id, $alt_text, $image_urls) {
    $component = get_post($component_id);
    
    if (!$component || $component->post_type !== 'wp_block') {
        altm_log('Component not found or not a reusable block: ' . $component_id);
        return false;
    }
    
    altm_log('Checking Etch component template: ' . $component_id);
    $content = $component->post_content;
    
    // Check if this component uses props for images (like {props.newProp2})
    if (strpos($content, '{props.') === false) {
        altm_log('Component does not use props, skipping template update');
        return false;
    }
    
    // Check if component already uses {props.alt}
    if (strpos($content, '{props.alt}') !== false) {
        altm_log('Component already uses {props.alt}, no template update needed');
        return false;
    }
    
    // Pattern to match img elements with dynamic props and a hardcoded alt
    // Example: {"tag":"img","attributes":{"src":"{props.newProp2}","alt":"Foto di {props.nome}"}}
    // We want to change the alt to use {props.alt} instead
    $pattern = '/(\"tag\"\s*:\s*\"img\"\s*,\s*\"attributes\"\s*:\s*\{[^}]*\"alt\"\s*:\s*\")([^\"]*)(\"[^}]*\})/i';
    
    $updated = false;
    $content = preg_replace_callback($pattern, function($matches) use ($alt_text, &$updated) {
        $before = $matches[1];
        $current_alt = $matches[2];
        $after = $matches[3];
        
        // Only update if the current alt uses dynamic props (not a static string)
        if (strpos($current_alt, '{props.') === false) {
            return $matches[0]; // Keep static alt as is
        }
        
        altm_log('Found img in component with dynamic alt: ' . $current_alt);
        
        // Replace with {props.alt} so it can be controlled from page instances
        // Etch will use the value passed from the page's attributes
        $new_alt = '{props.alt}';
        
        altm_log('Updated component alt to use {props.alt} for page-level control');
        $updated = true;
        
        return $before . $new_alt . $after;
    }, $content);
    
    if ($updated) {
        wp_update_post(array(
            'ID' => $component_id,
            'post_content' => $content
        ));
        altm_log('Successfully updated component template: ' . $component_id . ' to use {props.alt}');
        return true;
    }
    
    altm_log('No updates needed for component template: ' . $component_id);
    return false;
}

/**
 * Helper function to update alt text in Gutenberg block JSON
 * This handles custom blocks and page builders that store image data in JSON format
 */
function altm_update_alt_in_gutenberg_blocks($content, $attachment_id, $alt_text, $image_urls, $refresh_alt_text, &$updated_components = array()) {
    altm_log('Searching for image in Gutenberg block JSON attributes...');
    
    // Pattern to match Gutenberg block comments with JSON (handles nested braces)
    // Example: <!-- wp:image {"id":123,"alt":"old alt"} -->
    // Example: <!-- wp:etch/component {"ref":6016,"attributes":{"newProp2":"url"}} -->
    $pattern = '/<!--\s+wp:([a-z0-9\/_-]+)\s+(\{(?:[^{}]|(?R))*\})\s+-->/i';
    
    // For PHP < 5.4 that doesn't support recursive patterns, use a simpler approach
    // Match from opening brace to the last closing brace before -->
    $pattern = '/<!--\s+wp:([a-z0-9\/_-]+)\s+(\{.*?\})\s+-->/is';
    
    $updated_content = preg_replace_callback($pattern, function($matches) use ($attachment_id, $alt_text, $image_urls, $refresh_alt_text, &$updated_components) {
        $block_type = $matches[1];
        $json_string = $matches[2];
        
        // For nested JSON, we need to find the correct closing brace
        // Count braces to handle nested objects properly
        $brace_count = 0;
        $json_end = 0;
        for ($i = 0; $i < strlen($json_string); $i++) {
            if ($json_string[$i] === '{') {
                $brace_count++;
            } elseif ($json_string[$i] === '}') {
                $brace_count--;
                if ($brace_count === 0) {
                    $json_end = $i + 1;
                    break;
                }
            }
        }
        
        if ($json_end > 0) {
            $json_string = substr($json_string, 0, $json_end);
        }
        
        // Try to decode the JSON
        $block_attrs = json_decode($json_string, true);
        
        if (!$block_attrs) {
            return $matches[0]; // Return original if JSON decode fails
        }
        
        $should_update = false;
        $update_location = '';
        
        // Check if this block contains our image by ID
        if (isset($block_attrs['id']) && $block_attrs['id'] == $attachment_id) {
            $should_update = true;
            $update_location = 'id';
            altm_log('Found block with matching attachment ID in block type: ' . $block_type);
        }
        
        // Check if this block contains our image by URL
        if (!$should_update) {
            foreach ($image_urls as $url) {
                // Check direct url attribute
                if (isset($block_attrs['url']) && strpos($block_attrs['url'], $url) !== false) {
                    $should_update = true;
                    $update_location = 'url';
                    altm_log('Found block with matching URL in block type: ' . $block_type);
                    break;
                }
                
                // Check src attribute (for img tags in blocks)
                if (isset($block_attrs['attributes']['src']) && strpos($block_attrs['attributes']['src'], $url) !== false) {
                    $should_update = true;
                    $update_location = 'attributes.src';
                    altm_log('Found block with matching URL in attributes.src, block type: ' . $block_type);
                    break;
                }
                
                // Check nested attributes (like newProp2 in etch/component)
                if (isset($block_attrs['attributes']) && is_array($block_attrs['attributes'])) {
                    foreach ($block_attrs['attributes'] as $attr_key => $attr_value) {
                        if (is_string($attr_value) && strpos($attr_value, $url) !== false) {
                            $should_update = true;
                            $update_location = 'attributes.' . $attr_key;
                            altm_log('Found block with matching URL in attributes.' . $attr_key . ', block type: ' . $block_type);
                            break 2;
                        }
                    }
                }
                
                // General check - search entire JSON structure
                if (!$should_update) {
                    $json_full = json_encode($block_attrs);
                    if (strpos($json_full, $url) !== false) {
                        $should_update = true;
                        $update_location = 'nested';
                        altm_log('Found block with matching URL in nested attributes, block type: ' . $block_type);
                        break;
                    }
                }
            }
        }
        
        if ($should_update) {
            // Try to find current alt text in various locations
            $current_alt = '';
            if (isset($block_attrs['alt'])) {
                $current_alt = $block_attrs['alt'];
            } elseif (isset($block_attrs['attributes']['alt'])) {
                $current_alt = $block_attrs['attributes']['alt'];
            }
            
            altm_log('Current block alt text: ' . ($current_alt ? $current_alt : '(empty)') . ' [location: ' . $update_location . ']');
            
            // Update alt text if the option is set to 'all' or if the current alt is empty
            if ($refresh_alt_text === 'all' || empty($current_alt)) {
                // Determine where to place the alt text based on block structure
                if ($update_location === 'attributes.src' || isset($block_attrs['attributes']['src'])) {
                    // Image is in attributes.src (like etch/element img), put alt in attributes.alt
                    $block_attrs['attributes']['alt'] = $alt_text;
                    altm_log('Added alt to attributes.alt for block with attributes.src');
                } elseif (strpos($update_location, 'attributes.') === 0 || isset($block_attrs['attributes'])) {
                    // Image URL found in nested attributes (like etch/component with newProp2)
                    // Add alt to attributes object so it can be passed to the component
                    if (!isset($block_attrs['attributes'])) {
                        $block_attrs['attributes'] = array();
                    }
                    $block_attrs['attributes']['alt'] = $alt_text;
                    altm_log('Added alt to attributes.alt for component/block with nested attributes');
                    
                    // If this is an etch/component with a ref, also try to update the component template
                    // FEATURE FLAG: Set to true to enable updating Etch component templates (Case 2)
                    $enable_component_template_update = false;
                    
                    if ($enable_component_template_update && $block_type === 'etch/component' && isset($block_attrs['ref'])) {
                        $component_ref = $block_attrs['ref'];
                        if (!in_array($component_ref, $updated_components)) {
                            altm_log('Also updating component template: ' . $component_ref);
                            if (altm_update_etch_component_alt($component_ref, $alt_text, $image_urls)) {
                                $updated_components[] = $component_ref;
                            }
                        }
                    }
                } else {
                    // Standard location (direct image blocks)
                    $block_attrs['alt'] = $alt_text;
                    altm_log('Added alt to root level for standard block');
                }
                
                $new_json = json_encode($block_attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                altm_log('Updated block JSON with alt text: ' . $alt_text);
                return '<!-- wp:' . $block_type . ' ' . $new_json . ' -->';
            } else {
                altm_log('Skipping block update (refresh_alt_text: ' . $refresh_alt_text . ', current alt not empty)');
            }
        }
        
        return $matches[0]; // Return original if no update needed
    }, $content);
    
    return $updated_content;
}

// Updates the alt text of the image in all posts containing the image.
function altm_update_alt_text_in_all_posts($attachment_id, $alt_text) {
    global $wpdb;
    // Get the option to determine if alt text should be refreshed
    $refresh_alt_text = get_option('alt_magic_refresh_alt_text');

    // Get all URLs for this image (original + all sizes) - same approach as altm_get_primary_parent_post
    $image_urls = altm_get_all_image_urls($attachment_id);
    
    altm_log('Searching for posts containing attachment ID: ' . $attachment_id);
    altm_log('Image URLs to search: ' . print_r($image_urls, true));
    
    // Track component IDs we've already updated to avoid duplicates
    $updated_components = array();
    
    // Build search query to find posts by:
    // 1. Class patterns (wp-image-{id}, attachment_{id})
    // 2. Actual image URLs (original + all sizes) - this is what altm_get_primary_parent_post uses
    $search_conditions = array();
    $search_params = array();
    
    // Add class-based patterns
    $search_conditions[] = 'post_content LIKE %s';
    $search_params[] = '%wp-image-' . $attachment_id . '%';
    
    $search_conditions[] = 'post_content LIKE %s';
    $search_params[] = '%attachment_' . $attachment_id . '%';
    
    // Add URL-based patterns (THIS IS THE KEY FIX - matches altm_get_primary_parent_post)
    foreach ($image_urls as $url) {
        $search_conditions[] = 'post_content LIKE %s';
        $search_params[] = '%' . $wpdb->esc_like($url) . '%';
    }
    
    // Build the WHERE clause
    $where_clause = '(' . implode(' OR ', $search_conditions) . ') AND post_type NOT IN (\'revision\', \'attachment\', \'nav_menu_item\')';
    
    // Create a unique cache key based on attachment ID and image URLs
    $cache_key = 'altm_posts_with_image_' . $attachment_id . '_' . md5(serialize($image_urls));
    $cache_group = 'altm_image_posts';
    
    // Try to get from cache first
    $posts = wp_cache_get($cache_key, $cache_group);
    
    if (false === $posts) {
        // Not in cache, query database
        $query = $wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts} WHERE " . $where_clause,
            $search_params
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
        $posts = $wpdb->get_results($query);
        
        // Store in cache for 1 hour (cache will be deleted after updates below)
        wp_cache_set($cache_key, $posts, $cache_group, HOUR_IN_SECONDS);
        altm_log('Cached search results for attachment ID: ' . $attachment_id);
    } else {
        altm_log('Retrieved posts from cache for attachment ID: ' . $attachment_id);
    }

    // If no posts are found, exit
    if (empty($posts)) {
        altm_log('No posts found containing the image: ' . $attachment_id);
        return;
    }
    
    altm_log('Found ' . count($posts) . ' posts containing the image');


    // Iterate over each post and update the alt text
    foreach ($posts as $post) {
        altm_log('============================================');
        altm_log('Updating alt text in post: ' . $post->ID);
        
        // DO NOT use wp_unslash() - work with content as-is from database (like alttext.ai does)
        // This preserves JSON escape sequences like \n and \u002d in Gutenberg blocks
        $post_content = $post->post_content;
        
        altm_log('Original post_content length: ' . strlen($post_content));
        altm_log('Original content sample (first 500 chars): ' . substr($post_content, 0, 500));
        
        // Use WordPress 6.2+ HTML Tag Processor when available (more reliable than regex)
        if (version_compare(get_bloginfo('version'), '6.2') >= 0 && class_exists('WP_HTML_Tag_Processor')) {
            altm_log('Using WordPress 6.2+ HTML Tag Processor for more reliable HTML parsing');
            
            $tags = new WP_HTML_Tag_Processor($post_content);
            $content_updated = false;
            
            while ($tags->next_tag('img')) {
                // Check if this image matches our attachment ID
                $class_attr = $tags->get_attribute('class');
                $img_src = $tags->get_attribute('src');
                
                $is_target_image = false;
                
                // Check for wp-image-{id} or attachment_{id} in class attribute
                if ($class_attr && (strpos($class_attr, 'wp-image-' . $attachment_id) !== false || 
                    strpos($class_attr, 'attachment_' . $attachment_id) !== false)) {
                    $is_target_image = true;
                }
                
                // Also check if the attachment ID appears in any other attributes
                if (!$is_target_image) {
                    $all_attributes = array(
                        'data-attachment-id' => $tags->get_attribute('data-attachment-id'),
                        'data-id' => $tags->get_attribute('data-id'),
                        'id' => $tags->get_attribute('id'),
                    );
                    
                    foreach ($all_attributes as $attr_value) {
                        if ($attr_value && strpos($attr_value, (string)$attachment_id) !== false) {
                            $is_target_image = true;
                            break;
                        }
                    }
                }
                
                if ($is_target_image) {
                    $current_alt = $tags->get_attribute('alt');
                    altm_log('Found image tag with src: ' . $img_src);
                    altm_log('Current alt text: ' . ($current_alt ? $current_alt : '(empty)'));
                    
                    // Update alt text if the option is set to 'all' or if the current alt is empty
                    if ($refresh_alt_text === 'all' || empty($current_alt)) {
                        $tags->set_attribute('alt', esc_attr($alt_text));
                        $content_updated = true;
                        altm_log('Updated alt text to: ' . esc_attr($alt_text));
                    } else {
                        altm_log('Skipping update (refresh_alt_text: ' . $refresh_alt_text . ', current alt not empty)');
                    }
                }
            }
            
            $updated_content = $content_updated ? $tags->get_updated_html() : $post_content;
            
            // If no HTML img tags were updated, also try to update Gutenberg block JSON
            // This handles custom blocks and page builders that store images in JSON format
            if (!$content_updated) {
                altm_log('No HTML img tags found, checking Gutenberg block JSON...');
                $updated_content = altm_update_alt_in_gutenberg_blocks($updated_content, $attachment_id, $alt_text, $image_urls, $refresh_alt_text, $updated_components);
                if ($updated_content !== $post_content) {
                    $content_updated = true;
                    altm_log('Updated alt text in Gutenberg block JSON');
                }
            }
            
        } else {
            // Fall back to regex for older WordPress versions
            altm_log('Using regex fallback for WordPress < 6.2 or missing HTML Tag Processor');
            
            $updated_content = preg_replace_callback(
                '/<img[^>]*(?:wp-image-' . $attachment_id . '|attachment_' . $attachment_id . '|class=[\\\\]*"[^"]*' . $attachment_id . '[^"]*[\\\\]*")[^>]*>/', // Handle escaped quotes in slashed content
                function($matches) use ($alt_text, $refresh_alt_text) {
                    $img_tag = $matches[0];
                    
                    // Log the matched image tag
                    altm_log('Found image tag: ' . $img_tag);
                    
                    // Extract current alt text from the image tag - handle escaped quotes
                    $current_alt = '';
                    if (preg_match('/alt=[\\\\]*["\']([^"\'\\\\]*(?:\\\\.[^"\'\\\\]*)*)[\\\\]*["\']/', $img_tag, $alt_matches)) {
                        $current_alt = stripslashes($alt_matches[1]);
                    }
                    altm_log('Current alt text: ' . ($current_alt ? $current_alt : '(empty)'));
                    
                    // Update alt text if the option is set to 'all' or if the current alt is empty
                    if ($refresh_alt_text === 'all' || empty($current_alt)) {
                        $escaped_alt_text = addslashes(esc_attr($alt_text));
                        
                        if (strpos($img_tag, 'alt=') === false) {
                            // Add alt attribute if it doesn't exist
                            $img_tag = str_replace('<img', '<img alt=\"' . $escaped_alt_text . '\"', $img_tag);
                        } else {
                            // Replace existing alt attribute
                            $img_tag = preg_replace('/alt=[\\\\]*["\']([^"\'\\\\]*(?:\\\\.[^"\'\\\\]*)*)[\\\\]*["\']/', 'alt=\"' . $escaped_alt_text . '\"', $img_tag);
                        }
                        altm_log('Updated image tag: ' . $img_tag);
                    } else {
                        altm_log('Skipping update (refresh_alt_text: ' . $refresh_alt_text . ', current alt not empty)');
                    }
                    
                    return $img_tag;
                },
                $post_content
            );
            
            // Also check for Gutenberg block JSON if no HTML img tags were updated
            if ($updated_content === $post_content) {
                altm_log('No HTML img tags found in regex fallback, checking Gutenberg block JSON...');
                $updated_content = altm_update_alt_in_gutenberg_blocks($updated_content, $attachment_id, $alt_text, $image_urls, $refresh_alt_text, $updated_components);
            }
        }

        // If the content was updated, save the changes
        if ($updated_content !== $post_content) {
            altm_log('Content was changed, saving to database...');
            
            // Log updated content before saving
            altm_log('Updated content length: ' . strlen($updated_content));
            altm_log('Updated content sample (first 500 chars): ' . substr($updated_content, 0, 500));
            
            // Check if content contains CSS and log it specifically
            if (strpos($updated_content, 'marquee-container1') !== false) {
                $css_pos = strpos($updated_content, 'marquee-container1');
                altm_log('CSS DETECTED at position ' . $css_pos);
                altm_log('CSS snippet before save: ' . substr($updated_content, max(0, $css_pos - 100), 300));
            }
            
            // Use alttext.ai's exact approach: double backslashes before wp_update_post()
            // This preserves JSON escape sequences like \n, \u002d in Gutenberg blocks
            wp_update_post(array(
                'ID' => $post->ID,
                'post_content' => str_replace('\\', '\\\\', $updated_content),
            ));
            
            altm_log('Post updated successfully for post ID: ' . $post->ID);
        } else {
            altm_log('No changes detected, skipping database update');
        }
        altm_log('============================================');
    }
    
    // Log summary of component templates that were updated
    if (!empty($updated_components)) {
        altm_log('Also updated ' . count($updated_components) . ' component template(s): ' . implode(', ', $updated_components));
    }
    
    // Clear cache after updates since post content has changed
    wp_cache_delete($cache_key, $cache_group);
    altm_log('Cleared cache for attachment ID: ' . $attachment_id);

}