<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\ColumnRendererInterface;
use Qoliber\NebulaComponent\Api\FieldRendererInterface;
use Qoliber\NebulaComponent\Model\RendererPool;

class RendererPoolTest extends TestCase
{
    public function testGetFieldComponentFallsBackToConvention(): void
    {
        $pool = new RendererPool([], []);

        $this->assertSame('nebulaField_text', $pool->getFieldComponent('text'));
    }

    public function testGetFieldComponentUsesRegisteredRendererName(): void
    {
        $renderer = $this->createMock(FieldRendererInterface::class);
        $renderer->method('getComponentName')->willReturn('custom_comp');

        $pool = new RendererPool(['date' => $renderer], []);
        $this->assertSame('custom_comp', $pool->getFieldComponent('date'));
    }

    public function testGetColumnRendererReturnsDefaultWhenUnknown(): void
    {
        $text = $this->createMock(ColumnRendererInterface::class);
        $pool = new RendererPool([], [], $text);

        $this->assertSame($text, $pool->getColumnRenderer('unknown'));
    }

    public function testGetColumnRendererReturnsRegistered(): void
    {
        $badge = $this->createMock(ColumnRendererInterface::class);
        $pool = new RendererPool([], ['badge' => $badge]);

        $this->assertSame($badge, $pool->getColumnRenderer('badge'));
    }

    public function testGetColumnRendererThrowsWhenNoDefaultAndUnknown(): void
    {
        $pool = new RendererPool([], []);

        $this->expectException(\RuntimeException::class);
        $pool->getColumnRenderer('badge');
    }

    public function testHasColumnRendererReflectsRegistration(): void
    {
        $pool = new RendererPool([], ['badge' => $this->createMock(ColumnRendererInterface::class)]);

        $this->assertTrue($pool->hasColumnRenderer('badge'));
        $this->assertFalse($pool->hasColumnRenderer('text'));
    }

    public function testGetRendererCountsReflectRegistrations(): void
    {
        $field = $this->createMock(FieldRendererInterface::class);
        $column = $this->createMock(ColumnRendererInterface::class);
        $pool = new RendererPool(['text' => $field], ['badge' => $column]);

        $this->assertSame(1, $pool->getFieldRendererCount());
        $this->assertSame(1, $pool->getColumnRendererCount());
    }
}
