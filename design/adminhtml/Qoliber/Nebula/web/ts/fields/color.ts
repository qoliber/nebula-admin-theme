/**
 * nebulaField_color — HTML5 color input.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerColorField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_color', (config: NebulaFieldConfig = {}) => createBaseField(config));
    });
}
