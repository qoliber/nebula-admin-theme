<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\WidgetChooser;

use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CatalogCategory;

class CatalogCategoryTest extends TestCase
{
    private CategoryListInterface&MockObject $list;
    private CategoryRepositoryInterface&MockObject $repo;
    private SearchCriteriaBuilder&MockObject $criteria;
    private FilterBuilder&MockObject $filters;
    private CatalogCategory $vm;

    protected function setUp(): void
    {
        $this->list     = $this->createMock(CategoryListInterface::class);
        $this->repo     = $this->createMock(CategoryRepositoryInterface::class);
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

        $this->vm = new CatalogCategory($this->list, $this->repo, $this->criteria, $this->filters);
    }

    public function testSearchReturnsRowsWithCategoryIdPath(): void
    {
        $cat1 = $this->createMock(CategoryInterface::class);
        $cat1->method('getId')->willReturn(3);
        $cat1->method('getName')->willReturn('Sale');
        $cat1->method('getPath')->willReturn('1/2/3');
        $cat1->method('getLevel')->willReturn(2);
        $cat1->method('getIsActive')->willReturn(true);

        $cat2 = $this->createMock(CategoryInterface::class);
        $cat2->method('getId')->willReturn(4);
        $cat2->method('getName')->willReturn('Men');
        $cat2->method('getPath')->willReturn('1/2/4');
        $cat2->method('getLevel')->willReturn(2);
        $cat2->method('getIsActive')->willReturn(true);

        $results = $this->createMock(CategorySearchResultsInterface::class);
        $results->method('getItems')->willReturn([$cat1, $cat2]);
        $results->method('getTotalCount')->willReturn(2);

        $this->list->method('getList')->willReturn($results);

        $page = $this->vm->search('', 1, 50);

        $this->assertSame(2, $page['total']);
        $this->assertSame('category/3', $page['rows'][0]['value']);
        $this->assertSame('Sale', $page['rows'][0]['label']);
        $this->assertSame('1/2/3', $page['rows'][0]['path']);
        $this->assertSame(2, $page['rows'][0]['level']);
    }

    public function testGetByIdPathStripsCategoryPrefix(): void
    {
        $cat = $this->createMock(CategoryInterface::class);
        $cat->method('getId')->willReturn(7);
        $cat->method('getName')->willReturn('Footwear');
        $cat->method('getPath')->willReturn('1/2/7');
        $cat->method('getLevel')->willReturn(2);
        $cat->method('getIsActive')->willReturn(true);

        $this->repo->method('get')->with(7)->willReturn($cat);

        $row = $this->vm->getByValue('category/7');
        $this->assertSame('category/7', $row['value']);
        $this->assertSame('Footwear', $row['label']);
    }

    public function testGetByIdPathAcceptsBareId(): void
    {
        $cat = $this->createMock(CategoryInterface::class);
        $cat->method('getId')->willReturn(7);
        $cat->method('getName')->willReturn('Footwear');
        $cat->method('getPath')->willReturn('1/2/7');
        $cat->method('getLevel')->willReturn(2);
        $cat->method('getIsActive')->willReturn(true);

        $this->repo->method('get')->with(7)->willReturn($cat);

        $row = $this->vm->getByValue('7');
        $this->assertSame('category/7', $row['value']);
    }

    public function testGetByIdPathReturnsNullForMissing(): void
    {
        $this->repo->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->vm->getByValue('category/999'));
        $this->assertNull($this->vm->getByValue(''));
    }
}
