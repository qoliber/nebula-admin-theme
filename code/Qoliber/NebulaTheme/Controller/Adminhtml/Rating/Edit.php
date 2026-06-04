<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Rating;

class Edit extends AbstractRating
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->initEntityId();
        $rating = $this->initRating(true);

        if ($rating === null) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('We can\'t find that rating.'),
                    'redirectUrl' => $this->getUrl('review/rating/index'),
                ]);
            }

            return $this->resultRedirectFactory->create()->setPath('review/rating/index');
        }

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData(
                $this->createFormPayload(true, (string) $rating->getRatingCode())
            );
        }

        return $this->createPage((string) $rating->getRatingCode());
    }
}
