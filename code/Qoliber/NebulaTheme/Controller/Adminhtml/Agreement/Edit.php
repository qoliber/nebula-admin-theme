<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

class Edit extends AbstractAgreement
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $agreement = $this->initAgreement(true);
        if ($agreement === null) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('This condition no longer exists.'),
                    'redirectUrl' => $this->getUrl('nebula/agreement/index'),
                ]);
            }

            $this->messageManager->addErrorMessage(__('This condition no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('nebula/agreement/index');
        }

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData(
                $this->createFormPayload(true, (string) $agreement->getName())
            );
        }

        return $this->createPage((string) $agreement->getName());
    }
}
