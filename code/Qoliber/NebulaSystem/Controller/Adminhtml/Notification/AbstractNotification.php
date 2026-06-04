<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Notification;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

abstract class AbstractNotification extends Action
{
    public const ADMIN_RESOURCE = 'Magento_AdminNotification::show_list';

    public function __construct(
        Context $context,
        protected readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    protected function createPage(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaSystem::system_adminnotification');
        $resultPage->addBreadcrumb(__('Messages Inbox'), __('Messages Inbox'));

        return $resultPage;
    }

    protected function redirectToIndex(): \Magento\Backend\Model\View\Result\Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('nebulasystem/notification/index');
    }
}
