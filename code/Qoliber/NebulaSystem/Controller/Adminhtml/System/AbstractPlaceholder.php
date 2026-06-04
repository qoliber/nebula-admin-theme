<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Qoliber\NebulaSystem\Block\Adminhtml\System\Placeholder;
use Qoliber\NebulaSystem\Model\PageConfigProvider;

abstract class AbstractPlaceholder extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly PageConfigProvider $pageConfigProvider
    ) {
        parent::__construct($context);
    }

    abstract protected function getPageCode(): string;

    public function execute(): ResultInterface
    {
        $pageDefinition = $this->pageConfigProvider->get($this->getPageCode());
        $resultPage = $this->pageFactory->create();

        $resultPage->setActiveMenu($pageDefinition['menu_id']);
        $resultPage->getConfig()->getTitle()->prepend(__($pageDefinition['title']));
        $resultPage->addContent(
            $resultPage->getLayout()->createBlock(Placeholder::class)->setData('page_definition', $pageDefinition)
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        $pageDefinition = $this->pageConfigProvider->get($this->getPageCode());

        return $this->_authorization->isAllowed($pageDefinition['resource']);
    }
}
