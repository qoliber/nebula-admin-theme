<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Model\Layout\Merge;
use Qoliber\Nebula\Model\Config\Source\MenuPosition;

/**
 * Swaps page layouts to their top-menu equivalents when menu_position = "top".
 */
class SwapPageLayoutForTopMenu
{
    private const LAYOUT_MAP = [
        'admin-1column' => 'admin-1column-top',
        'admin-2columns-left' => 'admin-2columns-left-top',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterGetPageLayout(Merge $subject, ?string $result): ?string
    {
        if ($result === null) {
            return $result;
        }

        $position = $this->scopeConfig->getValue('nebula/theme/menu_position') ?: MenuPosition::SIDEBAR;

        if ($position !== MenuPosition::TOP) {
            return $result;
        }

        return self::LAYOUT_MAP[$result] ?? $result;
    }
}
