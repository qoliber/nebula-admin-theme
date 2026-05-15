<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\Widget;

use Magento\Framework\DataObject;
use Magento\Widget\Model\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Model\Registry\WidgetContainerFallbackRegistry;
use Qoliber\NebulaTheme\ViewModel\Widget\ContainerCatalog;

class ContainerCatalogTest extends TestCase
{
    private Widget&MockObject $widget;
    private WidgetContainerFallbackRegistry $fallback;
    private ContainerCatalog $vm;

    protected function setUp(): void
    {
        $this->widget   = $this->createMock(Widget::class);
        $this->fallback = new WidgetContainerFallbackRegistry(['content', 'sidebar.main']);
        $this->vm       = new ContainerCatalog($this->widget, $this->fallback);
    }

    public function testGetContainersForReadsSupportedContainers(): void
    {
        $config = new DataObject([
            'supported_containers' => [
                ['container_name' => 'sidebar.main', 'template' => []],
                ['container_name' => 'content',      'template' => []],
                'malformed-scalar',
                ['template' => ['x' => 'y']], // missing container_name → skipped
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $this->assertSame(['sidebar.main', 'content'], $this->vm->getContainersFor('X'));
    }

    public function testGetContainersForFallsBackWhenWidgetDeclaresNone(): void
    {
        $this->widget->method('getConfigAsObject')->willReturn(new DataObject());

        $this->assertSame(['content', 'sidebar.main'], $this->vm->getContainersFor('CmsBlock'));
    }

    public function testGetContainerTemplatesForMapsPerContainer(): void
    {
        $this->widget->method('getWidgets')->willReturn([
            'new_products' => [
                '@' => ['type' => 'Magento\Catalog\NewWidget'],
                'supported_containers' => [
                    ['container_name' => 'content', 'template' => ['grid' => 'default', 'list' => 'list']],
                    ['container_name' => 'sidebar.main', 'template' => ['default' => 'list_default']],
                ],
                'parameters' => [
                    'template' => [
                        'values' => [
                            'default'      => ['label' => 'Grid Template', 'value' => 'new_grid.phtml'],
                            'list'         => ['label' => 'List Template', 'value' => 'new_list.phtml'],
                            'list_default' => ['label' => 'Sidebar Template', 'value' => 'new_default_list.phtml'],
                        ],
                    ],
                ],
            ],
        ]);

        $map = $this->vm->getContainerTemplatesFor('Magento\Catalog\NewWidget');

        $this->assertSame([
            ['value' => 'new_grid.phtml', 'label' => 'Grid Template'],
            ['value' => 'new_list.phtml', 'label' => 'List Template'],
        ], $map['content']);
        $this->assertSame([
            ['value' => 'new_default_list.phtml', 'label' => 'Sidebar Template'],
        ], $map['sidebar.main']);
    }

    public function testGetContainerTemplatesForWidgetNotFoundReturnsEmpty(): void
    {
        $this->widget->method('getWidgets')->willReturn([]);
        $this->assertSame([], $this->vm->getContainerTemplatesFor('Bogus'));
    }

    public function testGetContainerTemplatesForFallsBackWhenNoContainers(): void
    {
        $this->widget->method('getWidgets')->willReturn([
            'cms_static_block' => [
                '@' => ['type' => 'Magento\Cms\Block'],
                'parameters' => [
                    'template' => [
                        'values' => [
                            'default' => ['label' => 'Default', 'value' => 'widget/static_block/default.phtml'],
                        ],
                    ],
                ],
            ],
        ]);

        $map = $this->vm->getContainerTemplatesFor('Magento\Cms\Block');

        // Each fallback container gets the full template list.
        $this->assertArrayHasKey('content', $map);
        $this->assertArrayHasKey('sidebar.main', $map);
        $this->assertSame([
            ['value' => 'widget/static_block/default.phtml', 'label' => 'Default'],
        ], $map['content']);
    }

    public function testGetContainerTemplatesForReturnsEmptyWhenNoTemplates(): void
    {
        $this->widget->method('getWidgets')->willReturn([
            'cms_static_block' => [
                '@' => ['type' => 'Magento\Cms\Block'],
                // no parameters block at all
            ],
        ]);

        $this->assertSame([], $this->vm->getContainerTemplatesFor('Magento\Cms\Block'));
    }
}
