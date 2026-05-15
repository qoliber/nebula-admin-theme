<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\Model\Source;

use Magento\Store\Model\System\Store as SystemStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Model\Source\StoreView;

class StoreViewTest extends TestCase
{
    private SystemStore&MockObject $systemStore;

    private StoreView $source;

    protected function setUp(): void
    {
        $this->systemStore = $this->createMock(SystemStore::class);
        $this->source = new StoreView($this->systemStore);
    }

    public function testReturnsEmptyArrayWhenNoStores(): void
    {
        $this->systemStore->method('getStoreValuesForForm')->willReturn([]);
        $this->assertSame([], $this->source->toOptionArray());
    }

    public function testSingleWebsiteSingleGroupCompactsTheLoneGroupHeader(): void
    {
        $nbsp = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0";
        $this->systemStore->method('getStoreValuesForForm')->willReturn([
            ['label' => 'All Store Views', 'value' => 0],
            ['label' => 'Main Website', 'value' => []],
            [
                'label' => $nbsp . 'Main Website Store',
                'value' => [
                    ['label' => $nbsp . 'Default Store View', 'value' => '1'],
                    ['label' => $nbsp . 'Dutch Store View', 'value' => '2'],
                    ['label' => $nbsp . 'German Store View', 'value' => '3'],
                ],
            ],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertCount(5, $rows);
        $this->assertSame(['value' => '0', 'label' => 'All Store Views', 'depth' => 0, 'all' => true], $rows[0]);
        $this->assertSame(['value' => null, 'label' => 'Main Website', 'depth' => 0, 'group' => true], $rows[1]);
        $this->assertSame(['value' => '1', 'label' => 'Default Store View', 'depth' => 1], $rows[2]);
        $this->assertSame(['value' => '2', 'label' => 'Dutch Store View', 'depth' => 1], $rows[3]);
        $this->assertSame(['value' => '3', 'label' => 'German Store View', 'depth' => 1], $rows[4]);
    }

    public function testMultipleGroupsKeepsTheGroupHeaders(): void
    {
        $nbsp = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0";
        $this->systemStore->method('getStoreValuesForForm')->willReturn([
            ['label' => 'All Store Views', 'value' => 0],
            ['label' => 'Main Website', 'value' => []],
            [
                'label' => $nbsp . 'Group A',
                'value' => [
                    ['label' => $nbsp . 'Store A1', 'value' => '1'],
                ],
            ],
            [
                'label' => $nbsp . 'Group B',
                'value' => [
                    ['label' => $nbsp . 'Store B1', 'value' => '2'],
                ],
            ],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertCount(6, $rows);
        $this->assertSame(['value' => '0', 'label' => 'All Store Views', 'depth' => 0, 'all' => true], $rows[0]);
        $this->assertSame(['value' => null, 'label' => 'Main Website', 'depth' => 0, 'group' => true], $rows[1]);
        $this->assertSame(['value' => null, 'label' => 'Group A', 'depth' => 1, 'group' => true], $rows[2]);
        $this->assertSame(['value' => '1', 'label' => 'Store A1', 'depth' => 2], $rows[3]);
        $this->assertSame(['value' => null, 'label' => 'Group B', 'depth' => 1, 'group' => true], $rows[4]);
        $this->assertSame(['value' => '2', 'label' => 'Store B1', 'depth' => 2], $rows[5]);
    }

    public function testMultipleWebsitesAreEachTheirOwnHeader(): void
    {
        $nbsp = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0";
        $this->systemStore->method('getStoreValuesForForm')->willReturn([
            ['label' => 'All Store Views', 'value' => 0],
            ['label' => 'Website Alpha', 'value' => []],
            [
                'label' => $nbsp . 'Alpha Group',
                'value' => [
                    ['label' => $nbsp . 'Alpha Store', 'value' => '1'],
                ],
            ],
            ['label' => 'Website Beta', 'value' => []],
            [
                'label' => $nbsp . 'Beta Group',
                'value' => [
                    ['label' => $nbsp . 'Beta Store', 'value' => '2'],
                ],
            ],
        ]);

        $rows = $this->source->toOptionArray();

        $this->assertCount(7, $rows);
        $this->assertSame('All Store Views', $rows[0]['label']);
        $this->assertSame('Website Alpha', $rows[1]['label']);
        $this->assertTrue($rows[1]['group']);
        $this->assertSame('Alpha Group', $rows[2]['label']);
        $this->assertSame(1, $rows[2]['depth']);
        $this->assertSame('Alpha Store', $rows[3]['label']);
        $this->assertSame('Website Beta', $rows[4]['label']);
        $this->assertSame('Beta Group', $rows[5]['label']);
        $this->assertSame('Beta Store', $rows[6]['label']);
    }
}
