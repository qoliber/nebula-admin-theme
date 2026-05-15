<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Form\AllowedFieldsCollector;

/**
 * Covers the mass-assignment whitelist that Form\Save uses to filter
 * the request payload (P0-4). Each test pins a specific JSON shape we
 * actually ship in code/Qoliber/*\/view/adminhtml/form/.
 */
class AllowedFieldsCollectorTest extends TestCase
{
    public function testCollectsFlatFieldsArray(): void
    {
        $collector = new AllowedFieldsCollector($this->createMock(EavConfig::class));

        $allowed = $collector->collect([
            'layout' => [
                ['type' => 'section', 'fields' => ['name', 'sku', 'price']],
            ],
        ]);

        sort($allowed);
        $this->assertSame(['name', 'price', 'sku'], $allowed);
    }

    public function testWalksNestedChildren(): void
    {
        $collector = new AllowedFieldsCollector($this->createMock(EavConfig::class));

        $allowed = $collector->collect([
            'layout' => [
                [
                    'type' => 'row',
                    'children' => [
                        [
                            'type' => 'column',
                            'children' => [
                                ['type' => 'section', 'fields' => ['name', 'sku']],
                            ],
                        ],
                        [
                            'type' => 'column',
                            'children' => [
                                ['type' => 'section', 'fields' => ['price']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        sort($allowed);
        $this->assertSame(['name', 'price', 'sku'], $allowed);
    }

    public function testIncludesOverrideKeysButSkipsRemoved(): void
    {
        $collector = new AllowedFieldsCollector($this->createMock(EavConfig::class));

        $allowed = $collector->collect([
            'layout' => [
                ['type' => 'section', 'fields' => ['name']],
            ],
            'overrides' => [
                'fields' => [
                    'description' => ['type' => 'wysiwyg'],
                    'news_to_date' => ['$remove' => true],
                ],
            ],
        ]);

        sort($allowed);
        $this->assertSame(['description', 'name'], $allowed);
        $this->assertNotContains('news_to_date', $allowed);
    }

    public function testEavExpandsAllAttributesForEntityType(): void
    {
        $attribute1 = $this->createMock(AbstractAttribute::class);
        $attribute1->method('getAttributeCode')->willReturn('color');
        $attribute2 = $this->createMock(AbstractAttribute::class);
        $attribute2->method('getAttributeCode')->willReturn('size');

        $eav = $this->createMock(EavConfig::class);
        $eav->expects($this->once())
            ->method('getEntityAttributes')
            ->with('catalog_product')
            ->willReturn([$attribute1, $attribute2]);

        $collector = new AllowedFieldsCollector($eav);

        $allowed = $collector->collect([
            'type' => 'eav',
            'entity' => 'catalog_product',
            'layout' => [
                ['type' => 'section', 'fields' => ['name']],
            ],
        ]);

        sort($allowed);
        $this->assertSame(['color', 'name', 'size'], $allowed);
    }

    public function testNonEavFormDoesNotTouchEavConfig(): void
    {
        $eav = $this->createMock(EavConfig::class);
        $eav->expects($this->never())->method('getEntityAttributes');

        $collector = new AllowedFieldsCollector($eav);

        $collector->collect([
            'type' => 'form',
            'layout' => [
                ['type' => 'section', 'fields' => ['title']],
            ],
        ]);
    }

    public function testSwallowsEavConfigFailureGracefully(): void
    {
        $eav = $this->createMock(EavConfig::class);
        $eav->method('getEntityAttributes')
            ->willThrowException(new \RuntimeException('eav table missing'));

        $collector = new AllowedFieldsCollector($eav);

        $allowed = $collector->collect([
            'type' => 'eav',
            'entity' => 'catalog_product',
            'layout' => [
                ['type' => 'section', 'fields' => ['name']],
            ],
        ]);

        $this->assertSame(['name'], $allowed);
    }

    public function testEmptyDefinitionReturnsEmptyArray(): void
    {
        $collector = new AllowedFieldsCollector($this->createMock(EavConfig::class));
        $this->assertSame([], $collector->collect([]));
    }
}
