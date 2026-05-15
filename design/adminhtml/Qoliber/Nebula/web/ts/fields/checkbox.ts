/**
 * nebulaField_checkbox — identical semantics to toggle, different UI.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig, SerializedPair } from '../types';

export function registerCheckboxField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_checkbox', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            return Object.assign(base, {
                value: Boolean(config.value),
                serialize(this: { fieldName: string; disabled: boolean; value: unknown }): SerializedPair[] {
                    if (!this.fieldName || this.disabled) return [];
                    return [{ name: this.fieldName, value: this.value ? '1' : '0' }];
                },
            });
        });
    });
}
