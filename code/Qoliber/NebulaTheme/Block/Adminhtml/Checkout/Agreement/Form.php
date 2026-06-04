<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Checkout\Agreement;

class Form extends \Magento\CheckoutAgreements\Block\Adminhtml\Agreement\Edit\Form
{
    protected function _prepareForm()
    {
        parent::_prepareForm();

        $form = $this->getForm();
        if ($form !== null) {
            $form->setAction($this->getUrl('nebula/agreement/save'));
        }

        return $this;
    }
}
