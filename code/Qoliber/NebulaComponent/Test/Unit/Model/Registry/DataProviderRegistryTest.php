<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Registry;

use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\DataProviderInterface;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaComponent\Model\Registry\DataProviderRegistry;

class DataProviderRegistryTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
    }

    public function testHasReturnsTrueForDiBinding(): void
    {
        $registry = new DataProviderRegistry($this->objectManager, [
            'catalog.product.grid' => 'Vendor\\Foo',
        ]);

        self::assertTrue($registry->has('catalog.product.grid'));
        self::assertFalse($registry->has('not.there'));
    }

    public function testGetResolvesInstance(): void
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $this->objectManager->expects(self::once())
            ->method('get')
            ->with('Vendor\\Foo')
            ->willReturn($provider);

        $registry = new DataProviderRegistry($this->objectManager, [
            'catalog.product.grid' => 'Vendor\\Foo',
        ]);

        self::assertSame($provider, $registry->get('catalog.product.grid'));
    }

    public function testGetMemoisesSecondLookup(): void
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $this->objectManager->expects(self::once())->method('get')->willReturn($provider);

        $registry = new DataProviderRegistry($this->objectManager, [
            'catalog.product.grid' => 'Vendor\\Foo',
        ]);
        $registry->get('catalog.product.grid');
        $registry->get('catalog.product.grid');
    }

    public function testRuntimeRegisterOverridesBindings(): void
    {
        $overridden = $this->createMock(DataProviderInterface::class);
        $this->objectManager->expects(self::once())
            ->method('get')
            ->with('Vendor\\Bar')
            ->willReturn($overridden);

        $registry = new DataProviderRegistry($this->objectManager, [
            'catalog.product.grid' => 'Vendor\\Foo',
        ]);
        $registry->register('catalog.product.grid', 'Vendor\\Bar');

        self::assertSame($overridden, $registry->get('catalog.product.grid'));
    }

    public function testGetThrowsOnUnknownAlias(): void
    {
        $registry = new DataProviderRegistry($this->objectManager, []);

        $this->expectException(UnknownAliasException::class);
        $registry->get('ghost');
    }

    public function testGetThrowsWhenResolvedObjectDoesNotImplementContract(): void
    {
        $this->objectManager->method('get')->willReturn(new \stdClass());

        $registry = new DataProviderRegistry($this->objectManager, [
            'bad.alias' => 'stdClass',
        ]);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $registry->get('bad.alias');
    }

    public function testAllReturnsDiAndRuntimeMappings(): void
    {
        $registry = new DataProviderRegistry($this->objectManager, [
            'di.one' => 'A',
            'di.two' => 'B',
        ]);
        $registry->register('runtime.three', 'C');
        $registry->register('di.one', 'A2');

        $all = $registry->all();
        self::assertSame(['di.one' => 'A2', 'di.two' => 'B', 'runtime.three' => 'C'], $all);
    }

    public function testRegisterRejectsBlankAlias(): void
    {
        $registry = new DataProviderRegistry($this->objectManager, []);

        $this->expectException(\InvalidArgumentException::class);
        $registry->register(' ', 'Vendor\\Foo');
    }
}
