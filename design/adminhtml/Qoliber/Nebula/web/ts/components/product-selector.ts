/**
 * `nebulaProductSelector` Alpine component — reusable product picker with
 * AJAX search. Extracted from the inline `<script>` in
 * `NebulaComponent/view/adminhtml/templates/snippet/product_selector.phtml`.
 */

interface ProductRow {
    id: number;
    [key: string]: unknown;
}

interface ProductSelectorConfig {
    fieldName?: string;
    fieldFormat?: string;
    selectedProducts?: ProductRow[];
    searchUrl?: string;
    formKey?: string;
    maxResults?: number;
}

interface SearchPayload {
    items?: ProductRow[];
}

export function registerProductSelector(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaProductSelector', (config: ProductSelectorConfig = {}) => ({
            fieldName: config.fieldName ?? '',
            fieldFormat: config.fieldFormat ?? 'flat',
            selectedProducts: (config.selectedProducts ?? []).slice() as ProductRow[],
            searchOpen: false,
            searchQuery: '',
            searchResults: [] as ProductRow[],
            searching: false,
            searchUrl: config.searchUrl ?? '',
            formKey: config.formKey ?? '',
            maxResults: config.maxResults ?? 20,

            search(): void {
                if (this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }
                this.searching = true;
                const excludeIds = this.selectedProducts.map((p) => p.id).join(',');
                const url = this.searchUrl
                    + '?q=' + encodeURIComponent(this.searchQuery)
                    + '&exclude=' + encodeURIComponent(excludeIds)
                    + '&limit=' + String(this.maxResults)
                    + '&form_key=' + encodeURIComponent(this.formKey);

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then((resp) => resp.json() as Promise<SearchPayload>)
                    .then((data) => {
                        this.searchResults = data.items ?? [];
                        this.searching = false;
                    })
                    .catch(() => {
                        this.searching = false;
                    });
            },

            addProduct(product: ProductRow): void {
                this.selectedProducts.push(product);
                this.searchResults = this.searchResults.filter((p) => p.id !== product.id);
            },

            removeProduct(index: number): void {
                this.selectedProducts.splice(index, 1);
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
