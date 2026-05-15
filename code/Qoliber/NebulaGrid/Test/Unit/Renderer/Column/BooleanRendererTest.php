<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\BooleanRenderer;

class BooleanRendererTest extends TestCase
{
    public function testToViewDataCoercesTruthy(): void
    {
        $result = $this->invokeToViewData(['is_active' => 1], 'is_active');
        $this->assertTrue($result['value']);
    }

    public function testToViewDataCoercesFalsy(): void
    {
        foreach ([0, '0', false, null, ''] as $falsy) {
            $result = $this->invokeToViewData(['is_active' => $falsy], 'is_active');
            $this->assertFalse($result['value']);
        }
    }

    public function testToViewDataDefaultsToFalseWhenKeyMissing(): void
    {
        $result = $this->invokeToViewData([], 'is_active');
        $this->assertFalse($result['value']);
    }

    public function testGetComponentName(): void
    {
        $renderer = new BooleanRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer()
        );

        $this->assertSame('nebulaColumn_boolean', $renderer->getComponentName());
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function invokeToViewData(array $item, string $key): array
    {
        $renderer = new BooleanRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer()
        );

        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, [], $key, $item);

        return $data;
    }
}
