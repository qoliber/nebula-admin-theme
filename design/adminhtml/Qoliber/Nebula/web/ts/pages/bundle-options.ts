/**
 * nebulaBundleOptions — Alpine component for the product-bundle wizard.
 */

import type { NebulaModelStore } from '../models';
import type { SerializedPair } from '../types';

interface BundleSelection {
    selection_id?: string | number;
    product_id: string | number;
    id?: string | number;
    name?: string;
    sku?: string;
    price: number | string;
    price_type?: number | string;
    qty: number;
    is_default: boolean;
    can_change_qty: boolean;
    position: number;
    thumbnail?: string;
    delete?: string;
}

interface BundleOption {
    option_id: string | number;
    title: string;
    type: string;
    required: boolean;
    position: number;
    selections: BundleSelection[];
    delete: string;
}

interface WizardProduct {
    id: string | number;
    name: string;
    sku: string;
    price?: number;
    qty?: number;
    thumbnail?: string;
}

interface BundleOptionsConfig {
    existingOptions?: BundleOption[];
    searchUrl: string;
    formKey: string;
}

interface SearchResponse {
    items?: WizardProduct[];
}

interface BundleOptionsState {
    options: BundleOption[];
    searchUrl: string;
    formKey: string;
    wizardOpen: boolean;
    wizardStep: number;
    wizardOption: { title: string; type: string; required: boolean };
    wizardSelections: WizardProduct[];
    wizardSearchOpen: boolean;
    wizardSearchQuery: string;
    wizardSearchResults: WizardProduct[];
    wizardSearching: boolean;
    optionTypes: Array<{ value: string; label: string }>;
    inlineSearchOpen: boolean;
    inlineSearchOptionIndex: number;
    inlineSearchQuery: string;
    inlineSearchResults: WizardProduct[];
    inlineSearching: boolean;
    init(): void;
    destroy(): void;
    serialize(): SerializedPair[];
    getTypeBadgeClass(type: string): string;
    getTypeLabel(type: string): string;
    getTypeIcon(type: string): string;
    openWizard(): void;
    readonly wizardCanProceed: boolean;
    wizardNext(): void;
    wizardApply(): void;
    openWizardSearch(): void;
    wizardSearch(): void;
    wizardAddProduct(product: WizardProduct): void;
    wizardRemoveProduct(index: number): void;
    openInlineSearch(optionIndex: number): void;
    inlineSearch(): void;
    inlineAddProduct(product: WizardProduct): void;
    removeOption(index: number): void;
    removeSelection(optionIndex: number, selectionIndex: number): void;
    toggleDefault(optionIndex: number, selectionIndex: number): void;
    readonly visibleOptions: BundleOption[];
    $nextTick?: (cb: () => void) => void;
}

export function createBundleOptions(config: BundleOptionsConfig): BundleOptionsState {
    return {
        options: config.existingOptions ?? [],
        searchUrl: config.searchUrl,
        formKey: config.formKey,

        wizardOpen: false,
        wizardStep: 0,
        wizardOption: { title: '', type: 'select', required: true },
        wizardSelections: [],
        wizardSearchOpen: false,
        wizardSearchQuery: '',
        wizardSearchResults: [],
        wizardSearching: false,

        optionTypes: [
            { value: 'select', label: 'Drop-down' },
            { value: 'radio', label: 'Radio Buttons' },
            { value: 'checkbox', label: 'Checkbox' },
            { value: 'multi', label: 'Multi Select' },
        ],

        inlineSearchOpen: false,
        inlineSearchOptionIndex: -1,
        inlineSearchQuery: '',
        inlineSearchResults: [],
        inlineSearching: false,

        init(this: BundleOptionsState): void {
            const models = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
            if (models) models.register('section:bundleOptions', this);
        },

        destroy(): void {
            const models = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
            if (models) models.unregister('section:bundleOptions');
        },

        serialize(this: BundleOptionsState): SerializedPair[] {
            const pairs: SerializedPair[] = [];
            pairs.push({ name: 'affect_bundle_product_selections', value: '1' });

            this.options.forEach((option, oi) => {
                const prefix = 'bundle_options[bundle_options][' + String(oi) + ']';
                pairs.push({ name: prefix + '[title]', value: option.title });
                pairs.push({ name: prefix + '[type]', value: option.type });
                pairs.push({ name: prefix + '[required]', value: option.required ? '1' : '0' });
                pairs.push({ name: prefix + '[position]', value: option.position });
                pairs.push({ name: prefix + '[option_id]', value: option.option_id !== undefined ? option.option_id : '' });
                pairs.push({ name: prefix + '[delete]', value: option.delete || '' });

                option.selections.forEach((sel, si) => {
                    const sp = prefix + '[bundle_selections][' + String(si) + ']';
                    pairs.push({ name: sp + '[product_id]', value: sel.product_id });
                    pairs.push({ name: sp + '[selection_qty]', value: sel.qty });
                    pairs.push({ name: sp + '[selection_price_value]', value: sel.price });
                    pairs.push({
                        name: sp + '[selection_price_type]',
                        value: sel.price_type !== undefined ? sel.price_type : 0,
                    });
                    pairs.push({ name: sp + '[is_default]', value: sel.is_default ? '1' : '0' });
                    pairs.push({
                        name: sp + '[selection_can_change_qty]',
                        value: sel.can_change_qty ? '1' : '0',
                    });
                    pairs.push({ name: sp + '[position]', value: sel.position });
                    pairs.push({
                        name: sp + '[selection_id]',
                        value: sel.selection_id !== undefined ? sel.selection_id : '',
                    });
                    pairs.push({ name: sp + '[delete]', value: sel.delete ?? '' });
                });
            });

            return pairs;
        },

        getTypeBadgeClass(type: string): string {
            const classes: Record<string, string> = {
                select: 'bg-blue-100 text-blue-700',
                radio: 'bg-purple-100 text-purple-700',
                checkbox: 'bg-green-100 text-green-700',
                multi: 'bg-amber-100 text-amber-700',
            };
            return classes[type] ?? 'bg-gray-100 text-gray-700';
        },

        getTypeLabel(this: BundleOptionsState, type: string): string {
            const found = this.optionTypes.find((t) => t.value === type);
            return found ? found.label : type;
        },

        getTypeIcon(type: string): string {
            const icons: Record<string, string> = {
                select:
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>',
                radio:
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
                checkbox:
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                multi:
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>',
            };
            return (
                '<svg class="h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' +
                (icons[type] ?? '') +
                '</svg>'
            );
        },

        openWizard(this: BundleOptionsState): void {
            this.wizardStep = 0;
            this.wizardOption = { title: '', type: 'select', required: true };
            this.wizardSelections = [];
            this.wizardOpen = true;
        },

        get wizardCanProceed(): boolean {
            if (this.wizardStep === 0) return this.wizardOption.title.trim().length > 0;
            if (this.wizardStep === 1) return this.wizardSelections.length > 0;
            return true;
        },

        wizardNext(this: BundleOptionsState): void {
            if (this.wizardCanProceed && this.wizardStep < 1) {
                this.wizardStep++;
            }
        },

        wizardApply(this: BundleOptionsState): void {
            const selections: BundleSelection[] = this.wizardSelections.map((s, i) => ({
                selection_id: '',
                product_id: s.id,
                name: s.name,
                sku: s.sku,
                price: s.price ?? 0,
                price_type: 0,
                qty: s.qty ?? 1,
                is_default: i === 0,
                can_change_qty: true,
                position: i,
                thumbnail: s.thumbnail ?? '',
                delete: '',
            }));

            this.options.push({
                option_id: '',
                title: this.wizardOption.title,
                type: this.wizardOption.type,
                required: this.wizardOption.required,
                position: this.options.length,
                selections,
                delete: '',
            });

            this.wizardOpen = false;
        },

        openWizardSearch(this: BundleOptionsState): void {
            this.inlineSearchOpen = false;
            this.wizardSearchOpen = true;
            this.wizardSearchQuery = '';
            this.wizardSearchResults = [];
            this.$nextTick?.(() => {
                const input = document.querySelector<HTMLInputElement>('[x-ref="wizardSearchInput"]');
                if (input) input.focus();
            });
        },

        wizardSearch(this: BundleOptionsState): void {
            if (this.wizardSearchQuery.length < 2) {
                this.wizardSearchResults = [];
                return;
            }
            this.wizardSearching = true;
            const excludeIds = this.wizardSelections.map((p) => String(p.id)).join(',');
            const url =
                this.searchUrl +
                '?q=' +
                encodeURIComponent(this.wizardSearchQuery) +
                '&exclude=' +
                encodeURIComponent(excludeIds) +
                '&limit=20' +
                '&form_key=' +
                encodeURIComponent(this.formKey);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((resp) => resp.json())
                .then((data: SearchResponse) => {
                    this.wizardSearchResults = data.items ?? [];
                    this.wizardSearching = false;
                })
                .catch(() => {
                    this.wizardSearching = false;
                });
        },

        wizardAddProduct(this: BundleOptionsState, product: WizardProduct): void {
            this.wizardSelections.push({
                id: product.id,
                name: product.name,
                sku: product.sku,
                price: product.price ?? 0,
                qty: 1,
                thumbnail: product.thumbnail ?? '',
            });
            this.wizardSearchResults = this.wizardSearchResults.filter((p) => p.id !== product.id);
        },

        wizardRemoveProduct(this: BundleOptionsState, index: number): void {
            this.wizardSelections.splice(index, 1);
        },

        openInlineSearch(this: BundleOptionsState, optionIndex: number): void {
            this.inlineSearchOptionIndex = optionIndex;
            this.inlineSearchOpen = true;
            this.inlineSearchQuery = '';
            this.inlineSearchResults = [];
            this.$nextTick?.(() => {
                const input = document.querySelector<HTMLInputElement>('[x-ref="inlineSearchInput"]');
                if (input) input.focus();
            });
        },

        inlineSearch(this: BundleOptionsState): void {
            if (this.inlineSearchQuery.length < 2) {
                this.inlineSearchResults = [];
                return;
            }
            this.inlineSearching = true;
            const opt = this.options[this.inlineSearchOptionIndex];
            const excludeIds = opt ? opt.selections.map((s) => String(s.product_id)).join(',') : '';
            const url =
                this.searchUrl +
                '?q=' +
                encodeURIComponent(this.inlineSearchQuery) +
                '&exclude=' +
                encodeURIComponent(excludeIds) +
                '&limit=20' +
                '&form_key=' +
                encodeURIComponent(this.formKey);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((resp) => resp.json())
                .then((data: SearchResponse) => {
                    this.inlineSearchResults = data.items ?? [];
                    this.inlineSearching = false;
                })
                .catch(() => {
                    this.inlineSearching = false;
                });
        },

        inlineAddProduct(this: BundleOptionsState, product: WizardProduct): void {
            const opt = this.options[this.inlineSearchOptionIndex];
            if (!opt) return;
            opt.selections.push({
                selection_id: '',
                product_id: product.id,
                name: product.name,
                sku: product.sku,
                price: 0,
                price_type: 0,
                qty: 1,
                is_default: false,
                can_change_qty: true,
                position: opt.selections.length,
                thumbnail: product.thumbnail ?? '',
                delete: '',
            });
            this.inlineSearchResults = this.inlineSearchResults.filter((p) => p.id !== product.id);
        },

        removeOption(this: BundleOptionsState, index: number): void {
            const opt = this.options[index];
            if (!opt) return;
            if (opt.option_id) {
                opt.delete = '1';
            } else {
                this.options.splice(index, 1);
            }
        },

        removeSelection(this: BundleOptionsState, optionIndex: number, selectionIndex: number): void {
            const opt = this.options[optionIndex];
            if (!opt) return;
            const sel = opt.selections[selectionIndex];
            if (!sel) return;
            if (sel.selection_id) {
                sel.delete = '1';
            } else {
                opt.selections.splice(selectionIndex, 1);
            }
        },

        toggleDefault(this: BundleOptionsState, optionIndex: number, selectionIndex: number): void {
            const opt = this.options[optionIndex];
            if (!opt) return;
            const isMulti = opt.type === 'checkbox' || opt.type === 'multi';
            if (!isMulti) {
                opt.selections.forEach((s, i) => {
                    s.is_default = i === selectionIndex;
                });
            } else {
                const sel = opt.selections[selectionIndex];
                if (sel) sel.is_default = !sel.is_default;
            }
        },

        get visibleOptions(): BundleOption[] {
            return this.options.filter((o) => o.delete !== '1');
        },
    };
}

function register(): void {
    window.Alpine.data('nebulaBundleOptions', (config: BundleOptionsConfig) =>
        createBundleOptions(config),
    );
}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
