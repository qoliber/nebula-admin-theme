/**
 * nebulaField_number — numeric input.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerNumberField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_number', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            const step = typeof config['step'] === 'number' ? (config['step'] as number) : 1;
            const min = (config['min'] ?? null) as number | null;
            const max = (config['max'] ?? null) as number | null;
            return Object.assign(base, { step, min, max });
        });
    });
}
