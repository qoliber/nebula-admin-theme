<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `customer_orders_lazy` snippet (stats + lazy-load grid).
 *
 * Replaces ObjectManager resolution of {@see \Magento\Framework\Pricing\PriceCurrencyInterface}.
 */
class CustomerOrdersLazy implements ArgumentInterface
{
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function formatCurrency(float $amount): string
    {
        return (string) $this->priceCurrency->format($amount, false);
    }
}
