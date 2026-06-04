<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Framework\Registry;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class TaxRuleProvider implements DataProviderInterface
{
    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly Registry $coreRegistry
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;
        $sessionFormValues = (array) ($this->coreRegistry->registry('tax_rule_form_data') ?? []);

        if ($entityId === null) {
            return $sessionFormValues;
        }

        $taxRule = $this->taxRuleRepository->get((int) $entityId);

        $data = [
            'tax_calculation_rule_id' => $taxRule->getId(),
            'code' => $taxRule->getCode(),
            'tax_rate' => $taxRule->getTaxRateIds(),
            'tax_customer_class' => $taxRule->getCustomerTaxClassIds(),
            'tax_product_class' => $taxRule->getProductTaxClassIds(),
            'priority' => $taxRule->getPriority(),
            'position' => $taxRule->getPosition(),
            'calculate_subtotal' => $taxRule->getCalculateSubtotal() ? '1' : '0',
        ];

        if ($sessionFormValues !== []) {
            $data = array_replace($data, $sessionFormValues);
        }

        return $data;
    }
}
