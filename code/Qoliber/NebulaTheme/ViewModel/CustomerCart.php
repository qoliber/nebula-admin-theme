<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `customer_cart` snippet (customer edit → cart tab).
 *
 * Formerly resolved {@see \Magento\Framework\Pricing\PriceCurrencyInterface}
 * from ObjectManager inside the phtml; now constructor-injected.
 */
class CustomerCart implements ArgumentInterface
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
