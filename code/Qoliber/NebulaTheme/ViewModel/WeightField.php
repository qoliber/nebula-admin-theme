<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `weight_field` snippet. Exposes the configured
 * weight unit (lbs/kgs) without forcing the phtml to reach for ObjectManager.
 */
class WeightField implements ArgumentInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getWeightUnit(): string
    {
        return (string) ($this->scopeConfig->getValue('general/locale/weight_unit') ?: 'lbs');
    }
}
