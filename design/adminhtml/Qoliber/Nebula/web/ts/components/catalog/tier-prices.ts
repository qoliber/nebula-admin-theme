/**
 * `nebulaTierPrices` Alpine component. Extracted from the inline `<script>`
 * in `NebulaCatalog/view/adminhtml/templates/eav/snippet/tier_prices.phtml`.
 */

interface TierPriceRow {
    website_id: string;
    cust_group: string;
    price_qty: string;
    price: string;
    value_type: string;
}

export function registerTierPrices(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaTierPrices', (initial: TierPriceRow[] = []) => ({
            rows: initial.slice(),
            addRow(): void {
                this.rows.push({
                    website_id: '0',
                    cust_group: '32000',
                    price_qty: '',
                    price: '',
                    value_type: 'fixed',
                });
            },
            removeRow(index: number): void {
                this.rows.splice(index, 1);
            },
        }));
    };

    document.addEventListener('alpine:init', install);
}
