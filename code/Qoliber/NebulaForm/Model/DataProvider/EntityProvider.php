<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\DataProvider;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class EntityProvider implements FormDataProviderInterface
{
    public function __construct(
        // nebula:allow-object-manager dynamic-repository-dispatch — the repository
        // FQCN is declared in form JSON (`dataSource.config.repository`). This is
        // the one place the generic provider has to reach into runtime DI.
        private readonly ObjectManagerInterface $objectManager,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        // Bridge-emitted forms (Slice 2 of NebulaUiBridge V2) declare the
        // upstream Magento DataProvider class directly; try that first when
        // present, fall back to the canonical repository path on failure.
        $providerClass = $config['dataProviderClass'] ?? null;
        if ($providerClass !== null) {
            $providerData = $this->loadFromDataProvider($providerClass, $config, $params);
            if ($providerData !== null) {
                return $providerData;
            }
        }

        $repositoryClass = $config['repository'] ?? null;
        $entityId = $params['entityId'] ?? null;

        if (!$repositoryClass || !$entityId) {
            return [];
        }

        $repository = $this->objectManager->get($repositoryClass);

        try {
            $entity = $repository->getById($entityId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return [];
        }

        if ($entity instanceof AbstractModel) {
            return $entity->getData();
        }

        $data = [];
        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (str_starts_with($name, 'get') && $method->getNumberOfParameters() === 0) {
                $key = lcfirst(substr($name, 3));
                $data[$key] = $method->invoke($entity);
            }
        }

        return $data;
    }

    /**
     * ObjectManager-instantiate a Magento UI Form DataProvider, call getData(),
     * and return the first item shape. Wrapped in try/catch — the bridge's
     * fallback to repository path fires when instantiation or invocation throws.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function loadFromDataProvider(string $providerClass, array $config, array $params): ?array
    {
        try {
            $name = (string) ($config['providerName'] ?? $providerClass);
            $primaryFieldName = (string) ($config['primaryFieldName'] ?? 'entity_id');
            $requestFieldName = (string) ($config['requestFieldName'] ?? 'id');

            $provider = $this->objectManager->create($providerClass, [
                'name' => $name,
                'primaryFieldName' => $primaryFieldName,
                'requestFieldName' => $requestFieldName,
            ]);

            if (!is_object($provider) || !method_exists($provider, 'getData')) {
                return null;
            }

            // UI DataProviders' getData() takes no args and returns
            // [entity_id => [field => value, ...], ...] — pull the first row.
            $data = $provider->getData();
            if (!is_array($data) || $data === []) {
                return [];
            }
            $first = reset($data);
            if (is_array($first)) {
                if (isset($first['general']) && is_array($first['general'])) {
                    // Some EAV-shaped providers nest payload under "general".
                    return $first['general'];
                }
                return $first;
            }
            return [];
        } catch (\Throwable $e) {
            $this->logger?->warning(
                'NebulaForm EntityProvider: failed to instantiate DataProvider ' . $providerClass,
                ['exception' => $e]
            );
            return null;
        }
    }
}
