<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alt Magic Deactivation Survey
 * Handles the deactivation questionnaire functionality
 */

class AltMagic_Deactivation_Survey {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_altm_deactivation_survey', array($this, 'handle_survey_submission'));
        add_action('admin_footer', array($this, 'render_survey_modal'));
    }
    
    /**
     * Enqueue scripts and styles for the deactivation survey
     */
    public function enqueue_scripts($hook) {
        // Only load on plugins page
        if ($hook !== 'plugins.php') {
            return;
        }
        
        wp_enqueue_script(
            'altm-deactivation-survey',
            plugin_dir_url(dirname(__FILE__)) . 'scripts/altm-deactivation-survey.js',
            array('jquery'),
            ALT_MAGIC_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'altm-deactivation-survey',
            plugin_dir_url(dirname(__FILE__)) . 'css/altm-deactivation-survey.css',
            array(),
            ALT_MAGIC_PLUGIN_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('altm-deactivation-survey', 'altm_deactivation_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('altm_deactivation_survey_nonce'),
            'plugin_file' => plugin_basename(dirname(dirname(__FILE__)) . '/altm-main-file.php')
        ));
    }
    
    /**
     * Handle AJAX survey submission
     */
    public function handle_survey_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_deactivation_survey_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get survey data
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
        $details = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
        
        // Send survey data to server
        $this->send_survey_data($reason, $details);
        
        wp_send_json_success(array('message' => 'Survey submitted successfully'));
    }
    
    /**
     * Send survey data to server
     */
    private function send_survey_data($reason, $details) {
        // Get site information
        $site_url = get_site_url();
        $user_id = get_option('alt_magic_user_id');
        
        // Get current user email (use the same function from plugin events tracker)
        $user_email = altm_get_current_user_email();
        
        // Prepare the request data
        $request_data = array(
            'event_type' => 'deactivation_survey',
            'plugin_version' => ALT_MAGIC_PLUGIN_VERSION,
            'site_url' => $site_url,
            'survey_data' => array(
                'reason' => $reason,
                'details' => $details,
                'timestamp' => current_time('mysql')
            )
        );
        
        // Add user_id if available
        if (!empty($user_id)) {
            $request_data['user_id'] = $user_id;
        }
        
        // Add user email if available
        if (!empty($user_email)) {
            $request_data['wp_login_email'] = $user_email;
        }
        
        // Get the base URL for the ping
        $base_url = ALT_MAGIC_API_BASE_URL;
        $ping_url = $base_url . ALT_MAGIC_PLUGIN_EVENTS_ENDPOINT;
        
        // Prepare the request arguments
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Alt-Magic-WordPress-Plugin/' . ALT_MAGIC_PLUGIN_VERSION
            ),
            'body' => wp_json_encode($request_data),
            'timeout' => 30,
            'blocking' => false, // Non-blocking request
            'httpversion' => '1.1',
            'sslverify' => true
        );
        
        // Send the ping
        wp_remote_post($ping_url, $args);
    }
    
    /**
     * Get survey questions/reasons
     */
    public function get_survey_reasons() {
        return array(
            'temporary_deactivation' => 'Temporary deactivation',
            'cost' => 'Pricing concerns',
            'no_longer_needed' => 'No longer needed on this site',
            'missing_features' => 'Missing features',
            'other' => 'Other (please specify)'
        );
    }
    
    /**
     * Render the deactivation survey modal
     */
    public function render_survey_modal() {
        // Only show on plugins page
        global $pagenow;
        if ($pagenow !== 'plugins.php') {
            return;
        }
        
        $reasons = $this->get_survey_reasons();
        ?>
        <div id="altm-deactivation-survey-modal" class="altm-modal" style="display: none;">
            <div class="altm-modal-content">
                <div class="altm-modal-header">
                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/main-logo-big.svg'); ?>" alt="Alt Magic" class="altm-logo">
                    <h2>Quick Feedback</h2>
                    <p>We're sorry to see you go! Could you please tell us why you're deactivating Alt Magic?</p>
                </div>
                
                <div class="altm-modal-body">
                    <form id="altm-deactivation-survey-form">
                        <div class="altm-reasons-list">
                            <?php foreach ($reasons as $value => $label): ?>
                                <label class="altm-reason-option">
                                    <input type="radio" name="reason" value="<?php echo esc_attr($value); ?>" <?php echo ($value === 'other' || $value === 'missing_features') ? 'data-requires-details="true"' : ''; ?>>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="altm-ltd-offer" style="display: none;">
                            <div class="altm-ltd-card">
                                <div class="altm-ltd-badge">Special Offer</div>
                                <h3 class="altm-ltd-title">Lifetime Deal</h3>
                                <p class="altm-ltd-subtitle">Pay $49 once and get image credits for lifetime.</p>
                                <a href="https://altmagic.pro/lifetime-deal#pricing" target="_blank" rel="noopener noreferrer" class="button button-primary altm-ltd-cta">Get Lifetime Deal</a>
                            </div>
                        </div>
                        
                        <div id="altm-details-section" style="display: none;">
                            <label for="altm-details">Please provide more details:</label>
                            <textarea id="altm-details" name="details" rows="4" placeholder="Your feedback helps us improve the plugin..."></textarea>
                        </div>
                    </form>
                </div>
                
                <div class="altm-modal-footer">
                    <div class="altm-footer-left">
                        <a href="#" id="altm-skip-and-deactivate" class="altm-skip-link">Skip & Deactivate</a>
                    </div>
                    <div class="altm-footer-right">
                        <button type="button" id="altm-cancel-deactivation" class="button altm-cancel-btn">Cancel</button>
                        <button type="button" id="altm-submit-and-deactivate" class="button button-primary" disabled>Submit & Deactivate</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="altm-modal-overlay" class="altm-modal-overlay" style="display: none;"></div>
        <?php
    }
}

// Initialize the deactivation survey
new AltMagic_Deactivation_Survey();

?>
