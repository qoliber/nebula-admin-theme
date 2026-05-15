<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Form\LayoutNodeFlattener;

class LayoutNodeFlattenerTest extends TestCase
{
    public function testFlattenReturnsEmptyWhenTreeIsEmpty(): void
    {
        $flattener = new LayoutNodeFlattener();

        $this->assertSame([], $flattener->flatten([], []));
    }

    public function testRowWithTwoColumnsEmitsRowMarkersAndSections(): void
    {
        $flattener = new LayoutNodeFlattener();

        $layout = [[
            'type' => 'row',
            'children' => [
                [
                    'type' => 'column',
                    'children' => [
                        ['type' => 'section', 'id' => 'main', 'label' => 'Main', 'fields' => ['sku', 'name']],
                    ],
                ],
                [
                    'type' => 'column',
                    'children' => [
                        ['type' => 'section', 'id' => 'sidebar', 'label' => 'Sidebar', 'fields' => []],
                    ],
                ],
            ],
        ]];

        $allFields = [
            'sku' => ['position' => 10],
            'name' => ['position' => 20],
        ];

        $nodes = $flattener->flatten($layout, $allFields);

        $markers = [];
        $sectionIds = [];
        foreach ($nodes as $node) {
            if (isset($node['__marker'])) {
                $markers[] = $node['__marker'];
            } elseif (isset($node['id'])) {
                $sectionIds[] = $node['id'];
            }
        }

        $this->assertSame(['row_start', 'col_break', 'row_end'], $markers);
        $this->assertSame(['main', 'sidebar'], $sectionIds);
    }

    public function testSectionFieldsAreSortedByPositionAsc(): void
    {
        $flattener = new LayoutNodeFlattener();

        $layout = [[
            'type' => 'section',
            'id' => 's',
            'label' => 'S',
            'fields' => ['name', 'sku'],
        ]];

        $allFields = [
            'name' => ['position' => 20],
            'sku' => ['position' => 10],
        ];

        $nodes = $flattener->flatten($layout, $allFields);

        $this->assertSame(['sku', 'name'], array_keys($nodes[0]['fields']));
    }

    public function testSectionCollectsChildSnippetRenderers(): void
    {
        $flattener = new LayoutNodeFlattener();

        $layout = [[
            'type' => 'section',
            'id' => 'sidebar',
            'label' => 'Sidebar',
            'children' => [
                ['type' => 'snippet', 'renderer' => 'snippet.status_badge'],
                ['type' => 'fields', 'fields' => ['sku']],
            ],
        ]];

        $allFields = ['sku' => ['position' => 0]];
        $nodes = $flattener->flatten($layout, $allFields);

        $this->assertContains('snippet.status_badge', $nodes[0]['renderers']);
        $this->assertArrayHasKey('sku', $nodes[0]['fields']);
    }
}
