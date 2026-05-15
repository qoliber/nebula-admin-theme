<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\WidgetChooser;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageSearchResultsInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsPage;

class CmsPageTest extends TestCase
{
    private PageRepositoryInterface&MockObject $repo;
    private SearchCriteriaBuilder&MockObject $criteria;
    private FilterBuilder&MockObject $filters;
    private CmsPage $vm;

    protected function setUp(): void
    {
        $this->repo     = $this->createMock(PageRepositoryInterface::class);
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

        $this->vm = new CmsPage($this->repo, $this->criteria, $this->filters);
    }

    public function testSearchReturnsRowsAndTotal(): void
    {
        $p1 = $this->createMock(PageInterface::class);
        $p1->method('getId')->willReturn(1);
        $p1->method('getIdentifier')->willReturn('home');
        $p1->method('getTitle')->willReturn('Home Page');
        $p1->method('isActive')->willReturn(true);

        $p2 = $this->createMock(PageInterface::class);
        $p2->method('getId')->willReturn(2);
        $p2->method('getIdentifier')->willReturn('about');
        $p2->method('getTitle')->willReturn('About Us');
        $p2->method('isActive')->willReturn(false);

        $results = $this->createMock(PageSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$p1, $p2]);
        $results->method('getTotalCount')->willReturn(2);

        $this->repo->method('getList')->willReturn($results);

        $page = $this->vm->search('', 1, 20);

        $this->assertSame(2, $page['total']);
        $this->assertSame(['value' => '1', 'identifier' => 'home',  'title' => 'Home Page', 'is_active' => true], $page['rows'][0]);
        $this->assertSame(['value' => '2', 'identifier' => 'about', 'title' => 'About Us',  'is_active' => false], $page['rows'][1]);
    }

    public function testGetByIdReturnsRowWhenFound(): void
    {
        $cmsPage = $this->createMock(PageInterface::class);
        $cmsPage->method('getId')->willReturn(5);
        $cmsPage->method('getIdentifier')->willReturn('contact');
        $cmsPage->method('getTitle')->willReturn('Contact Us');
        $cmsPage->method('isActive')->willReturn(true);

        $this->repo->method('getById')->with('5')->willReturn($cmsPage);

        $row = $this->vm->getByValue('5');

        $this->assertSame(['value' => '5', 'identifier' => 'contact', 'title' => 'Contact Us', 'is_active' => true], $row);
    }

    public function testGetByIdReturnsNullWhenMissing(): void
    {
        $this->repo->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->vm->getByValue('999'));
    }
}
