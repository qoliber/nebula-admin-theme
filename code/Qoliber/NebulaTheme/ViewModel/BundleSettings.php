<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `bundle_settings` snippet. Reads the configured
 * weight unit for the fixed-weight input helper.
 */
class BundleSettings implements ArgumentInterface
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
