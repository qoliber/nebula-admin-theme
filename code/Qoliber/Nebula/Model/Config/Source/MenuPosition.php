<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MenuPosition implements OptionSourceInterface
{
    public const SIDEBAR = 'sidebar';
    public const TOP = 'top';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::SIDEBAR, 'label' => __('Sidebar (left)')],
            ['value' => self::TOP, 'label' => __('Top (horizontal)')],
        ];
    }
}
