<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Checkout\Agreement;

use Magento\CheckoutAgreements\Block\Adminhtml\Agreement;
use Qoliber\NebulaGrid\Block\Grid;

class Manager extends Agreement
{
    protected $_template = 'Qoliber_NebulaTheme::checkout/agreement/manager.phtml';

    protected function _construct()
    {
        parent::_construct();
        $this->removeButton('add');
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('nebula/agreement/new');
    }

    public function getGridHtml(): string
    {
        $block = $this->getLayout()->createBlock(
            Grid::class,
            'nebula.checkout.agreement.grid',
            ['data' => ['grid_id' => 'agreement_listing']]
        );

        return $block ? $block->toHtml() : '';
    }

    public function getFormHtml(): string
    {
        if (!$this->shouldOpenModal()) {
            return '';
        }

        $block = $this->getLayout()->createBlock(Form::class, 'nebula.checkout.agreement.form');

        return $block ? $block->toHtml() : '';
    }

    public function shouldOpenModal(): bool
    {
        return in_array($this->getRequest()->getActionName(), ['edit', 'new'], true);
    }

    public function shouldRedirectOnClose(): bool
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        $fullActionName = (string) $request->getFullActionName();

        return $this->shouldOpenModal() && str_starts_with($fullActionName, 'nebula_agreement_');
    }

    public function isEditing(): bool
    {
        return (int) $this->getRequest()->getParam('id') > 0;
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('nebula/agreement/index');
    }

    public function getNewUrl(): string
    {
        return $this->getUrl('nebula/agreement/new');
    }

    public function getEditUrlTemplate(): string
    {
        return $this->getUrl('nebula/agreement/edit', ['id' => '__ID__']);
    }

    public function getRouteBasePath(): string
    {
        $path = (string) parse_url($this->getNewUrl(), PHP_URL_PATH);

        return (string) preg_replace('#/new/.*$#', '/', $path);
    }

    public function getLegacyRouteBasePath(): string
    {
        $path = (string) parse_url($this->getUrl('checkout/agreement/edit'), PHP_URL_PATH);

        return (string) preg_replace('#/edit/.*$#', '/', $path);
    }

    public function getCurrentLoadUrl(): string
    {
        if (!$this->shouldOpenModal()) {
            return '';
        }

        if ($this->isEditing()) {
            return $this->getUrl('nebula/agreement/edit', ['id' => (int) $this->getRequest()->getParam('id')]);
        }

        return $this->getNewUrl();
    }

    public function getDeleteUrl(): string
    {
        if (!$this->isEditing()) {
            return '';
        }

        return $this->getUrl('nebula/agreement/delete', ['id' => (int) $this->getRequest()->getParam('id')]);
    }

    public function getModalTitle(): string
    {
        return $this->isEditing()
            ? (string) __('Edit Condition')
            : (string) __('Add New Condition');
    }

    public function getModalDescription(): string
    {
        if (!$this->isEditing()) {
            return (string) __('Create terms and conditions without leaving the listing view.');
        }

        $name = $this->getCurrentAgreementName();

        return $name !== ''
            ? (string) __('Editing condition %1.', $name)
            : (string) __('Update this condition without leaving the listing view.');
    }

    private function getCurrentAgreementName(): string
    {
        $agreement = $this->_coreRegistry->registry('checkout_agreement');

        if ($agreement === null || !$agreement->getId()) {
            return '';
        }

        return (string) $agreement->getName();
    }
}
