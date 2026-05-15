/**
 * Nebula Confirm Dialog.
 *
 * Promise-based replacement for window.confirm(). Mounted once globally via
 * root.phtml (see components/confirm-dialog.phtml). State lives in the
 * Alpine.store('nebulaConfirm') instance returned by createConfirmStore().
 *
 *   await window.Nebula.confirm({ title, message, danger: true });
 *
 * Resolves true on confirm, false on cancel / Esc / backdrop / concurrent call.
 */

import type { ConfirmOptions } from './types';
import { installFocusTrap } from './modal';

interface ResolvedOptions extends Required<Omit<ConfirmOptions, 'danger'>> {
    danger: boolean;
}

export interface ConfirmStore {
    open: boolean;
    options: ResolvedOptions | null;
    show(options: ConfirmOptions): Promise<boolean>;
    confirm(): void;
    cancel(): void;
}

function resolveOptions(options: ConfirmOptions): ResolvedOptions {
    const danger = options.danger === true;
    return {
        title: options.title,
        message: options.message,
        confirmText: options.confirmText ?? (danger ? 'Delete' : 'Confirm'),
        cancelText: options.cancelText ?? 'Cancel',
        danger,
    };
}

export function createConfirmStore(): ConfirmStore {
    let resolver: ((value: boolean) => void) | null = null;

    const store: ConfirmStore = {
        open: false,
        options: null,
        show(options: ConfirmOptions): Promise<boolean> {
            if (this.open) {
                return Promise.resolve(false);
            }
            this.options = resolveOptions(options);
            this.open = true;
            return new Promise<boolean>((resolve) => {
                resolver = resolve;
            });
        },
        confirm(): void {
            this.open = false;
            this.options = null;
            const r = resolver;
            resolver = null;
            r?.(true);
        },
        cancel(): void {
            this.open = false;
            this.options = null;
            const r = resolver;
            resolver = null;
            r?.(false);
        },
    };

    return store;
}

/**
 * Wire createConfirmStore() into Alpine + expose window.Nebula.confirm().
 *
 * Called once from nebula-core.ts. The store is registered inside the
 * `alpine:init` handler so the dialog template (x-data="$store.nebulaConfirm")
 * can bind to it.
 */
export function installConfirm(): ConfirmStore {
    const store = createConfirmStore();
    let trapTeardown: (() => void) | null = null;
    let previousFocus: HTMLElement | null = null;

    function findPanel(): HTMLElement | null {
        const cancel = document.querySelector<HTMLElement>('[data-nebula-confirm-cancel]');
        return cancel?.closest('[role="dialog"]') ?? cancel?.parentElement ?? null;
    }

    function onOpen(): void {
        previousFocus = document.activeElement as HTMLElement | null;
        const panel = findPanel();
        if (!panel) {
            return;
        }
        trapTeardown = installFocusTrap(panel);

        const focusTarget = store.options?.danger
            ? panel.querySelector<HTMLElement>('[data-nebula-confirm-cancel]')
            : panel.querySelector<HTMLElement>('[data-nebula-confirm-confirm]');
        // queueMicrotask gives Alpine time to flip x-show off display:none before focusing.
        queueMicrotask(() => focusTarget?.focus());
    }

    function onClose(): void {
        trapTeardown?.();
        trapTeardown = null;
        previousFocus?.focus();
        previousFocus = null;
    }

    // Keep raw method references (unbound). Wrappers call them with .call(this)
    // so when the wrapper is invoked via the Alpine-reactive proxy, `this` is
    // the proxy → all mutations propagate through reactivity. Binding to the
    // raw `store` here would lock `this` to the original object and silently
    // bypass Alpine's effect system — symptom: store.open updates but x-show
    // never re-fires.
    const baseShow = store.show;
    const baseConfirm = store.confirm;
    const baseCancel = store.cancel;

    store.show = function (options) {
        const wasOpen = this.open;
        const p = baseShow.call(this, options);
        if (!wasOpen && this.open) {
            onOpen();
        }
        return p;
    };
    store.confirm = function () {
        const wasOpen = this.open;
        baseConfirm.call(this);
        if (wasOpen) {
            onClose();
        }
    };
    store.cancel = function () {
        const wasOpen = this.open;
        baseCancel.call(this);
        if (wasOpen) {
            onClose();
        }
    };

    document.addEventListener('alpine:init', () => {
        window.Alpine.store('nebulaConfirm', store);
    });

    window.Nebula = Object.assign({}, window.Nebula ?? {}, {
        confirm(options: ConfirmOptions): Promise<boolean> {
            // Route through the reactive store proxy — calling show() on the
            // raw `store` reference mutates the underlying object directly
            // and bypasses Alpine's reactivity, so x-show never re-fires.
            // Fall back to the raw store only if Alpine hasn't booted yet
            // (window.Nebula.confirm is sometimes called from page scripts
            // that race with alpine:init).
            const reactive = (window.Alpine?.store('nebulaConfirm') as ConfirmStore | undefined) ?? store;
            return reactive.show(options);
        },
    });

    return store;
}
