/**
 * `nebulaAttributeOptions` Alpine component — select/multiselect option
 * editor used on the product-attribute edit screen. Extracted from the
 * inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/snippet/attribute_options.phtml`.
 */

interface AttributeOption {
    id: string | number;
    label: string;
    sort_order: number;
    is_default: boolean;
    store_labels: Record<string, string>;
    is_new: boolean;
    is_delete: boolean;
}

interface AttributeOptionsConfig {
    options?: AttributeOption[];
    inputType?: string;
    stores?: Array<{ id: number; name: string; code: string }>;
}

export function registerAttributeOptions(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaAttributeOptions', (config: AttributeOptionsConfig = {}) => ({
            options: (config.options ?? []).slice(),
            inputType: config.inputType ?? 'select',
            stores: config.stores ?? [],
            nextId: 0,

            addOption(): void {
                this.options.push({
                    id: 'option_' + String(this.nextId++),
                    label: '',
                    sort_order: this.options.length + 1,
                    is_default: false,
                    store_labels: {},
                    is_new: true,
                    is_delete: false,
                });
            },

            removeOption(index: number): void {
                const opt = this.options[index];
                if (!opt) return;
                if (opt.is_new) {
                    this.options.splice(index, 1);
                } else {
                    opt.is_delete = true;
                }
            },

            setDefault(index: number): void {
                const opt = this.options[index];
                if (!opt) return;
                if (this.inputType === 'select') {
                    this.options.forEach((o, i) => { o.is_default = (i === index); });
                } else {
                    opt.is_default = !opt.is_default;
                }
            },

            get visibleOptions(): AttributeOption[] {
                return this.options.filter((o) => !o.is_delete);
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
