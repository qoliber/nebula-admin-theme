<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Qoliber\Nebula\Model\Config\Source\MenuPosition;

class AddMenuLayoutHandle implements ObserverInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function execute(Observer $observer): void
    {
        $layout = $observer->getEvent()->getLayout();
        $position = $this->scopeConfig->getValue('nebula/theme/menu_position') ?: MenuPosition::SIDEBAR;

        // Add the menu position handle (for template swap etc.)
        $layout->getUpdate()->addHandle('nebula_menu_' . $position);
    }
}
