<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Rating;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Review\Controller\Adminhtml\Rating;
use Magento\Review\Model\RatingFactory;
use Qoliber\NebulaTheme\Block\Adminhtml\Review\Rating\Form as RatingForm;

abstract class AbstractRating extends Rating
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        protected readonly PageFactory $resultPageFactory,
        protected readonly LayoutFactory $layoutFactory,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly Session $backendSession,
        protected readonly RatingFactory $ratingFactory
    ) {
        parent::__construct($context, $coreRegistry);
    }

    protected function createPage(string $title): \Magento\Backend\Model\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qoliber_NebulaMenu::stores_rating')
            ->addBreadcrumb(__('Stores'), __('Stores'))
            ->addBreadcrumb(__('Attributes'), __('Attributes'))
            ->addBreadcrumb(__('Ratings'), __('Ratings'));
        $resultPage->getConfig()->getTitle()->prepend(__('Ratings'));
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }

    protected function initRating(bool $required = false): ?\Magento\Review\Model\Rating
    {
        $ratingId = (int) $this->getRequest()->getParam('id');
        if ($ratingId <= 0) {
            return $required ? null : null;
        }

        $rating = $this->ratingFactory->create()->load($ratingId);
        if (!$rating->getId()) {
            return null;
        }

        $this->registerValue('rating_data', $rating);

        return $rating;
    }

    protected function renderFormHtml(): string
    {
        $layout = $this->layoutFactory->create();
        $block = $layout->createBlock(RatingForm::class, 'nebula.review.rating.form');

        return $block ? $block->toHtml() : '';
    }

    protected function createFormPayload(bool $isEditing, ?string $code = null): array
    {
        $description = $isEditing
            ? (string) __('Editing rating %1.', $code ?: __('selected record'))
            : (string) __('Create a rating without leaving the listing view.');

        return [
            'success' => true,
            'isEditing' => $isEditing,
            'title' => $isEditing ? (string) __('Edit Rating') : (string) __('Add New Rating'),
            'description' => $description,
            'html' => $this->renderFormHtml(),
            'loadUrl' => $isEditing
                ? $this->getUrl('nebula/rating/edit', ['id' => (int) $this->getRequest()->getParam('id')])
                : $this->getUrl('nebula/rating/new'),
            'deleteUrl' => $isEditing
                ? $this->getUrl('nebula/rating/delete', ['id' => (int) $this->getRequest()->getParam('id')])
                : '',
        ];
    }

    protected function isAjaxRequest(): bool
    {
        return strtolower((string) $this->getRequest()->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }

    protected function registerValue(string $key, mixed $value): void
    {
        if ($this->coreRegistry->registry($key) !== null) {
            $this->coreRegistry->unregister($key);
        }

        $this->coreRegistry->register($key, $value);
    }
}
