<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\PriceRenderer;

class PriceRendererTest extends TestCase
{
    public function testFormattedValueUsesInjectedCurrencyFormatter(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn ($v) => '$' . number_format((float) $v, 2)
        );

        $renderer = new PriceRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer(),
            $priceCurrency
        );

        $result = $this->invokeToViewData($renderer, [], 'price', ['price' => 49.9]);

        $this->assertSame('$49.90', $result['formatted']);
        $this->assertSame(49.9, $result['value']);
    }

    public function testFormattedValueEmptyWhenValueMissing(): void
    {
        $renderer = new PriceRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer(),
            $this->createMock(PriceCurrencyInterface::class)
        );

        $result = $this->invokeToViewData($renderer, [], 'price', []);

        $this->assertSame('', $result['formatted']);
        $this->assertNull($result['value']);
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function invokeToViewData(PriceRenderer $renderer, array $column, string $key, array $item): array
    {
        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, $column, $key, $item);

        return $data;
    }
}
