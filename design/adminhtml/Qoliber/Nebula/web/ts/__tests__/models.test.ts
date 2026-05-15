import { describe, expect, it } from 'vitest';
import { createModelStore } from '../models';
import type { NebulaModel } from '../types';

const makeModel = (name: string, value: string | number): NebulaModel => ({
    serialize: () => [{ name, value }],
});

describe('createModelStore', () => {
    it('registers, retrieves, and unregisters models', () => {
        const store = createModelStore();
        const m = makeModel('product[name]', 'Widget');
        store.register('field:product[name]', m);
        expect(store.get('field:product[name]')).toBe(m);

        store.unregister('field:product[name]');
        expect(store.get('field:product[name]')).toBeNull();
    });

    it('nextId yields unique incrementing ids', () => {
        const store = createModelStore();
        expect(store.nextId()).toBe('__model_1');
        expect(store.nextId()).toBe('__model_2');
        expect(store.nextId()).toBe('__model_3');
    });

    it('validateAll returns true when all models pass', () => {
        const store = createModelStore();
        store.register('a', { serialize: () => [], validate: () => true });
        store.register('b', { serialize: () => [], validate: () => true });
        expect(store.validateAll()).toBe(true);
    });

    it('validateAll returns false when any model fails', () => {
        const store = createModelStore();
        store.register('a', { serialize: () => [], validate: () => true });
        store.register('b', { serialize: () => [], validate: () => false });
        expect(store.validateAll()).toBe(false);
    });

    it('validateAll ignores models without a validate hook', () => {
        const store = createModelStore();
        store.register('a', { serialize: () => [] });
        expect(store.validateAll()).toBe(true);
    });

    it('serializeAll injects hidden inputs marked with data-nebula-model', () => {
        const form = document.createElement('form');
        const store = createModelStore();
        store.register('field:name', makeModel('product[name]', 'Widget'));
        store.register('field:sku', makeModel('product[sku]', 'SKU-001'));

        store.serializeAll(form);

        const inputs = form.querySelectorAll<HTMLInputElement>('input[data-nebula-model]');
        expect(inputs.length).toBe(2);
        const names = Array.from(inputs).map((i) => i.name).sort();
        expect(names).toEqual(['product[name]', 'product[sku]']);
    });

    it('serializeAll clears previously injected inputs before re-injecting', () => {
        const form = document.createElement('form');
        const store = createModelStore();
        store.register('field:name', makeModel('product[name]', 'A'));

        store.serializeAll(form);
        store.serializeAll(form);

        const inputs = form.querySelectorAll('input[data-nebula-model]');
        expect(inputs.length).toBe(1);
    });

    it('serializeAll skips null/undefined values with empty strings', () => {
        const form = document.createElement('form');
        const store = createModelStore();
        store.register('field:x', { serialize: () => [{ name: 'x', value: null }] });

        store.serializeAll(form);

        const input = form.querySelector<HTMLInputElement>('input[name="x"]');
        expect(input).not.toBeNull();
        expect(input!.value).toBe('');
    });

    it('serializeAll skips pairs with missing names', () => {
        const form = document.createElement('form');
        const store = createModelStore();
        // @ts-expect-error testing malformed pair (name missing)
        store.register('bad', { serialize: () => [{ value: 'x' }] });

        store.serializeAll(form);

        expect(form.querySelectorAll('input[data-nebula-model]').length).toBe(0);
    });

    it('serializeAll ignores models with non-function serialize', () => {
        const form = document.createElement('form');
        const store = createModelStore();
        // @ts-expect-error testing malformed model
        store.register('bad', { serialize: 'not-a-fn' });

        store.serializeAll(form);

        expect(form.querySelectorAll('input[data-nebula-model]').length).toBe(0);
    });

    it('debug reflects each model\'s serialize output', () => {
        const store = createModelStore();
        store.register('a', makeModel('field[a]', 1));
        store.register('b', makeModel('field[b]', 2));
        const dump = store.debug();
        expect(dump['a']).toEqual([{ name: 'field[a]', value: 1 }]);
        expect(dump['b']).toEqual([{ name: 'field[b]', value: 2 }]);
    });

    it('register overwrites when the same key is used twice', () => {
        const store = createModelStore();
        const first = makeModel('a', 1);
        const second = makeModel('a', 2);
        store.register('field:a', first);
        store.register('field:a', second);
        expect(store.get('field:a')).toBe(second);
    });
});
