<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Notification;

use Magento\AdminNotification\Model\NotificationService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;

class MarkAsRead extends AbstractNotification implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_AdminNotification::mark_as_read';

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly NotificationService $notificationService
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): \Magento\Backend\Model\View\Result\Redirect
    {
        $notificationId = (int) $this->getRequest()->getParam('id');
        if ($notificationId) {
            try {
                $this->notificationService->markAsRead($notificationId);
                $this->messageManager->addSuccessMessage(__('The message has been marked as Read.'));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __("We couldn't mark the notification as Read because of an error.")
                );
            }
        }

        return $this->redirectToIndex();
    }
}
