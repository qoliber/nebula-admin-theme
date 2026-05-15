<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\LinkRenderer;

class LinkRendererTest extends TestCase
{
    public function testToViewDataInterpolatesHref(): void
    {
        $renderer = $this->makeRenderer();

        $column = ['href' => '/admin/product/{{entity_id}}/edit'];
        $item = ['entity_id' => 42, 'name' => 'Widget'];

        $result = $this->invokeToViewData($renderer, $column, 'name', $item);

        $this->assertSame('/admin/product/42/edit', $result['href']);
        $this->assertSame('Widget', $result['value']);
    }

    public function testToViewDataFallsBackToHashWhenHrefMissing(): void
    {
        $renderer = $this->makeRenderer();
        $result = $this->invokeToViewData($renderer, [], 'name', ['name' => 'Widget']);

        $this->assertSame('#', $result['href']);
    }

    public function testGetComponentName(): void
    {
        $this->assertSame('nebulaColumn_link', $this->makeRenderer()->getComponentName());
    }

    private function makeRenderer(): LinkRenderer
    {
        return new LinkRenderer(
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
    private function invokeToViewData(LinkRenderer $renderer, array $column, string $key, array $item): array
    {
        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, $column, $key, $item);

        return $data;
    }
}
