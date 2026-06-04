<?php

declare(strict_types=1);

namespace Qoliber\NebulaUser\Model\DataProvider;

use Magento\Authorization\Model\RoleFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

/**
 * Loads an admin role (by `rid` / `role_id`) for the `admin_role_edit` form.
 * The form only needs scalar role-info fields; the resource tree and the
 * in-role users grid are rendered by snippets that pull their own data
 * (the ViewModels read the role id from the request, so they don't need a
 * payload through the data provider).
 */
class AdminRoleProvider implements DataProviderInterface
{
    public function __construct(
        private readonly RoleFactory $roleFactory
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $role = $this->roleFactory->create();
        $role->load((int) $entityId);

        if (!$role->getId()) {
            return [];
        }

        return [
            'role_id'   => (string) $role->getId(),
            'rolename'  => (string) $role->getRoleName(),
            'role_name' => (string) $role->getRoleName(),
        ];
    }
}
