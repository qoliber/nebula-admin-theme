<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaForm\Model\Validator;

class Validate extends Action implements HttpPostActionInterface
{
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly Validator $validator,
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
            $formId = $body['id'] ?? null;
            $formData = $body['data'] ?? [];

            $definition = $this->definitionResolver->resolve('form', $formId);
            $errors = $this->validator->validate($formData, $definition);

            if (empty($errors)) {
                return $result->setData(['valid' => true]);
            }

            return $result->setData(['valid' => false, 'errors' => $errors]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula Form/Validate failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['valid' => false, 'message' => 'Validation request failed.']);
        }
    }

    /**
     * Magento backend ACL hook — see Save::_isAllowed for the underscore note.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        try {
            /** @var \Magento\Framework\App\Request\Http $request */
            $request = $this->getRequest();
            $body = json_decode((string) $request->getContent(), true);
            $formId = $body['id'] ?? null;
            $definition = $this->definitionResolver->resolve('form', $formId);
            $acl = $definition['acl'] ?? 'Magento_Backend::admin';

            return $this->_authorization->isAllowed($acl);
        } catch (\Exception $e) {
            return false;
        }
    }
}
