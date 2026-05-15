/**
 * Nebula Rule Editor — Alpine.js condition tree editor.
 *
 * Uses event delegation for interactions inside x-html rendered content.
 * All interactive elements use data-action attributes instead of Alpine directives.
 */
document.addEventListener('alpine:init', () => {
    'use strict';

    const OPERATORS_BY_INPUT = {
        string: ['==', '!=', '>=', '>', '<=', '<', '{}', '!{}', '()', '!()'],
        numeric: ['==', '!=', '>=', '>', '<=', '<', '()', '!()'],
        date: ['==', '>=', '<='],
        select: ['==', '!=', '<=>'],
        boolean: ['==', '!=', '<=>'],
        multiselect: ['{}', '!{}', '()', '!()'],
        grid: ['()', '!()'],
    };

    const OPERATOR_LABELS = {
        '==': 'is', '!=': 'is not', '>=': 'equals or greater than',
        '>': 'greater than', '<=': 'equals or less than', '<': 'less than',
        '{}': 'contains', '!{}': 'does not contain',
        '()': 'is one of', '!()': 'is not one of', '<=>': 'is undefined',
    };

    let idCounter = 0;
    function nextId() { return 'n' + (++idCounter); }

    function getInputType(code, attrs) {
        if (!code) return 'string';
        const a = attrs.find(x => x.value === code);
        return a ? (a.inputType || 'string') : 'string';
    }

    function getOperatorsForType(type) {
        return (OPERATORS_BY_INPUT[type] || OPERATORS_BY_INPUT.string)
            .map(c => ({ value: c, label: OPERATOR_LABELS[c] || c }));
    }

    function buildNode(data, attrs) {
        const isCombine = Array.isArray(data.conditions);
        return {
            id: nextId(), type: isCombine ? 'combine' : 'leaf',
            className: data.type || '', aggregator: data.aggregator || 'all',
            attribute: data.attribute || null, operator: data.operator || '==',
            value: data.value != null ? String(data.value) : '',
            inputType: isCombine ? 'string' : getInputType(data.attribute, attrs),
            children: isCombine ? (data.conditions || []).map(c => buildNode(c, attrs)) : [],
        };
    }

    function serializeNode(node, path, prefix, modelKey, pairs) {
        const p = prefix + '[' + modelKey + '][' + path + ']';
        pairs.push({ name: p + '[type]', value: node.className });
        if (node.type === 'combine') {
            pairs.push({ name: p + '[aggregator]', value: node.aggregator });
            pairs.push({ name: p + '[value]', value: '1' });
            node.children.forEach((child, i) => {
                serializeNode(child, path + '--' + (i + 1), prefix, modelKey, pairs);
            });
        } else {
            pairs.push({ name: p + '[attribute]', value: node.attribute || '' });
            pairs.push({ name: p + '[operator]', value: node.operator || '' });
            pairs.push({ name: p + '[value]', value: node.value || '' });
        }
    }

    function findNodeByPath(root, pathStr) {
        if (pathStr === 'root') return root;
        const parts = pathStr.split('.');
        let node = root;
        for (const part of parts) {
            const m = part.match(/^children\[(\d+)\]$/);
            if (m && node.children) {
                node = node.children[parseInt(m[1])];
            } else {
                return null;
            }
        }
        return node;
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    Alpine.data('nebulaRuleEditor', (config = {}) => ({
        rootNode: null,
        availableAttributes: config.availableAttributes || [],
        conditionTypes: config.conditionTypes || [],
        ruleType: config.ruleType || 'catalog',
        fieldPrefix: config.fieldPrefix || 'rule',
        modelKey: config.modelKey || 'conditions',
        combineClass: config.combineClass || 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine',

        init() {
            if (config.conditions && config.conditions.type) {
                this.rootNode = buildNode(config.conditions, this.availableAttributes);
            } else {
                this.rootNode = {
                    id: nextId(), type: 'combine', className: this.combineClass,
                    aggregator: 'all', attribute: null, operator: '==',
                    value: '1', inputType: 'string', children: [],
                };
            }

            const models = Alpine.store('nebulaModels');
            if (models) {
                // Per-modelKey registration so the conditions tree and the
                // sales-rule actions tree can coexist on the same page without
                // overwriting each other's serializer.
                models.register('section:' + this.modelKey, {
                    serialize: () => this.serialize(),
                });
            }

            // Event delegation — handle all interactions in the rendered tree
            this.$nextTick(() => {
                const el = this.$el;
                el.addEventListener('change', (e) => this._handleChange(e));
                el.addEventListener('click', (e) => this._handleClick(e));
                el.addEventListener('input', (e) => this._handleInput(e));
            });
        },

        serialize() {
            const pairs = [];
            if (this.rootNode) serializeNode(this.rootNode, '1', this.fieldPrefix, this.modelKey, pairs);
            return pairs;
        },

        _rerender() {
            // Force Alpine reactivity by reassigning rootNode
            // (Array mutations like push/splice aren't detected by x-effect)
            this.rootNode = JSON.parse(JSON.stringify(this.rootNode));
        },

        _handleClick(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            const path = btn.dataset.path || 'root';
            const parent = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);

            if (action === 'add-condition' && parent) {
                const cls = this.conditionTypes.length ? this.conditionTypes[0].value : '';
                parent.children.push({
                    id: nextId(), type: 'leaf', className: cls,
                    aggregator: 'all', attribute: null, operator: '==',
                    value: '', inputType: 'string', children: [],
                });
                this._rerender();
            } else if (action === 'add-group' && parent) {
                parent.children.push({
                    id: nextId(), type: 'combine', className: this.combineClass,
                    aggregator: 'all', attribute: null, operator: '==',
                    value: '1', inputType: 'string', children: [],
                });
                this._rerender();
            } else if (action === 'remove') {
                const parentPath = btn.dataset.parent;
                const idx = parseInt(btn.dataset.index);
                const parentNode = findNodeByPath(this.rootNode, parentPath === 'root' ? 'root' : parentPath);
                if (parentNode && !isNaN(idx)) {
                    parentNode.children.splice(idx, 1);
                    this._rerender();
                }
            }
        },

        _handleChange(e) {
            const el = e.target;
            const role = el.dataset.role;
            if (!role) return;
            const path = el.dataset.path;
            const node = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);
            if (!node) return;

            if (role === 'aggregator') {
                node.aggregator = el.value;
                this._rerender();
            } else if (role === 'attribute') {
                node.attribute = el.value;
                node.inputType = getInputType(el.value, this.availableAttributes);
                // Update the condition class if the attribute defines one
                const attrDef = this.availableAttributes.find(a => a.value === el.value);
                if (attrDef && attrDef.conditionClass) {
                    node.className = attrDef.conditionClass;
                }
                const ops = getOperatorsForType(node.inputType);
                node.operator = ops.length ? ops[0].value : '==';
                node.value = '';
                this._rerender();
            } else if (role === 'operator') {
                node.operator = el.value;
                if (el.value === '<=>') node.value = '';
                this._rerender();
            } else if (role === 'value') {
                if (el.multiple) {
                    node.value = Array.from(el.selectedOptions).map(o => o.value).join(',');
                } else {
                    node.value = el.value;
                }
            }
        },

        _handleInput(e) {
            const el = e.target;
            if (el.dataset.role === 'value') {
                const path = el.dataset.path;
                const node = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);
                if (node) node.value = el.value;
            }
        },

        renderTree() {
            if (!this.rootNode) return '<div class="text-sm text-gray-400">Loading conditions...</div>';
            return this._renderCombine(this.rootNode, 0, 'root');
        },

        _renderCombine(node, depth, path) {
            const indent = depth > 0 ? 'ml-6 mt-2' : '';
            const bg = depth === 0 ? 'bg-gray-50 border-gray-200' : 'bg-indigo-50/30 border-indigo-200/50';
            let h = '<div class="' + indent + ' p-4 rounded-lg border ' + bg + ' space-y-2">';

            // Header
            h += '<div class="flex items-center gap-2 text-sm flex-wrap">';
            h += '<span class="font-medium text-gray-700">If</span>';
            h += '<select data-role="aggregator" data-path="' + esc(path) + '" '
              + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">'
              + '<option value="all"' + (node.aggregator === 'all' ? ' selected' : '') + '>ALL</option>'
              + '<option value="any"' + (node.aggregator === 'any' ? ' selected' : '') + '>ANY</option>'
              + '</select>';
            h += '<span class="text-gray-600">of these conditions are</span>';
            h += '<span class="font-semibold text-gray-900">TRUE</span>';
            h += '<span class="text-gray-400">:</span>';

            if (depth > 0) {
                const parentPath = path.substring(0, path.lastIndexOf('.'));
                const idx = path.match(/\[(\d+)\]$/)?.[1];
                if (idx !== undefined) {
                    h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + idx + '" '
                      + 'class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer" title="Remove group">'
                      + '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
                      + '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
                }
            }
            h += '</div>';

            // Children
            for (let i = 0; i < node.children.length; i++) {
                const childPath = path + '.children[' + i + ']';
                const child = node.children[i];
                if (child.type === 'combine') {
                    h += this._renderCombine(child, depth + 1, childPath);
                } else {
                    h += this._renderLeaf(child, i, path, childPath);
                }
            }

            // Add buttons
            h += '<div class="flex items-center gap-2 pt-1">';
            h += '<button type="button" data-action="add-condition" data-path="' + esc(path) + '" '
              + 'class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition cursor-pointer">'
              + '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
              + '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>'
              + ' Condition</button>';
            h += '<button type="button" data-action="add-group" data-path="' + esc(path) + '" '
              + 'class="inline-flex items-center gap-1 rounded-md bg-white border border-dashed border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-400 transition cursor-pointer">'
              + '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
              + '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>'
              + ' Group</button>';
            h += '</div></div>';
            return h;
        },

        _renderLeaf(node, index, parentPath, nodePath) {
            let h = '<div class="ml-6 mt-1 flex items-center gap-2 rounded-md bg-white border border-gray-200 px-3 py-2 text-sm flex-wrap shadow-sm">';

            // Attribute select
            h += '<select data-role="attribute" data-path="' + esc(nodePath) + '" '
              + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">'
              + '<option value="">-- select --</option>';
            this.availableAttributes.forEach(a => {
                h += '<option value="' + esc(a.value) + '"' + (a.value === node.attribute ? ' selected' : '') + '>' + esc(a.label) + '</option>';
            });
            h += '</select>';

            if (node.attribute) {
                // Operator
                const ops = getOperatorsForType(node.inputType);
                h += '<select data-role="operator" data-path="' + esc(nodePath) + '" '
                  + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">';
                ops.forEach(op => {
                    h += '<option value="' + esc(op.value) + '"' + (op.value === node.operator ? ' selected' : '') + '>' + esc(op.label) + '</option>';
                });
                h += '</select>';

                // Value
                if (node.operator !== '<=>') {
                    const attr = this.availableAttributes.find(a => a.value === node.attribute);
                    const opts = attr?.options || [];
                    const mode = this.getValueMode(node);

                    if (mode === 'select' && opts.length) {
                        h += '<select data-role="value" data-path="' + esc(nodePath) + '" '
                          + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">'
                          + '<option value="">--</option>';
                        opts.forEach(o => {
                            h += '<option value="' + esc(o.value) + '"' + (String(o.value) === String(node.value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                        });
                        h += '</select>';
                    } else if (mode === 'multiselect' && opts.length) {
                        const sel = node.value ? String(node.value).split(',') : [];
                        h += '<select multiple data-role="value" data-path="' + esc(nodePath) + '" '
                          + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none min-h-[50px]">';
                        opts.forEach(o => {
                            h += '<option value="' + esc(o.value) + '"' + (sel.includes(String(o.value)) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                        });
                        h += '</select>';
                    } else {
                        h += '<input type="text" data-role="value" data-path="' + esc(nodePath) + '" '
                          + 'value="' + esc(node.value) + '" '
                          + 'class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none w-36">';
                    }
                }
            }

            // Remove
            h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + index + '" '
              + 'class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer flex-shrink-0" title="Remove">'
              + '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
              + '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';

            h += '</div>';
            return h;
        },

        getValueMode(node) {
            if (node.operator === '<=>') return 'hidden';
            const t = node.inputType;
            if (t === 'select' || t === 'boolean') return 'select';
            if (t === 'multiselect') return 'multiselect';
            return 'text';
        },
    }));
});
