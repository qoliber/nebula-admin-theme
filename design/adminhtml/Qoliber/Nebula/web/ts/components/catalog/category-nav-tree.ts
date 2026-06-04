/**
 * `nebulaCategoryNavTree` Alpine component — sidebar tree for the category
 * edit screen. Handles expand/collapse, navigation guarding, and opt-in
 * drag-to-reorder via Sortable.js (same-parent only, buffered until Save).
 *
 * NOTE: The phtml template (category_navigation_tree.phtml) registers this
 * component inline via vanilla JS with PHP i18n strings and overrides this
 * compiled bundle at runtime. Keep both files in sync.
 */

interface TreeNode {
    id: string | number;
    name?: string;
    is_active?: boolean;
    children?: TreeNode[];
}

interface CategoryNavTreeConfig {
    tree?: TreeNode[];
    expandedIds?: Record<string, boolean>;
    currentId?: string;
    reorderUrl?: string;
    moveUrl?: string;
    deleteBaseUrl?: string;
    formKey?: string;
}

interface ContainerOrderMap {
    [parentId: string]: string[];
}

export function registerCategoryNavTree(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaCategoryNavTree', (config: CategoryNavTreeConfig = {}) => ({
            expanded: { ...(config.expandedIds ?? {}) },
            tree: config.tree ?? [],
            currentId: config.currentId ?? '',
            reorderUrl: config.reorderUrl ?? '',
            moveUrl: config.moveUrl ?? '',
            deleteBaseUrl: config.deleteBaseUrl ?? '',
            formKey: config.formKey ?? '',
            _sortableInstances: [] as unknown[],
            _sortSaving: false,
            sortMode: false,
            sortDirty: false,
            _savedContainerOrder: null as ContainerOrderMap | null,
            _pendingMoves: [] as { id: string; pid: string; aid: string }[],
            _deleteOpen: false,
            _deleteTarget: null as { id: string; name: string } | null,
            _deleteItems: [] as { id: string | number; name: string; depth: number; isActive: boolean }[],

            init(): void {
                // Sortable is not activated on load — user must click Reorder.
            },

            toggleExpand(id: string | number): void {
                const key = String(id);
                this.expanded[key] = !this.expanded[key];
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

            // --- Sort mode ---------------------------------------------------

            enableSortMode(): void {
                this._savedContainerOrder = this._captureContainerOrder();
                this.sortMode = true;
                this.$nextTick(() => this._initAllSortables());
            },

            disableSortMode(): void {
                this._destroyAllSortables();
                this.sortMode = false;
                this.sortDirty = false;
                this._savedContainerOrder = null;
                this._pendingMoves = [];
            },

            cancelSort(): void {
                window.location.reload();
            },

            saveSort(): void {
                this._sortSaving = true;
                this._savePendingChanges().then((success) => {
                    this._sortSaving = false;
                    if (success) {
                        this.disableSortMode();
                    }
                });
            },

            _captureContainerOrder(): ContainerOrderMap {
                const state: ContainerOrderMap = {};
                document.querySelectorAll<HTMLElement>('[data-sort-container]').forEach((el) => {
                    const parentId = el.dataset['parentId'] ?? '';
                    state[parentId] = Array.from(
                        el.querySelectorAll<HTMLElement>(':scope > [data-cat-id]')
                    ).map((child) => child.dataset['catId'] ?? '');
                });
                return state;
            },

            _savePendingChanges(): Promise<boolean> {
                // Step 1: replay cross-parent moves sequentially
                const moveChain = this._pendingMoves.reduce(
                    (chain: Promise<boolean>, move: { id: string; pid: string; aid: string }) =>
                        chain.then((ok) => (ok ? this._moveCategoryAsync(move.id, move.pid, move.aid) : false)),
                    Promise.resolve(true)
                );

                return moveChain.then((movesOk) => {
                    if (!movesOk) {
                        window.nebulaToast?.('error', 'Could not move category.');
                        window.location.reload();
                        return false;
                    }

                    // Step 2: reorder changed same-parent containers in parallel
                    const current = this._captureContainerOrder();
                    const original = this._savedContainerOrder ?? {};
                    const saves: Promise<boolean>[] = [];

                    document.querySelectorAll<HTMLElement>('[data-sort-container]').forEach((el) => {
                        const parentId = el.dataset['parentId'] ?? '';
                        const currentIds = current[parentId] ?? [];
                        const originalIds = original[parentId] ?? [];
                        if (JSON.stringify(currentIds) !== JSON.stringify(originalIds)) {
                            saves.push(this._reorderSiblingsAsync(parentId, currentIds));
                        }
                    });

                    if (saves.length === 0) {
                        window.nebulaToast?.('success', 'Category order saved.');
                        return true;
                    }

                    return Promise.all(saves).then((results) => {
                        const allOk = results.every(Boolean);
                        if (allOk) {
                            window.nebulaToast?.('success', 'Category order saved.');
                        } else {
                            window.nebulaToast?.('error', 'Some categories could not be saved.');
                        }
                        return allOk;
                    });
                });
            },

            _moveCategoryAsync(id: string, pid: string, aid: string): Promise<boolean> {
                const body = new URLSearchParams({ id, pid, aid, form_key: this.formKey as string });
                return fetch(this.moveUrl as string, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                })
                    .then((r) => r.json() as Promise<{ error: boolean; messages?: string }>)
                    .then((data) => {
                        if (data.error && data.messages) {
                            const tmp = document.createElement('div');
                            tmp.innerHTML = data.messages;
                            const text = (tmp.textContent ?? tmp.innerText ?? '').trim();
                            if (text) window.nebulaToast?.('error', text);
                        }
                        return !data.error;
                    })
                    .catch(() => false);
            },

            _reorderSiblingsAsync(parentId: string, ids: string[]): Promise<boolean> {
                const url = (this.reorderUrl as string) + '?form_key=' + encodeURIComponent(this.formKey as string);
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ parent_id: parentId, ids }),
                })
                    .then((r) => r.json() as Promise<{ success: boolean }>)
                    .then((data) => !!data.success)
                    .catch(() => false);
            },

            // --- Navigation guard --------------------------------------------

            async confirmCategorySwitch(url: string): Promise<void> {
                if (this.sortMode && this.sortDirty) {
                    window.nebulaToast?.('error', 'Save or cancel the reorder first.');
                    return;
                }

                if (this.sortMode && !this.sortDirty) {
                    this.disableSortMode();
                }

                if (!this.hasUnsavedChanges()) {
                    this.closeCategoryDrawer();
                    window.location.href = url;
                    return;
                }

                const confirmed = await window.Nebula?.confirm?.({
                    title: 'Discard unsaved changes?',
                    message: 'You have edits that will be lost if you leave this page.',
                    danger: true,
                    confirmText: 'Discard',
                    cancelText: 'Stay',
                });
                if (confirmed) {
                    this.closeCategoryDrawer();
                    window.location.href = url;
                }
            },

            hasUnsavedChanges(): boolean {
                if (window.Nebula && typeof window.Nebula.hasUnsavedCategoryChanges === 'function') {
                    return window.Nebula.hasUnsavedCategoryChanges();
                }
                return false;
            },

            closeCategoryDrawer(): void {
                const drawerRoot = document.querySelector<HTMLElement>('[data-nebula-category-drawer]');
                if (!drawerRoot || !window.Alpine || typeof Alpine.$data !== 'function') return;
                try {
                    const drawerData = Alpine.$data(
                        drawerRoot as Parameters<typeof Alpine.$data>[0],
                    ) as { closeDrawer?: () => void };
                    drawerData?.closeDrawer?.();
                } catch {
                    // Ignore drawer close failures
                }
            },

            // --- Drag-to-reorder (same-parent only) --------------------------

            _initAllSortables(): void {
                this._destroyAllSortables();
                document.querySelectorAll<HTMLElement>('[data-sort-container]').forEach((el) => {
                    if (!window.Sortable) return;
                    const instance = window.Sortable.create(el, {
                        group: { name: 'cat-tree', pull: true, put: true },
                        handle: '[data-cat-drag-handle]',
                        draggable: '[data-cat-id]',
                        animation: 150,
                        ghostClass: 'opacity-40',
                        onEnd: (evt) => this._onSortEnd(evt as unknown as SortableEvent),
                    });
                    (this._sortableInstances as unknown[]).push(instance);
                });
            },

            _destroyAllSortables(): void {
                for (const s of this._sortableInstances as { destroy?: () => void }[]) {
                    s?.destroy?.();
                }
                this._sortableInstances = [];
            },

            _onSortEnd(evt: SortableEvent): void {
                if (!evt.item || !evt.from || !evt.to) return;
                const movedId = (evt.item as HTMLElement).dataset['catId'];
                if (!movedId) return;

                const fromParentId = (evt.from as HTMLElement).dataset['parentId'];
                const toParentId = (evt.to as HTMLElement).dataset['parentId'] ?? '';

                if (fromParentId !== toParentId) {
                    if (movedId === toParentId || this._isAncestorOf(movedId, toParentId)) {
                        (evt.from as HTMLElement).insertBefore(
                            evt.item as HTMLElement,
                            (evt.from as HTMLElement).children[evt.oldIndex ?? 0] ?? null
                        );
                        window.nebulaToast?.('error', 'A category cannot be moved into one of its own subcategories.');
                        return;
                    }
                    const siblings = Array.from(
                        (evt.to as HTMLElement).querySelectorAll<HTMLElement>(':scope > [data-cat-id]')
                    );
                    const newIdx = siblings.indexOf(evt.item as HTMLElement);
                    const aid = newIdx > 0 ? (siblings[newIdx - 1]!.dataset['catId'] ?? '0') : '0';
                    this._pendingMoves.push({ id: movedId, pid: toParentId, aid });
                }

                this.sortDirty = true;
                // Same-parent: handled by _savePendingChanges() on Save
            },

            _findNodeInTree(id: string | number, nodes?: TreeNode[]): TreeNode | null {
                const list = nodes ?? this.tree;
                for (const node of list) {
                    if (String(node.id) === String(id)) return node;
                    if (node.children?.length) {
                        const found = this._findNodeInTree(id, node.children);
                        if (found) return found;
                    }
                }
                return null;
            },

            _countDescendants(id: string | number): number {
                const node = this._findNodeInTree(id);
                if (!node) return 0;
                const count = (n: TreeNode): number => {
                    let total = 0;
                    for (const child of n.children ?? []) {
                        total += 1 + count(child);
                    }
                    return total;
                };
                return count(node);
            },

            _isAncestorOf(ancestorId: string | number, descendantId: string | number): boolean {
                const ancestor = this._findNodeInTree(ancestorId);
                if (!ancestor) return false;
                const has = (n: TreeNode, targetId: string): boolean => {
                    for (const child of n.children ?? []) {
                        if (String(child.id) === targetId) return true;
                        if (has(child, targetId)) return true;
                    }
                    return false;
                };
                return has(ancestor, String(descendantId));
            },

            confirmDeleteCategory(id: string, name: string): void {
                const items: { id: string | number; name: string; depth: number; isActive: boolean }[] = [];
                const flatten = (node: TreeNode, depth: number): void => {
                    items.push({
                        id: node.id,
                        name: node.name ?? '',
                        depth,
                        isActive: node.is_active !== false,
                    });
                    for (const child of node.children ?? []) {
                        flatten(child, depth + 1);
                    }
                };
                const node = this._findNodeInTree(id);
                if (node) flatten(node, 0);
                this._deleteTarget = { id, name };
                this._deleteItems = items;
                this._deleteOpen = true;
            },

            _cancelDelete(): void {
                this._deleteOpen = false;
                this._deleteTarget = null;
                this._deleteItems = [];
            },

            _executeDelete(): void {
                if (this._deleteTarget) {
                    window.location.href = (this.deleteBaseUrl as string) + 'id/' + this._deleteTarget.id + '/';
                }
            },
        }));
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}

interface SortableEvent {
    item?: Node | HTMLElement | null;
    from?: Element | null;
    to?: Element | null;
    oldIndex?: number;
    newIndex?: number;
}
