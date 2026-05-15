<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;

class ActionsRenderer extends AbstractColumnRenderer
{
    /** @var array<string, object|false> instance cache, false on construction failure */
    private array $columnInstances = [];

    public function __construct(
        Escaper $escaper,
        UrlInterface $urlBuilder,
        LayoutInterface $layout,
        PhtmlRenderer $phtmlRenderer,
        // nebula:allow-object-manager dynamic-actions-column-dispatch — actions
        // FQCN comes from the bridge-converted listing JSON; we instantiate the
        // class to harvest per-row URLs Magento computes in PHP. Necessary to
        // mirror the action URLs of 3rd-party UI components without per-module
        // bridge config.
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($escaper, $urlBuilder, $layout, $phtmlRenderer);
    }

    public function getComponentName(): string
    {
        return 'nebulaColumn_actions';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/actions.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        $actions = [];

        // Path 1 — explicit actions array (XML carried inline editUrlPath /
        // deleteUrlPath / viewUrlPath).
        foreach ($column['actions'] ?? [] as $action) {
            $rawUrl = (string) ($action['url'] ?? '#');
            $interpolated = $this->interpolate($rawUrl, $item);
            $actions[] = [
                'label' => (string) ($action['label'] ?? ''),
                'url' => $this->urlBuilder->getUrl($interpolated),
            ];
        }

        // Path 2 — fallback to the Magento actions column class. Most listings
        // (Cms, Multiblog, etc.) compute URLs in PHP, so XML carries only the
        // FQCN. Bridge passes the FQCN through; we invoke prepareDataSource()
        // per row and convert the resulting actions map.
        $magentoClass = (string) ($column['_magentoColumnClass'] ?? '');
        if ($actions === [] && $magentoClass !== '') {
            $actionsKey = (string) ($column['_actionsKey'] ?? 'actions');
            $indexField = (string) ($column['_indexField'] ?? 'id');
            $extracted = $this->invokeMagentoColumn($magentoClass, $actionsKey, $indexField, $item);
            foreach ($extracted as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $actions[] = [
                    'label' => (string) ($entry['label'] ?? ''),
                    'url' => (string) ($entry['href'] ?? '#'),
                    'confirm' => $entry['confirm'] ?? null,
                ];
            }
        }

        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'actions' => $actions,
        ];
    }

    /**
     * Build (and memoise) the Magento actions column instance, then run its
     * prepareDataSource against a single-row dataSource and return the array
     * of action entries Magento attaches under $actionsKey.
     *
     * @param array<string, mixed> $item
     * @return array<int|string, mixed>
     */
    private function invokeMagentoColumn(string $class, string $actionsKey, string $indexField, array $item): array
    {
        $instance = $this->columnInstances[$class] ?? null;
        if ($instance === null) {
            try {
                $instance = $this->objectManager->create($class, [
                    'data' => [
                        'name' => $actionsKey,
                        'indexField' => $indexField,
                    ],
                ]);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'NebulaGrid actions: could not instantiate ' . $class . ': ' . $e->getMessage()
                );
                $this->columnInstances[$class] = false;
                return [];
            }
            $this->columnInstances[$class] = $instance;
        }
        if ($instance === false) {
            return [];
        }
        if (!\method_exists($instance, 'prepareDataSource')) {
            return [];
        }
        try {
            $dataSource = ['data' => ['items' => [$item]]];
            $result = $instance->prepareDataSource($dataSource);
            $enriched = $result['data']['items'][0] ?? [];
            $cell = $enriched[$actionsKey] ?? [];
            return is_array($cell) ? $cell : [];
        } catch (\Throwable $e) {
            $this->logger->error(
                'NebulaGrid actions: prepareDataSource failed for ' . $class . ': ' . $e->getMessage()
            );
            return [];
        }
    }
}
