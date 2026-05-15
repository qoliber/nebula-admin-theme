<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaGrid\Renderer\Column\SelectRenderer;

class SelectRendererTest extends TestCase
{
    private Escaper&MockObject $escaper;
    private SelectRenderer $renderer;

    protected function setUp(): void
    {
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->renderer = new SelectRenderer(
            $this->escaper,
            $this->createMock(UrlInterface::class),
            $this->createMock(LayoutInterface::class),
            $this->createMock(PhtmlRenderer::class),
        );
    }

    public function testReturnsLabelFromFilterOptions(): void
    {
        $column = [
            'type'          => 'select',
            'filterOptions' => [
                'Magento\Cms\Block\Widget\Block' => 'CMS Static Block',
                'Magento\Cms\Block\Widget\Page\Link' => 'CMS Page Link',
            ],
        ];
        $item = ['instance_type' => 'Magento\Cms\Block\Widget\Block'];

        $this->assertSame('CMS Static Block', $this->renderer->render($column, 'instance_type', $item));
    }

    public function testFallsBackToRawValueWhenNoLabelRegistered(): void
    {
        $column = [
            'type'          => 'select',
            'filterOptions' => ['known' => 'Known label'],
        ];
        $item = ['instance_type' => 'Some\Bespoke\Widget\Block'];

        $this->assertSame('Some\Bespoke\Widget\Block', $this->renderer->render($column, 'instance_type', $item));
    }

    public function testEmptyValueReturnsEmptyString(): void
    {
        $column = ['type' => 'select', 'filterOptions' => ['x' => 'X label']];
        $item   = ['instance_type' => null];

        $this->assertSame('', $this->renderer->render($column, 'instance_type', $item));
    }

    public function testWorksWithoutFilterOptionsKey(): void
    {
        $column = ['type' => 'select'];
        $item   = ['instance_type' => 'raw_value'];

        $this->assertSame('raw_value', $this->renderer->render($column, 'instance_type', $item));
    }
}
