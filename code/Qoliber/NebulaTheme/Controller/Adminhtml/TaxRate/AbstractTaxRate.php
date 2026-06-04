<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Controller\Adminhtml\Rate;
use Magento\Tax\Controller\RegistryConstants;
use Magento\Tax\Model\Calculation\Rate\Converter;
use Qoliber\NebulaTheme\Block\Adminhtml\Tax\Rate\Form as RateForm;

abstract class AbstractTaxRate extends Rate
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Converter $taxRateConverter,
        TaxRateRepositoryInterface $taxRateRepository,
        protected readonly PageFactory $resultPageFactory,
        protected readonly LayoutFactory $layoutFactory,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly Session $backendSession
    ) {
        parent::__construct($context, $coreRegistry, $taxRateConverter, $taxRateRepository);
    }

    protected function createPage(string $title): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaMenu::stores_tax_rates')
            ->addBreadcrumb(__('Stores'), __('Stores'))
            ->addBreadcrumb(__('Taxes'), __('Taxes'))
            ->addBreadcrumb(__('Tax Zones and Rates'), __('Tax Zones and Rates'));
        $resultPage->getConfig()->getTitle()->prepend(__('Tax Zones and Rates'));
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }

    protected function initRate(bool $required = false): ?TaxRateInterface
    {
        $rateId = (int) $this->getRequest()->getParam('rate');
        if ($rateId <= 0) {
            return $required ? null : null;
        }

        $this->registerValue(RegistryConstants::CURRENT_TAX_RATE_ID, $rateId);

        try {
            return $this->_taxRateRepository->get($rateId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    protected function registerFormDataFromSession(): void
    {
        $this->registerValue(
            RegistryConstants::CURRENT_TAX_RATE_FORM_DATA,
            (array) $this->backendSession->getFormData(true)
        );
    }

    protected function renderFormHtml(): string
    {
        $layout = $this->layoutFactory->create();
        $block = $layout->createBlock(RateForm::class, 'nebula.tax.rate.form');

        return $block ? $block->toHtml() : '';
    }

    protected function createFormPayload(bool $isEditing, ?string $code = null): array
    {
        $description = $isEditing
            ? (string) __('Editing tax rate %1.', $code ?: __('selected record'))
            : (string) __('Create a tax rate without leaving the listing view.');

        return [
            'success' => true,
            'isEditing' => $isEditing,
            'title' => $isEditing ? (string) __('Edit Tax Rate') : (string) __('Add New Tax Rate'),
            'description' => $description,
            'html' => $this->renderFormHtml(),
            'loadUrl' => $isEditing
                ? $this->getUrl('nebula/taxrate/edit', ['rate' => (int) $this->getRequest()->getParam('rate')])
                : $this->getUrl('nebula/taxrate/new'),
            'deleteUrl' => $isEditing
                ? $this->getUrl('nebula/taxrate/delete', ['rate' => (int) $this->getRequest()->getParam('rate')])
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
