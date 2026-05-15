<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Eav;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Eav\LayoutAttributeCollector;

class LayoutAttributeCollectorTest extends TestCase
{
    public function testCollectFieldCodesWalksNestedChildren(): void
    {
        $collector = new LayoutAttributeCollector();

        $tree = [
            [
                'type' => 'row',
                'children' => [
                    [
                        'type' => 'section',
                        'fields' => ['name', 'sku'],
                    ],
                    [
                        'type' => 'tabs',
                        'children' => [
                            ['type' => 'tab', 'fields' => ['price', 'special_price']],
                        ],
                    ],
                ],
            ],
        ];

        $codes = $collector->collectFieldCodes($tree);

        $this->assertSame(['name', 'sku', 'price', 'special_price'], $codes);
    }

    public function testCollectGroupCodesWalksNestedChildren(): void
    {
        $collector = new LayoutAttributeCollector();

        $tree = [
            [
                'type' => 'section',
                'groups' => ['general'],
                'children' => [
                    ['type' => 'section', 'groups' => ['prices', 'inventory']],
                ],
            ],
        ];

        $this->assertSame(
            ['general', 'prices', 'inventory'],
            $collector->collectGroupCodes($tree)
        );
    }

    public function testIgnoresNonArrayNodes(): void
    {
        $collector = new LayoutAttributeCollector();

        $tree = [
            'not-an-array',
            null,
            ['type' => 'section', 'fields' => ['sku']],
        ];

        $this->assertSame(['sku'], $collector->collectFieldCodes($tree));
        $this->assertSame([], $collector->collectGroupCodes($tree));
    }
}
