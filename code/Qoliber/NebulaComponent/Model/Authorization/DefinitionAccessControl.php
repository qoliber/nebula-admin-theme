<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Authorization;

use Magento\Framework\AuthorizationInterface;

/**
 * Authorizes access to a Nebula grid/form definition against Magento ACL.
 */
class DefinitionAccessControl
{
    /**
     * @param \Magento\Framework\AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Returns true when the current admin is authorized for the definition's
     * ACL resource. Default-deny: a definition that omits `acl` falls back to
     * `Magento_Backend::admin`, which still requires an authenticated admin
     * with at least baseline backend access. Standalone definitions that
     * legitimately need broader access must declare it explicitly.
     *
     * @param array<string, mixed> $definition
     * @return bool
     */
    public function isAllowed(array $definition): bool
    {
        // Treat a missing OR empty `acl` as unset — `??` alone leaves an empty
        // string in place, which would check an empty ACL resource instead of
        // falling back to default-deny.
        $acl = (string) ($definition['acl'] ?? '');
        if ($acl === '') {
            $acl = 'Magento_Backend::admin';
        }

        return $this->authorization->isAllowed($acl);
    }
}
