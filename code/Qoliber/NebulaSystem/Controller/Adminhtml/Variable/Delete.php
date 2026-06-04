<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

class Delete extends AbstractVariable
{
    public function execute(): \Magento\Backend\Model\View\Result\Redirect
    {
        $variable = $this->initVariable();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($variable->getId()) {
            try {
                $variable->delete();
                $this->messageManager->addSuccess(__('You deleted the custom variable.'));
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());

                return $resultRedirect->setPath('nebulasystem/variable/edit', ['_current' => true]);
            }
        }

        return $resultRedirect->setPath('nebulasystem/variable/index');
    }
}
