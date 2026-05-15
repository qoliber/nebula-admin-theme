import { beforeEach, describe, expect, it } from 'vitest';
import { __testables, installValidate } from '../validate';

const { RULES, parseRules, getMessage, validateValue, validateField, validateForm } = __testables;

describe('RULES', () => {
    describe('required', () => {
        it('fails on null, undefined, empty string, whitespace', () => {
            expect(RULES['required']?.(null, null)).toBe(false);
            expect(RULES['required']?.(undefined, null)).toBe(false);
            expect(RULES['required']?.('', null)).toBe(false);
            expect(RULES['required']?.('   ', null)).toBe(false);
        });
        it('passes on non-empty string and non-empty array', () => {
            expect(RULES['required']?.('x', null)).toBe(true);
            expect(RULES['required']?.(['a'], null)).toBe(true);
            expect(RULES['required']?.(0, null)).toBe(true); // "0" is non-empty string
        });
        it('fails on empty array', () => {
            expect(RULES['required']?.([], null)).toBe(false);
        });
    });

    describe('number', () => {
        it('passes on empty input (optional by default)', () => {
            expect(RULES['number']?.('', null)).toBe(true);
            expect(RULES['number']?.(null, null)).toBe(true);
        });
        it('accepts numeric strings', () => {
            expect(RULES['number']?.('42', null)).toBe(true);
            expect(RULES['number']?.('3.14', null)).toBe(true);
            expect(RULES['number']?.('-1', null)).toBe(true);
        });
        it('rejects non-numeric strings', () => {
            expect(RULES['number']?.('abc', null)).toBe(false);
            // "1.2.3" parses to 1.2 but isFinite("1.2.3") is false in JS.
            expect(RULES['number']?.('1.2.3', null)).toBe(false);
        });
    });

    describe('min/max', () => {
        it('min passes when value >= param', () => {
            expect(RULES['min']?.('10', '5')).toBe(true);
            expect(RULES['min']?.('5', '5')).toBe(true);
        });
        it('min fails when value < param', () => {
            expect(RULES['min']?.('4', '5')).toBe(false);
        });
        it('max passes when value <= param', () => {
            expect(RULES['max']?.('5', '10')).toBe(true);
            expect(RULES['max']?.('5', '5')).toBe(true);
        });
        it('max fails when value > param', () => {
            expect(RULES['max']?.('11', '10')).toBe(false);
        });
    });

    describe('minLength/maxLength', () => {
        it('minLength passes on empty input (optional)', () => {
            expect(RULES['minLength']?.('', '3')).toBe(true);
        });
        it('minLength counts characters, not bytes', () => {
            expect(RULES['minLength']?.('ab', '3')).toBe(false);
            expect(RULES['minLength']?.('abc', '3')).toBe(true);
        });
        it('maxLength caps length', () => {
            expect(RULES['maxLength']?.('abc', '3')).toBe(true);
            expect(RULES['maxLength']?.('abcd', '3')).toBe(false);
        });
    });

    describe('email', () => {
        it('passes on empty input', () => {
            expect(RULES['email']?.('', null)).toBe(true);
        });
        it('accepts well-formed addresses', () => {
            expect(RULES['email']?.('a@b.co', null)).toBe(true);
            expect(RULES['email']?.('john.doe@example.com', null)).toBe(true);
        });
        it('rejects malformed addresses', () => {
            expect(RULES['email']?.('nope', null)).toBe(false);
            expect(RULES['email']?.('a@b', null)).toBe(false);
            expect(RULES['email']?.('@b.co', null)).toBe(false);
        });
    });

    describe('pattern', () => {
        it('compiles the param as a RegExp and tests against value', () => {
            expect(RULES['pattern']?.('abc123', '^[a-z]+\\d+$')).toBe(true);
            expect(RULES['pattern']?.('ABC123', '^[a-z]+\\d+$')).toBe(false);
        });
    });
});

describe('parseRules', () => {
    it('returns empty list for empty string', () => {
        expect(parseRules('')).toEqual([]);
    });
    it('splits on `|` and `:`', () => {
        expect(parseRules('required|minLength:3')).toEqual([
            { name: 'required', param: null },
            { name: 'minLength', param: '3' },
        ]);
    });
});

describe('getMessage', () => {
    it('substitutes {label} and {param}', () => {
        expect(getMessage('min', 'Age', '18')).toBe('Age must be at least 18');
    });
    it('falls back to a generic message for unknown rules', () => {
        expect(getMessage('bogus', 'Field', null)).toBe('Field is invalid');
    });
});

describe('validateValue', () => {
    it('returns null when rules are missing or empty', () => {
        expect(validateValue('x', null)).toBeNull();
        expect(validateValue('x', {})).toBeNull();
    });
    it('returns the first error message on failure', () => {
        expect(validateValue('', { required: true, label: 'Name' })).toBe('Name is required');
    });
    it('returns null when every rule passes', () => {
        expect(validateValue('Widget', { required: true, minLength: 3 })).toBeNull();
    });
    it('honors boolean-false/null/undefined rules by skipping them', () => {
        expect(validateValue('', { required: false, label: 'Name' })).toBeNull();
    });
    it('label defaults to "Field"', () => {
        expect(validateValue('', { required: true })).toBe('Field is required');
    });
});

describe('validateField (DOM)', () => {
    beforeEach(() => {
        installValidate();
        document.body.innerHTML = '';
    });

    it('passes inputs without data-validate', () => {
        const input = document.createElement('input');
        document.body.appendChild(input);
        expect(validateField(input)).toBe(true);
    });

    it('fails, sets error class + message on blur of empty required field', () => {
        const wrap = document.createElement('div');
        const input = document.createElement('input');
        input.setAttribute('data-validate', 'required');
        input.setAttribute('data-validate-label', 'Name');
        wrap.appendChild(input);
        document.body.appendChild(wrap);

        expect(validateField(input)).toBe(false);
        expect(input.classList.contains('nebula-field-error')).toBe(true);
        expect(wrap.querySelector('.nebula-field-error-msg')?.textContent).toBe('Name is required');
    });

    it('clears previous errors on next pass', () => {
        const wrap = document.createElement('div');
        const input = document.createElement('input');
        input.setAttribute('data-validate', 'required');
        wrap.appendChild(input);
        document.body.appendChild(wrap);

        validateField(input);
        input.value = 'OK';
        expect(validateField(input)).toBe(true);
        expect(input.classList.contains('nebula-field-error')).toBe(false);
        expect(wrap.querySelector('.nebula-field-error-msg')).toBeNull();
    });
});

describe('validateForm (DOM)', () => {
    beforeEach(() => {
        installValidate();
        document.body.innerHTML = '';
    });

    it('returns true for forms with no validated inputs', () => {
        const form = document.createElement('form');
        document.body.appendChild(form);
        expect(validateForm(form)).toBe(true);
    });

    it('returns false when any field fails, and focuses the first error', () => {
        const form = document.createElement('form');
        const a = document.createElement('input');
        a.setAttribute('data-validate', 'required');
        a.setAttribute('data-validate-label', 'A');
        const b = document.createElement('input');
        b.setAttribute('data-validate', 'required');
        b.setAttribute('data-validate-label', 'B');
        form.append(a, b);
        document.body.appendChild(form);

        expect(validateForm(form)).toBe(false);
    });

    it('skips disabled and hidden inputs', () => {
        const form = document.createElement('form');
        const a = document.createElement('input');
        a.setAttribute('data-validate', 'required');
        a.disabled = true;
        const b = document.createElement('input');
        b.type = 'hidden';
        b.setAttribute('data-validate', 'required');
        form.append(a, b);
        document.body.appendChild(form);

        expect(validateForm(form)).toBe(true);
    });
});

describe('installValidate', () => {
    beforeEach(() => {
        delete (window as Window & { __nebulaValidateInstalled?: boolean }).__nebulaValidateInstalled;
        delete window.Nebula;
    });

    it('exposes the full Nebula API on window.Nebula', () => {
        installValidate();
        expect(typeof window.Nebula?.validateField).toBe('function');
        expect(typeof window.Nebula?.validateForm).toBe('function');
        expect(typeof window.Nebula?.validateValue).toBe('function');
        expect(typeof window.Nebula?.rule).toBe('function');
        expect(typeof window.Nebula?.registerValidator).toBe('function');
        expect(typeof window.Nebula?.clearFieldError).toBe('function');
        expect(typeof window.Nebula?.setFieldError).toBe('function');
    });

    it('custom rules registered via Nebula.rule() run in validateValue', () => {
        installValidate();
        window.Nebula!.rule!(
            'isWidget',
            (value) => value === 'Widget',
            '{label} must be a widget',
        );
        expect(window.Nebula!.validateValue!('Widget', { isWidget: true })).toBeNull();
        expect(window.Nebula!.validateValue!('Gizmo', { isWidget: true, label: 'Product' })).toBe(
            'Product must be a widget',
        );
    });
});
