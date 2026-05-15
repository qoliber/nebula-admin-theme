<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;

class Load extends Action implements HttpGetActionInterface
{
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        try {
            $request = $this->getRequest();
            $formId = $request->getParam('id');
            $entityId = $request->getParam('entityId');
            $definition = $this->definitionResolver->resolve('form', $formId);

            $providerClass = $definition['dataSource']['provider'] ?? null;

            if (!$providerClass) {
                return $result->setData(['success' => false, 'message' => 'No data provider configured']);
            }

            $provider = $this->objectManager->get($providerClass);
            $data = $provider->getData($definition['dataSource'] ?? [], ['entityId' => $entityId]);

            return $result->setData(['data' => $data, 'success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula Form/Load failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['success' => false, 'message' => 'Could not load form data.']);
        }
    }

    /**
     * Magento backend ACL hook — leading underscore is required by the
     * \Magento\Backend\App\AbstractAction contract.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        $formId = $this->getRequest()->getParam('id');

        try {
            $definition = $this->definitionResolver->resolve('form', $formId);
            $acl = $definition['acl'] ?? 'Magento_Backend::admin';

            return $this->_authorization->isAllowed($acl);
        } catch (\Exception $e) {
            return false;
        }
    }
}
