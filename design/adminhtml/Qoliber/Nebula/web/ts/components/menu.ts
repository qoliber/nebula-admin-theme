/**
 * Nebula admin menu Alpine components.
 *
 * nebulaMenuSidebar — drives the collapsible sidebar navigation with
 *   section open/close, pin/unpin, drag-to-reorder pins, and sidebar
 *   collapse integration.
 *
 * nebulaMenuTop — stub for the horizontal top navigation. The top menu
 *   uses per-item inline x-data for its dropdowns; this registration
 *   exists so the x-data="nebulaMenuTop()" hook is always defined and
 *   can be extended later without touching the template.
 */

interface NebulaStoreType {
    sidebarCollapsed: boolean;
    toggleSidebar(): void;
}

interface MenuConfig {
    openSections: string[];
    pinUrl: string;
    reorderUrl: string;
}

function createNebulaMenuSidebar(config: MenuConfig) {
    const cfg: MenuConfig = {
        openSections: Array.isArray(config?.openSections) ? config.openSections : [],
        pinUrl: config?.pinUrl ?? '',
        reorderUrl: config?.reorderUrl ?? '',
    };

    return {
        openSections: cfg.openSections,

        init(): void {
            const navContainer = ((this as unknown as { $root: HTMLElement }).$root).closest('nav');
            if (navContainer && !navContainer.hasAttribute('aria-label')) {
                navContainer.setAttribute('aria-label', 'Sidebar navigation');
            }

            const pinnedList = document.getElementById('nebula-pinned-list');
            if (pinnedList && typeof window.Sortable !== 'undefined') {
                window.Sortable.create(pinnedList, {
                    handle: '.nebula-drag-handle',
                    animation: 150,
                    ghostClass: 'opacity-30',
                    chosenClass: 'bg-nebula-800',
                    onEnd: () => { this.savePinOrder(); },
                });
            }

            const nebulaStore = window.Alpine?.store
                ? (window.Alpine.store('nebula') as NebulaStoreType | null)
                : null;
            if (nebulaStore && window.Alpine && typeof window.Alpine.effect === 'function') {
                window.Alpine.effect(() => {
                    if (nebulaStore.sidebarCollapsed) {
                        this.openSections = [];
                    }
                });
            }
        },

        toggle(id: string): void {
            const nebulaStore = window.Alpine?.store
                ? (window.Alpine.store('nebula') as NebulaStoreType | null)
                : null;
            if (nebulaStore?.sidebarCollapsed) {
                if (!this.openSections.includes(id)) {
                    this.openSections.push(id);
                }
                nebulaStore.toggleSidebar();
                return;
            }
            const i = this.openSections.indexOf(id);
            i > -1 ? this.openSections.splice(i, 1) : this.openSections.push(id);
        },

        isOpen(id: string): boolean {
            return this.openSections.includes(id);
        },

        async togglePin(itemId: string): Promise<void> {
            try {
                const formKey = document.querySelector<HTMLInputElement>('[name="form_key"]')?.value ?? '';
                const resp = await fetch(cfg.pinUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ item_id: itemId, form_key: formKey }),
                });
                const data = await resp.json() as { success: boolean };
                if (data.success) {
                    window.location.reload();
                }
            } catch (e) {
                console.error('Pin toggle failed:', e);
            }
        },

        async savePinOrder(): Promise<void> {
            const items = document.querySelectorAll<HTMLElement>('#nebula-pinned-list [data-pin-id]');
            const order = Array.from(items).map((el) => el.dataset['pinId']);
            try {
                const formKey = document.querySelector<HTMLInputElement>('[name="form_key"]')?.value ?? '';
                await fetch(cfg.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ order: JSON.stringify(order), form_key: formKey }),
                });
            } catch (e) {
                console.error('Reorder failed:', e);
            }
        },
    };
}

export function registerNebulaMenu(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaMenuSidebar', (config: MenuConfig) =>
            createNebulaMenuSidebar(config),
        );
        window.Alpine.data('nebulaMenuTop', () => ({}));
    });
}
