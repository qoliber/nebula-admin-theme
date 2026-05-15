<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\WidgetChooser;

use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Powers the catalog_category widget chooser — used by Catalog
 * Category Link / Catalog Product Link widgets via their id_path
 * parameter. The legacy widget renderer expects the value as
 * `category/<id>` (the same magic-path format Magento writes when its
 * own chooser is used). We preserve that here so the storefront-side
 * widget resolution keeps working unchanged.
 */
class CatalogCategory implements WidgetChooserViewModelInterface
{
    public function __construct(
        private readonly CategoryListInterface $categoryList,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{value: string, label: string, path: string, level: int, is_active: bool}>
     * }
     */
    public function search(string $query, int $page, int $pageSize): array
    {
        if ($query !== '') {
            $filter = $this->filterBuilder
                ->setField(CategoryInterface::KEY_NAME)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $this->criteriaBuilder->addFilters([$filter]);
        }
        // Hide the root (level 0) and the website root (level 1) from
        // the picker — neither is a valid widget target.
        $levelFilter = $this->filterBuilder
            ->setField(CategoryInterface::KEY_LEVEL)
            ->setConditionType('gteq')
            ->setValue('2')
            ->create();
        $this->criteriaBuilder->addFilters([$levelFilter]);

        $criteria = $this->criteriaBuilder
            ->setPageSize($pageSize)
            ->setCurrentPage($page)
            ->create();

        $results = $this->categoryList->getList($criteria);

        $rows = [];
        foreach ($results->getItems() as $category) {
            $rows[] = $this->row($category);
        }

        return ['total' => $results->getTotalCount(), 'rows' => $rows];
    }

    /**
     * @return array{value: string, label: string, path: string, level: int, is_active: bool}|null
     */
    public function getByValue(string $value): ?array
    {
        // Accept either `category/<id>` (what the form posts) or a bare id.
        $rawId = str_starts_with($value, 'category/')
            ? substr($value, strlen('category/'))
            : $value;
        if ($rawId === '') {
            return null;
        }
        try {
            return $this->row($this->categoryRepository->get((int) $rawId));
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return array{value: string, label: string, path: string, level: int, is_active: bool}
     */
    private function row(CategoryInterface $category): array
    {
        return [
            'value'     => 'category/' . (string) $category->getId(),
            'label'     => (string) $category->getName(),
            'path'      => (string) $category->getPath(),
            'level'     => (int) $category->getLevel(),
            'is_active' => (bool) $category->getIsActive(),
        ];
    }
}
