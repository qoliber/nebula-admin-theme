/**
 * Nebula Form — the `nebulaForm` Alpine component used by both Simple and
 * EAV form shells. Owns save orchestration:
 *   1. Run the single validator (`Nebula.validateForm`) on the DOM inputs.
 *   2. Let every registered Alpine model validate itself too (optional hook).
 *   3. Serialize all models into hidden inputs via `nebulaModels.serializeAll`.
 *   4. Submit the form via requestSubmit().
 *
 * No validation rules live in this file — they all live in validate.ts.
 */

import type { NebulaModelStore } from './models';

interface FormState {
    saving: boolean;
    saveAndContinue: boolean;
    saveWithBack(form: HTMLFormElement | null, back: string | boolean): void;
}

export function createForm(): FormState {
    return {
        saving: false,
        saveAndContinue: false,

        saveWithBack(this: FormState, form: HTMLFormElement | null, back: string | boolean): void {
            if (!form) {
                // eslint-disable-next-line no-console
                console.error('Nebula: form element not found');
                return;
            }

            if (window.Nebula && typeof window.Nebula.validateForm === 'function') {
                if (!window.Nebula.validateForm(form)) {
                    this.saving = false;
                    this.saveAndContinue = false;
                    return;
                }
            }

            const models = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
            if (models && typeof models.validateAll === 'function' && !models.validateAll()) {
                this.saving = false;
                this.saveAndContinue = false;
                return;
            }

            this.saving = true;
            this.saveAndContinue = Boolean(back);

            if (window.Nebula?.showSaveLoader) {
                window.Nebula.showSaveLoader();
            }

            if (models && typeof models.serializeAll === 'function') {
                models.serializeAll(form);
            }

            // Reconcile the back hidden input with the requested mode.
            //   - non-empty string  → set (e.g. 'continue', 'duplicate')
            //   - true              → set to '1' (boolean-style controllers)
            //   - false / '' / null → REMOVE the input entirely so
            //     controllers that do `$data['back'] ?? 'close'` (CMS Block/Page)
            //     fall through to their default instead of seeing an empty
            //     string that matches none of {continue, close, duplicate}.
            const existing = form.querySelector<HTMLInputElement>('input[name="back"]');
            const shouldSend = typeof back === 'string' ? back !== '' : Boolean(back);
            if (shouldSend) {
                const backInput = existing ?? Object.assign(document.createElement('input'), {
                    type: 'hidden',
                    name: 'back',
                });
                backInput.value = typeof back === 'string' ? back : '1';
                if (!existing) form.appendChild(backInput);
            } else if (existing) {
                existing.remove();
            }

            form.requestSubmit();
        },
    };
}

/**
 * Register the `nebulaForm` Alpine component + the Ctrl/Cmd-S save shortcut
 * and the full-page save loader on `window.Nebula`.
 */
export function registerForm(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaForm', () => createForm());
    });

    // Ctrl/Cmd+S save shortcut.
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const form = document.querySelector<HTMLElement>(
                '[x-ref="nebulaEavForm"], [x-ref="nebulaForm"]',
            );
            if (form) {
                const wrapper = form.closest<HTMLElement>('[x-data]');
                const saveBtn = wrapper?.querySelector<HTMLButtonElement>(
                    'button[data-nebula-save-action="saveWithBack"]',
                );
                if (saveBtn) saveBtn.click();
            }
        }
    });

    installSaveLoader();
}

function installSaveLoader(): void {
    const g = window as Window & { __nebulaSaveLoaderInstalled?: boolean };
    if (g.__nebulaSaveLoaderInstalled) return;
    g.__nebulaSaveLoaderInstalled = true;

    let overlay: HTMLElement | null = null;

    window.Nebula = Object.assign({}, window.Nebula ?? {}, {
        showSaveLoader(): void {
            if (overlay) return;

            overlay = document.createElement('div');
            overlay.id = 'nebula-save-overlay';
            overlay.innerHTML =
                '<div style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.8);backdrop-filter:blur(2px)">' +
                '<div style="text-align:center">' +
                '<svg style="width:48px;height:48px;margin:0 auto 16px;animation:nebula-spin 1s linear infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                '</svg>' +
                '<p style="font-size:14px;font-weight:500;color:#374151">Saving...</p>' +
                '</div>' +
                '</div>';
            document.body.appendChild(overlay);

            if (!document.getElementById('nebula-spin-style')) {
                const s = document.createElement('style');
                s.id = 'nebula-spin-style';
                s.textContent = '@keyframes nebula-spin { to { transform: rotate(360deg) } }';
                document.head.appendChild(s);
            }
        },
        hideSaveLoader(): void {
            if (overlay) {
                overlay.remove();
                overlay = null;
            }
        },
    });

    window.addEventListener('pageshow', () => window.Nebula?.hideSaveLoader?.());
}
