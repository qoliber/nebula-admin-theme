<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Review\Rating;

use Magento\Review\Block\Adminhtml\Rating\Edit\Tab\Form as CoreForm;

class Form extends CoreForm
{
    protected $_template = 'Qoliber_NebulaTheme::review/rating/form.phtml';

    protected function _prepareForm()
    {
        parent::_prepareForm();

        $form = $this->getForm();
        if ($form !== null) {
            $form->setUseContainer(true);
            $form->setId('edit_form');
            $form->setMethod('post');
            $form->setAction(
                $this->getUrl(
                    'nebula/rating/save',
                    ['id' => (int) $this->getRequest()->getParam('id')]
                )
            );
        }

        return $this;
    }
}
