<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to get valid concurrency value
 * Maps old values to new valid options: 1, 5, 10
 */
function altm_get_valid_concurrency_value() {
    $current_value = get_option('alt_magic_max_concurrency', 5);
    $valid_values = array(1, 5, 10);
    
    // If current value is already valid, return it
    if (in_array($current_value, $valid_values)) {
        return $current_value;
    }
    
    // Map old values to closest new values
    if ($current_value <= 1) return 1;
    if ($current_value <= 5) return 5;
    
    return 10; // For values > 5
}

// Include the supported languages file

function alt_magic_render_ai_settings_page() {

    // Enqueue the CSS file with a version number
    //altm_log('Enqueueing AI settings page CSS');
    wp_enqueue_style(
        'alt-magic-media-popup-button-css',
        plugin_dir_url(__FILE__) . '../css/altm-ai-settings-page.css',
        array(), // Dependencies
        '1.0.3'  // Version number
    );

    // Register and enqueue the JavaScript file
    wp_register_script(
        'alt-magic-ai-settings-js',
        esc_url(plugin_dir_url(__FILE__) . '../scripts/altm-ai-settings-page-script.js'),
        array('jquery'), // Dependencies
        '1.0.4', // Version number - Updated for image accessibility check
        true // Load in footer
    );
    wp_enqueue_script('alt-magic-ai-settings-js');

    // Pass data to the JavaScript file
    wp_localize_script('alt-magic-ai-settings-js', 'altMagicSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('alt_magic_save_settings')
    ));

    // Fetch each option individually
    $options = [
        'alt_magic_auto_generate' => get_option('alt_magic_auto_generate', 0),
        'alt_magic_alt_gen_type' => get_option('alt_magic_alt_gen_type', 'default'),
        'alt_magic_language' => get_option('alt_magic_language', 'en'),
        'alt_magic_use_for_title' => get_option('alt_magic_use_for_title', 0),
        'alt_magic_use_for_caption' => get_option('alt_magic_use_for_caption', 0),
        'alt_magic_use_for_description' => get_option('alt_magic_use_for_description', 0),
        'alt_magic_prepend_string' => get_option('alt_magic_prepend_string', ''),
        'alt_magic_append_string' => get_option('alt_magic_append_string', ''),
        'alt_magic_extra_prompt' => get_option('alt_magic_extra_prompt', ''),
        'alt_magic_use_seo_keywords' => get_option('alt_magic_use_seo_keywords', 0),
        'alt_magic_use_post_title' => get_option('alt_magic_use_post_title', 0),
        'alt_magic_refresh_alt_text' => get_option('alt_magic_refresh_alt_text', 'all'),
        'alt_magic_private_site' => get_option('alt_magic_private_site', 0),
        'alt_magic_woocommerce_use_product_name' => get_option('alt_magic_woocommerce_use_product_name', 0),
        'alt_magic_max_concurrency' => altm_get_valid_concurrency_value(),
        // Rename options
        'alt_magic_auto_rename_on_upload' => get_option('alt_magic_auto_rename_on_upload', 0),
        'alt_magic_enable_redirections' => get_option('alt_magic_enable_redirections', 0),
        'alt_magic_rename_use_seo_keywords' => get_option('alt_magic_rename_use_seo_keywords', 0),
        'alt_magic_rename_use_post_title' => get_option('alt_magic_rename_use_post_title', 0),
        'alt_magic_rename_use_woocommerce_product_name' => get_option('alt_magic_rename_use_woocommerce_product_name', 0),
        // Advanced URL Update Settings
        'alt_magic_update_posts' => get_option('alt_magic_update_posts', 1),
        'alt_magic_update_excerpts' => get_option('alt_magic_update_excerpts', 1),
        'alt_magic_update_postmeta' => get_option('alt_magic_update_postmeta', 1),
        'alt_magic_update_guid' => get_option('alt_magic_update_guid', 0),
        'alt_magic_rename_language' => get_option('alt_magic_rename_language', 'en')
    ];

    global $altm_supported_languages; // Make sure the $altm_supported_languages variable is accessible
    
    // For debugging purposes, you can uncomment these lines:
    // echo '<pre>';
    // print_r($options);
    // echo '</pre>';
    ?>
    <div class="wrap">
        <h1>Alt Magic AI Settings</h1>
        <div class="ai-settings-container">
            <form id="alt-magic-settings-form">
                <?php wp_nonce_field('alt_magic_save_settings', 'alt_magic_nonce'); ?>
                <h2 class="nav-tab-wrapper" style="margin-top:16px;">
                    <a href="#" class="nav-tab nav-tab-active" data-target="altm-tab-alt">Alt Text Settings</a>
                    <a href="#" class="nav-tab" data-target="altm-tab-rename">Image Rename Settings</a>
                </h2>
                <table class="form-table" id="alt-magic-settings-table">
                    <tbody id="altm-tab-alt" class="altm-tab-content" style="display: table-row-group;">
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Auto-generate Alt Text</div>
                            <div class="setting-description">Automatically generate alt text for new images</div>
                        </th>
                        <td>
                            <input type="checkbox" name="alt_magic_auto_generate" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_auto_generate'])); ?>> Automatically generate alt text when new images are added
                            <p class="alt-magic-setting-sub-label">Note: It will automatically generate alt text for all images added to your website.</p>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Alt Text Verbosity</div>
                            <div class="setting-description">Choose how detailed your alt text should be</div>
                        </th>
                        <td>
                            <div class="alt-magic-setting-group">
                                <p class="alt-magic-setting-label">
                                    <input type="radio" name="alt_magic_alt_gen_type" value="descriptive" class="alt-magic-setting" <?php checked($options['alt_magic_alt_gen_type'], 'descriptive'); ?>> 
                                    <strong>Elaborated</strong>
                                </p>
                                <p class="alt-magic-setting-sub-label">Example: A tall suspension bridge stretches across a body of water with a sailboat below. The bridge casts long shadows on the blue water. In the background, a layer of fog partially obscures a mountainous landscape under a clear, blue sky.</p>
                            </div>
                            <div class="alt-magic-setting-group">
                                <p class="alt-magic-setting-label">
                                    <input type="radio" name="alt_magic_alt_gen_type" value="default" class="alt-magic-setting" <?php checked($options['alt_magic_alt_gen_type'], 'default'); ?>>
                                    <strong>Standard</strong>
                                </p>
                                <p class="alt-magic-setting-sub-label">Example: A sailboat glides through calm waters beneath a large suspension bridge enveloped by mist. The bridge's tall cables stretch skyward against a bright blue sky, while faint hills loom in the background.</p>
                            </div>
                            <div class="alt-magic-setting-group">
                                <p class="alt-magic-setting-label">
                                    <input type="radio" name="alt_magic_alt_gen_type" value="concise" class="alt-magic-setting" <?php checked($options['alt_magic_alt_gen_type'], 'concise'); ?>>
                                    <strong>Concise</strong>
                                </p>
                                <p class="alt-magic-setting-sub-label">Example: A large suspension bridge spans over a body of water with a sailboat underneath. The scene is partially enveloped in fog.</p>
                            </div>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Alt Text Language</div>
                            <div class="setting-description">Select the language for generated alt text</div>
                        </th>
                        <td>
                            <select name="alt_magic_language" class="alt-magic-setting">
                                <?php 
                                if (isset($altm_supported_languages) && is_array($altm_supported_languages)) {
                                    foreach ($altm_supported_languages as $code => $language): 
                                ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($options['alt_magic_language'], $code); ?>><?php echo esc_html($language); ?></option>
                                <?php 
                                    endforeach;
                                } else {
                                    echo '<option value="">No languages available</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Field Mapping</div>
                            <div class="setting-description">Use generated alt text for other image fields</div>
                        </th>
                        <td>
                            <!-- <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_use_for_title" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_use_for_title'])); ?>> Use same alt text value for image title</p> -->
                            <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_use_for_caption" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_use_for_caption'])); ?>> Use same alt text value for image caption</p>
                            <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_use_for_description" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_use_for_description'])); ?>> Use same alt text value for image description</p>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Text Prefix</div>
                            <div class="setting-description">Add text to the beginning of alt text</div>
                        </th>
                        <td>
                            <input type="text" name="alt_magic_prepend_string" class="alt-magic-setting" value="<?php echo esc_attr($options['alt_magic_prepend_string']); ?>">
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Text Suffix</div>
                            <div class="setting-description">Add text to the end of alt text</div>
                        </th>
                        <td>
                            <input type="text" name="alt_magic_append_string" class="alt-magic-setting" value="<?php echo esc_attr($options['alt_magic_append_string']); ?>">
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Add ChatGPT Prompt</div>
                            <div class="setting-description">Customize the style of your alt text</div>
                        </th>
                        <td>
                            <textarea name="alt_magic_extra_prompt" class="alt-magic-setting" rows="3"><?php echo esc_textarea($options['alt_magic_extra_prompt']); ?></textarea>
                            <p class="alt-magic-setting-sub-label">This extra prompt will be used on the default alt text generated value to generate a final output.<br> Prompt example: "Keep all the words in upper caps. Like - THIS IS A SAMPLE ALT TEXT"</p>
                        </td>
                    </tr>
                    
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">SEO Integration</div>
                            <div class="setting-description">Use SEO keywords for better alt text</div>
                        </th>
                        <td>
                            <input type="checkbox" name="alt_magic_use_seo_keywords" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_use_seo_keywords'])); ?>> Use SEO focus keyphrases & keywords for generating alt text
                            <p class="alt-magic-setting-sub-label">Supported plugins: Yoast SEO, AIOSEO, Squirrly SEO, SEOPress & Rank Math</p>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Post Context</div>
                            <div class="setting-description">Use post information for better alt text</div>
                        </th>
                        <td>
                            <input type="checkbox" name="alt_magic_use_post_title" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_use_post_title'])); ?>> Use post title for generating alt text
                            <p class="alt-magic-setting-sub-label">Latest post/page title where the image is used will be used for the context of the image alt text.</p>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Update Strategy</div>
                            <div class="setting-description">Choose when to update existing alt texts</div>
                        </th>
                        <td>
                            <select name="alt_magic_refresh_alt_text" class="alt-magic-setting">
                                <option value="empty" <?php selected($options['alt_magic_refresh_alt_text'], 'empty'); ?>>Only in the posts where the image alt text is empty</option>
                                <option value="all" <?php selected($options['alt_magic_refresh_alt_text'], 'all'); ?>>For all posts, even if the image alt text is already filled.</option>
                            </select>
                        </td>
                    </tr>
                    
                    <?php if(is_plugin_active('woocommerce/woocommerce.php')): ?>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">WooCommerce Settings</div>
                            <div class="setting-description">Product-specific alt text generation</div>
                        </th>
                        <td>
                            <input type="checkbox" name="alt_magic_woocommerce_use_product_name" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_woocommerce_use_product_name'])); ?>> Use product name for generating alt text
                            <p class="alt-magic-setting-sub-label">Note: Product name will be used for the context of the image alt text.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Performance Settings</div>
                            <div class="setting-description">Balance speed vs server load</div>
                        </th>
                        <td>
                            <select name="alt_magic_max_concurrency" class="alt-magic-setting">
                                <option value="1" <?php selected($options['alt_magic_max_concurrency'], 1); ?>>1 - Slow (Low server load)</option>
                                <option value="5" <?php selected($options['alt_magic_max_concurrency'], 5); ?>>5 - Balanced (Recommended)</option>
                                <option value="10" <?php selected($options['alt_magic_max_concurrency'], 10); ?>>10 - Fast (Increased server load)</option>
                            </select>
                            <p class="alt-magic-setting-sub-label">Choose processing speed vs server load balance. Higher values process images faster but will increase server load during bulk processing.</p>
                        </td>
                    </tr>
                    <tr class="setting-row">
                        <th scope="row">
                            <div class="setting-title">Site Visibility</div>
                            <div class="setting-description">Configure for private/public sites</div>
                        </th>
                        <td>
                            <input type="checkbox" name="alt_magic_private_site" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_private_site'])); ?>> My site is not reachable on the internet or uses firewall
                            <p class="alt-magic-setting-sub-label">Note: If your site is unreachable on the internet or uses a firewall, please enable this option. (e.g. localhost, 127.0.0.1, mysite.local etc)</p>
                        </td>
                    </tr>
                    </tbody>
                    <tbody id="altm-tab-rename" class="altm-tab-content" style="display: none;">
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">Image Renaming</div>
                                <div class="setting-description">Automatically rename uploaded images</div>
                            </th>
                            <td>
                                <input type="checkbox" name="alt_magic_auto_rename_on_upload" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_auto_rename_on_upload'])); ?>> Enable automatic image renaming
                                <p class="alt-magic-setting-sub-label">When enabled, uploaded images will be automatically renamed using AI-generated descriptive names.</p>
                            </td>
                        </tr>
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">Image Name Language</div>
                                <div class="setting-description">Select the language for generated image names</div>
                            </th>
                            <td>
                                <select name="alt_magic_rename_language" class="alt-magic-setting">
                                    <?php 
                                    if (isset($altm_supported_languages) && is_array($altm_supported_languages)) {
                                        foreach ($altm_supported_languages as $code => $language): 
                                    ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($options['alt_magic_rename_language'], $code); ?>><?php echo esc_html($language); ?></option>
                                    <?php 
                                        endforeach;
                                    } else {
                                        echo '<option value="">No languages available</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">SEO Integration</div>
                                <div class="setting-description">Use SEO keywords for better image name</div>
                            </th>
                            <td>
                                <input type="checkbox" name="alt_magic_rename_use_seo_keywords" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_rename_use_seo_keywords'])); ?>> Use SEO focus keyphrases & keywords for generating image name
                                <p class="alt-magic-setting-sub-label">Supported plugins: Yoast SEO, AIOSEO, Squirrly SEO, SEOPress & Rank Math</p>
                            </td>
                        </tr>
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">Post Context</div>
                                <div class="setting-description">Use post information for better image name</div>
                            </th>   
                            <td>
                                <input type="checkbox" name="alt_magic_rename_use_post_title" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_rename_use_post_title'])); ?>> Use post title for generating image name
                                <p class="alt-magic-setting-sub-label">Latest post/page title where the image is used will be used for the context of the image name.</p>
                            </td>
                        </tr>
                        <?php if(is_plugin_active('woocommerce/woocommerce.php')): ?>
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">WooCommerce Settings</div>
                                <div class="setting-description">Product-specific image renaming</div>
                            </th>
                            <td>
                                <input type="checkbox" name="alt_magic_rename_use_woocommerce_product_name" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_rename_use_woocommerce_product_name'])); ?>> Use product name for generating image filename
                                <p class="alt-magic-setting-sub-label">Note: Product name will be used as context for generating a better image filename.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <!-- <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">URL Redirections</div>
                                <div class="setting-description">Automatically create redirections when renaming images</div>
                            </th>
                            <td>
                                <input type="checkbox" name="alt_magic_enable_redirections" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_enable_redirections'])); ?>> Create redirections for renamed images
                                <p class="alt-magic-setting-sub-label">When enabled, 301 redirections will be created from old image URLs to new ones after renaming. Requires the <a href="https://wordpress.org/plugins/redirection/" target="_blank">Redirection plugin</a> to be installed and active.</p>
                            </td>
                        </tr> -->
                        <tr class="setting-row">
                            <th scope="row">
                                <div class="setting-title">Advanced URL Updates</div>
                                <div class="setting-description">Control where image URL updates are applied after renaming</div>
                            </th>
                            <td>
                                <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_update_posts" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_update_posts'])); ?>> Update URLs in post content</p>
                                <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_update_excerpts" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_update_excerpts'])); ?>> Update URLs in post excerpts</p>
                                <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_update_postmeta" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_update_postmeta'])); ?>> Update URLs in post meta</p>
                                <!-- <p class="alt-magic-setting-label"><input type="checkbox" name="alt_magic_update_guid" class="alt-magic-setting" <?php checked(!empty($options['alt_magic_update_guid'])); ?>> Update attachment GUID <span style="color:#a00;">(Not recommended)</span></p>
                                <p class="alt-magic-setting-sub-label">Most sites should keep post content and post meta updates enabled. Excerpts are optional. Updating the GUID can affect external references and is generally discouraged.</p> -->
                            </td>
                        </tr>
                        
                    </tbody>
                </table>
            </form>
            <div id="alt-magic-settings-message"></div>
        </div>
    </div>


    <?php
}

/**
 * Sync WordPress settings with Alt Magic API
 * 
 * @return bool|WP_Error Returns true on success, WP_Error on failure
 */
function alt_magic_sync_settings_with_api() {
    $url = ALT_MAGIC_API_BASE_URL . '/save-wp-settings';
    
    // Get all settings
    $wp_settings = array(
        'alt_magic_auto_generate' => get_option('alt_magic_auto_generate', 0),
        'alt_magic_auto_rename_on_upload' => get_option('alt_magic_auto_rename_on_upload', 0),
        'alt_magic_alt_gen_type' => get_option('alt_magic_alt_gen_type', 'default'),
        'alt_magic_language' => get_option('alt_magic_language', 'en'),
        'alt_magic_use_for_title' => get_option('alt_magic_use_for_title', 0),
        'alt_magic_use_for_caption' => get_option('alt_magic_use_for_caption', 0),
        'alt_magic_use_for_description' => get_option('alt_magic_use_for_description', 0),
        'alt_magic_prepend_string' => get_option('alt_magic_prepend_string', ''),
        'alt_magic_append_string' => get_option('alt_magic_append_string', ''),
        'alt_magic_extra_prompt' => get_option('alt_magic_extra_prompt', ''),
        'alt_magic_use_seo_keywords' => get_option('alt_magic_use_seo_keywords', 0),
        'alt_magic_use_post_title' => get_option('alt_magic_use_post_title', 0),
        'alt_magic_refresh_alt_text' => get_option('alt_magic_refresh_alt_text', 'all'),
        'alt_magic_private_site' => get_option('alt_magic_private_site', 0),
        'alt_magic_woocommerce_use_product_name' => get_option('alt_magic_woocommerce_use_product_name', 0),
        'alt_magic_max_concurrency' => altm_get_valid_concurrency_value(),
        // Rename context options
        'alt_magic_rename_use_seo_keywords' => get_option('alt_magic_rename_use_seo_keywords', 0),
        'alt_magic_rename_use_post_title' => get_option('alt_magic_rename_use_post_title', 0),
        'alt_magic_rename_use_woocommerce_product_name' => get_option('alt_magic_rename_use_woocommerce_product_name', 0),
        // Advanced URL Update Settings
        'alt_magic_update_posts' => get_option('alt_magic_update_posts', 1),
        'alt_magic_update_excerpts' => get_option('alt_magic_update_excerpts', 0),
        'alt_magic_update_postmeta' => get_option('alt_magic_update_postmeta', 1),
        'alt_magic_update_guid' => get_option('alt_magic_update_guid', 0),
        'alt_magic_rename_language' => get_option('alt_magic_rename_language', 'en')
    );

    // Prepare the data to send
    $data = array(
        'user_id' => get_option('alt_magic_user_id'),
        'domain' => get_site_url(),
        'api_key' => get_option('alt_magic_api_key'),
        'wp_settings' => $wp_settings
    );

    // Send the request
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . get_option('alt_magic_api_key')
        ),
        'body' => json_encode($data)
    ));

    if (is_wp_error($response)) {
        altm_log('Alt Magic: Failed to sync settings with API - ' . $response->get_error_message());
        return $response;
    }

    return true;
}

/**
 * Sanitize option value based on its type
 * 
 * @param string $key The option key
 * @param mixed $value The value to sanitize
 * @return mixed The sanitized value
 */
function alt_magic_sanitize_option_value($key, $value) {
    // Define option types for proper sanitization
    $option_types = [
        'alt_magic_account_active' => 'boolean',
        'alt_magic_api_key' => 'string',
        'alt_magic_user_id' => 'string',
        'alt_magic_language' => 'string',
        'alt_magic_alt_gen_type' => 'string',
        'alt_magic_use_for_title' => 'boolean',
        'alt_magic_use_for_caption' => 'boolean',
        'alt_magic_use_for_description' => 'boolean',
        'alt_magic_prepend_string' => 'string',
        'alt_magic_append_string' => 'string',
        'alt_magic_auto_generate' => 'boolean',
        'alt_magic_auto_rename_on_upload' => 'boolean',
        'alt_magic_use_seo_keywords' => 'boolean',
        'alt_magic_use_post_title' => 'boolean',
        'alt_magic_refresh_alt_text' => 'string',
        'alt_magic_private_site' => 'boolean',
        'alt_magic_woocommerce_use_product_name' => 'boolean',
        'alt_magic_rename_use_seo_keywords' => 'boolean',
        'alt_magic_rename_use_post_title' => 'boolean',
        'alt_magic_rename_use_woocommerce_product_name' => 'boolean',
        'alt_magic_max_concurrency' => 'integer',
        'alt_magic_enable_redirections' => 'boolean',
        'altm_debug_mode' => 'boolean',
        'alt_magic_extra_prompt' => 'textarea',
        'alt_magic_rename_language' => 'string',
        'alt_magic_update_posts' => 'boolean',
        'alt_magic_update_excerpts' => 'boolean',
        'alt_magic_update_postmeta' => 'boolean',
        'alt_magic_update_guid' => 'boolean'
    ];
    
    $type = isset($option_types[$key]) ? $option_types[$key] : 'string';
    
    // Sanitize based on type
    switch ($type) {
        case 'boolean':
            return absint($value) ? 1 : 0;
            
        case 'integer':
            return absint($value);
            
        case 'textarea':
            return sanitize_textarea_field($value);
            
        case 'string':
        default:
            return sanitize_text_field($value);
    }
}

// Add this function to handle the AJAX request
function alt_magic_save_settings() {
    // Check if nonce is provided
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Security token missing. Please refresh the page and try again.');
        return;
    }
    
    // Check nonce for security
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'alt_magic_save_settings')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }

    // Check if the POST variables are set
    if (!isset($_POST['key']) || !isset($_POST['value'])) {
        wp_send_json_error('Invalid request. Missing required parameters.');
        return;
    }

    // Unslash and sanitize the POST variables
    $key = sanitize_text_field(wp_unslash($_POST['key']));
    $value = sanitize_text_field(wp_unslash($_POST['value']));

    // Define whitelist of allowed option keys to prevent arbitrary option updates
    $allowed_option_keys = [
        'alt_magic_account_active',
        'alt_magic_api_key',
        'alt_magic_user_id',
        'alt_magic_language',
        'alt_magic_alt_gen_type',
        'alt_magic_use_for_title',
        'alt_magic_use_for_caption',
        'alt_magic_use_for_description',
        'alt_magic_prepend_string',
        'alt_magic_append_string',
        'alt_magic_auto_generate',
        'alt_magic_auto_rename_on_upload',
        'alt_magic_use_seo_keywords',
        'alt_magic_use_post_title',
        'alt_magic_refresh_alt_text',
        'alt_magic_private_site',
        'alt_magic_woocommerce_use_product_name',
        'alt_magic_rename_use_seo_keywords',
        'alt_magic_rename_use_post_title',
        'alt_magic_rename_use_woocommerce_product_name',
        'alt_magic_max_concurrency',
        'alt_magic_enable_redirections',
        'altm_debug_mode',
        'alt_magic_extra_prompt',
        'alt_magic_rename_language',
        'alt_magic_update_posts',
        'alt_magic_update_excerpts',
        'alt_magic_update_postmeta',
        'alt_magic_update_guid'
    ];

    // Validate the key is in the whitelist
    if (empty($key) || !in_array($key, $allowed_option_keys, true)) {
        wp_send_json_error('Invalid setting key provided.');
        return;
    }

    // Sanitize value based on the option type
    $value = alt_magic_sanitize_option_value($key, $value);

    // Update the individual option
    $updated = update_option($key, $value);
    if ($updated === false) {
        wp_send_json_error('Failed to update setting. The value may be the same as the current value.');
        return;
    }

    // Try to sync with API, but don't let it affect the success of the local save
    $sync_result = alt_magic_sync_settings_with_api();
    if (is_wp_error($sync_result)) {
        // Log the error but still return success for the local save
        altm_log('Alt Magic: Failed to sync settings with API - ' . $sync_result->get_error_message());
        wp_send_json_success('Setting updated successfully');
        return;
    }

    wp_send_json_success('Setting updated successfully');
}

add_action('wp_ajax_alt_magic_save_settings', 'alt_magic_save_settings');
add_action('wp_ajax_nopriv_alt_magic_save_settings', 'alt_magic_save_settings');

/**
 * Check if images on this site are accessible from the internet
 * 
 * @return void Sends JSON response
 */
function alt_magic_check_image_accessibility() {
    try {
        // Check if nonce is provided
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(['message' => 'Security token missing. Please refresh the page and try again.']);
            return;
        }
        
        // Check nonce for security
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'alt_magic_save_settings')) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
            return;
        }

        // Verify ALT_MAGIC_API_BASE_URL is defined
        if (!defined('ALT_MAGIC_API_BASE_URL') || empty(ALT_MAGIC_API_BASE_URL)) {
            altm_log('Alt Magic: ALT_MAGIC_API_BASE_URL is not defined');
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        // Get a random image from the media library to test
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'orderby' => 'rand'
        );
        
        $images = get_posts($args);
        
        if (empty($images)) {
            // No images found - we'll use the site URL as a fallback test
            $site_url = get_site_url();
            if (!$site_url) {
                wp_send_json_error([
                    'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                    'accessible' => false
                ]);
                return;
            }
            $test_url = $site_url . '/wp-includes/images/media/default.png';
        } else {
            $test_url = wp_get_attachment_url($images[0]->ID);
        }
        
        if (!$test_url || !filter_var($test_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        // Call the Alt Magic API to check if the image is accessible
        $api_url = ALT_MAGIC_API_BASE_URL . '/check-image-accessibility';
        
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'image_url' => $test_url
            )),
            'timeout' => 15,
            'sslverify' => true,
            'blocking' => true
        ));

        if (is_wp_error($response)) {
            altm_log('Alt Magic: Image accessibility check failed - ' . $response->get_error_message());
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (!$response_code || $response_code !== 200) {
            altm_log('Alt Magic: Image accessibility check returned non-200 status - ' . $response_code);
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        if (empty($response_body)) {
            altm_log('Alt Magic: Image accessibility check returned empty response body');
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            altm_log('Alt Magic: Image accessibility check returned invalid JSON - ' . json_last_error_msg());
            wp_send_json_error([
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'accessible' => false
            ]);
            return;
        }

        // Check if the image is accessible
        $is_accessible = isset($data['accessible']) ? (bool) $data['accessible'] : false;
        
        if ($is_accessible) {
            wp_send_json_success([
                'accessible' => true,
                'message' => 'Your images are accessible from the internet.',
                'test_url' => $test_url
            ]);
        } else {
            // Build error message from API response
            // API returns: { "accessible": false, "error": "...", "details": "..." }
            $error_parts = array();
            
            if (isset($data['error']) && !empty($data['error'])) {
                $error_parts[] = sanitize_text_field($data['error']);
            }
            
            if (isset($data['details']) && !empty($data['details'])) {
                $error_parts[] = sanitize_text_field($data['details']);
            }
            
            // Fallback to 'message' field if it exists (for backward compatibility)
            if (empty($error_parts) && isset($data['message']) && !empty($data['message'])) {
                $error_parts[] = sanitize_text_field($data['message']);
            }
            
            // If no error details from API, use default message
            if (empty($error_parts)) {
                $error_message = 'Image not accessible to our servers.';
            } else {
                $error_message = implode(': ', $error_parts);
            }
            
            // Log the full API response for debugging
            altm_log('Alt Magic: Image accessibility check - Image not accessible. API response: ' . wp_json_encode($data));
            
            wp_send_json_error([
                'accessible' => false,
                'message' => 'Image not accessible to our servers. Please keep this option enabled.',
                'test_url' => $test_url
            ]);
        }
    } catch (Exception $e) {
        // Catch any unexpected errors and log them
        altm_log('Alt Magic: Image accessibility check caught exception - ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'Image not accessible to our servers. Please keep this option enabled.',
            'accessible' => false
        ]);
    } catch (Error $e) {
        // Catch PHP 7+ errors
        altm_log('Alt Magic: Image accessibility check caught error - ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'Image not accessible to our servers. Please keep this option enabled.',
            'accessible' => false
        ]);
    }
}

add_action('wp_ajax_alt_magic_check_image_accessibility', 'alt_magic_check_image_accessibility');