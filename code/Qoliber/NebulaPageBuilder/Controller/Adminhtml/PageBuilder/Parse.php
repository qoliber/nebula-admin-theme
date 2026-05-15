<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Controller\Adminhtml\PageBuilder;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaPageBuilder\Api\MasterFormatParserInterface;

class Parse extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Backend::admin';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MasterFormatParserInterface $parser,
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
            $body = json_decode((string) $request->getContent(), true);
            $html = $body['html'] ?? '';

            $tree = $this->parser->parse($html);

            return $result->setData(['tree' => $tree]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula PageBuilder/Parse failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['error' => true, 'message' => 'Parse failed.']);
        }
    }
}
