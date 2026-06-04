<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Tax\Rule;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

class Manager extends Template
{
    protected $_template = 'Qoliber_NebulaTheme::tax/rule/manager.phtml';

    public function __construct(
        Template\Context $context,
        private readonly Registry $coreRegistry,
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
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
        return $this->shouldOpenModal() && str_starts_with($this->getRequest()->getFullActionName(), 'nebula_taxrule_');
    }

    public function isEditing(): bool
    {
        return (int) $this->getRequest()->getParam('rule') > 0;
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('tax/rule/index');
    }

    public function getNewUrl(): string
    {
        return $this->getUrl('nebula/taxrule/new');
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
                'nebula/taxrule/edit',
                ['rule' => (int) $this->getRequest()->getParam('rule')]
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
            'nebula/taxrule/delete',
            ['rule' => (int) $this->getRequest()->getParam('rule')]
        );
    }

    public function getModalTitle(): string
    {
        return $this->isEditing()
            ? (string) __('Edit Tax Rule')
            : (string) __('Add New Tax Rule');
    }

    public function getModalDescription(): string
    {
        if (!$this->isEditing()) {
            return (string) __('Create a tax rule without leaving the listing view.');
        }

        $code = $this->getCurrentRuleCode();

        return $code !== ''
            ? (string) __('Editing tax rule %1.', $code)
            : (string) __('Update this tax rule without leaving the listing view.');
    }

    private function getCurrentRuleCode(): string
    {
        $ruleId = (int) ($this->coreRegistry->registry('tax_rule_id')
            ?? $this->getRequest()->getParam('rule'));

        if ($ruleId <= 0) {
            return '';
        }

        try {
            return (string) $this->taxRuleRepository->get($ruleId)->getCode();
        } catch (\Throwable) {
            return '';
        }
    }
}
