<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as UnitObjectManagerHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;
use Qoliber\NebulaComponent\Model\OptionSourceResolver;
use Qoliber\NebulaComponent\Model\RendererPool;
use Qoliber\NebulaGrid\Block\Grid;
use Qoliber\NebulaGrid\Model\ColumnLoader;
use Qoliber\NebulaGrid\Model\Definition\GridDefinitionNormalizer;
use Qoliber\NebulaGrid\Model\GridDataLoader;
use Qoliber\NebulaGrid\Renderer\Column\ColumnDispatcher;

class GridTest extends TestCase
{
    /** @var \Qoliber\NebulaComponent\Api\DefinitionResolverInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $definitionResolver;

    /** @var \Qoliber\NebulaComponent\Model\OptionSourceResolver&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $optionSourceResolver;

    /** @var \Qoliber\NebulaGrid\Model\GridDataLoader&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $gridDataLoader;

    /** @var \Qoliber\NebulaGrid\Renderer\Column\ColumnDispatcher&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $columnDispatcher;

    /** @var \Qoliber\NebulaGrid\Model\Definition\GridDefinitionNormalizer&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $gridDefinitionNormalizer;

    /** @var \Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $definitionAccessControl;

    /** @var \Magento\Framework\Data\Form\FormKey&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $formKey;

    /** @var \Magento\Framework\App\RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $request;

    /** @var \Magento\Framework\UrlInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $urlBuilder;

    private Grid $block;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        $this->fixture = $this->loadFixture('product_listing.json');

        $this->definitionResolver = $this->createMock(DefinitionResolverInterface::class);
        $this->optionSourceResolver = $this->createMock(OptionSourceResolver::class);
        $this->gridDataLoader = $this->createMock(GridDataLoader::class);
        $this->columnDispatcher = $this->createMock(ColumnDispatcher::class);
        $this->formKey = $this->createMock(FormKey::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->gridDefinitionNormalizer = $this->createMock(GridDefinitionNormalizer::class);
        $this->gridDefinitionNormalizer->method('normalize')->willReturnArgument(0);
        $this->definitionAccessControl = $this->createMock(DefinitionAccessControl::class);
        $this->definitionAccessControl->method('isAllowed')->willReturn(true);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);
        $escaper->method('escapeUrl')->willReturnArgument(0);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);
        $context->method('getEscaper')->willReturn($escaper);

        $rendererPool = new RendererPool([], []);

        $columnLoader = new ColumnLoader($this->optionSourceResolver);

        $helper = new UnitObjectManagerHelper($this);
        $this->block = $helper->getObject(Grid::class, [
            'context' => $context,
            'definitionResolver' => $this->definitionResolver,
            'formKey' => $this->formKey,
            'rendererPool' => $rendererPool,
            'columnDispatcher' => $this->columnDispatcher,
            'columnLoader' => $columnLoader,
            'gridDataLoader' => $this->gridDataLoader,
            'gridDefinitionNormalizer' => $this->gridDefinitionNormalizer,
            'definitionAccessControl' => $this->definitionAccessControl,
            'data' => ['grid_id' => 'product_listing'],
        ]);

        $this->definitionResolver->method('resolve')
            ->with('grid', 'product_listing')
            ->willReturn($this->fixture);
    }

    // =========================================================================
    // Identity & definition
    // =========================================================================

    public function testGetGridIdReturnsConfiguredId(): void
    {
        $this->assertSame('product_listing', $this->block->getGridId());
    }

    public function testGetDefinitionDelegatesToResolver(): void
    {
        $this->assertSame($this->fixture, $this->block->getDefinition());
    }

    public function testGetDefinitionIsCachedAcrossCalls(): void
    {
        $this->definitionResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($this->fixture);

        $this->block->getDefinition();
        $this->block->getDefinition();
        $this->block->getDefinition();
    }

    // =========================================================================
    // Columns — count, order, filter resolution
    // =========================================================================

    public function testGetColumnsReturnsAllDefinedColumns(): void
    {
        $columns = $this->block->getColumns();

        $this->assertCount(6, $columns);
        $this->assertArrayHasKey('entity_id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('actions', $columns);
    }

    public function testGetColumnsSortsByPositionAscending(): void
    {
        $columns = $this->block->getColumns();
        $keys = array_keys($columns);

        $this->assertSame(
            ['entity_id', 'name', 'sku', 'price', 'status', 'actions'],
            $keys,
            'Columns must come back ordered by the "position" key ascending'
        );
    }

    public function testGetColumnsSkipsFilterOptionsSourceWhenEmpty(): void
    {
        // No column in the fixture declares filterOptionsSource, so the
        // option-source resolver must not be invoked.
        $this->optionSourceResolver->expects($this->never())->method('toLabelMap');

        $this->block->getColumns();
    }

    // =========================================================================
    // Settings — page sizes, sort, mass actions
    // =========================================================================

    public function testGetPageSizeDefaultsToDefinition(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $default
        );

        $this->assertSame(25, $this->block->getPageSize());
    }

    public function testGetPageSizeHonorsRequestOverride(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'pageSize' ? 50 : $default
        );

        $this->assertSame(50, $this->block->getPageSize());
    }

    public function testGetPageSizesReturnsConfiguredSizes(): void
    {
        $this->assertSame([10, 25, 50, 100], $this->block->getPageSizes());
    }

    public function testGetPageIsAtLeastOne(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'page' ? 0 : $default
        );

        $this->assertSame(1, $this->block->getPage());
    }

    public function testGetSortUsesDefinitionDefault(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $default
        );

        $this->assertSame('entity_id', $this->block->getSort());
        $this->assertSame('desc', $this->block->getSortDir());
    }

    public function testGetSortDirFallsBackToAscForInvalidValue(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'dir' ? 'sideways' : $default
        );

        $this->assertSame('asc', $this->block->getSortDir());
    }

    public function testHasMassActionsIsTrueWhenConfigured(): void
    {
        $this->assertTrue($this->block->hasMassActions());
        $this->assertCount(1, $this->block->getMassActions());
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    public function testGetTotalPagesCeilsCorrectly(): void
    {
        // Put a non-empty provider result on the block via reflection, so we can
        // cover getTotalCount/getTotalPages without calling out to a real provider.
        $this->setProviderData(['items' => [], 'totalCount' => 73]);

        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $default
        );

        $this->assertSame(73, $this->block->getTotalCount());
        $this->assertSame(3, $this->block->getTotalPages()); // ceil(73/25) = 3
    }

    public function testGetTotalPagesIsOneWhenPageSizeZero(): void
    {
        $this->setProviderData(['items' => [], 'totalCount' => 10]);
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'pageSize' ? 0 : $default
        );

        $this->assertSame(1, $this->block->getTotalPages());
    }

    public function testGetItemsReturnsProviderItems(): void
    {
        $items = [
            ['entity_id' => 1, 'name' => 'Foo'],
            ['entity_id' => 2, 'name' => 'Bar'],
        ];
        $this->setProviderData(['items' => $items, 'totalCount' => 2]);

        $this->assertSame($items, $this->block->getItems());
    }

    // =========================================================================
    // URLs and keys
    // =========================================================================

    public function testGetDataUrlUsesNebulaRoute(): void
    {
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('nebula/grid/data', $this->anything())
            ->willReturn('/admin/nebula/grid/data');

        $this->assertSame('/admin/nebula/grid/data', $this->block->getDataUrl());
    }

    public function testGetExportUrlUsesNebulaRoute(): void
    {
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('nebula/grid/export', $this->anything())
            ->willReturn('/admin/nebula/grid/export');

        $this->assertSame('/admin/nebula/grid/export', $this->block->getExportUrl());
    }

    public function testGetFormKeyDelegates(): void
    {
        $this->formKey->expects($this->once())->method('getFormKey')->willReturn('ABC123');

        $this->assertSame('ABC123', $this->block->getFormKey());
    }

    // =========================================================================
    // Filters
    // =========================================================================

    public function testGetActiveFiltersStripsEmptyValues(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static function (string $key, mixed $default = null): mixed {
                if ($key === 'filters') {
                    return ['name' => 'foo', 'sku' => '', 'status' => null];
                }
                return $default;
            }
        );

        $filters = $this->block->getActiveFilters();

        $this->assertSame(['name' => 'foo'], $filters);
    }

    public function testGetActiveFiltersReturnsEmptyArrayWhenNotArray(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'filters' ? 'garbage' : $default
        );

        $this->assertSame([], $this->block->getActiveFilters());
    }

    public function testGetFiltersJsonSerializes(): void
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'filters' ? ['name' => 'foo'] : $default
        );

        $this->assertSame('{"name":"foo"}', $this->block->getFiltersJson());
    }

    // =========================================================================
    // Renderer pool integration
    // =========================================================================

    public function testGetColumnRendererComponentUsesPoolDefault(): void
    {
        $this->assertSame('nebulaColumn_badge', $this->block->getColumnRendererComponent('badge'));
    }

    public function testTranslatePassthroughReturnsString(): void
    {
        $this->assertIsString($this->block->translate('Any text'));
    }

    public function testIsAllowedDelegatesToDefinitionAccessControl(): void
    {
        $denyingControl = $this->createMock(DefinitionAccessControl::class);
        $denyingControl->method('isAllowed')->willReturn(false);

        $helper = new UnitObjectManagerHelper($this);
        $block = $helper->getObject(Grid::class, [
            'context' => $this->createConfiguredContext(),
            'definitionResolver' => $this->definitionResolver,
            'formKey' => $this->formKey,
            'rendererPool' => new RendererPool([], []),
            'columnDispatcher' => $this->columnDispatcher,
            'columnLoader' => new ColumnLoader($this->optionSourceResolver),
            'gridDataLoader' => $this->gridDataLoader,
            'gridDefinitionNormalizer' => $this->gridDefinitionNormalizer,
            'definitionAccessControl' => $denyingControl,
            'data' => ['grid_id' => 'product_listing'],
        ]);

        $this->assertFalse($block->isAllowed());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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

    /**
     * @param array<string, mixed> $data
     */
    private function setProviderData(array $data): void
    {
        $reflection = new \ReflectionClass($this->block);
        $prop = $reflection->getProperty('providerData');
        $prop->setAccessible(true);
        $prop->setValue($this->block, $data);
    }

    private function createConfiguredContext(): Context
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);
        $escaper->method('escapeUrl')->willReturnArgument(0);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);
        $context->method('getEscaper')->willReturn($escaper);

        return $context;
    }
}
