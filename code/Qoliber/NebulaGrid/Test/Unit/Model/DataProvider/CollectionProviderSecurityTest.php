<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model\DataProvider;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaGrid\Api\CollectionRegistryInterface;
use Qoliber\NebulaGrid\Model\DataProvider\CollectionProvider;

/**
 * Pins the RC1 request-hardening guards on CollectionProvider:
 *   B1 — filter on a column not declared in the grid definition is ignored.
 *   B2 — sort on an undeclared column is ignored.
 *   B3 — request pageSize is clamped to MAX_PAGE_SIZE.
 *
 * These prevent an authenticated admin from filtering/sorting arbitrary DB
 * columns or exhausting memory via ?pageSize=99999999. A future refactor that
 * drops a guard fails here.
 */
class CollectionProviderSecurityTest extends TestCase
{
    private const MAX_PAGE_SIZE = 200;

    /**
     * Build a provider whose registry resolves a single alias to a mock
     * collection, returning [$provider, $collection] so the test can set
     * per-method expectations on the collection.
     *
     * @return array{0: CollectionProvider, 1: AbstractCollection&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeProvider(): array
    {
        $collection = $this->createMock(AbstractCollection::class);
        $collection->method('toArray')->willReturn(['items' => []]);
        $collection->method('getSize')->willReturn(0);

        $registry = $this->createMock(CollectionRegistryInterface::class);
        $registry->method('resolve')->willReturn('Some\\Collection\\Class');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturn($collection);

        $logger = $this->createMock(LoggerInterface::class);

        return [new CollectionProvider($objectManager, $logger, $registry), $collection];
    }

    public function testFilterOnDeclaredColumnIsApplied(): void
    {
        [$provider, $collection] = $this->makeProvider();

        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('name', ['like' => '%abc%']);

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => ['name' => ['filter' => 'text']]],
            ['filters' => ['name' => 'abc']]
        );
    }

    public function testFilterOnUndeclaredColumnIsIgnored(): void
    {
        [$provider, $collection] = $this->makeProvider();

        // B1: an arbitrary column the grid does not declare must never reach
        // addFieldToFilter.
        $collection->expects($this->never())->method('addFieldToFilter');

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => ['name' => ['filter' => 'text']]],
            ['filters' => ['secret_token' => 'x']]
        );
    }

    public function testSortOnDeclaredColumnIsApplied(): void
    {
        [$provider, $collection] = $this->makeProvider();

        $collection->expects($this->once())
            ->method('setOrder')
            ->with('name', 'ASC');

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => ['name' => ['filter' => 'text']]],
            ['sort' => 'name', 'sortDir' => 'asc']
        );
    }

    public function testSortOnUndeclaredColumnIsIgnored(): void
    {
        [$provider, $collection] = $this->makeProvider();

        // B2: a bogus sort column must never reach setOrder.
        $collection->expects($this->never())->method('setOrder');

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => ['name' => ['filter' => 'text']]],
            ['sort' => 'password_hash', 'sortDir' => 'desc']
        );
    }

    public function testPageSizeIsClampedToMax(): void
    {
        [$provider, $collection] = $this->makeProvider();

        // B3: an oversized request page size is clamped, not passed through.
        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(self::MAX_PAGE_SIZE);

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => []],
            ['pageSize' => 99999999]
        );
    }

    public function testReasonablePageSizeIsPassedThrough(): void
    {
        [$provider, $collection] = $this->makeProvider();

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(50);

        $provider->getData(
            ['collection' => 'x.alias', 'columns' => []],
            ['pageSize' => 50]
        );
    }
}
