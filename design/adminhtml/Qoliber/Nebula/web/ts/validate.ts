/**
 * Nebula Validate — the one and only client-side validator.
 *
 * Supported rules (declared via `data-validate="required|number|min:X"`):
 *   required, number, min:X, max:X, minLength:X, maxLength:X, email, pattern:X
 *
 * Public API on `window.Nebula`:
 *   validateField(input)              → boolean
 *   validateForm(form)                → boolean
 *   validateValue(value, rulesObj)    → string|null (first error message)
 *   registerValidator(fn)             → register a cross-field snippet validator
 *   rule(name, fn, msg)               → register/override a rule
 */

import type {
    NebulaGlobal,
    SnippetValidator,
    ValidationRuleFn,
    ValidationRulesObject,
} from './types';

const ERROR_CLASS = 'nebula-field-error';

const RULES: Record<string, ValidationRuleFn> = {
    required: (value) => {
        if (value === null || value === undefined) return false;
        if (Array.isArray(value)) return value.length > 0;
        return String(value).trim() !== '';
    },
    number: (value) => {
        if (value === '' || value === null || value === undefined) return true;
        // Mirror the JS original: `isFinite(value)` coerces its argument so
        // multi-dot strings like "1.2.3" correctly fail (Number("1.2.3")=NaN).
        const num = parseFloat(String(value));
        return !isNaN(num) && isFinite(value as number);
    },
    min: (value, param) => {
        if (value === '' || value === null || value === undefined) return true;
        return parseFloat(String(value)) >= parseFloat(String(param));
    },
    max: (value, param) => {
        if (value === '' || value === null || value === undefined) return true;
        return parseFloat(String(value)) <= parseFloat(String(param));
    },
    minLength: (value, param) => {
        if (value === '' || value === null || value === undefined) return true;
        return String(value).length >= parseInt(String(param), 10);
    },
    maxLength: (value, param) => {
        if (value === '' || value === null || value === undefined) return true;
        return String(value).length <= parseInt(String(param), 10);
    },
    email: (value) => {
        if (value === '' || value === null || value === undefined) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value));
    },
    pattern: (value, param) => {
        if (value === '' || value === null || value === undefined) return true;
        return new RegExp(String(param)).test(String(value));
    },
};

const MESSAGES: Record<string, string> = {
    required: '{label} is required',
    number: '{label} must be a number',
    min: '{label} must be at least {param}',
    max: '{label} must be at most {param}',
    minLength: '{label} must be at least {param} characters',
    maxLength: '{label} must be at most {param} characters',
    email: '{label} must be a valid email',
    pattern: '{label} format is invalid',
};

interface ParsedRule {
    name: string;
    param: string | null;
}

const parseRules = (rulesStr: string): ParsedRule[] => {
    if (!rulesStr) return [];
    return rulesStr.split('|').map((rule) => {
        const parts = rule.split(':');
        return { name: parts[0] ?? '', param: parts[1] ?? null };
    });
};

const getMessage = (ruleName: string, label: string, param: string | null): string => {
    const msg = MESSAGES[ruleName] ?? '{label} is invalid';
    return msg.replace('{label}', label).replace('{param}', param ?? '');
};

const clearFieldError = (field: HTMLElement): void => {
    field.classList.remove(ERROR_CLASS);
    const existing = field.parentNode?.querySelector('.nebula-field-error-msg');
    if (existing) existing.remove();
};

const setFieldError = (field: HTMLElement, message: string): void => {
    field.classList.add(ERROR_CLASS);
    const msgEl = document.createElement('div');
    msgEl.className = 'nebula-field-error-msg';
    msgEl.textContent = message;
    const existing = field.parentNode?.querySelector('.nebula-field-error-msg');
    if (existing) existing.remove();
    field.parentNode?.appendChild(msgEl);
};

const readFieldValue = (field: HTMLElement): string => {
    if (
        field instanceof HTMLInputElement ||
        field instanceof HTMLTextAreaElement ||
        field instanceof HTMLSelectElement
    ) {
        return field.value;
    }
    return '';
};

const isHiddenOrDisabled = (field: HTMLElement): boolean => {
    if (
        field instanceof HTMLInputElement ||
        field instanceof HTMLSelectElement ||
        field instanceof HTMLTextAreaElement
    ) {
        if (field.disabled) return true;
        if (field instanceof HTMLInputElement && field.type === 'hidden') return true;
    }
    const parent = field.closest('[x-show]');
    if (parent && (field as HTMLElement).offsetParent === null) return true;
    return false;
};

const validateField = (field: HTMLElement): boolean => {
    const rulesStr = field.getAttribute('data-validate');
    if (!rulesStr) return true;

    const label =
        field.getAttribute('data-validate-label') ?? field.getAttribute('name') ?? 'Field';
    const rules = parseRules(rulesStr);
    const value = readFieldValue(field);

    clearFieldError(field);

    for (const rule of rules) {
        const fn = RULES[rule.name];
        if (fn && !fn(value, rule.param)) {
            setFieldError(field, getMessage(rule.name, label, rule.param));
            return false;
        }
    }

    return true;
};

const validateValue = (
    value: unknown,
    rules: ValidationRulesObject | null | undefined,
): string | null => {
    if (!rules || typeof rules !== 'object') return null;

    const label = rules.label ?? 'Field';
    const tests = Object.entries(rules);

    for (const [ruleName, param] of tests) {
        if (ruleName === 'label') continue;
        const fn = RULES[ruleName];
        if (!fn) continue;
        if (param === true) {
            if (!fn(value, null)) return getMessage(ruleName, label, null);
        } else if (param === false || param === null || param === undefined) {
            continue;
        } else {
            if (!fn(value, param)) return getMessage(ruleName, label, String(param));
        }
    }

    return null;
};

const snippetValidators: SnippetValidator[] = [];
const registerSnippetValidator = (fn: SnippetValidator): void => {
    snippetValidators.push(fn);
};

const runSnippetValidators = (form: HTMLFormElement): HTMLElement[] => {
    let errors: HTMLElement[] = [];
    snippetValidators.forEach((fn) => {
        const result = fn(form);
        if (result && result.length) errors = errors.concat(result);
    });
    return errors;
};

const validateForm = (form: HTMLFormElement): boolean => {
    const fields = form.querySelectorAll<HTMLElement>('[data-validate]');
    const errors: HTMLElement[] = [];
    let firstError: HTMLElement | null = null;

    fields.forEach((field) => {
        if (isHiddenOrDisabled(field)) return;
        if (!validateField(field)) {
            errors.push(field);
            if (!firstError) firstError = field;
        }
    });

    errors.push(...runSnippetValidators(form));

    if (errors.length > 0) {
        if (firstError) {
            (firstError as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'center' });
            (firstError as HTMLElement).focus();
        }
        if (window.nebulaToast) {
            window.nebulaToast(
                'error',
                errors.length + (errors.length === 1 ? ' error' : ' errors') + ' found. Please fix before saving.',
            );
        }
    }

    return errors.length === 0;
};

const registerRule = (name: string, fn: ValidationRuleFn, msg?: string): void => {
    RULES[name] = fn;
    if (msg) MESSAGES[name] = msg;
};

/**
 * Install the validate API on `window.Nebula` and wire the global blur/input
 * listeners. Safe to call multiple times — guarded via `__nebulaValidateInstalled`.
 */
export function installValidate(): void {
    const g = window as Window & { __nebulaValidateInstalled?: boolean };
    if (g.__nebulaValidateInstalled) return;
    g.__nebulaValidateInstalled = true;

    document.addEventListener(
        'blur',
        (e) => {
            const target = e.target as HTMLElement | null;
            if (target && typeof target.getAttribute === 'function' && target.getAttribute('data-validate')) {
                validateField(target);
            }
        },
        true,
    );

    document.addEventListener(
        'input',
        (e) => {
            const target = e.target as HTMLElement | null;
            if (target && target.classList && target.classList.contains(ERROR_CLASS)) {
                clearFieldError(target);
            }
        },
        true,
    );

    const api: NebulaGlobal = {
        validateField,
        validateForm,
        validateValue,
        registerValidator: registerSnippetValidator,
        clearFieldError,
        setFieldError,
        rule: registerRule,
    };

    window.Nebula = Object.assign({}, window.Nebula ?? {}, api);
}

// Test-only exports.
export const __testables = {
    RULES,
    MESSAGES,
    parseRules,
    getMessage,
    validateField,
    validateForm,
    validateValue,
    registerSnippetValidator,
    clearFieldError,
    setFieldError,
};
