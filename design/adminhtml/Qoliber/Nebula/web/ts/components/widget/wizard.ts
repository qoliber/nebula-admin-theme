export interface WidgetFieldConfig {
    name: string
    type: 'text' | 'select' | 'multiselect' | 'chooser'
    label: string
    description?: string
    required: boolean
    visible?: boolean
    default: string
    sortOrder?: number
    options?: Array<{ value: string; label: string }>
    chooser?: string
    depends?: Array<{ param: string; value: string }>
}

export interface PageGroupRow {
    page_group: string
    block: string
    template: string
    for: 'all' | 'specific'
    page_id: string
    entities: string
    /** Display label for the picked page_id (CMS Page) — UI only, not posted. */
    page_label: string
    /** Layout handle for `page_layouts` group rows (e.g., 1column). */
    layout_handle: string
    /** Containers loaded for this row's page_group via the Blocks AJAX. */
    containers?: string[]
    /** Loading flag while the Blocks AJAX is in flight. */
    loading?: boolean
}

export interface WizardState {
    instance_id: number | null
    instance_type: string
    theme_id: number | null
    title: string
    store_ids: string[]
    sort_order: number
    parameters: Record<string, string | string[]>
    parameter_labels: Record<string, string>
    page_groups: PageGroupRow[]
}

export interface WizardOptions {
    initial: WizardState
    paramsByType: Record<string, WidgetFieldConfig[]>
    /** Chooser aliases that B1 actually ships. Anything else renders disabled. */
    availableChoosers: string[]
    /** Per-widget-type list of container references for Layout Updates. */
    containersByType?: Record<string, string[]>
    /** Per-widget-type, per-container map of valid templates. Layout Updates
     *  cascades the Template dropdown by the picked Container. */
    containerTemplatesByType?: Record<string, Record<string, Array<{ value: string; label: string }>>>
    /** Available page-group options for Layout Updates rows. */
    pageGroupOptions?: Array<{ value: string; label: string }>
    /** Page-layout options for the `page_layouts` group's layout_handle select. */
    pageLayoutOptions?: Array<{ value: string; label: string }>
    /** Class FQCN → widget code map. Used to post `code` on save. */
    typeCodeMap?: Record<string, string>
    /** page_group → frontend layout handle. Drives the Container AJAX. */
    layoutHandleMap?: Record<string, string>
    /** URL of Magento's `/admin/widget_instance/blocks/` endpoint. */
    blocksUrl?: string
}

export interface ChooserSelectedDetail {
    fieldName: string
    value: string
    label: string
}

export interface WizardController {
    state: WizardState
    fields: WidgetFieldConfig[]
    availableChoosers: string[]
    pageGroupOptions: Array<{ value: string; label: string }>
    pageLayoutOptions: Array<{ value: string; label: string }>
    typeCodeMap: Record<string, string>
    currentCode(): string
    /** Classify a row's page-group: specified-page picker, page-layouts select,
     *  category-entity selector, product-entity selector, or none. */
    rowEntityKind(row: PageGroupRow): 'page' | 'page_layout' | 'category' | 'product' | 'none'
    hasTypeAndTheme(): boolean
    paramsEnabled(): boolean
    isValid(): boolean
    isPageGroupRowValid(row: PageGroupRow): boolean
    isChooserAvailable(chooserAlias: string | undefined): boolean
    isFieldVisible(field: WidgetFieldConfig): boolean
    containersForCurrentType(): string[]
    templatesForContainer(containerName: string): Array<{ value: string; label: string }>
    loadFieldsForType(): void
    /** Internal: tracks the widget type the wizard was last loaded for so
     *  loadFieldsForType can tell a genuine type-change from a spurious watcher trip. */
    _previousType: string
    handleChooserSelected(detail: ChooserSelectedDetail): void
    addPageGroup(): void
    removePageGroup(idx: number): void
    /** Fired when a row's page_group select changes: fetch containers for the
     *  picked group's layout handle, populate row.containers. Falls back to
     *  the widget's static container list on error. */
    onPageGroupChanged(row: PageGroupRow): Promise<void>
    containersForRow(row: PageGroupRow): string[]
    buildPostBody(): URLSearchParams
}

export function createWizardState(opts: WizardOptions): WizardController {
    const ctl: WizardController = {
        state: opts.initial,
        fields: opts.paramsByType[opts.initial.instance_type] ?? [],
        availableChoosers: opts.availableChoosers,
        pageGroupOptions: opts.pageGroupOptions ?? [],
        pageLayoutOptions: opts.pageLayoutOptions ?? [],
        typeCodeMap: opts.typeCodeMap ?? {},
        // Tracks the widget type the wizard was last configured for.
        // Initialized to the loaded type so the first $watch trip (if Alpine
        // ever fires one during init) doesn't look like a "type change" and
        // wipe edit-flow page_groups.
        _previousType: opts.initial.instance_type,

        currentCode() {
            return this.typeCodeMap[this.state.instance_type] ?? ''
        },

        rowEntityKind(row) {
            switch (row.page_group) {
                case 'pages':
                    return 'page'
                case 'page_layouts':
                    return 'page_layout'
                case 'anchor_categories':
                case 'notanchor_categories':
                    return 'category'
                case '':
                    return 'none'
                default:
                    // *_products groups (all_products, simple_products, …)
                    return row.page_group.endsWith('_products') ? 'product' : 'none'
            }
        },

        hasTypeAndTheme() {
            return !!this.state.instance_type && !!this.state.theme_id
        },

        paramsEnabled() {
            return this.hasTypeAndTheme()
        },

        isValid() {
            if (!this.hasTypeAndTheme()) return false
            if (this.state.title.trim().length === 0) return false
            // Required parameter fields must be filled (unless hidden / depends-gated).
            if (!this.fields.every(
                f => !f.required || !this.isFieldVisible(f) || hasValue(this.state.parameters[f.name]),
            )) {
                return false
            }
            // Each layout-update row's required fields must be filled.
            return this.state.page_groups.every(r => this.isPageGroupRowValid(r))
        },

        isPageGroupRowValid(row) {
            if (!row.page_group) return false
            if (!row.block)      return false
            if (!row.template)   return false
            const kind = this.rowEntityKind(row)
            // `page_layouts` needs a layout_handle picked from the dropdown.
            if (kind === 'page_layout' && !row.layout_handle) return false
            // `pages` (Specified Page) needs a page_id picked via the cms_page chooser.
            if (kind === 'page' && (!row.page_id || row.page_id === '0')) return false
            // Specific-entity rows (category / product) need at least one id.
            if ((kind === 'category' || kind === 'product') && row.for === 'specific' && !row.entities) {
                return false
            }
            return true
        },

        isChooserAvailable(chooserAlias) {
            return !!chooserAlias && this.availableChoosers.includes(chooserAlias)
        },

        isFieldVisible(field) {
            // visible=false on widget.xml → never rendered as an editable
            // control; we still post the saved/default value via a hidden input.
            if (field.visible === false) return false
            // depends: every clause must match the current parameter value.
            if (!field.depends || field.depends.length === 0) return true
            return field.depends.every(({ param, value }) => {
                const current = this.state.parameters[param]
                if (Array.isArray(current)) return current.includes(value)
                return String(current ?? '') === value
            })
        },

        containersForCurrentType() {
            return opts.containersByType?.[this.state.instance_type] ?? []
        },

        templatesForContainer(containerName) {
            if (!containerName) return []
            const byType = opts.containerTemplatesByType?.[this.state.instance_type]
            return byType?.[containerName] ?? []
        },

        loadFieldsForType() {
            const previousType = this._previousType
            this._previousType = this.state.instance_type
            // Reset Layout Updates + chooser labels ONLY when:
            //   1. we're on the new-widget flow (instance_id null),
            //   2. there WAS a previous non-empty type (the brand-new widget's
            //      first pick — "" → "X\\B" — has nothing to wipe),
            //   3. the type genuinely changed (defense against spurious
            //      $watch trips on Alpine init).
            if (this.state.instance_id === null
                && previousType !== ''
                && previousType !== this.state.instance_type
            ) {
                this.state.page_groups = []
                this.state.parameter_labels = {}
            }
            this.fields = opts.paramsByType[this.state.instance_type] ?? []
            for (const f of this.fields) {
                if (this.state.parameters[f.name] === undefined) {
                    this.state.parameters[f.name] = f.default
                }
            }
        },

        handleChooserSelected(detail) {
            // Page-group entity / page selectors use a `pg:<rowIdx>:<field>`
            // fieldName convention so we don't collide with widget-parameter
            // chooser writes.
            if (detail.fieldName.startsWith('pg:')) {
                const [, idxStr, mode] = detail.fieldName.split(':')
                const idx = Number(idxStr)
                const row = this.state.page_groups[idx]
                if (!row) return
                if (mode === 'page') {
                    row.page_id = detail.value
                    row.page_label = detail.label
                } else if (mode === 'entities') {
                    // Append the picked entity id (strip the `category/` /
                    // `product/` prefix Magento's chooser writes) to the
                    // row's comma list, dedup.
                    const id = detail.value.replace(/^(category|product)\//, '')
                    const ids = row.entities ? row.entities.split(',').map(s => s.trim()).filter(Boolean) : []
                    if (!ids.includes(id)) {
                        ids.push(id)
                        row.entities = ids.join(',')
                    }
                }
                return
            }
            this.state.parameters[detail.fieldName] = detail.value
            this.state.parameter_labels[detail.fieldName] = detail.label
        },

        addPageGroup() {
            this.state.page_groups.push({
                page_group: '',
                block: '',
                template: '',
                for: 'all',
                page_id: '0',
                entities: '',
                page_label: '',
                layout_handle: '',
                containers: [],
                loading: false,
            })
        },

        removePageGroup(idx) {
            this.state.page_groups.splice(idx, 1)
        },

        async onPageGroupChanged(row) {
            row.block = ''
            row.template = ''
            row.containers = []
            if (!row.page_group || !opts.blocksUrl) {
                return
            }
            const handle = opts.layoutHandleMap?.[row.page_group] ?? ''
            if (!handle) {
                // No handle known for this group — fall back to widget's
                // statically-declared containers.
                row.containers = this.containersForCurrentType()
                return
            }
            row.loading = true
            try {
                const code = this.currentCode()
                const themeId = this.state.theme_id ? String(this.state.theme_id) : ''
                const body = new URLSearchParams({
                    layout: handle,
                    code,
                    theme_id: themeId,
                    isAjax: 'true',
                    form_key: getFormKey(),
                })
                const resp = await fetch(opts.blocksUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                    credentials: 'same-origin',
                })
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`)
                const html = await resp.text()
                row.containers = parseContainerSelect(html)
                if (row.containers.length === 0) {
                    row.containers = this.containersForCurrentType()
                }
            } catch (e) {
                // Network / parsing error — fall back to the static widget list.
                console.warn('[nebula widget wizard] Blocks AJAX failed, falling back to static containers', e)
                row.containers = this.containersForCurrentType()
            } finally {
                row.loading = false
            }
        },

        containersForRow(row) {
            return row.containers && row.containers.length > 0
                ? row.containers
                : this.containersForCurrentType()
        },

        buildPostBody() {
            const body = new URLSearchParams()
            if (this.state.instance_id !== null) body.set('instance_id', String(this.state.instance_id))
            body.set('instance_type', this.state.instance_type)
            body.set('code', this.currentCode())
            body.set('theme_id', String(this.state.theme_id ?? ''))
            body.set('title', this.state.title)
            body.set('sort_order', String(this.state.sort_order))
            for (const id of this.state.store_ids) body.append('store_ids[]', id)
            for (const [name, value] of Object.entries(this.state.parameters)) {
                if (Array.isArray(value)) {
                    for (const v of value) body.append(`parameters[${name}][]`, v)
                } else {
                    body.set(`parameters[${name}]`, value)
                }
            }
            // Magento's Save controller (\Magento\Widget\Controller\...\Save::execute)
            // reads page-group rows from POST['widget_instance'] — NOT 'page_groups'.
            // Keep that contract intact byte-for-byte.
            this.state.page_groups.forEach((pg, i) => {
                if (!pg.page_group) return
                body.set(`widget_instance[${i}][page_group]`, pg.page_group)
                const k = pg.page_group
                body.set(`widget_instance[${i}][${k}][page_id]`, pg.page_id || '0')
                body.set(`widget_instance[${i}][${k}][for]`, pg.for)
                body.set(`widget_instance[${i}][${k}][block]`, pg.block)
                body.set(`widget_instance[${i}][${k}][template]`, pg.template)
                if (pg.for === 'specific') {
                    body.set(`widget_instance[${i}][${k}][entities]`, pg.entities)
                }
                // `pages` + `page_layouts` carry their own layout_handle; the
                // other groups' handle is computed server-side from page_group.
                if (k === 'pages' || k === 'page_layouts') {
                    body.set(`widget_instance[${i}][${k}][layout_handle]`, pg.layout_handle)
                }
            })
            return body
        },
    }
    return ctl
}

function hasValue(v: string | string[] | undefined): boolean {
    if (v === undefined) return false
    if (Array.isArray(v)) return v.length > 0
    return v.length > 0
}

/**
 * Pulls container names out of the HTML <select name="block"> the
 * /admin/widget_instance/blocks/ endpoint returns. The placeholder
 * empty <option value=""> is skipped.
 */
function parseContainerSelect(html: string): string[] {
    if (!html.trim()) return []
    const doc = new DOMParser().parseFromString(html, 'text/html')
    const opts = Array.from(doc.querySelectorAll('option'))
    return opts
        .map(o => o.getAttribute('value') ?? '')
        .filter(v => v !== '')
}

function getFormKey(): string {
    const el = document.querySelector<HTMLInputElement>('input[name="form_key"]')
    return el?.value ?? ''
}

export function registerWidgetWizard(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaWidgetWizard', (opts: WizardOptions) => createWizardState(opts))
    })
}
