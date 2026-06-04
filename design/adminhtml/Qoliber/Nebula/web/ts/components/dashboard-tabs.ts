/**
 * nebulaDashboardTabs — Alpine factory powering the lazy-loaded tab strip
 * on the admin dashboard's "Bestsellers & Stats" panel.
 *
 * Mirrors stock Magento\Backend\Block\Dashboard\Grids: the first tab
 * (Bestsellers) is pre-rendered server-side, the other three (Most
 * Viewed Products / New Customers / Customers) POST to the stock
 * `adminhtml/dashboard/<action>` endpoints on first click and inject
 * the returned raw HTML into their panel. Cached per-tab so re-clicks
 * are instant.
 *
 * The endpoints implement HttpPostActionInterface — form_key + a
 * X-Requested-With header are required.
 */

interface DashboardTab {
    id: string;
    label: string;
    content: string;
    url: string;
}

interface DashboardTabsConfig {
    tabs: DashboardTab[];
    formKey: string;
}

interface DashboardTabsState {
    tabs: DashboardTab[];
    formKey: string;
    /** Which tab button is highlighted — updates instantly on click. */
    active: number;
    /**
     * Which tab's HTML is currently rendered in the panel. Stays on the
     * previously-shown tab while the next one is fetching, so the panel
     * keeps its size and a loader overlays the existing content instead
     * of the container collapsing + expanding.
     */
    displayed: number;
    loading: boolean;
    error: string;
    /** Monotonic request id used to ignore stale responses. */
    _reqSeq: number;
    activate(idx: number): void;
}

function createDashboardTabs(config: DashboardTabsConfig): DashboardTabsState {
    return {
        tabs:      config.tabs || [],
        formKey:   config.formKey || '',
        active:    0,
        displayed: 0,
        loading:   false,
        error:     '',
        _reqSeq:   0,

        activate(idx: number): void {
            this.active = idx;
            this.error  = '';
            const tab = this.tabs[idx];
            if (!tab) {
                return;
            }

            // Cached or eager tab — swap instantly, no layout shift.
            if (tab.content || !tab.url) {
                this.displayed = idx;
                return;
            }

            // Fetch: keep the previously-displayed panel visible (and the
            // container at its current height) until the new HTML arrives.
            this.loading = true;
            const seq    = ++this._reqSeq;
            const body   = new FormData();
            body.append('form_key', this.formKey);
            fetch(tab.url, {
                method:      'POST',
                body:        body,
                credentials: 'same-origin',
                headers:     { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.text();
                })
                .then((html) => {
                    // Ignore the response if the user clicked another tab
                    // before this one finished.
                    if (seq !== this._reqSeq) {
                        return;
                    }
                    tab.content    = html;
                    this.displayed = idx;
                })
                .catch((err: Error) => {
                    if (seq !== this._reqSeq) {
                        return;
                    }
                    this.error = 'Failed to load: ' + (err?.message || 'unknown');
                })
                .finally(() => {
                    if (seq === this._reqSeq) {
                        this.loading = false;
                    }
                });
        },
    };
}

export function registerDashboardTabs(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaDashboardTabs', (config: DashboardTabsConfig) =>
            createDashboardTabs(config),
        );
    });
}
