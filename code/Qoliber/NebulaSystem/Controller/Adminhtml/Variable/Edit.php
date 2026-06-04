<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

class Edit extends AbstractVariable
{
    public function execute(): \Magento\Backend\Model\View\Result\Page
    {
        $variable = $this->initVariable();

        $resultPage = $this->createPage();
        $resultPage->getConfig()->getTitle()->prepend(__('Custom Variables'));
        $resultPage->getConfig()->getTitle()->prepend(
            $variable->getId() ? $variable->getCode() : __('New Custom Variable')
        );

        return $resultPage;
    }
}
