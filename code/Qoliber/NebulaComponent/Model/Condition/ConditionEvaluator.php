<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Condition;

/**
 * Evaluates structured conditions used by Nebula JSON definitions.
 *
 * The DSL is deliberately narrow: no expression strings, no PHP eval, no
 * user-supplied callables. Every condition is a JSON object with a known
 * discriminator key.
 *
 * Supported shapes (all values are JSON-literal):
 *   - field  : {"field": "status", "op": "eq", "value": "enabled"}
 *   - role   : {"role": "admin"}                           // current admin user role
 *   - config : {"config": "web/secure/use_in_adminhtml", "op": "eq", "value": "1"}
 *   - all    : {"all": [<condition>, <condition>, …]}
 *   - any    : {"any": [<condition>, <condition>, …]}
 *   - not    : {"not": <condition>}
 *
 * Operators: eq, neq, gt, gte, lt, lte, in, nin, contains, empty, notEmpty.
 *
 * The evaluator also understands the short-hand bool literal: a raw `true`/`false`
 * passed as a condition always evaluates to itself. This is useful for
 * `{"if": true, "then": ...}` stubs during authoring.
 *
 * Unknown discriminators throw {@see \Qoliber\NebulaComponent\Exception\MalformedConditionException},
 * preventing typos from silently evaluating to false.
 *
 * @api
 */
class ConditionEvaluator
{
    public function __construct(
        private readonly \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        private readonly \Magento\Backend\Model\Auth\Session $authSession
    ) {
    }

    /**
     * @param array<string, mixed>|bool $condition
     * @param array<string, mixed> $context
     */
    public function evaluate(array|bool $condition, array $context = []): bool
    {
        if (is_bool($condition)) {
            return $condition;
        }

        if (array_key_exists('all', $condition)) {
            return $this->evaluateAll($condition['all'], $context);
        }

        if (array_key_exists('any', $condition)) {
            return $this->evaluateAny($condition['any'], $context);
        }

        if (array_key_exists('not', $condition)) {
            return !$this->evaluate($this->castBranch($condition['not']), $context);
        }

        if (array_key_exists('field', $condition)) {
            return $this->evaluateField($condition, $context);
        }

        if (array_key_exists('role', $condition)) {
            return $this->evaluateRole((string) $condition['role']);
        }

        if (array_key_exists('config', $condition)) {
            return $this->evaluateConfig($condition, $context);
        }

        throw new \Qoliber\NebulaComponent\Exception\MalformedConditionException(
            __('Unknown Nebula condition shape. Allowed discriminators: field, role, config, all, any, not.')
        );
    }

    /**
     * @param mixed $branches
     * @param array<string, mixed> $context
     */
    private function evaluateAll(mixed $branches, array $context): bool
    {
        if (!is_array($branches)) {
            return false;
        }

        foreach ($branches as $branch) {
            if (!$this->evaluate($this->castBranch($branch), $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $branches
     * @param array<string, mixed> $context
     */
    private function evaluateAny(mixed $branches, array $context): bool
    {
        if (!is_array($branches)) {
            return false;
        }

        foreach ($branches as $branch) {
            if ($this->evaluate($this->castBranch($branch), $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function evaluateField(array $condition, array $context): bool
    {
        $path = (string) $condition['field'];
        $op = (string) ($condition['op'] ?? 'eq');
        $expected = $condition['value'] ?? null;
        $actual = $this->digPath($context, $path);

        return $this->compare($actual, $op, $expected);
    }

    private function evaluateRole(string $role): bool
    {
        $user = $this->authSession->getUser();

        if ($user === null) {
            return false;
        }

        // Magento user/role objects are DataObjects: prefer explicit accessors,
        // fall back to getData(). This keeps the component unit-testable against
        // plain stubs.
        $userRole = method_exists($user, 'getRole') ? $user->getRole() : null;

        if ($userRole === null) {
            return false;
        }

        $actual = method_exists($userRole, 'getRoleName')
            ? (string) $userRole->getRoleName()
            : '';

        return strcasecmp($actual, $role) === 0;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function evaluateConfig(array $condition, array $context): bool
    {
        $path = (string) $condition['config'];
        $op = (string) ($condition['op'] ?? 'eq');
        $expected = $condition['value'] ?? null;
        $scope = (string) ($condition['scope'] ?? \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $scopeCode = $condition['scopeCode'] ?? $context['storeId'] ?? null;

        $actual = $this->scopeConfig->getValue(
            $path,
            $scope,
            is_scalar($scopeCode) ? $scopeCode : null
        );

        return $this->compare($actual, $op, $expected);
    }

    private function compare(mixed $actual, string $op, mixed $expected): bool
    {
        return match ($op) {
            'eq' => $this->looseEquals($actual, $expected),
            'neq' => !$this->looseEquals($actual, $expected),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual + 0 > $expected + 0,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual + 0 >= $expected + 0,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual + 0 < $expected + 0,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual + 0 <= $expected + 0,
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'nin' => is_array($expected) && !in_array($actual, $expected, false),
            'contains' => is_string($actual) && is_string($expected) && $expected !== '' && str_contains($actual, $expected),
            'empty' => $actual === null || $actual === '' || $actual === [] || $actual === false,
            'notEmpty' => !($actual === null || $actual === '' || $actual === [] || $actual === false),
            default => throw new \Qoliber\NebulaComponent\Exception\MalformedConditionException(
                __('Unsupported Nebula condition operator "%1".', $op)
            ),
        };
    }

    private function looseEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return $a + 0 === $b + 0;
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function digPath(array $context, string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $cursor = $context;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Branches in all/any/not may be raw bools or nested condition arrays.
     *
     * @param mixed $branch
     * @return array<string, mixed>|bool
     */
    private function castBranch(mixed $branch): array|bool
    {
        if (is_bool($branch)) {
            return $branch;
        }

        if (is_array($branch)) {
            return $branch;
        }

        throw new \Qoliber\NebulaComponent\Exception\MalformedConditionException(
            __('Nebula condition branch must be an object or boolean literal.')
        );
    }
}
