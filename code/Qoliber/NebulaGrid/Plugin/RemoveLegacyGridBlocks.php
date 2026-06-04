<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Plugin;

use Magento\Backend\Block\Widget\Grid\Container;
use Qoliber\NebulaTheme\Block\Adminhtml\Checkout\Agreement\Manager as AgreementManager;

class RemoveLegacyGridBlocks
{
    public function afterToHtml(Container $subject, string $result): string
    {
        if ($subject instanceof AgreementManager) {
            return $result;
        }

        foreach ($subject->getLayout()->getAllBlocks() as $block) {
            if ($block instanceof \Qoliber\NebulaGrid\Block\Grid) {
                return '';
            }
        }

        return $result;
    }
}
