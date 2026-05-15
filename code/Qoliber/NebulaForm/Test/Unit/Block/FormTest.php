<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as UnitObjectManagerHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;
use Qoliber\NebulaComponent\Model\DataProviderResolver;
use Qoliber\NebulaComponent\Model\OptionSourceResolver;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaComponent\Model\SnippetViewModelRegistry;
use Qoliber\NebulaForm\Block\Form;
use Qoliber\NebulaForm\Model\Definition\FormDefinitionNormalizer;
use Qoliber\NebulaForm\Model\FieldNamer;
use Qoliber\NebulaForm\Model\Form\FieldsetLayoutBuilder;
use Qoliber\NebulaForm\Model\Form\LayoutNodeFlattener;
use Qoliber\NebulaForm\Model\Form\SectionRenderer;

class FormTest extends TestCase
{
    /** @var \Qoliber\NebulaComponent\Api\DefinitionResolverInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $definitionResolver;

    /** @var \Qoliber\NebulaComponent\Api\SnippetResolverInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $snippetResolver;

    /** @var \Qoliber\NebulaComponent\Model\DataProviderResolver&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $dataProviderResolver;

    /** @var \Qoliber\NebulaComponent\Model\OptionSourceResolver&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $optionSourceResolver;

    /** @var \Magento\Framework\Data\Form\FormKey&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $formKey;

    /** @var \Magento\Framework\App\RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $request;

    /** @var \Magento\Framework\UrlInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $urlBuilder;

    /** @var \Magento\Framework\View\Element\Template\Context&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $context;

    /** @var \Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $definitionAccessControl;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(DefinitionResolverInterface::class);
        $this->snippetResolver = $this->createMock(SnippetResolverInterface::class);
        $this->dataProviderResolver = $this->createMock(DataProviderResolver::class);
        $this->optionSourceResolver = $this->createMock(OptionSourceResolver::class);
        $this->formKey = $this->createMock(FormKey::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);
        $escaper->method('escapeUrl')->willReturnArgument(0);

        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getUrlBuilder')->willReturn($this->urlBuilder);
        $this->context->method('getEscaper')->willReturn($escaper);

        $this->definitionAccessControl = $this->createMock(DefinitionAccessControl::class);
        $this->definitionAccessControl->method('isAllowed')->willReturn(true);
    }

    // =========================================================================
    // customer_group_edit fixture (classic fieldsets, two-column)
    // =========================================================================

    public function testGetFormIdReturnsConfiguredId(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('customer_group_edit', $block->getFormId());
    }

    public function testGetFieldsetsSortsByPosition(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $fieldsets = $block->getFieldsets();

        $this->assertSame(
            ['general', 'meta', 'notes'],
            array_keys($fieldsets),
            'Fieldsets must be ordered by position'
        );
        $this->assertCount(3, $fieldsets);
    }

    public function testGetSortedFieldsOrdersByPosition(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $fieldsets = $block->getFieldsets();
        $fields = $block->getSortedFields($fieldsets['general']);

        $this->assertSame(
            ['customer_group_code', 'tax_class_id'],
            array_keys($fields)
        );
    }

    public function testGetLayoutTreeIsEmptyWhenFixtureHasNoLayoutKey(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame([], $block->getLayoutTree());
    }

    public function testGetRenderableNodesFromFieldsetsProducesTwoColumnMarkers(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $nodes = $block->getRenderableNodes();

        // Expect: row_start, general, col_break, meta, row_end, then notes (full)
        $markers = array_values(array_filter(array_map(
            static fn (array $n): ?string => $n['__marker'] ?? null,
            $nodes
        )));

        $this->assertSame(['row_start', 'col_break', 'row_end'], $markers);
        $this->assertCount(6, $nodes);
    }

    public function testIsNewEntityTrueWhenNoIdParam(): void
    {
        $this->request->method('getParam')->willReturn(null);
        $block = $this->buildBlock('customer_group_edit');

        $this->assertTrue($block->isNewEntity());
        $this->assertNull($block->getEntityId());
    }

    public function testIsNewEntityFalseWhenIdParamPresent(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'id' ? 42 : $default
        );
        $block = $this->buildBlock('customer_group_edit');

        $this->assertFalse($block->isNewEntity());
        $this->assertSame('42', $block->getEntityId());
    }

    public function testGetEntityDataFallsBackToDefaultsWhenCreating(): void
    {
        $this->request->method('getParam')->willReturn(null);
        $block = $this->buildBlock('customer_group_edit');

        // Default for customer_group_code is "" per fixture
        $this->assertSame(['customer_group_code' => ''], $block->getEntityData());
        $this->assertSame('', $block->getFieldValue('customer_group_code'));
        $this->assertNull($block->getFieldValue('unknown'));
    }

    public function testGetSaveUrlRespectsDefinitionSetting(): void
    {
        $this->urlBuilder->method('getUrl')
            ->with('customer/group/save', $this->anything())
            ->willReturn('/admin/customer/group/save');

        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('/admin/customer/group/save', $block->getSaveUrl());
    }

    public function testGetBackUrlRespectsDefinitionSetting(): void
    {
        $this->urlBuilder->method('getUrl')
            ->with('customer/group/index', $this->anything())
            ->willReturn('/admin/customer/group/');

        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('/admin/customer/group/', $block->getBackUrl());
    }

    public function testGetDeleteUrlEmptyWhenNotConfiguredInFixture(): void
    {
        $this->request->method('getParam')->willReturn(null);
        $block = $this->buildBlockWithDefinition('customer_group_edit', [
            'id' => 'x',
            'settings' => [],
            'fieldsets' => [],
        ]);

        $this->assertSame('', $block->getDeleteUrl());
    }

    public function testGetFormKeyDelegates(): void
    {
        $this->formKey->expects($this->once())->method('getFormKey')->willReturn('FK_XYZ');

        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('FK_XYZ', $block->getFormKey());
    }

    // =========================================================================
    // product_edit fixture (layout tree path)
    // =========================================================================

    public function testLayoutTreeIsFlattenedIntoMarkersAndSections(): void
    {
        $block = $this->buildBlock('product_edit');

        $nodes = $block->getRenderableNodes();

        // Layout is row > two columns > two sections
        // Expected order: row_start(cols=2), main_section, col_break, sidebar_section, row_end
        $markers = [];
        $sections = [];
        foreach ($nodes as $node) {
            if (isset($node['__marker'])) {
                $markers[] = $node['__marker'];
            } elseif (isset($node['id'])) {
                $sections[] = $node['id'];
            }
        }

        $this->assertSame(['row_start', 'col_break', 'row_end'], $markers);
        $this->assertSame(['main', 'sidebar'], $sections);
    }

    public function testLayoutSectionResolvesFieldReferences(): void
    {
        $block = $this->buildBlock('product_edit');

        $nodes = $block->getRenderableNodes();
        $mainSection = $nodes[1] ?? null;

        $this->assertIsArray($mainSection);
        $this->assertSame('main', $mainSection['id']);
        $this->assertArrayHasKey('fields', $mainSection);
        $this->assertSame(['sku', 'name'], array_keys($mainSection['fields']));
    }

    public function testLayoutSectionCollectsSnippetRenderers(): void
    {
        $block = $this->buildBlock('product_edit');

        $nodes = $block->getRenderableNodes();
        $sidebar = $nodes[3] ?? null;

        $this->assertIsArray($sidebar);
        $this->assertSame('sidebar', $sidebar['id']);
        $this->assertContains('snippet.status_badge', $sidebar['renderers']);
    }

    public function testGetFieldOptionsReturnsInlineArrayWhenProvided(): void
    {
        $block = $this->buildBlock('product_edit');

        $options = $block->getFieldOptions([
            'options' => [
                ['value' => '1', 'label' => 'Yes'],
                ['value' => '0', 'label' => 'No'],
            ],
        ]);

        $this->assertCount(2, $options);
        $this->assertSame('Yes', $options[0]['label']);
    }

    public function testGetFieldOptionsReturnsEmptyWhenNoSource(): void
    {
        $block = $this->buildBlock('product_edit');

        $this->assertSame([], $block->getFieldOptions(['type' => 'text']));
    }

    // =========================================================================
    // Section rendering
    // =========================================================================

    public function testRenderSectionReturnsEmptyStringForNoRenderers(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('', $block->renderSection([]));
    }

    public function testRenderSectionReturnsEmptyWhenUnknownRenderer(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $this->assertSame('', $block->renderSection(['renderers' => ['not-a-snippet-or-template']]));
    }

    public function testTranslatePassthroughReturnsString(): void
    {
        $block = $this->buildBlock('customer_group_edit');

        $this->assertIsString($block->translate('Hello'));
    }

    public function testIsAllowedDelegatesToDefinitionAccessControl(): void
    {
        $denyingControl = $this->createMock(DefinitionAccessControl::class);
        $denyingControl->method('isAllowed')->willReturn(false);

        $block = $this->buildBlockWithDefinitionAndAccessControl('customer_group_edit', $denyingControl);

        $this->assertFalse($block->isAllowed());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildBlock(string $fixtureName): Form
    {
        $definition = $this->loadFixture($fixtureName . '.json');
        return $this->buildBlockWithDefinition($fixtureName, $definition);
    }

    private function buildBlockWithDefinitionAndAccessControl(
        string $fixtureName,
        DefinitionAccessControl $accessControl
    ): Form {
        $definition = $this->loadFixture($fixtureName . '.json');

        $this->definitionResolver->method('resolve')
            ->with('form', $fixtureName)
            ->willReturn($definition);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);

        $formDefinitionNormalizer = $this->createMock(FormDefinitionNormalizer::class);
        $formDefinitionNormalizer->method('normalize')->willReturnArgument(0);

        $helper = new UnitObjectManagerHelper($this);

        return $helper->getObject(Form::class, [
            'context' => $this->context,
            'definitionResolver' => $this->definitionResolver,
            'dataProviderResolver' => $this->dataProviderResolver,
            'optionSourceResolver' => $this->optionSourceResolver,
            'formKey' => $this->formKey,
            'fieldNamer' => new FieldNamer(),
            'fieldsetLayoutBuilder' => new FieldsetLayoutBuilder(),
            'layoutNodeFlattener' => new LayoutNodeFlattener(),
            'sectionRenderer' => new SectionRenderer(
                $this->snippetResolver,
                new PhtmlRenderer(),
                $escaper,
                new SnippetViewModelRegistry()
            ),
            'formDefinitionNormalizer' => $formDefinitionNormalizer,
            'definitionAccessControl' => $accessControl,
            'data' => ['form_id' => $fixtureName],
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function buildBlockWithDefinition(string $formId, array $definition): Form
    {
        $this->definitionResolver->method('resolve')
            ->with('form', $formId)
            ->willReturn($definition);

        $helper = new UnitObjectManagerHelper($this);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);

        $formDefinitionNormalizer = $this->createMock(FormDefinitionNormalizer::class);
        $formDefinitionNormalizer->method('normalize')->willReturnArgument(0);

        return $helper->getObject(Form::class, [
            'context' => $this->context,
            'definitionResolver' => $this->definitionResolver,
            'dataProviderResolver' => $this->dataProviderResolver,
            'optionSourceResolver' => $this->optionSourceResolver,
            'formKey' => $this->formKey,
            'fieldNamer' => new FieldNamer(),
            'fieldsetLayoutBuilder' => new FieldsetLayoutBuilder(),
            'layoutNodeFlattener' => new LayoutNodeFlattener(),
            'sectionRenderer' => new SectionRenderer(
                $this->snippetResolver,
                new PhtmlRenderer(),
                $escaper,
                new SnippetViewModelRegistry()
            ),
            'formDefinitionNormalizer' => $formDefinitionNormalizer,
            'definitionAccessControl' => $this->definitionAccessControl,
            'data' => ['form_id' => $formId],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $name): array
    {
        $raw = file_get_contents(__DIR__ . '/../_fixtures/' . $name);
        if ($raw === false) {
            $this->fail('Unable to load fixture: ' . $name);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
