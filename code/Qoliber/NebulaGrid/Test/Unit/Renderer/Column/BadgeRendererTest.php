<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\BadgeRenderer;

class BadgeRendererTest extends TestCase
{
    public function testGetComponentNameAndTemplate(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertSame('nebulaColumn_badge', $renderer->getComponentName());
        $this->assertSame('Qoliber_NebulaGrid::column/badge.phtml', $renderer->getTemplate());
    }

    public function testToViewDataResolvesKnownOption(): void
    {
        $renderer = $this->makeRenderer();

        $column = ['options' => ['1' => ['label' => 'Active', 'class' => 'success']]];
        $html = $this->invokeToViewData($renderer, $column, 'status', ['status' => 1]);

        $this->assertTrue($html['has_option']);
        $this->assertSame('Active', $html['label']);
        $this->assertSame(BadgeRenderer::BADGE_CLASS_MAP['success'], $html['tw_class']);
    }

    public function testToViewDataFallsBackWhenOptionMissing(): void
    {
        $renderer = $this->makeRenderer();

        $result = $this->invokeToViewData(
            $renderer,
            ['options' => ['1' => ['label' => 'Active']]],
            'status',
            ['status' => '99']
        );

        $this->assertFalse($result['has_option']);
    }

    public function testToViewDataAllowsArbitraryTailwindClass(): void
    {
        $renderer = $this->makeRenderer();

        $result = $this->invokeToViewData(
            $renderer,
            ['options' => ['1' => ['label' => 'Custom', 'class' => 'custom-tone']]],
            'status',
            ['status' => 1]
        );

        $this->assertSame('custom-tone', $result['tw_class']);
    }

    public function testBadgeClassMapContainsCanonicalTones(): void
    {
        foreach (['success', 'error', 'warning', 'info', 'neutral'] as $tone) {
            $this->assertArrayHasKey($tone, BadgeRenderer::BADGE_CLASS_MAP);
        }
    }

    private function makeRenderer(): BadgeRenderer
    {
        return new BadgeRenderer(
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
    private function invokeToViewData(BadgeRenderer $renderer, array $column, string $key, array $item): array
    {
        $reflection = new \ReflectionMethod($renderer, 'toViewData');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $reflection->invoke($renderer, $column, $key, $item);

        return $data;
    }
}
