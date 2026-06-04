<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRule;

class Edit extends AbstractTaxRule
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->registerFormDataFromSession();
        $rule = $this->initRule(true);

        if ($rule === null) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('This rule no longer exists.'),
                    'redirectUrl' => $this->getUrl('tax/rule/index'),
                ]);
            }

            $this->messageManager->addError(__('This rule no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('tax/rule/index');
        }

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData(
                $this->createFormPayload(true, (string) $rule->getCode())
            );
        }

        return $this->createPage((string) $rule->getCode());
    }
}
