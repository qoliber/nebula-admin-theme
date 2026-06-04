<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Tax\Rate\Toolbar;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Tax\Block\Adminhtml\Rate\Form as RateForm;

class Save extends \Magento\Tax\Block\Adminhtml\Rate\Toolbar\Save
{
    /**
     * @var string
     */
    protected $_template = 'Qoliber_NebulaTheme::tax/rate/save.phtml';

    protected function _prepareLayout()
    {
        $this->getFormBlock();

        return $this;
    }

    public function getHeaderText(): string
    {
        return (string) ($this->getData('header') ?? '');
    }

    public function getFormBlock(): ?AbstractBlock
    {
        $form = $this->getData('form');
        if (!$form instanceof AbstractBlock) {
            return null;
        }

        if ($form instanceof RateForm) {
            $form->setShowLegend(false);
        }

        return $form;
    }

    public function getRenderedFormHtml(): string
    {
        $form = $this->getFormBlock();

        return $form ? $form->toHtml() : '';
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('tax/*/');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl(
            'tax/*/delete',
            ['rate' => (int) $this->getRequest()->getParam('rate')]
        );
    }

    public function isEditMode(): bool
    {
        return (int) $this->getRequest()->getParam('rate') > 0;
    }
}
