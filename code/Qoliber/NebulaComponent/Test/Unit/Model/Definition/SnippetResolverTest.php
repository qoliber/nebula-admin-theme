<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Definition;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Model\Definition\Loader;
use Qoliber\NebulaComponent\Model\Definition\Merger;
use Qoliber\NebulaComponent\Model\Definition\SnippetResolver;

class SnippetResolverTest extends TestCase
{
    /** @var \Qoliber\NebulaComponent\Model\Definition\Loader&\PHPUnit\Framework\MockObject\MockObject */
    private Loader&MockObject $loader;

    /** @var \Qoliber\NebulaComponent\Model\Definition\Merger&\PHPUnit\Framework\MockObject\MockObject */
    private Merger&MockObject $merger;

    /** @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private LoggerInterface&MockObject $logger;

    /** @var \Qoliber\NebulaComponent\Model\Definition\SnippetResolver */
    private SnippetResolver $resolver;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(Loader::class);
        $this->merger = $this->createMock(Merger::class);
        $this->logger = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->resolver = new SnippetResolver(
            $this->loader,
            $this->merger,
            $this->logger
        );
    }

    public function testResolveLoadsAndMergesSnippet(): void
    {
        $this->loader->expects($this->once())
            ->method('load')
            ->with('snippet', 'my_snippet')
            ->willReturn([['component' => 'box'], ['component' => 'box2']]);

        $this->merger->expects($this->once())
            ->method('merge')
            ->with([['component' => 'box'], ['component' => 'box2']])
            ->willReturn(['component' => 'box2']);

        $result = $this->resolver->resolve('my_snippet');
        $this->assertSame(['component' => 'box2'], $result);
    }

    public function testResolveLayoutReturnsDefinitionWithoutLayout(): void
    {
        $definition = ['fields' => ['name' => ['label' => 'Name']]];
        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame($definition, $result);
    }

    public function testResolveLayoutWithEmptyLayout(): void
    {
        $definition = ['layout' => []];
        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame(['layout' => []], $result);
    }

    public function testResolveLayoutWalksChildren(): void
    {
        $definition = [
            'layout' => [
                'children' => [
                    ['component' => 'text'],
                    ['component' => 'input'],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('text', $result['layout']['children'][0]['component']);
        $this->assertSame('input', $result['layout']['children'][1]['component']);
    }

    public function testResolveLayoutResolvesUseDirective(): void
    {
        $this->configureResolve('sidebar_layout', ['component' => 'sidebar', 'children' => []]);

        $definition = [
            'layout' => [
                '@use' => 'sidebar_layout',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('sidebar', $result['layout']['component']);
        $this->assertArrayNotHasKey('@use', $result['layout']);
    }

    public function testResolveLayoutMergesUseWithExistingProperties(): void
    {
        $this->configureResolve('base', ['component' => 'box', 'class' => 'default']);

        $definition = [
            'layout' => [
                '@use' => 'base',
                'class' => 'custom',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('box', $result['layout']['component']);
        $this->assertSame('custom', $result['layout']['class']);
    }

    public function testResolveLayoutResolvesRefDirective(): void
    {
        $definition = [
            'fields' => [
                'name' => ['label' => 'Name', 'input' => 'text'],
            ],
            'layout' => [
                '@ref' => 'fields.name',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('Name', $result['layout']['label']);
        $this->assertSame('fields', $result['layout']['type']);
        $this->assertSame('name', $result['layout']['key']);
    }

    public function testResolveLayoutRefMissing(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Unresolved snippet @ref returned as placeholder.', ['ref' => 'fields.missing']);

        $definition = [
            'layout' => [
                '@ref' => 'fields.missing',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame(['@ref' => 'fields.missing'], $result['layout']);
    }

    public function testResolveLayoutRefSinglePart(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Unresolved snippet @ref returned as placeholder.', ['ref' => 'noperiod']);

        $definition = [
            'layout' => [
                '@ref' => 'noperiod',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame(['@ref' => 'noperiod'], $result['layout']);
    }

    public function testResolveLayoutChildStringRef(): void
    {
        $definition = [
            'fields' => [
                'name' => ['label' => 'Name'],
                'email' => ['label' => 'Email'],
            ],
            'layout' => [
                'children' => [
                    '@fields.name',
                    '@fields.email',
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('Name', $result['layout']['children'][0]['label']);
        $this->assertSame('fields', $result['layout']['children'][0]['type']);
        $this->assertSame('Email', $result['layout']['children'][1]['label']);
    }

    public function testResolveLayoutNestedUse(): void
    {
        $this->loader->method('load')->willReturnMap([
            ['snippet', 'outer', [['children' => [['@use' => 'inner']]]]],
            ['snippet', 'inner', [['component' => 'deep']]],
        ]);
        $this->merger->method('merge')->willReturnCallback(fn (array $defs) => $defs[0]);

        $definition = [
            'layout' => [
                '@use' => 'outer',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('deep', $result['layout']['children'][0]['component']);
    }

    public function testResolveLayoutSlots(): void
    {
        $this->configureResolve('with_slots', [
            'component' => 'wrapper',
            'children' => [
                ['slot' => 'main', 'class' => 'main-area'],
                ['slot' => 'sidebar', 'class' => 'side-area'],
            ],
        ]);

        $definition = [
            'layout' => [
                '@use' => 'with_slots',
                'slots' => [
                    'main' => [
                        ['component' => 'form'],
                    ],
                    'sidebar' => [
                        ['component' => 'nav'],
                    ],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('form', $result['layout']['children'][0]['children'][0]['component']);
        $this->assertSame('nav', $result['layout']['children'][1]['children'][0]['component']);
    }

    public function testResolveLayoutSlotsMissing(): void
    {
        $this->configureResolve('with_slots', [
            'component' => 'wrapper',
            'children' => [
                ['slot' => 'main'],
            ],
        ]);

        $definition = [
            'layout' => [
                '@use' => 'with_slots',
                'slots' => [
                    'nonexistent' => [['component' => 'orphan']],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertArrayNotHasKey('children', $result['layout']['children'][0]);
    }

    public function testResolveLayoutSlotsWithRefContent(): void
    {
        $this->configureResolve('slot_snippet', [
            'children' => [
                ['slot' => 'content'],
            ],
        ]);

        $definition = [
            'fields' => [
                'name' => ['label' => 'Name'],
            ],
            'layout' => [
                '@use' => 'slot_snippet',
                'slots' => [
                    'content' => ['@fields.name'],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('Name', $result['layout']['children'][0]['children'][0]['label']);
        $this->assertSame('fields', $result['layout']['children'][0]['children'][0]['type']);
    }

    public function testResolveLayoutSlotsWithNestedUse(): void
    {
        $this->loader->method('load')->willReturnMap([
            ['snippet', 'slot_host', [['children' => [['slot' => 'body']]]]],
            ['snippet', 'inner_widget', [['component' => 'widget']]],
        ]);
        $this->merger->method('merge')->willReturnCallback(fn (array $defs) => $defs[0]);

        $definition = [
            'layout' => [
                '@use' => 'slot_host',
                'slots' => [
                    'body' => [
                        ['@use' => 'inner_widget'],
                    ],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('widget', $result['layout']['children'][0]['children'][0]['component']);
    }

    public function testResolveLayoutDeepNesting(): void
    {
        $definition = [
            'fields' => [
                'deep' => ['label' => 'Deep'],
            ],
            'layout' => [
                'children' => [
                    [
                        'children' => [
                            [
                                'children' => [
                                    '@fields.deep',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $deepChild = $result['layout']['children'][0]['children'][0]['children'][0];
        $this->assertSame('Deep', $deepChild['label']);
        $this->assertSame('fields', $deepChild['type']);
    }

    public function testResolveLayoutMixedChildrenTypes(): void
    {
        $definition = [
            'fields' => [
                'email' => ['label' => 'Email'],
            ],
            'layout' => [
                'children' => [
                    '@fields.email',
                    ['component' => 'divider'],
                ],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('Email', $result['layout']['children'][0]['label']);
        $this->assertSame('divider', $result['layout']['children'][1]['component']);
    }

    public function testResolveLayoutPreservesNonSpecialKeys(): void
    {
        $definition = [
            'layout' => [
                'component' => 'panel',
                'class' => 'my-class',
                'data-id' => '42',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('panel', $result['layout']['component']);
        $this->assertSame('my-class', $result['layout']['class']);
        $this->assertSame('42', $result['layout']['data-id']);
    }

    public function testResolveLayoutNodeWithoutChildren(): void
    {
        $this->configureResolve('no_children', ['component' => 'leaf']);

        $definition = [
            'layout' => [
                '@use' => 'no_children',
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        $this->assertSame('leaf', $result['layout']['component']);
        $this->assertArrayNotHasKey('children', $result['layout']);
    }

    public function testResolveLayoutEmptySlots(): void
    {
        $this->configureResolve('slotted', [
            'children' => [
                ['slot' => 'main'],
            ],
        ]);

        $definition = [
            'layout' => [
                '@use' => 'slotted',
                'slots' => [],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);
        // Empty slots array means no slot resolution happens
        $this->assertArrayNotHasKey('children', $result['layout']['children'][0]);
    }

    public function testResolveLayoutArrayShapedLayoutResolvesRefs(): void
    {
        $definition = [
            'fields' => [
                'title' => ['label' => 'Title'],
                'body'  => ['label' => 'Body'],
            ],
            'layout' => [
                ['@ref' => 'fields.title'],
                ['@ref' => 'fields.body'],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);

        $this->assertIsArray($result['layout']);
        $this->assertCount(2, $result['layout']);
        $this->assertSame('Title', $result['layout'][0]['label']);
        $this->assertSame('Body', $result['layout'][1]['label']);
    }

    public function testResolveLayoutArrayShapedLayoutResolvesUse(): void
    {
        $this->loader->method('load')
            ->with('snippet', 'row_snippet')
            ->willReturn([['component' => 'row']]);
        $this->merger->method('merge')
            ->with([['component' => 'row']])
            ->willReturn(['component' => 'row']);

        $definition = [
            'layout' => [
                ['@use' => 'row_snippet'],
                ['component' => 'static_node'],
            ],
        ];

        $result = $this->resolver->resolveLayout($definition);

        $this->assertIsArray($result['layout']);
        $this->assertCount(2, $result['layout']);
        $this->assertSame('row', $result['layout'][0]['component']);
        $this->assertSame('static_node', $result['layout'][1]['component']);
    }

    /**
     * Configure loader and merger to return a snippet for a given id.
     */
    private function configureResolve(string $id, array $merged): void
    {
        $this->loader->method('load')
            ->with('snippet', $id)
            ->willReturn([$merged]);

        $this->merger->method('merge')
            ->with([$merged])
            ->willReturn($merged);
    }
}
