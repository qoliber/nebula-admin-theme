<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Notification;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\View\Result\PageFactory;

class MassMarkAsRead extends AbstractNotification implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_AdminNotification::mark_as_read';

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly InboxFactory $inboxFactory
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): \Magento\Backend\Model\View\Result\Redirect
    {
        $ids = $this->getRequest()->getParam('notification');
        if (!is_array($ids)) {
            $this->messageManager->addErrorMessage(__('Please select messages.'));

            return $this->redirectToIndex();
        }

        try {
            foreach ($ids as $id) {
                $model = $this->inboxFactory->create()->load($id);
                if ($model->getId()) {
                    $model->setIsRead(1)->save();
                }
            }
            $this->messageManager->addSuccessMessage(
                __('A total of %1 record(s) have been marked as Read.', count($ids))
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __("We couldn't mark the notification as Read because of an error.")
            );
        }

        return $this->redirectToIndex();
    }
}
