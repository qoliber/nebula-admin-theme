<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;
use Qoliber\NebulaComponent\Model\DataProviderResolver;
use Qoliber\NebulaComponent\Model\OptionSourceResolver;
use Qoliber\NebulaForm\Model\Definition\FormDefinitionNormalizer;
use Qoliber\NebulaForm\Model\FieldNamer;
use Qoliber\NebulaForm\Model\Form\FieldsetLayoutBuilder;
use Qoliber\NebulaForm\Model\Form\HiddenInputResolver;
use Qoliber\NebulaForm\Model\Form\LayoutNodeFlattener;
use Qoliber\NebulaForm\Model\Form\SectionRenderer;
use Qoliber\NebulaForm\Model\Field\WysiwygRendererInterface;

/**
 * SimpleForm block — flat DB-table-backed forms (CMS pages/blocks, customer
 * groups, catalog rules, tax rules, admin users, email templates, etc.).
 *
 * The block is a thin orchestrator: it pulls the definition, delegates to
 * services for layout flattening, section rendering and option resolution,
 * and exposes URLs + field metadata to the template.
 */
class Form extends Template
{
    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    /** @var array<string, mixed>|null */
    private ?array $entityData = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Qoliber\NebulaComponent\Api\DefinitionResolverInterface $definitionResolver
     * @param \Qoliber\NebulaComponent\Model\DataProviderResolver $dataProviderResolver
     * @param \Qoliber\NebulaComponent\Model\OptionSourceResolver $optionSourceResolver
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Qoliber\NebulaForm\Model\FieldNamer $fieldNamer
     * @param \Qoliber\NebulaForm\Model\Form\FieldsetLayoutBuilder $fieldsetLayoutBuilder
     * @param \Qoliber\NebulaForm\Model\Form\LayoutNodeFlattener $layoutNodeFlattener
     * @param \Qoliber\NebulaForm\Model\Form\SectionRenderer $sectionRenderer
     * @param \Qoliber\NebulaForm\Model\Definition\FormDefinitionNormalizer $formDefinitionNormalizer
     * @param \Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl $definitionAccessControl
     * @param \Qoliber\NebulaForm\Model\Form\HiddenInputResolver $hiddenInputResolver
     * @param \Qoliber\NebulaForm\Model\Field\WysiwygRendererInterface $wysiwygRenderer
     * @param array<string, string> $fieldTemplates Map of field type => "Module::field/<type>.phtml" template id.
     *        Lets other modules supply a field template for a type NebulaForm
     *        doesn't ship itself (e.g. NebulaPageBuilder contributes `pagebuilder`)
     *        without NebulaForm depending on them. Empty when those modules are off.
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly DataProviderResolver $dataProviderResolver,
        private readonly OptionSourceResolver $optionSourceResolver,
        private readonly FormKey $formKey,
        private readonly FieldNamer $fieldNamer,
        private readonly FieldsetLayoutBuilder $fieldsetLayoutBuilder,
        private readonly LayoutNodeFlattener $layoutNodeFlattener,
        private readonly SectionRenderer $sectionRenderer,
        private readonly FormDefinitionNormalizer $formDefinitionNormalizer,
        private readonly DefinitionAccessControl $definitionAccessControl,
        private readonly HiddenInputResolver $hiddenInputResolver,
        private readonly WysiwygRendererInterface $wysiwygRenderer,
        private readonly array $fieldTemplates = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaForm::form.phtml');
    }

    /**
     * Resolve an externally-contributed field template id for a field type, or
     * '' when none is registered (the caller then falls back to the built-in
     * partial). Lets other modules supply a template for a type NebulaForm does
     * not ship itself (e.g. NebulaPageBuilder contributes `pagebuilder` via
     * di.xml) without NebulaForm depending on them.
     *
     * @param string $type
     * @return string Template id like "Qoliber_NebulaPageBuilder::field/pagebuilder.phtml".
     */
    public function getFieldTemplateOverride(string $type): string
    {
        return $this->fieldTemplates[$type] ?? '';
    }

    /**
     * Return the resolved, normalized form definition (lazily loaded).
     *
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        if ($this->definition === null) {
            // NebulaUiBridge schedules this block with an inline_definition
            // argument. When present, skip the resolver and use it directly
            // (same pattern as NebulaGrid\Block\Grid).
            $inline = $this->getData('inline_definition');
            if (is_array($inline) && !empty($inline)) {
                $this->definition = $this->formDefinitionNormalizer->normalize($inline);
            } else {
                $raw = $this->definitionResolver->resolve('form', $this->getFormId());
                $this->definition = $this->formDefinitionNormalizer->normalize($raw);
            }
        }

        return $this->definition;
    }

    /**
     * Form identifier supplied via the block's `form_id` argument.
     *
     * @return string
     */
    public function getFormId(): string
    {
        return (string) $this->getData('form_id');
    }

    /**
     * Whether the current admin is authorized for this form's ACL resource.
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        return $this->definitionAccessControl->isAllowed($this->getDefinition());
    }

    /**
     * Resolve the edited entity id from the request, or null when creating.
     *
     * @return string|null
     */
    public function getEntityId(): ?string
    {
        $definition = $this->getDefinition();
        $idField = $definition['dataSource']['config']['identifierParam'] ?? 'id';
        $entityId = $this->getRequest()->getParam($idField);

        return $entityId !== null ? (string) $entityId : null;
    }

    /**
     * Whether the form is rendering a new (unsaved) entity.
     *
     * @return bool
     */
    public function isNewEntity(): bool
    {
        return $this->getEntityId() === null;
    }

    /**
     * Expose the field namer so templates can build submit names / DOM ids.
     *
     * @return \Qoliber\NebulaForm\Model\FieldNamer
     */
    public function getFieldNamer(): FieldNamer
    {
        return $this->fieldNamer;
    }

    /**
     * Submit-time field-name prefix declared by the form settings (null if none).
     *
     * @return string|null
     */
    public function getFieldPrefix(): ?string
    {
        $prefix = $this->getDefinition()['settings']['fieldPrefix'] ?? null;

        return $prefix === '' ? null : $prefix;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    /**
     * Return the form's fieldsets, ordered for rendering.
     *
     * @return array<string, mixed>
     */
    public function getFieldsets(): array
    {
        $fieldsets = $this->getDefinition()['fieldsets'] ?? [];

        // Carry the assoc key forward as `name` so downstream consumers can
        // identify each fieldset after FieldsetLayoutBuilder::buildFromFieldsets
        // calls array_values() and drops the keys.
        foreach ($fieldsets as $key => &$fieldset) {
            if (!isset($fieldset['name'])) {
                $fieldset['name'] = (string) $key;
            }
        }
        unset($fieldset);

        uasort($fieldsets, static function (array $a, array $b): int {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        return $fieldsets;
    }

    /**
     * Return the form's layout-tree nodes (empty for fieldset-based forms).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLayoutTree(): array
    {
        $layout = $this->getDefinition()['layout'] ?? [];

        return is_array($layout) ? array_values($layout) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Return the flattened, renderable layout nodes for the form body.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRenderableNodes(): array
    {
        $layout = $this->getLayoutTree();

        if ($layout !== []) {
            return $this->layoutNodeFlattener->flatten($layout, $this->getAllFields());
        }

        return $this->fieldsetLayoutBuilder->buildFromFieldsets(
            $this->getFieldsets(),
            (string) ($this->getDefinition()['settings']['layout'] ?? 'single')
        );
    }

    /**
     * Return a fieldset's fields ordered by their `position`.
     *
     * @param array<string, mixed> $fieldset
     * @return array<string, array<string, mixed>>
     */
    public function getSortedFields(array $fieldset): array
    {
        $fields = $fieldset['fields'] ?? [];

        uasort($fields, static function (array $a, array $b): int {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        return $fields;
    }

    /**
     * Return the entity data the form is populated from (lazily resolved).
     *
     * @return array<string, mixed>
     */
    /**
     * Return the entity data the form is populated from (lazily resolved).
     *
     * @return array<string, mixed>
     */
    public function getEntityData(): array
    {
        if ($this->entityData !== null) {
            return $this->entityData;
        }

        $entityId = $this->getEntityId();
        if ($entityId === null) {
            return $this->entityData = $this->getDefaults();
        }

        $definition = $this->getDefinition();
        $providerAlias = (string) ($definition['dataSource']['provider'] ?? '');

        if ($providerAlias === '') {
            return $this->entityData = [];
        }

        return $this->entityData = $this->dataProviderResolver->fetch(
            $providerAlias,
            $definition['dataSource']['config'] ?? [],
            ['entityId' => $entityId]
        );
    }

    /**
     * Resolve a single field's current value from the entity data.
     *
     * @param string $fieldKey
     * @return mixed
     */
    public function getFieldValue(string $fieldKey): mixed
    {
        $data = $this->getEntityData();

        // Fast path: flat key exists in data (e.g. "name" => "value")
        if (array_key_exists($fieldKey, $data)) {
            return $data[$fieldKey];
        }

        // Bracket path: "store[store_id]" must walk $data['store']['store_id'].
        // Providers commonly return nested arrays matching the HTML form
        // prefix (e.g. Store/Customer/Group providers returning
        // ['store' => [...]]) while the form JSON declares flat bracketed
        // names. Traverse the path so both shapes resolve.
        if (str_contains($fieldKey, '[')) {
            $path = preg_split('/[\[\]]+/', rtrim($fieldKey, ']'));
            if ($path === false || $path === []) {
                return null;
            }
            $value = $data;
            foreach ($path as $segment) {
                if ($segment === '') {
                    continue;
                }
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            return $value;
        }

        return null;
    }

    /**
     * Admin route the form POSTs to on save.
     *
     * @return string
     */
    /**
     * Admin route the form POSTs to on save (with the identifier param injected).
     *
     * @return string
     */
    public function getSaveUrl(): string
    {
        $definition = $this->getDefinition();
        $saveUrl = (string) ($definition['settings']['saveUrl'] ?? '*/*/save');

        // Inject the entity's identifierParam (id / block_id / page_id / …)
        // into the save URL. Without it Magento's native save controllers
        // (cms/block/save, customer/group/save, …) treat every POST as a
        // CREATE and silently spawn duplicate records on edit. Same fix the
        // EAV form path already applies — keep both in sync.
        $params = $this->hiddenInputResolver->resolveSaveUrlParams(
            $definition,
            $this->getRequest(),
            $this->getEntityData()
        );

        return $this->getUrl($saveUrl, $params);
    }

    /**
     * Admin route the Back button navigates to.
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl($this->getDefinition()['settings']['backUrl'] ?? '*/*/');
    }

    /**
     * Admin route the Delete button posts to (empty when delete is disabled).
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        $deleteUrl = (string) ($this->getDefinition()['settings']['deleteUrl'] ?? '');
        if ($deleteUrl === '') {
            return '';
        }

        // Use the JSON-declared identifierParam (e.g. page_id, customer_id) so
        // the vendor delete controller's `getParam(<name>)` can read it.
        // Defaults to 'id' for backwards compatibility.
        $identifierParam = (string) (
            $this->getDefinition()['dataSource']['config']['identifierParam']
            ?? 'id'
        );

        return $this->getUrl($deleteUrl, [$identifierParam => $this->getEntityId()]);
    }

    /**
     * Request param name carrying the identifier for the delete action.
     *
     * @return string
     */
    public function getDeleteIdentifierParam(): string
    {
        return (string) (
            $this->getDefinition()['dataSource']['config']['identifierParam']
            ?? 'id'
        );
    }

    /**
     * Current admin form key for CSRF-protected form submission.
     *
     * @return string
     */
    /**
     * Current admin form key for CSRF-protected form submission.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Translate a string through Magento's i18n layer.
     *
     * @param string $text
     * @return string
     */
    public function translate(string $text): string
    {
        return (string) __($text);
    }

    /**
     * Resolve field options — either from inline `options` or a registered
     * `optionsSource` alias.
     *
     * @param array<string, mixed> $field
     * @return array<int, array{value: string|null, label: string, depth?: int, group?: bool}|string>
     */
    public function getFieldOptions(array $field): array
    {
        if (!empty($field['options']) && is_array($field['options'])) {
            return $field['options'];
        }

        if (!empty($field['optionsSource'])) {
            return $this->optionSourceResolver->toOptionArray(
                (string) $field['optionsSource'],
                (string) ($field['optionsMethod'] ?? 'toOptionArray')
            );
        }

        return [];
    }

    /**
     * Build the hidden inputs (declared hiddenFields + entity identifier).
     *
     * @return array<string, string>
     */
    public function getHiddenRouteFields(): array
    {
        $hidden = $this->getDefinition()['hiddenFields'] ?? [];
        $result = [];

        foreach ($hidden as $name => $value) {
            $result[(string) $name] = (string) $value;
        }

        // Native Magento save controllers (Cms\Page\Save, Cms\Block\Save,
        // CatalogRule\Promo\Catalog\Save, …) read the entity id via
        // getParam(), then call $model->setData(getPostValue()). If the
        // identifier isn't in the POST body, setData() clobbers it back to
        // null and the repository INSERTs a new row. Inject the identifier
        // as a hidden POST input on every edit so the body carries it too.
        //
        // Two distinct names exist in the JSON:
        //   - settings.identifierField  → the POST-body field the controller
        //                                 reads (e.g. role_id, page_id,
        //                                 "user[user_id]"). Required for the
        //                                 hidden input here.
        //   - dataSource.config.identifierParam → the URL query param (e.g.
        //                                 rid for /editrole/rid/N). Used for
        //                                 the save/delete URL construction.
        // Prefer identifierField; fall back to identifierParam when a form
        // doesn't distinguish them (most forms share the same name for both).
        $entityId = $this->getEntityId();
        if ($entityId !== null && $entityId !== '') {
            $definition  = $this->getDefinition();
            $fieldName = (string) (
                $definition['settings']['identifierField']
                ?? $definition['dataSource']['config']['identifierParam']
                ?? $definition['settings']['identifierParam']
                ?? 'id'
            );
            if (!isset($result[$fieldName])) {
                $result[$fieldName] = (string) $entityId;
            }
        }

        return $result;
    }

    /**
     * Render a layout section/snippet body, or '' when it has no renderer.
     *
     * @param array<string, mixed> $section
     * @return string
     */
    public function renderSection(array $section): string
    {
        if (!$this->hasRenderableSection($section)) {
            return '';
        }

        return $this->sectionRenderer->render($this->getLayout(), $section, [
            'entity_data' => $this->getEntityData(),
            'form_block' => $this,
        ]);
    }

    /**
     * Render the WYSIWYG control markup for a wysiwyg field.
     *
     * @param string $fieldKey
     * @param array<string, mixed> $field
     * @param string $fieldName
     * @param string $fieldId
     * @param mixed $fieldValue
     * @param bool $isRequired
     * @param bool $isDisabled
     * @return string
     */
    public function renderWysiwygFieldControl(
        string $fieldKey,
        array $field,
        string $fieldName,
        string $fieldId,
        mixed $fieldValue,
        bool $isRequired,
        bool $isDisabled
    ): string {
        $validation = $field['validation'] ?? [];

        if (!is_array($validation)) {
            $validation = [];
        }

        $validation['label'] = $this->translate((string) ($field['label'] ?? $fieldKey));

        $config = $this->escapeHtmlAttr(
            (string) json_encode([
                'fieldName' => $fieldName,
                'value' => (string) ($fieldValue ?? ''),
                'disabled' => $isDisabled,
                'required' => $isRequired,
                'rows' => (int) ($field['rows'] ?? 5),
                'validation' => $validation,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $rules = [];
        if ($isRequired) {
            $rules[] = 'required';
        }

        foreach ($validation as $rule => $value) {
            if ($rule === 'label') {
                continue;
            }

            if ($value === true) {
                $rules[] = (string) $rule;
            } elseif ($value !== false && $value !== null) {
                $rules[] = (string) $rule . ':' . $value;
            }
        }

        $validateAttr = $rules !== []
            ? ' data-validate="' . $this->escapeHtmlAttr(implode('|', $rules)) . '"'
                . ' data-validate-label="' . $this->escapeHtmlAttr((string) $validation['label']) . '"'
            : '';

        $cssClass = 'block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm text-gray-900 shadow-sm'
            . ' placeholder:text-gray-400 focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20'
            . ' focus:outline-none disabled:bg-gray-50 disabled:text-gray-500';

        return $this->wysiwygRenderer->render(
            $fieldId,
            $fieldName,
            (string) ($fieldValue ?? ''),
            $config,
            $validateAttr,
            $cssClass
        );
    }

    /**
     * Whether a layout section declares a resolvable snippet/template renderer.
     *
     * @param array<string, mixed> $section
     * @return bool
     */
    private function hasRenderableSection(array $section): bool
    {
        $renderers = $section['renderers'] ?? [];

        if (empty($renderers) && !empty($section['renderer'])) {
            $renderers = [(string) $section['renderer']];
        }

        foreach ($renderers as $renderer) {
            $renderer = (string) $renderer;

            if ($renderer !== '' && (str_starts_with($renderer, 'snippet.') || str_contains($renderer, '::'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Propagate the owning module name onto a child block from its template id.
     *
     * @param \Magento\Framework\View\Element\Template $block
     * @param string $template
     * @return void
     */
    public function setModuleOnBlock(Template $block, string $template): void
    {
        if (str_contains($template, '::')) {
            $block->setData('module_name', explode('::', $template, 2)[0]);
        }
    }

    /**
     * Build the default field values map for a new (unsaved) entity.
     *
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        $defaults = [];

        foreach ($this->getAllFields() as $key => $field) {
            if (isset($field['default'])) {
                $defaults[$key] = $field['default'];
            }
        }

        return $defaults;
    }

    /**
     * Flatten every fieldset's fields into a single name => field map.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getAllFields(): array
    {
        $allFields = [];

        foreach ($this->getFieldsets() as $fieldset) {
            foreach ($fieldset['fields'] ?? [] as $key => $field) {
                $allFields[$key] = $field;
            }
        }

        return $allFields;
    }
}
