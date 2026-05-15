/**
 * nebulaField_date — ISO-8601 date input.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerDateField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_date', (config: NebulaFieldConfig = {}) => createBaseField(config));
    });
}
