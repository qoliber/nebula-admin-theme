import type { NebulaDirectiveMeta, NebulaDirectiveOpenOptions } from '../../types';

interface DirectiveConfig {
    variableUrl: string;
    widgetTypesUrl: string;
    widgetParamsUrl: string;
    widgetBuildUrl: string;
    formKey: string;
    meta?: NebulaDirectiveMeta;
}

interface Variable {
    label: string;
    group: string;
    type: 'default' | 'custom';
    code: string;
    directive: string;
}

interface DirectiveResponse<T> {
    success: boolean;
    message?: string;
    data?: T;
}

async function parseDirectiveResponse<T>(response: Response): Promise<T> {
    const payload = (await response.json()) as DirectiveResponse<T>;

    if (!response.ok || !payload.success || !payload.data) {
        throw new Error(payload.message ?? 'Directive request failed.');
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

function getVariableSelectionKey(directive: string): string | null {
    if (directive.startsWith('{{config')) {
        const path = getDirectiveAttribute(directive, 'path');
        return path ? 'default:' + path : null;
    }

    if (directive.startsWith('{{customVar')) {
        const code = getDirectiveAttribute(directive, 'code');
        return code ? 'custom:' + code : null;
    }

    return null;
}

export function registerVariablePicker(): void {
    const install = (): void => {
        window.Alpine?.data('nebulaVariablePicker', (config: DirectiveConfig) => ({
            isOpen: false,
            loading: false,
            variables: [] as Variable[],
            search: '',
            selected: null as Variable | null,
            resolver: null as ((directive: string | null) => void) | null,

            get filtered(): Variable[] {
                const q = this.search.toLowerCase();

                if (!q) {
                    return this.variables;
                }

                return this.variables.filter(
                    (v) =>
                        v.label.toLowerCase().includes(q) ||
                        v.group.toLowerCase().includes(q) ||
                        v.code.toLowerCase().includes(q),
                );
            },

            init(): void {
                const directiveApi = {
                    ...(window.NebulaDirective ?? {}),
                    openVariable: (options?: NebulaDirectiveOpenOptions) => this.open(options),
                };

                const meta = config.meta ?? window.NebulaDirective?.meta;
                if (meta) {
                    directiveApi.meta = meta;
                }

                window.NebulaDirective = directiveApi;
            },

            async open(options?: NebulaDirectiveOpenOptions): Promise<string | null> {
                this.isOpen = true;
                this.search = '';
                this.selected = null;
                this.lockPageScroll();

                if (this.variables.length === 0) {
                    await this.loadVariables();
                }

                if (options?.directive) {
                    const key = getVariableSelectionKey(options.directive);
                    if (key) {
                        this.selected = this.variables.find((variable) => key === variable.type + ':' + variable.code) ?? null;
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

            select(variable: Variable): void {
                this.selected = variable;
            },

            insertSelected(): void {
                if (!this.selected || !this.resolver) {
                    return;
                }

                const resolver = this.resolver;
                this.resolver = null;
                this.isOpen = false;
                this.unlockPageScroll();
                resolver(this.selected.directive);
            },

            async loadVariables(): Promise<void> {
                this.loading = true;

                try {
                    const response = await fetch(config.variableUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await parseDirectiveResponse<{ variables: Variable[] }>(response);
                    this.variables = data.variables;
                } catch {
                    // Leave variables empty; the table will show "No variables found."
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
        }));
    };

    if (window.Alpine) {
        install();
    }

    document.addEventListener('alpine:init', install);
}
