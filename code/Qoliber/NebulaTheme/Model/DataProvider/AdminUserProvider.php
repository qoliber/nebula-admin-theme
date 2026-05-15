<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\User\Model\UserFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class AdminUserProvider implements DataProviderInterface
{
    public function __construct(
        private readonly UserFactory $userFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $user = $this->userFactory->create()->load((int) $entityId);

        if (!$user->getId()) {
            return [];
        }

        return [
            'user_id' => $user->getId(),
            'username' => $user->getUserName(),
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'email' => $user->getEmail(),
            'interface_locale' => $user->getInterfaceLocale(),
            'is_active' => (string) $user->getIsActive(),
        ];
    }
}
