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
        add_action('wp_ajax_altm_deactivation_retention_click', array($this, 'handle_retention_click'));
        add_action('wp_ajax_altm_set_deactivation_reason', array($this, 'handle_set_deactivation_reason'));
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
            'plugin_file' => plugin_basename(dirname(dirname(__FILE__)) . '/altm-main-file.php'),
            'account_settings_url' => admin_url('admin.php?page=alt-magic'),
            'one_time_deal_url' => 'https://altmagic.pro/lifetime-deal#pricing'
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
        $retention_action = isset($_POST['retention_action']) ? sanitize_text_field(wp_unslash($_POST['retention_action'])) : '';

        $should_store_reason_context = empty($retention_action) || $retention_action === 'continue_deactivation';

        if ($should_store_reason_context && !empty($reason) && function_exists('altm_set_deactivation_reason_context')) {
            altm_set_deactivation_reason_context($reason);
        }
        
        // Send survey data to server
        $this->send_survey_data($reason, $details, $retention_action);
        
        wp_send_json_success(array('message' => 'Survey submitted successfully'));
    }

    /**
     * Track retention CTA clicks without submitting a deactivation survey event.
     */
    public function handle_retention_click() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_deactivation_survey_nonce')) {
            wp_die('Security check failed');
        }

        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
        $retention_action = isset($_POST['retention_action']) ? sanitize_text_field(wp_unslash($_POST['retention_action'])) : '';
        $details = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';

        $this->send_retention_click_data($reason, $retention_action, $details);

        wp_send_json_success(array('message' => 'Retention click tracked successfully'));
    }

    /**
     * Store the deactivation reason for flows that skip the survey payload.
     */
    public function handle_set_deactivation_reason() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'altm_deactivation_survey_nonce')) {
            wp_die('Security check failed');
        }

        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        if (!empty($reason) && function_exists('altm_set_deactivation_reason_context')) {
            altm_set_deactivation_reason_context($reason);
        }

        wp_send_json_success(array('message' => 'Deactivation reason stored successfully'));
    }
    
    /**
     * Send survey data to server
     */
    private function send_survey_data($reason, $details, $retention_action = '') {
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

        if (!empty($retention_action)) {
            $request_data['survey_data']['retention_action'] = $retention_action;
        }
        
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
     * Send retention-click data to the server without marking it as a deactivation survey.
     */
    private function send_retention_click_data($reason, $retention_action, $details = '') {
        $site_url = get_site_url();
        $user_id = get_option('alt_magic_user_id');
        $user_email = altm_get_current_user_email();
        $event_type = 'deactivation_retention_click';

        if ($retention_action === 'keep_free_plan') {
            $event_type = 'deactivation_retention_click_free';
        } elseif ($retention_action === 'switch_to_one_time_pricing') {
            $event_type = 'deactivation_retention_click_lifetime';
        }

        $request_data = array(
            'event_type' => $event_type,
            'plugin_version' => ALT_MAGIC_PLUGIN_VERSION,
            'site_url' => $site_url,
            'retention_data' => array(
                'reason' => $reason,
                'retention_action' => $retention_action,
                'details' => $details,
                'timestamp' => current_time('mysql')
            )
        );

        if (!empty($user_id)) {
            $request_data['user_id'] = $user_id;
        }

        if (!empty($user_email)) {
            $request_data['wp_login_email'] = $user_email;
        }

        $base_url = ALT_MAGIC_API_BASE_URL;
        $ping_url = $base_url . ALT_MAGIC_PLUGIN_EVENTS_ENDPOINT;

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Alt-Magic-WordPress-Plugin/' . ALT_MAGIC_PLUGIN_VERSION
            ),
            'body' => wp_json_encode($request_data),
            'timeout' => 30,
            'blocking' => false,
            'httpversion' => '1.1',
            'sslverify' => true
        );

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
            'other' => 'Other reason'
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
                <form id="altm-deactivation-survey-form">
                    <div id="altm-survey-step" class="altm-modal-step">
                        <div class="altm-modal-header">
                            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/main-logo-big.svg'); ?>" alt="Alt Magic" class="altm-logo">
                            <h2>Quick feedback</h2>
                            <p>Before you deactivate Alt Magic, could you tell us why?</p>
                        </div>

                        <div class="altm-modal-body">
                            <div class="altm-reasons-list" role="radiogroup" aria-label="Deactivation reasons">
                                <?php foreach ($reasons as $value => $label): ?>
                                    <label class="altm-reason-option">
                                        <input type="radio" name="reason" value="<?php echo esc_attr($value); ?>" <?php echo ($value === 'other' || $value === 'missing_features') ? 'data-requires-details="true"' : ''; ?>>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div id="altm-details-section" style="display: none;">
                                <label for="altm-details">Please share a bit more</label>
                                <textarea id="altm-details" name="details" rows="4" placeholder="Your feedback helps us improve Alt Magic."></textarea>
                            </div>
                        </div>

                        <div class="altm-modal-footer altm-modal-footer-split">
                            <a href="#" id="altm-skip-and-deactivate" class="altm-skip-link">Skip and deactivate</a>
                            <button type="button" id="altm-continue-button" class="button button-primary" disabled>Continue</button>
                        </div>
                    </div>

                    <div id="altm-pricing-step" class="altm-modal-step" style="display: none;">
                        <div class="altm-modal-header altm-modal-header-left">
                            <div class="altm-pricing-header-title">
                                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/main-logo-big.svg'); ?>" alt="Alt Magic" class="altm-logo altm-pricing-logo">
                                <h2>Pricing is a concern? Here are lower-cost ways to keep using Alt Magic.</h2>
                            </div>
                        </div>

                        <div class="altm-modal-body altm-pricing-body">
                            <div class="altm-pricing-grid">
                                <section class="altm-pricing-card altm-pricing-card-free">
                                    <span class="altm-pricing-badge">Best for light usage</span>
                                    <h3>Stay on Free</h3>
                                    <p class="altm-pricing-price">50 free credits monthly</p>
                                    <ul class="altm-pricing-points">
                                        <li>Refreshes credits monthly</li>
                                        <li>Auto-generate alt text and image names at no cost monthly</li>
                                    </ul>
                                    <button type="button" id="altm-keep-free-plan" class="button altm-option-button">Keep Free Plan</button>
                                </section>

                                <section class="altm-pricing-card altm-pricing-card-featured">
                                    <span class="altm-pricing-badge altm-pricing-badge-featured">Best for occasional bulk usage</span>
                                    <h3>Get lifetime deal</h3>
                                    <p class="altm-pricing-price">Pay $49 once</p>
                                    <ul class="altm-pricing-points">
                                        <li>Pay once; get monthly credits for lifetime</li>
                                        <li>Ideal for seasonal image volume</li>
                                    </ul>
                                    <button type="button" id="altm-get-one-time-deal" class="button button-primary altm-option-button">Check Lifetime Deal</button>
                                </section>
                            </div>

                        </div>

                        <div class="altm-modal-footer altm-modal-footer-stack altm-modal-footer-secondary">
                            <a href="#" id="altm-continue-deactivation" class="altm-skip-link">Continue deactivation</a>
                            <p class="altm-footer-note">You can deactivate anytime if these options are not the right fit.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="altm-modal-overlay" class="altm-modal-overlay" style="display: none;"></div>
        <?php
    }
}

// Initialize the deactivation survey
new AltMagic_Deactivation_Survey();

?>
