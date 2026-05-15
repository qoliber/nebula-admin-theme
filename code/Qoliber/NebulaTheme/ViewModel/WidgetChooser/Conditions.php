<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\WidgetChooser;

use Qoliber\NebulaComponent\ViewModel\RuleEditor;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Inline-mode chooser for the Catalog Products List widget's `condition`
 * parameter. Doesn't open a modal — instead, the registered template
 * (widget/chooser/conditions.phtml) mounts the existing nebulaRuleEditor
 * inline inside the parameter row.
 *
 * The chooser-interface methods are present for contract completeness
 * but unused for this mode: search() always returns an empty page,
 * getByValue() is never called (the rule editor handles its own state
 * via the inline rule-tree).
 *
 * `buildConfig()` is the actual entry point; the snippet phtml calls it
 * with the saved parameters[conditions] tree and gets back the full
 * payload `nebulaRuleEditor` expects.
 */
class Conditions implements WidgetChooserViewModelInterface
{
    public function __construct(
        private readonly RuleEditor $ruleEditor,
    ) {
    }

    /**
     * Build the rule-editor Alpine config. fieldPrefix='parameters' so
     * the hidden inputs the editor serialises post as
     * `parameters[conditions][1][type]` etc. — the exact shape Magento's
     * widget Save controller reads.
     *
     * @param array<string, mixed> $existing Saved widget parameters
     *        (the wizard's state.parameters object on edit). The
     *        `conditions` key is decoded and threaded into the editor.
     * @return array<string, mixed>
     */
    public function buildConfig(array $existing = []): array
    {
        return $this->ruleEditor->buildConfig(
            ['conditions_serialized' => json_encode($existing['conditions'] ?? [])],
            'catalog',
            'parameters',
        );
    }

    /** {@inheritDoc} */
    public function search(string $query, int $page, int $pageSize): array
    {
        // Conditions chooser doesn't paginate / search. The rule editor
        // is its own self-contained UI.
        return ['total' => 0, 'rows' => []];
    }

    /** {@inheritDoc} */
    public function getByValue(string $value): ?array
    {
        // No single-record lookup for conditions — the value is a
        // rule-tree, not an entity reference.
        return null;
    }
}
