<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Delete extends AbstractAgreement implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Backend\Model\Session $backendSession,
        \Magento\CheckoutAgreements\Model\AgreementFactory $agreementFactory,
        private readonly CheckoutAgreementsRepositoryInterface $agreementRepository
    ) {
        parent::__construct(
            $context,
            $coreRegistry,
            $resultPageFactory,
            $layoutFactory,
            $resultJsonFactory,
            $backendSession,
            $agreementFactory
        );
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');

        if ($id <= 0) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('This condition no longer exists.'),
                ]);
            }

            return $resultRedirect->setPath('nebula/agreement/index');
        }

        try {
            $agreement = $this->agreementRepository->get($id);
            if (!$agreement->getAgreementId()) {
                throw new LocalizedException(__('This condition no longer exists.'));
            }

            $this->agreementRepository->delete($agreement);
            $this->messageManager->addSuccessMessage(__('You deleted the condition.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You deleted the condition.'),
                    'redirectUrl' => $this->getUrl('nebula/agreement/index'),
                ]);
            }

            return $resultRedirect->setPath('nebula/agreement/index');
        } catch (LocalizedException $exception) {
            $message = $exception->getMessage();
        } catch (\Exception) {
            $message = (string) __('Something went wrong while deleting this condition.');
        }

        $this->messageManager->addErrorMessage($message);

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => $message,
            ]);
        }

        return $resultRedirect->setPath('nebula/agreement/edit', ['id' => $id]);
    }
}
