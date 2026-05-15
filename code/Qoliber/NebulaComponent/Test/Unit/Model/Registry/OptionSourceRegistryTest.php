<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Registry;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaComponent\Model\Registry\OptionSourceRegistry;

class OptionSourceRegistryTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
    }

    public function testGetReturnsOptionSource(): void
    {
        $source = $this->createMock(OptionSourceInterface::class);
        $this->objectManager->method('get')->willReturn($source);

        $registry = new OptionSourceRegistry($this->objectManager, [
            'magento.tax.rate' => 'Vendor\\Source',
        ]);

        self::assertSame($source, $registry->get('magento.tax.rate'));
    }

    public function testUnknownAliasThrows(): void
    {
        $this->expectException(UnknownAliasException::class);
        (new OptionSourceRegistry($this->objectManager, []))->get('ghost');
    }

    public function testRejectsNonOptionSource(): void
    {
        $this->objectManager->method('get')->willReturn(new \stdClass());

        $registry = new OptionSourceRegistry($this->objectManager, [
            'bad' => 'stdClass',
        ]);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $registry->get('bad');
    }
}
