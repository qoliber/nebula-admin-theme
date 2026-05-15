<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Plugin\Definition;

use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\Condition\ConditionEvaluator;

/**
 * After the {@see \Qoliber\NebulaComponent\Model\Definition\SnippetResolver}
 * walks a definition's layout tree, this plugin collapses any
 * `{"if": …, "then": …, "else": …}` nodes using the typed condition DSL.
 *
 * The plugin runs after ref/slot resolution so conditions can reference
 * already-expanded fragments.
 */
class ConditionalBranchResolver
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator
    ) {
    }

    /**
     * @param \Qoliber\NebulaComponent\Api\SnippetResolverInterface $subject
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterResolveLayout(SnippetResolverInterface $subject, array $result): array
    {
        if (!isset($result['layout']) || !is_array($result['layout'])) {
            return $result;
        }

        $context = $this->buildContext($result);
        $result['layout'] = $this->walk($result['layout'], $context);

        return $result;
    }

    /**
     * @param array<int|string, mixed> $node
     * @param array<string, mixed> $context
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function walk(array $node, array $context): array
    {
        if ($this->isIfNode($node)) {
            return $this->resolveIfNode($node, $context);
        }

        if (array_is_list($node)) {
            $out = [];

            foreach ($node as $child) {
                $out = array_merge($out, $this->walkChild($child, $context));
            }

            return $out;
        }

        if (isset($node['children']) && is_array($node['children'])) {
            $children = [];

            foreach ($node['children'] as $child) {
                $children = array_merge($children, $this->walkChild($child, $context));
            }

            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * @param mixed $child
     * @param array<string, mixed> $context
     * @return list<mixed>
     */
    private function walkChild(mixed $child, array $context): array
    {
        if (!is_array($child)) {
            return [$child];
        }

        $resolved = $this->walk($child, $context);

        if (array_is_list($resolved)) {
            return $resolved;
        }

        return [$resolved];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function resolveIfNode(array $node, array $context): array
    {
        $condition = $node['if'];

        if (!is_array($condition) && !is_bool($condition)) {
            return [];
        }

        $verdict = $this->evaluator->evaluate(
            is_bool($condition) ? $condition : $condition,
            $context
        );

        $branch = $verdict ? ($node['then'] ?? null) : ($node['else'] ?? null);

        if ($branch === null) {
            return [];
        }

        $branchList = array_is_list($branch) ? $branch : [$branch];
        $out = [];

        foreach ($branchList as $item) {
            if (is_array($item)) {
                $resolved = $this->walk($item, $context);
                $out = array_is_list($resolved)
                    ? array_merge($out, $resolved)
                    : array_merge($out, [$resolved]);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isIfNode(array $node): bool
    {
        return array_key_exists('if', $node)
            && (array_key_exists('then', $node) || array_key_exists('else', $node));
    }

    /**
     * Build the default context given to evaluators: the definition itself
     * (so `{"field": "settings.foo"}` works), plus any explicit `context`
     * key the definition opted into.
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function buildContext(array $definition): array
    {
        $context = $definition;

        if (isset($definition['context']) && is_array($definition['context'])) {
            $context = array_replace($context, $definition['context']);
        }

        return $context;
    }
}
