<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `customer_overview` snippet. Provides currency
 * formatting plus a humanised "relative date" helper so the phtml
 * stays markup-only.
 */
class CustomerOverview implements ArgumentInterface
{
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function formatCurrency(float $amount): string
    {
        return (string) $this->priceCurrency->format($amount, false);
    }

    /**
     * Pure-domain helper (doesn't need DI), but lives here so the phtml
     * doesn't declare closures that pull services from ObjectManager.
     */
    public function relativeDate(?string $date): string
    {
        if (!$date) {
            return (string) __('Never');
        }

        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $then = new DateTime($date, new DateTimeZone('UTC'));
        } catch (Exception) {
            return (string) __('Never');
        }

        $diff = $now->diff($then);

        if ($diff->days === 0) {
            return (string) __('Today');
        }
        if ($diff->days === 1) {
            return (string) __('Yesterday');
        }
        if ($diff->days < 30) {
            return (string) __('%1 days ago', $diff->days);
        }
        if ($diff->days < 365) {
            $months = (int) floor($diff->days / 30);
            return (string) __('%1 month(s) ago', $months);
        }
        $years = (int) floor($diff->days / 365);

        return (string) __('%1 year(s) ago', $years);
    }
}
