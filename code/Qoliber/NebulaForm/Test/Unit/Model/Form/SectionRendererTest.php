<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use Magento\Framework\Escaper;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaComponent\Model\SnippetViewModelRegistry;
use Qoliber\NebulaForm\Model\Form\SectionRenderer;

class SectionRendererTest extends TestCase
{
    public function testReturnsEmptyStringWhenNoRenderers(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertSame(
            '',
            $renderer->render($this->createMock(LayoutInterface::class), [])
        );
    }

    public function testSingleRendererKeyPromotedToRenderersList(): void
    {
        $renderer = $this->makeRenderer();

        $result = $renderer->render(
            $this->createMock(LayoutInterface::class),
            ['renderer' => 'not-a-real-renderer']
        );

        $this->assertSame('', $result);
    }

    public function testSnippetRendererEmitsPlaceholderWhenSnippetHasNoTemplate(): void
    {
        $snippetResolver = $this->createMock(SnippetResolverInterface::class);
        $snippetResolver->method('resolve')->willReturn([]);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);

        $renderer = new SectionRenderer(
            $snippetResolver,
            new PhtmlRenderer(),
            $escaper,
            new SnippetViewModelRegistry()
        );

        $result = $renderer->render(
            $this->createMock(LayoutInterface::class),
            ['renderers' => ['snippet.unknown']]
        );

        $this->assertStringContainsString('Snippet: unknown', $result);
    }

    public function testSnippetRendererInjectsViewModelFromRegistry(): void
    {
        $snippetResolver = $this->createMock(SnippetResolverInterface::class);
        $snippetResolver->method('resolve')->willReturn([
            'template' => 'Vendor_Module::snippet/example.phtml',
        ]);

        $viewModel = new class() implements \Magento\Framework\View\Element\Block\ArgumentInterface {
            public function label(): string
            {
                return 'vm-label';
            }
        };

        $registry = new SnippetViewModelRegistry(['example' => $viewModel]);

        $phtml = $this->createMock(PhtmlRenderer::class);
        $phtml->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                'Vendor_Module::snippet/example.phtml',
                $this->callback(static function (array $data) use ($viewModel): bool {
                    return ($data['view_model'] ?? null) === $viewModel;
                })
            )
            ->willReturn('rendered');

        $renderer = new SectionRenderer(
            $snippetResolver,
            $phtml,
            $this->createMock(Escaper::class),
            $registry
        );

        $result = $renderer->render(
            $this->createMock(LayoutInterface::class),
            ['renderers' => ['snippet.example']]
        );

        $this->assertSame('rendered', $result);
    }

    private function makeRenderer(): SectionRenderer
    {
        return new SectionRenderer(
            $this->createMock(SnippetResolverInterface::class),
            new PhtmlRenderer(),
            $this->createMock(Escaper::class),
            new SnippetViewModelRegistry()
        );
    }
}
