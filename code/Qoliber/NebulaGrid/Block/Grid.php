<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;
use Qoliber\NebulaComponent\Model\RendererPool;
use Qoliber\NebulaGrid\Model\ColumnLoader;
use Qoliber\NebulaGrid\Model\Definition\GridDefinitionNormalizer;
use Qoliber\NebulaGrid\Model\GridDataLoader;
use Qoliber\NebulaGrid\Renderer\Column\ColumnDispatcher;

/**
 * Nebula grid block. Owns sort/filter/pagination glue and delegates every
 * cell render to the column-renderer registry via {@see ColumnDispatcher}.
 */
class Grid extends Template
{
    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    /** @var array{items: array<int, array<string, mixed>>, totalCount: int}|null */
    private ?array $providerData = null;

    public function __construct(
        Context $context,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly FormKey $formKey,
        private readonly RendererPool $rendererPool,
        private readonly ColumnDispatcher $columnDispatcher,
        private readonly ColumnLoader $columnLoader,
        private readonly GridDataLoader $gridDataLoader,
        private readonly GridDefinitionNormalizer $gridDefinitionNormalizer,
        private readonly DefinitionAccessControl $definitionAccessControl,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaGrid::grid.phtml');
    }

    public function getDefinition(): array
    {
        if ($this->definition !== null) {
            return $this->definition;
        }

        $inline = $this->getData('inline_definition');
        $raw = is_array($inline) && !empty($inline)
            ? $inline
            : $this->definitionResolver->resolve('grid', $this->getGridId());

        return $this->definition = $this->gridDefinitionNormalizer->normalize($raw);
    }

    public function getGridId(): string
    {
        return (string) $this->getData('grid_id');
    }

    public function isAllowed(): bool
    {
        return $this->definitionAccessControl->isAllowed($this->getDefinition());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getColumns(): array
    {
        return $this->columnLoader->load($this->getDefinition());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->getProviderData()['items'];
    }

    public function getTotalCount(): int
    {
        return $this->getProviderData()['totalCount'];
    }

    public function getPage(): int
    {
        return max(1, (int) $this->getRequest()->getParam('page', 1));
    }

    public function getPageSize(): int
    {
        $default = (int) ($this->getDefinition()['settings']['pageSize'] ?? 20);

        return (int) $this->getRequest()->getParam('pageSize', $default);
    }

    public function getTotalPages(): int
    {
        $pageSize = $this->getPageSize();

        return $pageSize > 0 ? (int) ceil($this->getTotalCount() / $pageSize) : 1;
    }

    public function getSort(): string
    {
        $default = $this->getDefinition()['settings']['defaultSort']['field'] ?? '';

        return (string) $this->getRequest()->getParam('sort', $default);
    }

    public function getSortDir(): string
    {
        $default = $this->getDefinition()['settings']['defaultSort']['direction'] ?? 'asc';
        $dir = (string) $this->getRequest()->getParam('dir', $default);

        return in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
    }

    /**
     * @return array<int, int>
     */
    public function getPageSizes(): array
    {
        return $this->getDefinition()['settings']['pageSizes'] ?? [10, 20, 50];
    }

    public function hasMassActions(): bool
    {
        return !empty($this->getDefinition()['settings']['massActions']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMassActions(): array
    {
        return $this->getDefinition()['settings']['massActions'] ?? [];
    }

    public function getDataUrl(): string
    {
        return $this->getUrl('nebula/grid/data');
    }

    public function getExportUrl(): string
    {
        return $this->getUrl('nebula/grid/export');
    }

    public function renderColumnValue(array $column, string $key, array $item): string
    {
        return $this->columnDispatcher->render($column, $key, $item);
    }

    /**
     * @return array<string, string>
     */
    public function getActiveFilters(): array
    {
        $filters = $this->getRequest()->getParam('filters', []);

        return is_array($filters)
            ? array_filter($filters, static fn ($v): bool => $v !== '' && $v !== null)
            : [];
    }

    public function translate(string $text): string
    {
        return (string) __($text);
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getColumnRendererComponent(string $type): string
    {
        return $this->rendererPool->getColumnComponent($type);
    }

    public function getFiltersJson(): string
    {
        return (string) json_encode($this->getActiveFilters());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function getGridUrl(array $overrides = []): string
    {
        $params = array_merge([
            'sort' => $this->getSort(),
            'dir' => $this->getSortDir(),
            'page' => $this->getPage(),
            'pageSize' => $this->getPageSize(),
        ], $overrides);

        return $this->getUrl('*/*/*', $params);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    private function getProviderData(): array
    {
        if ($this->providerData !== null) {
            return $this->providerData;
        }

        return $this->providerData = $this->gridDataLoader->load(
            $this->getDefinition(),
            $this->getRequest(),
            $this->getPage(),
            $this->getPageSize(),
            $this->getSort(),
            $this->getSortDir()
        );
    }
}
