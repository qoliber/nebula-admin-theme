/**
 * nebulaPageBuilder — visual content tree editor with drag/drop.
 */

interface ContentType {
    defaults?: Record<string, unknown>;
    parents?: {
        defaultPolicy?: 'allow' | 'deny';
        allow?: string[];
    };
}

interface PbNode {
    id: string;
    type: string;
    appearance: string;
    data: Record<string, unknown>;
    children: PbNode[];
}

interface DropTarget {
    parentType: string;
    parentId: string;
}

interface PbConfig {
    contentTypes?: Record<string, ContentType>;
    renderUrl?: string;
    parseUrl?: string;
    fieldName?: string;
    initialHtml?: string;
    formKey?: string;
}

interface RenderResponse {
    html?: string;
}

interface ParseResponse {
    tree?: PbNode[];
}

interface PageBuilderState {
    contentTypes: Record<string, ContentType>;
    renderUrl: string;
    parseUrl: string;
    fieldName: string;
    formKey: string;
    tree: PbNode[];
    masterHtml: string;
    editingNodeId: string | null;
    syncing: boolean;
    editorOpen: boolean;
    _submitPending: boolean;
    dragType: string | null;
    dragNodeId: string | null;
    dropTarget: DropTarget | null;
    initPageBuilder(): void;
    generateId(): string;
    getContentType(type: string): ContentType | null;
    panelDragStart(e: DragEvent, type: string): void;
    panelDragEnd(): void;
    nodeDragStart(e: DragEvent, nodeId: string): void;
    nodeDragEnd(e: DragEvent): void;
    zoneEnter(e: DragEvent, parentType: string, parentId: string): void;
    zoneOver(e: DragEvent): void;
    zoneLeave(e: DragEvent, parentType: string, parentId: string): void;
    zoneDrop(e: DragEvent, parentType: string, parentId: string): void;
    isDropTarget(parentType: string, parentId: string): boolean;
    createNode(type: string): PbNode;
    addToStage(type: string): void;
    addNodeToParent(type: string, parent: PbNode | null): void;
    findNode(id: string, nodes?: PbNode[]): PbNode | null;
    findParentAndIndex(
        id: string,
        nodes?: PbNode[],
        parent?: PbNode | null,
    ): { parent: PbNode | null; index: number; siblings: PbNode[] } | null;
    deleteNode(id: string): void;
    duplicateNode(id: string): void;
    reassignIds(node: PbNode): void;
    editNode(id: string): void;
    getEditingNode(): PbNode | null;
    updateNodeData(id: string, key: string, value: unknown): void;
    closeEditPanel(): void;
    closeEditor(): void;
    getNodePreviewStyles(node: PbNode): string;
    getHeadingClass(tag: string): string;
    syncMasterFormat(): Promise<void>;
    parseHtml(html: string): Promise<void>;
    $refs?: Record<string, HTMLElement>;
    $el?: HTMLElement;
}

export function createPageBuilder(): PageBuilderState {
    const state: PageBuilderState = {
        contentTypes: {},
        renderUrl: '',
        parseUrl: '',
        fieldName: 'content',
        formKey: '',
        tree: [],
        masterHtml: '',
        editingNodeId: null,
        syncing: false,
        editorOpen: false,
        _submitPending: false,

        dragType: null,
        dragNodeId: null,
        dropTarget: null,

        initPageBuilder(this: PageBuilderState): void {
            const configEl = this.$refs?.['pbConfig'];
            if (configEl) {
                try {
                    const cfg = JSON.parse(configEl.textContent ?? '{}') as PbConfig;
                    this.contentTypes = cfg.contentTypes ?? {};
                    this.renderUrl = cfg.renderUrl ?? '';
                    this.parseUrl = cfg.parseUrl ?? '';
                    this.fieldName = cfg.fieldName ?? 'content';
                    this.masterHtml = cfg.initialHtml ?? '';
                    this.formKey = cfg.formKey ?? '';
                    if (!this.formKey) {
                        const fkInput = document.querySelector<HTMLInputElement>(
                            'input[name="form_key"]',
                        );
                        if (fkInput) this.formKey = fkInput.value;
                    }
                } catch (e) {
                    // eslint-disable-next-line no-console
                    console.error('PageBuilder: config parse failed', e);
                }
            }

            if (
                this.masterHtml &&
                this.masterHtml.trim() !== '' &&
                this.masterHtml.indexOf('data-content-type') !== -1
            ) {
                void this.parseHtml(this.masterHtml);
            }

            const form = this.$el?.closest<HTMLFormElement>('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    if (state._submitPending) return;
                    if (state.tree.length === 0) {
                        state.masterHtml = '';
                        return;
                    }
                    e.preventDefault();
                    void state.syncMasterFormat().then(() => {
                        state._submitPending = true;
                        form.submit();
                    });
                });
            }
        },

        generateId(): string {
            return 'pb_' + Math.random().toString(36).substring(2, 14);
        },

        getContentType(this: PageBuilderState, type: string): ContentType | null {
            return this.contentTypes[type] ?? null;
        },

        panelDragStart(this: PageBuilderState, e: DragEvent, type: string): void {
            this.dragType = type;
            this.dragNodeId = null;
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', 'pb:' + type);
            }
            const target = e.target as HTMLElement | null;
            if (target && typeof target.cloneNode === 'function') {
                const ghost = target.cloneNode(true) as HTMLElement;
                ghost.style.width = '80px';
                ghost.style.opacity = '0.8';
                document.body.appendChild(ghost);
                e.dataTransfer?.setDragImage(ghost, 40, 20);
                setTimeout(() => {
                    document.body.removeChild(ghost);
                }, 0);
            }
        },

        panelDragEnd(this: PageBuilderState): void {
            this.dragType = null;
            this.dropTarget = null;
        },

        nodeDragStart(this: PageBuilderState, e: DragEvent, nodeId: string): void {
            this.dragNodeId = nodeId;
            this.dragType = null;
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'pb-node:' + nodeId);
            }
            const target = e.target as HTMLElement | null;
            if (target) target.style.opacity = '0.5';
        },

        nodeDragEnd(this: PageBuilderState, e: DragEvent): void {
            this.dragNodeId = null;
            this.dropTarget = null;
            const target = e.target as HTMLElement | null;
            if (target) target.style.opacity = '';
        },

        zoneEnter(this: PageBuilderState, e: DragEvent, parentType: string, parentId: string): void {
            e.preventDefault();
            e.stopPropagation();
            this.dropTarget = { parentType, parentId };
        },

        zoneOver(this: PageBuilderState, e: DragEvent): void {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = this.dragNodeId ? 'move' : 'copy';
            }
        },

        zoneLeave(this: PageBuilderState, e: DragEvent, _parentType: string, parentId: string): void {
            const related = e.relatedTarget as Node | null;
            const current = e.currentTarget as HTMLElement | null;
            if (related && current?.contains(related)) return;
            if (this.dropTarget && this.dropTarget.parentId === parentId) {
                this.dropTarget = null;
            }
        },

        zoneDrop(this: PageBuilderState, e: DragEvent, parentType: string, parentId: string): void {
            e.preventDefault();
            e.stopPropagation();
            this.dropTarget = null;

            if (this.dragType) {
                const type = this.dragType;
                this.dragType = null;
                if (parentType === 'stage') {
                    this.addToStage(type);
                } else if (parentId) {
                    this.addNodeToParent(type, this.findNode(parentId));
                }
            } else if (this.dragNodeId) {
                const id = this.dragNodeId;
                this.dragNodeId = null;
                const info = this.findParentAndIndex(id);
                if (!info) return;
                const node = info.siblings.splice(info.index, 1)[0];
                if (!node) return;
                if (parentType === 'stage') {
                    this.tree.push(node);
                } else {
                    const p = this.findNode(parentId);
                    if (p) {
                        if (!p.children) p.children = [];
                        p.children.push(node);
                    }
                }
            }
        },

        isDropTarget(this: PageBuilderState, parentType: string, parentId: string): boolean {
            if (!this.dropTarget) return false;
            return this.dropTarget.parentType === parentType && this.dropTarget.parentId === parentId;
        },

        createNode(this: PageBuilderState, type: string): PbNode {
            const ct = this.getContentType(type);
            const defaults = (ct?.defaults ?? {}) as Record<string, unknown>;
            const appearance = (defaults['appearance'] as string | undefined) ?? 'default';
            const node: PbNode = {
                id: this.generateId(),
                type,
                appearance,
                data: { ...defaults, appearance },
                children: [],
            };
            if (type === 'column-group') {
                node.children = [
                    {
                        id: this.generateId(),
                        type: 'column-line',
                        appearance: 'default',
                        data: { appearance: 'default' },
                        children: [
                            {
                                id: this.generateId(),
                                type: 'column',
                                appearance: 'full-height',
                                data: { appearance: 'full-height', width: '50%' },
                                children: [],
                            },
                            {
                                id: this.generateId(),
                                type: 'column',
                                appearance: 'full-height',
                                data: { appearance: 'full-height', width: '50%' },
                                children: [],
                            },
                        ],
                    },
                ];
            }
            if (type === 'buttons') {
                node.children = [
                    {
                        id: this.generateId(),
                        type: 'button-item',
                        appearance: 'default',
                        data: {
                            appearance: 'default',
                            button_text: 'Button',
                            link_url: '#',
                            button_type: 'primary',
                        },
                        children: [],
                    },
                ];
            }
            return node;
        },

        addToStage(this: PageBuilderState, type: string): void {
            const ct = this.getContentType(type);
            if (!ct) return;
            const rules = ct.parents ?? {};
            if (rules.defaultPolicy === 'deny' && !(rules.allow ?? []).includes('stage')) {
                const last = this.tree[this.tree.length - 1];
                if (this.tree.length > 0 && last?.type === 'row') {
                    this.addNodeToParent(type, last);
                }
                return;
            }
            this.tree.push(this.createNode(type));
        },

        addNodeToParent(this: PageBuilderState, type: string, parent: PbNode | null): void {
            if (!parent) return;
            if (!parent.children) parent.children = [];
            parent.children.push(this.createNode(type));
        },

        findNode(this: PageBuilderState, id: string, nodes?: PbNode[]): PbNode | null {
            const ns = nodes ?? this.tree;
            for (const n of ns) {
                if (n.id === id) return n;
                if (n.children) {
                    const f = this.findNode(id, n.children);
                    if (f) return f;
                }
            }
            return null;
        },

        findParentAndIndex(
            this: PageBuilderState,
            id: string,
            nodes?: PbNode[],
            parent: PbNode | null = null,
        ): { parent: PbNode | null; index: number; siblings: PbNode[] } | null {
            const ns = nodes ?? this.tree;
            for (let i = 0; i < ns.length; i++) {
                const node = ns[i];
                if (!node) continue;
                if (node.id === id) return { parent, index: i, siblings: ns };
                if (node.children) {
                    const f = this.findParentAndIndex(id, node.children, node);
                    if (f) return f;
                }
            }
            return null;
        },

        deleteNode(this: PageBuilderState, id: string): void {
            const info = this.findParentAndIndex(id);
            if (info) {
                info.siblings.splice(info.index, 1);
                if (this.editingNodeId === id) this.editingNodeId = null;
            }
        },

        duplicateNode(this: PageBuilderState, id: string): void {
            const info = this.findParentAndIndex(id);
            if (!info) return;
            const source = info.siblings[info.index];
            if (!source) return;
            const clone = JSON.parse(JSON.stringify(source)) as PbNode;
            this.reassignIds(clone);
            info.siblings.splice(info.index + 1, 0, clone);
        },

        reassignIds(this: PageBuilderState, node: PbNode): void {
            node.id = this.generateId();
            if (node.children) {
                for (const child of node.children) this.reassignIds(child);
            }
        },

        editNode(this: PageBuilderState, id: string): void {
            this.editingNodeId = id;
        },

        getEditingNode(this: PageBuilderState): PbNode | null {
            return this.editingNodeId ? this.findNode(this.editingNodeId) : null;
        },

        updateNodeData(this: PageBuilderState, id: string, key: string, value: unknown): void {
            const n = this.findNode(id);
            if (n) {
                if (!n.data) n.data = {};
                n.data[key] = value;
            }
        },

        closeEditPanel(this: PageBuilderState): void {
            this.editingNodeId = null;
        },

        closeEditor(this: PageBuilderState): void {
            this.editingNodeId = null;
            this.editorOpen = false;
            if (this.tree.length > 0) {
                void this.syncMasterFormat();
            } else {
                this.masterHtml = '';
            }
        },

        getNodePreviewStyles(_node: PbNode): string {
            const d = (_node.data ?? {}) as Record<string, unknown>;
            const s: string[] = [];
            if (d['background_color']) s.push('background-color:' + String(d['background_color']));
            if (d['min_height']) s.push('min-height:' + String(d['min_height']));
            if (d['text_align']) s.push('text-align:' + String(d['text_align']));
            return s.join(';');
        },

        getHeadingClass(tag: string): string {
            const map: Record<string, string> = {
                h1: 'text-3xl font-bold',
                h2: 'text-2xl font-bold',
                h3: 'text-xl font-semibold',
                h4: 'text-lg font-semibold',
                h5: 'text-base font-medium',
                h6: 'text-sm font-medium',
            };
            return map[tag] ?? 'text-2xl font-bold';
        },

        async syncMasterFormat(this: PageBuilderState): Promise<void> {
            this.syncing = true;
            try {
                const url = this.renderUrl + (this.renderUrl.includes('?') ? '&' : '?')
                    + 'form_key=' + encodeURIComponent(this.formKey);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.tree),
                });
                if (!r.ok) throw new Error('Render ' + String(r.status));
                const d = (await r.json()) as RenderResponse;
                if (d.html !== undefined) this.masterHtml = d.html;
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error('Sync error:', e);
            } finally {
                this.syncing = false;
            }
        },

        async parseHtml(this: PageBuilderState, html: string): Promise<void> {
            try {
                const url = this.parseUrl + (this.parseUrl.includes('?') ? '&' : '?')
                    + 'form_key=' + encodeURIComponent(this.formKey);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ html }),
                });
                if (!r.ok) throw new Error('Parse ' + String(r.status));
                const d = (await r.json()) as ParseResponse;
                if (d.tree) this.tree = d.tree;
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error('Parse error:', e);
            }
        },
    };

    return state;
}

export function registerPageBuilder(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaPageBuilder', () => createPageBuilder());
    });
}

registerPageBuilder();
