(function ($) {
    var localSiteSupportBaseUrl = 'https://www.altmagic.pro/?utm_source=wordpress_plugin&utm_medium=local_site_unlock';

    function getErrorMessage(error) {
        if (!error) {
            return '';
        }

        if (typeof error === 'string') {
            return error;
        }

        if (error.message) {
            return error.message;
        }

        if (typeof error.error === 'string') {
            return error.error;
        }

        if (error.data && error.data.message) {
            return error.data.message;
        }

        if (error.data && typeof error.data.error === 'string') {
            return error.data.error;
        }

        if (error.responseJSON) {
            return getErrorMessage(error.responseJSON);
        }

        if (error.responseText) {
            try {
                return getErrorMessage(JSON.parse(error.responseText));
            } catch (parseError) {
                return error.responseText;
            }
        }

        return '';
    }

    function getSiteUrl() {
        if (window.altmLocalSiteUnlock && window.altmLocalSiteUnlock.siteUrl) {
            return window.altmLocalSiteUnlock.siteUrl;
        }

        return window.location.origin || '';
    }

    function getUnlockRequestMessage() {
        return 'Please unlock local site generation for my WordPress site: ' + getSiteUrl();
    }

    function getLocalSiteSupportUrl() {
        return localSiteSupportBaseUrl +
            '&altm_open_chat=1' +
            '&altm_chat_topic=local_site_unlock' +
            '&altm_site_url=' + encodeURIComponent(getSiteUrl()) +
            '&altm_chat_message=' + encodeURIComponent(getUnlockRequestMessage());
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function copyUnlockMessage() {
        var message = getUnlockRequestMessage();

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(message).catch(function () {});
        }
    }

    function showLocalSiteBlockedModal(error) {
        $('#altm-local-site-blocked-modal').remove();

        var unlockInstruction = ' Click "Unlock for local site" to ask Alt Magic support to enable generation for this local site.';
        var message = getErrorMessage(error) || 'Local development site URLs are temporarily blocked for generation. Please use a publicly accessible site URL.';

        if (message.indexOf('Unlock for local site') === -1 && isLocalSiteGenerationBlocked(error)) {
            message += unlockInstruction;
        }

        var modalHtml = '<div id="altm-local-site-blocked-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000000;">' +
            '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; min-width: 400px; max-width: 540px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">' +
            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
            '<h3 style="margin: 0; color: #b70000;">Local site generation is blocked</h3>' +
            '<button type="button" id="close-local-site-blocked-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; line-height: 1;">&times;</button>' +
            '</div>' +
            '<div style="margin-bottom: 20px; line-height: 1.6;">' +
            '<p style="margin: 0;">' + escapeHtml(message) + '</p>' +
            '</div>' +
            '<div style="text-align: end;">' +
            '<button type="button" id="dismiss-local-site-blocked-modal" class="button" style="margin-right: 10px;">Dismiss</button>' +
            '<a id="unlock-local-site-generation" href="' + getLocalSiteSupportUrl() + '" target="_blank" rel="noopener noreferrer" class="button button-primary">Unlock for local site</a>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(modalHtml);
        $('#altm-local-site-blocked-modal').fadeIn(200);
    }

    function isLocalSiteGenerationBlocked(error) {
        if (error && error.error_code === 'local_site_blocked') {
            return true;
        }

        if (error && error.data && error.data.error_code === 'local_site_blocked') {
            return true;
        }

        var message = getErrorMessage(error).toLowerCase();

        return message.indexOf('local development site') !== -1 ||
            message.indexOf('publicly accessible site url') !== -1 ||
            message.indexOf('publicly accessible site') !== -1;
    }

    $(document).on('click', '#close-local-site-blocked-modal, #dismiss-local-site-blocked-modal', function () {
        $('#altm-local-site-blocked-modal').fadeOut(200, function () {
            $(this).remove();
        });
    });

    $(document).on('click', '#unlock-local-site-generation', function () {
        copyUnlockMessage();
        $('#altm-local-site-blocked-modal').fadeOut(200, function () {
            $(this).remove();
        });
    });

    $(document).on('click', '#altm-local-site-blocked-modal', function (event) {
        if (event.target === this) {
            $(this).fadeOut(200, function () {
                $(this).remove();
            });
        }
    });

    window.altmIsLocalSiteGenerationBlocked = isLocalSiteGenerationBlocked;
    window.altmShowLocalSiteUnlockModal = showLocalSiteBlockedModal;
})(jQuery);
