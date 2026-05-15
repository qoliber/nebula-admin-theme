interface NebulaFieldsetState {
    open: boolean;
    pinned: boolean;
    toggle(): void;
    togglePin(): Promise<void>;
}

function createNebulaFieldset(
    initialOpen: boolean,
    initialPinned: boolean,
    sectionId: string,
    pinUrl: string,
): NebulaFieldsetState {
    return {
        open: initialOpen,
        pinned: initialPinned,
        toggle(): void {
            this.open = !this.open;
        },
        async togglePin(): Promise<void> {
            try {
                const formKey = document.querySelector<HTMLInputElement>('[name=form_key]')?.value ?? '';
                const resp = await fetch(pinUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ section: sectionId, form_key: formKey }),
                });
                const data = await resp.json() as { success?: boolean; pinned?: boolean };
                if (data.success) {
                    this.pinned = !!data.pinned;
                    if (data.pinned) this.open = true;
                }
            } catch (e) {
                console.error('Pin failed:', e);
            }
        },
    };
}

interface NebulaConfigFieldState {
    inherited: boolean;
    onToggle(el: HTMLInputElement): void;
}

function createNebulaConfigField(isInherited: boolean): NebulaConfigFieldState {
    return {
        inherited: isInherited,
        onToggle(el: HTMLInputElement): void {
            this.inherited = el.checked;
            const row = el.closest('.nebula-config-field');
            row?.querySelectorAll<HTMLElement>('select, input:not(.nebula-inherit-checkbox), textarea').forEach((inp) => {
                (inp as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).disabled = el.checked;
            });
        },
    };
}

export function registerConfigForm(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaFieldset', (
            initialOpen: boolean,
            initialPinned: boolean,
            sectionId: string,
            pinUrl: string,
        ) => createNebulaFieldset(initialOpen, initialPinned, sectionId, pinUrl));

        window.Alpine.data('nebulaConfigField', (isInherited: boolean) =>
            createNebulaConfigField(isInherited),
        );
    });
}
