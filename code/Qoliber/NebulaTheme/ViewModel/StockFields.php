<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `stock_fields` snippet. Resolves the product's
 * current stock item + config defaults for advanced-inventory fields.
 */
class StockFields implements ArgumentInterface
{
    public function __construct(
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getStockItem(?int $productId): ?StockItemInterface
    {
        if (!$productId) {
            return null;
        }

        return $this->stockRegistry->getStockItem($productId);
    }

    public function getConfigValue(string $path): string
    {
        return (string) $this->scopeConfig->getValue($path);
    }
}
