<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

use Magento\Framework\Escaper;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;
use Qoliber\NebulaComponent\Model\SnippetViewModelRegistry;

/**
 * Renders a form "section" defined by a `renderer` string that either:
 *   - starts with `snippet.<id>` (resolves the snippet's template), or
 *   - contains `::` (a direct `Vendor_Module::path/to.phtml` reference).
 *
 * Extracted from Form/EavForm blocks so they stop owning the markup
 * assembly logic. Also injects any DI-registered ViewModel for the
 * snippet into the template context under `view_model` so phtml
 * partials never touch ObjectManager.
 */
class SectionRenderer
{
    public function __construct(
        private readonly SnippetResolverInterface $snippetResolver,
        private readonly PhtmlRenderer $phtmlRenderer,
        private readonly Escaper $escaper,
        private readonly SnippetViewModelRegistry $viewModelRegistry
    ) {
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, mixed> $context block_name + entity + entity_data for the template
     */
    public function render(LayoutInterface $layout, array $section, array $context = []): string
    {
        $renderers = $section['renderers'] ?? [];

        if (empty($renderers) && !empty($section['renderer'])) {
            $renderers = [(string) $section['renderer']];
        }

        if (empty($renderers)) {
            return '';
        }

        $html = '';
        foreach ($renderers as $renderer) {
            $html .= $this->renderOne((string) $renderer, $section, $context, $layout);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, mixed> $context
     */
    private function renderOne(string $renderer, array $section, array $context, LayoutInterface $layout): string
    {
        if ($renderer === '') {
            return '';
        }

        if (str_starts_with($renderer, 'snippet.')) {
            $snippetId = substr($renderer, 8);
            $snippet = $this->snippetResolver->resolve($snippetId);
            $template = (string) ($snippet['template'] ?? '');

            if ($template === '') {
                return $this->placeholder($snippetId);
            }

            $data = array_merge($context, ['section' => $section]);
            $viewModel = $this->viewModelRegistry->get($snippetId);
            if ($viewModel !== null) {
                $data['view_model'] = $viewModel;
            }

            return $this->phtmlRenderer->render($layout, $template, $data);
        }

        if (str_contains($renderer, '::')) {
            return $this->phtmlRenderer->render(
                $layout,
                $renderer,
                array_merge($context, ['section' => $section])
            );
        }

        return '';
    }

    private function placeholder(string $snippetName): string
    {
        return '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center text-sm text-gray-500">'
            . 'Snippet: ' . $this->escaper->escapeHtml($snippetName) . ' (coming soon)'
            . '</div>';
    }
}
