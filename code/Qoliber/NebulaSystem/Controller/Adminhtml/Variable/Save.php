<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

class Save extends AbstractVariable
{
    public function execute(): \Magento\Backend\Model\View\Result\Redirect
    {
        $variable = $this->initVariable();
        $data = $this->getRequest()->getPost('variable');
        $back = (bool) $this->getRequest()->getParam('back', false);
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            $data['variable_id'] = $variable->getId();
            $variable->setData($data);

            try {
                $variable->save();
                $this->messageManager->addSuccess(__('You saved the custom variable.'));

                if ($back) {
                    return $resultRedirect->setPath(
                        'nebulasystem/variable/edit',
                        ['_current' => true, 'variable_id' => $variable->getId()]
                    );
                }

                return $resultRedirect->setPath('nebulasystem/variable/index');
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());

                return $resultRedirect->setPath('nebulasystem/variable/edit', ['_current' => true]);
            }
        }

        return $resultRedirect->setPath('nebulasystem/variable/index');
    }
}
