<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRule;

class Save extends AbstractTaxRule
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $postData = $this->getRequest()->getPostValue();

        if (!$postData) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('No tax rule data was submitted.'),
                ]);
            }

            return $resultRedirect->setPath('tax/rule/index');
        }

        $postData['calculate_subtotal'] = (int) $this->getRequest()->getParam('calculate_subtotal', 0);
        $ruleId = (int) ($postData['tax_calculation_rule_id'] ?? 0);

        try {
            $taxRule = $this->populateTaxRule($postData);
            $savedRule = $this->ruleService->save($taxRule);

            $this->messageManager->addSuccess(__('You saved the tax rule.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You saved the tax rule.'),
                    'redirectUrl' => $this->getUrl('tax/rule/index'),
                    'entityId' => (int) $savedRule->getId(),
                ]);
            }

            return $resultRedirect->setPath('tax/rule/index');
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Exception) {
            $message = (string) __('We can\'t save this tax rule right now.');
        }

        $this->backendSession->setRuleData($postData);
        $this->registerFormDataFromSession();

        if ($ruleId > 0) {
            $this->registerValue('tax_rule_id', $ruleId);
        }

        $this->messageManager->addError($message);

        if ($this->isAjaxRequest()) {
            $payload = $this->createFormPayload($ruleId > 0);
            $payload['success'] = false;
            $payload['message'] = $message;

            return $this->resultJsonFactory->create()->setData($payload);
        }

        return $resultRedirect->setPath(
            $ruleId > 0 ? 'nebula/taxrule/edit' : 'nebula/taxrule/new',
            $ruleId > 0 ? ['rule' => $ruleId] : []
        );
    }
}
