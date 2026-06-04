<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Test\Unit\Model\Menu\Renderer;

use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;
use Qoliber\Nebula\Model\Menu\Renderer\ImageRenderer;

class ImageRendererTest extends TestCase
{
    private Escaper $escaper;

    private AssetRepository $assetRepository;

    protected function setUp(): void
    {
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeUrl')->willReturnArgument(0);
        $this->escaper->method('escapeHtmlAttr')->willReturnArgument(0);

        $this->assetRepository = $this->createMock(AssetRepository::class);
    }

    public function testRendersAbsoluteUrlAsIs(): void
    {
        $this->assetRepository->expects($this->never())->method('getUrl');
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $html = $renderer->render(['src' => 'https://cdn.example.com/logo.svg']);
        $this->assertSame('<img src="https://cdn.example.com/logo.svg"/>', $html);
    }

    public function testResolvesModuleReferenceThroughAssetRepository(): void
    {
        $this->assetRepository->expects($this->once())
            ->method('getUrl')
            ->with('Acme_Catalog::images/rocket.svg')
            ->willReturn('/static/Acme_Catalog/images/rocket.svg');
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $html = $renderer->render(['src' => 'Acme_Catalog::images/rocket.svg']);
        $this->assertStringContainsString('src="/static/Acme_Catalog/images/rocket.svg"', $html);
    }

    public function testEmitsAltAttributeWhenProvided(): void
    {
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $html = $renderer->render(['src' => '/x.svg', 'alt' => 'Logo']);
        $this->assertSame('<img src="/x.svg" alt="Logo"/>', $html);
    }

    public function testEmitsExtraClasses(): void
    {
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $html = $renderer->render(['src' => '/x.svg'], 'w-5 opacity-70');
        $this->assertSame('<img src="/x.svg" class="w-5 opacity-70"/>', $html);
    }

    public function testReturnsEmptyStringWhenSrcMissing(): void
    {
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $this->assertSame('', $renderer->render([]));
        $this->assertSame('', $renderer->render(['alt' => 'no src here']));
    }

    public function testCombinesSrcAltAndClassesInOrder(): void
    {
        $renderer = new ImageRenderer($this->escaper, $this->assetRepository);

        $html = $renderer->render(
            ['src' => '/logo.svg', 'alt' => 'Brand'],
            'w-5 text-center'
        );
        $this->assertSame('<img src="/logo.svg" alt="Brand" class="w-5 text-center"/>', $html);
    }
}
