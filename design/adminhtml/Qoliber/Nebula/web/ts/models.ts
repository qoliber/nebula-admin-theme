/**
 * Nebula Form Models — Alpine.js model registry for form serialization.
 *
 * Every field and section component auto-registers on init().
 * On form submit, serializeAll() collects data from all models
 * and injects hidden inputs — templates have zero hidden inputs.
 */

import type { NebulaModel, SerializedPair } from './types';

export interface NebulaModelStore {
    _models: Record<string, NebulaModel>;
    _counter: number;
    register(name: string, model: NebulaModel): void;
    unregister(name: string): void;
    nextId(): string;
    get(name: string): NebulaModel | null;
    validateAll(): boolean;
    serializeAll(form: HTMLFormElement): void;
    debug(): Record<string, SerializedPair[]>;
}

export function createModelStore(): NebulaModelStore {
    return {
        _models: {},
        _counter: 0,

        register(name: string, model: NebulaModel): void {
            this._models[name] = model;
        },

        unregister(name: string): void {
            delete this._models[name];
        },

        nextId(): string {
            return '__model_' + String(++this._counter);
        },

        get(name: string): NebulaModel | null {
            return this._models[name] ?? null;
        },

        validateAll(): boolean {
            let valid = true;
            for (const model of Object.values(this._models)) {
                if (typeof model.validate === 'function' && !model.validate()) {
                    valid = false;
                }
            }
            return valid;
        },

        serializeAll(form: HTMLFormElement): void {
            // Remove previously injected model inputs.
            form.querySelectorAll('[data-nebula-model]').forEach((el) => el.remove());

            for (const [name, model] of Object.entries(this._models)) {
                if (typeof model.serialize !== 'function') continue;

                const pairs = model.serialize();
                if (!Array.isArray(pairs)) continue;

                for (const pair of pairs) {
                    if (pair.name === undefined || pair.name === null) continue;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = pair.name;
                    input.value =
                        pair.value !== undefined && pair.value !== null ? String(pair.value) : '';
                    input.setAttribute('data-nebula-model', name);
                    form.appendChild(input);
                }
            }
        },

        debug(): Record<string, SerializedPair[]> {
            const result: Record<string, SerializedPair[]> = {};
            for (const [name, model] of Object.entries(this._models)) {
                if (typeof model.serialize === 'function') {
                    result[name] = model.serialize();
                }
            }
            return result;
        },
    };
}

/**
 * Register the Alpine store on `alpine:init`. Idempotent.
 */
export function registerModelStore(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.store('nebulaModels', createModelStore());
    });
}
