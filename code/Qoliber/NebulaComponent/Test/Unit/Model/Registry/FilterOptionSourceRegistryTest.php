<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Registry;

use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaComponent\Model\Registry\FilterOptionSourceRegistry;

class FilterOptionSourceRegistryTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
    }

    public function testAcceptsAnyObjectWithToOptionArray(): void
    {
        $src = new class() {
            public function toOptionArray(): array
            {
                return [];
            }
        };
        $this->objectManager->method('get')->willReturn($src);

        $registry = new FilterOptionSourceRegistry($this->objectManager, [
            'magento.store.system' => 'Vendor\\Legacy',
        ]);

        self::assertSame($src, $registry->get('magento.store.system'));
    }

    public function testRejectsObjectWithoutToOptionArray(): void
    {
        $this->objectManager->method('get')->willReturn(new \stdClass());

        $registry = new FilterOptionSourceRegistry($this->objectManager, [
            'bad' => 'stdClass',
        ]);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $registry->get('bad');
    }

    public function testUnknownAliasThrows(): void
    {
        $this->expectException(UnknownAliasException::class);
        (new FilterOptionSourceRegistry($this->objectManager, []))->get('ghost');
    }
}
