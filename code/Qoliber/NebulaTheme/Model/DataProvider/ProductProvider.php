<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Model\Store;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;

/**
 * Grid data provider for the admin product listing (EAV-aware).
 */
class ProductProvider implements GridDataProviderInterface
{
    /** @var int Upper bound for request-controlled page size (memory-exhaustion guard). */
    private const MAX_PAGE_SIZE = 200;

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Magento\Catalog\Model\Product\Attribute\Source\Status $statusSource
     * @param \Magento\Catalog\Model\Product\Visibility $visibilitySource
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Eav\Model\Config $eavConfig
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Status $statusSource,
        private readonly Visibility $visibilitySource,
        private readonly ImageHelper $imageHelper,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * Fetch product rows and total count for the given grid definition/request.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function getData(array $config, array $params = []): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);

        $attributes = $config['attributes']
            ?? ['name', 'sku', 'price', 'status', 'visibility', 'type_id', 'thumbnail'];
        foreach ($attributes as $attr) {
            $collection->addAttributeToSelect($attr);
        }

        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            null,
            'left'
        );

        // Search
        $search = $params['search'] ?? '';
        if (!empty($search)) {
            $searchFields = [];
            foreach ($config['columns'] ?? [] as $key => $col) {
                if (!empty($col['searchable'])) {
                    $searchFields[] = $key;
                }
            }

            if (!empty($searchFields)) {
                $conditions = [];
                foreach ($searchFields as $field) {
                    if (in_array($field, ['name', 'sku'])) {
                        $conditions[] = ['attribute' => $field, 'like' => '%' . $search . '%'];
                    }
                }
                if (!empty($conditions)) {
                    $collection->addAttributeToFilter($conditions);
                }
            }
        }

        // Filters
        $columns = $config['columns'] ?? [];
        $filters = $params['filters'] ?? [];
        $processedRanges = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            // Range filters: field_from / field_to
            $baseField = preg_replace('/_(from|to)$/', '', $field);
            if ($baseField !== $field && isset($columns[$baseField])) {
                if (in_array($baseField, $processedRanges, true)) {
                    continue;
                }
                $processedRanges[] = $baseField;
                $condition = [];
                $from = $filters[$baseField . '_from'] ?? '';
                $to = $filters[$baseField . '_to'] ?? '';
                if ($from !== '' && $from !== null) {
                    $condition['from'] = $from;
                }
                if ($to !== '' && $to !== null) {
                    $condition['to'] = $to;
                }
                if (!empty($condition)) {
                    if ($this->isEavAttribute($baseField)) {
                        $collection->addAttributeToFilter($baseField, $condition);
                    } else {
                        $collection->addFieldToFilter($baseField, $condition);
                    }
                }
                continue;
            }

            $filterType = $columns[$field]['filter'] ?? 'text';
            if ($filterType === 'select') {
                if ($this->isEavAttribute($field)) {
                    $collection->addAttributeToFilter($field, $value);
                } else {
                    $collection->addFieldToFilter($field, $value);
                }
            } else {
                if ($this->isEavAttribute($field)) {
                    $collection->addAttributeToFilter($field, ['like' => '%' . $value . '%']);
                } else {
                    $collection->addFieldToFilter($field, ['like' => '%' . $value . '%']);
                }
            }
        }

        // Sort — only by a declared column, never an arbitrary request field.
        $sort = $params['sort'] ?? '';
        $sortDir = $params['sortDir'] ?? 'asc';
        if ($sort !== '' && isset($columns[$sort])) {
            $collection->setOrder($sort, strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC');
        }

        // Pagination
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = (int) ($params['pageSize'] ?? 20);
        if ($pageSize > 0) {
            $pageSize = min($pageSize, self::MAX_PAGE_SIZE);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);
        }

        $items = [];
        foreach ($collection as $product) {
            $data = $product->getData();
            $data['status_label'] = $this->statusSource->getOptionText((string) ($data['status'] ?? 0));
            // Visibility::getOptionText is typed as int while Status is string —
            // Magento's source-model param shapes differ across modules.
            $data['visibility_label'] = $this->visibilitySource->getOptionText((int) ($data['visibility'] ?? 0));
            $data['thumbnail'] = $this->imageHelper->init($product, 'product_listing_thumbnail')
                ->setImageFile($product->getThumbnail())
                ->getUrl();
            $items[] = $data;
        }

        return [
            'items' => $items,
            'totalCount' => $collection->getSize(),
        ];
    }

    /**
     * Whether the given field is a catalog_product EAV attribute.
     *
     * @param string $field
     * @return bool
     */
    private function isEavAttribute(string $field): bool
    {
        $attr = $this->eavConfig->getAttribute('catalog_product', $field);

        return $attr !== false && $attr->getId() !== null;
    }
}
