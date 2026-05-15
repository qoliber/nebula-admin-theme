<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\Model\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Widget\Model\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Model\Source\WidgetInstanceType;

class WidgetInstanceTypeTest extends TestCase
{
    private ResourceConnection&MockObject $resource;
    private AdapterInterface&MockObject $connection;
    private Select&MockObject $select;
    private Widget&MockObject $widget;
    private WidgetInstanceType $source;

    protected function setUp(): void
    {
        $this->resource   = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select     = $this->createMock(Select::class);
        $this->widget     = $this->createMock(Widget::class);

        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('distinct')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();

        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')->willReturn('widget_instance');
        $this->connection->method('select')->willReturn($this->select);

        $this->source = new WidgetInstanceType($this->resource, $this->widget);
    }

    public function testReturnsEmptyArrayWhenNoWidgetsExist(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $this->widget->method('getWidgetsArray')->willReturn([]);

        $this->assertSame([], $this->source->toOptionArray());
    }

    public function testMapsTypesToFriendlyLabelsFromWidgetConfig(): void
    {
        $this->connection->method('fetchCol')->willReturn([
            'Magento\Cms\Block\Widget\Block',
            'Magento\Catalog\Block\Product\Widget\NewWidget',
        ]);
        $this->widget->method('getWidgetsArray')->willReturn([
            ['type' => 'Magento\Cms\Block\Widget\Block', 'name' => 'CMS Static Block'],
            ['type' => 'Magento\Catalog\Block\Product\Widget\NewWidget', 'name' => 'Catalog New Products List'],
            ['type' => 'Magento\Cms\Block\Widget\Page\Link', 'name' => 'CMS Page Link'],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertCount(2, $rows);
        $this->assertSame(
            ['value' => 'Magento\Cms\Block\Widget\Block', 'label' => 'CMS Static Block'],
            $rows[0],
        );
        $this->assertSame(
            ['value' => 'Magento\Catalog\Block\Product\Widget\NewWidget', 'label' => 'Catalog New Products List'],
            $rows[1],
        );
    }

    public function testFallsBackToRawTypeWhenWidgetConfigMissing(): void
    {
        $this->connection->method('fetchCol')->willReturn([
            'Qoliber\Removed\Block\Widget',
            'Magento\Cms\Block\Widget\Block',
        ]);
        $this->widget->method('getWidgetsArray')->willReturn([
            ['type' => 'Magento\Cms\Block\Widget\Block', 'name' => 'CMS Static Block'],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertSame('Qoliber\Removed\Block\Widget', $rows[0]['label']);
        $this->assertSame('CMS Static Block', $rows[1]['label']);
    }

    public function testIgnoresMalformedWidgetConfigEntries(): void
    {
        $this->connection->method('fetchCol')->willReturn(['Magento\Cms\Block\Widget\Block']);
        $this->widget->method('getWidgetsArray')->willReturn([
            'not-an-array',
            ['type' => '', 'name' => 'Empty Type'],
            ['type' => 'Some\Type', 'name' => ''],
            ['type' => 'Magento\Cms\Block\Widget\Block', 'name' => 'CMS Static Block'],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertSame('CMS Static Block', $rows[0]['label']);
    }
}
