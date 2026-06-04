<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Test\Unit\Model\Menu\Renderer;

use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;
use Qoliber\Nebula\Model\Menu\Renderer\FontawesomeRenderer;

class FontawesomeRendererTest extends TestCase
{
    private FontawesomeRenderer $renderer;

    protected function setUp(): void
    {
        $escaper = $this->createMock(Escaper::class);
        // Identity escapes — keeps assertions readable; real Escaper would
        // HTML-encode `"` etc., which is verified separately below.
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);
        $this->renderer = new FontawesomeRenderer($escaper);
    }

    public function testEmitsIconWithSpecClassAlone(): void
    {
        $html = $this->renderer->render(['class' => 'fa-solid fa-gauge-high']);
        $this->assertSame('<i class="fa-solid fa-gauge-high"></i>', $html);
    }

    public function testAppendsExtraClassesAfterSpecClass(): void
    {
        $html = $this->renderer->render(
            ['class' => 'fa-solid fa-gauge-high'],
            'w-5 text-center opacity-70'
        );
        $this->assertSame(
            '<i class="fa-solid fa-gauge-high w-5 text-center opacity-70"></i>',
            $html
        );
    }

    public function testReturnsEmptyStringWhenBothClassAndExtraAreEmpty(): void
    {
        $this->assertSame('', $this->renderer->render([]));
        $this->assertSame('', $this->renderer->render(['class' => ''], ''));
    }

    public function testStillRendersWhenSpecClassMissingButExtraProvided(): void
    {
        // Edge case: a custom module registers an icon entry with only a
        // `type` field, expecting the slot-level `extra` to carry the
        // class. Don't fail silently — render what we have.
        $html = $this->renderer->render([], 'fa-solid fa-rocket');
        $this->assertSame('<i class="fa-solid fa-rocket"></i>', $html);
    }

    public function testEscapesClassAttributeAgainstInjection(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->expects($this->once())
            ->method('escapeHtmlAttr')
            ->with('fa-solid fa-x" onerror="alert(1)')
            ->willReturn('fa-solid fa-x&quot; onerror=&quot;alert(1)');
        $renderer = new FontawesomeRenderer($escaper);

        $html = $renderer->render(['class' => 'fa-solid fa-x" onerror="alert(1)']);
        $this->assertStringContainsString('&quot;', $html);
        $this->assertStringNotContainsString('"alert(1)"', $html);
    }
}
