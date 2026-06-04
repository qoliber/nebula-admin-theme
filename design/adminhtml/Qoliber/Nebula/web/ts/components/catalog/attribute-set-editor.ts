/**
 * `attributeSetEditor` Alpine component — manages the drag-reorder
 * groups / attributes editor on the product attribute set edit screen.
 * Extracted from the inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/attribute-set/edit.phtml`.
 *
 * Reads server-side config off `data-*` attributes on the root element
 * so the phtml never embeds a `<script>` body.
 */

interface AttributeRow {
    attribute_id: number | string;
    code: string;
    label: string;
    entity_id?: number | string | undefined;
    is_user_defined?: boolean | number | undefined;
    is_unassignable?: boolean | undefined;
}

interface GroupRow {
    id: number | string;
    name: string;
    sort_order: number;
    attributes: AttributeRow[];
    collapsed: boolean;
    editing: boolean;
    addingAttribute: boolean;
    attrSearch: string;
}

interface AttributeSetConfig {
    setName: string;
    groups: Array<Omit<GroupRow, 'collapsed' | 'editing' | 'addingAttribute' | 'attrSearch'>>;
    unassigned: AttributeRow[];
    formKey: string;
    saveUrl: string;
    deleteUrl: string;
    messages: {
        promptGroup: string;
        groupExists: string;
        systemAttrs: string;
        confirmDeleteGroup: string;
        confirmDeleteSet: string;
        saveOk: string;
        saveError: string;
        nameRequired: string;
    };
}

interface SaveResponse {
    error?: boolean;
    message?: string;
    url?: string;
}

export function registerAttributeSetEditor(): void {
    const install = (): void => {
        window.Alpine?.data('attributeSetEditor', () => {
            const root = (document.currentScript as HTMLElement | null)?.closest<HTMLElement>('[data-nebula-attribute-set]')
                ?? document.querySelector<HTMLElement>('[data-nebula-attribute-set]');
            const rawConfig = root?.dataset.nebulaAttributeSet ?? '{}';
            const config = JSON.parse(rawConfig) as AttributeSetConfig;

            return {
                setName: config.setName,
                groups: [] as GroupRow[],
                unassigned: [] as AttributeRow[],
                removedGroups: [] as Array<number | string>,
                unassignedSearch: '',
                saving: false,
                message: '',
                messageError: false,

                init(): void {
                    this.groups = config.groups.map((g): GroupRow => ({
                        ...g,
                        collapsed: false,
                        editing: false,
                        addingAttribute: false,
                        attrSearch: '',
                    }));
                    this.unassigned = config.unassigned.slice();
                },

                filteredUnassigned(search: string): AttributeRow[] {
                    if (!search) return this.unassigned;
                    const q = search.toLowerCase();
                    return this.unassigned.filter((a) =>
                        a.code.toLowerCase().includes(q) || a.label.toLowerCase().includes(q)
                    );
                },

                addGroup(): void {
                    const name = window.prompt(config.messages.promptGroup);
                    if (!name || !name.trim()) return;
                    if (this.groups.some((g) => g.name.toLowerCase() === name.trim().toLowerCase())) {
                        window.alert(config.messages.groupExists);
                        return;
                    }
                    this.groups.push({
                        id: 'gNEW_' + String(Date.now()),
                        name: name.trim(),
                        sort_order: this.groups.length + 1,
                        attributes: [],
                        collapsed: false,
                        editing: false,
                        addingAttribute: false,
                        attrSearch: '',
                    });
                },

                startEditGroupName(group: GroupRow): void {
                    group.editing = true;
                    this.$nextTick(() => {
                        const input = (this.$el as HTMLElement).querySelector<HTMLInputElement>('input[x-model="group.name"]');
                        input?.focus();
                    });
                },

                async deleteGroup(gIdx: number): Promise<void> {
                    const group = this.groups[gIdx];
                    if (!group) return;
                    const hasSystem = group.attributes.some((a) => !a.is_unassignable);
                    if (hasSystem) {
                        window.alert(config.messages.systemAttrs);
                        return;
                    }
                    if (group.attributes.length > 0 && !(await window.Nebula!.confirm!({
                        title: 'Delete this group?',
                        message: config.messages.confirmDeleteGroup,
                        danger: true,
                        confirmText: 'Delete group',
                    }))) {
                        return;
                    }
                    group.attributes.forEach((attr) => {
                        if (attr.is_unassignable) {
                            this.unassigned.push({
                                attribute_id: attr.attribute_id,
                                code: attr.code,
                                label: attr.label,
                                is_user_defined: attr.is_user_defined,
                                entity_id: attr.entity_id,
                            });
                        }
                    });
                    if (typeof group.id === 'number' || (typeof group.id === 'string' && !group.id.startsWith('gNEW_'))) {
                        this.removedGroups.push(group.id);
                    }
                    this.groups.splice(gIdx, 1);
                },

                moveGroup(idx: number, direction: number): void {
                    const newIdx = idx + direction;
                    if (newIdx < 0 || newIdx >= this.groups.length) return;
                    const a = this.groups[idx];
                    const b = this.groups[newIdx];
                    if (!a || !b) return;
                    this.groups[idx] = b;
                    this.groups[newIdx] = a;
                    this.groups = this.groups.slice();
                },

                moveAttribute(group: GroupRow, idx: number, direction: number): void {
                    const newIdx = idx + direction;
                    if (newIdx < 0 || newIdx >= group.attributes.length) return;
                    const a = group.attributes[idx];
                    const b = group.attributes[newIdx];
                    if (!a || !b) return;
                    group.attributes[idx] = b;
                    group.attributes[newIdx] = a;
                    group.attributes = group.attributes.slice();
                },

                assignAttribute(group: GroupRow, attr: AttributeRow): void {
                    this.unassigned = this.unassigned.filter((a) => a.attribute_id !== attr.attribute_id);
                    group.attributes.push({
                        attribute_id: attr.attribute_id,
                        code: attr.code,
                        label: attr.label,
                        entity_id: attr.entity_id ?? 0,
                        is_user_defined: attr.is_user_defined,
                        is_unassignable: true,
                    });
                    group.addingAttribute = false;
                    group.attrSearch = '';
                },

                unassignAttribute(group: GroupRow, aIdx: number): void {
                    const attr = group.attributes[aIdx];
                    if (!attr) return;
                    group.attributes.splice(aIdx, 1);
                    this.unassigned.push({
                        attribute_id: attr.attribute_id,
                        code: attr.code,
                        label: attr.label,
                        is_user_defined: attr.is_user_defined,
                        entity_id: attr.entity_id,
                    });
                },

                async confirmDelete(): Promise<void> {
                    if (await window.Nebula!.confirm!({
                        title: 'Delete this attribute set?',
                        message: config.messages.confirmDeleteSet,
                        danger: true,
                        confirmText: 'Delete set',
                    })) {
                        window.location.href = config.deleteUrl;
                    }
                },

                showMessage(msg: string, isError: boolean): void {
                    this.message = msg;
                    this.messageError = isError;
                    window.setTimeout(() => { this.message = ''; }, 4000);
                },

                save(): void {
                    if (!this.setName.trim()) {
                        this.showMessage(config.messages.nameRequired, true);
                        return;
                    }
                    this.saving = true;

                    const reqGroups: Array<[number | string, string, number]> = [];
                    const reqAttributes: Array<[number | string, number | string, number, number | string]> = [];
                    const reqNotAttributes: Array<number | string> = [];

                    this.groups.forEach((group, gIdx) => {
                        reqGroups.push([group.id, group.name, gIdx + 1]);
                        group.attributes.forEach((attr, aIdx) => {
                            reqAttributes.push([attr.attribute_id, group.id, aIdx + 1, attr.entity_id ?? 0]);
                        });
                    });

                    this.unassigned.forEach((attr) => {
                        if (Number(attr.entity_id ?? 0) > 0) {
                            reqNotAttributes.push(attr.entity_id as number | string);
                        }
                    });

                    const data = {
                        attribute_set_name: this.setName,
                        groups: reqGroups,
                        attributes: reqAttributes,
                        not_attributes: reqNotAttributes,
                        removeGroups: [...new Set(this.removedGroups)],
                    };

                    const formData = new FormData();
                    formData.append('data', JSON.stringify(data));
                    formData.append('form_key', config.formKey);

                    fetch(config.saveUrl, {
                        method: 'POST',
                        body: formData,
                    })
                        .then((r) => r.json() as Promise<SaveResponse>)
                        .then((response) => {
                            this.saving = false;
                            if (response.error) {
                                this.showMessage(response.message ?? config.messages.saveError, true);
                            } else if (response.url) {
                                this.showMessage(config.messages.saveOk, false);
                                window.setTimeout(() => { window.location.href = response.url as string; }, 1000);
                            }
                        })
                        .catch(() => {
                            this.saving = false;
                            this.showMessage(config.messages.saveError, true);
                        });
                },
            };
        });
    };

    if (window.Alpine) {
        install();
    } else {
        document.addEventListener('alpine:init', install);
    }
}
