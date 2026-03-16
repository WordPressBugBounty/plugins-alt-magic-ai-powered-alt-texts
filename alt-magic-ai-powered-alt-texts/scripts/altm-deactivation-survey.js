/**
 * Alt Magic Deactivation Survey JavaScript
 * Handles the deactivation questionnaire functionality
 */

(function ($) {
    'use strict';

    var AltMagicDeactivationSurvey = {

        // Plugin deactivation link
        deactivationLink: null,

        // Modal elements
        modal: null,
        overlay: null,
        form: null,

        // Form elements
        reasonInputs: null,
        surveyStep: null,
        pricingStep: null,
        detailsSection: null,
        detailsTextarea: null,
        continueButton: null,
        skipButton: null,
        continueDeactivationButton: null,
        keepFreePlanButton: null,
        oneTimeDealButton: null,
        currentStep: 'survey',

        /**
         * Initialize the deactivation survey
         */
        init: function () {
            this.bindEvents();
            this.setupModal();
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Find the deactivation link for Alt Magic plugin
            $(document).ready(function () {
                self.findDeactivationLink();
            });

            // Handle deactivation link click
            $(document).on('click', 'tr[data-slug*="alt-magic"] .deactivate a', function (e) {
                e.preventDefault();
                self.deactivationLink = $(this);
                self.showModal();
            });
        },

        /**
         * Find the deactivation link for the plugin
         */
        findDeactivationLink: function () {
            // Look for the plugin row by plugin file name
            var pluginFile = altm_deactivation_ajax.plugin_file;
            var pluginRow = $('tr[data-plugin="' + pluginFile + '"]');
            var self = this;

            if (pluginRow.length) {
                this.deactivationLink = pluginRow.find('.deactivate a');
            } else {
                // Fallback: look for Alt Magic in plugin names
                $('tr.active').each(function () {
                    var pluginName = $(this).find('.plugin-title strong').text();
                    if (pluginName.toLowerCase().indexOf('alt magic') !== -1) {
                        self.deactivationLink = $(this).find('.deactivate a');
                        return false; // break loop
                    }
                });
            }
        },

        /**
         * Setup modal elements
         */
        setupModal: function () {
            this.modal = $('#altm-deactivation-survey-modal');
            this.overlay = $('#altm-modal-overlay');
            this.form = $('#altm-deactivation-survey-form');

            this.reasonInputs = this.form.find('input[name="reason"]');
            this.surveyStep = $('#altm-survey-step');
            this.pricingStep = $('#altm-pricing-step');
            this.detailsSection = $('#altm-details-section');
            this.detailsTextarea = $('#altm-details');

            this.continueButton = $('#altm-continue-button');
            this.skipButton = $('#altm-skip-and-deactivate');
            this.continueDeactivationButton = $('#altm-continue-deactivation');
            this.keepFreePlanButton = $('#altm-keep-free-plan');
            this.oneTimeDealButton = $('#altm-get-one-time-deal');

            this.bindModalEvents();
        },

        /**
         * Bind modal events
         */
        bindModalEvents: function () {
            var self = this;

            // Handle reason selection
            this.reasonInputs.on('change', function () {
                self.handleReasonChange($(this));
            });

            // Handle page-one continue button
            this.continueButton.on('click', function () {
                self.handleContinue();
            });

            // Handle skip button
            this.skipButton.on('click', function (e) {
                e.preventDefault();
                self.storeDeactivationReasonAndProceed('no_reason');
            });

            // Handle pricing rescue actions
            this.keepFreePlanButton.on('click', function () {
                self.handleRetentionAction('keep_free_plan', altm_deactivation_ajax.account_settings_url);
            });

            this.oneTimeDealButton.on('click', function () {
                self.handleRetentionAction('switch_to_one_time_pricing', altm_deactivation_ajax.one_time_deal_url, {
                    openInNewTab: true,
                    closeModal: true
                });
            });

            this.continueDeactivationButton.on('click', function (e) {
                e.preventDefault();
                self.submitSurvey({
                    retentionAction: 'continue_deactivation',
                    onSuccess: function () {
                        self.proceedWithDeactivation();
                    }
                });
            });

            // Handle modal overlay click
            this.overlay.on('click', function () {
                self.hideModal();
            });

            // Handle escape key
            $(document).on('keydown', function (e) {
                if (e.keyCode === 27 && self.modal.is(':visible')) {
                    self.hideModal();
                }
            });
        },

        /**
         * Handle reason selection change
         */
        handleReasonChange: function (selectedInput) {
            var requiresDetails = selectedInput.data('requires-details');
            var selectedValue = selectedInput.val();

            // Show/hide details section based on selection
            if (selectedValue === 'other' || requiresDetails) {
                this.detailsSection.slideDown(200);
                this.detailsTextarea.attr('required', true);
            } else {
                this.detailsSection.slideUp(200);
                this.detailsTextarea.attr('required', false);
                this.detailsTextarea.val('');
            }

            this.reasonInputs.closest('.altm-reason-option').removeClass('is-selected');
            selectedInput.closest('.altm-reason-option').addClass('is-selected');

            this.continueButton.prop('disabled', false);
        },

        /**
         * Handle page-one continue action
         */
        handleContinue: function () {
            var selectedReason = this.reasonInputs.filter(':checked').val();

            if (!selectedReason) {
                alert('Please select a reason for deactivation.');
                return;
            }

            if (!this.validateDetails(selectedReason)) {
                return;
            }

            if (selectedReason === 'cost') {
                this.showStep('pricing');
                return;
            }

            this.submitSurvey({
                onSuccess: this.proceedWithDeactivation.bind(this)
            });
        },

        /**
         * Show a specific step in the modal
         */
        showStep: function (stepName) {
            this.currentStep = stepName;

            if (stepName === 'pricing') {
                this.surveyStep.hide();
                this.pricingStep.fadeIn(180);
                this.keepFreePlanButton.trigger('focus');
                return;
            }

            this.pricingStep.hide();
            this.surveyStep.fadeIn(180);
            this.reasonInputs.first().trigger('focus');
        },

        /**
         * Validate required details
         */
        validateDetails: function (selectedReason) {
            var requiresDetails = this.reasonInputs.filter(':checked').data('requires-details');
            var details = $.trim(this.detailsTextarea.val());

            if ((requiresDetails || selectedReason === 'other') && !details) {
                alert('Please provide additional details.');
                this.detailsTextarea.trigger('focus');
                return false;
            }

            return true;
        },

        /**
         * Store a deactivation reason before following the original deactivate link.
         */
        storeDeactivationReasonAndProceed: function (reason) {
            var self = this;

            $.post(altm_deactivation_ajax.ajax_url, {
                action: 'altm_set_deactivation_reason',
                nonce: altm_deactivation_ajax.nonce,
                reason: reason
            })
                .always(function () {
                    self.proceedWithDeactivation();
                });
        },

        /**
         * Fire-and-forget retention-click event for non-deactivation actions.
         */
        sendRetentionClickEvent: function (retentionAction) {
            var selectedReason = this.reasonInputs.filter(':checked').val() || 'cost';
            var details = $.trim(this.detailsTextarea.val());

            if (navigator.sendBeacon) {
                var formData = new FormData();
                formData.append('action', 'altm_deactivation_retention_click');
                formData.append('nonce', altm_deactivation_ajax.nonce);
                formData.append('reason', selectedReason);
                formData.append('details', details);
                formData.append('retention_action', retentionAction || '');

                navigator.sendBeacon(altm_deactivation_ajax.ajax_url, formData);
                return;
            }

            $.post(altm_deactivation_ajax.ajax_url, {
                action: 'altm_deactivation_retention_click',
                nonce: altm_deactivation_ajax.nonce,
                reason: selectedReason,
                details: details,
                retention_action: retentionAction || ''
            });
        },

        /**
         * Handle a retention-path action
         */
        handleRetentionAction: function (retentionAction, redirectUrl, options) {
            options = options || {};

            this.sendRetentionClickEvent(retentionAction);

            if (options.closeModal) {
                this.hideModal();
            } else {
                this.hideModal({ keepSelection: true });
            }

            if (options.openInNewTab) {
                window.open(redirectUrl, '_blank', 'noopener,noreferrer');
                return;
            }

            window.location.href = redirectUrl;
        },

        /**
         * Show the modal
         */
        showModal: function () {
            this.showStep('survey');
            this.overlay.fadeIn(200);
            this.modal.fadeIn(200);

            // Focus on first reason option
            this.reasonInputs.first().trigger('focus');

            // Prevent body scroll
            $('body').addClass('altm-modal-open');
        },

        /**
         * Hide the modal
         */
        hideModal: function (options) {
            options = options || {};

            this.modal.fadeOut(200);
            this.overlay.fadeOut(200);

            // Reset form
            if (!options.keepSelection) {
                this.resetForm();
            }

            // Allow body scroll
            $('body').removeClass('altm-modal-open');
        },

        /**
         * Reset the form
         */
        resetForm: function () {
            this.form[0].reset();
            this.detailsSection.hide();
            this.detailsTextarea.attr('required', false);
            this.reasonInputs.closest('.altm-reason-option').removeClass('is-selected');
            this.continueButton.prop('disabled', true).text('Continue');
            this.keepFreePlanButton.prop('disabled', false).text('Keep Free Plan');
            this.oneTimeDealButton.prop('disabled', false).text('Check Lifetime Deal');
            this.continueDeactivationButton.text('Continue deactivation');
            this.showStep('survey');
        },

        /**
         * Submit the survey
         */
        submitSurvey: function (options) {
            var self = this;
            var selectedReason = this.reasonInputs.filter(':checked').val();

            options = options || {};

            if (!selectedReason) {
                alert('Please select a reason for deactivation.');
                return;
            }

            if (!this.validateDetails(selectedReason)) {
                return;
            }

            this.setActionLoading(options.retentionAction, true, 'Working...');

            // Prepare survey data
            var surveyData = {
                action: 'altm_deactivation_survey',
                nonce: altm_deactivation_ajax.nonce,
                reason: selectedReason,
                details: $.trim(this.detailsTextarea.val()),
                retention_action: options.retentionAction || ''
            };

            $.post(altm_deactivation_ajax.ajax_url, surveyData)
                .done(function (response) {
                    if (response.success) {
                        if (typeof options.onSuccess === 'function') {
                            options.onSuccess();
                        }
                    } else if (typeof options.onSuccess === 'function') {
                        options.onSuccess();
                    } else {
                        self.proceedWithDeactivation();
                    }
                })
                .fail(function () {
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess();
                    } else {
                        self.proceedWithDeactivation();
                    }
                })
                .always(function () {
                    self.setActionLoading(options.retentionAction, false);
                });
        },

        /**
         * Update loading states for active CTA
         */
        setActionLoading: function (retentionAction, isLoading, continueText) {
            continueText = continueText || 'Continue';
            var isPricingAction = !!retentionAction;

            this.continueButton.prop('disabled', isLoading || !this.reasonInputs.filter(':checked').length);
            this.keepFreePlanButton.prop('disabled', isPricingAction && isLoading);
            this.oneTimeDealButton.prop('disabled', isPricingAction && isLoading);
            this.continueDeactivationButton
                .toggleClass('is-disabled', isPricingAction && isLoading)
                .attr('aria-disabled', isPricingAction && isLoading ? 'true' : 'false');

            if (!retentionAction) {
                this.continueButton.text(isLoading ? continueText : 'Continue');
            }

            if (retentionAction === 'keep_free_plan') {
                this.keepFreePlanButton.text(isLoading ? 'Working...' : 'Keep Free Plan');
            }

            if (retentionAction === 'switch_to_one_time_pricing') {
                this.oneTimeDealButton.text(isLoading ? 'Working...' : 'Check Lifetime Deal');
            }

            if (retentionAction === 'continue_deactivation') {
                this.continueDeactivationButton.text(isLoading ? 'Working...' : 'Continue deactivation');
            }
        },

        /**
         * Proceed with plugin deactivation
         */
        proceedWithDeactivation: function () {
            this.hideModal({ keepSelection: true });

            if (this.deactivationLink && this.deactivationLink.length) {
                window.location.href = this.deactivationLink.attr('href');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        AltMagicDeactivationSurvey.init();
    });

    // Add CSS class for body when modal is open
    $('<style>').text('.altm-modal-open { overflow: hidden; }').appendTo('head');

})(jQuery);
