/**
 * `<x-nebula-error>` — a dismissable inline error banner.
 *
 * Usage (markup):
 *   <div x-data="nebulaError({ message: 'Save failed. Please retry.' })" x-show="visible">
 *       <template x-ref="body">
 *           <p x-text="message"></p>
 *       </template>
 *   </div>
 *
 * Public API (via Alpine root):
 *   show(message?: string): void
 *   hide(): void
 *   setMessage(message: string): void
 *
 * Headless: Nebula ships baseline styles in app.css (`.nebula-inline-error`).
 * Consumers are free to replace the template — the component exposes state, not chrome.
 */

export interface NebulaErrorConfig {
    message?: string;
    visible?: boolean;
    /** Auto-hide after N ms; 0 disables auto-hide. */
    autoHide?: number;
}

interface NebulaErrorComponent {
    message: string;
    visible: boolean;
    autoHide: number;
    _timer: number | null;
    show(message?: string): void;
    hide(): void;
    setMessage(message: string): void;
    init(): void;
}

export function registerErrorComponent(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaError', (config: NebulaErrorConfig = {}): NebulaErrorComponent => ({
            message: config.message ?? '',
            visible: config.visible ?? Boolean(config.message),
            autoHide: typeof config.autoHide === 'number' ? config.autoHide : 0,
            _timer: null,
            init(): void {
                if (this.visible && this.autoHide > 0) {
                    this._scheduleHide();
                }
            },
            show(message?: string): void {
                if (typeof message === 'string') {
                    this.message = message;
                }
                this.visible = true;
                this._scheduleHide();
            },
            hide(): void {
                this.visible = false;
                if (this._timer !== null) {
                    window.clearTimeout(this._timer);
                    this._timer = null;
                }
            },
            setMessage(message: string): void {
                this.message = message;
            },
            _scheduleHide(this: NebulaErrorComponent & { _scheduleHide(): void }): void {
                if (this.autoHide <= 0) return;
                if (this._timer !== null) {
                    window.clearTimeout(this._timer);
                }
                this._timer = window.setTimeout(() => this.hide(), this.autoHide);
            },
        } as NebulaErrorComponent & { _scheduleHide(): void }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}

