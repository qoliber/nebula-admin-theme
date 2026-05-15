/**
 * nebulaField_toggle — boolean switch. Serializes '1' / '0' so Magento
 * controllers see scalar values.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig, SerializedPair } from '../types';

export function registerToggleField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_toggle', (config: NebulaFieldConfig = {}) => {
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
