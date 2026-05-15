<?php

declare(strict_types=1);

namespace Qoliber\NebulaMenu\Model;

use Magento\Backend\Model\Auth\Session;

/**
 * Manages pinned menu items per admin user.
 * Stored in user extra data under 'nebulaPinnedMenuItems'.
 */
class PinnedItems
{
    private const EXTRA_KEY = 'nebulaPinnedMenuItems';

    public function __construct(
        private readonly Session $authSession,
    ) {
    }

    public function getPinnedIds(): array
    {
        $user = $this->authSession->getUser();
        if (!$user) {
            return [];
        }

        $extra = $user->getExtra() ?: [];

        return $extra[self::EXTRA_KEY] ?? [];
    }

    public function isPinned(string $menuItemId): bool
    {
        return in_array($menuItemId, $this->getPinnedIds());
    }

    public function toggle(string $menuItemId): bool
    {
        $user = $this->authSession->getUser();
        if (!$user) {
            return false;
        }

        $extra = $user->getExtra() ?: [];
        $pinned = $extra[self::EXTRA_KEY] ?? [];

        if (in_array($menuItemId, $pinned)) {
            $pinned = array_values(array_diff($pinned, [$menuItemId]));
            $isPinned = false;
        } else {
            $pinned[] = $menuItemId;
            $isPinned = true;
        }

        $extra[self::EXTRA_KEY] = $pinned;
        $user->saveExtra($extra);

        return $isPinned;
    }

    public function reorder(array $orderedIds): void
    {
        $user = $this->authSession->getUser();
        if (!$user) {
            return;
        }

        $extra = $user->getExtra() ?: [];
        $extra[self::EXTRA_KEY] = array_values($orderedIds);
        $user->saveExtra($extra);
    }
}
