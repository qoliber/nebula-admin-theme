/**
 * `nebulaBundleSettings` Alpine component. Extracted from the inline
 * `<script>` in
 * `NebulaProductBundle/view/adminhtml/templates/snippet/bundle_settings.phtml`.
 */

interface BundleSettingsConfig {
    skuType: number;
    priceType: number;
    weightType: number;
    shipmentType: number;
    weight: string;
    isNew: boolean;
}

export function registerBundleSettings(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaBundleSettings', (config: BundleSettingsConfig) => ({
            skuType: config.skuType,
            priceType: config.priceType,
            weightType: config.weightType,
            shipmentType: config.shipmentType,
            weight: config.weight,
            isNew: config.isNew,

            get isDynamicSku(): boolean { return this.skuType === 1; },
            get isDynamicPrice(): boolean { return this.priceType === 0; },
            get isDynamicWeight(): boolean { return this.weightType === 1; },

            toggleSku(): void { this.skuType = this.skuType === 1 ? 0 : 1; },
            toggleWeight(): void { this.weightType = this.weightType === 1 ? 0 : 1; },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
