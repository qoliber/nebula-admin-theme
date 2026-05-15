/**
 * nebulaField_hidden — headless component. Owns a server-routing value
 * that must ship on submit but should not be user-editable.
 */

import { createBaseField } from './base';
import type { NebulaFieldConfig } from '../types';

export function registerHiddenField(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaField_hidden', (config: NebulaFieldConfig = {}) => createBaseField(config));
    });
}
