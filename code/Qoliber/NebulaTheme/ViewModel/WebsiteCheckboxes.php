<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel for the `website_checkboxes` snippet. Exposes the configured
 * websites list without forcing the phtml to reach for ObjectManager.
 */
class WebsiteCheckboxes implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<WebsiteInterface>
     */
    public function getWebsites(): array
    {
        return array_values($this->storeManager->getWebsites());
    }
}
