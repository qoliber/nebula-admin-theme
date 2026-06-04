/**
 * Nebula public contracts. Mirrors the JSON schemas under
 * app/code/Qoliber/NebulaComponent/schemas/ and adds the runtime-only types
 * (models, validation context) that never appear in JSON.
 */

// ─── Alpine global surface ────────────────────────────────────────────────

import type { Alpine as AlpineModule } from 'alpinejs';

declare global {
    // eslint-disable-next-line no-var -- Alpine binds this global at bundle-init time.
    var Alpine: AlpineModule;

    interface Window {
        Alpine: AlpineModule;
        Nebula?: NebulaGlobal;
        NebulaField?: NebulaFieldFactory;
        nebulaToast?: (type: ToastType, message: string) => void;
        NebulaDirective?: NebulaDirectiveApi;
    }
}

// ─── Core contracts ────────────────────────────────────────────────────────

export type Primitive = string | number | boolean | null;

export interface SerializedPair {
    name: string;
    value: Primitive;
}

export interface NebulaModel {
    serialize(): SerializedPair[];
    validate?(): boolean;
    destroy?(): void;
}

// ─── Validation ───────────────────────────────────────────────────────────

export type ValidationRuleFn = (value: unknown, param: unknown) => boolean;

export interface ValidationRulesObject {
    label?: string;
    required?: boolean;
    number?: boolean;
    email?: boolean;
    min?: number | string;
    max?: number | string;
    minLength?: number | string;
    maxLength?: number | string;
    pattern?: string;
    [custom: string]: unknown;
}

export type SnippetValidator = (form: HTMLFormElement) => HTMLElement[] | void;

export interface NebulaGlobal {
    validateField?: (input: HTMLElement) => boolean;
    validateForm?: (form: HTMLFormElement) => boolean;
    validateValue?: (value: unknown, rules: ValidationRulesObject | null | undefined) => string | null;
    registerValidator?: (fn: SnippetValidator) => void;
    clearFieldError?: (field: HTMLElement) => void;
    setFieldError?: (field: HTMLElement, message: string) => void;
    rule?: (name: string, fn: ValidationRuleFn, msg?: string) => void;
    modal?: (config?: ModalConfig) => Record<string, unknown>;
    confirm?: (options: ConfirmOptions) => Promise<boolean>;
    showSaveLoader?: () => void;
    hideSaveLoader?: () => void;
    hasUnsavedCategoryChanges?: () => boolean;
}

export interface NebulaDirectiveOpenOptions {
    directive?: string;
}

export interface NebulaDirectiveVariableMeta {
    label: string;
    group: string;
    directive: string;
}

export interface NebulaDirectiveWidgetMeta {
    name: string;
    description: string;
    placeholderUrl: string;
}

export interface NebulaDirectiveMeta {
    variables?: Record<string, NebulaDirectiveVariableMeta>;
    widgets?: Record<string, NebulaDirectiveWidgetMeta>;
}

export interface NebulaDirectiveApi {
    openVariable?(options?: NebulaDirectiveOpenOptions): Promise<string | null>;
    openWidget?(options?: NebulaDirectiveOpenOptions): Promise<string | null>;
    meta?: NebulaDirectiveMeta;
}

// ─── Field factories ──────────────────────────────────────────────────────

export interface NebulaFieldConfig {
    fieldName?: string;
    value?: unknown;
    default?: unknown;
    disabled?: boolean;
    required?: boolean;
    validation?: ValidationRulesObject;
    options?: FieldOption[];
    [extra: string]: unknown;
}

export interface NebulaFieldBase extends NebulaModel {
    value: unknown;
    fieldName: string;
    disabled: boolean;
    required: boolean;
    validation: ValidationRulesObject;
    error: string | null;
    _modelKey: string | null;
    init(): void;
}

export interface NebulaFieldFactory {
    base(config?: NebulaFieldConfig): NebulaFieldBase;
}

// ─── Modal mixin ──────────────────────────────────────────────────────────

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

export interface ModalConfig {
    title?: string;
    size?: ModalSize;
    steps?: string[];
    onOpen?: (this: ModalState) => void;
    onClose?: (this: ModalState) => void;
}

export interface ModalState {
    modalOpen: boolean;
    modalStep: number;
    modalSteps: string[];
    modalSize: ModalSize;
    modalTitle: string;
    openModal(): void;
    closeModal(): void;
    nextModalStep(canProceed?: (this: ModalState) => boolean): void;
    prevModalStep(): void;
    goToModalStep(index: number): void;
    readonly modalSizeClass: string;
    readonly isFirstModalStep: boolean;
    readonly isLastModalStep: boolean;
    readonly hasModalSteps: boolean;
    readonly currentModalStepLabel: string;
    readonly modalStepCount: number;
    // Alpine magics; only present when mixed into a component:
    $el?: HTMLElement;
    $nextTick?: (cb: () => void) => void;
}

// ─── Toast ────────────────────────────────────────────────────────────────

export type ToastType = 'success' | 'error' | 'warning' | 'notice';

// ─── Confirm dialog ───────────────────────────────────────────────────────

export interface ConfirmOptions {
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
}

// ─── Grid ─────────────────────────────────────────────────────────────────

export interface GridConfig {
    filters?: string;
    formKey: string;
}

export type ColumnType = 'text' | 'date' | 'price' | 'thumbnail' | 'badge' | 'actions';

export interface BadgeOption {
    label: string;
    class: 'success' | 'error' | 'warning' | 'info' | 'neutral';
}

export interface RowAction {
    label: string;
    url: string;
}

export interface ColumnDefinition {
    label: string;
    type?: ColumnType;
    renderer?: string;
    position: number;
    sortable?: boolean;
    searchable?: boolean;
    filter?: false | 'text' | 'select' | 'range' | 'date';
    filterOptions?: Record<string, string>;
    filterOptionsSource?: string;
    options?: Record<string, BadgeOption>;
    actions?: RowAction[];
}

export interface MassActionDefinition {
    id: string;
    label: string;
    url: string;
    confirm?: string;
}

// ─── Form / EAV ───────────────────────────────────────────────────────────

export interface FieldOption {
    value: string | null;
    label: string;
    conditionClass?: string;
    options?: FieldOption[];
}

export interface FieldValidationConstraints {
    required?: boolean;
    pattern?: string;
    min?: number | string;
    max?: number | string;
    minLength?: number;
    maxLength?: number;
}

export type FieldType =
    | 'text'
    | 'textarea'
    | 'wysiwyg'
    | 'select'
    | 'multiselect'
    | 'toggle'
    | 'checkbox'
    | 'number'
    | 'date'
    | 'color'
    | 'hidden';

export interface FieldDefinition {
    id: string;
    type: FieldType;
    label?: string;
    name?: string;
    value?: unknown;
    default?: unknown;
    disabled?: boolean;
    required?: boolean;
    position?: number;
    validation?: FieldValidationConstraints;
    options?: FieldOption[];
    [extra: string]: unknown;
}

// ─── Filter (snippet / grid filter widgets) ───────────────────────────────

export interface FilterDefinition {
    field: string;
    label?: string;
    type: 'text' | 'select' | 'range' | 'date';
    options?: FieldOption[];
    default?: unknown;
}

// ─── Rule editor ──────────────────────────────────────────────────────────

export type RuleInputType = 'string' | 'numeric' | 'date' | 'select' | 'boolean' | 'multiselect' | 'grid' | 'category';

export interface RuleAttribute {
    value: string;
    label: string;
    inputType?: RuleInputType;
    conditionClass?: string;
    options?: FieldOption[];
}

export interface RuleConditionTypeOption {
    value: string;
    label: string;
}

export interface RuleConditionData {
    type?: string;
    aggregator?: 'all' | 'any';
    attribute?: string | null;
    operator?: string;
    value?: string | number | boolean | null;
    conditions?: RuleConditionData[];
}

export interface RuleEditorConfig {
    availableAttributes?: RuleAttribute[];
    conditionTypes?: RuleConditionTypeOption[];
    ruleType?: 'catalog' | 'sales';
    fieldPrefix?: string;
    conditions?: RuleConditionData;
    registerModel?: boolean;
}

export interface RuleNode {
    id: string;
    type: 'combine' | 'leaf';
    className: string;
    aggregator: 'all' | 'any';
    attribute: string | null;
    operator: string;
    value: string;
    inputType: RuleInputType;
    children: RuleNode[];
}
