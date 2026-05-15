import { describe, expect, it } from 'vitest'
import { createWizardState, type WizardState } from '../../components/widget/wizard'

function emptyState(): WizardState {
    return {
        instance_id: null,
        instance_type: '',
        theme_id: null,
        title: '',
        store_ids: [],
        sort_order: 0,
        parameters: {},
        parameter_labels: {},
        page_groups: [],
    }
}

describe('widget wizard state machine', () => {
    it('paramsEnabled is false until type + theme picked', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {},
            availableChoosers: ['cms_block'],
        })

        expect(w.paramsEnabled()).toBe(false)

        w.state.instance_type = 'A\\B'
        expect(w.paramsEnabled()).toBe(false)

        w.state.theme_id = 7
        expect(w.paramsEnabled()).toBe(true)
    })

    it('loadFieldsForType pulls params and seeds defaults', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {
                'A\\B': [{ name: 'title', type: 'text', label: 'Title', required: false, default: 'hi' }],
            },
            availableChoosers: [],
        })
        w.state.instance_type = 'A\\B'
        w.state.theme_id = 7

        w.loadFieldsForType()

        expect(w.fields).toHaveLength(1)
        expect(w.fields[0]?.name).toBe('title')
        expect(w.state.parameters.title).toBe('hi')
    })

    it('loadFieldsForType resets page_groups on the new-widget flow when type genuinely changes', () => {
        const w = createWizardState({
            initial: {
                ...emptyState(),
                instance_id: null,
                instance_type: 'A\\B',
                page_groups: [
                    { page_group: 'all_pages', block: 'content', template: 'a.phtml',
                      for: 'all', page_id: '0', entities: '', page_label: '', layout_handle: '' },
                ],
            },
            paramsByType: { 'A\\B': [], 'C\\D': [] },
            availableChoosers: [],
        })

        w.state.instance_type = 'C\\D'
        w.loadFieldsForType()

        expect(w.state.page_groups).toEqual([])
    })

    it('loadFieldsForType does NOT reset on a spurious same-type call (Alpine init quirk)', () => {
        const existingRow = {
            page_group: 'all_pages', block: 'content', template: 'a.phtml',
            for: 'all' as const, page_id: '0', entities: '', page_label: '', layout_handle: '',
        }
        const w = createWizardState({
            initial: {
                ...emptyState(),
                instance_id: null,
                instance_type: 'A\\B',
                page_groups: [existingRow],
            },
            paramsByType: { 'A\\B': [] },
            availableChoosers: [],
        })

        // First call with the same type as initial — must NOT wipe (this is
        // the path that fired on edit-flow reload and clobbered loaded data).
        w.loadFieldsForType()
        expect(w.state.page_groups).toEqual([existingRow])
    })

    it('loadFieldsForType does NOT reset when first picking a type on new-widget flow', () => {
        // Brand-new widget: initial type is empty, user picks A\B for the first time.
        // No previous type means nothing to wipe.
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: { 'A\\B': [] },
            availableChoosers: [],
        })

        w.state.instance_type = 'A\\B'
        w.loadFieldsForType()

        expect(w.state.page_groups).toEqual([])  // already empty, still empty
    })

    it('loadFieldsForType preserves page_groups on the edit flow', () => {
        const existingRow = {
            page_group: 'all_pages', block: 'content', template: 'a.phtml',
            for: 'all' as const, page_id: '0', entities: '', page_label: '', layout_handle: '',
        }
        const w = createWizardState({
            initial: {
                ...emptyState(),
                instance_id: 42,             // edit flow
                instance_type: 'A\\B',
                page_groups: [existingRow],
            },
            paramsByType: { 'A\\B': [] },
            availableChoosers: [],
        })

        w.loadFieldsForType()

        // Type is locked on edit, but defensively the reset is gated on
        // instance_id so existing rows survive even if loadFieldsForType
        // is invoked.
        expect(w.state.page_groups).toEqual([existingRow])
    })

    it('loadFieldsForType preserves already-set parameter values', () => {
        const w = createWizardState({
            initial: { ...emptyState(), instance_type: 'A\\B', parameters: { title: 'pre-set' } },
            paramsByType: {
                'A\\B': [{ name: 'title', type: 'text', label: 'Title', required: false, default: 'default-val' }],
            },
            availableChoosers: [],
        })

        w.loadFieldsForType()

        expect(w.state.parameters.title).toBe('pre-set')
    })

    it('isPageGroupRowValid enforces per-kind required fields', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {},
            availableChoosers: [],
        })

        // Minimal valid baseline.
        const valid = {
            page_group: 'all_pages', block: 'content', template: 'tpl.phtml',
            for: 'all' as const, page_id: '0', entities: '',
            page_label: '', layout_handle: '',
        }
        expect(w.isPageGroupRowValid(valid)).toBe(true)
        expect(w.isPageGroupRowValid({ ...valid, page_group: '' })).toBe(false)
        expect(w.isPageGroupRowValid({ ...valid, block: '' })).toBe(false)
        expect(w.isPageGroupRowValid({ ...valid, template: '' })).toBe(false)

        // page_layouts requires layout_handle.
        expect(w.isPageGroupRowValid({ ...valid, page_group: 'page_layouts', layout_handle: '' })).toBe(false)
        expect(w.isPageGroupRowValid({ ...valid, page_group: 'page_layouts', layout_handle: '1column' })).toBe(true)

        // pages requires a non-zero page_id.
        expect(w.isPageGroupRowValid({ ...valid, page_group: 'pages', page_id: '0' })).toBe(false)
        expect(w.isPageGroupRowValid({ ...valid, page_group: 'pages', page_id: '5' })).toBe(true)

        // Specific-entity rows need at least one entity id.
        expect(w.isPageGroupRowValid({
            ...valid, page_group: 'anchor_categories', for: 'specific' as const, entities: '',
        })).toBe(false)
        expect(w.isPageGroupRowValid({
            ...valid, page_group: 'anchor_categories', for: 'specific' as const, entities: '5,7',
        })).toBe(true)
        // For-all categories don't need entities.
        expect(w.isPageGroupRowValid({
            ...valid, page_group: 'anchor_categories', for: 'all' as const, entities: '',
        })).toBe(true)
    })

    it('isValid rejects incomplete layout-update rows', () => {
        const w = createWizardState({
            initial: {
                ...emptyState(),
                instance_type: 'A\\B',
                theme_id: 7,
                title: 'X',
                page_groups: [
                    // Missing layout_handle for page_layouts row.
                    { page_group: 'page_layouts', block: 'content', template: 'tpl.phtml',
                      for: 'all', page_id: '0', entities: '', page_label: '', layout_handle: '' },
                ],
            },
            paramsByType: { 'A\\B': [] },
            availableChoosers: [],
        })

        expect(w.isValid()).toBe(false)
        const row = w.state.page_groups[0]
        if (row) row.layout_handle = '1column'
        expect(w.isValid()).toBe(true)
    })

    it('isValid requires type, theme, title, and all required params filled', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {
                'A\\B': [{ name: 'block_id', type: 'chooser', label: 'Block', required: true, default: '' }],
            },
            availableChoosers: ['cms_block'],
        })

        expect(w.isValid()).toBe(false)
        w.state.instance_type = 'A\\B'
        w.loadFieldsForType()
        expect(w.isValid()).toBe(false)
        w.state.theme_id = 7
        expect(w.isValid()).toBe(false)
        w.state.title = 'Hello'
        expect(w.isValid()).toBe(false)
        w.state.parameters.block_id = '42'
        expect(w.isValid()).toBe(true)
    })

    it('isChooserAvailable returns true only for choosers in the available list', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {},
            availableChoosers: ['cms_block'],
        })

        expect(w.isChooserAvailable('cms_block')).toBe(true)
        expect(w.isChooserAvailable('cms_page')).toBe(false)
        expect(w.isChooserAvailable('catalog_product')).toBe(false)
        expect(w.isChooserAvailable(undefined)).toBe(false)
    })

    it('chooser:selected handler writes value and label into state', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {},
            availableChoosers: ['cms_block'],
        })

        w.handleChooserSelected({ fieldName: 'block_id', value: '42', label: 'Footer (footer-links)' })

        expect(w.state.parameters.block_id).toBe('42')
        expect(w.state.parameter_labels.block_id).toBe('Footer (footer-links)')
    })

    it('isFieldVisible hides fields with visible=false', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {},
            availableChoosers: [],
        })

        expect(w.isFieldVisible({ name: 'ui', type: 'text', label: '', required: false, default: '', visible: false })).toBe(false)
        expect(w.isFieldVisible({ name: 'title', type: 'text', label: '', required: false, default: '' })).toBe(true)
    })

    it('isFieldVisible respects depends conditions', () => {
        const w = createWizardState({
            initial: { ...emptyState(), parameters: { show_pager: '0' } },
            paramsByType: {},
            availableChoosers: [],
        })

        const field: import('../../components/widget/wizard').WidgetFieldConfig = {
            name: 'products_per_page',
            type: 'text',
            label: 'Per Page',
            required: false,
            default: '',
            depends: [{ param: 'show_pager', value: '1' }],
        }

        expect(w.isFieldVisible(field)).toBe(false)
        w.state.parameters.show_pager = '1'
        expect(w.isFieldVisible(field)).toBe(true)
    })

    it('isValid ignores required check on hidden / depends-hidden fields', () => {
        const w = createWizardState({
            initial: emptyState(),
            paramsByType: {
                'X': [
                    { name: 'a', type: 'text', label: 'a', required: true, default: '' },
                    { name: 'b', type: 'text', label: 'b', required: true, default: '', depends: [{ param: 'a', value: 'show' }] },
                ],
            },
            availableChoosers: [],
        })
        w.state.instance_type = 'X'
        w.state.theme_id = 7
        w.state.title = 'T'
        w.loadFieldsForType()

        // a is hidden by nothing → must be filled; b depends on a==show → hidden until then.
        expect(w.isValid()).toBe(false)
        w.state.parameters.a = 'hidden-not-show'
        expect(w.isValid()).toBe(true) // b still hidden
        w.state.parameters.a = 'show'
        // Now b is visible + required + empty.
        expect(w.isValid()).toBe(false)
        w.state.parameters.b = 'filled'
        expect(w.isValid()).toBe(true)
    })

    it('currentCode looks up the widget code from typeCodeMap', () => {
        const w = createWizardState({
            initial: { ...emptyState(), instance_type: 'Magento\\Cms\\Block\\Widget\\Block' },
            paramsByType: {},
            availableChoosers: [],
            typeCodeMap: { 'Magento\\Cms\\Block\\Widget\\Block': 'cms_static_block' },
        })

        expect(w.currentCode()).toBe('cms_static_block')

        w.state.instance_type = 'Unknown\\Class'
        expect(w.currentCode()).toBe('')
    })

    it('containers / templates accessors cascade by container', () => {
        const w = createWizardState({
            initial: { ...emptyState(), instance_type: 'A\\B' },
            paramsByType: {},
            availableChoosers: [],
            containersByType: { 'A\\B': ['content', 'sidebar.main'] },
            containerTemplatesByType: {
                'A\\B': {
                    'content':      [{ value: 'grid.phtml', label: 'Grid' }],
                    'sidebar.main': [{ value: 'sidebar.phtml', label: 'Sidebar' }],
                },
            },
        })

        expect(w.containersForCurrentType()).toEqual(['content', 'sidebar.main'])
        expect(w.templatesForContainer('content')).toEqual([{ value: 'grid.phtml', label: 'Grid' }])
        expect(w.templatesForContainer('sidebar.main')).toEqual([{ value: 'sidebar.phtml', label: 'Sidebar' }])
        // Empty container → empty templates (Layout Updates shows "Please Select Container First").
        expect(w.templatesForContainer('')).toEqual([])
        expect(w.templatesForContainer('unknown')).toEqual([])
    })

    it('buildPostBody emits Magento-shaped form data', () => {
        const w = createWizardState({
            initial: {
                ...emptyState(),
                instance_id: 5,
                instance_type: 'A\\B',
                theme_id: 7,
                title: 'My Widget',
                store_ids: ['0', '1'],
                sort_order: 10,
                parameters: { block_id: '15', categories: ['2', '3'] },
                page_groups: [
                    { page_group: 'all_pages', block: 'content', template: 'tpl.phtml',
                      for: 'all', page_id: '0', entities: '', page_label: '', layout_handle: '' },
                ],
            },
            paramsByType: {},
            availableChoosers: [],
        })

        const body = w.buildPostBody()
        expect(body.get('instance_id')).toBe('5')
        expect(body.get('instance_type')).toBe('A\\B')
        // code is empty when typeCodeMap doesn't have the FQCN — caller-side responsibility to populate.
        expect(body.get('code')).toBe('')
        expect(body.get('theme_id')).toBe('7')
        expect(body.get('title')).toBe('My Widget')
        expect(body.get('sort_order')).toBe('10')
        expect(body.getAll('store_ids[]')).toEqual(['0', '1'])
        expect(body.get('parameters[block_id]')).toBe('15')
        expect(body.getAll('parameters[categories][]')).toEqual(['2', '3'])
        // Magento Save controller reads layout-update rows from
        // POST['widget_instance'], NOT 'page_groups'.
        expect(body.get('widget_instance[0][page_group]')).toBe('all_pages')
        expect(body.get('widget_instance[0][all_pages][block]')).toBe('content')
        expect(body.get('widget_instance[0][all_pages][template]')).toBe('tpl.phtml')
        expect(body.get('widget_instance[0][all_pages][for]')).toBe('all')
        expect(body.get('widget_instance[0][all_pages][page_id]')).toBe('0')
    })
})
