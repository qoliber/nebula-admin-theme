/**
 * `nebulaCategoryProducts` Alpine component — manages the
 * category-edit page's product list + positions. Extracted from the
 * inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/eav/snippet/category_products.phtml`.
 */

interface CategoryProductRow {
    id: number;
    position?: number;
    [key: string]: unknown;
}

interface CategoryProductsConfig {
    products?: CategoryProductRow[];
    productsJson?: Record<string, number>;
    searchUrl?: string;
    formKey?: string;
}

export function registerCategoryProducts(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaCategoryProducts', (config: CategoryProductsConfig = {}) => ({
            products: (config.products ?? []).slice(),
            productsJson: { ...(config.productsJson ?? {}) } as Record<string, number>,

            syncJson(): void {
                const json: Record<string, number> = {};
                this.products.forEach((p, i) => {
                    json[String(p.id)] = typeof p.position === 'number' ? p.position : i;
                });
                this.productsJson = json;
            },

            addProduct(product: CategoryProductRow): void {
                if (this.products.find((p) => p.id === product.id)) return;
                product.position = this.products.length;
                this.products.push(product);
                this.syncJson();
            },

            removeProduct(id: number): void {
                this.products = this.products.filter((p) => p.id !== id);
                this.syncJson();
            },

            updatePosition(id: number, pos: string | number): void {
                const p = this.products.find((x) => x.id === id);
                if (p) {
                    p.position = typeof pos === 'number' ? pos : parseInt(pos, 10) || 0;
                }
                this.syncJson();
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
