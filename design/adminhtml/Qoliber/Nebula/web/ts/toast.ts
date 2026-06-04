/**
 * Nebula Toast — Auto-converts Magento admin messages into slide-down toasts
 * with timer bar, pause on hover, and manual pause button.
 */

import type { ToastType } from './types';

const TOAST_DURATION = 5000; // ms
const ANIMATION_DURATION = 300; // ms

interface ToastTypeStyle {
    bg: string;
    icon: string;
}

const TYPE_STYLES: Record<ToastType, ToastTypeStyle> = {
    success: {
        bg: '#16a34a',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
    },
    error: {
        bg: '#dc2626',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>',
    },
    warning: {
        bg: '#d97706',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
    },
    notice: {
        bg: '#2563eb',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>',
    },
};

const createToastContainer = (): HTMLElement => {
    const existing = document.getElementById('nebula-toast-container');
    if (existing) return existing;

    const container = document.createElement('div');
    container.id = 'nebula-toast-container';
    container.setAttribute('role', 'region');
    container.setAttribute('aria-label', 'Notifications');
    container.style.cssText =
        'position:fixed;top:0;left:0;right:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;pointer-events:none;';
    document.body.appendChild(container);
    return container;
};

export const createToast = (type: ToastType, message: string): HTMLElement => {
    const style = TYPE_STYLES[type] ?? TYPE_STYLES.notice;
    const container = createToastContainer();

    const toast = document.createElement('div');
    toast.className = 'nebula-toast';
    toast.dataset['type'] = type;
    toast.style.cssText =
        'pointer-events:auto;width:100%;max-width:600px;margin-top:8px;transform:translateY(-100%);opacity:0;transition:transform ' +
        ANIMATION_DURATION +
        'ms ease-out, opacity ' +
        ANIMATION_DURATION +
        'ms ease-out;';

    toast.innerHTML =
        '' +
        '<div style="background:' +
        style.bg +
        ';color:#f8fafc !important;border-radius:12px;padding:0;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0,0,0,.2),0 8px 10px -6px rgba(0,0,0,.1);">' +
        '  <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;">' +
        '    <svg style="width:20px;height:20px;color:#f8fafc !important;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
        style.icon +
        '</svg>' +
        '    <span class="nebula-toast-message" style="flex:1;color:#f8fafc !important;font-size:14px;font-weight:600;line-height:1.4;">' +
        '</span>' +
        '    <button class="nebula-toast-pause" title="Pause" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">' +
        '      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>' +
        '    </button>' +
        '    <button class="nebula-toast-close" title="Close" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">' +
        '      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>' +
        '    </button>' +
        '  </div>' +
        '  <div class="nebula-toast-timer" style="height:3px;background:rgba(255,255,255,.4);border-radius:0 0 12px 12px;">' +
        '    <div class="nebula-toast-timer-bar" style="height:100%;background:rgba(255,255,255,.8);border-radius:0 0 12px 12px;width:100%;transition:width linear;"></div>' +
        '  </div>' +
        '</div>';

    // The chrome above is static developer markup; the message is the only
    // dynamic value, so inject it as text — never as HTML — to prevent XSS
    // from admin messages that echo user-controlled input.
    const messageEl = toast.querySelector<HTMLElement>('.nebula-toast-message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    container.appendChild(toast);

    const hoverButtons = toast.querySelectorAll<HTMLButtonElement>(
        '.nebula-toast-pause, .nebula-toast-close',
    );
    hoverButtons.forEach((btn) => {
        btn.addEventListener('mouseover', () => {
            btn.style.color = 'white';
        });
        btn.addEventListener('mouseout', () => {
            btn.style.color = 'rgba(255,255,255,.7)';
        });
    });

    // Slide in.
    const timerBar = toast.querySelector<HTMLElement>('.nebula-toast-timer-bar')!;
    let paused = false;
    let remaining = TOAST_DURATION;
    let startTime = Date.now();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
            startTime = Date.now();
        });
    });

    timerBar.style.transitionDuration = TOAST_DURATION + 'ms';
    requestAnimationFrame(() => {
        timerBar.style.width = '0%';
    });

    const dismiss = (): void => {
        toast.style.transform = 'translateY(-100%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, ANIMATION_DURATION);
    };

    let timer: ReturnType<typeof setTimeout> = setTimeout(dismiss, TOAST_DURATION);

    toast.addEventListener('mouseenter', () => {
        if (!paused) {
            remaining -= Date.now() - startTime;
            clearTimeout(timer);
            timerBar.style.transitionDuration = '0ms';
            timerBar.style.width = (remaining / TOAST_DURATION) * 100 + '%';
        }
    });

    toast.addEventListener('mouseleave', () => {
        if (!paused) {
            startTime = Date.now();
            timerBar.style.transitionDuration = remaining + 'ms';
            requestAnimationFrame(() => {
                timerBar.style.width = '0%';
            });
            timer = setTimeout(dismiss, remaining);
        }
    });

    const pauseBtn = toast.querySelector<HTMLButtonElement>('.nebula-toast-pause')!;
    const pauseIcon =
        '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>';
    const playIcon =
        '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>';

    pauseBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (paused) {
            paused = false;
            pauseBtn.innerHTML = pauseIcon;
            pauseBtn.title = 'Pause';
            startTime = Date.now();
            timerBar.style.transitionDuration = remaining + 'ms';
            requestAnimationFrame(() => {
                timerBar.style.width = '0%';
            });
            timer = setTimeout(dismiss, remaining);
        } else {
            paused = true;
            pauseBtn.innerHTML = playIcon;
            pauseBtn.title = 'Resume';
            remaining -= Date.now() - startTime;
            clearTimeout(timer);
            timerBar.style.transitionDuration = '0ms';
            timerBar.style.width = (remaining / TOAST_DURATION) * 100 + '%';
        }
    });

    toast.querySelector<HTMLButtonElement>('.nebula-toast-close')!.addEventListener('click', (e) => {
        e.stopPropagation();
        clearTimeout(timer);
        dismiss();
    });

    return toast;
};

const detectType = (classNames: string): ToastType => {
    if (classNames.indexOf('success') !== -1) return 'success';
    if (classNames.indexOf('error') !== -1) return 'error';
    if (classNames.indexOf('warning') !== -1) return 'warning';
    return 'notice';
};

export const processMessages = (): void => {
    const wrappers = document.querySelectorAll<HTMLElement>(
        '.nebula-messages .messages, .page.messages .messages, .messages',
    );
    wrappers.forEach((wrapper) => {
        const msgs = wrapper.querySelectorAll<HTMLElement>('.message');
        msgs.forEach((msg) => {
            const text = (msg.textContent ?? '').trim();
            if (!text) return;
            const type = detectType(msg.className);
            createToast(type, text);
        });
        const parent = wrapper.closest<HTMLElement>('.nebula-messages');
        if (parent) {
            parent.style.display = 'none';
        } else {
            wrapper.style.display = 'none';
        }
    });
};

/**
 * Install the toast processing + observer. Exposes `window.nebulaToast`.
 * Safe to call multiple times.
 *
 * The MutationObserver is scoped to the admin's main content area (or
 * document.body as a fallback) rather than blanket-watching the entire
 * document. The previous unscoped observer fired the callback on every
 * mutation across the whole admin page, queuing a setTimeout each time
 * — measurable cost in long-lived admin sessions. The scoped version
 * still catches AJAX-injected `.messages` elements because every legit
 * Magento admin page renders messages inside the main content region.
 *
 * The setTimeout is also debounced so rapid bursts of mutations
 * (e.g. when a snippet renders dozens of nested elements) collapse to
 * one processMessages call per quiet window.
 */
export function installToast(): void {
    const g = window as Window & {
        __nebulaToastInstalled?: boolean;
        __nebulaToastObserver?: MutationObserver;
    };
    if (g.__nebulaToastInstalled) return;
    g.__nebulaToastInstalled = true;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', processMessages);
    } else {
        processMessages();
    }

    let scheduled = false;
    const scheduleProcess = (): void => {
        if (scheduled) return;
        scheduled = true;
        setTimeout(() => {
            scheduled = false;
            processMessages();
        }, 50);
    };

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of Array.from(mutation.addedNodes)) {
                if (node.nodeType !== 1) continue;
                const el = node as Element;
                if (el.classList?.contains('messages') || el.querySelector?.('.messages')) {
                    scheduleProcess();
                    return;
                }
            }
        }
    });

    const target = document.querySelector<HTMLElement>('main, .nebula-content, #anchor-content')
        ?? document.body;
    observer.observe(target, { childList: true, subtree: true });
    g.__nebulaToastObserver = observer;

    window.nebulaToast = (type: ToastType, message: string): void => {
        createToast(type, message);
    };
}

/**
 * Disconnect the toast MutationObserver. Tests + long-lived single-page
 * admin shells can call this to release the observer before re-installing.
 */
export function disconnectToastObserver(): void {
    const g = window as Window & {
        __nebulaToastInstalled?: boolean;
        __nebulaToastObserver?: MutationObserver;
    };
    if (g.__nebulaToastObserver) {
        g.__nebulaToastObserver.disconnect();
        delete g.__nebulaToastObserver;
    }
    g.__nebulaToastInstalled = false;
}

export const __testables = { detectType, TYPE_STYLES, TOAST_DURATION };
