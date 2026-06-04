<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Rating;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends AbstractRating implements HttpPostActionInterface
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ratingId = (int) $this->getRequest()->getParam('id');

        if ($ratingId <= 0) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('We can\'t delete this rating because of an incorrect rating ID.'),
                ]);
            }

            return $resultRedirect->setPath('review/rating/index');
        }

        try {
            $this->ratingFactory->create()->load($ratingId)->delete();
            $this->messageManager->addSuccessMessage(__('You deleted the rating.'));

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You deleted the rating.'),
                    'redirectUrl' => $this->getUrl('review/rating/index'),
                ]);
            }

            return $resultRedirect->setPath('review/rating/index');
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $this->messageManager->addErrorMessage($message);

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => $message,
                ]);
            }

            return $resultRedirect->setPath('nebula/rating/edit', ['id' => $ratingId]);
        }
    }
}
