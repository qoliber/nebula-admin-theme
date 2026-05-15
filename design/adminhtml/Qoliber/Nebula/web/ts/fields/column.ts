/**
 * Column renderers (read-only Alpine components used by grids).
 *
 * Kept in the same folder as field components because they share the
 * Alpine.data registration convention; no form-layer coupling.
 */

interface ColumnConfig {
    value?: unknown;
    label?: string;
    name?: string;
    sortable?: boolean;
    visible?: boolean;
    [extra: string]: unknown;
}

interface ColumnBase {
    value: unknown;
    label: string;
    name: string;
    sortable: boolean;
    visible: boolean;
}

type ColumnFactory = (config: ColumnConfig) => ColumnBase;

const baseColumnData = (config: ColumnConfig): ColumnBase => ({
    value: config.value ?? '',
    label: config.label ?? '',
    name: config.name ?? '',
    sortable: Boolean(config.sortable),
    visible: config.visible !== false,
});

const columnFactories: Record<string, ColumnFactory> = {
    text: (c) => baseColumnData(c),

    badge: (c) => {
        const b = baseColumnData(c);
        const variant = typeof c['variant'] === 'string' ? (c['variant'] as string) : 'default';
        const map = (c['map'] ?? {}) as Record<string, string>;
        return Object.assign(b, {
            variant,
            map,
            getBadgeClass(this: { value: unknown; map: Record<string, string>; variant: string }): string {
                return this.map[String(this.value)] ?? this.variant;
            },
        });
    },

    actions: (c) => {
        const b = baseColumnData(c);
        const actions = Array.isArray(c['actions']) ? c['actions'] : [];
        return Object.assign(b, { actions });
    },

    boolean: (c) => {
        const b = baseColumnData(c);
        const trueLabel = typeof c['trueLabel'] === 'string' ? (c['trueLabel'] as string) : 'Yes';
        const falseLabel = typeof c['falseLabel'] === 'string' ? (c['falseLabel'] as string) : 'No';
        return Object.assign(b, {
            getDisplayValue(this: { value: unknown }): string {
                return this.value ? trueLabel : falseLabel;
            },
        });
    },

    date: (c) => {
        const b = baseColumnData(c);
        const format = typeof c['format'] === 'string' ? (c['format'] as string) : 'YYYY-MM-DD';
        return Object.assign(b, {
            format,
            getFormattedDate(this: { value: unknown }): string {
                if (!this.value) return '';
                try {
                    return new Date(String(this.value)).toLocaleDateString();
                } catch {
                    return String(this.value);
                }
            },
        });
    },

    price: (c) => {
        const b = baseColumnData(c);
        const currency = typeof c['currency'] === 'string' ? (c['currency'] as string) : 'USD';
        return Object.assign(b, {
            currency,
            getFormattedPrice(this: { value: unknown; currency: string }): string {
                const n = parseFloat(String(this.value));
                if (isNaN(n)) return '';
                try {
                    return n.toLocaleString(undefined, { style: 'currency', currency: this.currency });
                } catch {
                    return n.toFixed(2);
                }
            },
        });
    },

    link: (c) => {
        const b = baseColumnData(c);
        return Object.assign(b, {
            href: typeof c['href'] === 'string' ? (c['href'] as string) : '#',
            target: typeof c['target'] === 'string' ? (c['target'] as string) : '_self',
        });
    },

    thumbnail: (c) => {
        const b = baseColumnData(c);
        return Object.assign(b, {
            src: typeof c['src'] === 'string' ? (c['src'] as string) : '',
            alt: typeof c['alt'] === 'string' ? (c['alt'] as string) : '',
            width: typeof c['width'] === 'number' ? (c['width'] as number) : 50,
            height: typeof c['height'] === 'number' ? (c['height'] as number) : 50,
        });
    },
};

export function registerColumnComponents(): void {
    document.addEventListener('alpine:init', () => {
        Object.keys(columnFactories).forEach((type) => {
            window.Alpine.data('nebulaColumn_' + type, (config: ColumnConfig = {}) => {
                const factory = columnFactories[type];
                if (!factory) return baseColumnData(config);
                return factory(config);
            });
        });
    });
}
