<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\Widget;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\View\Model\PageLayout\Config\BuilderInterface as PageLayoutConfigBuilder;
use Magento\Framework\View\Model\PageLayout\ConfigInterface as PageLayoutConfig;
use Magento\Framework\View\PageLayout\Config as PageLayoutConfigImpl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\Widget\PageGroupCatalog;

class PageGroupCatalogTest extends TestCase
{
    private ProductType&MockObject $productType;
    private PageLayoutConfigBuilder&MockObject $pageLayoutConfigBuilder;
    private PageGroupCatalog $vm;

    protected function setUp(): void
    {
        $this->productType             = $this->createMock(ProductType::class);
        $this->pageLayoutConfigBuilder = $this->createMock(PageLayoutConfigBuilder::class);
        $this->vm                      = new PageGroupCatalog(
            $this->productType,
            $this->pageLayoutConfigBuilder,
        );
    }

    public function testGetPageGroupOptionsCombinesStaticAndDynamicProductTypes(): void
    {
        $this->productType->method('getTypes')->willReturn([
            'simple'       => ['label' => 'Simple Product'],
            'configurable' => ['label' => 'Configurable Product'],
        ]);

        $rows = $this->vm->getPageGroupOptions();

        // 6 hardcoded (all_pages, pages, page_layouts, anchor_categories,
        // notanchor_categories, all_products) + 2 product-type rows = 8 total.
        $this->assertCount(8, $rows);
        $values = array_column($rows, 'value');
        $this->assertContains('all_pages', $values);
        $this->assertContains('pages', $values);
        $this->assertContains('page_layouts', $values);
        $this->assertContains('anchor_categories', $values);
        $this->assertContains('notanchor_categories', $values);
        $this->assertContains('all_products', $values);
        $this->assertContains('simple_products', $values);
        $this->assertContains('configurable_products', $values);
    }

    public function testGetPageLayoutOptionsHandlesArrayShape(): void
    {
        // Magento\Framework\View\PageLayout\Config::getPageLayouts() returns
        // a string[][] map: ['1column' => ['label' => '1 column'], ...].
        $config = new class {
            public function getPageLayouts(): array
            {
                return [
                    '1column'       => ['label' => '1 column'],
                    '2columns-left' => ['label' => '2 columns with left bar'],
                ];
            }
        };

        $this->pageLayoutConfigBuilder->method('getPageLayoutsConfig')->willReturn($config);

        $rows = $this->vm->getPageLayoutOptions();

        $this->assertSame([
            ['value' => '1column',       'label' => '1 column'],
            ['value' => '2columns-left', 'label' => '2 columns with left bar'],
        ], $rows);
    }

    public function testGetPageLayoutOptionsHandlesObjectShape(): void
    {
        // Theme-overridden configs sometimes return DataObjects with getLabel().
        $config = new class {
            public function getPageLayouts(): array
            {
                return [
                    '1column' => new \Magento\Framework\DataObject(['label' => '1 column']),
                ];
            }
        };

        $this->pageLayoutConfigBuilder->method('getPageLayoutsConfig')->willReturn($config);

        $rows = $this->vm->getPageLayoutOptions();
        $this->assertSame([['value' => '1column', 'label' => '1 column']], $rows);
    }

    public function testGetPageLayoutOptionsHandlesStringShape(): void
    {
        // Some legacy paths return code → label string directly.
        $config = new class {
            public function getPageLayouts(): array
            {
                return ['1column' => '1 column'];
            }
        };

        $this->pageLayoutConfigBuilder->method('getPageLayoutsConfig')->willReturn($config);

        $rows = $this->vm->getPageLayoutOptions();
        $this->assertSame([['value' => '1column', 'label' => '1 column']], $rows);
    }

    public function testGetPageGroupOptionsHandlesMissingTypeLabel(): void
    {
        $this->productType->method('getTypes')->willReturn([
            'mystery' => [],
        ]);

        $rows = $this->vm->getPageGroupOptions();
        $mystery = array_values(array_filter($rows, fn ($r) => $r['value'] === 'mystery_products'));
        $this->assertSame('mystery', $mystery[0]['label']);
    }

    public function testGetLayoutHandleMapStaticEntries(): void
    {
        $this->productType->method('getTypes')->willReturn([]);

        $map = $this->vm->getLayoutHandleMap();

        $this->assertSame('default', $map['all_pages']);
        $this->assertSame('catalog_product_view', $map['all_products']);
        $this->assertSame('catalog_category_view_type_layered', $map['anchor_categories']);
        $this->assertSame('catalog_category_view_type_default', $map['notanchor_categories']);
    }

    public function testGetLayoutHandleMapInterpolatesProductTypes(): void
    {
        $this->productType->method('getTypes')->willReturn([
            'configurable' => ['label' => 'Configurable'],
            'simple'       => ['label' => 'Simple'],
        ]);

        $map = $this->vm->getLayoutHandleMap();

        $this->assertSame('catalog_product_view_type_configurable', $map['configurable_products']);
        $this->assertSame('catalog_product_view_type_simple', $map['simple_products']);
    }
}
