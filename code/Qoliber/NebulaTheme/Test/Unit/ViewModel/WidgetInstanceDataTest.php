<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel;

use Magento\Framework\Registry;
use Magento\Widget\Model\Widget\Instance;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\WidgetInstanceData;

class WidgetInstanceDataTest extends TestCase
{
    private Registry&MockObject $registry;
    private WidgetInstanceData $viewModel;

    protected function setUp(): void
    {
        $this->registry  = $this->createMock(Registry::class);
        $this->viewModel = new WidgetInstanceData($this->registry);
    }

    public function testReturnsEmptyShapeWhenNoInstanceInRegistry(): void
    {
        $this->registry->method('registry')->willReturn(null);

        $this->assertSame([
            'instance_id'   => null,
            'instance_type' => '',
            'theme_id'      => null,
            'title'         => '',
            'store_ids'     => [],
            'sort_order'    => 0,
            'parameters'    => [],
            'page_groups'   => [],
        ], $this->viewModel->getInitialState());
    }

    public function testMapsLoadedInstanceFields(): void
    {
        $instance = $this->createInstanceMock();
        $instance->method('getId')->willReturn(42);
        $instance->method('getInstanceType')->willReturn('Magento\Cms\Block\Widget\Block');
        $instance->method('getThemeId')->willReturn(7);
        $instance->method('getTitle')->willReturn('Footer Block');
        $instance->method('getStoreIds')->willReturn('0,1,2');
        $instance->method('getSortOrder')->willReturn('10');
        $instance->method('getWidgetParameters')->willReturn(['block_id' => '15', 'template' => 'block.phtml']);
        $instance->method('getPageGroups')->willReturn([]);

        $this->registry->method('registry')->with('current_widget_instance')->willReturn($instance);

        $state = $this->viewModel->getInitialState();

        $this->assertSame(42, $state['instance_id']);
        $this->assertSame('Magento\Cms\Block\Widget\Block', $state['instance_type']);
        $this->assertSame(7, $state['theme_id']);
        $this->assertSame('Footer Block', $state['title']);
        $this->assertSame(['0', '1', '2'], $state['store_ids']);
        $this->assertSame(10, $state['sort_order']);
        $this->assertSame(['block_id' => '15', 'template' => 'block.phtml'], $state['parameters']);
    }

    public function testStoreIdsHandlesArrayInput(): void
    {
        $instance = $this->createInstanceMock();
        $instance->method('getStoreIds')->willReturn(['1', '3']);
        $instance->method('getId')->willReturn(1);
        $instance->method('getInstanceType')->willReturn('X');
        $instance->method('getThemeId')->willReturn(1);
        $instance->method('getTitle')->willReturn('');
        $instance->method('getSortOrder')->willReturn(0);
        $instance->method('getWidgetParameters')->willReturn([]);
        $instance->method('getPageGroups')->willReturn([]);

        $this->registry->method('registry')->willReturn($instance);

        $this->assertSame(['1', '3'], $this->viewModel->getInitialState()['store_ids']);
    }

    public function testNewInstanceFlowHasNullInstanceId(): void
    {
        // Magento's _initWidgetInstance() registers a freshly-created Instance
        // with no id for the new-widget flow. The ViewModel should emit
        // instance_id: null (not 0).
        $instance = $this->createInstanceMock();
        $instance->method('getId')->willReturn(null);
        $instance->method('getInstanceType')->willReturn('');
        $instance->method('getThemeId')->willReturn(null);
        $instance->method('getTitle')->willReturn(null);
        $instance->method('getStoreIds')->willReturn(null);
        $instance->method('getSortOrder')->willReturn(null);
        $instance->method('getWidgetParameters')->willReturn(null);
        $instance->method('getPageGroups')->willReturn(null);

        $this->registry->method('registry')->willReturn($instance);

        $state = $this->viewModel->getInitialState();
        $this->assertNull($state['instance_id']);
        $this->assertNull($state['theme_id']);
        $this->assertSame('', $state['title']);
        $this->assertSame([], $state['store_ids']);
        $this->assertSame(0, $state['sort_order']);
        $this->assertSame([], $state['parameters']);
        $this->assertSame([], $state['page_groups']);
    }

    public function testNormalizePageGroupsHandlesDbShape(): void
    {
        // Magento's _afterLoad fetches widget_instance_page rows raw, so the
        // in-memory shape uses DB column names: page_for (not for) and
        // page_template (not template). We must accept those aliases.
        $instance = $this->createInstanceMock();
        $instance->method('getId')->willReturn(5);
        $instance->method('getInstanceType')->willReturn('Magento\Cms\Block\Widget\Block');
        $instance->method('getThemeId')->willReturn(7);
        $instance->method('getTitle')->willReturn('Footer Block');
        $instance->method('getStoreIds')->willReturn('1');
        $instance->method('getSortOrder')->willReturn(0);
        $instance->method('getWidgetParameters')->willReturn([]);
        $instance->method('getPageGroups')->willReturn([
            [
                'page_id'         => '12',
                'instance_id'     => '5',
                'page_group'      => 'all_pages',
                'layout_handle'   => 'default',
                'block_reference' => 'sidebar.main',
                'page_for'        => 'all',
                'entities'        => '',
                'page_template'   => 'widget/static_block/default.phtml',
            ],
        ]);

        $this->registry->method('registry')->willReturn($instance);
        $rows = $this->viewModel->getInitialState()['page_groups'];

        $this->assertCount(1, $rows);
        $this->assertSame('all_pages',                            $rows[0]['page_group']);
        $this->assertSame('sidebar.main',                         $rows[0]['block']);
        $this->assertSame('widget/static_block/default.phtml',    $rows[0]['template']);
        $this->assertSame('all',                                  $rows[0]['for']);
        $this->assertSame('default',                              $rows[0]['layout_handle']);
    }

    /**
     * @return Instance&MockObject
     */
    private function createInstanceMock(): Instance&MockObject
    {
        /** @var Instance&MockObject $mock */
        $mock = $this->getMockBuilder(Instance::class)
            ->disableOriginalConstructor()
            ->addMethods(['getInstanceType', 'getTitle', 'getSortOrder', 'getThemeId', 'getPageGroups'])
            ->onlyMethods(['getId', 'getStoreIds', 'getWidgetParameters'])
            ->getMock();

        return $mock;
    }
}
