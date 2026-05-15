/**
 * Nebula Modal Mixin - Composable modal/wizard behavior for Alpine.js components.
 *
 * Usage: spread into any Alpine.data component to add modal capabilities.
 */

import type { ModalConfig, ModalSize, ModalState } from './types';

const sizeClasses: Record<ModalSize, string> = {
    sm: 'max-w-md',
    md: 'max-w-xl',
    lg: 'max-w-3xl',
    xl: 'max-w-5xl',
    full: 'max-w-7xl',
};

/**
 * Focusable selectors used by the focus-trap helper. Mirrors WCAG's
 * "focusable disabled-aware" set.
 */
const FOCUSABLE_SELECTOR =
    'a[href], area[href], input:not([disabled]):not([type="hidden"]), '
    + 'select:not([disabled]), textarea:not([disabled]), '
    + 'button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex^="-"]), '
    + '[contenteditable=true]';

/**
 * Trap Tab navigation inside a modal panel. Returns a teardown function
 * that removes the listener — call it from closeModal so the listener
 * doesn't accumulate over many open/close cycles.
 */
export function installFocusTrap(panel: HTMLElement): () => void {
    const handler = (event: KeyboardEvent): void => {
        if (event.key !== 'Tab') return;
        const focusables = Array.from(
            panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
        ).filter((el) => el.offsetParent !== null || el === document.activeElement);

        if (focusables.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusables[0]!;
        const last = focusables[focusables.length - 1]!;
        const active = document.activeElement as HTMLElement | null;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };
    panel.addEventListener('keydown', handler);
    return () => panel.removeEventListener('keydown', handler);
}

/**
 * Create modal mixin properties to spread into an Alpine.data component.
 */
export function createModal(config: ModalConfig = {}): ModalState {
    const steps = config.steps ?? [];
    const size: ModalSize = config.size ?? 'lg';
    const onOpen = config.onOpen ?? null;
    const onClose = config.onClose ?? null;

    // Closure-scoped state for accessibility plumbing — survives openModal /
    // closeModal cycles without polluting the public ModalState surface.
    let previouslyFocused: HTMLElement | null = null;
    let trapTeardown: (() => void) | null = null;

    return {
        modalOpen: false,
        modalStep: 0,
        modalSteps: steps,
        modalSize: size,
        modalTitle: config.title ?? '',

        openModal(this: ModalState): void {
            this.modalStep = 0;
            this.modalOpen = true;

            // Remember which element opened the modal so we can return focus
            // to it on close — WCAG 2.4.3 (Focus Order) requires it.
            previouslyFocused = (document.activeElement as HTMLElement | null);

            if (onOpen) {
                onOpen.call(this);
            }

            const self = this;
            this.$nextTick?.(() => {
                if (self.$el) {
                    self.$el.dispatchEvent(new CustomEvent('nebula-modal:open', { bubbles: true }));
                }
                const panel = self.$el?.querySelector<HTMLElement>('[x-show="modalOpen"]');
                const focusTarget = panel?.querySelector<HTMLElement>(
                    'input, select, textarea, button, [tabindex]:not([tabindex^="-"])',
                );
                if (focusTarget) focusTarget.focus();

                // Install focus trap — Tab + Shift+Tab cycle inside the panel
                // so keyboard users can't escape the modal accidentally.
                if (panel && trapTeardown === null) {
                    trapTeardown = installFocusTrap(panel);
                }
            });
        },

        closeModal(this: ModalState): void {
            this.modalOpen = false;

            if (trapTeardown) {
                trapTeardown();
                trapTeardown = null;
            }

            if (onClose) {
                onClose.call(this);
            }

            if (this.$el) {
                this.$el.dispatchEvent(new CustomEvent('nebula-modal:close', { bubbles: true }));
            }

            // Restore focus to the element that opened the modal. Wrapped in
            // requestAnimationFrame so Alpine's x-show transition has finished
            // hiding the panel before the focus jumps back.
            const previous = previouslyFocused;
            previouslyFocused = null;
            if (previous && typeof previous.focus === 'function') {
                requestAnimationFrame(() => {
                    if (document.contains(previous)) previous.focus();
                });
            }
        },

        nextModalStep(this: ModalState, canProceed?: (this: ModalState) => boolean): void {
            if (canProceed && !canProceed.call(this)) return;
            if (this.modalStep < this.modalSteps.length - 1) {
                this.modalStep++;
            }
        },

        prevModalStep(this: ModalState): void {
            if (this.modalStep > 0) {
                this.modalStep--;
            }
        },

        goToModalStep(this: ModalState, index: number): void {
            if (index >= 0 && index < this.modalSteps.length) {
                this.modalStep = index;
            }
        },

        get modalSizeClass(): string {
            return sizeClasses[this.modalSize] ?? sizeClasses.lg;
        },

        get isFirstModalStep(): boolean {
            return this.modalStep === 0;
        },

        get isLastModalStep(): boolean {
            return this.modalSteps.length === 0 || this.modalStep === this.modalSteps.length - 1;
        },

        get hasModalSteps(): boolean {
            return this.modalSteps.length > 0;
        },

        get currentModalStepLabel(): string {
            if (this.modalSteps.length === 0) return '';
            return this.modalSteps[this.modalStep] ?? '';
        },

        get modalStepCount(): number {
            return this.modalSteps.length;
        },
    };
}

/**
 * Install the modal mixin on `window.Nebula.modal`. Idempotent.
 */
export function installModal(): void {
    const current = (window.Nebula ?? {}) as Partial<import('./types').NebulaGlobal>;
    window.Nebula = Object.assign({}, current, {
        modal: (config?: ModalConfig) => createModal(config ?? {}) as unknown as Record<string, unknown>,
    }) as import('./types').NebulaGlobal;
}
