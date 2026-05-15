<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaForm\Model\Form\AllowedFieldsCollector;
use Qoliber\NebulaForm\Model\Validator;

class Save extends Action implements HttpPostActionInterface
{
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly ObjectManagerInterface $objectManager,
        private readonly Validator $validator,
        private readonly AllowedFieldsCollector $allowedFieldsCollector,
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
            $entityId = $body['entityId'] ?? null;
            $formData = $body['data'] ?? [];

            $definition = $this->definitionResolver->resolve('form', $formId);

            $errors = $this->validator->validate($formData, $definition);

            if (!empty($errors)) {
                return $result->setData(['success' => false, 'errors' => $errors]);
            }

            // Whitelist filter: drop any request keys the form definition
            // doesn't declare. Without this addData() / setData() below would
            // accept arbitrary keys from a tampered request body.
            $allowed = array_flip($this->allowedFieldsCollector->collect($definition));
            $formData = is_array($formData) ? array_intersect_key($formData, $allowed) : [];

            $repositoryClass = $definition['dataSource']['repository'] ?? null;

            if (!$repositoryClass) {
                return $result->setData(['success' => false, 'message' => 'No repository configured']);
            }

            $repository = $this->objectManager->get($repositoryClass);

            if ($entityId) {
                $identifierField = $definition['dataSource']['identifierField'] ?? 'entity_id';
                $entity = $repository->getById($entityId);
                $entity->addData($formData);
            } else {
                $modelClass = $definition['dataSource']['model'] ?? null;
                $entity = $this->objectManager->create($modelClass);
                $entity->setData($formData);
            }

            $savedEntity = $repository->save($entity);
            $savedEntityId = $savedEntity->getId();

            return $result->setData(['success' => true, 'entityId' => $savedEntityId]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula Form/Save failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData([
                'success' => false,
                'message' => 'Could not save the record. Check the admin error log.',
            ]);
        }
    }

    /**
     * Magento backend ACL hook. The leading underscore is required by the
     * \Magento\Backend\App\AbstractAction contract — phpcs PSR-12 flags it,
     * but renaming would break the framework override.
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
