<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `configurable_options` snippet (wizard that edits
 * the variation grid for configurable products).
 *
 * Encapsulates the attribute-collection + variation lookup that used to
 * live inline in the phtml with ObjectManager.
 */
class ConfigurableOptions implements ArgumentInterface
{
    public function __construct(
        private readonly Configurable $configurableType,
        private readonly EavConfig $eavConfig,
        private readonly ProductAttributeCollectionFactory $productAttributeCollectionFactory
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAvailableAttributes(int $attributeSetId): array
    {
        $collection = $this->productAttributeCollectionFactory->create();
        $collection->setAttributeSetFilter($attributeSetId)
            ->addFieldToFilter('is_global', 1)
            ->addFieldToFilter('frontend_input', 'select')
            ->addFieldToFilter('is_user_defined', 1)
            ->setOrder('frontend_label', 'ASC');

        $available = [];
        foreach ($collection as $attr) {
            $source = $attr->getSource();
            if ($source === null) {
                continue;
            }

            $options = [];
            foreach ($source->getAllOptions(false) as $opt) {
                if ($opt['value'] !== '') {
                    $options[] = [
                        'value' => (string) $opt['value'],
                        'label' => (string) $opt['label'],
                    ];
                }
            }
            if ($options === []) {
                continue;
            }

            $available[] = [
                'id' => (string) $attr->getId(),
                'code' => (string) $attr->getAttributeCode(),
                'label' => (string) $attr->getFrontendLabel(),
                'options' => $options,
            ];
        }

        return $available;
    }

    /**
     * @return array{usedAttributeCodes: list<string>, usedAttributeIds: list<string>, existingVariations: list<array<string, mixed>>}
     */
    public function resolveVariations(Product $entity): array
    {
        $usedAttributes = $this->configurableType->getConfigurableAttributes($entity);
        $childProducts = $this->configurableType->getUsedProducts($entity);

        $usedAttributeCodes = [];
        $usedAttributeIds = [];
        foreach ($usedAttributes as $attr) {
            $productAttribute = $attr->getProductAttribute();
            $usedAttributeCodes[] = (string) $productAttribute->getAttributeCode();
            $usedAttributeIds[] = (string) $attr->getAttributeId();
        }

        $existingVariations = [];
        foreach ($childProducts as $child) {
            $variation = [
                'id' => $child->getId(),
                'sku' => $child->getSku(),
                'name' => $child->getName(),
                'price' => $child->getPrice(),
                'status' => (int) $child->getStatus(),
                'attributes' => [],
            ];
            foreach ($usedAttributeCodes as $code) {
                $value = $child->getData($code);
                $attrObj = $this->eavConfig->getAttribute('catalog_product', $code);
                $label = $attrObj->getSource()?->getOptionText($value) ?? '';
                $variation['attributes'][$code] = [
                    'value' => (string) $value,
                    'label' => (string) $label,
                ];
            }
            $existingVariations[] = $variation;
        }

        return [
            'usedAttributeCodes' => $usedAttributeCodes,
            'usedAttributeIds' => $usedAttributeIds,
            'existingVariations' => $existingVariations,
        ];
    }
}
