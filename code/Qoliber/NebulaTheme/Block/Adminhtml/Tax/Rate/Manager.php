<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Tax\Rate;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Controller\RegistryConstants;

class Manager extends Template
{
    protected $_template = 'Qoliber_NebulaTheme::tax/rate/manager.phtml';

    public function __construct(
        Template\Context $context,
        private readonly Registry $coreRegistry,
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getGridHtml(): string
    {
        return $this->getChildHtml('grid');
    }

    public function getFormHtml(): string
    {
        $formBlock = $this->getChildBlock('form');
        if ($formBlock === false) {
            return '';
        }

        return $formBlock->toHtml();
    }

    public function shouldOpenModal(): bool
    {
        return in_array($this->getRequest()->getActionName(), ['edit', 'new'], true);
    }

    public function shouldRedirectOnClose(): bool
    {
        return $this->shouldOpenModal() && str_starts_with($this->getRequest()->getFullActionName(), 'nebula_taxrate_');
    }

    public function isEditing(): bool
    {
        return (int) $this->getRequest()->getParam('rate') > 0;
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('tax/rate/index');
    }

    public function getNewUrl(): string
    {
        return $this->getUrl('nebula/taxrate/new');
    }

    public function getRouteBasePath(): string
    {
        $path = (string) parse_url($this->getNewUrl(), PHP_URL_PATH);

        return (string) preg_replace('#/new/.*$#', '/', $path);
    }

    public function getCurrentLoadUrl(): string
    {
        if (!$this->shouldOpenModal()) {
            return '';
        }

        if ($this->isEditing()) {
            return $this->getUrl(
                'nebula/taxrate/edit',
                ['rate' => (int) $this->getRequest()->getParam('rate')]
            );
        }

        return $this->getNewUrl();
    }

    public function getDeleteUrl(): string
    {
        if (!$this->isEditing()) {
            return '';
        }

        return $this->getUrl(
            'nebula/taxrate/delete',
            ['rate' => (int) $this->getRequest()->getParam('rate')]
        );
    }

    public function getModalTitle(): string
    {
        return $this->isEditing()
            ? (string) __('Edit Tax Rate')
            : (string) __('Add New Tax Rate');
    }

    public function getModalDescription(): string
    {
        if (!$this->isEditing()) {
            return (string) __('Create a tax rate without leaving the listing view.');
        }

        $code = $this->getCurrentRateCode();

        return $code !== ''
            ? (string) __('Editing tax rate %1.', $code)
            : (string) __('Update this tax rate without leaving the listing view.');
    }

    private function getCurrentRateCode(): string
    {
        $rateId = (int) ($this->coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_ID)
            ?? $this->getRequest()->getParam('rate'));

        if ($rateId <= 0) {
            return '';
        }

        try {
            return (string) $this->taxRateRepository->get($rateId)->getCode();
        } catch (\Throwable) {
            return '';
        }
    }
}
