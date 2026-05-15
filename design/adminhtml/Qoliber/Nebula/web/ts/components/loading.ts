/**
 * `<x-nebula-loading>` — an accessible busy indicator.
 *
 * Usage (markup):
 *   <div x-data="nebulaLoading({ size: 'md', label: 'Saving…' })"
 *        x-show="active"
 *        role="status"
 *        :aria-label="label">
 *       <span class="nebula-spinner" :class="sizeClass"></span>
 *       <span x-text="label" class="nebula-sr-only"></span>
 *   </div>
 *
 * Public API (via Alpine root):
 *   start(label?: string): void
 *   stop(): void
 *   toggle(active: boolean): void
 *
 * Chrome lives in app.css (`.nebula-spinner`, `.nebula-sr-only`). The component
 * exposes a typed state machine around "is-busy".
 */

export type NebulaLoadingSize = 'sm' | 'md' | 'lg';

export interface NebulaLoadingConfig {
    size?: NebulaLoadingSize;
    label?: string;
    active?: boolean;
    /** Minimum visible time (ms). Prevents flicker when an async op finishes instantly. */
    minVisible?: number;
}

interface NebulaLoadingComponent {
    active: boolean;
    label: string;
    size: NebulaLoadingSize;
    minVisible: number;
    sizeClass: string;
    _shownAt: number;
    start(label?: string): void;
    stop(): void;
    toggle(active: boolean): void;
    init(): void;
}

const SIZE_CLASSES: Record<NebulaLoadingSize, string> = {
    sm: 'nebula-spinner--sm',
    md: 'nebula-spinner--md',
    lg: 'nebula-spinner--lg',
};

export function registerLoadingComponent(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaLoading', (config: NebulaLoadingConfig = {}): NebulaLoadingComponent => {
            const size: NebulaLoadingSize = config.size ?? 'md';

            return {
                active: config.active ?? false,
                label: config.label ?? 'Loading…',
                size,
                sizeClass: SIZE_CLASSES[size],
                minVisible: typeof config.minVisible === 'number' ? config.minVisible : 200,
                _shownAt: 0,
                init(): void {
                    if (this.active) {
                        this._shownAt = Date.now();
                    }
                },
                start(label?: string): void {
                    if (typeof label === 'string') {
                        this.label = label;
                    }
                    this.active = true;
                    this._shownAt = Date.now();
                },
                stop(): void {
                    const elapsed = Date.now() - this._shownAt;
                    const wait = Math.max(0, this.minVisible - elapsed);
                    if (wait === 0) {
                        this.active = false;
                        return;
                    }
                    window.setTimeout(() => {
                        this.active = false;
                    }, wait);
                },
                toggle(active: boolean): void {
                    if (active) {
                        this.start();
                    } else {
                        this.stop();
                    }
                },
            };
        });
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}

