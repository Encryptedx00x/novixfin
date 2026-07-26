/* NovixFin v3.1 hotfix: balance visibility toggle.
 *
 * Server-rendered body.hide-balances remains the first-paint privacy fallback.
 * After DOMContentLoaded this script becomes the single page-level source of
 * truth, replacing each [data-balance] text node with dots while hidden and
 * restoring the original text from data-balance-value while visible.
 */

(function () {
    'use strict';

    var BALANCE_PLACEHOLDER = '\u2022\u2022\u2022\u2022';
    var currentHidden = true;
    var initialized = false;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function csrf() {
        if (window.NOVIX_CSRF_TOKEN) return window.NOVIX_CSRF_TOKEN;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        return '';
    }

    function collectBalanceElements(root) {
        if (!root) {
            return Array.prototype.slice.call(document.querySelectorAll('[data-balance]'));
        }

        var elements = [];
        if (root.nodeType === 1 && root.matches('[data-balance]')) {
            elements.push(root);
        }
        if (root.querySelectorAll) {
            elements = elements.concat(Array.prototype.slice.call(root.querySelectorAll('[data-balance]')));
        }
        return elements;
    }

    function rememberBalanceValue(element) {
        if (!element.dataset.balanceValue && element.textContent !== BALANCE_PLACEHOLDER) {
            element.dataset.balanceValue = element.textContent;
        }
    }

    function updateBalanceValues(hidden, root) {
        var elements = collectBalanceElements(root);

        elements.forEach(function (element) {
            rememberBalanceValue(element);
        });

        if (!hidden) {
            restoreBalanceValues(root);
            return;
        }

        elements.forEach(function (element) {
            element.textContent = BALANCE_PLACEHOLDER;
        });
    }

    function restoreBalanceValues(root) {
        collectBalanceElements(root).forEach(function (element) {
            element.textContent = element.dataset.balanceValue || '';
        });
    }

    function balanceButtons() {
        return Array.prototype.slice.call(document.querySelectorAll('#balance-visibility-btn'));
    }

    function findToggleButton(target) {
        while (target && target !== document) {
            if (target.id === 'balance-visibility-btn') {
                return target;
            }
            target = target.parentNode;
        }
        return null;
    }

    function syncButton() {
        balanceButtons().forEach(function (btn) {
            var icon = btn.querySelector('#balance-visibility-icon');
            var label = btn.querySelector('#balance-visibility-text');

            if (icon) {
                icon.textContent = currentHidden ? '\uD83D\uDE48' : '\uD83D\uDC41';
            }
            if (label) {
                label.textContent = currentHidden ? 'Mostrar saldos' : 'Ocultar saldos';
            }
            btn.setAttribute('aria-pressed', currentHidden ? 'true' : 'false');
            btn.setAttribute('title', currentHidden ? 'Mostrar saldos' : 'Ocultar saldos');
        });
    }

    function setButtonsDisabled(disabled) {
        balanceButtons().forEach(function (btn) {
            btn.disabled = disabled === true;
        });
    }

    function setHidden(hidden, root) {
        currentHidden = hidden === true;

        if (document.body) {
            document.body.classList.toggle('hide-balances', currentHidden);
            document.body.dataset.hideBalancesDefault = currentHidden ? 'true' : 'false';
        }

        updateBalanceValues(currentHidden, root);
        syncButton();
    }

    function persistPreference(hidden) {
        var params = new URLSearchParams();
        params.append('_token', csrf());
        params.append('hide_balances', hidden ? '1' : '0');

        return fetch('/actions/profile/update-balance-visibility.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: params.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || payload.ok !== true) {
                throw new Error('Invalid response');
            }
        });
    }

    ready(function () {
        var body = document.body;
        if (!body || initialized) return;
        initialized = true;
        body.dataset.balanceVisibilityInit = 'true';

        currentHidden = body.dataset.hideBalancesDefault === 'true';
        setHidden(currentHidden);
        body.classList.add('balance-visibility-ready');

        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                        updateBalanceValues(currentHidden, node);
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        document.addEventListener('click', function (event) {
            var btn = findToggleButton(event.target);
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();

            setHidden(!currentHidden);
            setButtonsDisabled(true);

            persistPreference(currentHidden).catch(function (err) {
                console.warn('balance-visibility: persist failed.', err && err.message);
            }).then(function () {
                setButtonsDisabled(false);
            }, function () {
                setButtonsDisabled(false);
            });
        });
    });
})();
