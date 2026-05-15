<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Attributes extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_CatalogRule::promo';

    /** @var array<string, string> */
    private const INPUT_TYPE_MAP = [
        'text' => 'string',
        'textarea' => 'string',
        'date' => 'date',
        'datetime' => 'date',
        'boolean' => 'boolean',
        'select' => 'select',
        'multiselect' => 'multiselect',
        'price' => 'numeric',
        'weight' => 'numeric',
        'int' => 'numeric',
    ];

    /** @var array<string, string> */
    private const SPECIAL_ATTRIBUTES = [
        'category_ids' => 'Category',
        'attribute_set_id' => 'Attribute Set',
    ];

    /** @var array<string, array{label: string, inputType: string}> */
    private const CART_ATTRIBUTES = [
        'quote_item_qty' => ['label' => 'Quantity in cart', 'inputType' => 'numeric'],
        'quote_item_price' => ['label' => 'Price in cart', 'inputType' => 'numeric'],
        'quote_item_row_total' => ['label' => 'Row total in cart', 'inputType' => 'numeric'],
    ];

    /** @var array<string, array{label: string, inputType: string}> */
    private const ADDRESS_ATTRIBUTES = [
        'base_subtotal' => ['label' => 'Subtotal', 'inputType' => 'numeric'],
        'total_qty' => ['label' => 'Total Items Quantity', 'inputType' => 'numeric'],
        'weight' => ['label' => 'Total Weight', 'inputType' => 'numeric'],
        'payment_method' => ['label' => 'Payment Method', 'inputType' => 'select'],
        'shipping_method' => ['label' => 'Shipping Method', 'inputType' => 'select'],
        'postcode' => ['label' => 'Shipping Postcode', 'inputType' => 'string'],
        'region' => ['label' => 'Shipping Region', 'inputType' => 'string'],
        'region_id' => ['label' => 'Shipping State/Province', 'inputType' => 'select'],
        'country_id' => ['label' => 'Shipping Country', 'inputType' => 'select'],
    ];

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly \Magento\Eav\Model\Config $eavConfig,
        private readonly \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,
        private readonly \Magento\Directory\Model\Config\Source\Country $countrySource,
        private readonly \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory
    ) {
        parent::__construct($context);
    }

    /** @return \Magento\Framework\Controller\Result\Json */
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();
        $type = $this->getRequest()->getParam('type', 'catalog');

        $attributes = $this->getProductAttributes();

        if ($type === 'sales') {
            $attributes = array_merge(
                $attributes,
                $this->getAddressAttributes(),
                $this->getCartAttributes()
            );
        }

        return $result->setData($attributes);
    }

    /** @return array<int, array<string, mixed>> */
    private function getProductAttributes(): array
    {
        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('is_used_for_promo_rules', 1);
        $collection->addFieldToFilter('frontend_input', ['neq' => '']);
        $collection->setOrder('frontend_label', 'ASC');

        $attributes = [];

        foreach ($collection as $attribute) {
            $frontendInput = $attribute->getFrontendInput();
            $inputType = self::INPUT_TYPE_MAP[$frontendInput] ?? 'string';

            $item = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel() ?: $attribute->getAttributeCode(),
                'inputType' => $inputType,
                'options' => [],
            ];

            if (in_array($inputType, ['select', 'multiselect', 'boolean'])) {
                $item['options'] = $this->getAttributeOptions($attribute);
            }

            $attributes[] = $item;
        }

        foreach (self::SPECIAL_ATTRIBUTES as $code => $label) {
            $specialItem = [
                'value' => $code,
                'label' => $label,
                'inputType' => $code === 'category_ids' ? 'grid' : 'select',
                'options' => [],
            ];

            if ($code === 'attribute_set_id') {
                $specialItem['options'] = $this->getAttributeSetOptions();
            }

            $attributes[] = $specialItem;
        }

        return $attributes;
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
     * @return array<int, array{value: string, label: string}>
     */
    private function getAttributeOptions(\Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute): array
    {
        $options = [];
        $source = $attribute->getSource();

        if (!$source instanceof \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource) {
            return $options;
        }

        foreach ($source->getAllOptions(true, true) as $option) {
            if ($option['value'] === '') {
                continue;
            }

            $options[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
            ];
        }

        return $options;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function getAttributeSetOptions(): array
    {
        $entityType = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY);
        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $setCollection */
        $setCollection = $entityType->getAttributeSetCollection();
        $options = [];

        foreach ($setCollection as $set) {
            $options[] = [
                'value' => (string) $set->getId(),
                'label' => $set->getAttributeSetName(),
            ];
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    private function getAddressAttributes(): array
    {
        $attributes = [];

        foreach (self::ADDRESS_ATTRIBUTES as $code => $config) {
            $item = [
                'value' => $code,
                'label' => $config['label'],
                'inputType' => $config['inputType'],
                'options' => [],
            ];

            if ($code === 'country_id') {
                $item['options'] = $this->getCountryOptions();
            } elseif ($code === 'region_id') {
                $item['options'] = $this->getRegionOptions();
            }

            $attributes[] = $item;
        }

        return $attributes;
    }

    /** @return array<int, array<string, mixed>> */
    private function getCartAttributes(): array
    {
        $attributes = [];

        foreach (self::CART_ATTRIBUTES as $code => $config) {
            $attributes[] = [
                'value' => $code,
                'label' => $config['label'],
                'inputType' => $config['inputType'],
                'options' => [],
            ];
        }

        return $attributes;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function getCountryOptions(): array
    {
        $options = [];

        foreach ($this->countrySource->toOptionArray(true) as $option) {
            if ($option['value'] === '') {
                continue;
            }

            $options[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
            ];
        }

        return $options;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function getRegionOptions(): array
    {
        $collection = $this->regionCollectionFactory->create();
        $options = [];

        foreach ($collection as $region) {
            $options[] = [
                'value' => (string) $region->getId(),
                'label' => $region->getDefaultName(),
            ];
        }

        return $options;
    }
}
