<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Authorization;

use Magento\Framework\AuthorizationInterface;

class DefinitionAccessControl
{
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
     */
    public function isAllowed(array $definition): bool
    {
        $acl = (string) ($definition['acl'] ?? 'Magento_Backend::admin');

        return $this->authorization->isAllowed($acl);
    }
}
