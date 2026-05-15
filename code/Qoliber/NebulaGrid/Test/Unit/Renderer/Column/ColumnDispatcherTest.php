<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Renderer\Column;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\ColumnRendererInterface;
use Qoliber\NebulaComponent\Model\RendererPool;
use Qoliber\NebulaGrid\Renderer\Column\ColumnDispatcher;
use Qoliber\NebulaGrid\Renderer\Column\SnippetRenderer;
use Qoliber\NebulaGrid\Renderer\Column\TemplateRenderer;

class ColumnDispatcherTest extends TestCase
{
    public function testDispatchesToSnippetRendererWhenRendererStartsWithSnippet(): void
    {
        $snippet = $this->createMock(SnippetRenderer::class);
        $snippet->expects($this->once())->method('render')->willReturn('<snippet-html/>');

        $dispatcher = new ColumnDispatcher(
            new RendererPool([], []),
            $snippet,
            $this->createMock(TemplateRenderer::class)
        );

        $result = $dispatcher->render(['renderer' => 'snippet.status_badge'], 'status', []);
        $this->assertSame('<snippet-html/>', $result);
    }

    public function testDispatchesToTemplateRendererWhenTemplateKeyPresent(): void
    {
        $template = $this->createMock(TemplateRenderer::class);
        $template->expects($this->once())->method('render')->willReturn('<tpl/>');

        $dispatcher = new ColumnDispatcher(
            new RendererPool([], []),
            $this->createMock(SnippetRenderer::class),
            $template
        );

        $result = $dispatcher->render(['template' => 'Vendor_Mod::foo.phtml'], 'x', []);
        $this->assertSame('<tpl/>', $result);
    }

    public function testDispatchesToTemplateRendererWhenRendererContainsDoubleColon(): void
    {
        $template = $this->createMock(TemplateRenderer::class);
        $template->expects($this->once())->method('render')->willReturn('<tpl/>');

        $dispatcher = new ColumnDispatcher(
            new RendererPool([], []),
            $this->createMock(SnippetRenderer::class),
            $template
        );

        $dispatcher->render(['renderer' => 'Vendor_Mod::col/foo.phtml'], 'x', []);
    }

    public function testFallsBackToTypeRendererFromRendererPool(): void
    {
        $fallback = $this->createMock(ColumnRendererInterface::class);
        $fallback->expects($this->once())->method('render')->willReturn('typed');

        $dispatcher = new ColumnDispatcher(
            new RendererPool([], ['price' => $fallback]),
            $this->createMock(SnippetRenderer::class),
            $this->createMock(TemplateRenderer::class)
        );

        $this->assertSame('typed', $dispatcher->render(['type' => 'price'], 'p', []));
    }

    public function testDefaultsToTextTypeWhenColumnHasNoType(): void
    {
        $fallback = $this->createMock(ColumnRendererInterface::class);
        $fallback->method('render')->willReturn('text!');

        $dispatcher = new ColumnDispatcher(
            new RendererPool([], [], $fallback),
            $this->createMock(SnippetRenderer::class),
            $this->createMock(TemplateRenderer::class)
        );

        $this->assertSame('text!', $dispatcher->render([], 'x', []));
    }
}
