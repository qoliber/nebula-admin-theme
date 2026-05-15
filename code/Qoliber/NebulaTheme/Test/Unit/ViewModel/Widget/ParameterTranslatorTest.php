<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\Widget;

use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Widget\Model\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry;
use Qoliber\NebulaTheme\ViewModel\Widget\ParameterTranslator;

class ParameterTranslatorTest extends TestCase
{
    private Widget&MockObject $widget;
    private ObjectManagerInterface&MockObject $objectManager;
    private ParameterTranslator $vm;

    protected function setUp(): void
    {
        $this->widget        = $this->createMock(Widget::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        // ParameterTranslator only uses the registry's aliasForHelperBlock()
        // — it never instantiates a chooser ViewModel — so the TMapFactory
        // dep is irrelevant to these tests.
        $tmapFactory = $this->createMock(\Magento\Framework\ObjectManager\TMapFactory::class);
        $tmapFactory->method('create')->willReturn($this->createMock(\Magento\Framework\ObjectManager\TMap::class));
        $registry = new WidgetChooserRegistry($tmapFactory, [
            'cms_block' => [
                'helper_block' => 'Magento\Cms\Block\Adminhtml\Block\Widget\Chooser',
                'template'     => 'Qoliber_NebulaTheme::widget/chooser/cms_block.phtml',
                'view_model'   => 'Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsBlock',
            ],
        ]);
        $this->vm = new ParameterTranslator($this->widget, $this->objectManager, $registry);
    }

    public function testTextFieldTranslates(): void
    {
        $config = new DataObject([
            'parameters' => [
                'products_count' => new DataObject([
                    'key'      => 'products_count',
                    'type'     => 'text',
                    'label'    => new Phrase('Count'),
                    'value'    => '5',
                    'required' => '1',
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');

        $this->assertSame('products_count', $rows[0]['name']);
        $this->assertSame('text', $rows[0]['type']);
        $this->assertSame('Count', $rows[0]['label']);
        $this->assertSame('5', $rows[0]['default']);
        $this->assertTrue($rows[0]['required']);
    }

    public function testSelectFieldWithInlineOptions(): void
    {
        // Uses `display_type` rather than `template` because the latter is
        // filtered out (Magento's Properties tab hides it).
        $config = new DataObject([
            'parameters' => [
                'display_type' => new DataObject([
                    'key'   => 'display_type',
                    'type'  => 'select',
                    'label' => new Phrase('Display Type'),
                    'values' => [
                        ['label' => new Phrase('Grid'), 'value' => 'a.phtml'],
                        ['label' => new Phrase('List'), 'value' => 'b.phtml'],
                    ],
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');

        $this->assertSame('select', $rows[0]['type']);
        $this->assertSame([
            ['value' => 'a.phtml', 'label' => 'Grid'],
            ['value' => 'b.phtml', 'label' => 'List'],
        ], $rows[0]['options']);
    }

    public function testSelectFieldResolvesSourceModel(): void
    {
        $source = new class implements \Magento\Framework\Data\OptionSourceInterface {
            public function toOptionArray(): array
            {
                return [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']];
            }
        };
        $config = new DataObject([
            'parameters' => [
                'show_pager' => new DataObject([
                    'key'          => 'show_pager',
                    'type'         => 'select',
                    'label'        => new Phrase('Show Pager'),
                    'source_model' => 'Magento\Config\Model\Config\Source\Yesno',
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);
        $this->objectManager->method('get')->willReturn($source);

        $rows = $this->vm->getParameters('X');

        $this->assertSame('select', $rows[0]['type']);
        $this->assertSame([
            ['value' => '1', 'label' => 'Yes'],
            ['value' => '0', 'label' => 'No'],
        ], $rows[0]['options']);
    }

    public function testBlockChooserResolvesAliasFromRegistry(): void
    {
        $helper = new DataObject();
        $helper->setType('Magento\Cms\Block\Adminhtml\Block\Widget\Chooser');

        $config = new DataObject([
            'parameters' => [
                'block_id' => new DataObject([
                    'key'          => 'block_id',
                    'type'         => 'label',
                    'label'        => new Phrase('Block'),
                    'helper_block' => $helper,
                    'required'     => '1',
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');

        $this->assertSame('chooser', $rows[0]['type']);
        $this->assertSame('cms_block', $rows[0]['chooser']);
        $this->assertTrue($rows[0]['required']);
    }

    public function testUnknownHelperFallsBackToGenericChooser(): void
    {
        $helper = new DataObject();
        $helper->setType('Some\Third\Party\Chooser');

        $config = new DataObject([
            'parameters' => [
                'thing_id' => new DataObject([
                    'key'          => 'thing_id',
                    'type'         => 'label',
                    'label'        => new Phrase('Thing'),
                    'helper_block' => $helper,
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');
        $this->assertSame('generic', $rows[0]['chooser']);
    }

    public function testConditionsTypeMapsToConditionsChooser(): void
    {
        $config = new DataObject([
            'parameters' => [
                'condition' => new DataObject([
                    'key'   => 'condition',
                    'type'  => 'Magento\CatalogWidget\Block\Product\Widget\Conditions',
                    'label' => new Phrase('Conditions'),
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');
        $this->assertSame('chooser', $rows[0]['type']);
        $this->assertSame('conditions', $rows[0]['chooser']);
    }

    public function testEmptyConfigReturnsEmptyArray(): void
    {
        $this->widget->method('getConfigAsObject')->willReturn(new DataObject());

        $this->assertSame([], $this->vm->getParameters('Nope'));
    }

    public function testEmptyNameSkipped(): void
    {
        $config = new DataObject([
            'parameters' => [
                '' => new DataObject(['type' => 'text', 'key' => '']),
                'real' => new DataObject([
                    'key'   => 'real',
                    'type'  => 'text',
                    'label' => new Phrase('Real'),
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');
        $this->assertCount(1, $rows);
        $this->assertSame('real', $rows[0]['name']);
    }

    public function testDependsExtraction(): void
    {
        $config = new DataObject([
            'parameters' => [
                'products_per_page' => new DataObject([
                    'key'     => 'products_per_page',
                    'type'    => 'text',
                    'label'   => new Phrase('Per Page'),
                    'depends' => ['show_pager' => ['value' => '1']],
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');
        $this->assertSame([['param' => 'show_pager', 'value' => '1']], $rows[0]['depends']);
    }

    public function testVisibleFlagAndDescriptionAndSortOrder(): void
    {
        $config = new DataObject([
            'parameters' => [
                'cache_lifetime' => new DataObject([
                    'key'         => 'cache_lifetime',
                    'type'        => 'text',
                    'label'       => new Phrase('Cache Lifetime'),
                    'description' => '<p>Time in seconds…</p>',
                    'sort_order'  => '30',
                ]),
                'uiComponent' => new DataObject([
                    'key'     => 'uiComponent',
                    'type'    => 'text',
                    'label'   => new Phrase('UI Component'),
                    'visible' => '0',
                    'value'   => 'widget_recently_viewed',
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');
        $this->assertSame('<p>Time in seconds…</p>', $rows[0]['description']);
        $this->assertSame(30, $rows[0]['sortOrder']);
        $this->assertTrue($rows[0]['visible']);
        $this->assertFalse($rows[1]['visible']);
        $this->assertSame('widget_recently_viewed', $rows[1]['default']);
    }

    public function testTemplateParameterIsHiddenFromTheParametersSection(): void
    {
        // Mirrors Magento's own Properties tab — template lives in
        // Layout Updates rows, not in the parameters list.
        $config = new DataObject([
            'parameters' => [
                'template' => new DataObject([
                    'key'   => 'template',
                    'type'  => 'select',
                    'label' => new Phrase('Template'),
                    'values' => [
                        ['label' => new Phrase('Default'), 'value' => 'tpl.phtml'],
                    ],
                ]),
                'title' => new DataObject([
                    'key'   => 'title',
                    'type'  => 'text',
                    'label' => new Phrase('Title'),
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturn($config);

        $rows = $this->vm->getParameters('X');

        $this->assertCount(1, $rows);
        $this->assertSame('title', $rows[0]['name']);
    }

    public function testGetAllParametersBuildsTypeKeyedMap(): void
    {
        $configA = new DataObject([
            'parameters' => [
                'title' => new DataObject([
                    'key'   => 'title',
                    'type'  => 'text',
                    'label' => new Phrase('Title'),
                ]),
            ],
        ]);
        $configC = new DataObject([
            'parameters' => [
                'count' => new DataObject([
                    'key'   => 'count',
                    'type'  => 'text',
                    'label' => new Phrase('Count'),
                ]),
            ],
        ]);
        $this->widget->method('getConfigAsObject')->willReturnMap([
            ['A\B', $configA],
            ['C\D', $configC],
        ]);

        $map = $this->vm->getAllParameters([
            ['value' => 'A\B', 'label' => 'A'],
            ['value' => 'C\D', 'label' => 'C'],
        ]);

        $this->assertSame('title', $map['A\B'][0]['name']);
        $this->assertSame('count', $map['C\D'][0]['name']);
    }
}
