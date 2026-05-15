<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\WidgetChooser;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Powers the catalog_product widget chooser — used by the Catalog
 * Product Link widget's id_path parameter. Magento writes this value
 * as `product/<id>`; we preserve that format so the storefront-side
 * widget renderer keeps working unchanged.
 *
 * Search matches against both name and sku (OR group). First 100
 * products are server-rendered for B1; live pagination is a B2
 * enhancement (the catalog can be very large).
 */
class CatalogProduct implements WidgetChooserViewModelInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{value: string, label: string, sku: string, status: string}>
     * }
     */
    public function search(string $query, int $page, int $pageSize): array
    {
        if ($query !== '') {
            $nameFilter = $this->filterBuilder
                ->setField(ProductInterface::NAME)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $skuFilter = $this->filterBuilder
                ->setField(ProductInterface::SKU)
                ->setConditionType('like')
                ->setValue('%' . $query . '%')
                ->create();
            $this->criteriaBuilder->addFilters([$nameFilter, $skuFilter]);
        }

        $criteria = $this->criteriaBuilder
            ->setPageSize($pageSize)
            ->setCurrentPage($page)
            ->create();

        $results = $this->productRepository->getList($criteria);

        $rows = [];
        foreach ($results->getItems() as $product) {
            $rows[] = $this->row($product);
        }

        return ['total' => $results->getTotalCount(), 'rows' => $rows];
    }

    /**
     * @return array{value: string, label: string, sku: string, status: string}|null
     */
    public function getByValue(string $value): ?array
    {
        $rawId = str_starts_with($value, 'product/')
            ? substr($value, strlen('product/'))
            : $value;
        if ($rawId === '') {
            return null;
        }
        try {
            return $this->row(
                ctype_digit($rawId)
                    ? $this->productRepository->getById((int) $rawId)
                    : $this->productRepository->get($rawId),
            );
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return array{value: string, label: string, sku: string, status: string}
     */
    private function row(ProductInterface $product): array
    {
        return [
            'value'  => 'product/' . (string) $product->getId(),
            'label'  => (string) $product->getName(),
            'sku'    => (string) $product->getSku(),
            'status' => ((string) $product->getStatus()) === '1' ? 'enabled' : 'disabled',
        ];
    }
}
