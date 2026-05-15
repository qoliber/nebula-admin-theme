<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\WidgetChooser;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Powers the cms_page widget chooser snippet — used by the CMS Page Link
 * widget's page_id parameter. Returns rows with the CMS page id, identifier
 * (URL key), title, and active flag.
 */
class CmsPage implements WidgetChooserViewModelInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{value: string, identifier: string, title: string, is_active: bool}>
     * }
     */
    public function search(string $query, int $page, int $pageSize): array
    {
        if ($query !== '') {
            $titleFilter = $this->filterBuilder
                ->setField(PageInterface::TITLE)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $identifierFilter = $this->filterBuilder
                ->setField(PageInterface::IDENTIFIER)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $this->criteriaBuilder->addFilters([$titleFilter, $identifierFilter]);
        }

        $criteria = $this->criteriaBuilder
            ->setPageSize($pageSize)
            ->setCurrentPage($page)
            ->create();

        $results = $this->pageRepository->getList($criteria);

        $rows = [];
        foreach ($results->getItems() as $cmsPage) {
            $rows[] = $this->row($cmsPage);
        }

        return ['total' => $results->getTotalCount(), 'rows' => $rows];
    }

    /**
     * @return array{value: string, identifier: string, title: string, is_active: bool}|null
     */
    public function getByValue(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        try {
            return $this->row($this->pageRepository->getById($value));
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return array{value: string, identifier: string, title: string, is_active: bool}
     */
    private function row(PageInterface $cmsPage): array
    {
        return [
            'value'      => (string) $cmsPage->getId(),
            'identifier' => (string) $cmsPage->getIdentifier(),
            'title'      => (string) $cmsPage->getTitle(),
            'is_active'  => (bool) $cmsPage->isActive(),
        ];
    }
}
