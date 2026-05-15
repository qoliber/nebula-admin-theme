document.addEventListener('alpine:init', () => {
    'use strict';

    Alpine.data('nebulaPageBuilder', () => {
        return {
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

            // Drag state
            dragType: null,      // content type name from panel
            dragNodeId: null,    // node id being moved
            dropTarget: null,    // {parentType, parentId} of current hover

            initPageBuilder: function () {
                const configEl = this.$refs.pbConfig;
                if (configEl) {
                    try {
                        const cfg = JSON.parse(configEl.textContent);
                        this.contentTypes = cfg.contentTypes || {};
                        this.renderUrl = cfg.renderUrl || '';
                        this.parseUrl = cfg.parseUrl || '';
                        this.fieldName = cfg.fieldName || 'content';
                        this.masterHtml = cfg.initialHtml || '';
                        this.formKey = cfg.formKey || '';
                        if (!this.formKey) {
                            const fkInput = document.querySelector('input[name="form_key"]');
                            if (fkInput) this.formKey = fkInput.value;
                        }
                    } catch (e) {
                        console.error('PageBuilder: config parse failed', e);
                    }
                }

                if (this.masterHtml && this.masterHtml.trim() !== '' && this.masterHtml.indexOf('data-content-type') !== -1) {
                    this.parseHtml(this.masterHtml);
                }

                const self = this;
                const form = this.$el.closest('form');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        if (self._submitPending) return;
                        if (self.tree.length === 0) { self.masterHtml = ''; return; }
                        e.preventDefault();
                        self.syncMasterFormat().then(() => {
                            self._submitPending = true;
                            form.submit();
                        });
                    });
                }
            },

            generateId: function () {
                return 'pb_' + Math.random().toString(36).substr(2, 12);
            },

            getContentType: function (type) {
                return this.contentTypes[type] || null;
            },

            // ── Drag & Drop ──
            panelDragStart: function (e, type) {
                this.dragType = type;
                this.dragNodeId = null;
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', 'pb:' + type);
                // Shrink drag image
                if (e.target.cloneNode) {
                    const ghost = e.target.cloneNode(true);
                    ghost.style.width = '80px';
                    ghost.style.opacity = '0.8';
                    document.body.appendChild(ghost);
                    e.dataTransfer.setDragImage(ghost, 40, 20);
                    setTimeout(() => { document.body.removeChild(ghost); }, 0);
                }
            },

            panelDragEnd: function () {
                this.dragType = null;
                this.dropTarget = null;
            },

            nodeDragStart: function (e, nodeId) {
                this.dragNodeId = nodeId;
                this.dragType = null;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'pb-node:' + nodeId);
                e.target.style.opacity = '0.5';
            },

            nodeDragEnd: function (e) {
                this.dragNodeId = null;
                this.dropTarget = null;
                e.target.style.opacity = '';
            },

            zoneEnter: function (e, parentType, parentId) {
                e.preventDefault();
                e.stopPropagation();
                this.dropTarget = { parentType: parentType, parentId: parentId };
            },

            zoneOver: function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = this.dragNodeId ? 'move' : 'copy';
            },

            zoneLeave: function (e, parentType, parentId) {
                // Only clear if actually leaving this zone (not entering a child)
                const related = e.relatedTarget;
                if (related && e.currentTarget.contains(related)) return;
                if (this.dropTarget && this.dropTarget.parentId === parentId) {
                    this.dropTarget = null;
                }
            },

            zoneDrop: function (e, parentType, parentId) {
                e.preventDefault();
                e.stopPropagation();
                this.dropTarget = null;

                if (this.dragType) {
                    // New from panel
                    const type = this.dragType;
                    this.dragType = null;
                    if (parentType === 'stage') {
                        this.addToStage(type);
                    } else if (parentId) {
                        this.addNodeToParent(type, this.findNode(parentId));
                    }
                } else if (this.dragNodeId) {
                    // Move existing
                    const id = this.dragNodeId;
                    this.dragNodeId = null;
                    const info = this.findParentAndIndex(id);
                    if (!info) return;
                    const node = info.siblings.splice(info.index, 1)[0];
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

            isDropTarget: function (parentType, parentId) {
                if (!this.dropTarget) return false;
                return this.dropTarget.parentType === parentType && this.dropTarget.parentId === parentId;
            },

            // ── Node CRUD ──
            createNode: function (type) {
                const ct = this.getContentType(type);
                const defaults = ct ? (ct.defaults || {}) : {};
                const appearance = defaults.appearance || 'default';
                const node = {
                    id: this.generateId(),
                    type: type,
                    appearance: appearance,
                    data: Object.assign({}, defaults, { appearance: appearance }),
                    children: []
                };
                if (type === 'column-group') {
                    node.children = [{
                        id: this.generateId(),
                        type: 'column-line',
                        appearance: 'default',
                        data: { appearance: 'default' },
                        children: [
                            { id: this.generateId(), type: 'column', appearance: 'full-height', data: { appearance: 'full-height', width: '50%' }, children: [] },
                            { id: this.generateId(), type: 'column', appearance: 'full-height', data: { appearance: 'full-height', width: '50%' }, children: [] }
                        ]
                    }];
                }
                if (type === 'buttons') {
                    node.children = [
                        { id: this.generateId(), type: 'button-item', appearance: 'default', data: { appearance: 'default', button_text: 'Button', link_url: '#', button_type: 'primary' }, children: [] }
                    ];
                }
                return node;
            },

            addToStage: function (type) {
                const ct = this.getContentType(type);
                if (!ct) return;
                const rules = ct.parents || {};
                if (rules.defaultPolicy === 'deny' && (rules.allow || []).indexOf('stage') === -1) {
                    if (this.tree.length > 0 && this.tree[this.tree.length - 1].type === 'row') {
                        this.addNodeToParent(type, this.tree[this.tree.length - 1]);
                    }
                    return;
                }
                this.tree.push(this.createNode(type));
            },

            addNodeToParent: function (type, parent) {
                if (!parent) return;
                if (!parent.children) parent.children = [];
                parent.children.push(this.createNode(type));
            },

            findNode: function (id, nodes) {
                nodes = nodes || this.tree;
                for (let i = 0; i < nodes.length; i++) {
                    if (nodes[i].id === id) return nodes[i];
                    if (nodes[i].children) {
                        const f = this.findNode(id, nodes[i].children);
                        if (f) return f;
                    }
                }
                return null;
            },

            findParentAndIndex: function (id, nodes, parent) {
                nodes = nodes || this.tree;
                parent = parent || null;
                for (let i = 0; i < nodes.length; i++) {
                    if (nodes[i].id === id) return { parent: parent, index: i, siblings: nodes };
                    if (nodes[i].children) {
                        const f = this.findParentAndIndex(id, nodes[i].children, nodes[i]);
                        if (f) return f;
                    }
                }
                return null;
            },

            deleteNode: function (id) {
                const info = this.findParentAndIndex(id);
                if (info) {
                    info.siblings.splice(info.index, 1);
                    if (this.editingNodeId === id) this.editingNodeId = null;
                }
            },

            duplicateNode: function (id) {
                const info = this.findParentAndIndex(id);
                if (!info) return;
                const clone = JSON.parse(JSON.stringify(info.siblings[info.index]));
                this.reassignIds(clone);
                info.siblings.splice(info.index + 1, 0, clone);
            },

            reassignIds: function (node) {
                node.id = this.generateId();
                if (node.children) {
                    for (let i = 0; i < node.children.length; i++) this.reassignIds(node.children[i]);
                }
            },

            // ── Edit ──
            editNode: function (id) { this.editingNodeId = id; },
            getEditingNode: function () { return this.editingNodeId ? this.findNode(this.editingNodeId) : null; },
            updateNodeData: function (id, key, value) {
                const n = this.findNode(id);
                if (n) { if (!n.data) n.data = {}; n.data[key] = value; }
            },
            closeEditPanel: function () { this.editingNodeId = null; },
            closeEditor: function () {
                this.editingNodeId = null;
                this.editorOpen = false;
                if (this.tree.length > 0) { this.syncMasterFormat(); } else { this.masterHtml = ''; }
            },

            // ── Preview ──
            getNodePreviewStyles: function (node) {
                const d = node.data || {}, s = [];
                if (d.background_color) s.push('background-color:' + d.background_color);
                if (d.min_height) s.push('min-height:' + d.min_height);
                if (d.text_align) s.push('text-align:' + d.text_align);
                return s.join(';');
            },
            getHeadingClass: function (tag) {
                return { h1:'text-3xl font-bold', h2:'text-2xl font-bold', h3:'text-xl font-semibold', h4:'text-lg font-semibold', h5:'text-base font-medium', h6:'text-sm font-medium' }[tag] || 'text-2xl font-bold';
            },

            // ── Master format ──
            syncMasterFormat: function () {
                const self = this;
                self.syncing = true;
                return fetch(self.renderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(self.tree)
                })
                .then((r) => { if (!r.ok) throw new Error('Render ' + r.status); return r.json(); })
                .then((d) => { if (d.html !== undefined) self.masterHtml = d.html; self.syncing = false; })
                .catch((e) => { console.error('Sync error:', e); self.syncing = false; });
            },

            parseHtml: function (html) {
                const self = this;
                return fetch(self.parseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ html: html })
                })
                .then((r) => { if (!r.ok) throw new Error('Parse ' + r.status); return r.json(); })
                .then((d) => { if (d.tree) self.tree = d.tree; })
                .catch((e) => { console.error('Parse error:', e); });
            }
        };
    });
});
