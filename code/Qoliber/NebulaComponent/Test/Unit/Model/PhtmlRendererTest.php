<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;

class PhtmlRendererTest extends TestCase
{
    public function testRenderReturnsEmptyStringForEmptyTemplate(): void
    {
        $renderer = new PhtmlRenderer();
        $this->assertSame('', $renderer->render($this->createMock(LayoutInterface::class), ''));
    }

    public function testRenderCreatesBlockAndSetsTemplateAndData(): void
    {
        $block = $this->createMock(Template::class);
        $block->expects($this->once())->method('setTemplate')->with('Vendor_Mod::foo.phtml');
        $block->method('setData')->willReturnSelf();
        $block->method('toHtml')->willReturn('<ok/>');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())
            ->method('createBlock')
            ->with(Template::class)
            ->willReturn($block);

        $renderer = new PhtmlRenderer();

        $this->assertSame(
            '<ok/>',
            $renderer->render($layout, 'Vendor_Mod::foo.phtml', ['foo' => 'bar'])
        );
    }

    public function testSetsModuleNameFromPrefixedTemplate(): void
    {
        $moduleNameCalls = [];

        $block = $this->createMock(Template::class);
        $block->method('setData')->willReturnCallback(
            function (string $key, mixed $value) use ($block, &$moduleNameCalls): Template {
                if ($key === 'module_name') {
                    $moduleNameCalls[] = $value;
                }
                return $block;
            }
        );
        $block->method('toHtml')->willReturn('');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($block);

        $renderer = new PhtmlRenderer();
        $renderer->render($layout, 'Qoliber_Nebula::widget.phtml');

        $this->assertSame(['Qoliber_Nebula'], $moduleNameCalls);
    }
}
