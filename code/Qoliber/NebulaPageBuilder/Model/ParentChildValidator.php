<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model;

use Qoliber\NebulaPageBuilder\Model\ContentType\Registry;

class ParentChildValidator
{
    public function __construct(
        private readonly Registry $registry
    ) {
    }

    /**
     * Check if childType can be placed inside parentType.
     *
     * @param string $childType
     * @param string $parentType
     * @return bool
     */
    public function canDrop(string $childType, string $parentType): bool
    {
        $childDef = $this->registry->get($childType);
        $parentDef = $this->registry->get($parentType);

        if ($childDef === null || $parentDef === null) {
            return false;
        }

        // Check child's parent rules
        $parentRules = $childDef['parents'] ?? [];
        if (!$this->checkPolicy($parentType, $parentRules)) {
            return false;
        }

        // Check parent's children rules
        $childRules = $parentDef['children'] ?? [];

        return $this->checkPolicy($childType, $childRules);
    }

    private function checkPolicy(string $name, array $rules): bool
    {
        $defaultPolicy = $rules['defaultPolicy'] ?? 'allow';
        $allow = $rules['allow'] ?? [];
        $deny = $rules['deny'] ?? [];

        if ($defaultPolicy === 'deny') {
            return in_array($name, $allow, true);
        }

        return !in_array($name, $deny, true);
    }
}
