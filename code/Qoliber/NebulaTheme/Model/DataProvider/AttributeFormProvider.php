<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Model\Product\Attribute\Frontend\Inputtype\Presentation;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class AttributeFormProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly AttributeFactory $attributeFactory,
        private readonly Presentation $presentation
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;
        if ($entityId === null) {
            return [];
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        $attribute = $this->attributeFactory->create();
        $attribute->load((int) $entityId);

        if (!$attribute->getId()) {
            return [];
        }

        $data = $attribute->getData();
        $data['store_labels'] = $attribute->getStoreLabels();
        $data['frontend_input'] = $this->presentation->getPresentationInputType($attribute);

        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
            $options = [];
            if ($attribute->getSource()) {
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    $options[] = [
                        'value' => $option['value'],
                        'label' => $option['label'],
                    ];
                }
            }
            $data['_options'] = $options;
        }

        return $data;
    }
}
