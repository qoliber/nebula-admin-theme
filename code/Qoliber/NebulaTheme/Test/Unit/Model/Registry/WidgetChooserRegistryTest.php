<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\Model\Registry;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;
use Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry;

class WidgetChooserRegistryTest extends TestCase
{
    private TMapFactory&MockObject $tmapFactory;
    private TMap&MockObject $tmap;

    protected function setUp(): void
    {
        $this->tmapFactory = $this->createMock(TMapFactory::class);
        $this->tmap        = $this->createMock(TMap::class);
        $this->tmapFactory->method('create')->willReturn($this->tmap);
    }

    public function testAliasForHelperBlockResolvesViaIndex(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'cms_block' => [
                'helper_block' => 'Magento\Cms\Block\Adminhtml\Block\Widget\Chooser',
                'template'     => 'Qoliber_NebulaTheme::widget/chooser/cms_block.phtml',
                'view_model'   => 'Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsBlock',
            ],
            'cms_page' => [
                'helper_block' => 'Magento\Cms\Block\Adminhtml\Page\Widget\Chooser',
                'template'     => 'Qoliber_NebulaTheme::widget/chooser/cms_page.phtml',
                'view_model'   => 'Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsPage',
            ],
        ]);

        $this->assertSame('cms_block', $reg->aliasForHelperBlock('Magento\Cms\Block\Adminhtml\Block\Widget\Chooser'));
        $this->assertSame('cms_page',  $reg->aliasForHelperBlock('Magento\Cms\Block\Adminhtml\Page\Widget\Chooser'));
    }

    public function testAliasForHelperBlockReturnsFallbackForUnknownHelper(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, []);
        $this->assertSame('generic', $reg->aliasForHelperBlock('Some\Third\Party\Chooser'));
        $this->assertSame('custom',  $reg->aliasForHelperBlock('Some\Third\Party\Chooser', 'custom'));
    }

    public function testAllReturnsConfigByAliasWithModeDefault(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'cms_block' => [
                'helper_block' => 'X\Y',
                'template'     => 'V::tpl.phtml',
                'view_model'   => 'V\M',
            ],
        ]);

        $this->assertSame([
            'cms_block' => [
                'helper_block' => 'X\Y',
                'template'     => 'V::tpl.phtml',
                'view_model'   => 'V\M',
                'mode'         => 'modal',
            ],
        ], $reg->all());
        $this->assertSame(['cms_block'], $reg->aliases());
        $this->assertTrue($reg->has('cms_block'));
        $this->assertFalse($reg->has('catalog_product'));
    }

    public function testGetReturnsNullForUnknownAlias(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, []);
        $this->assertNull($reg->get('nonexistent'));
    }

    public function testSanitizeDropsEntriesMissingTemplateOrViewModel(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'good' => [
                'helper_block' => 'X\Y',
                'template'     => 'V::tpl.phtml',
                'view_model'   => 'V\M',
            ],
            'no_template' => [
                'helper_block' => 'X\Y',
                'view_model'   => 'V\M',
            ],
            'no_view_model' => [
                'helper_block' => 'X\Y',
                'template'     => 'V::tpl.phtml',
            ],
            'malformed-scalar',
            '' => [
                'template' => 'V::x.phtml',
                'view_model' => 'V\M',
            ],
        ]);

        $this->assertSame(['good'], $reg->aliases());
    }

    public function testHelperBlockIsOptional(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'conditions' => [
                'helper_block' => '',
                'template'     => 'V::conditions.phtml',
                'view_model'   => 'V\C',
                'mode'         => 'inline',
            ],
        ]);

        $this->assertTrue($reg->has('conditions'));
        $this->assertSame('generic', $reg->aliasForHelperBlock(''));
    }

    public function testModeSplitsModalAndInline(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'no_mode_specified' => [
                'helper_block' => 'X',
                'template'     => 'V::a.phtml',
                'view_model'   => 'V\A',
            ],
            'unknown_mode' => [
                'helper_block' => 'Y',
                'template'     => 'V::b.phtml',
                'view_model'   => 'V\B',
                'mode'         => 'something_weird',
            ],
            'inline_chooser' => [
                'helper_block' => '',
                'template'     => 'V::c.phtml',
                'view_model'   => 'V\C',
                'mode'         => 'inline',
            ],
        ]);

        $this->assertSame('modal',  $reg->get('no_mode_specified')['mode']);
        $this->assertSame('modal',  $reg->get('unknown_mode')['mode']);
        $this->assertSame('inline', $reg->get('inline_chooser')['mode']);

        $this->assertSame(['no_mode_specified', 'unknown_mode'], array_keys($reg->modal()));
        $this->assertSame(['inline_chooser'],                    array_keys($reg->inline()));
    }

    public function testResolveViewModelDelegatesToTMap(): void
    {
        $instance = $this->createMock(WidgetChooserViewModelInterface::class);
        $this->tmap->method('offsetExists')->with('a')->willReturn(true);
        $this->tmap->method('offsetGet')->with('a')->willReturn($instance);

        $reg = new WidgetChooserRegistry($this->tmapFactory, [
            'a' => [
                'helper_block' => 'X',
                'template'     => 'V::a.phtml',
                'view_model'   => 'V\A',
            ],
        ]);

        $this->assertSame($instance, $reg->resolveViewModel('a'));
    }

    public function testResolveViewModelThrowsForUnknownAlias(): void
    {
        $reg = new WidgetChooserRegistry($this->tmapFactory, []);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/No widget chooser is registered for alias "bogus"/');
        $reg->resolveViewModel('bogus');
    }

    public function testTMapFactoryReceivesCorrectShape(): void
    {
        $this->tmapFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($args) {
                $this->assertSame(WidgetChooserViewModelInterface::class, $args['type']);
                $this->assertSame([
                    'a' => 'V\A',
                    'b' => 'V\B',
                ], $args['array']);
                return true;
            }))
            ->willReturn($this->tmap);

        new WidgetChooserRegistry($this->tmapFactory, [
            'a' => ['helper_block' => 'X', 'template' => 'V::a.phtml', 'view_model' => 'V\A'],
            'b' => ['helper_block' => 'Y', 'template' => 'V::b.phtml', 'view_model' => 'V\B'],
        ]);
    }
}
