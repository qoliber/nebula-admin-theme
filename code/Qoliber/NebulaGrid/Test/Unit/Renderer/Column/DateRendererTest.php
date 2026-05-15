<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\DateRenderer;

class DateRendererTest extends TestCase
{
    public function testGetComponentName(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertSame('nebulaColumn_date', $renderer->getComponentName());
    }

    public function testToViewDataFormatsIsoDate(): void
    {
        $renderer = $this->makeRenderer();
        $result = $this->invokeToViewData($renderer, [], 'created_at', ['created_at' => '2025-06-15']);

        $this->assertSame('Jun 15, 2025', $result['formatted']);
        $this->assertFalse($result['parse_failed']);
    }

    public function testToViewDataHonorsCustomFormat(): void
    {
        $renderer = $this->makeRenderer();
        $result = $this->invokeToViewData(
            $renderer,
            ['format' => 'Y-m'],
            'created_at',
            ['created_at' => '2025-06-15']
        );

        $this->assertSame('2025-06', $result['formatted']);
    }

    public function testToViewDataFlagsUnparseableValue(): void
    {
        $renderer = $this->makeRenderer();
        $result = $this->invokeToViewData($renderer, [], 'created_at', ['created_at' => 'garbage']);

        $this->assertTrue($result['parse_failed']);
        $this->assertSame('garbage', $result['formatted']);
    }

    public function testToViewDataLeavesFormattedEmptyWhenValueMissing(): void
    {
        $renderer = $this->makeRenderer();
        $result = $this->invokeToViewData($renderer, [], 'created_at', []);

        $this->assertSame('', $result['formatted']);
        $this->assertNull($result['value']);
    }

    private function makeRenderer(): DateRenderer
    {
        return new DateRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer()
        );
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function invokeToViewData(DateRenderer $renderer, array $column, string $key, array $item): array
    {
        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, $column, $key, $item);

        return $data;
    }
}
