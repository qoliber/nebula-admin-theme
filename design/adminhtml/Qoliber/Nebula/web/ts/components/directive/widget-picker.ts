import type { NebulaDirectiveMeta, NebulaDirectiveOpenOptions } from '../../types';
import { createRuleEditor } from '../../rule-editor';
import type { RuleEditorConfig, SerializedPair } from '../../types';

interface DirectiveConfig {
    variableUrl: string;
    widgetTypesUrl: string;
    widgetParamsUrl: string;
    widgetBuildUrl: string;
    formKey: string;
    meta?: NebulaDirectiveMeta;
}

interface WidgetType {
    code: string;
    name: string;
    description: string;
    placeholderUrl?: string;
}

interface WidgetParam {
    name: string;
    label: string;
    type: 'text' | 'textarea' | 'select' | 'boolean' | 'chooser' | 'conditions' | 'unsupported';
    required: boolean;
    default: string;
    note: string;
    description?: string;
    visible?: boolean;
    chooser?: string;
    selectedLabel?: string;
    depends?: Array<{ param: string; value: string }>;
    ruleEditorConfig?: RuleEditorConfig;
    options?: Array<{ value: string; label: string }>;
    multiple?: boolean;
}

interface DirectiveResponse<T> {
    success: boolean;
    message?: string;
    data?: T;
}

async function parseWidgetResponse<T>(response: Response): Promise<T> {
    const payload = (await response.json()) as DirectiveResponse<T>;

    if (!response.ok || !payload.success || !payload.data) {
        throw new Error(payload.message ?? 'Widget request failed.');
    }

    return payload.data;
}

function getDirectiveAttribute(directive: string, name: string): string | null {
    const match = directive.match(new RegExp(name + '=(?:"([^"]*)"|([^\\s}]+))'));

    if (!match) {
        return null;
    }

    const value = match[1] ?? match[2] ?? '';

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

function parseWidgetDirective(directive: string): { type: string; values: Record<string, string> } | null {
    if (!directive.startsWith('{{widget')) {
        return null;
    }

    const type = getDirectiveAttribute(directive, 'type');

    if (!type) {
        return null;
    }

    const values: Record<string, string> = {};
    const attributePattern = /([a-zA-Z0-9_]+)=(?:"([^"]*)"|([^\s}]+))/g;
    let match: RegExpExecArray | null = attributePattern.exec(directive);

    while (match) {
        const key = match[1] ?? '';

        if (key !== '' && key !== 'type') {
            const rawValue = match[2] ?? match[3] ?? '';

            try {
                values[key] = decodeURIComponent(rawValue);
            } catch {
                values[key] = rawValue;
            }
        }

        match = attributePattern.exec(directive);
    }

    return { type, values };
}

export function registerWidgetPicker(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaWidgetPicker', (config: DirectiveConfig) => ({
            isOpen: false,
            loading: false,
            errorMessage: '',
            types: [] as WidgetType[],
            selectedType: '',
            params: [] as WidgetParam[],
            values: {} as Record<string, string>,
            chooserLabels: {} as Record<string, string>,
            pendingValues: {} as Record<string, string>,
            ruleEditors: {} as Record<string, ReturnType<typeof createRuleEditor>>,
            ruleEditorVersion: 0,
            resolver: null as ((directive: string | null) => void) | null,

            init(): void {
                const directiveApi = {
                    ...(window.NebulaDirective ?? {}),
                    openWidget: (options?: NebulaDirectiveOpenOptions) => this.open(options),
                };

                const meta = config.meta ?? window.NebulaDirective?.meta;
                if (meta) {
                    directiveApi.meta = meta;
                }

                window.NebulaDirective = directiveApi;
            },

            async open(options?: NebulaDirectiveOpenOptions): Promise<string | null> {
                this.isOpen = true;
                this.errorMessage = '';
                this.selectedType = '';
                this.params = [];
                this.values = {};
                this.chooserLabels = {};
                this.pendingValues = {};
                this.ruleEditors = {};
                this.ruleEditorVersion = 0;
                this.lockPageScroll();

                if (this.types.length === 0) {
                    await this.loadTypes();
                }

                if (options?.directive) {
                    const parsed = parseWidgetDirective(options.directive);

                    if (parsed) {
                        this.selectedType = parsed.type;
                        this.pendingValues = parsed.values;
                        await this.onTypeChange();
                    }
                }

                return new Promise<string | null>((resolve) => {
                    this.resolver = resolve;
                });
            },

            close(): void {
                this.isOpen = false;
                this.unlockPageScroll();
                const resolver = this.resolver;
                this.resolver = null;

                if (resolver) {
                    resolver(null);
                }
            },

            async loadTypes(): Promise<void> {
                this.loading = true;

                try {
                    const response = await fetch(config.widgetTypesUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await parseWidgetResponse<{ types: WidgetType[] }>(response);
                    this.types = data.types;
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to load widget types.';
                } finally {
                    this.loading = false;
                }
            },

            async onTypeChange(): Promise<void> {
                if (!this.selectedType) {
                    this.params = [];
                    this.values = {};
                    this.chooserLabels = {};
                    this.pendingValues = {};
                    this.ruleEditors = {};
                    return;
                }

                this.loading = true;
                this.errorMessage = '';
                this.params = [];
                this.values = {};
                this.chooserLabels = {};
                this.ruleEditors = {};
                this.ruleEditorVersion = 0;

                try {
                    const url = new URL(config.widgetParamsUrl, window.location.origin);
                    url.searchParams.set('type', this.selectedType);

                    if (Object.keys(this.pendingValues).length > 0) {
                        url.searchParams.set('current_values', JSON.stringify(this.pendingValues));
                    }

                    const response = await fetch(url.toString(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await parseWidgetResponse<{ params: WidgetParam[] }>(response);
                    this.params = data.params;

                    const defaults: Record<string, string> = {};

                    for (const param of data.params) {
                        defaults[param.name] = param.default;

                        if (param.type === 'chooser' && param.selectedLabel) {
                            this.chooserLabels[param.name] = param.selectedLabel;
                        }
                    }

                    this.values = {
                        ...defaults,
                        ...this.pendingValues,
                    };
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to load widget parameters.';
                } finally {
                    this.loading = false;
                }
            },

            async insertSelected(): Promise<void> {
                if (!this.selectedType || !this.resolver) {
                    return;
                }

                this.loading = true;
                this.errorMessage = '';

                try {
                    const formData = new FormData();
                    formData.append('form_key', config.formKey);
                    formData.append('widget_type', this.selectedType);

                    for (const param of this.params) {
                        if (param.type !== 'conditions' && !this.shouldSubmitField(param)) {
                            continue;
                        }

                        if (param.type === 'conditions') {
                            const editor = this.ruleEditors[param.name];

                            if (!editor) {
                                continue;
                            }

                            const serialized = editor.serialize() as SerializedPair[];

                            if (serialized.length === 0) {
                                continue;
                            }

                            for (const pair of serialized) {
                                formData.append(pair.name, String(pair.value));
                            }

                            continue;
                        }

                        const value = this.values[param.name] ?? '';

                        if (value !== '') {
                            formData.append('parameters[' + param.name + ']', value);
                        }
                    }

                    const response = await fetch(config.widgetBuildUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await parseWidgetResponse<{ directive: string }>(response);

                    const resolver = this.resolver;
                    this.resolver = null;
                    this.isOpen = false;
                    this.unlockPageScroll();
                    resolver(data.directive);
                } catch (error) {
                    this.errorMessage = error instanceof Error ? error.message : 'Unable to build the widget directive.';
                } finally {
                    this.loading = false;
                }
            },

            lockPageScroll(): void {
                document.documentElement.style.overflow = 'hidden';
                document.body.style.overflow = 'hidden';
            },

            unlockPageScroll(): void {
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
            },

            isFieldVisible(param: WidgetParam): boolean {
                if (param.visible === false) {
                    return false;
                }

                if (!param.depends || param.depends.length === 0) {
                    return true;
                }

                return param.depends.every(
                    (depend) => String(this.values[depend.param] ?? '') === depend.value,
                );
            },

            shouldSubmitField(param: WidgetParam): boolean {
                if (param.visible === false) {
                    return true;
                }

                return this.isFieldVisible(param);
            },

            isChooserAvailable(alias?: string): boolean {
                return typeof alias === 'string' && alias !== '' && alias !== 'generic';
            },

            getFieldValue(name: string): string {
                return String(this.values[name] ?? '');
            },

            setFieldValue(name: string, value: string): void {
                this.values = {
                    ...this.values,
                    [name]: value,
                };
            },

            getMultiValues(name: string): string[] {
                return this.getFieldValue(name)
                    .split(',')
                    .map((value) => value.trim())
                    .filter((value) => value !== '');
            },

            isMultiValueChecked(name: string, optionValue: string): boolean {
                return this.getMultiValues(name).includes(String(optionValue));
            },

            toggleMultiValue(name: string, optionValue: string, checked: boolean): void {
                const nextValues = this.getMultiValues(name).filter((value) => value !== String(optionValue));

                if (checked) {
                    nextValues.push(String(optionValue));
                }

                this.setFieldValue(name, nextValues.join(','));
            },

            isSelectOptionSelected(name: string, optionValue: string, multiple = false): boolean {
                const currentValue = this.getFieldValue(name);

                if (!multiple) {
                    return currentValue === String(optionValue);
                }

                return currentValue
                    .split(',')
                    .map((value) => value.trim())
                    .filter((value) => value !== '')
                    .includes(String(optionValue));
            },

            getChooserLabel(param: WidgetParam): string {
                return this.chooserLabels[param.name]
                    ?? this.getFieldValue(param.name)
                    ?? '';
            },

            openChooser(param: WidgetParam): void {
                if (!this.isChooserAvailable(param.chooser)) {
                    return;
                }

                window.dispatchEvent(
                    new CustomEvent('open-chooser-' + String(param.chooser).replace(/_/g, '-'), {
                        detail: {
                            fieldName: param.name,
                        },
                    }),
                );
            },

            handleChooserSelected(detail: { fieldName?: string; value?: string; label?: string }): void {
                if (!detail.fieldName || !this.params.some((param) => param.name === detail.fieldName)) {
                    return;
                }

                this.setFieldValue(detail.fieldName, String(detail.value ?? ''));
                this.chooserLabels = {
                    ...this.chooserLabels,
                    [detail.fieldName]: String(detail.label ?? detail.value ?? ''),
                };
            },

            mountRuleEditor(element: HTMLElement, param: WidgetParam): void {
                if (param.type !== 'conditions' || !param.ruleEditorConfig) {
                    return;
                }

                if (this.ruleEditors[param.name]) {
                    return;
                }

                const editor = createRuleEditor({
                    ...param.ruleEditorConfig,
                    registerModel: false,
                });

                const originalRerender = editor._rerender.bind(editor);

                editor._rerender = (): void => {
                    originalRerender();
                    this.ruleEditorVersion += 1;
                };

                editor.$el = element;
                editor.$nextTick = (callback: () => void): void => callback();
                editor.init();
                this.ruleEditors[param.name] = editor;
                this.ruleEditorVersion += 1;
            },

            renderRuleEditor(name: string): string {
                this.ruleEditorVersion;
                return this.ruleEditors[name]?.renderTree() ?? '';
            },
        }));
    };

    if (window.Alpine) {
        install();
    }

    document.addEventListener('alpine:init', install);
}
