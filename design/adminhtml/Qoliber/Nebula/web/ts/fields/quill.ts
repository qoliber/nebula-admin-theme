import Quill from 'quill';
import type Embed from 'quill/blots/embed';

import { createBaseField } from './base';
import type {
    NebulaDirectiveMeta,
    NebulaDirectiveOpenOptions,
    NebulaFieldConfig,
    ValidationRulesObject,
} from '../types';

const HISTORY_DELAY = 500;
const HISTORY_MAX_STACK = 100;
const DEFAULT_TABLE_ROWS = 2;
const DEFAULT_TABLE_COLUMNS = 2;
const DIRECTIVE_PATTERN = /{{(?:widget|config|customVar)\b[^{}]*}}/g;

const UNDO_ICON =
    '<svg viewBox="0 0 18 18"><polyline class="ql-stroke" points="6 5 3 8 6 11"></polyline><path class="ql-stroke" d="M5 8h5a4 4 0 1 1 0 8h-1"></path></svg>';
const REDO_ICON =
    '<svg viewBox="0 0 18 18"><polyline class="ql-stroke" points="12 5 15 8 12 11"></polyline><path class="ql-stroke" d="M13 8H8a4 4 0 1 0 0 8h1"></path></svg>';
const VARIABLE_ICON =
    '<svg viewBox="0 0 18 18"><text class="ql-fill" font-family="monospace" font-size="11" font-weight="600" x="1" y="13">{x}</text></svg>';
const WIDGET_ICON =
    '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5"><rect class="ql-stroke" x="2" y="2" width="6" height="6" rx="1"/><rect class="ql-stroke" x="10" y="2" width="6" height="6" rx="1"/><rect class="ql-stroke" x="2" y="10" width="6" height="6" rx="1"/><rect class="ql-stroke" x="10" y="10" width="6" height="6" rx="1"/></svg>';

interface QuillRefs {
    editorHost?: HTMLElement;
    fallbackInput?: HTMLTextAreaElement;
    linkUrlInput?: HTMLInputElement;
    tableRowsInput?: HTMLInputElement;
}

interface QuillState extends ReturnType<typeof createBaseField> {
    editor: Quill | null;
    editorReady: boolean;
    sourceMode: boolean;
    toolbarElement: HTMLDivElement | null;
    lastRange: QuillRange | null;
    savedRange: QuillRange | null;
    editingImageRange: QuillRange | null;
    linkDialogOpen: boolean;
    linkDialogUrl: string;
    linkDialogText: string;
    tableDialogOpen: boolean;
    tableRows: number;
    tableColumns: number;
    $refs?: QuillRefs;
    $nextTick?: (callback: () => void) => void;
    validation: ValidationRulesObject;
    handleSourceInput(): void;
    toggleSourceMode(): void;
    openSourceVariablePicker(): void;
    openSourceWidgetPicker(): void;
    openSourceImagePicker(): void;
    openLinkDialog(): void;
    closeLinkDialog(): void;
    submitLinkDialog(): void;
    openTableDialog(): void;
    closeTableDialog(): void;
    submitTableDialog(): void;
}

interface HistoryModule {
    undo(): void;
    redo(): void;
}

interface TableModule {
    insertTable(rows: number, columns: number): void;
}

interface QuillRange {
    index: number;
    length: number;
}

interface NebulaMediaSelection {
    src: string;
    alt: string;
    width: number | null;
    height: number | null;
    alignment: 'left' | 'center' | 'right';
}

interface NebulaMediaApi {
    open(options?: {
        resetSelection?: boolean;
        selection?: NebulaMediaSelection | null;
    }): Promise<NebulaMediaSelection | null>;
}

interface DirectiveDescriptor {
    directive: string;
    kind: 'widget' | 'variable';
    label: string;
    subtitle: string;
    placeholderUrl: string | null;
}

declare global {
    interface Window {
        NebulaMedia?: NebulaMediaApi;
    }
}

function asString(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }

    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function normalizeHtml(html: string): string {
    const trimmed = html.trim();

    return trimmed === '<p><br></p>' ? '' : trimmed;
}

function getDirectiveMeta(): NebulaDirectiveMeta | undefined {
    return window.NebulaDirective?.meta;
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

function humanizeSegment(value: string): string {
    return value
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .trim();
}

function getFriendlyWidgetName(type: string): string {
    const parts = type.split('\\').filter(Boolean);
    const label = parts.slice(-2).map(humanizeSegment).join(' ');

    return label || humanizeSegment(type);
}

function getVariableLookupKey(directive: string): string | null {
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

function describeDirective(directive: string): DirectiveDescriptor {
    if (directive.startsWith('{{widget')) {
        const type = getDirectiveAttribute(directive, 'type') ?? '';
        const widgetMeta = type ? getDirectiveMeta()?.widgets?.[type] : undefined;

        return {
            directive,
            kind: 'widget',
            label: widgetMeta?.name || getFriendlyWidgetName(type),
            subtitle: type,
            placeholderUrl: widgetMeta?.placeholderUrl || null,
        };
    }

    const key = getVariableLookupKey(directive);
    const variableMeta = key ? getDirectiveMeta()?.variables?.[key] : undefined;
    const code = getDirectiveAttribute(directive, 'path') ?? getDirectiveAttribute(directive, 'code') ?? directive;
    const group = variableMeta?.group ? variableMeta.group + ' / ' : '';

    return {
        directive,
        kind: 'variable',
        label: variableMeta ? group + variableMeta.label : humanizeSegment(code),
        subtitle: code,
        placeholderUrl: null,
    };
}

function buildDirectivePlaceholderMarkup(descriptor: DirectiveDescriptor): string {
    const preview = descriptor.kind === 'widget' && descriptor.placeholderUrl
        ? '<span class="nebula-quill-directive__preview"><img src="'
            + escapeHtmlAttribute(descriptor.placeholderUrl)
            + '" alt=""></span>'
        : '<span class="nebula-quill-directive__badge">'
            + (descriptor.kind === 'widget' ? 'Widget' : 'Variable')
            + '</span>';

    return preview
        + '<span class="nebula-quill-directive__content">'
        + '<span class="nebula-quill-directive__label">'
        + escapeHtmlAttribute(descriptor.label)
        + '</span>'
        + '<span class="nebula-quill-directive__subtitle">'
        + escapeHtmlAttribute(descriptor.subtitle)
        + '</span>'
        + '</span>';
}

function buildDirectivePlaceholderHtml(directive: string): string {
    const descriptor = describeDirective(directive);

    return '<span class="nebula-quill-directive nebula-quill-directive--'
        + descriptor.kind
        + '" contenteditable="false" data-directive="'
        + escapeHtmlAttribute(descriptor.directive)
        + '" data-kind="'
        + escapeHtmlAttribute(descriptor.kind)
        + '" data-label="'
        + escapeHtmlAttribute(descriptor.label)
        + '" data-subtitle="'
        + escapeHtmlAttribute(descriptor.subtitle)
        + '"'
        + (descriptor.placeholderUrl
            ? ' data-placeholder-url="' + escapeHtmlAttribute(descriptor.placeholderUrl) + '"'
            : '')
        + '>'
        + buildDirectivePlaceholderMarkup(descriptor)
        + '</span>';
}

const EmbedBlot = Quill.import('blots/embed') as typeof Embed;

let directiveBlotRegistered = false;

function registerDirectiveBlot(): void {
    if (directiveBlotRegistered) {
        return;
    }

    class NebulaDirectiveBlot extends EmbedBlot {
        static override create(value: DirectiveDescriptor): HTMLElement {
            const node = super.create() as HTMLElement;

            node.classList.add('nebula-quill-directive--' + value.kind);
            node.setAttribute('contenteditable', 'false');
            node.dataset.directive = value.directive;
            node.dataset.kind = value.kind;
            node.dataset.label = value.label;
            node.dataset.subtitle = value.subtitle;

            if (value.placeholderUrl) {
                node.dataset.placeholderUrl = value.placeholderUrl;
            } else {
                delete node.dataset.placeholderUrl;
            }

            node.innerHTML = buildDirectivePlaceholderMarkup(value);

            return node;
        }

        static override value(node: HTMLElement): DirectiveDescriptor {
            return {
                directive: node.dataset.directive ?? '',
                kind: (node.dataset.kind === 'widget' ? 'widget' : 'variable') as 'widget' | 'variable',
                label: node.dataset.label ?? '',
                subtitle: node.dataset.subtitle ?? '',
                placeholderUrl: node.dataset.placeholderUrl ?? null,
            };
        }
    }

    (NebulaDirectiveBlot as { blotName?: string }).blotName = 'nebula-directive';
    (NebulaDirectiveBlot as { className?: string }).className = 'nebula-quill-directive';
    (NebulaDirectiveBlot as { tagName?: string }).tagName = 'span';

    Quill.register(NebulaDirectiveBlot, true);
    directiveBlotRegistered = true;
}

function cloneRange(range: QuillRange | null | undefined): QuillRange | null {
    if (!range) {
        return null;
    }

    return {
        index: range.index,
        length: range.length,
    };
}

function getEndRange(quill: Quill): QuillRange {
    return {
        index: quill.getLength(),
        length: 0,
    };
}

function getDialogRange(quill: Quill, lastRange: QuillRange | null): QuillRange {
    return cloneRange(lastRange) ?? getEndRange(quill);
}

function focusEditorRoot(quill: Quill | null, nextTick?: (callback: () => void) => void): void {
    if (!quill) {
        return;
    }

    const focus = (): void => {
        quill.root.focus();
    };

    if (nextTick) {
        nextTick(focus);
        return;
    }

    window.setTimeout(focus, 0);
}

function restoreRange(quill: Quill, range: QuillRange | null): QuillRange | null {
    if (!range) {
        return null;
    }

    quill.setSelection(range.index, range.length, 'silent');

    return cloneRange(range);
}

function getSelectedText(quill: Quill, range: QuillRange): string {
    return quill.getText(range.index, range.length).replace(/\n+$/, '');
}

function escapeHtmlAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function buildImageHtml(selection: NebulaMediaSelection): string {
    const attributes = [
        'src="' + escapeHtmlAttribute(selection.src) + '"',
        'alt="' + escapeHtmlAttribute(selection.alt) + '"',
    ];
    const styles: string[] = [];

    if (selection.width !== null && Number.isFinite(selection.width) && selection.width > 0) {
        attributes.push('width="' + String(selection.width) + '"');
    }

    if (selection.height !== null && Number.isFinite(selection.height) && selection.height > 0) {
        attributes.push('height="' + String(selection.height) + '"');
    }

    if (selection.alignment === 'left') {
        styles.push('display:block', 'float:left', 'margin:0 16px 16px 0');
    } else if (selection.alignment === 'right') {
        styles.push('display:block', 'float:right', 'margin:0 0 16px 16px');
    } else {
        styles.push('display:block', 'margin:0 auto 16px auto');
    }

    attributes.push('style="' + escapeHtmlAttribute(styles.join(';')) + '"');

    return '<img ' + attributes.join(' ') + '>';
}

function renderVisualHtml(sourceHtml: string): string {
    return sourceHtml.replace(DIRECTIVE_PATTERN, (directive) => buildDirectivePlaceholderHtml(directive));
}

function serializeEditorHtml(root: HTMLElement): string {
    const clone = root.cloneNode(true) as HTMLElement;

    clone.querySelectorAll<HTMLElement>('.nebula-quill-directive').forEach((placeholder) => {
        placeholder.replaceWith(document.createTextNode(placeholder.dataset.directive ?? ''));
    });

    return normalizeHtml(clone.innerHTML);
}

function getImageAlignment(image: HTMLImageElement): 'left' | 'center' | 'right' {
    const style = (image.getAttribute('style') ?? '').toLowerCase();

    if (style.includes('float:right')) {
        return 'right';
    }

    if (style.includes('margin:0 auto') || style.includes('margin-left:auto') || style.includes('margin-right:auto')) {
        return 'center';
    }

    return 'left';
}

function getImageSelection(image: HTMLImageElement): NebulaMediaSelection {
    const width = image.getAttribute('width');
    const height = image.getAttribute('height');

    return {
        src: image.getAttribute('src') ?? '',
        alt: image.getAttribute('alt') ?? '',
        width: width ? Number(width) : null,
        height: height ? Number(height) : null,
        alignment: getImageAlignment(image),
    };
}

function getImageRange(quill: Quill, image: HTMLImageElement): QuillRange | null {
    const blot = Quill.find(image, true) as { statics?: { blotName?: string } } | null;
    if (!blot) {
        return null;
    }

    const index = quill.getIndex(blot as never);

    return {
        index,
        length: 1,
    };
}

function getDirectiveRange(quill: Quill, directiveElement: HTMLElement): QuillRange | null {
    const blot = Quill.find(directiveElement, true) as { statics?: { blotName?: string } } | null;

    if (!blot) {
        return null;
    }

    return {
        index: quill.getIndex(blot as never),
        length: 1,
    };
}

function insertDirective(
    quill: Quill,
    promise: Promise<string | null>,
    savedRange: QuillRange,
    nextTick?: (callback: () => void) => void,
): void {
    void promise.then((directive) => {
        if (!directive) {
            focusEditorRoot(quill, nextTick);
            return;
        }

        const range = restoreRange(quill, savedRange) ?? savedRange;
        const insertAt = range.index + range.length;
        quill.insertEmbed(insertAt, 'nebula-directive', describeDirective(directive), 'user');
        quill.setSelection(insertAt + 1, 0, 'silent');
        focusEditorRoot(quill, nextTick);
    });
}

function insertAtCursor(textarea: HTMLTextAreaElement, content: string): void {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? start;

    textarea.setRangeText(content, start, end, 'end');
    textarea.focus();
}

function buildButton(className: string, title: string, icon?: string): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.title = title;
    button.setAttribute('aria-label', title);

    if (icon) {
        button.innerHTML = icon;
    }

    return button;
}

function buildToolbar(): HTMLDivElement {
    const toolbar = document.createElement('div');

    const headerGroup = document.createElement('span');
    headerGroup.className = 'ql-formats';
    const headerSelect = document.createElement('select');
    headerSelect.className = 'ql-header';
    [null, '1', '2', '3'].forEach((value) => {
        const option = document.createElement('option');
        if (value !== null) {
            option.value = value;
        } else {
            option.selected = true;
        }
        headerSelect.appendChild(option);
    });
    headerGroup.appendChild(headerSelect);
    toolbar.appendChild(headerGroup);

    const inlineGroup = document.createElement('span');
    inlineGroup.className = 'ql-formats';
    ['bold', 'italic', 'underline', 'strike'].forEach((name) => {
        inlineGroup.appendChild(buildButton('ql-' + name, name));
    });
    toolbar.appendChild(inlineGroup);

    const blockGroup = document.createElement('span');
    blockGroup.className = 'ql-formats';
    ['blockquote', 'code-block'].forEach((name) => {
        blockGroup.appendChild(buildButton('ql-' + name, name));
    });
    toolbar.appendChild(blockGroup);

    const listGroup = document.createElement('span');
    listGroup.className = 'ql-formats';
    const ordered = buildButton('ql-list', 'ordered list');
    ordered.value = 'ordered';
    const bullet = buildButton('ql-list', 'bullet list');
    bullet.value = 'bullet';
    listGroup.appendChild(ordered);
    listGroup.appendChild(bullet);
    toolbar.appendChild(listGroup);

    const mediaGroup = document.createElement('span');
    mediaGroup.className = 'ql-formats';
    ['link', 'image', 'table'].forEach((name) => {
        mediaGroup.appendChild(buildButton('ql-' + name, name));
    });
    toolbar.appendChild(mediaGroup);

    const alignGroup = document.createElement('span');
    alignGroup.className = 'ql-formats';
    const alignSelect = document.createElement('select');
    alignSelect.className = 'ql-align';
    [null, 'center', 'right', 'justify'].forEach((value) => {
        const option = document.createElement('option');
        if (value !== null) {
            option.value = value;
        } else {
            option.selected = true;
        }
        alignSelect.appendChild(option);
    });
    alignGroup.appendChild(alignSelect);
    toolbar.appendChild(alignGroup);

    const directiveGroup = document.createElement('span');
    directiveGroup.className = 'ql-formats';
    directiveGroup.appendChild(buildButton('ql-variable', 'Insert Variable', VARIABLE_ICON));
    directiveGroup.appendChild(buildButton('ql-widget', 'Insert Widget', WIDGET_ICON));
    toolbar.appendChild(directiveGroup);

    const historyGroup = document.createElement('span');
    historyGroup.className = 'ql-formats';
    historyGroup.appendChild(buildButton('ql-undo', 'undo', UNDO_ICON));
    historyGroup.appendChild(buildButton('ql-redo', 'redo', REDO_ICON));
    historyGroup.appendChild(buildButton('ql-clean', 'clear formatting'));
    toolbar.appendChild(historyGroup);

    return toolbar;
}

export function registerQuillField(): void {
    const install = (): void => {
        window.Alpine.data('nebulaField_quill', (config: NebulaFieldConfig = {}) => {
            const base = createBaseField(config);
            const baseInit = base.init;
            const baseDestroy = base.destroy;

            return Object.assign(base, {
                editor: null as Quill | null,
                editorReady: false,
                sourceMode: false,
                toolbarElement: null as HTMLDivElement | null,
                lastRange: null as QuillRange | null,
                savedRange: null as QuillRange | null,
                editingImageRange: null as QuillRange | null,
                linkDialogOpen: false,
                linkDialogUrl: '',
                linkDialogText: '',
                tableDialogOpen: false,
                tableRows: DEFAULT_TABLE_ROWS,
                tableColumns: DEFAULT_TABLE_COLUMNS,

                init(this: QuillState): void {
                    baseInit.call(this);

                    const afterInit = this.$nextTick ?? ((callback: () => void) => callback());
                    afterInit(() => {
                        const host = this.$refs?.editorHost;
                        const fallbackInput = this.$refs?.fallbackInput;
                        const component = this;

                        if (!host || !fallbackInput) {
                            return;
                        }

                        registerDirectiveBlot();

                        const initialValue = asString(this.value || fallbackInput.value);
                        const toolbar = buildToolbar();
                        this.toolbarElement = toolbar;
                        host.before(toolbar);
                        const editor = new Quill(host, {
                            modules: {
                                history: {
                                    delay: HISTORY_DELAY,
                                    maxStack: HISTORY_MAX_STACK,
                                    userOnly: true,
                                },
                                table: true,
                                toolbar: {
                                    container: toolbar,
                                    handlers: {
                                        link(): void {
                                            component.openLinkDialog();
                                        },
                                        image(this: { quill: Quill }): void {
                                            const selection = getDialogRange(this.quill, component.lastRange);
                                            if (!window.NebulaMedia?.open) {
                                                console.error('NebulaMedia is not available.');
                                                return;
                                            }

                                            component.editingImageRange = null;

                                            void window.NebulaMedia.open({ resetSelection: true }).then((imageSelection) => {
                                                if (!imageSelection) {
                                                    focusEditorRoot(this.quill, component.$nextTick);
                                                    return;
                                                }

                                                const imageRange = component.editingImageRange;
                                                const range = imageRange
                                                    ? restoreRange(this.quill, imageRange) ?? imageRange
                                                    : restoreRange(this.quill, selection) ?? selection;
                                                const index = imageRange ? range.index : range.index + range.length;

                                                if (imageRange) {
                                                    this.quill.deleteText(range.index, range.length, 'user');
                                                }

                                                this.quill.clipboard.dangerouslyPasteHTML(
                                                    index,
                                                    buildImageHtml(imageSelection),
                                                    'user',
                                                );
                                                this.quill.setSelection(index + 1, 0, 'silent');
                                                component.lastRange = { index: index + 1, length: 0 };
                                                component.editingImageRange = null;
                                                focusEditorRoot(this.quill, component.$nextTick);
                                            });
                                        },
                                        table(): void {
                                            component.openTableDialog();
                                        },
                                        variable(this: { quill: Quill }): void {
                                            const savedRange = getDialogRange(this.quill, component.lastRange);

                                            if (!window.NebulaDirective?.openVariable) {
                                                console.error('NebulaDirective is not available.');
                                                return;
                                            }

                                            insertDirective(
                                                this.quill,
                                                window.NebulaDirective.openVariable(),
                                                savedRange,
                                                component.$nextTick,
                                            );
                                        },
                                        widget(this: { quill: Quill }): void {
                                            const savedRange = getDialogRange(this.quill, component.lastRange);

                                            if (!window.NebulaDirective?.openWidget) {
                                                console.error('NebulaDirective is not available.');
                                                return;
                                            }

                                            insertDirective(
                                                this.quill,
                                                window.NebulaDirective.openWidget(),
                                                savedRange,
                                                component.$nextTick,
                                            );
                                        },
                                        undo(this: { quill: Quill }): void {
                                            const history = this.quill.getModule('history') as HistoryModule | undefined;
                                            history?.undo();
                                        },
                                        redo(this: { quill: Quill }): void {
                                            const history = this.quill.getModule('history') as HistoryModule | undefined;
                                            history?.redo();
                                        },
                                    },
                                },
                            },
                            readOnly: this.disabled,
                            theme: 'snow',
                        });

                        this.editor = editor;

                        if (initialValue.trim() !== '') {
                            editor.clipboard.dangerouslyPasteHTML(renderVisualHtml(initialValue));
                        }

                        this.value = serializeEditorHtml(editor.root);
                        fallbackInput.value = asString(this.value);
                        fallbackInput.removeAttribute('name');
                        fallbackInput.hidden = true;
                        host.hidden = false;
                        toolbar.hidden = false;
                        this.editorReady = true;

                        editor.on('text-change', () => {
                            this.value = serializeEditorHtml(editor.root);
                            fallbackInput.value = asString(this.value);

                            if (
                                window.Nebula?.clearFieldError &&
                                editor.container.classList.contains('nebula-field-error')
                            ) {
                                window.Nebula.clearFieldError(editor.container);
                            }
                        });

                        editor.on('selection-change', (range: { index: number; length: number } | null) => {
                            if (!range) {
                                return;
                            }

                            this.lastRange = {
                                index: range.index,
                                length: range.length,
                            };
                        });

                        editor.root.addEventListener('click', (event) => {
                            const target = event.target;
                            if (!(target instanceof HTMLImageElement) || !window.NebulaMedia?.open) {
                                return;
                            }

                            event.preventDefault();
                            event.stopPropagation();

                            const imageRange = getImageRange(editor, target);
                            if (!imageRange) {
                                return;
                            }

                            component.editingImageRange = imageRange;
                            component.lastRange = imageRange;

                            void window.NebulaMedia.open({
                                resetSelection: false,
                                selection: getImageSelection(target),
                            }).then((imageSelection) => {
                                if (!imageSelection) {
                                    component.editingImageRange = null;
                                    focusEditorRoot(editor, component.$nextTick);
                                    return;
                                }

                                const range = restoreRange(editor, imageRange) ?? imageRange;
                                editor.deleteText(range.index, range.length, 'user');
                                editor.clipboard.dangerouslyPasteHTML(range.index, buildImageHtml(imageSelection), 'user');
                                editor.setSelection(range.index + 1, 0, 'silent');
                                component.lastRange = { index: range.index + 1, length: 0 };
                                component.editingImageRange = null;
                                focusEditorRoot(editor, component.$nextTick);
                            });
                        });

                        editor.root.addEventListener('dblclick', (event) => {
                            const target = event.target;
                            const placeholder = target instanceof HTMLElement
                                ? target.closest<HTMLElement>('.nebula-quill-directive')
                                : null;

                            if (!placeholder) {
                                return;
                            }

                            const directive = placeholder.dataset.directive ?? '';
                            const range = getDirectiveRange(editor, placeholder);

                            if (!range) {
                                return;
                            }

                            const openOptions: NebulaDirectiveOpenOptions = { directive };

                            if (placeholder.dataset.kind === 'widget' && window.NebulaDirective?.openWidget) {
                                event.preventDefault();
                                event.stopPropagation();
                                component.lastRange = range;

                                void window.NebulaDirective.openWidget(openOptions).then((updatedDirective) => {
                                    if (!updatedDirective) {
                                        focusEditorRoot(editor, component.$nextTick);
                                        return;
                                    }

                                    const directiveRange = restoreRange(editor, range) ?? range;
                                    editor.deleteText(directiveRange.index, directiveRange.length, 'user');
                                    editor.insertEmbed(
                                        directiveRange.index,
                                        'nebula-directive',
                                        describeDirective(updatedDirective),
                                        'user',
                                    );
                                    editor.setSelection(directiveRange.index + 1, 0, 'silent');
                                    component.lastRange = { index: directiveRange.index + 1, length: 0 };
                                    focusEditorRoot(editor, component.$nextTick);
                                });

                                return;
                            }

                            if (placeholder.dataset.kind === 'variable' && window.NebulaDirective?.openVariable) {
                                event.preventDefault();
                                event.stopPropagation();
                                component.lastRange = range;

                                void window.NebulaDirective.openVariable(openOptions).then((updatedDirective) => {
                                    if (!updatedDirective) {
                                        focusEditorRoot(editor, component.$nextTick);
                                        return;
                                    }

                                    const directiveRange = restoreRange(editor, range) ?? range;
                                    editor.deleteText(directiveRange.index, directiveRange.length, 'user');
                                    editor.insertEmbed(
                                        directiveRange.index,
                                        'nebula-directive',
                                        describeDirective(updatedDirective),
                                        'user',
                                    );
                                    editor.setSelection(directiveRange.index + 1, 0, 'silent');
                                    component.lastRange = { index: directiveRange.index + 1, length: 0 };
                                    focusEditorRoot(editor, component.$nextTick);
                                });
                            }
                        });
                    });
                },

                handleSourceInput(this: QuillState): void {
                    const fallbackInput = this.$refs?.fallbackInput;

                    if (!fallbackInput) {
                        return;
                    }

                    this.value = fallbackInput.value;
                },

                toggleSourceMode(this: QuillState): void {
                    const fallbackInput = this.$refs?.fallbackInput;
                    const host = this.$refs?.editorHost;

                    if (!fallbackInput || !host) {
                        return;
                    }

                    if (this.sourceMode) {
                        if (this.editor) {
                            this.editor.setContents([]);

                            if (fallbackInput.value.trim() !== '') {
                                this.editor.clipboard.dangerouslyPasteHTML(renderVisualHtml(fallbackInput.value));
                            }

                            this.value = serializeEditorHtml(this.editor.root);
                            fallbackInput.value = asString(this.value);
                        }

                        this.sourceMode = false;
                        fallbackInput.hidden = true;
                        host.hidden = false;
                        if (this.toolbarElement) {
                            this.toolbarElement.hidden = false;
                        }
                        focusEditorRoot(this.editor, this.$nextTick);
                        return;
                    }

                    if (this.editor) {
                        this.value = serializeEditorHtml(this.editor.root);
                        fallbackInput.value = asString(this.value);
                    }

                    this.sourceMode = true;
                    fallbackInput.hidden = false;
                    host.hidden = true;
                    if (this.toolbarElement) {
                        this.toolbarElement.hidden = true;
                    }
                    fallbackInput.focus();
                },

                openSourceVariablePicker(this: QuillState): void {
                    const fallbackInput = this.$refs?.fallbackInput;

                    if (!fallbackInput || !window.NebulaDirective?.openVariable) {
                        return;
                    }

                    void window.NebulaDirective.openVariable().then((directive) => {
                        if (!directive) {
                            return;
                        }

                        insertAtCursor(fallbackInput, directive);
                        this.handleSourceInput();
                    });
                },

                openSourceWidgetPicker(this: QuillState): void {
                    const fallbackInput = this.$refs?.fallbackInput;

                    if (!fallbackInput || !window.NebulaDirective?.openWidget) {
                        return;
                    }

                    void window.NebulaDirective.openWidget().then((directive) => {
                        if (!directive) {
                            return;
                        }

                        insertAtCursor(fallbackInput, directive);
                        this.handleSourceInput();
                    });
                },

                openSourceImagePicker(this: QuillState): void {
                    const fallbackInput = this.$refs?.fallbackInput;

                    if (!fallbackInput || !window.NebulaMedia?.open) {
                        return;
                    }

                    void window.NebulaMedia.open({ resetSelection: true }).then((imageSelection) => {
                        if (!imageSelection) {
                            return;
                        }

                        insertAtCursor(fallbackInput, buildImageHtml(imageSelection));
                        this.handleSourceInput();
                    });
                },

                openLinkDialog(this: QuillState): void {
                    if (!this.editor) {
                        return;
                    }

                    const range = getDialogRange(this.editor, this.lastRange);
                    const formats = this.editor.getFormat(range.index, range.length);

                    this.savedRange = cloneRange(range);
                    this.linkDialogUrl = typeof formats.link === 'string' ? formats.link : '';
                    this.linkDialogText = range.length > 0 ? getSelectedText(this.editor, range) : this.linkDialogUrl;
                    this.linkDialogOpen = true;

                    this.$nextTick?.(() => {
                        this.$refs?.linkUrlInput?.focus();
                        this.$refs?.linkUrlInput?.select();
                    });
                },

                closeLinkDialog(this: QuillState): void {
                    this.linkDialogOpen = false;
                    focusEditorRoot(this.editor, this.$nextTick);
                },

                submitLinkDialog(this: QuillState): void {
                    if (!this.editor) {
                        return;
                    }

                    const url = this.linkDialogUrl.trim();
                    const text = this.linkDialogText.trim();
                    const range = restoreRange(this.editor, this.savedRange) ?? {
                        index: this.editor.getLength(),
                        length: 0,
                    };

                    if (range.length > 0) {
                        if (url === '') {
                            this.editor.formatText(range.index, range.length, 'link', false, 'user');
                        } else {
                            const currentText = getSelectedText(this.editor, range);
                            if (text !== '' && text !== currentText) {
                                this.editor.deleteText(range.index, range.length, 'user');
                                this.editor.insertText(range.index, text, { link: url }, 'user');
                                this.editor.setSelection(range.index + text.length, 0, 'silent');
                                this.lastRange = { index: range.index + text.length, length: 0 };
                            } else {
                                this.editor.formatText(range.index, range.length, 'link', url, 'user');
                                this.lastRange = cloneRange(range);
                            }
                        }
                    } else if (url !== '') {
                        const displayText = text !== '' ? text : url;
                        this.editor.insertText(range.index, displayText, { link: url }, 'user');
                        this.editor.setSelection(range.index + displayText.length, 0, 'silent');
                        this.lastRange = { index: range.index + displayText.length, length: 0 };
                    }

                    this.linkDialogOpen = false;
                    this.savedRange = null;
                    focusEditorRoot(this.editor, this.$nextTick);
                },

                openTableDialog(this: QuillState): void {
                    if (!this.editor) {
                        return;
                    }

                    this.savedRange = getDialogRange(this.editor, this.lastRange);
                    this.tableRows = DEFAULT_TABLE_ROWS;
                    this.tableColumns = DEFAULT_TABLE_COLUMNS;
                    this.tableDialogOpen = true;

                    this.$nextTick?.(() => {
                        this.$refs?.tableRowsInput?.focus();
                        this.$refs?.tableRowsInput?.select();
                    });
                },

                closeTableDialog(this: QuillState): void {
                    this.tableDialogOpen = false;
                    focusEditorRoot(this.editor, this.$nextTick);
                },

                submitTableDialog(this: QuillState): void {
                    if (!this.editor) {
                        return;
                    }

                    const rows = Number(this.tableRows);
                    const columns = Number(this.tableColumns);

                    if (!Number.isInteger(rows) || rows <= 0 || !Number.isInteger(columns) || columns <= 0) {
                        return;
                    }

                    restoreRange(this.editor, this.savedRange);
                    const table = this.editor.getModule('table') as TableModule | undefined;
                    if (table && typeof table.insertTable === 'function') {
                        table.insertTable(rows, columns);
                    }

                    this.lastRange = cloneRange(this.savedRange);
                    this.tableDialogOpen = false;
                    this.savedRange = null;
                    focusEditorRoot(this.editor, this.$nextTick);
                },

                destroy(this: QuillState): void {
                    this.editor = null;
                    if (typeof baseDestroy === 'function') {
                        baseDestroy.call(this);
                    }
                },

                validate(this: QuillState): boolean {
                    if (!window.Nebula?.validateValue) {
                        return true;
                    }

                    const message = window.Nebula.validateValue(this.value, this.validation);
                    const field = this.editor?.container;

                    if (!field || !window.Nebula.clearFieldError || !window.Nebula.setFieldError) {
                        return message === null;
                    }

                    window.Nebula.clearFieldError(field);

                    if (message !== null) {
                        window.Nebula.setFieldError(field, message);
                        return false;
                    }

                    return true;
                },
            });
        });
    };

    if (window.Alpine) {
        install();
    }

    document.addEventListener('alpine:init', install);
}
