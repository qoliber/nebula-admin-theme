<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Test\Unit\ViewModel\WidgetChooser;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\Data\BlockSearchResultsInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsBlock;

class CmsBlockTest extends TestCase
{
    private BlockRepositoryInterface&MockObject $repo;
    private SearchCriteriaBuilder&MockObject $criteria;
    private FilterBuilder&MockObject $filters;
    private CmsBlock $viewModel;

    protected function setUp(): void
    {
        $this->repo     = $this->createMock(BlockRepositoryInterface::class);
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

        $this->viewModel = new CmsBlock($this->repo, $this->criteria, $this->filters);
    }

    public function testSearchReturnsRowsAndTotal(): void
    {
        $b1 = $this->createMock(BlockInterface::class);
        $b1->method('getId')->willReturn(1);
        $b1->method('getIdentifier')->willReturn('footer-links');
        $b1->method('getTitle')->willReturn('Footer Links');
        $b1->method('isActive')->willReturn(true);

        $b2 = $this->createMock(BlockInterface::class);
        $b2->method('getId')->willReturn(2);
        $b2->method('getIdentifier')->willReturn('contact-info');
        $b2->method('getTitle')->willReturn('Contact Info');
        $b2->method('isActive')->willReturn(false);

        $results = $this->createMock(BlockSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$b1, $b2]);
        $results->method('getTotalCount')->willReturn(2);

        $this->repo->method('getList')->willReturn($results);

        $page = $this->viewModel->search('footer', 1, 20);

        $this->assertSame(2, $page['total']);
        $this->assertCount(2, $page['rows']);
        $this->assertSame(['value' => '1', 'identifier' => 'footer-links', 'title' => 'Footer Links', 'is_active' => true], $page['rows'][0]);
        $this->assertSame(['value' => '2', 'identifier' => 'contact-info', 'title' => 'Contact Info', 'is_active' => false], $page['rows'][1]);
    }

    public function testGetByIdReturnsRowWhenFound(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->method('getId')->willReturn(5);
        $block->method('getIdentifier')->willReturn('hero');
        $block->method('getTitle')->willReturn('Homepage Hero');
        $block->method('isActive')->willReturn(true);

        $this->repo->method('getById')->with('5')->willReturn($block);

        $row = $this->viewModel->getByValue('5');

        $this->assertSame(['value' => '5', 'identifier' => 'hero', 'title' => 'Homepage Hero', 'is_active' => true], $row);
    }

    public function testGetByIdReturnsNullWhenMissing(): void
    {
        $this->repo->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->viewModel->getByValue('999'));
    }
}
