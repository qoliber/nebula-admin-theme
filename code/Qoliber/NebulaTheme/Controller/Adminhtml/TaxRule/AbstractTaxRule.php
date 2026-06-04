<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRule;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Magento\Tax\Controller\Adminhtml\Rule;
use Qoliber\NebulaForm\Block\Form;

abstract class AbstractTaxRule extends Rule
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        TaxRuleRepositoryInterface $ruleService,
        \Magento\Tax\Api\Data\TaxRuleInterfaceFactory $taxRuleDataObjectFactory,
        protected readonly PageFactory $resultPageFactory,
        protected readonly LayoutFactory $layoutFactory,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly Session $backendSession
    ) {
        parent::__construct($context, $coreRegistry, $ruleService, $taxRuleDataObjectFactory);
    }

    protected function createPage(string $title): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaMenu::stores_tax_rules')
            ->addBreadcrumb(__('Stores'), __('Stores'))
            ->addBreadcrumb(__('Taxes'), __('Taxes'))
            ->addBreadcrumb(__('Tax Rules'), __('Tax Rules'));
        $resultPage->getConfig()->getTitle()->prepend(__('Tax Rules'));
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }

    protected function initRule(bool $required = false): ?TaxRuleInterface
    {
        $ruleId = (int) $this->getRequest()->getParam('rule');
        if ($ruleId <= 0) {
            return $required ? null : null;
        }

        $this->registerValue('tax_rule_id', $ruleId);

        try {
            return $this->ruleService->get($ruleId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    protected function registerFormDataFromSession(): void
    {
        $this->registerValue('tax_rule_form_data', (array) $this->backendSession->getRuleData(true));
    }

    protected function renderFormHtml(): string
    {
        $layout = $this->layoutFactory->create();
        /** @var Form|false $block */
        $block = $layout->createBlock(Form::class, 'nebula.tax.rule.form');
        if ($block === false) {
            return '';
        }

        $block->setData('form_id', 'tax_rule_edit');

        return $block->toHtml();
    }

    protected function createFormPayload(bool $isEditing, ?string $code = null): array
    {
        $description = $isEditing
            ? (string) __('Editing tax rule %1.', $code ?: __('selected record'))
            : (string) __('Create a tax rule without leaving the listing view.');

        return [
            'success' => true,
            'isEditing' => $isEditing,
            'title' => $isEditing ? (string) __('Edit Tax Rule') : (string) __('Add New Tax Rule'),
            'description' => $description,
            'html' => $this->renderFormHtml(),
            'loadUrl' => $isEditing
                ? $this->getUrl('nebula/taxrule/edit', ['rule' => (int) $this->getRequest()->getParam('rule')])
                : $this->getUrl('nebula/taxrule/new'),
            'deleteUrl' => $isEditing
                ? $this->getUrl('nebula/taxrule/delete', ['rule' => (int) $this->getRequest()->getParam('rule')])
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
