<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends AbstractVariable implements HttpGetActionInterface
{
    public function execute(): \Magento\Backend\Model\View\Result\Page
    {
        $this->initVariable();

        $resultPage = $this->createPage();
        $resultPage->getConfig()->getTitle()->prepend(__('Custom Variables'));

        return $resultPage;
    }
}
