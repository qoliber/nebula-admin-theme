<?php

declare(strict_types=1);

namespace Qoliber\NebulaUser\ViewModel;

use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\User\Model\ResourceModel\User\Collection as UserCollection;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;

/**
 * Supplies the user list (rendered as a checkbox table) for the role edit
 * page's "Role Users" tab.
 *
 * Returns every admin user (active and inactive) together with the membership
 * snapshot for the current role (the `rid` request param), so the template
 * can:
 *   - pre-check users already assigned, and
 *   - emit the `in_role_user_old` hidden value the SaveRole controller uses
 *     to diff added/removed users on POST.
 *
 * Both the new state (`in_role_user`) and the old state (`in_role_user_old`)
 * are serialised on the client as URL-encoded `userId=&userId=&...` strings
 * because the stock controller parses them with `parse_str()`.
 */
class RoleUsersGrid implements ArgumentInterface
{
    /** @var array<int, array{user_id: string, username: string, firstname: string, lastname: string, email: string, is_active: bool, in_role: bool, current_role_id: string, current_role_name: string}>|null */
    private ?array $users = null;

    /** @var array<int, string>|null */
    private ?array $assignedIds = null;

    /** @var array<string, array{role_id: string, role_name: string}>|null */
    private ?array $userRoleMap = null;

    public function __construct(
        private readonly UserCollectionFactory $userCollectionFactory,
        private readonly RoleFactory $roleFactory,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return array<int, array{user_id: string, username: string, firstname: string, lastname: string, email: string, is_active: bool, in_role: bool}>
     */
    public function getUsers(): array
    {
        if ($this->users !== null) {
            return $this->users;
        }

        $assigned = array_flip($this->getAssignedUserIds());

        /** @var UserCollection $collection */
        $collection = $this->userCollectionFactory->create();
        $collection->setOrder('username', 'asc');

        $roleMap = $this->getUserRoleMap();

        $rows = [];
        foreach ($collection as $user) {
            $id          = (string) $user->getId();
            $current     = $roleMap[$id] ?? null;
            $rows[]      = [
                'user_id'           => $id,
                'username'          => (string) $user->getUserName(),
                'firstname'         => (string) $user->getFirstName(),
                'lastname'          => (string) $user->getLastName(),
                'email'             => (string) $user->getEmail(),
                'is_active'         => (bool) $user->getIsActive(),
                'in_role'           => isset($assigned[$id]),
                'current_role_id'   => $current['role_id']   ?? '',
                'current_role_name' => $current['role_name'] ?? '',
            ];
        }

        return $this->users = $rows;
    }

    /**
     * Returns the role each admin user currently belongs to (Magento allows
     * a user in at most one role at a time). Empty entry → user is roleless.
     *
     * @return array<string, array{role_id: string, role_name: string}>
     */
    public function getUserRoleMap(): array
    {
        if ($this->userRoleMap !== null) {
            return $this->userRoleMap;
        }

        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('authorization_role');

        // Each row in authorization_role with role_type='U' is a (user -> role)
        // assignment; the user's role is the row's parent_id, the role row itself
        // is a sibling with role_type='G' under the same parent_id.
        $select = $connection->select()
            ->from(['ur' => $table], ['user_id', 'parent_id'])
            ->joinLeft(
                ['r' => $table],
                'r.role_id = ur.parent_id AND r.role_type = "G"',
                ['role_name' => 'role_name']
            )
            ->where('ur.role_type = ?', 'U')
            ->where('ur.user_id > 0');

        $map = [];
        foreach ($connection->fetchAll($select) as $row) {
            $map[(string) $row['user_id']] = [
                'role_id'   => (string) $row['parent_id'],
                'role_name' => (string) ($row['role_name'] ?? ''),
            ];
        }

        return $this->userRoleMap = $map;
    }

    /**
     * Original (DB-side) list of users in this role. The template emits this
     * verbatim into the `in_role_user_old` hidden so the save controller can
     * compute removed-user diff.
     *
     * @return array<int, string>
     */
    public function getAssignedUserIds(): array
    {
        if ($this->assignedIds !== null) {
            return $this->assignedIds;
        }

        $rid = (int) $this->request->getParam('rid', 0);
        if ($rid === 0) {
            return $this->assignedIds = [];
        }

        try {
            $role = $this->roleFactory->create();
            $role->setId($rid);
            $users = $role->getRoleUsers();
        } catch (\Throwable) {
            return $this->assignedIds = [];
        }

        return $this->assignedIds = array_values(array_map('strval', (array) $users));
    }

    public function hasRole(): bool
    {
        return (int) $this->request->getParam('rid', 0) > 0;
    }
}
