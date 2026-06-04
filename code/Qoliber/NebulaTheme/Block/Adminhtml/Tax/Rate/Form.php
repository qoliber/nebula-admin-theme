<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Tax\Rate;

class Form extends \Magento\Tax\Block\Adminhtml\Rate\Form
{
    protected function _prepareForm()
    {
        parent::_prepareForm();

        $form = $this->getForm();
        if ($form !== null) {
            $form->setAction($this->getUrl('nebula/taxrate/save'));
        }

        return $this;
    }
}
