<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model\Definition;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaGrid\Model\AddButtonUrlBuilder;
use Qoliber\NebulaGrid\Model\Definition\GridDefinitionNormalizer;

class GridDefinitionNormalizerTest extends TestCase
{
    /** @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $logger;

    private GridDefinitionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $addButtonUrlBuilder = $this->createMock(AddButtonUrlBuilder::class);
        $addButtonUrlBuilder->method('resolve')->willReturnArgument(0);
        $this->normalizer = new GridDefinitionNormalizer($this->logger, $addButtonUrlBuilder);
    }

    public function testMovesTopLevelMassActionsToSettings(): void
    {
        $massActions = [
            ['id' => 'delete', 'label' => 'Delete', 'url' => 'catalog/product/massDelete'],
        ];

        $definition = [
            'massActions' => $massActions,
            'settings' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayNotHasKey('massActions', $result);
        $this->assertSame($massActions, $result['settings']['massActions']);
    }

    public function testDoesNotOverwriteExistingSettingsMassActions(): void
    {
        $existing = [
            ['id' => 'approve', 'label' => 'Approve', 'url' => 'admin/order/approve'],
        ];
        $topLevel = [
            ['id' => 'delete', 'label' => 'Delete', 'url' => 'catalog/product/massDelete'],
        ];

        $definition = [
            'massActions' => $topLevel,
            'settings' => [
                'massActions' => $existing,
            ],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayNotHasKey('massActions', $result);
        $this->assertSame($existing, $result['settings']['massActions']);
    }

    public function testStripsBridgeMetadataKeys(): void
    {
        $definition = [
            '_warning' => 'auto-generated',
            '_generated' => true,
            '_meta' => ['source' => 'bridge'],
            'urlPath' => 'catalog/product/index',
            'idField' => 'entity_id',
            'urlTemplate' => 'catalog/product/edit/id/{{id}}',
            'settings' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayNotHasKey('_warning', $result);
        $this->assertArrayNotHasKey('_generated', $result);
        $this->assertArrayNotHasKey('_meta', $result);
        $this->assertArrayNotHasKey('urlPath', $result);
        $this->assertArrayNotHasKey('idField', $result);
        $this->assertArrayNotHasKey('urlTemplate', $result);
    }

    public function testDefaultsSettingsMassActionsToEmptyArray(): void
    {
        $definition = [
            'settings' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertSame([], $result['settings']['massActions']);
    }

    public function testDefaultsSettingsMassActionsWhenBoolean(): void
    {
        $definition = [
            'settings' => [
                'massActions' => true,
            ],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertSame([], $result['settings']['massActions']);
    }

    public function testLogsWarningForUnknownColumnType(): void
    {
        $this->logger->expects($this->once())->method('warning');

        $definition = [
            'settings' => [],
            'columns' => [
                'foo' => ['type' => 'invalid_type'],
            ],
        ];

        $this->normalizer->normalize($definition);
    }

    public function testDoesNotLogWarningWhenRendererPresent(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $definition = [
            'settings' => [],
            'columns' => [
                'foo' => ['type' => 'invalid_type', 'renderer' => 'snippet.foo'],
            ],
        ];

        $this->normalizer->normalize($definition);
    }

    public function testStripsAllBridgeMetadataKeys(): void
    {
        $definition = [
            '_warning' => 'some warning',
            '_generated' => true,
            '_meta' => ['source' => 'bridge'],
            'visible' => true,
            'urlPath' => 'catalog/product',
            'idField' => 'entity_id',
            'urlTemplate' => 'catalog/product/:id/edit',
            'columns' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayNotHasKey('_warning', $result);
        $this->assertArrayNotHasKey('_generated', $result);
        $this->assertArrayNotHasKey('_meta', $result);
        $this->assertArrayNotHasKey('visible', $result);
        $this->assertArrayNotHasKey('urlPath', $result);
        $this->assertArrayNotHasKey('idField', $result);
        $this->assertArrayNotHasKey('urlTemplate', $result);
        $this->assertArrayHasKey('columns', $result);
    }

    public function testKnownTypesDoNotTriggerWarning(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $columns = [];
        foreach (['text', 'date', 'price', 'thumbnail', 'badge', 'boolean', 'link', 'html', 'actions'] as $type) {
            $columns[$type . '_col'] = ['type' => $type];
        }

        $definition = [
            'settings' => [],
            'columns' => $columns,
        ];

        $this->normalizer->normalize($definition);
    }
}
