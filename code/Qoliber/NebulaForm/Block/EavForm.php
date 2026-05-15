<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Block;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\DataProviderResolver;
use Qoliber\NebulaForm\Model\Eav\AttributeGroupLoader;
use Qoliber\NebulaForm\Model\Eav\AttributeGroupRenderer;
use Qoliber\NebulaForm\Model\Eav\LayoutAttributeCollector;
use Qoliber\NebulaForm\Model\Field\EavFieldRenderer;
use Qoliber\NebulaForm\Model\Field\EavFieldResolver;
use Qoliber\NebulaForm\Model\Field\EavFieldTypeMapper;
use Qoliber\NebulaForm\Model\FieldNamer;
use Qoliber\NebulaForm\Model\Form\HiddenInputResolver;
use Qoliber\NebulaForm\Model\Form\LayoutTreeRenderer;
use Qoliber\NebulaForm\Model\Form\SectionRenderer;

/**
 * EAV form block. Delegates attribute loading, applicability, group rendering
 * and section markup to injected services — keeping the block as a thin
 * orchestrator over the form definition.
 */
class EavForm extends Template
{
    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    /** @var array<string, mixed>|null */
    private ?array $entityData = null;

    /** @var \Magento\Framework\DataObject|null */
    private ?DataObject $entity = null;

    private bool $entityLoaded = false;

    public function __construct(
        Context $context,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly SnippetResolverInterface $snippetResolver,
        private readonly DataProviderResolver $dataProviderResolver,
        private readonly FormKey $formKey,
        private readonly FieldNamer $fieldNamer,
        private readonly EavFieldRenderer $fieldRenderer,
        private readonly LayoutTreeRenderer $layoutTreeRenderer,
        private readonly AttributeGroupLoader $attributeGroupLoader,
        private readonly AttributeGroupRenderer $attributeGroupRenderer,
        private readonly LayoutAttributeCollector $layoutAttributeCollector,
        private readonly EavFieldResolver $fieldResolver,
        private readonly EavFieldTypeMapper $fieldTypeMapper,
        private readonly HiddenInputResolver $hiddenInputResolver,
        private readonly SectionRenderer $sectionRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaForm::eav/form.phtml');
    }

    /**
     * Resolve a `snippet.<id>` renderer reference (as declared in the form
     * definition's `settings.sidebarRenderer` / `settings.drawerRenderer`)
     * to the snippet manifest entry. Returns null when the reference is
     * empty, malformed, or unknown — callers degrade to "no sidebar" rather
     * than crashing the form render.
     *
     * @return array<string, mixed>|null
     */
    public function resolveSnippet(string $rendererRef): ?array
    {
        if ($rendererRef === '' || !str_starts_with($rendererRef, 'snippet.')) {
            return null;
        }

        try {
            return $this->snippetResolver->resolve(substr($rendererRef, 8));
        } catch (\Throwable) {
            return null;
        }
    }

    public function getFieldNamer(): FieldNamer
    {
        return $this->fieldNamer;
    }

    public function getFieldRenderer(): EavFieldRenderer
    {
        return $this->fieldRenderer;
    }

    public function getLayoutTreeRenderer(): LayoutTreeRenderer
    {
        return $this->layoutTreeRenderer;
    }

    public function getFieldPrefix(): ?string
    {
        $prefix = $this->getDefinition()['settings']['fieldPrefix'] ?? null;

        return $prefix === '' ? null : $prefix;
    }

    protected function _beforeToHtml(): self
    {
        if (!$this->getData('_sidebar_rendered')) {
            $def = $this->getDefinition();
            if (!empty($def['settings']['template'])) {
                $this->setTemplate($def['settings']['template']);
            }
        }

        return parent::_beforeToHtml();
    }

    public function getDefinition(): array
    {
        if ($this->definition === null) {
            $this->definition = $this->definitionResolver->resolve('form', $this->getFormId());
        }

        return $this->definition;
    }

    public function getFormId(): string
    {
        return (string) $this->getData('form_id');
    }

    public function getEntityId(): ?string
    {
        $definition = $this->getDefinition();
        $idParam = $definition['dataSource']['config']['identifierParam']
            ?? $definition['settings']['identifierParam']
            ?? 'id';
        $entityId = $this->getRequest()->getParam($idParam);

        return $entityId !== null ? (string) $entityId : null;
    }

    public function getEntity(): ?DataObject
    {
        if (!$this->entityLoaded) {
            $this->loadEntityData();
        }

        return $this->entity;
    }

    public function getEntityData(): array
    {
        if ($this->entityData === null) {
            $this->loadEntityData();
        }

        return $this->entityData ?? [];
    }

    public function getEntityTypeCode(): string
    {
        return $this->getDefinition()['entity'] ?? '';
    }

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\Group[]
     */
    public function getAttributeGroups(): array
    {
        $attributeSetId = isset($this->getEntityData()['attribute_set_id'])
            ? (int) $this->getEntityData()['attribute_set_id']
            : null;

        return $this->attributeGroupLoader->getAttributeGroups(
            $this->getEntityTypeCode(),
            $attributeSetId
        );
    }

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]
     */
    public function getGroupAttributes(string $groupCode): array
    {
        $attributeSetId = isset($this->getEntityData()['attribute_set_id'])
            ? (int) $this->getEntityData()['attribute_set_id']
            : null;

        return $this->attributeGroupLoader->getGroupAttributes(
            $this->getEntityTypeCode(),
            $groupCode,
            $attributeSetId
        );
    }

    public function getAttribute(string $attributeCode): ?AbstractAttribute
    {
        return $this->attributeGroupLoader->getAttribute(
            $this->getEntityTypeCode(),
            $attributeCode
        );
    }

    public function getFieldType(AbstractAttribute $attribute): string
    {
        return $this->fieldTypeMapper->map(
            $attribute,
            $this->getFieldOverrides($attribute->getAttributeCode())
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getAttributeOptions(AbstractAttribute $attribute): array
    {
        return $this->fieldTypeMapper->getOptions($attribute);
    }

    public function getFieldValue(string $attributeCode): mixed
    {
        return $this->getEntityData()[$attributeCode] ?? null;
    }

    public function getFieldOverrides(string $attributeCode): array
    {
        return $this->fieldResolver->getFieldOverrides($this->getDefinition(), $attributeCode);
    }

    public function hasCustomRenderer(string $attributeCode): bool
    {
        return !empty($this->getFieldOverrides($attributeCode)['renderer']);
    }

    public function renderCustomField(string $attributeCode): string
    {
        $overrides = $this->getFieldOverrides($attributeCode);
        $renderer = (string) ($overrides['renderer'] ?? '');

        return $this->sectionRenderer->render(
            $this->getLayout(),
            ['renderer' => $renderer],
            [
                'attribute_code' => $attributeCode,
                'entity' => $this->getEntity(),
                'entity_data' => $this->getEntityData(),
                'config' => $overrides,
                'form_block' => $this,
            ]
        );
    }

    public function getSaveUrl(): string
    {
        $definition = $this->getDefinition();
        $saveUrl = (string) ($definition['settings']['saveUrl'] ?? '*/*/save');

        $params = $this->hiddenInputResolver->resolveSaveUrlParams(
            $definition,
            $this->getRequest(),
            $this->getEntityData()
        );

        return $this->getUrl($saveUrl, $params);
    }

    /**
     * @return array<string, string|string[]>
     */
    public function getHiddenInputs(): array
    {
        return $this->hiddenInputResolver->resolve(
            $this->getDefinition(),
            $this->getRequest(),
            $this->getEntityData()
        );
    }

    public function getBackUrl(): string
    {
        return $this->getUrl($this->getDefinition()['settings']['backUrl'] ?? '*/*/');
    }

    public function getDeleteUrl(): string
    {
        $deleteUrl = (string) ($this->getDefinition()['settings']['deleteUrl'] ?? '');
        if ($deleteUrl === '') {
            return '';
        }

        $identifierParam = $this->getDeleteIdentifierParam();

        return $this->getUrl($deleteUrl, [$identifierParam => $this->getEntityId()]);
    }

    public function getDeleteIdentifierParam(): string
    {
        $definition = $this->getDefinition();

        return (string) (
            $definition['dataSource']['config']['identifierParam']
            ?? $definition['settings']['identifierParam']
            ?? 'id'
        );
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function translate(string $text): string
    {
        return (string) __($text);
    }

    public function getLayoutTree(): array
    {
        return $this->getDefinition()['layout'] ?? [];
    }

    public function isNewEntity(): bool
    {
        return $this->getEntityId() === null;
    }

    public function renderSection(array $section): string
    {
        if (!$this->sectionHasRenderer($section)) {
            return '';
        }

        return $this->sectionRenderer->render($this->getLayout(), $section, [
            'entity' => $this->getEntity(),
            'entity_data' => $this->getEntityData(),
            'form_block' => $this,
        ]);
    }

    /**
     * @param array<string, mixed> $section
     */
    private function sectionHasRenderer(array $section): bool
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
     * @return array<int, string>
     */
    public function getExplicitFieldCodes(): array
    {
        return $this->layoutAttributeCollector->collectFieldCodes($this->getLayoutTree());
    }

    /**
     * @return array<int, string>
     */
    public function getExplicitGroupCodes(): array
    {
        return $this->layoutAttributeCollector->collectGroupCodes($this->getLayoutTree());
    }

    /**
     * @return array<string, array{label: string, attributes: \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]}>
     */
    public function getRemainingAttributeGroups(): array
    {
        return $this->attributeGroupRenderer->getRemainingGroups(
            $this->getEntityTypeCode(),
            $this->getDefinition(),
            $this->getEntityData(),
            $this->getExplicitFieldCodes(),
            $this->getExplicitGroupCodes()
        );
    }

    public function isCompositeProduct(): bool
    {
        $typeCode = (string) ($this->getFieldValue('type_id') ?? 'simple');

        return $this->fieldResolver->isComposite($typeCode);
    }

    public function isQtyProduct(): bool
    {
        $typeCode = (string) ($this->getFieldValue('type_id') ?? 'simple');

        return $this->fieldResolver->tracksQty($typeCode);
    }

    public function isFieldApplicable(string $attributeCode): bool
    {
        $attribute = $this->getAttribute($attributeCode);

        if ($attribute === null) {
            return true;
        }

        return $this->fieldResolver->isApplicable($attribute, $this->getEntityData());
    }

    public function setModuleOnBlock(Template $block, string $template): void
    {
        if (str_contains($template, '::')) {
            $block->setData('module_name', explode('::', $template, 2)[0]);
        }
    }

    private function loadEntityData(): void
    {
        $this->entityLoaded = true;
        $entityId = $this->getEntityId();
        $definition = $this->getDefinition();
        $providerAlias = (string) ($definition['dataSource']['provider'] ?? '');

        if ($entityId === null) {
            // For new entities, merge JSON defaults with any provider-supplied
            // defaults (e.g. request-param driven type_id / attribute_set_id).
            $providerDefaults = [];
            if ($providerAlias !== '') {
                $providerDefaults = $this->dataProviderResolver->fetch(
                    $providerAlias,
                    $definition['dataSource']['config'] ?? [],
                    ['entityId' => null]
                );
            }
            $this->entityData = array_merge($this->getDefaults(), $providerDefaults);
            return;
        }

        if ($providerAlias === '') {
            $this->entityData = [];
            return;
        }

        $this->entityData = $this->dataProviderResolver->fetch(
            $providerAlias,
            $definition['dataSource']['config'] ?? [],
            ['entityId' => $entityId]
        );

        if (isset($this->entityData['_entity']) && $this->entityData['_entity'] instanceof DataObject) {
            $this->entity = $this->entityData['_entity'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return $this->getDefinition()['defaults'] ?? [];
    }
}
