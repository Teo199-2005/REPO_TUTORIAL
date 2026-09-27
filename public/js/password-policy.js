/**
 * Shared password-requirement live feedback.
 *
 * Used by every form that creates or sets a password. Watches the password
 * input and ticks off each requirement in the indicator rendered by
 * Views/partials/password_requirements.php, so the user sees exactly which rule
 * is still unmet instead of guessing.
 *
 * The values mirror password_policy() in the PHP helper; they are written into
 * the page by the helper (see applyPasswordPolicy) so there is a single source
 * of truth on the server.
 */
(function () {
    'use strict';

    /**
     * Evaluate one requirement against a password value.
     *
     * @param {string} type  'min_length' or 'min_digits'
     * @param {string} value
     * @param {number} n
     * @returns {boolean}
     */
    function meets(type, value, n) {
        if (type === 'min_length') {
            return Array.from(value).length >= n;
        }
        if (type === 'min_digits') {
            return (value.match(/\d/g) || []).length >= n;
        }
        return false;
    }

    /**
     * Read the policy from a data attribute rendered by the PHP helper.
     *
     * @param {Element} root element carrying data-password-policy='{"min_length":8,...}'
     * @returns {{min_length:number, min_digits:number}}
     */
    function readPolicy(root) {
        var raw = root.getAttribute('data-password-policy');
        if (!raw) {
            return { min_length: 8, min_digits: 1 };
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return { min_length: 8, min_digits: 1 };
        }
    }

    /**
     * Update the indicator next to a password field.
     *
     * @param {HTMLInputElement} field
     */
    function updateField(field) {
        if (!field) {
            return;
        }

        var wrapper = field.closest('[data-password-indicator]') || field.parentElement;
        if (!wrapper) {
            return;
        }

        var indicator = wrapper.querySelector('.password-requirements');
        if (!indicator) {
            return;
        }

        var policy = readPolicy(indicator);
        var value = field.value || '';
        var items = indicator.querySelectorAll('.password-requirement');
        var labels = ['min_length', 'min_digits'];

        for (var i = 0; i < items.length; i++) {
            var type = labels[i];
            if (!type) {
                continue;
            }
            var ok = meets(type, value, policy[type]);
            items[i].classList.toggle('is-unmet', !ok);
            items[i].classList.toggle('is-met', ok);
        }
    }

    /**
     * Wire up every password field that opts in via data-password-indicator.
     */
    function init() {
        var fields = document.querySelectorAll('input[type="password"][data-password-indicator]');
        Array.prototype.forEach.call(fields, function (field) {
            updateField(field);
            field.addEventListener('input', function () { updateField(field); });
            field.addEventListener('blur', function () { updateField(field); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for pages that build inputs dynamically.
    window.PasswordPolicy = { updateField: updateField, init: init, meets: meets };
}());
