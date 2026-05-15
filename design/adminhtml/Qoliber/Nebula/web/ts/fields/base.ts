/**
 * nebulaField base — shared factory for every `nebulaField_<type>` Alpine
 * component. Owns the model-layer contract: register on init, serialize
 * on demand, unregister on destroy.
 *
 * All validation delegates to Nebula.validateField / Nebula.validateForm
 * from validate.ts. This file ships zero validation rules.
 */

import type { NebulaModelStore } from '../models';
import type { NebulaFieldBase, NebulaFieldConfig, SerializedPair } from '../types';

interface BaseState extends NebulaFieldBase {
    $el?: HTMLElement;
}

export function createBaseField(config: NebulaFieldConfig = {}): BaseState {
    return {
        value: config.value !== undefined ? config.value : config.default !== undefined ? config.default : '',
        fieldName: config.fieldName ?? '',
        disabled: !!config.disabled,
        required: !!config.required,
        validation: config.validation ?? {},
        error: null,
        _modelKey: null,

        init(this: BaseState): void {
            if (this.fieldName) {
                this._modelKey = 'field:' + this.fieldName;
                const store = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
                if (store) store.register(this._modelKey, this);
            }
        },

        destroy(this: BaseState): void {
            if (this._modelKey) {
                const store = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
                if (store) store.unregister(this._modelKey);
            }
        },

        serialize(this: BaseState): SerializedPair[] {
            if (!this.fieldName || this.disabled) return [];
            const raw = this.value;
            const value =
                raw === null || raw === undefined
                    ? ''
                    : typeof raw === 'string' || typeof raw === 'number' || typeof raw === 'boolean'
                        ? raw
                        : String(raw);
            return [{ name: this.fieldName, value }];
        },

        validate(this: BaseState): boolean {
            const root = this.$el;
            if (!root || !window.Nebula || typeof window.Nebula.validateField !== 'function') {
                return true;
            }
            const input = root.querySelector<HTMLElement>('[data-validate]');
            if (!input) return true;
            return window.Nebula.validateField(input);
        },
    };
}

/**
 * Install the base factory on `window.NebulaField`. Idempotent.
 */
export function installBaseField(): void {
    window.NebulaField = window.NebulaField ?? {
        base: (config?: NebulaFieldConfig) => createBaseField(config ?? {}),
    };
}
