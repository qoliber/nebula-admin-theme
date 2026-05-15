<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Qoliber\Nebula\Model\Config\Source\MenuPosition;

class AddMenuPositionBodyClass
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterRenderElementAttributes(
        \Magento\Framework\View\Page\Config\Renderer $subject,
        string $result,
        string $elementType
    ): string {
        if ($elementType !== PageConfig::ELEMENT_TYPE_BODY) {
            return $result;
        }

        $position = $this->scopeConfig->getValue('nebula/theme/menu_position') ?: MenuPosition::SIDEBAR;

        return $result . ' nebula-menu-' . $position;
    }
}
