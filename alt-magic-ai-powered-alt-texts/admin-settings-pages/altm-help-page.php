<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function alt_magic_render_help_page() {
    
    $current_debug_mode = get_option('altm_debug_mode', 0);

    // Path to the error log file
    $log_file_path = ABSPATH . 'wp-content/altm_debug.log'; // Adjust the path as needed

    // Check if the log file exists and is readable
    $log_file_size = 0;
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        $logs = file_get_contents($log_file_path);
        $log_file_size = filesize($log_file_path);
        
        // Filter logs to include only those starting with ""
        $filtered_logs = '';
        $log_lines = explode("\n", $logs);
        foreach ($log_lines as $line) {
            if (strpos($line, '') !== false) { // Check if the line contains ""
                $filtered_logs .= $line . "\n";
            }
        }
    } else {
        $filtered_logs = 'Error log file not found or not readable.';
    }

    // Add a download link for the log file
    $download_url = admin_url('admin-post.php?action=download_altm_log');

    ?>
    <div class="wrap">
        <h1>Alt Magic Help (v<?php echo esc_html(ALT_MAGIC_PLUGIN_VERSION); ?>)</h1>
        
        
        <div class="chat-support" style="width: 600px; border: 1px solid #ccc; padding: 10px; margin-top: 20px;">
            <h2 style="margin: 0px;">Contact Chat Support</h2>
            <p style="margin-top: 6px; margin-bottom: 24px; color: #666;">To contact chat support, visit your account on <a href="https://app.altmagic.pro" target="_blank">app.altmagic.pro</a> and open the chat support.</p>
            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/alt-magic-chat-support.webp'); ?>" alt="Alt Magic Chat Support" style="max-width: 100%; height: auto;" />
        </div>
    </div>

    <div style="width: 600px; border: 1px solid #ccc; padding: 10px; margin-top: 20px; display: flex; flex-direction: column;">
        <h2 style="margin: 0px;">Alt Magic Debug Logs</h2>
        <p style="margin-top: 6px; margin-bottom: 14px; color: #666;">Download the logs if asked by chat support.</p>
        <div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
            <a style="width: max-content;" href="<?php echo esc_url($download_url); ?>" class="button">Download Logs</a>
            <button type="button" id="altm-clear-logs" class="button" style="background-color: #dc3232; color: white; border-color: #dc3232;">Clear Logs</button>
        </div>
        <p style="margin: 0 0 10px 0; color: #666; font-size: 12px;">
            Log file size: <span id="altm-log-size"><?php echo esc_html(size_format($log_file_size)); ?></span>
            <a href="#" id="altm-refresh-logs" style="margin-left: 10px; color: #0073aa; text-decoration: none; cursor: pointer;" title="Refresh log content and size">Refresh</a>
        </p>
        <form id="altm-debug-form" >
            <?php wp_nonce_field('altm_help_page_action', 'altm_help_page_nonce'); ?>
            <label for="altm_debug_mode">
                <input type="checkbox" name="altm_debug_mode" value="1" <?php checked($current_debug_mode, true); ?> />
                Enable debug mode (not recommended for general use)
            </label>
        </form>
        <textarea id="altm-logs" readonly style="background-color: #212121; color: #cdcdcd; font-family: monospace; font-size: 11px; margin-top: 10px; width: 100%; height: 200px; display: <?php echo esc_attr($current_debug_mode ? 'block' : 'none'); ?>;"><?php echo esc_textarea($filtered_logs); ?></textarea>
    </div>
    <script>
        // Localize ajaxurl for AJAX requests
        var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle logs visibility and save setting
            document.querySelector('input[name="altm_debug_mode"]').addEventListener('change', function() {
                const isChecked = this.checked;
                document.getElementById('altm-logs').style.display = isChecked ? 'block' : 'none';
                
                // Save the debug mode setting
                const nonce = document.querySelector('input[name="altm_help_page_nonce"]').value;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=altm_save_debug_mode&nonce=' + nonce + '&debug_mode=' + (isChecked ? 1 : 0)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Failed to save debug mode setting');
                    }
                })
                .catch(error => {
                    console.error('Error saving debug mode setting:', error);
                });
            });
            
            // Refresh logs link functionality
            document.getElementById('altm-refresh-logs').addEventListener('click', function(e) {
                e.preventDefault();
                const link = this;
                const originalText = link.textContent;
                link.textContent = 'Refreshing...';
                link.style.pointerEvents = 'none';
                
                // Get the nonce from the form
                const nonce = document.querySelector('input[name="altm_help_page_nonce"]').value;
                
                // Make AJAX request to refresh logs
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=altm_refresh_logs&nonce=' + nonce
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update log size
                        document.getElementById('altm-log-size').textContent = data.data.size;
                        
                        // Update log content
                        document.getElementById('altm-logs').value = data.data.logs;
                        
                        // Show success message briefly
                        link.textContent = 'Refreshed!';
                        setTimeout(() => {
                            link.textContent = originalText;
                        }, 1000);
                    } else {
                        alert('Error refreshing logs. Please try again.');
                        link.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error refreshing logs. Please try again.');
                    link.textContent = originalText;
                })
                .finally(() => {
                    link.style.pointerEvents = 'auto';
                });
            });
            
            // Clear logs button functionality
            document.getElementById('altm-clear-logs').addEventListener('click', function() {
                if (confirm('Are you sure you want to clear all debug logs? This action cannot be undone.')) {
                    const button = this;
                    const originalText = button.textContent;
                    button.textContent = 'Clearing...';
                    button.disabled = true;
                    
                    // Get the nonce from the form
                    const nonce = document.querySelector('input[name="altm_help_page_nonce"]').value;
                    
                    // Make AJAX request to clear logs
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=altm_clear_logs&nonce=' + nonce
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear the textarea
                            document.getElementById('altm-logs').value = '';
                            
                            // Update log size
                            updateLogSize();
                            
                            alert('Logs cleared successfully!');
                        } else {
                            alert('Error: ' + (data.data.message || 'Failed to clear logs'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error clearing logs. Please try again.');
                    })
                    .finally(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                    });
                }
            });
            
            // Function to update log size
            function updateLogSize() {
                const nonce = document.querySelector('input[name="altm_help_page_nonce"]').value;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=altm_get_log_size&nonce=' + nonce
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('altm-log-size').textContent = data.data.size;
                    }
                })
                .catch(error => {
                    console.error('Error updating log size:', error);
                });
            }
        });
    </script>
    <?php
}

// Add a handler for the log download
add_action('admin_post_download_altm_log', 'altm_download_log_file');

function altm_download_log_file() {
    $log_file_path = ABSPATH . 'wp-content/altm_debug.log'; // Adjust the path as needed
    $log_file_name = gmdate('Y-m-d_H-i-s') . '_altmagic_debug.log';

    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Read file contents using WP_Filesystem
        $file_contents = $wp_filesystem->get_contents($log_file_path);
        
        if ($file_contents !== false) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $log_file_name . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($file_contents));
            echo $file_contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw file contents for download
            exit;
        } else {
            wp_die('Error reading log file.');
        }
    } else {
        wp_die('Error log file not found or not readable.');
    }
}

// Add AJAX handlers for clearing logs
add_action('wp_ajax_altm_clear_logs', 'altm_clear_logs_ajax_handler');
add_action('wp_ajax_altm_get_log_size', 'altm_get_log_size_ajax_handler');
add_action('wp_ajax_altm_refresh_logs', 'altm_refresh_logs_ajax_handler');
add_action('wp_ajax_altm_save_debug_mode', 'altm_save_debug_mode_ajax_handler');

function altm_clear_logs_ajax_handler() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_help_page_action')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $log_file_path = ABSPATH . 'wp-content/altm_debug.log';
    
    // Clear the log file
    if (file_exists($log_file_path)) {
        if (file_put_contents($log_file_path, '') !== false) {
            wp_send_json_success(array('message' => 'Logs cleared successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to clear logs'));
        }
    } else {
        wp_send_json_error(array('message' => 'Log file not found'));
    }
}

function altm_get_log_size_ajax_handler() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_help_page_action')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $log_file_path = ABSPATH . 'wp-content/altm_debug.log';
    $log_file_size = 0;
    
    if (file_exists($log_file_path)) {
        $log_file_size = filesize($log_file_path);
    }
    
    wp_send_json_success(array('size' => size_format($log_file_size)));
}

function altm_refresh_logs_ajax_handler() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_help_page_action')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $log_file_path = ABSPATH . 'wp-content/altm_debug.log';
    $log_file_size = 0;
    $filtered_logs = '';
    
    // Check if the log file exists and is readable
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        $logs = file_get_contents($log_file_path);
        $log_file_size = filesize($log_file_path);
        
        // Filter logs to include only those starting with ""
        $log_lines = explode("\n", $logs);
        foreach ($log_lines as $line) {
            if (strpos($line, '') !== false) { // Check if the line contains ""
                $filtered_logs .= $line . "\n";
            }
        }
    } else {
        $filtered_logs = 'Error log file not found or not readable.';
    }
    
    wp_send_json_success(array(
        'size' => size_format($log_file_size),
        'logs' => $filtered_logs
    ));
}

function altm_save_debug_mode_ajax_handler() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_help_page_action')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $debug_mode = isset($_POST['debug_mode']) ? absint($_POST['debug_mode']) : 0;
    
    // Save the debug mode setting
    update_option('altm_debug_mode', $debug_mode);
    
    wp_send_json_success(array('message' => 'Debug mode setting saved'));
}
