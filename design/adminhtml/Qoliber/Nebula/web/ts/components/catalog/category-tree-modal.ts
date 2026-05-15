/**
 * `nebulaCategoryTreeModal` Alpine component.
 *
 * Pairs a browse-button + modal tree UI with a sibling `nebulaMultiselect`
 * component so ticking a category in the tree also ticks it in the selector.
 * Migrated from `NebulaCatalog/view/adminhtml/templates/eav/snippet/category_tree.phtml`
 * which used to ship this as an inline `<script>`.
 */

interface TreeNode {
    id: string | number;
    name: string;
    children?: TreeNode[];
    is_active?: boolean;
}

interface CategoryTreeModalConfig {
    tree?: TreeNode[];
    expandedIds?: Record<string, boolean>;
}

interface MultiselectLike {
    selectedValues: string[];
}

interface NodeDataset {
    nodeId?: string;
    searchText?: string;
}

export function registerCategoryTreeModal(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaCategoryTreeModal', (config: CategoryTreeModalConfig = {}) => ({
            treeOpen: false,
            treeSearch: '',
            expanded: { ...(config.expandedIds ?? {}) } as Record<string, boolean>,
            tree: config.tree ?? [],

            toggleExpand(id: string | number): void {
                const key = String(id);
                this.expanded[key] = !this.expanded[key];
            },

            /**
             * Locate the sibling searchable-multiselect Alpine state. The
             * multiselect's factory is named `nebulaMultiselect` (an earlier
             * version looked for `nebulaSearchMultiselect`, which never
             * matched anything → checkbox toggles in the modal didn't sync
             * back to the multiselect → category IDs were lost on save).
             * Walks up from $el looking for any ancestor that contains the
             * multiselect — works regardless of where the snippet sits.
             */
            getMultiselect(): MultiselectLike | null {
                let node: HTMLElement | null = this.$el as HTMLElement;
                while (node && node !== document.body.parentElement) {
                    const ms = node.querySelector?.('[x-data^="nebulaMultiselect"]') as HTMLElement | null;
                    if (ms) {
                        return window.Alpine?.$data(ms) as MultiselectLike;
                    }
                    node = node.parentElement;
                }
                return null;
            },

            isChecked(id: string | number): boolean {
                const ms = this.getMultiselect();
                return !!ms?.selectedValues.includes(String(id));
            },

            toggleCategory(id: string | number): void {
                const ms = this.getMultiselect();
                if (!ms) return;
                const key = String(id);
                if (ms.selectedValues.includes(key)) {
                    ms.selectedValues = ms.selectedValues.filter((v) => v !== key);
                } else {
                    ms.selectedValues.push(key);
                }
            },

            /**
             * Show a node when the search box is empty OR the node's subtree
             * text (data-search-text on the wrapping element, set server-side)
             * contains the lowercased query. Auto-expand matching branches so
             * hits in collapsed subtrees become visible.
             */
            matchesSearch(el: HTMLElement): boolean {
                if (!this.treeSearch) return true;
                const dataset = el.dataset as NodeDataset;
                const q = this.treeSearch.toLowerCase();
                const text = (dataset.searchText ?? '').toLowerCase();
                const matched = text.indexOf(q) !== -1;
                if (matched && dataset.nodeId) {
                    this.expanded[dataset.nodeId] = true;
                }
                return matched;
            },

            clearSearch(): void {
                this.treeSearch = '';
            },

            expandAll(): void {
                const walk = (nodes: TreeNode[]): void => {
                    for (const n of nodes) {
                        if (n.children && n.children.length) {
                            this.expanded[String(n.id)] = true;
                            walk(n.children);
                        }
                    }
                };
                walk(this.tree);
            },

            collapseAll(): void {
                this.expanded = {};
            },
        }));
    };

    document.addEventListener('alpine:init', install);
}
