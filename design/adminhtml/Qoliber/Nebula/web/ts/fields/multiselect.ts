/**
 * nebulaField_multiselect — array-valued Alpine component. Serializes with
 * the `[]` suffix convention so Magento POST parsing builds an array.
 *
 * Shape-aware:
 *   - Flat options (rows without `depth`/`group`): dropdown / modal UI.
 *   - Tree options (rows with `depth` or `group`): indented checkbox tree.
 *
 * The phtml sets `config.tree` based on the resolved options. Both branches
 * share state (value, search) and serialization.
 */

import { createBaseField } from './base';
import type { FieldOption, NebulaFieldConfig, SerializedPair } from '../types';

interface MultiselectUiConfig {
    compactThreshold?: number;
}

interface MultiselectUi {
    search: string;
    showDropdown: boolean;
    modalOpen: boolean;
    modalSearchTerm: string;
    compactThreshold: number;
    isSelected(v: unknown): boolean;
    toggle(v: unknown): void;
    $root?: HTMLElement;
}

interface TreeNode {
    value: string | null;
    label: string;
    depth: number;
    group: boolean;
    all: boolean;
}

interface TreeUi {
    search: string;
    nodes: TreeNode[];
    visibleNodes(): TreeNode[];
    /**
     * Apply the user's toggle of a single node to a current selection array,
     * returning the new selection. Enforces "all-vs-specific" exclusivity:
     * checking an `all: true` node clears every other selection; checking
     * any other leaf removes any `all` sentinels from the current selection.
     */
    toggle(currentValue: string[], node: TreeNode): string[];
}

function normalizeTreeNodes(options: FieldOption[]): TreeNode[] {
    return options.map((o) => {
        const raw = o as FieldOption & { depth?: unknown; group?: unknown; all?: unknown };
        return {
            value: raw.value === null || raw.value === undefined ? null : String(raw.value),
            label: String(raw.label ?? ''),
            depth: typeof raw.depth === 'number' ? raw.depth : 0,
            group: raw.group === true,
            all: raw.all === true,
        };
    });
}

/**
 * Pure factory — used both by the Alpine registration and by unit tests.
 * Returns an object whose `visibleNodes()` recomputes from the current
 * `search` value each call (no caching — Alpine re-evaluates expressions
 * automatically and the dataset is small).
 */
export function createTreeUi(options: FieldOption[]): TreeUi {
    const nodes = normalizeTreeNodes(options);

    return {
        search: '',
        nodes,
        visibleNodes(): TreeNode[] {
            const q = this.search.trim().toLowerCase();
            if (q === '') {
                return this.nodes;
            }

            // Step 1: find indexes of leaves whose label matches.
            const matched: number[] = [];
            this.nodes.forEach((n, i) => {
                if (!n.group && n.label.toLowerCase().includes(q)) {
                    matched.push(i);
                }
            });
            if (matched.length === 0) {
                return [];
            }

            // Step 2: for each matched leaf, also include its ancestor headers
            // (any earlier row with depth < this row's depth, walking backwards
            // until a depth-0 header). Keep insertion order.
            const keep = new Set<number>(matched);
            for (const idx of matched) {
                let minDepthFound = this.nodes[idx]?.depth ?? 0;
                for (let j = idx - 1; j >= 0; j--) {
                    const n = this.nodes[j];
                    if (!n) continue;
                    if (n.group && n.depth < minDepthFound) {
                        keep.add(j);
                        minDepthFound = n.depth;
                        if (n.depth === 0) break;
                    }
                }
            }
            const sorted = Array.from(keep).sort((a, b) => a - b);
            return sorted.map((i) => this.nodes[i] as TreeNode);
        },
        toggle(currentValue: string[], node: TreeNode): string[] {
            if (node.value === null) {
                // Group headers aren't selectable — defensive no-op.
                return currentValue;
            }
            const v = node.value;
            const isSelected = currentValue.includes(v);

            if (node.all) {
                // Toggling the "all" sentinel on clears every other selection;
                // off just removes it.
                return isSelected ? currentValue.filter((x) => x !== v) : [v];
            }

            // Toggling a regular leaf: drop any "all" sentinels first, then
            // add/remove this leaf.
            const allValues = new Set(
                this.nodes
                    .filter((n) => n.all && n.value !== null)
                    .map((n) => n.value as string),
            );
            const withoutAll = currentValue.filter((x) => !allValues.has(x));
            if (isSelected) {
                return withoutAll.filter((x) => x !== v);
            }
            return withoutAll.concat(v);
        },
    };
}

export function registerMultiselectField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_multiselect', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            const options: FieldOption[] = Array.isArray(config.options) ? config.options : [];
            const initial: string[] = Array.isArray(config.value) ? (config.value as string[]).slice() : [];
            const tree = (config as NebulaFieldConfig & { tree?: boolean }).tree === true;

            const component = Object.assign(base, {
                options,
                tree,
                treeUi: tree ? createTreeUi(options) : null,
                value: initial as unknown,
                serialize(this: { fieldName: string; disabled: boolean; value: unknown }): SerializedPair[] {
                    if (!this.fieldName || this.disabled) return [];
                    const values = Array.isArray(this.value) ? (this.value as unknown[]) : [];
                    return values.map((v) => ({
                        name: this.fieldName + '[]',
                        value:
                            typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean'
                                ? v
                                : String(v),
                    }));
                },
            });

            return component;
        });

        window.Alpine.data('nebulaMultiselectUi', (opts: MultiselectUiConfig = {}): MultiselectUi => ({
            search: '',
            showDropdown: false,
            modalOpen: false,
            modalSearchTerm: '',
            compactThreshold: opts.compactThreshold ?? 12,

            isSelected(this: MultiselectUi, v: unknown): boolean {
                const root = this.$root as HTMLElement | undefined;
                const parentEl = root?.parentElement?.closest('[x-data]') as HTMLElement | null;
                const parent: { value?: unknown } = parentEl
                    ? ((window.Alpine as unknown as { $data(el: HTMLElement): unknown }).$data(parentEl) as { value?: unknown })
                    : {};
                const values = Array.isArray(parent.value) ? parent.value : [];
                return values.includes(String(v));
            },

            toggle(this: MultiselectUi, raw: unknown): void {
                const root = this.$root as HTMLElement | undefined;
                const parentEl = root?.parentElement?.closest('[x-data]') as HTMLElement | null;
                if (!parentEl) return;
                const parent = (window.Alpine as unknown as { $data(el: HTMLElement): unknown }).$data(parentEl) as { value?: unknown };
                const v = String(raw);
                const current = Array.isArray(parent.value) ? parent.value : [];
                if ((current as string[]).includes(v)) {
                    (parent as { value: string[] }).value = (current as string[]).filter((item) => item !== v);
                } else {
                    (parent as { value: string[] }).value = (current as string[]).concat(v);
                }
            },
        }));
    });
}

export const __testables = { createTreeUi };
