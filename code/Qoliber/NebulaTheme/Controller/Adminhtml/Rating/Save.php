<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Rating;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Review\Model\Rating\OptionFactory;

class Save extends AbstractRating implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Backend\Model\Session $backendSession,
        \Magento\Review\Model\RatingFactory $ratingFactory,
        private readonly OptionFactory $optionFactory
    ) {
        parent::__construct(
            $context,
            $coreRegistry,
            $resultPageFactory,
            $layoutFactory,
            $resultJsonFactory,
            $backendSession,
            $ratingFactory
        );
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->initEntityId();
        $resultRedirect = $this->resultRedirectFactory->create();
        $post = $this->getRequest()->getPostValue();
        $ratingId = (int) $this->getRequest()->getParam('id');

        if (!$post) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('No rating data was submitted.'),
                ]);
            }

            return $resultRedirect->setPath('review/rating/index');
        }

        try {
            $rating = $this->ratingFactory->create();
            $stores = (array) $this->getRequest()->getParam('stores', []);
            if (!in_array(0, $stores, true)) {
                $stores[] = 0;
            }

            $position = (int) $this->getRequest()->getParam('position');
            $isActive = (bool) $this->getRequest()->getParam('is_active');

            $rating->setRatingCode((string) $this->getRequest()->getParam('rating_code'))
                ->setRatingCodes($this->getRequest()->getParam('rating_codes'))
                ->setStores($stores)
                ->setPosition($position)
                ->setId($ratingId ?: null)
                ->setIsActive($isActive)
                ->setEntityId($this->coreRegistry->registry('entityId'))
                ->save();

            $options = $this->getRequest()->getParam('option_title');
            if (is_array($options)) {
                $i = 1;
                foreach ($options as $key => $optionCode) {
                    $optionModel = $this->optionFactory->create();
                    if (!preg_match('/^add_([0-9]*?)$/', (string) $key)) {
                        $optionModel->setId($key);
                    }

                    $optionModel->setCode($optionCode)
                        ->setValue($i)
                        ->setRatingId($rating->getId())
                        ->setPosition($i)
                        ->save();
                    $i++;
                }
            }

            $this->messageManager->addSuccessMessage(__('You saved the rating.'));
            $this->backendSession->setRatingData(false);

            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => true,
                    'message' => (string) __('You saved the rating.'),
                    'redirectUrl' => $this->getUrl('review/rating/index'),
                    'entityId' => (int) $rating->getId(),
                ]);
            }

            return $resultRedirect->setPath('review/rating/index');
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->backendSession->setRatingData($post);

            if ($ratingId > 0) {
                $this->initRating(true);
            }

            if ($this->isAjaxRequest()) {
                $payload = $this->createFormPayload($ratingId > 0);
                $payload['success'] = false;
                $payload['message'] = $exception->getMessage();

                return $this->resultJsonFactory->create()->setData($payload);
            }

            return $resultRedirect->setPath(
                $ratingId > 0 ? 'nebula/rating/edit' : 'nebula/rating/new',
                $ratingId > 0 ? ['id' => $ratingId] : []
            );
        }
    }
}
