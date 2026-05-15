<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaMedia\Model\MediaPicker;
use Throwable;

abstract class AbstractJson extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Cms::media_gallery';

    public function __construct(
        Action\Context $context,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly MediaPicker $mediaPicker,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function executeAction(): array;

    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            return $resultJson->setData([
                'success' => true,
                'data' => $this->executeAction(),
            ]);
        } catch (LocalizedException $exception) {
            $resultJson->setHttpResponseCode(400);

            return $resultJson->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->critical($exception);
            $resultJson->setHttpResponseCode(500);

            return $resultJson->setData([
                'success' => false,
                'message' => __('An unexpected media error occurred.'),
            ]);
        }
    }
}
