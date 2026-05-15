document.addEventListener('alpine:init', () => {
    Alpine.data('nebulaGrid', (config) => ({
        selected: [],
        loading: false,
        confirmAction: null,
        pendingFilters: JSON.parse(config.filters || '{}'),

        toggleSelectAll(checked) {
            if (checked) {
                this.selected = Array.from(
                    this.$root.querySelectorAll('tbody input[type="checkbox"][value]')
                ).map(el => el.value);
            } else {
                this.selected = [];
            }
        },

        applyAllFilters() {
            const url = new URL(window.location.href);
            // Clear existing filters
            Array.from(url.searchParams.keys()).forEach(key => {
                if (key.startsWith('filters[')) {
                    url.searchParams.delete(key);
                }
            });
            // Apply pending filters
            Object.entries(this.pendingFilters).forEach(([field, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set('filters[' + field + ']', value);
                }
            });
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        confirmMassAction(url, label) {
            if (this.selected.length === 0) {
                return;
            }
            this.confirmAction = { url, label };
        },

        executeMassAction() {
            if (!this.confirmAction) {
                return;
            }
            this.submitMassAction(this.confirmAction.url);
            this.confirmAction = null;
        },

        async submitMassAction(url) {
            if (this.selected.length === 0) {
                return;
            }

            this.loading = true;

            try {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;

                const fkInput = document.createElement('input');
                fkInput.type = 'hidden';
                fkInput.name = 'form_key';
                fkInput.value = config.formKey;
                form.appendChild(fkInput);

                this.selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            } catch (e) {
                console.error('Mass action error:', e);
                this.loading = false;
            }
        }
    }));
});
