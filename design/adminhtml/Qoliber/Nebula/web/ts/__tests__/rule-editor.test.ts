import { describe, expect, it, vi } from 'vitest';
import {
    buildNode,
    createRuleEditor,
    findNodeByPath,
    getOperatorsForType,
    serializeNode,
} from '../rule-editor';
import type { RuleNode, SerializedPair } from '../types';

describe('getOperatorsForType', () => {
    it('returns the string operators for unknown types', () => {
        // @ts-expect-error testing fallback for an invalid type
        expect(getOperatorsForType('???').length).toBeGreaterThan(0);
    });

    it('string operators include equality + contains + one-of', () => {
        const ops = getOperatorsForType('string').map((o) => o.value);
        expect(ops).toContain('==');
        expect(ops).toContain('{}');
        expect(ops).toContain('()');
    });

    it('numeric operators exclude contains', () => {
        const ops = getOperatorsForType('numeric').map((o) => o.value);
        expect(ops).not.toContain('{}');
        expect(ops).toContain('>=');
    });

    it('multiselect operators only cover contains/one-of', () => {
        const ops = getOperatorsForType('multiselect').map((o) => o.value);
        expect(ops.every((op) => ['{}', '!{}', '()', '!()'].includes(op))).toBe(true);
    });
});

describe('buildNode', () => {
    it('creates a leaf node for atomic conditions', () => {
        const node = buildNode(
            { type: 'Some\\Class', attribute: 'price', operator: '==', value: '10' },
            [],
        );
        expect(node.type).toBe('leaf');
        expect(node.attribute).toBe('price');
        expect(node.operator).toBe('==');
        expect(node.value).toBe('10');
        expect(node.children).toEqual([]);
    });

    it('creates a combine node with children for conditions arrays', () => {
        const node = buildNode(
            {
                type: 'Combine',
                aggregator: 'any',
                conditions: [
                    { type: 'X', attribute: 'a', operator: '==', value: '1' },
                    { type: 'Y', attribute: 'b', operator: '==', value: '2' },
                ],
            },
            [],
        );
        expect(node.type).toBe('combine');
        expect(node.aggregator).toBe('any');
        expect(node.children).toHaveLength(2);
        expect(node.children[0]?.attribute).toBe('a');
    });

    it('infers inputType from the attribute registry', () => {
        const node = buildNode(
            { type: 'C', attribute: 'sku', operator: '==', value: '' },
            [{ value: 'sku', label: 'SKU', inputType: 'numeric' }],
        );
        expect(node.inputType).toBe('numeric');
    });

    it('defaults inputType to string when attribute is unknown', () => {
        const node = buildNode({ type: 'C', attribute: 'unknown', operator: '==', value: '' }, []);
        expect(node.inputType).toBe('string');
    });
});

describe('serializeNode', () => {
    const makeLeaf = (attribute: string, operator: string, value: string): RuleNode => ({
        id: 'x',
        type: 'leaf',
        className: 'Vendor\\Cond',
        aggregator: 'all',
        attribute,
        operator,
        value,
        inputType: 'string',
        children: [],
    });

    it('serializes a lone leaf into the Magento rule shape', () => {
        const out: SerializedPair[] = [];
        serializeNode(makeLeaf('price', '>=', '10'), '1', 'rule', out);

        expect(out).toContainEqual({ name: 'rule[conditions][1][type]', value: 'Vendor\\Cond' });
        expect(out).toContainEqual({ name: 'rule[conditions][1][attribute]', value: 'price' });
        expect(out).toContainEqual({ name: 'rule[conditions][1][operator]', value: '>=' });
        expect(out).toContainEqual({ name: 'rule[conditions][1][value]', value: '10' });
    });

    it('serializes a combine with nested children using the "--N" path suffix', () => {
        const combine: RuleNode = {
            id: 'root',
            type: 'combine',
            className: 'Combine',
            aggregator: 'all',
            attribute: null,
            operator: '==',
            value: '1',
            inputType: 'string',
            children: [makeLeaf('sku', '==', 'A'), makeLeaf('price', '>', '0')],
        };
        const out: SerializedPair[] = [];
        serializeNode(combine, '1', 'rule', out);

        expect(out).toContainEqual({ name: 'rule[conditions][1][aggregator]', value: 'all' });
        expect(out).toContainEqual({ name: 'rule[conditions][1][value]', value: '1' });
        expect(out).toContainEqual({ name: 'rule[conditions][1--1][attribute]', value: 'sku' });
        expect(out).toContainEqual({ name: 'rule[conditions][1--2][attribute]', value: 'price' });
    });
});

describe('findNodeByPath', () => {
    const tree: RuleNode = {
        id: 'r',
        type: 'combine',
        className: 'Combine',
        aggregator: 'all',
        attribute: null,
        operator: '==',
        value: '1',
        inputType: 'string',
        children: [
            {
                id: 'c0',
                type: 'leaf',
                className: 'X',
                aggregator: 'all',
                attribute: 'a',
                operator: '==',
                value: '',
                inputType: 'string',
                children: [],
            },
            {
                id: 'c1',
                type: 'combine',
                className: 'Combine',
                aggregator: 'all',
                attribute: null,
                operator: '==',
                value: '1',
                inputType: 'string',
                children: [
                    {
                        id: 'c1-0',
                        type: 'leaf',
                        className: 'Y',
                        aggregator: 'all',
                        attribute: 'b',
                        operator: '==',
                        value: '',
                        inputType: 'string',
                        children: [],
                    },
                ],
            },
        ],
    };

    it('returns the root for "root"', () => {
        expect(findNodeByPath(tree, 'root')).toBe(tree);
    });
    it('navigates via children[N] segments', () => {
        const child = findNodeByPath(tree, 'root.children[0]');
        expect(child?.id).toBe('c0');
    });
    it('navigates multi-level paths', () => {
        const deep = findNodeByPath(tree, 'root.children[1].children[0]');
        expect(deep?.id).toBe('c1-0');
    });
    it('returns null for malformed paths', () => {
        expect(findNodeByPath(tree, 'root.bogus')).toBeNull();
    });
});

describe('createRuleEditor', () => {
    it('serialize returns nothing before init()', () => {
        const editor = createRuleEditor({ fieldPrefix: 'rule' });
        expect(editor.serialize()).toEqual([]);
    });

    it('serialize produces pairs after init() builds the root node', () => {
        const editor = createRuleEditor({
            fieldPrefix: 'rule',
            conditions: {
                type: 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine',
                aggregator: 'all',
                conditions: [],
            },
            availableAttributes: [],
            conditionTypes: [],
        });

        // init() tries to register a model + attach listeners — skip DOM parts.
        editor.rootNode = {
            id: 'n',
            type: 'combine',
            className: 'Combine',
            aggregator: 'all',
            attribute: null,
            operator: '==',
            value: '1',
            inputType: 'string',
            children: [],
        };

        const pairs = editor.serialize();
        expect(pairs).toContainEqual({ name: 'rule[conditions][1][aggregator]', value: 'all' });
    });

    it('getValueMode honors operator + inputType', () => {
        const editor = createRuleEditor();
        const base: RuleNode = {
            id: 'x',
            type: 'leaf',
            className: '',
            aggregator: 'all',
            attribute: 'a',
            operator: '==',
            value: '',
            inputType: 'string',
            children: [],
        };
        expect(editor.getValueMode({ ...base, operator: '<=>' })).toBe('hidden');
        expect(editor.getValueMode({ ...base, inputType: 'select' })).toBe('select');
        expect(editor.getValueMode({ ...base, inputType: 'multiselect' })).toBe('multiselect');
        expect(editor.getValueMode({ ...base, inputType: 'numeric' })).toBe('text');
    });
});

describe('createRuleEditor — value changes do not trigger _rerender', () => {
    function makeEditor() {
        const editor = createRuleEditor({
            conditions: {
                type: 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine',
                aggregator: 'all',
                value: '1',
                conditions: [
                    {
                        type: 'Magento\\Rule\\Model\\Condition\\Product\\Attributes',
                        attribute: 'sku',
                        operator: '==',
                        value: 'foo',
                    },
                ],
            },
            availableAttributes: [{ value: 'sku', label: 'SKU', inputType: 'string' }],
        });
        // init() uses Alpine and $nextTick which are not available in unit tests.
        // Manually build the tree the same way init() would.
        editor.rootNode = buildNode(
            {
                type: 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine',
                aggregator: 'all',
                value: '1',
                conditions: [
                    {
                        type: 'Magento\\Rule\\Model\\Condition\\Product\\Attributes',
                        attribute: 'sku',
                        operator: '==',
                        value: 'foo',
                    },
                ],
            },
            [{ value: 'sku', label: 'SKU', inputType: 'string' }],
        );
        return editor;
    }

    it('_handleInput on role=value mutates node.value without calling _rerender', () => {
        const editor = makeEditor();
        const rerenderSpy = vi.spyOn(editor, '_rerender');

        const leafNode = editor.rootNode!.children[0]!;
        const input = document.createElement('input');
        input.dataset['role'] = 'value';
        input.dataset['path'] = 'children[0]';
        input.value = 'bar';

        const fakeEvent = { target: input } as unknown as Event;
        editor._handleInput(fakeEvent);

        expect(rerenderSpy).not.toHaveBeenCalled();
        expect(leafNode.value).toBe('bar');
    });

    it('_handleChange on role=value mutates node.value without calling _rerender', () => {
        const editor = makeEditor();
        const rerenderSpy = vi.spyOn(editor, '_rerender');

        const leafNode = editor.rootNode!.children[0]!;
        const input = document.createElement('input');
        input.dataset['role'] = 'value';
        input.dataset['path'] = 'children[0]';
        input.value = 'baz';

        const fakeEvent = { target: input } as unknown as Event;
        editor._handleChange(fakeEvent);

        expect(rerenderSpy).not.toHaveBeenCalled();
        expect(leafNode.value).toBe('baz');
    });

    it('typing 10 characters into a value field does not call _rerender', () => {
        const editor = makeEditor();
        const rerenderSpy = vi.spyOn(editor, '_rerender');

        const leafNode = editor.rootNode!.children[0]!;
        let currentValue = '';
        for (let i = 0; i < 10; i++) {
            currentValue += String(i);
            const input = document.createElement('input');
            input.dataset['role'] = 'value';
            input.dataset['path'] = 'children[0]';
            input.value = currentValue;
            editor._handleInput({ target: input } as unknown as Event);
        }

        expect(rerenderSpy).not.toHaveBeenCalled();
        expect(leafNode.value).toBe('0123456789');
    });
});
