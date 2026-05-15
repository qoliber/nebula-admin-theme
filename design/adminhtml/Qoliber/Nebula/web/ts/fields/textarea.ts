/**
 * nebulaField_textarea — textarea / wysiwyg Alpine component.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerTextareaField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_textarea', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            const rows = typeof config['rows'] === 'number' ? (config['rows'] as number) : 4;
            return Object.assign(base, { rows });
        });
    });
}
