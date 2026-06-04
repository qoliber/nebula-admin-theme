<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Notification;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Remove extends AbstractNotification implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_AdminNotification::adminnotification_remove';

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly InboxFactory $inboxFactory
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): \Magento\Backend\Model\View\Result\Redirect
    {
        $id = (int) $this->getRequest()->getParam('id');
        if (!$id) {
            return $this->redirectToIndex();
        }

        $model = $this->inboxFactory->create()->load($id);
        if (!$model->getId()) {
            return $this->redirectToIndex();
        }

        try {
            $model->setIsRemove(1)->save();
            $this->messageManager->addSuccessMessage(__('The message has been removed.'));
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __("We couldn't remove the messages because of an error.")
            );
        }

        return $this->redirectToIndex();
    }
}
