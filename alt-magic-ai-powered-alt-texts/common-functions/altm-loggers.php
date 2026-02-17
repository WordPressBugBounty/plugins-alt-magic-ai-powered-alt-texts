<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Custom logging function
function altm_log($message) {
    $debug_mode = get_option('altm_debug_mode');
    //$debug_mode = '1';
    if ($debug_mode) {
        $log_file_path = ABSPATH . 'wp-content/altm_debug.log'; // Custom log file path
        $log_message = gmdate('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
        file_put_contents($log_file_path, $log_message, FILE_APPEND);
    }
}