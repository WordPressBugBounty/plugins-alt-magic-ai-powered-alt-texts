<?php
/*
Plugin Name: Alt Magic: AI Powered Alt Texts & Image Renaming
Plugin URI: https://altmagic.pro/
Description: Automatically generate SEO-optimized alt texts and rename images using AI. Improve accessibility, rankings, and WooCommerce product image visibility with one powerful plugin.
Version: 1.6.2
Author: Alt Magic
Author URI: https://altmagic.pro/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
*/

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Define a base URL for API calls
define('ALT_MAGIC_API_BASE_URL', 'https://alt-magic-api-eabaa2c8506a.herokuapp.com');
//define('ALT_MAGIC_API_BASE_URL', 'http://192.168.1.16:3000');

// Define plugin version constant
define('ALT_MAGIC_PLUGIN_VERSION', '1.6.2');

require_once plugin_dir_path( __FILE__ ) . '/admin-functions/altm-initialize-all-settings-values.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-functions/altm-supported-languages.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-functions/altm-plugin-activation-flow.php';   


require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-admin-menu-generator.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-account-settings-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-ai-settings-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-bulk-generation-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-help-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-image-processing-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-image-renaming-page.php';
require_once plugin_dir_path( __FILE__ ) . '/admin-settings-pages/altm-processed-images-page.php';


// Plugin event tracking - sends pings to server for activation, deactivation, installation, and deletion events
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-plugin-events-tracker.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-deactivation-survey.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-alt-text-generator-ajax.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-alt-text-generator.php';
require_once plugin_dir_path( __FILE__ ) . '/media-library-page-functions/altm-media-library-button.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-update-post-metadata.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-image-data-functions.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-image-renaming-handler.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-redirection-handler.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-bulk-image-alt-handler.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-loggers.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-processed-images-functions.php';
require_once plugin_dir_path( __FILE__ ) . '/common-functions/altm-upload-handler.php';

require_once plugin_dir_path( __FILE__ ) . '/integrations-functions/altm-fetch-yoast-keywords.php';