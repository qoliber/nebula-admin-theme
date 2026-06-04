<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Block\Adminhtml\Variable;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Variable\Model\VariableFactory;

class Manager extends Template
{
    protected $_template = 'Qoliber_NebulaSystem::variable/manager.phtml';

    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly VariableFactory $variableFactory,
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

        $formBlock->setData('action', $this->getSaveUrl());

        return $formBlock->toHtml();
    }

    public function getCurrentVariable(): \Magento\Variable\Model\Variable
    {
        $variable = $this->registry->registry('current_variable');

        if ($variable instanceof \Magento\Variable\Model\Variable) {
            return $variable;
        }

        return $this->variableFactory->create();
    }

    public function isEditing(): bool
    {
        return (bool) $this->getCurrentVariable()->getId();
    }

    public function shouldOpenModal(): bool
    {
        return in_array($this->getRequest()->getActionName(), ['edit', 'new'], true);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('nebulasystem/variable/index');
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('nebulasystem/variable/save', ['_current' => true, 'back' => null]);
    }

    public function getValidationUrl(): string
    {
        return $this->getUrl('nebulasystem/variable/validate', ['_current' => true]);
    }

    public function getDeleteUrl(): string
    {
        if (!$this->isEditing()) {
            return '';
        }

        return $this->getUrl(
            'nebulasystem/variable/delete',
            ['variable_id' => (int) $this->getCurrentVariable()->getId()]
        );
    }

    public function getEditTitle(): string
    {
        return $this->isEditing()
            ? (string) __('Edit Custom Variable')
            : (string) __('Add New Variable');
    }
}
