<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class Save extends AbstractAgreement implements HttpPostActionInterface
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $postData = $this->getRequest()->getPostValue();
        $agreementId = (int) ($postData['agreement_id'] ?? 0);

        if (!$postData) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('No condition data was submitted.'),
                ]);
            }

            return $resultRedirect->setPath('nebula/agreement/index');
        }

        $model = $this->agreementFactory->create();
        $model->setData($postData);

        try {
            $validationResult = $model->validateData(new DataObject($postData));
            if ($validationResult !== true) {
                foreach ($validationResult as $message) {
                    $this->messageManager->addErrorMessage($message);
                }

                throw new LocalizedException(__('Please correct the highlighted errors.'));
            }

            $model->save();
            $this->messageManager->addSuccessMessage(__('You saved the condition.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You saved the condition.'),
                    'redirectUrl' => $this->getUrl('nebula/agreement/index'),
                    'entityId' => (int) $model->getId(),
                ]);
            }

            return $resultRedirect->setPath('nebula/agreement/index');
        } catch (LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Exception) {
            $message = (string) __('Something went wrong while saving this condition.');
        }

        $this->messageManager->addErrorMessage($message);
        $this->backendSession->setAgreementData($postData);
        $this->initAgreement();

        if ($this->isAjaxRequest()) {
            $payload = $this->createFormPayload($agreementId > 0, (string) ($postData['name'] ?? ''));
            $payload['success'] = false;
            $payload['message'] = $message;

            return $this->resultJsonFactory->create()->setData($payload);
        }

        return $resultRedirect->setPath(
            $agreementId > 0 ? 'nebula/agreement/edit' : 'nebula/agreement/new',
            $agreementId > 0 ? ['id' => $agreementId] : []
        );
    }
}
