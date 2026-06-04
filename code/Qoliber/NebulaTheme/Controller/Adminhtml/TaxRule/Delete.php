<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRule;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends AbstractTaxRule implements HttpPostActionInterface
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ruleId = (int) $this->getRequest()->getParam('rule');

        if ($ruleId <= 0) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('This rule no longer exists.'),
                ]);
            }

            return $resultRedirect->setPath('tax/rule/index');
        }

        try {
            $this->ruleService->deleteById($ruleId);
            $this->messageManager->addSuccess(__('The tax rule has been deleted.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('The tax rule has been deleted.'),
                    'redirectUrl' => $this->getUrl('tax/rule/index'),
                ]);
            }

            return $resultRedirect->setPath('tax/rule/index');
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            $message = (string) __('This rule no longer exists.');
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Exception) {
            $message = (string) __('Something went wrong deleting this tax rule.');
        }

        $this->messageManager->addError($message);

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => $message,
            ]);
        }

        return $resultRedirect->setPath('nebula/taxrule/edit', ['rule' => $ruleId]);
    }
}
