/**
 * Nebula Rule Editor — Alpine.js condition tree editor.
 *
 * Uses event delegation for interactions inside x-html rendered content.
 * All interactive elements use data-action attributes instead of Alpine directives.
 */

import type { NebulaModelStore } from './models';
import type {
    RuleAttribute,
    RuleConditionData,
    RuleConditionTypeOption,
    RuleEditorConfig,
    RuleInputType,
    RuleNode,
    SerializedPair,
} from './types';

const OPERATORS_BY_INPUT: Record<RuleInputType, string[]> = {
    string: ['==', '!=', '>=', '>', '<=', '<', '{}', '!{}', '()', '!()'],
    numeric: ['==', '!=', '>=', '>', '<=', '<', '()', '!()'],
    date: ['==', '>=', '<='],
    select: ['==', '!=', '<=>'],
    boolean: ['==', '!=', '<=>'],
    multiselect: ['{}', '!{}', '()', '!()'],
    grid: ['()', '!()'],
};

const OPERATOR_LABELS: Record<string, string> = {
    '==': 'is',
    '!=': 'is not',
    '>=': 'equals or greater than',
    '>': 'greater than',
    '<=': 'equals or less than',
    '<': 'less than',
    '{}': 'contains',
    '!{}': 'does not contain',
    '()': 'is one of',
    '!()': 'is not one of',
    '<=>': 'is undefined',
};

let _moduleIdCounter = 0;
function _moduleNextId(): string {
    return 'n' + String(++_moduleIdCounter);
}

function getInputType(code: string | null | undefined, attrs: RuleAttribute[]): RuleInputType {
    if (!code) return 'string';
    const a = attrs.find((x) => x.value === code);
    return (a?.inputType ?? 'string') as RuleInputType;
}

export function getOperatorsForType(type: RuleInputType): { value: string; label: string }[] {
    return (OPERATORS_BY_INPUT[type] ?? OPERATORS_BY_INPUT.string).map((c) => ({
        value: c,
        label: OPERATOR_LABELS[c] ?? c,
    }));
}

export function buildNode(
    data: RuleConditionData,
    attrs: RuleAttribute[],
    nextIdFn: () => string = _moduleNextId,
): RuleNode {
    const isCombine = Array.isArray(data.conditions);
    return {
        id: nextIdFn(),
        type: isCombine ? 'combine' : 'leaf',
        className: data.type ?? '',
        aggregator: (data.aggregator ?? 'all') as 'all' | 'any',
        attribute: data.attribute ?? null,
        operator: data.operator ?? '==',
        value: data.value != null ? String(data.value) : '',
        inputType: isCombine ? 'string' : getInputType(data.attribute, attrs),
        children: isCombine ? (data.conditions ?? []).map((c) => buildNode(c, attrs, nextIdFn)) : [],
    };
}

export function serializeNode(
    node: RuleNode,
    path: string,
    prefix: string,
    pairs: SerializedPair[],
): void {
    const p = prefix + '[conditions][' + path + ']';
    pairs.push({ name: p + '[type]', value: node.className });
    if (node.type === 'combine') {
        pairs.push({ name: p + '[aggregator]', value: node.aggregator });
        pairs.push({ name: p + '[value]', value: '1' });
        node.children.forEach((child, i) => {
            serializeNode(child, path + '--' + String(i + 1), prefix, pairs);
        });
    } else {
        pairs.push({ name: p + '[attribute]', value: node.attribute ?? '' });
        pairs.push({ name: p + '[operator]', value: node.operator });
        pairs.push({ name: p + '[value]', value: node.value });
    }
}

export function findNodeByPath(root: RuleNode, pathStr: string): RuleNode | null {
    if (pathStr === 'root') return root;
    // Paths emitted by the render functions look like `root.children[0].children[1]`.
    // The leading `root` segment is optional for legacy callers — treat both the
    // bare `children[0]` and `root.children[0]` forms as starting at `root`.
    const parts = pathStr.split('.').filter((p) => p !== 'root');
    let node: RuleNode | null = root;
    for (const part of parts) {
        const m = part.match(/^children\[(\d+)\]$/);
        if (m && node && node.children) {
            const idx = parseInt(m[1] ?? '0', 10);
            node = node.children[idx] ?? null;
        } else {
            return null;
        }
    }
    return node;
}

const _escEl = document.createElement('div');
function esc(str: unknown): string {
    _escEl.textContent = str == null ? '' : String(str);
    return _escEl.innerHTML;
}

interface RuleEditorState {
    rootNode: RuleNode | null;
    availableAttributes: RuleAttribute[];
    conditionTypes: RuleConditionTypeOption[];
    ruleType: 'catalog' | 'sales';
    fieldPrefix: string;
    init(): void;
    serialize(): SerializedPair[];
    renderTree(): string;
    getValueMode(node: RuleNode): 'hidden' | 'select' | 'multiselect' | 'text';
    _rerender(): void;
    _handleClick(e: Event): void;
    _handleChange(e: Event): void;
    _handleInput(e: Event): void;
    _renderCombine(node: RuleNode, depth: number, path: string): string;
    _renderLeaf(node: RuleNode, index: number, parentPath: string, nodePath: string): string;
    $el?: HTMLElement;
    $nextTick?: (cb: () => void) => void;
}

function defaultCombineClass(ruleType: 'catalog' | 'sales'): string {
    return ruleType === 'sales'
        ? 'Magento\\SalesRule\\Model\\Rule\\Condition\\Combine'
        : 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine';
}

export function createRuleEditor(config: RuleEditorConfig = {}): RuleEditorState {
    let idCounter = 0;
    function nextId(): string {
        return 'n' + String(++idCounter);
    }

    const availableAttributes = config.availableAttributes ?? [];
    const conditionTypes = config.conditionTypes ?? [];
    const ruleType = config.ruleType ?? 'catalog';
    const fieldPrefix = config.fieldPrefix ?? 'rule';

    const state: RuleEditorState = {
        rootNode: null,
        availableAttributes,
        conditionTypes,
        ruleType,
        fieldPrefix,

        init(this: RuleEditorState): void {
            if (config.conditions && config.conditions.type) {
                this.rootNode = buildNode(config.conditions, this.availableAttributes, nextId);
            } else {
                this.rootNode = {
                    id: nextId(),
                    type: 'combine',
                    className: defaultCombineClass(this.ruleType),
                    aggregator: 'all',
                    attribute: null,
                    operator: '==',
                    value: '1',
                    inputType: 'string',
                    children: [],
                };
            }

            const models = window.Alpine?.store('nebulaModels') as NebulaModelStore | undefined;
            if (models) {
                models.register('section:conditions', {
                    serialize: () => this.serialize(),
                });
            }

            const self = this;
            this.$nextTick?.(() => {
                const el = self.$el;
                if (!el) return;
                el.addEventListener('change', (e) => self._handleChange(e));
                el.addEventListener('click', (e) => self._handleClick(e));
                el.addEventListener('input', (e) => self._handleInput(e));
            });
        },

        serialize(this: RuleEditorState): SerializedPair[] {
            const pairs: SerializedPair[] = [];
            if (this.rootNode) serializeNode(this.rootNode, '1', this.fieldPrefix, pairs);
            return pairs;
        },

        _rerender(this: RuleEditorState): void {
            this.rootNode = JSON.parse(JSON.stringify(this.rootNode)) as RuleNode;
        },

        _handleClick(this: RuleEditorState, e: Event): void {
            if (!this.rootNode) return;
            const target = e.target as HTMLElement | null;
            if (!target) return;
            const btn = target.closest<HTMLElement>('[data-action]');
            if (!btn) return;
            const action = btn.dataset['action'];
            const path = btn.dataset['path'] ?? 'root';
            const parent = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);

            if (action === 'add-condition' && parent) {
                const cls = this.conditionTypes.length ? this.conditionTypes[0]!.value : '';
                parent.children.push({
                    id: nextId(),
                    type: 'leaf',
                    className: cls,
                    aggregator: 'all',
                    attribute: null,
                    operator: '==',
                    value: '',
                    inputType: 'string',
                    children: [],
                });
                this._rerender();
            } else if (action === 'add-group' && parent) {
                parent.children.push({
                    id: nextId(),
                    type: 'combine',
                    className: defaultCombineClass(this.ruleType),
                    aggregator: 'all',
                    attribute: null,
                    operator: '==',
                    value: '1',
                    inputType: 'string',
                    children: [],
                });
                this._rerender();
            } else if (action === 'remove') {
                const parentPath = btn.dataset['parent'];
                const idx = parseInt(btn.dataset['index'] ?? 'NaN', 10);
                if (!parentPath) return;
                const parentNode = findNodeByPath(
                    this.rootNode,
                    parentPath === 'root' ? 'root' : parentPath,
                );
                if (parentNode && !isNaN(idx)) {
                    parentNode.children.splice(idx, 1);
                    this._rerender();
                }
            }
        },

        _handleChange(this: RuleEditorState, e: Event): void {
            if (!this.rootNode) return;
            const el = e.target as (HTMLInputElement | HTMLSelectElement) | null;
            if (!el) return;
            const role = el.dataset['role'];
            if (!role) return;
            const path = el.dataset['path'];
            if (!path) return;
            const node = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);
            if (!node) return;

            if (role === 'aggregator') {
                node.aggregator = el.value === 'any' ? 'any' : 'all';
                this._rerender();
            } else if (role === 'attribute') {
                node.attribute = el.value;
                node.inputType = getInputType(el.value, this.availableAttributes);
                const attrDef = this.availableAttributes.find((a) => a.value === el.value);
                if (attrDef && attrDef.conditionClass) {
                    node.className = attrDef.conditionClass;
                }
                const ops = getOperatorsForType(node.inputType);
                node.operator = ops.length ? ops[0]!.value : '==';
                node.value = '';
                this._rerender();
            } else if (role === 'operator') {
                node.operator = el.value;
                if (el.value === '<=>') node.value = '';
                this._rerender();
            } else if (role === 'value') {
                if (el instanceof HTMLSelectElement && el.multiple) {
                    node.value = Array.from(el.selectedOptions)
                        .map((o) => o.value)
                        .join(',');
                } else {
                    node.value = el.value;
                }
            }
        },

        _handleInput(this: RuleEditorState, e: Event): void {
            if (!this.rootNode) return;
            const el = e.target as HTMLInputElement | null;
            if (!el) return;
            if (el.dataset['role'] === 'value') {
                const path = el.dataset['path'];
                if (!path) return;
                const node = findNodeByPath(this.rootNode, path === 'root' ? 'root' : path);
                if (node) node.value = el.value;
            }
        },

        renderTree(this: RuleEditorState): string {
            if (!this.rootNode) return '<div class="text-sm text-gray-400">Loading conditions...</div>';
            return this._renderCombine(this.rootNode, 0, 'root');
        },

        _renderCombine(this: RuleEditorState, node: RuleNode, depth: number, path: string): string {
            const indent = depth > 0 ? 'ml-6 mt-2' : '';
            const bg = depth === 0 ? 'bg-gray-50 border-gray-200' : 'bg-indigo-50/30 border-indigo-200/50';
            let h = '<div class="' + indent + ' p-4 rounded-lg border ' + bg + ' space-y-2">';

            h += '<div class="flex items-center gap-2 text-sm flex-wrap">';
            h += '<span class="font-medium text-gray-700">If</span>';
            h +=
                '<select data-role="aggregator" data-path="' +
                esc(path) +
                '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">' +
                '<option value="all"' +
                (node.aggregator === 'all' ? ' selected' : '') +
                '>ALL</option>' +
                '<option value="any"' +
                (node.aggregator === 'any' ? ' selected' : '') +
                '>ANY</option>' +
                '</select>';
            h += '<span class="text-gray-600">of these conditions are</span>';
            h += '<span class="font-semibold text-gray-900">TRUE</span>';
            h += '<span class="text-gray-400">:</span>';

            if (depth > 0) {
                const parentPath = path.substring(0, path.lastIndexOf('.'));
                const idxMatch = path.match(/\[(\d+)\]$/);
                const idx = idxMatch ? idxMatch[1] : undefined;
                if (idx !== undefined) {
                    h +=
                        '<button type="button" data-action="remove" data-parent="' +
                        esc(parentPath) +
                        '" data-index="' +
                        idx +
                        '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer" title="Remove group">' +
                        '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
                }
            }
            h += '</div>';

            for (let i = 0; i < node.children.length; i++) {
                const childPath = path + '.children[' + i + ']';
                const child = node.children[i];
                if (!child) continue;
                if (child.type === 'combine') {
                    h += this._renderCombine(child, depth + 1, childPath);
                } else {
                    h += this._renderLeaf(child, i, path, childPath);
                }
            }

            h += '<div class="flex items-center gap-2 pt-1">';
            h +=
                '<button type="button" data-action="add-condition" data-path="' +
                esc(path) +
                '" class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition cursor-pointer">' +
                '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>' +
                ' Condition</button>';
            h +=
                '<button type="button" data-action="add-group" data-path="' +
                esc(path) +
                '" class="inline-flex items-center gap-1 rounded-md bg-white border border-dashed border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-400 transition cursor-pointer">' +
                '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>' +
                ' Group</button>';
            h += '</div></div>';
            return h;
        },

        _renderLeaf(
            this: RuleEditorState,
            node: RuleNode,
            index: number,
            parentPath: string,
            nodePath: string,
        ): string {
            let h =
                '<div class="ml-6 mt-1 flex items-center gap-2 rounded-md bg-white border border-gray-200 px-3 py-2 text-sm flex-wrap shadow-sm">';

            h +=
                '<select data-role="attribute" data-path="' +
                esc(nodePath) +
                '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">' +
                '<option value="">-- select --</option>';
            this.availableAttributes.forEach((a) => {
                h +=
                    '<option value="' +
                    esc(a.value) +
                    '"' +
                    (a.value === node.attribute ? ' selected' : '') +
                    '>' +
                    esc(a.label) +
                    '</option>';
            });
            h += '</select>';

            if (node.attribute) {
                const ops = getOperatorsForType(node.inputType);
                h +=
                    '<select data-role="operator" data-path="' +
                    esc(nodePath) +
                    '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">';
                ops.forEach((op) => {
                    h +=
                        '<option value="' +
                        esc(op.value) +
                        '"' +
                        (op.value === node.operator ? ' selected' : '') +
                        '>' +
                        esc(op.label) +
                        '</option>';
                });
                h += '</select>';

                if (node.operator !== '<=>') {
                    const attr = this.availableAttributes.find((a) => a.value === node.attribute);
                    const opts = attr?.options ?? [];
                    const mode = this.getValueMode(node);

                    if (mode === 'select' && opts.length) {
                        h +=
                            '<select data-role="value" data-path="' +
                            esc(nodePath) +
                            '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">' +
                            '<option value="">--</option>';
                        opts.forEach((o) => {
                            h +=
                                '<option value="' +
                                esc(o.value) +
                                '"' +
                                (String(o.value) === String(node.value) ? ' selected' : '') +
                                '>' +
                                esc(o.label) +
                                '</option>';
                        });
                        h += '</select>';
                    } else if (mode === 'multiselect' && opts.length) {
                        const sel = node.value ? String(node.value).split(',') : [];
                        h +=
                            '<select multiple data-role="value" data-path="' +
                            esc(nodePath) +
                            '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none min-h-[50px]">';
                        opts.forEach((o) => {
                            h +=
                                '<option value="' +
                                esc(o.value) +
                                '"' +
                                (sel.includes(String(o.value)) ? ' selected' : '') +
                                '>' +
                                esc(o.label) +
                                '</option>';
                        });
                        h += '</select>';
                    } else {
                        h +=
                            '<input type="text" data-role="value" data-path="' +
                            esc(nodePath) +
                            '" value="' +
                            esc(node.value) +
                            '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none w-36">';
                    }
                }
            }

            h +=
                '<button type="button" data-action="remove" data-parent="' +
                esc(parentPath) +
                '" data-index="' +
                String(index) +
                '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer flex-shrink-0" title="Remove">' +
                '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';

            h += '</div>';
            return h;
        },

        getValueMode(_node: RuleNode): 'hidden' | 'select' | 'multiselect' | 'text' {
            if (_node.operator === '<=>') return 'hidden';
            const t = _node.inputType;
            if (t === 'select' || t === 'boolean') return 'select';
            if (t === 'multiselect') return 'multiselect';
            return 'text';
        },
    };

    return state;
}

export function registerRuleEditor(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaRuleEditor', (config: RuleEditorConfig = {}) =>
            createRuleEditor(config),
        );
    });
}
