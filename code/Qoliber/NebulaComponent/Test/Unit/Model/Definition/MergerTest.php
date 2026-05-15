<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Definition;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Qoliber\NebulaComponent\Model\Definition\Merger;

class MergerTest extends TestCase
{
    private Merger $merger;

    protected function setUp(): void
    {
        $this->merger = new Merger(new NullLogger());
    }

    // =========================================================================
    // Basic merge tests
    // =========================================================================

    public function testMergeEmptyDefinitions(): void
    {
        $this->assertSame([], $this->merger->merge([]));
    }

    public function testMergeSingleDefinition(): void
    {
        $def = ['id' => 'test', 'settings' => ['title' => 'Test']];
        $this->assertSame($def, $this->merger->merge([$def]));
    }

    public function testMergeScalarOverride(): void
    {
        $base = ['title' => 'Original'];
        $overlay = ['title' => 'Updated'];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('Updated', $result['title']);
    }

    public function testMergeNewKeyAdded(): void
    {
        $base = ['title' => 'Test'];
        $overlay = ['description' => 'New field'];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('Test', $result['title']);
        $this->assertSame('New field', $result['description']);
    }

    // =========================================================================
    // Associative deep merge
    // =========================================================================

    public function testDeepMergeAssociativeArrays(): void
    {
        $base = ['settings' => ['pageSize' => 20, 'title' => 'Grid']];
        $overlay = ['settings' => ['pageSize' => 50]];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame(50, $result['settings']['pageSize']);
        $this->assertSame('Grid', $result['settings']['title']);
    }

    public function testDeepMergeColumns(): void
    {
        $base = [
            'columns' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'position' => 10],
                'status' => ['label' => 'Status', 'type' => 'badge', 'position' => 20],
            ],
        ];
        $overlay = [
            'columns' => [
                'name' => ['searchable' => true],
                'custom' => ['label' => 'Custom', 'type' => 'text', 'position' => 15],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        // name merged
        $this->assertSame('Name', $result['columns']['name']['label']);
        $this->assertTrue($result['columns']['name']['searchable']);

        // custom added
        $this->assertSame('Custom', $result['columns']['custom']['label']);

        // status untouched
        $this->assertSame('Status', $result['columns']['status']['label']);
    }

    // =========================================================================
    // $remove support
    // =========================================================================

    public function testRemoveKey(): void
    {
        $base = ['columns' => ['name' => ['label' => 'Name'], 'old' => ['label' => 'Old']]];
        $overlay = ['columns' => ['old' => ['$remove' => true]]];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertArrayHasKey('name', $result['columns']);
        $this->assertArrayNotHasKey('old', $result['columns']);
    }

    // =========================================================================
    // Sequential array replacement (no IDs)
    // =========================================================================

    public function testSequentialArrayReplacedWithoutIds(): void
    {
        $base = ['pageSizes' => [10, 20, 50]];
        $overlay = ['pageSizes' => [20, 50, 100]];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame([20, 50, 100], $result['pageSizes']);
    }

    // =========================================================================
    // ID-based merge for layout arrays
    // =========================================================================

    public function testIdBasedMergeAddsNewNode(): void
    {
        $base = [
            'layout' => [
                ['id' => 'row1', 'type' => 'row', 'children' => []],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'row2', 'type' => 'row', 'label' => 'New Row'],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertCount(2, $result['layout']);
        $this->assertSame('row1', $result['layout'][0]['id']);
        $this->assertSame('row2', $result['layout'][1]['id']);
        $this->assertSame('New Row', $result['layout'][1]['label']);
    }

    public function testIdBasedMergeUpdatesExistingNode(): void
    {
        $base = [
            'layout' => [
                ['id' => 'section1', 'type' => 'section', 'label' => 'Original', 'fields' => ['name']],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'section1', 'label' => 'Updated'],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertCount(1, $result['layout']);
        $this->assertSame('Updated', $result['layout'][0]['label']);
        $this->assertSame('section', $result['layout'][0]['type']);
        $this->assertSame(['name'], $result['layout'][0]['fields']);
    }

    public function testIdBasedMergeRemovesNode(): void
    {
        $base = [
            'layout' => [
                ['id' => 'section1', 'type' => 'section'],
                ['id' => 'section2', 'type' => 'section'],
                ['id' => 'section3', 'type' => 'section'],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'section2', '$remove' => true],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertCount(2, $result['layout']);
        $ids = array_column($result['layout'], 'id');
        $this->assertContains('section1', $ids);
        $this->assertContains('section3', $ids);
        $this->assertNotContains('section2', $ids);
    }

    public function testIdBasedMergeWithNestedChildren(): void
    {
        $base = [
            'layout' => [
                [
                    'id' => 'main_row',
                    'type' => 'row',
                    'children' => [
                        ['id' => 'left_col', 'type' => 'column', 'width' => '1/2', 'children' => [
                            ['id' => 'details', 'type' => 'section', 'label' => 'Details', 'fields' => ['name', 'sku']],
                        ]],
                        ['id' => 'right_col', 'type' => 'column', 'width' => '1/2', 'children' => [
                            ['id' => 'pricing', 'type' => 'section', 'label' => 'Pricing', 'fields' => ['price']],
                        ]],
                    ],
                ],
            ],
        ];
        $overlay = [
            'layout' => [
                [
                    'id' => 'main_row',
                    'children' => [
                        ['id' => 'left_col', 'children' => [
                            ['id' => 'vendor_section', 'type' => 'section', 'label' => 'Vendor Data', 'fields' => ['vendor_code'], 'position' => 25],
                        ]],
                    ],
                ],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        // Main row still exists
        $this->assertSame('main_row', $result['layout'][0]['id']);

        // Left col has 2 children now (details + vendor_section)
        $leftCol = $result['layout'][0]['children'][0];
        $this->assertSame('left_col', $leftCol['id']);
        $this->assertCount(2, $leftCol['children']);

        $childIds = array_column($leftCol['children'], 'id');
        $this->assertContains('details', $childIds);
        $this->assertContains('vendor_section', $childIds);

        // Right col untouched
        $rightCol = $result['layout'][0]['children'][1];
        $this->assertSame('right_col', $rightCol['id']);
        $this->assertSame('Pricing', $rightCol['children'][0]['label']);
    }

    public function testIdBasedMergeWithPosition(): void
    {
        $base = [
            'layout' => [
                ['id' => 'a', 'label' => 'A', 'position' => 30],
                ['id' => 'b', 'label' => 'B', 'position' => 10],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'c', 'label' => 'C', 'position' => 20],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertCount(3, $result['layout']);
        // Sorted by position: B(10), C(20), A(30)
        $this->assertSame('B', $result['layout'][0]['label']);
        $this->assertSame('C', $result['layout'][1]['label']);
        $this->assertSame('A', $result['layout'][2]['label']);
    }

    // =========================================================================
    // Condition system (just passes through, no merge logic needed)
    // =========================================================================

    public function testConditionPreservedOnMerge(): void
    {
        $base = [
            'layout' => [
                ['id' => 'pricing', 'type' => 'section', 'condition' => ['type_id' => ['simple', 'virtual']]],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'pricing', 'label' => 'Updated Pricing'],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame(['type_id' => ['simple', 'virtual']], $result['layout'][0]['condition']);
        $this->assertSame('Updated Pricing', $result['layout'][0]['label']);
    }

    // =========================================================================
    // Real-world scenario: third-party module adds to product form
    // =========================================================================

    public function testThirdPartyAddsColumnToGrid(): void
    {
        $base = [
            'columns' => [
                'name' => ['label' => 'Name', 'position' => 10],
                'sku' => ['label' => 'SKU', 'position' => 20],
            ],
        ];
        $vendorOverlay = [
            'columns' => [
                'custom_field' => ['label' => 'Custom', 'type' => 'text', 'position' => 15],
                'sku' => ['searchable' => true],
            ],
        ];
        $result = $this->merger->merge([$base, $vendorOverlay]);

        $this->assertCount(3, $result['columns']);
        $this->assertArrayHasKey('custom_field', $result['columns']);
        $this->assertTrue($result['columns']['sku']['searchable']);
    }

    public function testThirdPartyAddsSectionToProductForm(): void
    {
        $base = [
            'layout' => [
                [
                    'id' => 'main_row',
                    'type' => 'row',
                    'children' => [
                        [
                            'id' => 'left_col',
                            'type' => 'column',
                            'width' => '1/2',
                            'children' => [
                                ['id' => 'product_details', 'type' => 'section', 'label' => 'Details'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Vendor module adds a section to left column
        $vendorOverlay = [
            'layout' => [
                [
                    'id' => 'main_row',
                    'children' => [
                        [
                            'id' => 'left_col',
                            'children' => [
                                ['id' => 'vendor_warranty', 'type' => 'section', 'label' => 'Warranty Info', 'renderer' => 'snippet.warranty'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->merger->merge([$base, $vendorOverlay]);

        $leftCol = $result['layout'][0]['children'][0];
        $this->assertCount(2, $leftCol['children']);

        $ids = array_column($leftCol['children'], 'id');
        $this->assertContains('product_details', $ids);
        $this->assertContains('vendor_warranty', $ids);
    }

    public function testMultipleOverlaysInSequence(): void
    {
        $base = ['columns' => ['a' => ['label' => 'A']]];
        $overlay1 = ['columns' => ['b' => ['label' => 'B']]];
        $overlay2 = ['columns' => ['a' => ['note' => 'Updated'], 'c' => ['label' => 'C']]];
        $overlay3 = ['columns' => ['b' => ['$remove' => true]]];

        $result = $this->merger->merge([$base, $overlay1, $overlay2, $overlay3]);

        $this->assertCount(2, $result['columns']);
        $this->assertSame('A', $result['columns']['a']['label']);
        $this->assertSame('Updated', $result['columns']['a']['note']);
        $this->assertSame('C', $result['columns']['c']['label']);
        $this->assertArrayNotHasKey('b', $result['columns']);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testMixedIdAndNoIdItems(): void
    {
        $base = [
            'layout' => [
                ['id' => 'section1', 'label' => 'Section 1'],
                ['type' => 'divider'], // no id
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'section2', 'label' => 'Section 2'],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        // All 3 items should be present
        $this->assertCount(3, $result['layout']);
    }

    public function testEmptyOverlayDoesNothing(): void
    {
        $base = ['title' => 'Test', 'columns' => ['a' => ['label' => 'A']]];
        $result = $this->merger->merge([$base, []]);

        $this->assertSame($base, $result);
    }

    public function testPositionSortingOnAssociativeArrays(): void
    {
        $def = [
            'columns' => [
                'z' => ['label' => 'Z', 'position' => 30],
                'a' => ['label' => 'A', 'position' => 10],
                'm' => ['label' => 'M', 'position' => 20],
            ],
        ];
        $result = $this->merger->merge([$def]);

        $keys = array_keys($result['columns']);
        $this->assertSame(['a', 'm', 'z'], $keys);
    }

    // =========================================================================
    // Additional edge cases
    // =========================================================================

    public function testMergeNullValueInOverlay(): void
    {
        $base = ['settings' => ['title' => 'Grid', 'description' => 'Products']];
        $overlay = ['settings' => ['description' => null]];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('Grid', $result['settings']['title']);
        $this->assertNull($result['settings']['description']);
    }

    public function testMergeRemoveNestedKey(): void
    {
        $base = [
            'settings' => [
                'pagination' => ['enabled' => true, 'pageSize' => 20],
                'title' => 'Grid',
            ],
        ];
        $overlay = [
            'settings' => [
                'pagination' => ['$remove' => true],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('Grid', $result['settings']['title']);
        $this->assertArrayNotHasKey('pagination', $result['settings']);
    }

    public function testMergeDeeplyNestedFiveLevels(): void
    {
        $base = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => 'original',
                        ],
                        'sibling' => 'keep',
                    ],
                ],
            ],
        ];
        $overlay = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => 'updated',
                            'newKey' => 'added',
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('updated', $result['level1']['level2']['level3']['level4']['level5']);
        $this->assertSame('added', $result['level1']['level2']['level3']['level4']['newKey']);
        $this->assertSame('keep', $result['level1']['level2']['level3']['sibling']);
    }

    public function testMergePositionAsString(): void
    {
        $base = [
            'layout' => [
                ['id' => 'a', 'label' => 'A', 'position' => '30'],
                ['id' => 'b', 'label' => 'B', 'position' => '10'],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'c', 'label' => 'C', 'position' => '20'],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertCount(3, $result['layout']);
        $this->assertSame('B', $result['layout'][0]['label']);
        $this->assertSame('C', $result['layout'][1]['label']);
        $this->assertSame('A', $result['layout'][2]['label']);
    }

    public function testMergeConflictingPositions(): void
    {
        $base = [
            'layout' => [
                ['id' => 'a', 'label' => 'A', 'position' => 10],
                ['id' => 'b', 'label' => 'B', 'position' => 10],
            ],
        ];
        $result = $this->merger->merge([$base]);

        $this->assertCount(2, $result['layout']);
        $ids = array_column($result['layout'], 'id');
        $this->assertContains('a', $ids);
        $this->assertContains('b', $ids);
    }

    public function testMergeIdBasedArrayWithDuplicateIds(): void
    {
        $base = [
            'layout' => [
                ['id' => 'row1', 'label' => 'First'],
                ['id' => 'row1', 'label' => 'Second'],
            ],
        ];
        $result = $this->merger->merge([$base]);

        // When base has duplicate IDs, the last one should win during indexing
        $ids = array_column($result['layout'], 'id');
        $row1Items = array_filter($result['layout'], fn (array $item) => ($item['id'] ?? null) === 'row1');
        $lastRow1 = end($row1Items);
        $this->assertSame('Second', $lastRow1['label']);
    }

    public function testMergeEmptyOverlayDoesNothing(): void
    {
        $base = [
            'settings' => ['title' => 'Grid'],
            'columns' => ['name' => ['label' => 'Name']],
            'layout' => [['id' => 'row1', 'type' => 'row']],
        ];
        $result = $this->merger->merge([$base, []]);

        $this->assertSame('Grid', $result['settings']['title']);
        $this->assertSame('Name', $result['columns']['name']['label']);
        $this->assertCount(1, $result['layout']);
    }

    public function testMergeOverlayAddsToEmptyBase(): void
    {
        $base = [];
        $overlay = [
            'settings' => ['title' => 'New Grid'],
            'columns' => ['name' => ['label' => 'Name', 'position' => 10]],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame('New Grid', $result['settings']['title']);
        $this->assertSame('Name', $result['columns']['name']['label']);
    }

    public function testMergeRemoveOnNonExistentKey(): void
    {
        $base = ['columns' => ['name' => ['label' => 'Name']]];
        $overlay = ['columns' => ['nonexistent' => ['$remove' => true]]];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertArrayHasKey('name', $result['columns']);
        $this->assertArrayNotHasKey('nonexistent', $result['columns']);
    }

    public function testMergeConditionArrayPreservedOnOverride(): void
    {
        $base = [
            'layout' => [
                ['id' => 'pricing', 'type' => 'section', 'condition' => ['type_id' => ['simple', 'virtual']]],
            ],
        ];
        $overlay = [
            'layout' => [
                ['id' => 'pricing', 'condition' => ['type_id' => ['configurable', 'bundle']]],
            ],
        ];
        $result = $this->merger->merge([$base, $overlay]);

        $this->assertSame(['type_id' => ['configurable', 'bundle']], $result['layout'][0]['condition']);
        $this->assertSame('section', $result['layout'][0]['type']);
    }
}
