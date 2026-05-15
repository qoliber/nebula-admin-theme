/**
 * nebulaGrid — Alpine component for the Nebula admin grid template.
 *
 * Owns: row selection, filter manipulation on URL, mass-action submission.
 * No domain logic about grid definitions — it consumes a pre-rendered table.
 */

import type { GridConfig } from './types';

interface GridState {
    selected: string[];
    loading: boolean;
    confirmAction: { url: string; label: string } | null;
    pendingFilters: Record<string, string>;
    toggleSelectAll(checked: boolean): void;
    applyAllFilters(): void;
    confirmMassAction(url: string, label: string): void;
    executeMassAction(): void;
    submitMassAction(url: string): void;
    $root?: HTMLElement;
}

export function createGrid(config: GridConfig): GridState {
    let pendingFilters: Record<string, string> = {};
    try {
        pendingFilters = JSON.parse(config.filters ?? '{}') as Record<string, string>;
    } catch {
        pendingFilters = {};
    }

    return {
        selected: [],
        loading: false,
        confirmAction: null,
        pendingFilters,

        toggleSelectAll(this: GridState, checked: boolean): void {
            if (!this.$root) {
                this.selected = [];
                return;
            }
            if (checked) {
                this.selected = Array.from(
                    this.$root.querySelectorAll<HTMLInputElement>(
                        'tbody input[type="checkbox"][value]',
                    ),
                ).map((el) => el.value);
            } else {
                this.selected = [];
            }
        },

        applyAllFilters(this: GridState): void {
            const url = new URL(window.location.href);
            Array.from(url.searchParams.keys()).forEach((key) => {
                if (key.startsWith('filters[')) {
                    url.searchParams.delete(key);
                }
            });
            Object.entries(this.pendingFilters).forEach(([field, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set('filters[' + field + ']', String(value));
                }
            });
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        confirmMassAction(this: GridState, url: string, label: string): void {
            if (this.selected.length === 0) return;
            this.confirmAction = { url, label };
        },

        executeMassAction(this: GridState): void {
            if (!this.confirmAction) return;
            this.submitMassAction(this.confirmAction.url);
            this.confirmAction = null;
        },

        submitMassAction(this: GridState, url: string): void {
            if (this.selected.length === 0) return;

            this.loading = true;

            try {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;

                const fkInput = document.createElement('input');
                fkInput.type = 'hidden';
                fkInput.name = 'form_key';
                fkInput.value = config.formKey;
                form.appendChild(fkInput);

                this.selected.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error('Mass action error:', e);
                this.loading = false;
            }
        },
    };
}

export function registerGrid(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaGrid', (config: GridConfig) => createGrid(config));
    });
}
