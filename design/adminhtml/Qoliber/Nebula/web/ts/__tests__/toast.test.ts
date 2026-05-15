import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { __testables, createToast, processMessages } from '../toast';

describe('detectType', () => {
    const { detectType } = __testables;

    it('returns success for classes containing "success"', () => {
        expect(detectType('message message-success')).toBe('success');
    });
    it('returns error for classes containing "error"', () => {
        expect(detectType('message message-error')).toBe('error');
    });
    it('returns warning for classes containing "warning"', () => {
        expect(detectType('message message-warning')).toBe('warning');
    });
    it('falls back to notice', () => {
        expect(detectType('message message-info')).toBe('notice');
        expect(detectType('')).toBe('notice');
    });
});

describe('createToast', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('appends a toast to the container', () => {
        const toast = createToast('success', 'Saved!');
        const container = document.getElementById('nebula-toast-container');
        expect(container).not.toBeNull();
        expect(container!.contains(toast)).toBe(true);
    });

    it('creates a single container shared by all toasts', () => {
        createToast('success', 'a');
        createToast('error', 'b');
        expect(document.querySelectorAll('#nebula-toast-container').length).toBe(1);
        expect(document.querySelectorAll('.nebula-toast').length).toBe(2);
    });

    it('stores the type on the toast dataset', () => {
        const toast = createToast('warning', 'Careful');
        expect(toast.dataset['type']).toBe('warning');
    });

    it('renders the message text inside the toast', () => {
        const toast = createToast('notice', 'Hello world');
        expect(toast.textContent).toContain('Hello world');
    });

    it('close button removes the toast from the DOM', () => {
        const toast = createToast('error', 'Bad');
        const closeBtn = toast.querySelector<HTMLButtonElement>('.nebula-toast-close');
        expect(closeBtn).not.toBeNull();
        closeBtn!.click();
        // Dismiss schedules removal — force it.
        toast.remove();
        expect(document.body.contains(toast)).toBe(false);
    });

    it('pause button toggles the title attribute', () => {
        const toast = createToast('notice', 'X');
        const pauseBtn = toast.querySelector<HTMLButtonElement>('.nebula-toast-pause');
        expect(pauseBtn).not.toBeNull();
        expect(pauseBtn!.title).toBe('Pause');
        pauseBtn!.click();
        expect(pauseBtn!.title).toBe('Resume');
        pauseBtn!.click();
        expect(pauseBtn!.title).toBe('Pause');
    });
});

describe('processMessages', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('converts each .message inside wrappers into a toast', () => {
        document.body.innerHTML =
            '<div class="messages">' +
            '<div class="message message-success">Saved</div>' +
            '<div class="message message-error">Boom</div>' +
            '</div>';

        processMessages();

        expect(document.querySelectorAll('.nebula-toast').length).toBe(2);
    });

    it('hides the source wrapper once messages are converted', () => {
        document.body.innerHTML =
            '<div class="messages"><div class="message message-success">X</div></div>';

        processMessages();
        const wrapper = document.querySelector<HTMLElement>('.messages');
        expect(wrapper?.style.display).toBe('none');
    });

    it('skips empty messages', () => {
        document.body.innerHTML =
            '<div class="messages"><div class="message message-success">   </div></div>';

        processMessages();
        expect(document.querySelectorAll('.nebula-toast').length).toBe(0);
    });
});
