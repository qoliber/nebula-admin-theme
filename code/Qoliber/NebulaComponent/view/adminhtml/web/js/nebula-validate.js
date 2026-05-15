/**
 * Nebula Validate — Client-side form validation library
 *
 * Usage:
 *   HTML: <input data-validate="required|number|min:0.01" data-validate-label="Price">
 *   JS:   var errors = Nebula.validateForm(formElement);
 *         var valid = Nebula.validateField(inputElement);
 *
 * Rules: required, number, min:X, max:X, minLength:X, maxLength:X, email, pattern:X
 */
(() => {
    'use strict';

    const ERROR_CLASS = 'nebula-field-error';

    // Inject error styles once
    const style = document.createElement('style');
    style.textContent = ''
        + '.' + ERROR_CLASS + ' { border-color: #ef4444 !important; ring-color: #ef4444 !important; }'
        + '.' + ERROR_CLASS + ':focus { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239,68,68,.2) !important; }'
        + '.nebula-field-error-msg { color: #ef4444; font-size: 12px; margin-top: 4px; }';
    document.head.appendChild(style);

    const RULES = {
        required: (value) => {
            if (value === null || value === undefined) return false;
            return String(value).trim() !== '';
        },
        number: (value) => {
            if (value === '' || value === null || value === undefined) return true; // not required check
            return !isNaN(parseFloat(value)) && isFinite(value);
        },
        min: (value, param) => {
            if (value === '' || value === null || value === undefined) return true;
            return parseFloat(value) >= parseFloat(param);
        },
        max: (value, param) => {
            if (value === '' || value === null || value === undefined) return true;
            return parseFloat(value) <= parseFloat(param);
        },
        minLength: (value, param) => {
            if (value === '' || value === null || value === undefined) return true;
            return String(value).length >= parseInt(param);
        },
        maxLength: (value, param) => {
            if (value === '' || value === null || value === undefined) return true;
            return String(value).length <= parseInt(param);
        },
        email: (value) => {
            if (value === '' || value === null || value === undefined) return true;
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        pattern: (value, param) => {
            if (value === '' || value === null || value === undefined) return true;
            return new RegExp(param).test(value);
        }
    };

    const MESSAGES = {
        required: '{label} is required',
        number: '{label} must be a number',
        min: '{label} must be at least {param}',
        max: '{label} must be at most {param}',
        minLength: '{label} must be at least {param} characters',
        maxLength: '{label} must be at most {param} characters',
        email: '{label} must be a valid email',
        pattern: '{label} format is invalid'
    };

    const parseRules = (rulesStr) => {
        if (!rulesStr) return [];
        return rulesStr.split('|').map((rule) => {
            const parts = rule.split(':');
            return { name: parts[0], param: parts[1] || null };
        });
    };

    const getMessage = (ruleName, label, param) => {
        const msg = MESSAGES[ruleName] || '{label} is invalid';
        return msg.replace('{label}', label).replace('{param}', param || '');
    };

    const clearFieldError = (field) => {
        field.classList.remove(ERROR_CLASS);
        const existing = field.parentNode.querySelector('.nebula-field-error-msg');
        if (existing) existing.remove();
    };

    const setFieldError = (field, message) => {
        field.classList.add(ERROR_CLASS);
        const msgEl = document.createElement('div');
        msgEl.className = 'nebula-field-error-msg';
        msgEl.textContent = message;
        // Avoid duplicates
        const existing = field.parentNode.querySelector('.nebula-field-error-msg');
        if (existing) existing.remove();
        field.parentNode.appendChild(msgEl);
    };

    const validateField = (field) => {
        const rulesStr = field.getAttribute('data-validate');
        if (!rulesStr) return true;

        const label = field.getAttribute('data-validate-label') || field.getAttribute('name') || 'Field';
        const rules = parseRules(rulesStr);
        const value = field.value;

        clearFieldError(field);

        for (let i = 0; i < rules.length; i++) {
            const rule = rules[i];
            const fn = RULES[rule.name];
            if (fn && !fn(value, rule.param)) {
                setFieldError(field, getMessage(rule.name, label, rule.param));
                return false;
            }
        }

        return true;
    };

    const validateForm = (form) => {
        const fields = form.querySelectorAll('[data-validate]');
        let errors = [];
        let firstError = null;

        fields.forEach((field) => {
            // Skip hidden/disabled fields
            if (field.type === 'hidden' || field.disabled) return;
            // Skip fields inside removed/hidden containers
            if (field.closest('[x-show]') && field.offsetParent === null) return;

            if (!validateField(field)) {
                errors.push(field);
                if (!firstError) firstError = field;
            }
        });

        // Also run any registered snippet validators
        const customErrors = runSnippetValidators(form);
        errors = errors.concat(customErrors);

        if (errors.length > 0) {
            // Scroll to first error
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            // Toast
            if (window.nebulaToast) {
                window.nebulaToast('error', errors.length + (errors.length === 1 ? ' error' : ' errors') + ' found. Please fix before saving.');
            }
        }

        return errors.length === 0;
    };

    // Snippet validator registry
    const snippetValidators = [];

    const registerSnippetValidator = (fn) => {
        snippetValidators.push(fn);
    };

    const runSnippetValidators = (form) => {
        let errors = [];
        snippetValidators.forEach((fn) => {
            const result = fn(form);
            if (result && result.length) {
                errors = errors.concat(result);
            }
        });
        return errors;
    };

    // Auto-validate on blur
    document.addEventListener('blur', (e) => {
        if (e.target && e.target.getAttribute && e.target.getAttribute('data-validate')) {
            validateField(e.target);
        }
    }, true);

    // Clear error on input
    document.addEventListener('input', (e) => {
        if (e.target && e.target.classList && e.target.classList.contains(ERROR_CLASS)) {
            clearFieldError(e.target);
        }
    }, true);

    // Expose globally
    window.Nebula = window.Nebula || {};
    window.Nebula.validateForm = validateForm;
    window.Nebula.validateField = validateField;
    window.Nebula.registerValidator = registerSnippetValidator;
    window.Nebula.clearFieldError = clearFieldError;
    window.Nebula.setFieldError = setFieldError;
})();
