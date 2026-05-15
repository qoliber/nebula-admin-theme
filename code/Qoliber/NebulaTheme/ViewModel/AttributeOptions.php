<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel for the `attribute_options` snippet (select / multiselect
 * attribute option editor). Loads existing options + per-store labels.
 */
class AttributeOptions implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly AttributeFactory $attributeFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return list<StoreInterface>
     */
    public function getStores(): array
    {
        return array_values($this->storeManager->getStores());
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    public function getStoresData(): array
    {
        $stores = [];
        foreach ($this->getStores() as $store) {
            $stores[] = [
                'id' => (int) $store->getId(),
                'name' => (string) $store->getName(),
                'code' => (string) $store->getCode(),
            ];
        }

        return $stores;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getExistingOptions(?int $attributeId): array
    {
        if (!$attributeId) {
            return [];
        }

        $attribute = $this->attributeFactory->create()->load($attributeId);
        $source = $attribute->getSource();
        if ($source === null) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $optionValueTable = $this->resourceConnection->getTableName('eav_attribute_option_value');
        $optionTable = $this->resourceConnection->getTableName('eav_attribute_option');

        $defaultValues = [];
        $defaultRaw = $attribute->getDefaultValue();
        if ($defaultRaw !== null && $defaultRaw !== '') {
            $defaultValues = explode(',', (string) $defaultRaw);
        }

        $existing = [];
        foreach ($source->getAllOptions(false) as $option) {
            if ($option['value'] === '') {
                continue;
            }

            $optionId = $option['value'];

            $storeLabels = [];
            $labelRows = $connection->fetchAll(
                $connection->select()
                    ->from($optionValueTable, ['store_id', 'value'])
                    ->where('option_id = ?', $optionId)
            );
            foreach ($labelRows as $row) {
                $storeLabels[(int) $row['store_id']] = (string) $row['value'];
            }

            $sortOrder = (int) ($connection->fetchOne(
                $connection->select()
                    ->from($optionTable, ['sort_order'])
                    ->where('option_id = ?', $optionId)
            ) ?: 0);

            $existing[] = [
                'id' => $optionId,
                'label' => (string) $option['label'],
                'sort_order' => $sortOrder,
                'is_default' => in_array((string) $optionId, $defaultValues, true),
                'store_labels' => $storeLabels,
                'is_new' => false,
                'is_delete' => false,
            ];
        }

        return $existing;
    }
}
