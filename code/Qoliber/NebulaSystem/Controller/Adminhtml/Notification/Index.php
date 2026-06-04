<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Notification;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends AbstractNotification implements HttpGetActionInterface
{
    public function execute(): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->createPage();
        $resultPage->getConfig()->getTitle()->prepend(__('Notifications'));

        return $resultPage;
    }
}
