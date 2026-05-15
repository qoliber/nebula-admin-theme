/**
 * nebulaMediaSync — Alpine factory bound by Qoliber\Nebula\Block\Config\Form\Field\MediaSynchronize.
 * Handles the System Config → Storage Configuration for Media synchronize flow:
 * watch the storage/database selects, enable Sync when current ≠ baseline, POST to
 * the sync endpoint, then long-poll the status endpoint until the flag clears.
 *
 * Replaces vendor's Prototype + RequireJS implementation that doesn't run under nebula.
 */

const STATE_RUNNING = 1;
const STATE_FINISHED = 2;
const STATE_NOTIFIED = 3;
const POLL_INTERVAL_MS = 5000;

interface NebulaMediaSyncConfig {
    syncUrl: string;
    statusUrl: string;
    storageSelector: string;
    databaseSelector: string;
    initiallyRunning: boolean;
}

interface NebulaMediaSyncState {
    syncing: boolean;
    message: string;
    current: { storage: string; database: string };
    baseline: { storage: string; database: string };
    readonly canSync: boolean;
    init(): void;
    sync(): Promise<void>;
}

interface StatusResponse {
    state?: number;
    message?: string;
    has_errors?: boolean;
}

function readSelectValue(selector: string): string {
    const el = document.querySelector<HTMLSelectElement>(selector);
    return el?.value ?? '';
}

function setSelectsDisabled(selectors: string[], disabled: boolean): void {
    selectors.forEach((selector) => {
        const el = document.querySelector<HTMLSelectElement>(selector);
        if (el) el.disabled = disabled;
    });
}

function createMediaSync(config: NebulaMediaSyncConfig): NebulaMediaSyncState {
    let pollTimer: number | null = null;

    const state: NebulaMediaSyncState = {
        syncing: false,
        message: '',
        current: { storage: '', database: '' },
        baseline: { storage: '', database: '' },

        get canSync(): boolean {
            if (this.syncing) return false;
            return this.current.storage !== this.baseline.storage
                || this.current.database !== this.baseline.database;
        },

        init(): void {
            // Baseline is the saved config — i.e. whatever the selects show on page load.
            // PHP-side sync flag data only matters for resuming polling, not for the baseline.
            this.baseline.storage = readSelectValue(config.storageSelector);
            this.baseline.database = readSelectValue(config.databaseSelector);
            this.current.storage = this.baseline.storage;
            this.current.database = this.baseline.database;

            const refresh = () => {
                this.current.storage = readSelectValue(config.storageSelector);
                this.current.database = readSelectValue(config.databaseSelector);
            };
            const storageEl = document.querySelector(config.storageSelector);
            const databaseEl = document.querySelector(config.databaseSelector);
            if (storageEl) storageEl.addEventListener('change', refresh);
            if (databaseEl) databaseEl.addEventListener('change', refresh);

            if (config.initiallyRunning) {
                this.syncing = true;
                setSelectsDisabled([config.storageSelector, config.databaseSelector], true);
                this.poll();
            }
        },

        async sync(): Promise<void> {
            if (!this.canSync) return;

            this.syncing = true;
            this.message = '';
            setSelectsDisabled([config.storageSelector, config.databaseSelector], true);

            const formKey = document.querySelector<HTMLInputElement>('[name=form_key]')?.value ?? '';
            try {
                await fetch(config.syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        storage: this.current.storage,
                        connection: this.current.database,
                        form_key: formKey,
                    }),
                });
            } catch (e) {
                console.error('[nebula] media-sync request failed', e);
                this.syncing = false;
                setSelectsDisabled([config.storageSelector, config.databaseSelector], false);
                return;
            }

            window.setTimeout(() => this.poll(), 2000);
        },

        async poll(): Promise<void> {
            try {
                const resp = await fetch(config.statusUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await resp.json() as StatusResponse;

                if (Number(data.state) === STATE_RUNNING) {
                    this.message = data.message ?? '';
                    pollTimer = window.setTimeout(() => this.poll(), POLL_INTERVAL_MS);
                    return;
                }

                this.syncing = false;
                this.message = '';
                setSelectsDisabled([config.storageSelector, config.databaseSelector], false);

                const finishedClean = Number(data.state) === STATE_FINISHED
                    || (Number(data.state) === STATE_NOTIFIED && !data.has_errors);
                if (finishedClean) {
                    this.baseline = { ...this.current };
                }
            } catch (e) {
                console.error('[nebula] media-sync poll failed', e);
                this.syncing = false;
                setSelectsDisabled([config.storageSelector, config.databaseSelector], false);
            }
        },
    } as NebulaMediaSyncState & { poll(): Promise<void> };

    return state;
}

export function registerMediaSynchronize(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaMediaSync', (config: NebulaMediaSyncConfig) => createMediaSync(config));
    });
}
