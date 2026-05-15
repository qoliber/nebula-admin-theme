/**
 * Tiny shims for the handful of legacy admin globals that vendor inline
 * `onclick`/`onchange` strings still call. We don't load Prototype.js or
 * the legacy admin shell, so any unguarded reference throws ReferenceError
 * and the click does nothing. Define each only if missing so we never clash
 * with a real script that arrives later.
 *
 * Covers (so far):
 *   setLocation(url)  — vanilla one-liner used by Sales > Delivery Methods >
 *                       Table Rates → Export CSV button onclick.
 *   $(id)             — Prototype.js getElementById shortcut. Native
 *                       Element already exposes .value / .checked etc.,
 *                       so callers like `$('id').value` work transparently.
 */

interface LegacyWindow {
    setLocation?: (url: string) => void;
    $?: (id: string) => HTMLElement | null;
}

export function installVendorCompat(): void {
    const w = window as unknown as LegacyWindow;

    if (typeof w.setLocation !== 'function') {
        w.setLocation = (url: string): void => { window.location.href = url; };
    }

    if (typeof w.$ !== 'function') {
        w.$ = (id: string): HTMLElement | null => document.getElementById(id);
    }
}
