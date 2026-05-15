<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\WidgetChooser;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Powers the cms_block widget chooser snippet. `search` returns a
 * paginated grid; `getByValue` is used to populate the "currently
 * selected" display on the wizard when editing an existing widget
 * instance whose block_id parameter points at a real CMS block.
 */
class CmsBlock implements WidgetChooserViewModelInterface
{
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
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
                ->setField(BlockInterface::TITLE)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $identifierFilter = $this->filterBuilder
                ->setField(BlockInterface::IDENTIFIER)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            // Passing both filters in a single addFilters() call creates an OR
            // group — title OR identifier match.
            $this->criteriaBuilder->addFilters([$titleFilter, $identifierFilter]);
        }

        $criteria = $this->criteriaBuilder
            ->setPageSize($pageSize)
            ->setCurrentPage($page)
            ->create();

        $results = $this->blockRepository->getList($criteria);

        $rows = [];
        foreach ($results->getItems() as $block) {
            $rows[] = $this->row($block);
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
            return $this->row($this->blockRepository->getById($value));
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return array{value: string, identifier: string, title: string, is_active: bool}
     */
    private function row(BlockInterface $block): array
    {
        return [
            'value'      => (string) $block->getId(),
            'identifier' => (string) $block->getIdentifier(),
            'title'      => (string) $block->getTitle(),
            'is_active'  => (bool) $block->isActive(),
        ];
    }
}
