<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\CheckoutAgreements\Controller\Adminhtml\Agreement;
use Magento\CheckoutAgreements\Model\Agreement as AgreementModel;
use Magento\CheckoutAgreements\Model\AgreementFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Qoliber\NebulaTheme\Block\Adminhtml\Checkout\Agreement\Form as AgreementForm;
use Qoliber\NebulaTheme\Block\Adminhtml\Checkout\Agreement\Manager;

abstract class AbstractAgreement extends Agreement
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        protected readonly PageFactory $resultPageFactory,
        protected readonly LayoutFactory $layoutFactory,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly Session $backendSession,
        protected readonly AgreementFactory $agreementFactory
    ) {
        parent::__construct($context, $coreRegistry);
    }

    protected function createPage(string $title): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaMenu::stores_terms_conditions')
            ->addBreadcrumb(__('Stores'), __('Stores'))
            ->addBreadcrumb(__('Settings'), __('Settings'))
            ->addBreadcrumb(__('Terms and Conditions'), __('Terms and Conditions'));
        $resultPage->getConfig()->getTitle()->prepend(__('Terms and Conditions'));
        $resultPage->getConfig()->getTitle()->prepend($title);
        $resultPage->addContent($resultPage->getLayout()->createBlock(Manager::class));

        return $resultPage;
    }

    protected function initAgreement(bool $required = false): ?AgreementModel
    {
        $agreementId = (int) $this->getRequest()->getParam('id');
        $agreement = $this->agreementFactory->create();

        if ($agreementId > 0) {
            $agreement->load($agreementId);
            if (!$agreement->getId()) {
                return null;
            }
        } elseif ($required) {
            return null;
        }

        $data = $this->backendSession->getAgreementData(true);
        if (is_array($data) && $data !== []) {
            $agreement->setData($data);
        }

        $this->registerValue('checkout_agreement', $agreement);

        return $agreement;
    }

    protected function renderFormHtml(): string
    {
        $layout = $this->layoutFactory->create();
        $block = $layout->createBlock(AgreementForm::class, 'nebula.checkout.agreement.form');

        return $block ? $block->toHtml() : '';
    }

    protected function createFormPayload(bool $isEditing, ?string $name = null): array
    {
        $description = $isEditing
            ? (string) __('Editing condition %1.', $name ?: __('selected record'))
            : (string) __('Create terms and conditions without leaving the listing view.');

        return [
            'success' => true,
            'isEditing' => $isEditing,
            'title' => $isEditing ? (string) __('Edit Condition') : (string) __('Add New Condition'),
            'description' => $description,
            'html' => $this->renderFormHtml(),
            'loadUrl' => $isEditing
                ? $this->getUrl('nebula/agreement/edit', ['id' => (int) $this->getRequest()->getParam('id')])
                : $this->getUrl('nebula/agreement/new'),
            'deleteUrl' => $isEditing
                ? $this->getUrl('nebula/agreement/delete', ['id' => (int) $this->getRequest()->getParam('id')])
                : '',
        ];
    }

    protected function isAjaxRequest(): bool
    {
        return strtolower((string) $this->getRequest()->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }

    protected function registerValue(string $key, mixed $value): void
    {
        if ($this->_coreRegistry->registry($key) !== null) {
            $this->_coreRegistry->unregister($key);
        }

        $this->_coreRegistry->register($key, $value);
    }
}
