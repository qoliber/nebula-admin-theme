<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Delete extends AbstractTaxRate implements HttpPostActionInterface
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $rateId = (int) $this->getRequest()->getParam('rate');

        if ($rateId <= 0) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('We can\'t delete this rate because of an incorrect rate ID.'),
                ]);
            }

            return $resultRedirect->setPath('tax/rate/index');
        }

        try {
            $this->_taxRateRepository->deleteById($rateId);
            $this->messageManager->addSuccess(__('You deleted the tax rate.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You deleted the tax rate.'),
                    'redirectUrl' => $this->getUrl('tax/rate/index'),
                ]);
            }

            return $resultRedirect->setPath('tax/rate/index');
        } catch (NoSuchEntityException) {
            $message = (string) __('We can\'t delete this rate because of an incorrect rate ID.');
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Exception) {
            $message = (string) __('Something went wrong deleting this rate.');
        }

        $this->messageManager->addError($message);

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => $message,
            ]);
        }

        return $resultRedirect->setPath('nebula/taxrate/edit', ['rate' => $rateId]);
    }
}
