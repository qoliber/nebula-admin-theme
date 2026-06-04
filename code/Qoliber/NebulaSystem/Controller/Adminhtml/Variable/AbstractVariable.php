<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;

abstract class AbstractVariable extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Variable::variable';

    public function __construct(
        Context $context,
        protected readonly Registry $coreRegistry,
        protected readonly ForwardFactory $resultForwardFactory,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly PageFactory $resultPageFactory,
        protected readonly LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
    }

    protected function createPage(): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaSystem::system_variable')
            ->addBreadcrumb(__('Custom Variables'), __('Custom Variables'));

        return $resultPage;
    }

    protected function initVariable(): \Magento\Variable\Model\Variable
    {
        $variableId = $this->getRequest()->getParam('variable_id');
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        /** @var \Magento\Variable\Model\Variable $variable */
        $variable = $this->_objectManager->create(\Magento\Variable\Model\Variable::class);

        if ($variableId) {
            $variable->setStoreId($storeId)->load((int) $variableId);
        }

        $this->coreRegistry->register('current_variable', $variable);

        return $variable;
    }
}
