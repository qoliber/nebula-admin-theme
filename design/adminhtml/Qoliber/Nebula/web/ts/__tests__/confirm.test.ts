import { beforeEach, describe, expect, it } from 'vitest';
import { createConfirmStore } from '../confirm';

describe('createConfirmStore', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('starts closed with no pending resolver', () => {
        const store = createConfirmStore();
        expect(store.open).toBe(false);
        expect(store.options).toBeNull();
    });

    it('show() opens the dialog and returns a Promise', () => {
        const store = createConfirmStore();
        const p = store.show({ title: 't', message: 'm' });
        expect(p).toBeInstanceOf(Promise);
        expect(store.open).toBe(true);
        expect(store.options?.title).toBe('t');
    });

    it('confirm() resolves the Promise with true and closes', async () => {
        const store = createConfirmStore();
        const p = store.show({ title: 't', message: 'm' });
        store.confirm();
        await expect(p).resolves.toBe(true);
        expect(store.open).toBe(false);
        expect(store.options).toBeNull();
    });

    it('cancel() resolves with false and closes', async () => {
        const store = createConfirmStore();
        const p = store.show({ title: 't', message: 'm' });
        store.cancel();
        await expect(p).resolves.toBe(false);
        expect(store.open).toBe(false);
    });

    it('concurrent show() rejects the new call and leaves the open dialog alone', async () => {
        const store = createConfirmStore();
        const first = store.show({ title: 'a', message: 'a' });
        const second = store.show({ title: 'b', message: 'b' });
        await expect(second).resolves.toBe(false);
        expect(store.open).toBe(true);
        expect(store.options?.title).toBe('a');
        store.confirm();
        await expect(first).resolves.toBe(true);
    });

    it('applies danger defaults: cancelText="Cancel", confirmText="Delete"', () => {
        const store = createConfirmStore();
        store.show({ title: 't', message: 'm', danger: true });
        expect(store.options?.confirmText).toBe('Delete');
        expect(store.options?.cancelText).toBe('Cancel');
        expect(store.options?.danger).toBe(true);
    });

    it('applies non-danger defaults: cancelText="Cancel", confirmText="Confirm"', () => {
        const store = createConfirmStore();
        store.show({ title: 't', message: 'm' });
        expect(store.options?.confirmText).toBe('Confirm');
        expect(store.options?.cancelText).toBe('Cancel');
        expect(store.options?.danger).toBeFalsy();
    });

    it('preserves caller-provided button copy', () => {
        const store = createConfirmStore();
        store.show({ title: 't', message: 'm', confirmText: 'Remove', cancelText: 'Back' });
        expect(store.options?.confirmText).toBe('Remove');
        expect(store.options?.cancelText).toBe('Back');
    });
});

import { installConfirm } from '../confirm';

describe('installConfirm focus behavior', () => {
    let store: ReturnType<typeof installConfirm>;

    beforeEach(() => {
        document.body.innerHTML = `
            <button id="trigger">Open</button>
            <div data-testid="confirm-root" role="dialog">
                <button data-nebula-confirm-cancel>Cancel</button>
                <button data-nebula-confirm-confirm>Confirm</button>
            </div>
        `;
        // Fresh window.Nebula slot each test.
        delete (window as unknown as { Nebula?: Record<string, unknown> }).Nebula;
        store = installConfirm();
    });

    it('exposes window.Nebula.confirm as a function', () => {
        expect(typeof (window as unknown as { Nebula: { confirm: unknown } }).Nebula.confirm).toBe('function');
    });

    it('autofocuses the confirm button by default', async () => {
        const trigger = document.getElementById('trigger') as HTMLButtonElement;
        trigger.focus();
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const p = (window as any).Nebula.confirm({ title: 't', message: 'm' });
        await Promise.resolve(); // let microtask scheduler flush autofocus
        const confirmBtn = document.querySelector<HTMLButtonElement>('[data-nebula-confirm-confirm]');
        expect(document.activeElement).toBe(confirmBtn);
        // Resolve the dialog so the test cleans up.
        store.confirm();
        await p;
    });

    it('autofocuses cancel button when danger: true', async () => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const p = (window as any).Nebula.confirm({ title: 't', message: 'm', danger: true });
        await Promise.resolve();
        const cancelBtn = document.querySelector<HTMLButtonElement>('[data-nebula-confirm-cancel]');
        expect(document.activeElement).toBe(cancelBtn);
        store.cancel();
        await p;
    });

    it('restores focus to the previously-focused element on close', async () => {
        const trigger = document.getElementById('trigger') as HTMLButtonElement;
        trigger.focus();
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const p = (window as any).Nebula.confirm({ title: 't', message: 'm' });
        await Promise.resolve();
        store.cancel();
        await p;
        expect(document.activeElement).toBe(trigger);
    });
});
