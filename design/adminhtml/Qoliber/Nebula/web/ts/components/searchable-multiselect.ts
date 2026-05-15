/**
 * `nebulaMultiselect` Alpine component — searchable multi-select with a
 * compact inline dropdown and a full-browse modal. Extracted from the
 * inline `<script>` in
 * `NebulaComponent/view/adminhtml/templates/snippet/searchable_multiselect.phtml`.
 */

interface MultiselectOption {
    value: string | number;
    label: string;
    path?: string;
}

interface MultiselectConfig {
    fieldName?: string;
    options?: MultiselectOption[];
    selected?: Array<string | number>;
    label?: string;
}

export function registerSearchableMultiselect(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaMultiselect', (config: MultiselectConfig = {}) => ({
            fieldName: config.fieldName ?? '',
            allOptions: config.options ?? [],
            selectedValues: (config.selected ?? []).map(String),
            label: config.label ?? 'Select Options',
            search: '',
            showDropdown: false,
            modalOpen: false,
            modalSearchTerm: '',

            get inlineFilteredOptions(): MultiselectOption[] {
                if (!this.search) return [];
                const term = this.search.toLowerCase();
                return this.allOptions
                    .filter((o) => (o.label ?? '').toLowerCase().includes(term)
                        || (o.path ?? '').toLowerCase().includes(term))
                    .slice(0, 50);
            },

            get modalFilteredOptions(): MultiselectOption[] {
                if (!this.modalSearchTerm) return this.allOptions;
                const term = this.modalSearchTerm.toLowerCase();
                return this.allOptions.filter((o) => (o.label ?? '').toLowerCase().includes(term)
                    || (o.path ?? '').toLowerCase().includes(term));
            },

            get selectedItems(): MultiselectOption[] {
                return this.selectedValues
                    .map((v) => this.allOptions.find((o) => String(o.value) === String(v)))
                    .filter((o): o is MultiselectOption => o !== undefined);
            },

            isSelected(value: string | number): boolean {
                return this.selectedValues.includes(String(value));
            },

            toggle(value: string | number): void {
                const key = String(value);
                if (this.isSelected(key)) {
                    this.selectedValues = this.selectedValues.filter((v) => v !== key);
                } else {
                    this.selectedValues.push(key);
                }
            },

            selectAll(): void {
                this.modalFilteredOptions.forEach((opt) => {
                    const v = String(opt.value);
                    if (!this.isSelected(v)) this.selectedValues.push(v);
                });
            },

            deselectAll(): void {
                const visible = this.modalFilteredOptions.map((o) => String(o.value));
                this.selectedValues = this.selectedValues.filter((v) => !visible.includes(v));
            },

            openModal(): void {
                this.modalSearchTerm = '';
                this.modalOpen = true;
                this.$nextTick(() => {
                    const input = this.$refs.modalSearch as HTMLInputElement | undefined;
                    input?.focus();
                });
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
