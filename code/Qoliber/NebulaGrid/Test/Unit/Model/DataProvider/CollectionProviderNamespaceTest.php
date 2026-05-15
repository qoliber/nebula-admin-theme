<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model\DataProvider;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaGrid\Api\CollectionRegistryInterface;
use Qoliber\NebulaGrid\Model\DataProvider\CollectionProvider;

/**
 * Covers CollectionProvider after the P0-6 typed-registry refactor.
 *
 * The pre-P0-6 prefix-allowlist was replaced by an alias-based
 * {@see CollectionRegistryInterface} — the JSON `dataSource.config.collection`
 * is now an alias (`cms_page.collection`), not an FQCN. The provider asks the
 * registry to resolve the alias to an FQCN and instantiates that. An unknown
 * alias must NOT bubble up; it must log a warning and degrade to an empty
 * grid so a misconfigured JSON can't crash the page.
 */
class CollectionProviderNamespaceTest extends TestCase
{
    public function testKnownAliasResolvesAndReturnsRows(): void
    {
        $collection = $this->createMock(AbstractCollection::class);
        $collection->method('toArray')->willReturn(['items' => [['id' => 1]]]);
        $collection->method('getSize')->willReturn(1);

        $registry = $this->createMock(CollectionRegistryInterface::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('cms_page.collection')
            ->willReturn('Magento\Cms\Model\ResourceModel\Page\Collection');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('create')
            ->with('Magento\Cms\Model\ResourceModel\Page\Collection')
            ->willReturn($collection);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $provider = new CollectionProvider($objectManager, $logger, $registry);

        $result = $provider->getData(['collection' => 'cms_page.collection']);

        $this->assertSame([['id' => 1]], $result['items']);
        $this->assertSame(1, $result['totalCount']);
    }

    public function testUnknownAliasLogsWarningAndReturnsEmpty(): void
    {
        $registry = $this->createMock(CollectionRegistryInterface::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('made.up.alias')
            ->willThrowException(
                UnknownAliasException::forRegistry('collection', 'made.up.alias', ['cms_page.collection'])
            );

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'NebulaGrid: unknown collection alias',
                $this->callback(static fn (array $ctx): bool => $ctx['alias'] === 'made.up.alias')
            );

        $provider = new CollectionProvider($objectManager, $logger, $registry);

        $result = $provider->getData(['collection' => 'made.up.alias']);

        $this->assertSame(['items' => [], 'totalCount' => 0], $result);
    }

    public function testEmptyAliasReturnsEmptyAndDoesNotResolve(): void
    {
        $registry = $this->createMock(CollectionRegistryInterface::class);
        $registry->expects($this->never())->method('resolve');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $provider = new CollectionProvider($objectManager, $logger, $registry);

        $result = $provider->getData(['collection' => '']);

        $this->assertSame(['items' => [], 'totalCount' => 0], $result);
    }

    public function testNonCollectionInstanceLogsWarningAndReturnsEmpty(): void
    {
        // Defense-in-depth: if a registry binding accidentally points at a
        // class that's not an AbstractCollection (mistyped DI alias), the
        // provider must reject it instead of calling collection-only methods
        // on an arbitrary object.
        $registry = $this->createMock(CollectionRegistryInterface::class);
        $registry->method('resolve')->willReturn('stdClass');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('create')
            ->with('stdClass')
            ->willReturn(new \stdClass());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'NebulaGrid: collection alias resolves to non-collection class',
                $this->callback(static fn (array $ctx): bool => $ctx['class'] === 'stdClass')
            );

        $provider = new CollectionProvider($objectManager, $logger, $registry);

        $result = $provider->getData(['collection' => 'broken.alias']);

        $this->assertSame(['items' => [], 'totalCount' => 0], $result);
    }
}
