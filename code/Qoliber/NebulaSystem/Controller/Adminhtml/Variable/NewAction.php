<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

class NewAction extends AbstractVariable
{
    public function execute(): \Magento\Backend\Model\View\Result\Forward
    {
        $resultForward = $this->resultForwardFactory->create();

        return $resultForward->forward('edit');
    }
}
