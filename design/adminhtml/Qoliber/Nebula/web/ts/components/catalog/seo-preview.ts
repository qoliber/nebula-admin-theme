interface SeoPreviewConfig {
    title: string;
    url: string;
    description: string;
    baseUrl: string;
}

function createSeoPreview(config: SeoPreviewConfig) {
    return {
        title: config.title,
        url: config.url,
        description: config.description,
        baseUrl: config.baseUrl,

        init(this: ReturnType<typeof createSeoPreview>): void {
            const self = this;
            const listen = (attrCode: string, prop: keyof SeoPreviewConfig): void => {
                const el = document.getElementById('nebula-eav-' + attrCode)
                    ?? document.querySelector<HTMLInputElement>(
                        '[name=' + attrCode + '], [name$="[' + attrCode + ']"]',
                    );
                if (el) {
                    el.addEventListener('input', (e) => {
                        (self as Record<string, unknown>)[prop] = (e.target as HTMLInputElement).value;
                    });
                }
            };
            listen('meta_title', 'title');
            listen('url_key', 'url');
            listen('meta_description', 'description');
            listen('name', 'title');
        },
    };
}

export function registerSeoPreview(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaSeoPreview', (config: SeoPreviewConfig) => createSeoPreview(config));
    });
}
