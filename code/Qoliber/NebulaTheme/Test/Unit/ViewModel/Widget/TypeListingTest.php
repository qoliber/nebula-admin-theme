<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\Widget;

use Magento\Framework\View\Design\Theme\Label\ListInterface as ThemeLabelList;
use Magento\Widget\Model\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\Widget\TypeListing;

class TypeListingTest extends TestCase
{
    private Widget&MockObject $widget;
    private ThemeLabelList&MockObject $themeLabels;
    private TypeListing $vm;

    protected function setUp(): void
    {
        $this->widget      = $this->createMock(Widget::class);
        $this->themeLabels = $this->createMock(ThemeLabelList::class);
        $this->vm          = new TypeListing($this->widget, $this->themeLabels);
    }

    public function testGetWidgetTypesReturnsValueLabelRows(): void
    {
        $this->widget->method('getWidgetsArray')->willReturn([
            ['type' => 'Magento\Cms\Block\Widget\Block', 'name' => 'CMS Static Block'],
            ['type' => 'Magento\Cms\Block\Widget\Page\Link', 'name' => 'CMS Page Link'],
        ]);

        $this->assertSame(
            [
                ['value' => 'Magento\Cms\Block\Widget\Block', 'label' => 'CMS Static Block'],
                ['value' => 'Magento\Cms\Block\Widget\Page\Link', 'label' => 'CMS Page Link'],
            ],
            $this->vm->getWidgetTypes(),
        );
    }

    public function testGetWidgetTypesSkipsMalformedEntries(): void
    {
        $this->widget->method('getWidgetsArray')->willReturn([
            'malformed-scalar',
            ['type' => '', 'name' => 'No Type'],
            ['type' => 'X\Y', 'name' => ''],
            ['type' => 'X\Y\Z', 'name' => 'Valid'],
        ]);

        $rows = $this->vm->getWidgetTypes();

        $this->assertCount(1, $rows);
        $this->assertSame('X\Y\Z', $rows[0]['value']);
    }

    public function testGetThemesReturnsLabelListRows(): void
    {
        $this->themeLabels->method('getLabels')->willReturn([
            ['value' => '7', 'label' => 'Magento Luma'],
            ['value' => '8', 'label' => 'Qoliber Storefront'],
        ]);

        $this->assertSame(
            [
                ['value' => '7', 'label' => 'Magento Luma'],
                ['value' => '8', 'label' => 'Qoliber Storefront'],
            ],
            $this->vm->getThemes(),
        );
    }

    public function testGetThemesSkipsBlankRows(): void
    {
        $this->themeLabels->method('getLabels')->willReturn([
            ['value' => '7', 'label' => 'Magento Luma'],
            ['value' => '',  'label' => 'No Value'],
            ['value' => '9', 'label' => ''],
            'malformed-scalar',
        ]);

        $rows = $this->vm->getThemes();
        $this->assertCount(1, $rows);
        $this->assertSame('7', $rows[0]['value']);
    }

    public function testGetTypeCodeMapPairsClassFqcnWithWidgetCode(): void
    {
        $this->widget->method('getWidgetsArray')->willReturn([
            ['type' => 'Magento\Cms\Block\Widget\Block', 'name' => 'CMS Static Block', 'code' => 'cms_static_block'],
            ['type' => 'Magento\Cms\Block\Widget\Page\Link', 'name' => 'CMS Page Link', 'code' => 'cms_page_link'],
            ['type' => 'X\Y', 'name' => 'X', 'code' => ''], // skipped — empty code
        ]);

        $map = $this->vm->getTypeCodeMap();

        $this->assertSame([
            'Magento\Cms\Block\Widget\Block' => 'cms_static_block',
            'Magento\Cms\Block\Widget\Page\Link' => 'cms_page_link',
        ], $map);
    }
}
