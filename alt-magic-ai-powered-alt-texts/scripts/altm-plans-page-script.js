(function () {
    'use strict';

    var lastFocusedElement = null;

    function normalizeView(view) {
        return view === 'lifetime' ? 'lifetime' : 'credits';
    }

    function setSurfaceView(surface, view, shouldFocus) {
        if (!surface) {
            return;
        }

        view = normalizeView(view);
        surface.setAttribute('data-selected-view', view);

        Array.prototype.forEach.call(surface.querySelectorAll('[data-altm-plan-tab]'), function (tab) {
            var isActive = tab.getAttribute('data-altm-plan-tab') === view;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');

            if (isActive && shouldFocus) {
                tab.focus();
            }
        });

        Array.prototype.forEach.call(surface.querySelectorAll('[data-altm-plan-panel]'), function (panel) {
            var isActive = panel.getAttribute('data-altm-plan-panel') === view;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (surface.id === 'altm-plans-page-surface' && window.history && window.URL) {
            var pageUrl = new URL(window.location.href);
            pageUrl.searchParams.set('view', view);
            window.history.replaceState({}, '', pageUrl.toString());
        }

        if (surface.id === 'altm-plans-modal-surface' && typeof altmPlansSettings !== 'undefined' && altmPlansSettings.plansPageUrl) {
            var fullPageLink = document.querySelector('.altm-plans-modal__footer a');
            if (fullPageLink) {
                var fullPageUrl = new URL(altmPlansSettings.plansPageUrl, window.location.href);
                fullPageUrl.searchParams.set('view', view);
                fullPageLink.href = fullPageUrl.toString();
            }
        }
    }

    function initializeSurfaces() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-altm-plans-surface]'), function (surface) {
            setSurfaceView(surface, surface.getAttribute('data-selected-view'), false);
        });
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function resetFaqAnswer(answer) {
        answer.style.height = '';
        answer.style.opacity = '';
        answer.style.overflow = '';
    }

    function animateFaqItem(item, shouldOpen) {
        var answer = item.querySelector('.altm-plans-faq__answer');

        if (!answer) {
            item.open = shouldOpen;
            item.altmFaqExpanded = shouldOpen;
            return;
        }

        var wasOpen = item.open;
        var startHeight = wasOpen ? answer.getBoundingClientRect().height : 0;
        var startOpacity = wasOpen ? parseFloat(window.getComputedStyle(answer).opacity) : 0;

        if (item.altmFaqAnimation) {
            item.altmFaqAnimation.cancel();
        }

        item.altmFaqExpanded = shouldOpen;

        if (prefersReducedMotion() || typeof answer.animate !== 'function') {
            item.open = shouldOpen;
            resetFaqAnswer(answer);
            return;
        }

        if (shouldOpen) {
            item.open = true;
        } else if (!wasOpen) {
            item.open = false;
            resetFaqAnswer(answer);
            return;
        }

        var endHeight = shouldOpen ? answer.scrollHeight : 0;
        var endOpacity = shouldOpen ? 1 : 0;

        answer.style.height = startHeight + 'px';
        answer.style.opacity = String(startOpacity);
        answer.style.overflow = 'hidden';

        var animation = answer.animate(
            [
                { height: startHeight + 'px', opacity: startOpacity },
                { height: endHeight + 'px', opacity: endOpacity }
            ],
            {
                duration: 220,
                easing: 'ease-in-out'
            }
        );
        item.altmFaqAnimation = animation;

        animation.onfinish = function () {
            if (item.altmFaqAnimation !== animation) {
                return;
            }

            if (!shouldOpen) {
                item.open = false;
            }

            resetFaqAnswer(answer);
            item.altmFaqAnimation = null;
        };

        animation.oncancel = function () {
            if (item.altmFaqAnimation !== animation) {
                return;
            }

            if (!shouldOpen) {
                item.open = false;
            }

            resetFaqAnswer(answer);
            item.altmFaqAnimation = null;
        };
    }

    function initializeFaqs() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-altm-plans-faq]'), function (faqSection) {
            var items = Array.prototype.slice.call(faqSection.querySelectorAll('details'));

            items.forEach(function (item) {
                var summary = item.querySelector('summary');
                item.altmFaqExpanded = item.open;

                if (!summary) {
                    return;
                }

                summary.addEventListener('click', function (event) {
                    event.preventDefault();

                    var shouldOpen = !item.altmFaqExpanded;

                    if (shouldOpen) {
                        items.forEach(function (otherItem) {
                            if (otherItem !== item && otherItem.altmFaqExpanded) {
                                animateFaqItem(otherItem, false);
                            }
                        });
                    }

                    animateFaqItem(item, shouldOpen);
                });
            });
        });
    }

    function initializePlansUi() {
        initializeSurfaces();
        initializeFaqs();
    }

    function closePlansModal() {
        var modal = document.getElementById('altm-plans-modal');

        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('altm-plans-modal-open');

        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    function openPlansModal(message, view) {
        var modal = document.getElementById('altm-plans-modal');

        if (!modal) {
            if (typeof altmPlansSettings !== 'undefined' && altmPlansSettings.plansPageUrl) {
                window.location.href = altmPlansSettings.plansPageUrl;
            }
            return;
        }

        lastFocusedElement = document.activeElement;

        var titleElement = document.getElementById('altm-plans-modal-title');
        if (titleElement) {
            titleElement.textContent = (
                typeof altmPlansSettings !== 'undefined'
                && altmPlansSettings.defaultNoCreditsMessage
                    ? altmPlansSettings.defaultNoCreditsMessage
                    : 'No credits remaining.'
            );
        }

        setSurfaceView(modal.querySelector('[data-altm-plans-surface]'), view, false);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('altm-plans-modal-open');

        var closeButton = modal.querySelector('.altm-plans-modal__close');
        if (closeButton) {
            closeButton.focus();
        }
    }

    function readCreditError(payload) {
        var texts = [];
        var creditNumbers = [];
        var visited = [];

        function inspect(value, depth) {
            if (value === null || typeof value === 'undefined' || depth > 4) {
                return;
            }

            if (typeof value === 'string') {
                var trimmedValue = value.trim();
                if ((trimmedValue.charAt(0) === '{' || trimmedValue.charAt(0) === '[') && trimmedValue.length < 20000) {
                    try {
                        inspect(JSON.parse(trimmedValue), depth + 1);
                        return;
                    } catch (error) {
                        // Fall through to text matching for non-JSON response bodies.
                    }
                }
                texts.push(value);
                return;
            }

            if (typeof value !== 'object' || visited.indexOf(value) !== -1) {
                return;
            }

            visited.push(value);

            ['message', 'error', 'error_code', 'errorCode', 'error_message', 'errorMessage'].forEach(function (key) {
                if (typeof value[key] === 'string') {
                    texts.push(value[key]);
                }
            });

            ['credits_available', 'creditsAvailable', 'credits_required', 'creditsRequired'].forEach(function (key) {
                if (typeof value[key] === 'number' || (typeof value[key] === 'string' && value[key] !== '')) {
                    var numberValue = Number(value[key]);
                    if (!isNaN(numberValue)) {
                        creditNumbers.push({ key: key, value: numberValue });
                    }
                }
            });

            ['data', 'responseJSON', 'response', 'responseText', 'body'].forEach(function (key) {
                if (value[key]) {
                    inspect(value[key], depth + 1);
                }
            });
        }

        inspect(payload, 0);

        var combinedText = texts.join(' ').toLowerCase();
        var hasCreditLanguage = /(credit|credits|quota|balance)/.test(combinedText)
            && /(insufficient|not enough|no |zero|deplet|exhaust|run out|ran out|required|purchase)/.test(combinedText);
        var available = null;
        var required = null;

        creditNumbers.forEach(function (item) {
            if (/available/i.test(item.key)) {
                available = item.value;
            }
            if (/required/i.test(item.key)) {
                required = item.value;
            }
        });

        var hasCreditNumbers = available !== null && (available <= 0 || (required !== null && required > available));

        return {
            isCreditsError: hasCreditLanguage || hasCreditNumbers,
            message: texts.length ? texts[0] : ''
        };
    }

    window.altmOpenPlansModal = openPlansModal;
    window.altmClosePlansModal = closePlansModal;
    window.altmIsCreditsError = function (payload) {
        return readCreditError(payload).isCreditsError;
    };
    window.altmHandleCreditsError = function (payload, fallbackMessage) {
        var creditError = readCreditError(payload);

        if (!creditError.isCreditsError) {
            return false;
        }

        openPlansModal(creditError.message || fallbackMessage, 'credits');
        return true;
    };

    document.addEventListener('click', function (event) {
        var modalOpener = event.target.closest('[data-altm-plans-open]');
        if (modalOpener) {
            event.preventDefault();
            openPlansModal(modalOpener.getAttribute('data-altm-plans-message'), 'credits');
            return;
        }

        var tab = event.target.closest('[data-altm-plan-tab]');
        if (tab) {
            event.preventDefault();
            setSurfaceView(tab.closest('[data-altm-plans-surface]'), tab.getAttribute('data-altm-plan-tab'), false);
            return;
        }

        if (event.target.closest('[data-altm-plans-close]')) {
            event.preventDefault();
            closePlansModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        var modal = document.getElementById('altm-plans-modal');

        if ((event.key === 'ArrowLeft' || event.key === 'ArrowRight') && event.target.matches('[data-altm-plan-tab]')) {
            event.preventDefault();
            var surface = event.target.closest('[data-altm-plans-surface]');
            var tabs = Array.prototype.slice.call(surface.querySelectorAll('[data-altm-plan-tab]'));
            var currentIndex = tabs.indexOf(event.target);
            var direction = event.key === 'ArrowRight' ? 1 : -1;
            var nextIndex = (currentIndex + direction + tabs.length) % tabs.length;
            setSurfaceView(surface, tabs[nextIndex].getAttribute('data-altm-plan-tab'), true);
            return;
        }

        if (event.key === 'Escape' && modal && !modal.hidden) {
            closePlansModal();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePlansUi);
    } else {
        initializePlansUi();
    }
}());
