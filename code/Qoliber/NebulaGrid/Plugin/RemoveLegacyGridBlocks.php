<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Plugin;

use Magento\Backend\Block\Widget\Grid\Container;

class RemoveLegacyGridBlocks
{
    public function afterToHtml(Container $subject, string $result): string
    {
        foreach ($subject->getLayout()->getAllBlocks() as $block) {
            if ($block instanceof \Qoliber\NebulaGrid\Block\Grid) {
                return '';
            }
        }

        return $result;
    }
}
