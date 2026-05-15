<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model;

use Magento\Eav\Model\Config as EavConfig;

class AddButtonUrlBuilder
{
    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /** Replace {{defaultAttributeSet}} placeholder in addButton option URLs. */
    public function resolve(array $definition): array
    {
        $options = $definition['settings']['addButton']['options'] ?? [];
        if (empty($options)) {
            return $definition;
        }

        $setId = (int) $this->eavConfig
            ->getEntityType('catalog_product')
            ->getDefaultAttributeSetId();

        foreach ($options as $i => $option) {
            if (isset($option['url'])) {
                $definition['settings']['addButton']['options'][$i]['url'] =
                    str_replace('{{defaultAttributeSet}}', (string) $setId, $option['url']);
            }
        }

        return $definition;
    }
}
