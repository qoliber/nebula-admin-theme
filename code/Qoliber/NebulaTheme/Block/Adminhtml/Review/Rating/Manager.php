<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block\Adminhtml\Review\Rating;

use Magento\Review\Block\Adminhtml\Rating;

class Manager extends Rating
{
    protected $_template = 'Qoliber_NebulaTheme::review/rating/manager.phtml';

    protected function _construct()
    {
        parent::_construct();
        $this->removeButton('add');
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('nebula/rating/new');
    }

    public function getGridHtml(): string
    {
        return parent::getGridHtml();
    }

    public function getFormHtml(): string
    {
        if (!$this->shouldOpenModal()) {
            return '';
        }

        $block = $this->getLayout()->createBlock(Form::class, 'nebula.review.rating.form');

        return $block ? $block->toHtml() : '';
    }

    public function shouldOpenModal(): bool
    {
        return in_array($this->getRequest()->getActionName(), ['edit', 'new'], true);
    }

    public function shouldRedirectOnClose(): bool
    {
        return $this->shouldOpenModal() && str_starts_with($this->getRequest()->getFullActionName(), 'nebula_rating_');
    }

    public function isEditing(): bool
    {
        return (int) $this->getRequest()->getParam('id') > 0;
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('review/rating/index');
    }

    public function getNewUrl(): string
    {
        return $this->getUrl('nebula/rating/new');
    }

    public function getEditUrlTemplate(): string
    {
        return $this->getUrl('nebula/rating/edit', ['id' => '__ID__']);
    }

    public function getRouteBasePath(): string
    {
        $path = (string) parse_url($this->getNewUrl(), PHP_URL_PATH);

        return (string) preg_replace('#/new/.*$#', '/', $path);
    }

    public function getLegacyRouteBasePath(): string
    {
        $path = (string) parse_url($this->getUrl('review/rating/edit'), PHP_URL_PATH);

        return (string) preg_replace('#/edit/.*$#', '/', $path);
    }

    public function getCurrentLoadUrl(): string
    {
        if (!$this->shouldOpenModal()) {
            return '';
        }

        if ($this->isEditing()) {
            return $this->getUrl('nebula/rating/edit', ['id' => (int) $this->getRequest()->getParam('id')]);
        }

        return $this->getNewUrl();
    }

    public function getDeleteUrl(): string
    {
        if (!$this->isEditing()) {
            return '';
        }

        return $this->getUrl('nebula/rating/delete', ['id' => (int) $this->getRequest()->getParam('id')]);
    }

    public function getModalTitle(): string
    {
        return $this->isEditing()
            ? (string) __('Edit Rating')
            : (string) __('Add New Rating');
    }

    public function getModalDescription(): string
    {
        if (!$this->isEditing()) {
            return (string) __('Create a rating without leaving the listing view.');
        }

        $code = $this->getCurrentRatingCode();

        return $code !== ''
            ? (string) __('Editing rating %1.', $code)
            : (string) __('Update this rating without leaving the listing view.');
    }

    private function getCurrentRatingCode(): string
    {
        $rating = $this->_coreRegistry->registry('rating_data');

        if ($rating === null || !$rating->getId()) {
            return '';
        }

        return (string) $rating->getRatingCode();
    }
}
