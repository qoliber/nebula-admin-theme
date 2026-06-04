/**
 * Nebula Core — single-bundle entry that installs Alpine.js + every Nebula
 * primitive that every admin page needs: model store, validator, toast,
 * modal mixin, per-field Alpine components, and column renderers.
 *
 * Replaces the four vendor .min.js files plus the five NebulaComponent
 * .js files that the old scripts.phtml loaded separately.
 */

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import { registerErrorComponent } from './components/error';
import { registerLoadingComponent } from './components/loading';
import { registerProductSelector } from './components/product-selector';
import { registerSearchableMultiselect } from './components/searchable-multiselect';
import { registerConfigForm } from './components/config-form';
import { registerDashboardTabs } from './components/dashboard-tabs';
import { bootConfigDepends } from './components/config-depends';
import { registerMediaSynchronize } from './components/media-synchronize';
import { registerNebulaMenu } from './components/menu';
import { registerStorePicker } from './components/store-picker';
import { registerWidgetWizard } from './components/widget/wizard';
import { bootTestConnection } from './components/test-connection';
import { bootImagePreview } from './components/image-preview';
import { installVendorCompat } from './components/vendor-compat';
import { installBaseField } from './fields/base';
import { registerCheckboxField } from './fields/checkbox';
import { registerColorField } from './fields/color';
import { registerColumnComponents } from './fields/column';
import { registerDateField } from './fields/date';
import { registerHiddenField } from './fields/hidden';
import { registerLayoutComponent } from './fields/layout';
import { registerMultiselectField } from './fields/multiselect';
import { registerNumberField } from './fields/number';
import { registerSelectField } from './fields/select';
import { registerTextField } from './fields/text';
import { registerTextareaField } from './fields/textarea';
import { registerToggleField } from './fields/toggle';
import { installModal } from './modal';
import { installConfirm } from './confirm';
import { registerModelStore } from './models';
import { registerRuleEditor } from './rule-editor';
import { installToast } from './toast';
import { installValidate } from './validate';

// Attach Alpine to the window BEFORE plugins + components hook up.
window.Alpine = Alpine;

// Alpine plugins.
Alpine.plugin(collapse);

// Singletons / installers (side-effect: window.* bindings).
installVendorCompat();
installBaseField();
installValidate();
installToast();
installModal();
installConfirm();

// Alpine.data / Alpine.store registrations — fire on alpine:init.
registerModelStore();
registerTextField();
registerTextareaField();
registerSelectField();
registerMultiselectField();
registerToggleField();
registerCheckboxField();
registerNumberField();
registerDateField();
registerColorField();
registerHiddenField();
registerColumnComponents();
registerLayoutComponent();
registerRuleEditor();
registerErrorComponent();
registerLoadingComponent();
registerProductSelector();
registerSearchableMultiselect();
registerConfigForm();
registerDashboardTabs();
registerMediaSynchronize();
registerNebulaMenu();
registerStorePicker();
registerWidgetWizard();

// System Config <depends> wiring (DOM-based, runs once on page load).
function bootSystemConfig(): void {
    bootConfigDepends();
    bootTestConnection();
    bootImagePreview();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSystemConfig);
} else {
    bootSystemConfig();
}

// Defer Alpine.start() so sibling bundles (nebula-grid, nebula-form,
// nebula-pagebuilder, …) loaded later in the document get a chance to
// attach their `alpine:init` listeners first. Without this, any bundle
// executing AFTER nebula-core misses alpine:init and its `Alpine.data(...)`
// registrations never run — manifesting as "nebulaGrid is not defined"
// when Alpine tries to evaluate x-data on the grid element.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Alpine.start());
} else {
    queueMicrotask(() => Alpine.start());
}
