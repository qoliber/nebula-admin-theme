<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

use Magento\Framework\Exception\NoSuchEntityException;

class Save extends AbstractTaxRate
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ratePost = $this->getRequest()->getPostValue();

        if (!$ratePost) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('No tax rate data was submitted.'),
                ]);
            }

            return $resultRedirect->setPath('tax/rate/index');
        }

        $ratePost = $this->_processRateData($ratePost);
        $rateId = (int) ($this->getRequest()->getParam('tax_calculation_rate_id') ?: 0);
        if ($rateId > 0) {
            try {
                $this->_taxRateRepository->get($rateId);
            } catch (NoSuchEntityException) {
                unset($ratePost['tax_calculation_rate_id']);
                $rateId = 0;
            }
        }

        try {
            $taxData = $this->_taxRateConverter->populateTaxRateData($ratePost);
            $savedRate = $this->_taxRateRepository->save($taxData);

            $this->messageManager->addSuccess(__('You saved the tax rate.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You saved the tax rate.'),
                    'redirectUrl' => $this->getUrl('tax/rate/index'),
                    'entityId' => (int) $savedRate->getId(),
                ]);
            }

            return $resultRedirect->setPath('tax/rate/index');
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $this->backendSession->setFormData($ratePost);
            $this->registerFormDataFromSession();

            if ($rateId > 0) {
                $this->registerValue(\Magento\Tax\Controller\RegistryConstants::CURRENT_TAX_RATE_ID, $rateId);
            }

            $this->messageManager->addError($exception->getMessage());

            if ($this->isAjaxRequest()) {
                $payload = $this->createFormPayload($rateId > 0);
                $payload['success'] = false;
                $payload['message'] = $exception->getMessage();

                return $this->resultJsonFactory->create()->setData($payload);
            }

            return $resultRedirect->setPath(
                $rateId > 0 ? 'nebula/taxrate/edit' : 'nebula/taxrate/new',
                $rateId > 0 ? ['rate' => $rateId] : []
            );
        } catch (\Exception $exception) {
            $this->backendSession->setFormData($ratePost);
            $this->registerFormDataFromSession();

            if ($rateId > 0) {
                $this->registerValue(\Magento\Tax\Controller\RegistryConstants::CURRENT_TAX_RATE_ID, $rateId);
            }

            $this->messageManager->addError($exception->getMessage());

            if ($this->isAjaxRequest()) {
                $payload = $this->createFormPayload($rateId > 0);
                $payload['success'] = false;
                $payload['message'] = $exception->getMessage();

                return $this->resultJsonFactory->create()->setData($payload);
            }

            return $resultRedirect->setPath(
                $rateId > 0 ? 'nebula/taxrate/edit' : 'nebula/taxrate/new',
                $rateId > 0 ? ['rate' => $rateId] : []
            );
        }
    }
}
