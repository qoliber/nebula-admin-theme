<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\ActionsRenderer;

class ActionsRendererTest extends TestCase
{
    public function testToViewDataBuildsInterpolatedActions(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnArgument(0);

        $renderer = new ActionsRenderer(
            $this->createMock(Escaper::class),
            $url,
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer(),
            $this->createMock(ObjectManagerInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $column = [
            'actions' => [
                ['label' => 'Edit', 'url' => '/admin/thing/{{id}}/edit'],
                ['label' => 'Delete', 'url' => '/admin/thing/{{id}}/delete'],
            ],
        ];
        $item = ['id' => 99];

        $result = $this->invokeToViewData($renderer, $column, 'actions', $item);

        $this->assertCount(2, $result['actions']);
        $this->assertSame('Edit', $result['actions'][0]['label']);
        $this->assertSame('/admin/thing/99/edit', $result['actions'][0]['url']);
        $this->assertSame('/admin/thing/99/delete', $result['actions'][1]['url']);
    }

    public function testToViewDataReturnsEmptyWhenNoActions(): void
    {
        $renderer = new ActionsRenderer(
            $this->createMock(Escaper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            new PhtmlRenderer(),
            $this->createMock(ObjectManagerInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $result = $this->invokeToViewData($renderer, [], 'actions', []);

        $this->assertSame([], $result['actions']);
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function invokeToViewData(ActionsRenderer $renderer, array $column, string $key, array $item): array
    {
        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, $column, $key, $item);

        return $data;
    }
}
