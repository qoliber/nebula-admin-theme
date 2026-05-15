/**
 * `nebulaStockFields` Alpine component — advanced-inventory fieldset for
 * product edit. Extracted from the inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/eav/snippet/stock_fields.phtml`.
 */

type StockFieldConfig = Record<string, unknown>;

export function registerStockFields(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaStockFields', (config: StockFieldConfig = {}) => ({
            showAdvanced: false,
            fields: config,
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
