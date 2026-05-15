<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Magento\Framework\App\Area;
use Magento\Theme\Model\View\Design;

class ForceAdminTheme
{
    private const THEME_PATH = 'Qoliber/Nebula';

    public function beforeSetDesignTheme(
        Design $subject,
        mixed $themeId = null,
        ?string $area = null
    ): array {
        if ($area === Area::AREA_ADMINHTML || $subject->getArea() === Area::AREA_ADMINHTML) {
            return [self::THEME_PATH, $area];
        }

        return [$themeId, $area];
    }
}
