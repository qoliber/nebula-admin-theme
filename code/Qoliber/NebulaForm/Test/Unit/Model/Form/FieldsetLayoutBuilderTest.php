<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Form\FieldsetLayoutBuilder;

class FieldsetLayoutBuilderTest extends TestCase
{
    public function testSingleColumnLayoutReturnsFlatList(): void
    {
        $builder = new FieldsetLayoutBuilder();

        $result = $builder->buildFromFieldsets(
            ['a' => ['label' => 'A'], 'b' => ['label' => 'B']],
            'single'
        );

        $this->assertSame([['label' => 'A'], ['label' => 'B']], $result);
    }

    public function testTwoColumnWithoutColumnAssignmentProducesOnlyFullFieldsets(): void
    {
        $builder = new FieldsetLayoutBuilder();

        $result = $builder->buildFromFieldsets(
            ['a' => ['label' => 'A'], 'b' => ['label' => 'B']],
            'two-column'
        );

        $this->assertSame([['label' => 'A'], ['label' => 'B']], $result);
    }

    public function testTwoColumnLayoutEmitsRowMarkers(): void
    {
        $builder = new FieldsetLayoutBuilder();

        $result = $builder->buildFromFieldsets(
            [
                'main' => ['label' => 'Main', 'column' => 'left'],
                'sidebar' => ['label' => 'Sidebar', 'column' => 'right'],
                'footer' => ['label' => 'Footer'],
            ],
            'two-column'
        );

        $markers = array_values(array_filter(array_map(
            static fn (array $n): ?string => $n['__marker'] ?? null,
            $result
        )));

        $this->assertSame(['row_start', 'col_break', 'row_end'], $markers);
    }

    public function testThreeColumnLayoutEmitsRowMarkers(): void
    {
        $builder = new FieldsetLayoutBuilder();

        $result = $builder->buildFromFieldsets(
            [
                'left' => ['label' => 'L', 'column' => 'left'],
                'right' => ['label' => 'R', 'column' => 'right'],
                'third' => ['label' => 'T', 'column' => 'third'],
            ],
            'two-column'
        );

        $rowStart = $result[0];
        $this->assertSame('row_start', $rowStart['__marker']);
        $this->assertSame(3, $rowStart['__cols']);

        $markers = array_values(array_filter(array_map(
            static fn (array $n): ?string => $n['__marker'] ?? null,
            $result
        )));

        $this->assertSame(['row_start', 'col_break', 'col_break', 'row_end'], $markers);
    }
}
