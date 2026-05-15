/**
 * nebulaField_text — text / password / email Alpine component.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerTextField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_text', (config: NebulaFieldConfig = {}) =>
            createBaseField(config),
        );
    });
}
