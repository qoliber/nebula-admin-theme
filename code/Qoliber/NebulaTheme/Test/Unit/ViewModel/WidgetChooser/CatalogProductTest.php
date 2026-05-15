<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\WidgetChooser;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CatalogProduct;

class CatalogProductTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $repo;
    private SearchCriteriaBuilder&MockObject $criteria;
    private FilterBuilder&MockObject $filters;
    private CatalogProduct $vm;

    protected function setUp(): void
    {
        $this->repo     = $this->createMock(ProductRepositoryInterface::class);
        $this->criteria = $this->createMock(SearchCriteriaBuilder::class);
        $this->filters  = $this->createMock(FilterBuilder::class);

        $this->criteria->method('addFilters')->willReturnSelf();
        $this->criteria->method('setPageSize')->willReturnSelf();
        $this->criteria->method('setCurrentPage')->willReturnSelf();
        $this->criteria->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $this->filters->method('setField')->willReturnSelf();
        $this->filters->method('setConditionType')->willReturnSelf();
        $this->filters->method('setValue')->willReturnSelf();
        $this->filters->method('create')->willReturn($this->createMock(Filter::class));

        $this->vm = new CatalogProduct($this->repo, $this->criteria, $this->filters);
    }

    public function testSearchReturnsProductRowsWithProductIdPath(): void
    {
        $p1 = $this->createMock(ProductInterface::class);
        $p1->method('getId')->willReturn(15);
        $p1->method('getName')->willReturn('Yoga Mat');
        $p1->method('getSku')->willReturn('YM-001');
        $p1->method('getStatus')->willReturn('1');

        $p2 = $this->createMock(ProductInterface::class);
        $p2->method('getId')->willReturn(22);
        $p2->method('getName')->willReturn('Dumbbell');
        $p2->method('getSku')->willReturn('DB-002');
        $p2->method('getStatus')->willReturn('2');

        $results = $this->createMock(ProductSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$p1, $p2]);
        $results->method('getTotalCount')->willReturn(2);

        $this->repo->method('getList')->willReturn($results);

        $page = $this->vm->search('', 1, 50);

        $this->assertSame('product/15', $page['rows'][0]['value']);
        $this->assertSame('Yoga Mat',   $page['rows'][0]['label']);
        $this->assertSame('YM-001',     $page['rows'][0]['sku']);
        $this->assertSame('enabled',    $page['rows'][0]['status']);
        $this->assertSame('disabled',   $page['rows'][1]['status']);
    }

    public function testGetByIdPathStripsProductPrefix(): void
    {
        $p = $this->createMock(ProductInterface::class);
        $p->method('getId')->willReturn(15);
        $p->method('getName')->willReturn('Yoga Mat');
        $p->method('getSku')->willReturn('YM-001');
        $p->method('getStatus')->willReturn('1');

        $this->repo->method('getById')->with(15)->willReturn($p);

        $row = $this->vm->getByValue('product/15');
        $this->assertSame('product/15', $row['value']);
    }

    public function testGetByIdPathLooksUpBySkuForNonNumericId(): void
    {
        $p = $this->createMock(ProductInterface::class);
        $p->method('getId')->willReturn(15);
        $p->method('getName')->willReturn('Yoga Mat');
        $p->method('getSku')->willReturn('YM-001');
        $p->method('getStatus')->willReturn('1');

        $this->repo->method('get')->with('YM-001')->willReturn($p);

        $row = $this->vm->getByValue('product/YM-001');
        $this->assertSame('product/15', $row['value']);
    }

    public function testGetByIdPathReturnsNullForMissing(): void
    {
        $this->repo->method('getById')->willThrowException(new NoSuchEntityException());
        $this->repo->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->vm->getByValue('product/999'));
        $this->assertNull($this->vm->getByValue(''));
    }
}
