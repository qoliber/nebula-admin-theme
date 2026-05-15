/**
 * `nebulaConfigWizard` Alpine component — 3-step wizard that builds
 * configurable-product variations from a product's attribute set.
 * Extracted from the inline `<script>` in
 * `NebulaProductConfigurable/view/adminhtml/templates/snippet/configurable_options.phtml`.
 */

interface AttrOption {
    value: string;
    label: string;
}

interface AvailableAttribute {
    id: string;
    code: string;
    label: string;
    options: AttrOption[];
}

interface Variation {
    id: number | null;
    sku: string;
    name: string;
    price: string;
    qty?: string;
    status: number | string;
    attributes: Record<string, { value: string; label: string }>;
    isNew?: boolean;
}

interface ConfigWizardInput {
    availableAttributes?: AvailableAttribute[];
    existingVariations?: Variation[];
    usedAttributeCodes?: string[];
    usedAttributeIds?: string[];
    getAttributesUrl?: string;
    formKey?: string;
}

interface NebulaModelPair {
    name: string;
    value: string | number | boolean | null;
}

interface NebulaModelStore {
    register(name: string, model: unknown): void;
    unregister(name: string): void;
}

export function registerConfigWizard(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaConfigWizard', (config: ConfigWizardInput = {}) => ({
            wizardOpen: false,
            currentStep: 0,
            availableAttributes: config.availableAttributes ?? [],
            variations: (config.existingVariations ?? []).slice() as Variation[],
            selectedAttributeIds: [] as string[],
            selectedOptions: {} as Record<string, string[]>,
            previewVariations: [] as Array<Record<string, string>>,

            init(): void {
                const models = window.Alpine?.store('nebulaModels') as NebulaModelStore | undefined;
                models?.register('section:configurable', this);

                const codes = config.usedAttributeCodes ?? [];
                if (codes.length === 0) return;

                this.selectedAttributeIds = this.availableAttributes
                    .filter((a) => codes.includes(a.code))
                    .map((a) => a.id);

                this.selectedAttributeIds.forEach((attrId) => {
                    const attr = this.availableAttributes.find((a) => a.id === attrId);
                    if (!attr) return;
                    const usedValues = new Set<string>();
                    this.variations.forEach((v) => {
                        const code = attr.code;
                        const val = v.attributes[code];
                        if (val) usedValues.add(val.value);
                    });
                    this.selectedOptions[attrId] = Array.from(usedValues);
                });
            },

            get usedAttrs(): AvailableAttribute[] {
                const codes = new Set<string>();
                this.variations.forEach((v) => {
                    Object.keys(v.attributes ?? {}).forEach((c) => codes.add(c));
                });
                return this.availableAttributes.filter((a) => codes.has(a.code));
            },

            get selectedAttributes(): AvailableAttribute[] {
                const ids = this.selectedAttributeIds;
                return this.availableAttributes.filter((a) => ids.includes(a.id));
            },

            get canProceed(): boolean {
                if (this.currentStep === 0) return this.selectedAttributeIds.length > 0;
                if (this.currentStep === 1) {
                    return this.selectedAttributes.every((attr) => (this.selectedOptions[attr.id] ?? []).length > 0);
                }
                return true;
            },

            get generatedVariations(): Array<Record<string, string>> {
                const attrs = this.selectedAttributes;
                if (attrs.length === 0) return [];
                let combos: Array<Record<string, string>> = [{}];
                const parentSku = (document.querySelector<HTMLInputElement>('[name=sku]')?.value) ?? 'SKU';

                attrs.forEach((attr) => {
                    const opts = this.selectedOptions[attr.id] ?? [];
                    const newCombos: Array<Record<string, string>> = [];
                    combos.forEach((combo) => {
                        opts.forEach((optValue) => {
                            const c: Record<string, string> = { ...combo };
                            c[attr.code] = optValue;
                            newCombos.push(c);
                        });
                    });
                    combos = newCombos;
                });

                return combos.map((combo) => {
                    const skuParts = [parentSku];
                    attrs.forEach((attr) => {
                        const opt = attr.options.find((o) => o.value === combo[attr.code]);
                        if (opt) skuParts.push(opt.label.replace(/\s+/g, '-'));
                    });
                    combo.sku = combo.sku || skuParts.join('-');
                    combo.price = combo.price || '';
                    combo.qty = combo.qty || '0';
                    combo.status = combo.status || '1';
                    return combo;
                });
            },

            openWizard(): void {
                this.currentStep = 0;
                this.wizardOpen = true;
            },

            nextStep(): void {
                if (!this.canProceed || this.currentStep >= 2) return;
                if (this.currentStep === 0) {
                    this.selectedAttributeIds.forEach((id) => {
                        if (!this.selectedOptions[id]) this.selectedOptions[id] = [];
                    });
                }
                this.currentStep++;
                if (this.currentStep === 2) {
                    this.previewVariations = this.generatedVariations;
                }
            },

            isOptionSelected(attrId: string, optValue: string): boolean {
                return (this.selectedOptions[attrId] ?? []).includes(optValue);
            },

            toggleOption(attrId: string, optValue: string): void {
                const bucket = this.selectedOptions[attrId] ?? (this.selectedOptions[attrId] = []);
                const idx = bucket.indexOf(optValue);
                if (idx >= 0) bucket.splice(idx, 1);
                else bucket.push(optValue);
            },

            toggleAllOptions(attrId: string): void {
                const attr = this.availableAttributes.find((a) => a.id === attrId);
                if (!attr) return;
                const allValues = attr.options.map((o) => o.value);
                if ((this.selectedOptions[attrId] ?? []).length === allValues.length) {
                    this.selectedOptions[attrId] = [];
                } else {
                    this.selectedOptions[attrId] = allValues.slice();
                }
            },

            getOptionLabel(attrId: string, optValue: string): string {
                const attr = this.availableAttributes.find((a) => a.id === attrId);
                if (!attr) return optValue;
                const opt = attr.options.find((o) => o.value === optValue);
                return opt ? opt.label : optValue;
            },

            serialize(): NebulaModelPair[] {
                const pairs: NebulaModelPair[] = [];
                this.variations.forEach((variation, i) => {
                    this.usedAttrs.forEach((attr) => {
                        const vAttr = variation.attributes[attr.code];
                        pairs.push({
                            name: 'configurable_variations[' + String(i) + '][attributes][' + attr.code + ']',
                            value: vAttr ? vAttr.value : '',
                        });
                    });
                    pairs.push({ name: 'configurable_variations[' + String(i) + '][sku]', value: variation.sku });
                    pairs.push({ name: 'configurable_variations[' + String(i) + '][name]', value: variation.name });
                    pairs.push({ name: 'configurable_variations[' + String(i) + '][price]', value: variation.price });
                    pairs.push({ name: 'configurable_variations[' + String(i) + '][status]', value: variation.status });
                    if (variation.id) {
                        pairs.push({ name: 'configurable_variations[' + String(i) + '][id]', value: variation.id });
                    }
                });
                pairs.push({
                    name: 'associated_product_ids_serialized',
                    value: JSON.stringify(this.variations.filter((v) => v.id).map((v) => v.id)),
                });
                (config.usedAttributeIds ?? []).forEach((attrId) => {
                    pairs.push({ name: 'attributes[]', value: attrId });
                });
                pairs.push({ name: 'affect_configurable_product_attributes', value: '1' });
                return pairs;
            },

            destroy(): void {
                const models = window.Alpine?.store('nebulaModels') as NebulaModelStore | undefined;
                models?.unregister('section:configurable');
            },

            applyVariations(): void {
                this.variations = this.previewVariations.map((v): Variation => {
                    const attrs: Variation['attributes'] = {};
                    this.selectedAttributes.forEach((attr) => {
                        attrs[attr.code] = {
                            value: v[attr.code] ?? '',
                            label: this.getOptionLabel(attr.id, v[attr.code] ?? ''),
                        };
                    });
                    return {
                        id: null,
                        sku: v.sku ?? '',
                        name: v.sku ?? '',
                        price: v.price ?? '',
                        qty: v.qty ?? '0',
                        status: parseInt(String(v.status), 10) || 1,
                        attributes: attrs,
                        isNew: true,
                    };
                });
                this.wizardOpen = false;
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
