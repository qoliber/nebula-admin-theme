<?php

declare(strict_types=1);

namespace Qoliber\NebulaUser\Model\DataProvider;

use Magento\User\Model\UserFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

/**
 * Loads an admin user (by `user_id`) into the shape expected by the
 * `admin_user_edit` form. The select for `user_role` reads its options via
 * `optionsSource` (FQCN fallback) — only the currently-selected role is
 * pre-populated here, which is what the form needs.
 *
 * On new records (no entityId) the provider returns an empty array; the
 * Form block then falls back to the JSON-declared `defaults` (e.g.
 * `is_active = 1`) so the toggle starts in the right position.
 */
class AdminUserProvider implements DataProviderInterface
{
    public function __construct(
        private readonly UserFactory $userFactory
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
            // Defaults for the create form: new users are active out of the box,
            // mirroring the stock vendor behaviour (see Magento\User\Block\User\
            // Edit\Tab\Main::_prepareForm setting is_active = 1 on new models).
            return [
                'is_active' => '1',
            ];
        }

        $user = $this->userFactory->create();
        $user->load((int) $entityId);

        if (!$user->getId()) {
            return [];
        }

        // The Magento User model exposes role membership via getRole() (single
        // role, mirroring the admin UI's "User Role" radio). Fall back to the
        // first entry of getRoles() if the convenience getter is missing.
        $roleId = '';
        $role = $user->getRole();
        if ($role && $role->getId()) {
            $roleId = (string) $role->getId();
        } else {
            $roles = (array) $user->getRoles();
            if ($roles !== []) {
                $roleId = (string) reset($roles);
            }
        }

        return [
            'user_id'          => (string) $user->getId(),
            'username'         => (string) $user->getUserName(),
            'firstname'        => (string) $user->getFirstName(),
            'lastname'         => (string) $user->getLastName(),
            'email'            => (string) $user->getEmail(),
            'interface_locale' => (string) $user->getInterfaceLocale(),
            'is_active'        => (string) (int) $user->getIsActive(),
            'user_role'        => $roleId,
        ];
    }
}
