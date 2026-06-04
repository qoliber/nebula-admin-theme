<?php

declare(strict_types=1);

namespace Qoliber\NebulaCurrency\Block\Adminhtml\System;

class Currencysymbol extends \Magento\CurrencySymbol\Block\Adminhtml\System\Currencysymbol
{
    /**
     * Keep the stock data loading behavior but remove the stock top toolbar button.
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $toolbar = $this->getToolbar();
        if ($toolbar !== false && $toolbar !== null) {
            $toolbar->unsetChild('save_button');
        }

        return $this;
    }
}
