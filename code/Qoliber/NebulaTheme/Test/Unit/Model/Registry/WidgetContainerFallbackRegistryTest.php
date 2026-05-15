<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\Model\Registry;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\Model\Registry\WidgetContainerFallbackRegistry;

class WidgetContainerFallbackRegistryTest extends TestCase
{
    public function testAllReturnsConfiguredContainers(): void
    {
        $reg = new WidgetContainerFallbackRegistry([
            'content', 'sidebar.main', 'footer',
        ]);

        $this->assertSame(['content', 'sidebar.main', 'footer'], $reg->all());
    }

    public function testSanitizeDropsEmptyAndNonStringEntries(): void
    {
        $reg = new WidgetContainerFallbackRegistry([
            'content',
            '',
            null,
            42,
            ['not-a-string'],
            'sidebar.main',
        ]);

        $this->assertSame(['content', 'sidebar.main'], $reg->all());
    }

    public function testSanitizeDeduplicates(): void
    {
        $reg = new WidgetContainerFallbackRegistry([
            'content', 'sidebar.main', 'content',
        ]);

        $this->assertSame(['content', 'sidebar.main'], $reg->all());
    }

    public function testEmptyConfigReturnsEmptyList(): void
    {
        $reg = new WidgetContainerFallbackRegistry();

        $this->assertSame([], $reg->all());
    }
}
