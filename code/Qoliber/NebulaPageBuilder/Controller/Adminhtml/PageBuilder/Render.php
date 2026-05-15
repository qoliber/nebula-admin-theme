<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Controller\Adminhtml\PageBuilder;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaPageBuilder\Api\MasterFormatRendererInterface;

class Render extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Backend::admin';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MasterFormatRendererInterface $renderer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        try {
            /** @var \Magento\Framework\App\Request\Http $request */
            $request = $this->getRequest();
            $treeJson = $request->getContent();
            $tree = json_decode($treeJson, true);

            if (!is_array($tree)) {
                return $result->setData(['error' => true, 'message' => 'Invalid tree JSON']);
            }

            $html = $this->renderer->render($tree);

            return $result->setData(['html' => $html]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula PageBuilder/Render failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['error' => true, 'message' => 'Render failed.']);
        }
    }
}
