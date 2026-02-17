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
        detailsSection: null,
        detailsTextarea: null,
        submitButton: null,
        skipButton: null,
        cancelButton: null,

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

            if (pluginRow.length) {
                this.deactivationLink = pluginRow.find('.deactivate a');
            } else {
                // Fallback: look for Alt Magic in plugin names
                $('tr.active').each(function () {
                    var pluginName = $(this).find('.plugin-title strong').text();
                    if (pluginName.toLowerCase().indexOf('alt magic') !== -1) {
                        this.deactivationLink = $(this).find('.deactivate a');
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
            this.detailsSection = $('#altm-details-section');
            this.detailsTextarea = $('#altm-details');
            this.ltdOffer = $('#altm-ltd-offer');

            this.submitButton = $('#altm-submit-and-deactivate');
            this.skipButton = $('#altm-skip-and-deactivate');
            this.cancelButton = $('#altm-cancel-deactivation');

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

            // Handle submit button
            this.submitButton.on('click', function () {
                self.submitSurvey();
            });

            // Handle skip button
            this.skipButton.on('click', function (e) {
                e.preventDefault();
                self.proceedWithDeactivation();
            });

            // Handle cancel button
            this.cancelButton.on('click', function () {
                self.hideModal();
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
            var isOtherSelected = selectedInput.val() === 'other';
            var isPricingConcern = selectedInput.val() === 'cost';

            // Show/hide details section based on selection
            if (isOtherSelected || requiresDetails) {
                this.detailsSection.slideDown(200);
                this.detailsTextarea.attr('required', true);
            } else {
                this.detailsSection.slideUp(200);
                this.detailsTextarea.attr('required', false);
                this.detailsTextarea.val('');
            }

            // Toggle lifetime deal offer visibility for pricing concerns
            if (isPricingConcern) {
                this.ltdOffer.stop(true, true).slideDown(200);
            } else {
                this.ltdOffer.stop(true, true).slideUp(200);
            }

            // Enable submit button
            this.submitButton.prop('disabled', false);
        },

        /**
         * Show the modal
         */
        showModal: function () {
            this.overlay.fadeIn(200);
            this.modal.fadeIn(200);

            // Focus on first reason option
            this.reasonInputs.first().focus();

            // Prevent body scroll
            $('body').addClass('altm-modal-open');
        },

        /**
         * Hide the modal
         */
        hideModal: function () {
            this.modal.fadeOut(200);
            this.overlay.fadeOut(200);

            // Reset form
            this.resetForm();

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
            this.submitButton.prop('disabled', true);
        },

        /**
         * Submit the survey
         */
        submitSurvey: function () {
            var self = this;
            var selectedReason = this.reasonInputs.filter(':checked').val();

            if (!selectedReason) {
                alert('Please select a reason for deactivation.');
                return;
            }

            // Check if details are required and provided
            var requiresDetails = this.reasonInputs.filter(':checked').data('requires-details');
            var details = this.detailsTextarea.val().trim();

            if ((requiresDetails || selectedReason === 'other') && !details) {
                alert('Please provide additional details.');
                this.detailsTextarea.focus();
                return;
            }

            // Disable submit button and show loading
            this.submitButton.prop('disabled', true).text('Submitting...');

            // Prepare survey data
            var surveyData = {
                action: 'altm_deactivation_survey',
                nonce: altm_deactivation_ajax.nonce,
                reason: selectedReason,
                details: details
            };

            // Submit survey via AJAX
            $.post(altm_deactivation_ajax.ajax_url, surveyData)
                .done(function (response) {
                    if (response.success) {
                        self.proceedWithDeactivation();
                    } else {
                        alert('Failed to submit survey. Proceeding with deactivation.');
                        self.proceedWithDeactivation();
                    }
                })
                .fail(function () {
                    // If AJAX fails, proceed with deactivation anyway
                    self.proceedWithDeactivation();
                })
                .always(function () {
                    self.submitButton.prop('disabled', false).text('Submit & Deactivate');
                });
        },

        /**
         * Proceed with plugin deactivation
         */
        proceedWithDeactivation: function () {
            this.hideModal();

            if (this.deactivationLink && this.deactivationLink.length) {
                // Navigate to the original deactivation URL
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
