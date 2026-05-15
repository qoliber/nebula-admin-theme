<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel for the `attribute_labels` snippet. Exposes all stores
 * (including the admin store — hence `getStores(true)`) for the
 * per-store label editor.
 */
class AttributeLabels implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<StoreInterface>
     */
    public function getStores(): array
    {
        return array_values($this->storeManager->getStores(true));
    }
}
