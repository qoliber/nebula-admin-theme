/**
 * nebulaField_select — single-select Alpine component.
 */

import { createBaseField } from './base';
import type { FieldOption, NebulaFieldConfig } from '../types';

export function registerSelectField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_select', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            const options: FieldOption[] = Array.isArray(config.options) ? config.options : [];
            return Object.assign(base, { options });
        });
    });
}
