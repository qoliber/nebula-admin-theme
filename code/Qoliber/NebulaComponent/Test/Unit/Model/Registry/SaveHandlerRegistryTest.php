<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Registry;

use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\FormSaveHandlerInterface;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaComponent\Model\Registry\SaveHandlerRegistry;

class SaveHandlerRegistryTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
    }

    public function testGetResolvesHandler(): void
    {
        $handler = $this->createMock(FormSaveHandlerInterface::class);
        $this->objectManager->method('get')->willReturn($handler);

        $registry = new SaveHandlerRegistry($this->objectManager, [
            'customer.group.save' => 'Vendor\\Handler',
        ]);

        self::assertSame($handler, $registry->get('customer.group.save'));
    }

    public function testGetThrowsOnUnknownAlias(): void
    {
        $this->expectException(UnknownAliasException::class);
        (new SaveHandlerRegistry($this->objectManager, []))->get('ghost');
    }

    public function testGetRejectsNonHandlerInstance(): void
    {
        $this->objectManager->method('get')->willReturn(new \stdClass());

        $registry = new SaveHandlerRegistry($this->objectManager, [
            'bad' => 'stdClass',
        ]);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $registry->get('bad');
    }
}
