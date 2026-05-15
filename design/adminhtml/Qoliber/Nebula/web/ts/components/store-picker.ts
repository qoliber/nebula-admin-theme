/**
 * nebulaStorePicker — Alpine factory powering hierarchical store selection in tree-aware
 * multiselect fields (formerly used by the store_view_picker snippet, now integrated into
 * the nebula.store.view options source).
 *
 * Owns the array of selected store ids, exposes:
 *   value          : string[] — selected ids (serialises into store_id[] hidden inputs)
 *   search         : string   — current filter text
 *   isSelected(v)  : checks membership
 *   toggle(v)      : toggles a single value
 *   visibleNodes   : tree nodes filtered by `search` — group rows stay when ANY
 *                    descendant store matches so the hierarchy reads correctly
 */

interface StoreNode {
    depth: number;
    label: string;
    value: string | null;
    group: boolean;
}

interface StorePickerConfig {
    fieldName: string;
    value: string[];
    nodes: StoreNode[];
    required: boolean;
}

interface StorePickerState {
    fieldName: string;
    value: string[];
    nodes: (StoreNode & { key: string })[];
    search: string;
    readonly visibleNodes: (StoreNode & { key: string })[];
    isSelected(v: string | null): boolean;
    toggle(v: string | null): void;
}

function createStorePicker(config: StorePickerConfig): StorePickerState {
    // Stable keys so x-for doesn't shuffle when search filters change.
    const rawNodes = Array.isArray(config?.nodes) ? config.nodes : [];
    const nodes = rawNodes.map((n, i) => ({ ...n, key: `${n.depth}:${n.value ?? 'g'}:${i}` }));
    const initial = Array.isArray(config?.value) ? config.value.map(String) : [];

    return {
        fieldName: config?.fieldName ?? 'store_id',
        value: initial,
        nodes,
        search: '',

        get visibleNodes() {
            const q = this.search.trim().toLowerCase();
            if (q === '') return this.nodes;

            // Walk in reverse so a group's "visibility" can be set when we
            // see a matching descendant. Track which group depths are
            // currently "live" — they should be retained.
            const keepStores: boolean[] = this.nodes.map((n) =>
                !n.group && n.label.toLowerCase().includes(q),
            );
            const keepGroups: boolean[] = this.nodes.map(() => false);

            // For each kept store, mark all ancestor groups (shallower depth
            // earlier in the array) as kept too.
            for (let i = 0; i < this.nodes.length; i++) {
                if (!keepStores[i]) continue;
                const storeDepth = this.nodes[i]!.depth;
                for (let j = i - 1; j >= 0; j--) {
                    const node = this.nodes[j]!;
                    if (!node.group) continue;
                    if (node.depth < storeDepth) {
                        keepGroups[j] = true;
                        // shallower group found; keep walking up for parents
                    }
                }
            }

            return this.nodes.filter((_, i) => keepStores[i] || keepGroups[i]);
        },

        isSelected(v: string | null): boolean {
            return v !== null && this.value.includes(v);
        },

        toggle(v: string | null): void {
            if (v === null) return;
            const i = this.value.indexOf(v);
            if (i === -1) this.value.push(v);
            else this.value.splice(i, 1);
        },
    };
}

export function registerStorePicker(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaStorePicker', (config: StorePickerConfig) => createStorePicker(config));
    });
}
