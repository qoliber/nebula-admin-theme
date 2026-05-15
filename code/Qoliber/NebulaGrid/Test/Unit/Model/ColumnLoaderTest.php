<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\OptionSourceResolver;
use Qoliber\NebulaGrid\Model\ColumnLoader;

class ColumnLoaderTest extends TestCase
{
    public function testColumnsAreSortedByPosition(): void
    {
        $loader = new ColumnLoader($this->createMock(OptionSourceResolver::class));

        $columns = $loader->load([
            'columns' => [
                'c' => ['position' => 30, 'label' => 'C'],
                'a' => ['position' => 10, 'label' => 'A'],
                'b' => ['position' => 20, 'label' => 'B'],
            ],
        ]);

        $this->assertSame(['a', 'b', 'c'], array_keys($columns));
    }

    public function testColumnsWithMissingPositionSortAsZero(): void
    {
        $loader = new ColumnLoader($this->createMock(OptionSourceResolver::class));

        $columns = $loader->load([
            'columns' => [
                'a' => ['label' => 'A'],
                'b' => ['position' => 5, 'label' => 'B'],
                'c' => ['position' => -5, 'label' => 'C'],
            ],
        ]);

        $this->assertSame(['c', 'a', 'b'], array_keys($columns));
    }

    public function testFilterOptionsSourceIsResolvedWhenFilterOptionsEmpty(): void
    {
        $resolver = $this->createMock(OptionSourceResolver::class);
        $resolver->expects($this->once())
            ->method('toLabelMap')
            ->with('status.alias')
            ->willReturn(['1' => 'Active', '0' => 'Inactive']);

        $loader = new ColumnLoader($resolver);
        $columns = $loader->load([
            'columns' => [
                'status' => [
                    'position' => 10,
                    'filterOptionsSource' => 'status.alias',
                ],
            ],
        ]);

        $this->assertSame(['1' => 'Active', '0' => 'Inactive'], $columns['status']['filterOptions']);
    }

    public function testFilterOptionsSourceSkippedWhenFilterOptionsAlreadySet(): void
    {
        $resolver = $this->createMock(OptionSourceResolver::class);
        $resolver->expects($this->never())->method('toLabelMap');

        $loader = new ColumnLoader($resolver);
        $columns = $loader->load([
            'columns' => [
                'status' => [
                    'filterOptionsSource' => 'status.alias',
                    'filterOptions' => ['already' => 'set'],
                ],
            ],
        ]);

        $this->assertSame(['already' => 'set'], $columns['status']['filterOptions']);
    }

    public function testReturnsEmptyArrayWhenDefinitionHasNoColumns(): void
    {
        $loader = new ColumnLoader($this->createMock(OptionSourceResolver::class));
        $this->assertSame([], $loader->load([]));
    }
}
