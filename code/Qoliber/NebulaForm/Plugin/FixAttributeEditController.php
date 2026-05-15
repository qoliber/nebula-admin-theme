<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Plugin;

use Magento\Catalog\Controller\Adminhtml\Product\Attribute\Edit;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

/**
 * Around-plugin on the core product-attribute edit controller that replaces
 * the upstream UI-Component-based render with a Nebula one.
 *
 * Keeps the runtime small: all dependencies are constructor-injected so the
 * plugin never reaches into ObjectManager.
 */
class FixAttributeEditController
{
    public function __construct(
        private readonly AttributeFactory $attributeFactory,
        private readonly EavConfig $eavConfig,
        private readonly Registry $registry,
        private readonly MessageManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly PageFactory $pageFactory
    ) {
    }

    public function aroundExecute(Edit $subject, callable $proceed): mixed
    {
        $id = $subject->getRequest()->getParam('attribute_id');

        /** @var Attribute $model */
        $model = $this->attributeFactory->create()
            ->setEntityTypeId(
                $this->eavConfig->getEntityType(Product::ENTITY)->getId()
            );

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This attribute no longer exists.'));
                return $this->redirectFactory->create()->setPath('catalog/*/');
            }
        }

        if (!$this->registry->registry('entity_attribute')) {
            $this->registry->register('entity_attribute', $model);
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Magento_Catalog::catalog_attributes_attributes');
        $resultPage->addBreadcrumb(__('Catalog'), __('Catalog'));
        $resultPage->addBreadcrumb(__('Manage Product Attributes'), __('Manage Product Attributes'));
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? $model->getFrontendLabel() : __('New Product Attribute')
        );

        return $resultPage;
    }
}
