<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Controller\Adminhtml\Grid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;
use Qoliber\NebulaComponent\Model\DataProviderResolver;

/**
 * Admin export endpoint for Nebula grids (CSV/XML). Resolves the provider
 * through the alias registry — the controller never calls ObjectManager.
 */
class Export extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly DataProviderResolver $dataProviderResolver,
        private readonly JsonFactory $jsonFactory,
        private readonly FileFactory $fileFactory,
        private readonly DefinitionAccessControl $definitionAccessControl,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
    {
        $id = (string) $this->getRequest()->getParam('id');
        $format = (string) $this->getRequest()->getParam('format', 'csv');

        try {
            $definition = $this->definitionResolver->resolve('grid', $id);

            if (!$this->definitionAccessControl->isAllowed($definition)) {
                return $this->jsonFactory->create()->setData(['error' => 'Access denied.']);
            }

            $providerAlias = (string) ($definition['dataSource']['provider'] ?? '');
            if ($providerAlias === '') {
                return $this->jsonFactory->create()->setData(['error' => 'No data provider configured.']);
            }

            $config = $definition['dataSource']['config'] ?? [];
            $config['columns'] = $definition['columns'] ?? [];

            $data = $this->dataProviderResolver->fetch($providerAlias, $config, [
                'sort' => (string) $this->getRequest()->getParam('sort', ''),
                'sortDir' => (string) $this->getRequest()->getParam('sortDir', 'asc'),
                'filters' => (array) $this->getRequest()->getParam('filters', []),
                'search' => (string) $this->getRequest()->getParam('search', ''),
            ]);

            $items = $data['items'] ?? [];
            $columns = $definition['columns'] ?? [];
            $filename = 'export_' . $id . '_' . date('Ymd_His');

            return $format === 'xml'
                ? $this->exportXml($items, $columns, $filename)
                : $this->exportCsv($items, $columns, $filename);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula Grid/Export failed: ' . $e->getMessage(), ['exception' => $e]);
            return $this->jsonFactory->create()->setData(['error' => 'Export failed.']);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $columns
     */
    private function exportCsv(array $items, array $columns, string $filename): \Magento\Framework\App\ResponseInterface
    {
        $headers = [];
        foreach ($columns as $key => $col) {
            if (($col['type'] ?? '') === 'actions') {
                continue;
            }
            $headers[$key] = $col['label'] ?? $key;
        }

        $content = implode(',', array_map(
            fn (string $h): string => $this->csvCell($h),
            $headers
        )) . "\n";

        foreach ($items as $item) {
            $row = [];
            foreach (array_keys($headers) as $key) {
                $row[] = $this->csvCell((string) ($item[$key] ?? ''));
            }
            $content .= implode(',', $row) . "\n";
        }

        return $this->fileFactory->create(
            $filename . '.csv',
            $content,
            DirectoryList::VAR_DIR
        );
    }

    /**
     * Quote a cell for CSV output AND defuse spreadsheet-injection payloads.
     *
     * Cells whose first character is one of `= + - @ \t \r` are treated as
     * formulas by Excel / LibreOffice / Google Sheets. A malicious vendor
     * order comment, customer note, etc., can land in an exported grid and
     * fire arbitrary spreadsheet functions when the recipient opens the
     * file. OWASP recommends prefixing the value with a single apostrophe
     * which the spreadsheet apps interpret as "this is a literal string".
     */
    private function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $columns
     */
    private function exportXml(array $items, array $columns, string $filename): \Magento\Framework\App\ResponseInterface
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<items>\n";

        $columnKeys = [];
        foreach ($columns as $key => $col) {
            if (($col['type'] ?? '') === 'actions') {
                continue;
            }
            $columnKeys[] = $key;
        }

        foreach ($items as $item) {
            $xml .= "  <item>\n";
            foreach ($columnKeys as $key) {
                $value = htmlspecialchars((string) ($item[$key] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "    <{$key}>{$value}</{$key}>\n";
            }
            $xml .= "  </item>\n";
        }

        $xml .= "</items>\n";

        return $this->fileFactory->create(
            $filename . '.xml',
            $xml,
            DirectoryList::VAR_DIR
        );
    }

    /**
     * Magento backend ACL hook — leading underscore is required by the
     * \Magento\Backend\App\AbstractAction contract.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        $id = (string) $this->getRequest()->getParam('id');

        try {
            $definition = $this->definitionResolver->resolve('grid', $id);
            $acl = (string) ($definition['acl'] ?? 'Magento_Backend::admin');

            return $this->_authorization->isAllowed($acl);
        } catch (\Exception) {
            return false;
        }
    }
}
