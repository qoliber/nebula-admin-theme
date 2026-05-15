/**
 * Nebula bridge for Magento_AdvancedSearch's "Test Connection" button
 * (Catalog Search engine settings — base + OpenSearch + Elasticsearch8 subclasses).
 *
 * Vendor renders the button with
 *   data-mage-init='{"testConnection":{ url, elementId, successText, failedText, fieldMapping }}'
 * and the testconnection.js mage-init module gathers the listed fields, POSTs
 * to the URL, and updates the inner <span id="<elementId>_result"> text.
 * That entire pipeline never runs under nebula because RequireJS is stripped.
 *
 * Read the same data-mage-init payload directly, attach a click handler that
 * does the same thing (XHR + result text + success/fail visual state). Using
 * the vendor data attribute as the contract means every TestConnection
 * subclass works without per-class PHP preferences.
 */

interface TestConnectionConfig {
    url: string;
    elementId: string;
    successText: string;
    failedText: string;
    fieldMapping: string; // JSON string: { paramName: domElementId, ... }
}

interface TestConnectionResponse {
    success?: boolean;
    errorMessage?: string;
}

function extractConfig(button: HTMLButtonElement): TestConnectionConfig | null {
    const raw = button.dataset.mageInit;
    if (!raw) return null;

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>;
        const tc = parsed['testConnection'] as TestConnectionConfig | undefined;
        if (!tc?.url || !tc?.elementId) return null;
        return tc;
    } catch (e) {
        console.warn('[nebula] test-connection: invalid data-mage-init', e);
        return null;
    }
}

function gatherParams(fieldMappingJson: string): Record<string, string> {
    const params: Record<string, string> = {};
    let mapping: Record<string, string>;
    try {
        mapping = JSON.parse(fieldMappingJson) as Record<string, string>;
    } catch {
        return params;
    }

    Object.entries(mapping).forEach(([key, domId]) => {
        const el = document.getElementById(domId) as HTMLInputElement | HTMLSelectElement | null;
        params[key] = el?.value ?? '';
    });

    return params;
}

async function runTest(button: HTMLButtonElement, config: TestConnectionConfig): Promise<void> {
    const resultSpan = document.getElementById(`${config.elementId}_result`);
    const originalText = resultSpan?.textContent ?? '';

    button.disabled = true;
    button.classList.remove('nebula-test-success', 'nebula-test-fail');
    button.classList.add('nebula-test-pending');
    if (resultSpan) resultSpan.textContent = 'Testing…';

    const formKey = document.querySelector<HTMLInputElement>('[name=form_key]')?.value ?? '';
    const params = gatherParams(config.fieldMapping);
    params['form_key'] = formKey;

    let response: TestConnectionResponse;
    try {
        const resp = await fetch(config.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams(params),
        });
        response = await resp.json() as TestConnectionResponse;
    } catch (e) {
        console.error('[nebula] test-connection request failed', e);
        button.classList.remove('nebula-test-pending');
        button.classList.add('nebula-test-fail');
        if (resultSpan) resultSpan.textContent = config.failedText || originalText;
        button.disabled = false;
        return;
    }

    button.classList.remove('nebula-test-pending');
    if (response.success) {
        button.classList.add('nebula-test-success');
        if (resultSpan) resultSpan.textContent = config.successText || originalText;
    } else {
        button.classList.add('nebula-test-fail');
        if (resultSpan) resultSpan.textContent = config.failedText || originalText;
        if (response.errorMessage) {
            window.alert(response.errorMessage);
        }
    }

    button.disabled = false;
}

function wireButton(button: HTMLButtonElement): void {
    if (button.dataset.nebulaTestConnectionBound === '1') return;
    const config = extractConfig(button);
    if (!config) return;

    button.dataset.nebulaTestConnectionBound = '1';
    button.addEventListener('click', (e) => {
        e.preventDefault();
        void runTest(button, config);
    });
}

export function bootTestConnection(): void {
    const buttons = document.querySelectorAll<HTMLButtonElement>('button[data-mage-init*="testConnection"]');
    if (buttons.length === 0) return;

    console.debug(`[nebula] test-connection boot: ${buttons.length} button(s)`);
    buttons.forEach((btn) => wireButton(btn));
}
