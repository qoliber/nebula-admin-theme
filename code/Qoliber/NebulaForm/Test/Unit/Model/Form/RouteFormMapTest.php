<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaForm\Model\Form\RouteFormMap;

class RouteFormMapTest extends TestCase
{
    /**
     * Build a RouteFormMap whose discovery yields the given form ids and whose
     * resolver returns the given merged definition per id.
     *
     * @param array<string, array<string, mixed>> $definitions form_id => merged definition
     */
    private function createMap(array $definitions): RouteFormMap
    {
        $formIds = array_keys($definitions);

        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getNames')->willReturn(['Qoliber_NebulaForm']);

        $dirReader = $this->createMock(Reader::class);
        $dirReader->method('getModuleDir')->willReturn('/modules/Qoliber/NebulaForm');

        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('readDirectory')->willReturn(
            array_map(static fn (string $id): string => $id . '.json', $formIds)
        );

        $resolver = $this->createMock(DefinitionResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(
            function (string $type, string $id) use ($definitions): array {
                self::assertSame('form', $type);
                return $definitions[$id] ?? [];
            }
        );

        return new RouteFormMap(
            $resolver,
            $dirReader,
            $fileDriver,
            $moduleList,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testStringRouteMapsToFormWithReplaces(): void
    {
        $map = $this->createMap([
            'cms_block_edit' => [
                'id' => 'cms_block_edit',
                'route' => 'cms_block_edit',
                'replaces' => 'cms_block_form',
            ],
        ]);

        $this->assertSame(
            ['form_id' => 'cms_block_edit', 'replaces' => ['cms_block_form']],
            $map->find('cms_block_edit')
        );
    }

    public function testArrayRouteMapsBothFullActionNamesToSameForm(): void
    {
        $map = $this->createMap([
            'product_attribute_edit' => [
                'id' => 'product_attribute_edit',
                'route' => ['catalog_product_attribute_edit', 'catalog_product_attribute_new'],
                'replaces' => 'attribute_edit_tabs',
            ],
        ]);

        $expected = ['form_id' => 'product_attribute_edit', 'replaces' => ['attribute_edit_tabs']];

        $this->assertSame($expected, $map->find('catalog_product_attribute_edit'));
        $this->assertSame($expected, $map->find('catalog_product_attribute_new'));
    }

    public function testArrayReplacesIsNormalisedToList(): void
    {
        $map = $this->createMap([
            'admin_user_edit' => [
                'id' => 'admin_user_edit',
                'route' => 'adminhtml_user_edit',
                'replaces' => ['adminhtml.user.edit.tabs', 'adminhtml.user.edit', 'adminhtml.user.roles.grid.js'],
            ],
        ]);

        $this->assertSame(
            [
                'form_id' => 'admin_user_edit',
                'replaces' => ['adminhtml.user.edit.tabs', 'adminhtml.user.edit', 'adminhtml.user.roles.grid.js'],
            ],
            $map->find('adminhtml_user_edit')
        );
    }

    public function testArrayReplacesDropsEmptyAndDuplicateEntries(): void
    {
        $map = $this->createMap([
            'tax_rule_edit' => [
                'id' => 'tax_rule_edit',
                'route' => 'tax_rule_edit',
                'replaces' => ['tax-rate-form', '', 'tax-rate-form', 'content.tax_rule_edit'],
            ],
        ]);

        $this->assertSame(
            ['form_id' => 'tax_rule_edit', 'replaces' => ['tax-rate-form', 'content.tax_rule_edit']],
            $map->find('tax_rule_edit')
        );
    }

    public function testRouteWithoutReplacesYieldsEmptyReplaces(): void
    {
        $map = $this->createMap([
            'store_group_edit' => [
                'id' => 'store_group_edit',
                'route' => 'adminhtml_system_store_editgroup',
            ],
        ]);

        $this->assertSame(
            ['form_id' => 'store_group_edit', 'replaces' => []],
            $map->find('adminhtml_system_store_editgroup')
        );
    }

    public function testUnmappedRouteReturnsNull(): void
    {
        $map = $this->createMap([
            'cms_block_edit' => [
                'id' => 'cms_block_edit',
                'route' => 'cms_block_edit',
                'replaces' => 'cms_block_form',
            ],
        ]);

        $this->assertNull($map->find('catalog_product_edit'));
    }

    public function testFormDefinitionWithoutRouteIsSkipped(): void
    {
        $map = $this->createMap([
            'catalog_product_edit_tabs' => [
                'id' => 'catalog_product_edit_tabs',
                // no route key — not a mountable page form
            ],
            'cms_block_edit' => [
                'id' => 'cms_block_edit',
                'route' => 'cms_block_edit',
                'replaces' => 'cms_block_form',
            ],
        ]);

        $this->assertSame(
            ['cms_block_edit' => ['form_id' => 'cms_block_edit', 'replaces' => ['cms_block_form']]],
            $map->getMap()
        );
    }

    public function testEmptyStringReplacesNormalisedToEmptyList(): void
    {
        $map = $this->createMap([
            'tax_rule_edit' => [
                'id' => 'tax_rule_edit',
                'route' => 'tax_rule_edit',
                'replaces' => '',
            ],
        ]);

        $this->assertSame(
            ['form_id' => 'tax_rule_edit', 'replaces' => []],
            $map->find('tax_rule_edit')
        );
    }

    public function testDotNotationReplacesPassedThroughVerbatim(): void
    {
        $map = $this->createMap([
            'admin_user_edit' => [
                'id' => 'admin_user_edit',
                'route' => 'adminhtml_user_edit',
                'replaces' => 'adminhtml.user.edit.tabs',
            ],
        ]);

        $this->assertSame(
            ['form_id' => 'admin_user_edit', 'replaces' => ['adminhtml.user.edit.tabs']],
            $map->find('adminhtml_user_edit')
        );
    }

    public function testMapIsMemoisedAcrossCalls(): void
    {
        $resolver = $this->createMock(DefinitionResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->willReturn(['id' => 'cms_block_edit', 'route' => 'cms_block_edit']);

        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getNames')->willReturn(['Qoliber_NebulaForm']);

        $dirReader = $this->createMock(Reader::class);
        $dirReader->method('getModuleDir')->willReturn('/modules/Qoliber/NebulaForm');

        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('readDirectory')->willReturn(['cms_block_edit.json']);

        $map = new RouteFormMap(
            $resolver,
            $dirReader,
            $fileDriver,
            $moduleList,
            $this->createMock(LoggerInterface::class)
        );

        $map->getMap();
        $map->getMap();
        $map->find('cms_block_edit');
    }
}
