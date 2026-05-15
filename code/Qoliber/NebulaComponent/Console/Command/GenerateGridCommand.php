<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Console\Command;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateGridCommand extends Command
{
    private const ARG_MODULE = 'module';
    private const ARG_ID = 'id';

    public function __construct(
        private readonly DirReader $dirReader,
        private readonly FileDriver $fileDriver,
        private readonly JsonSerializer $json,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('nebula:generate:grid')
            ->setDescription('Generate a skeleton Nebula grid definition and layout XML')
            ->addArgument(self::ARG_MODULE, InputArgument::REQUIRED, 'Module name, e.g. Vendor_Module')
            ->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Grid identifier, e.g. my_listing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = $input->getArgument(self::ARG_MODULE);
        $gridId = $input->getArgument(self::ARG_ID);

        try {
            $moduleDir = $this->dirReader->getModuleDir('', $module);
        } catch (\Exception $e) {
            $output->writeln('<error>Module "' . $module . '" not found or not registered.</error>');
            return Command::FAILURE;
        }

        $gridDir = $moduleDir . '/view/adminhtml/grid';
        $layoutDir = $moduleDir . '/view/adminhtml/layout';

        $this->ensureDirectory($gridDir);
        $this->ensureDirectory($layoutDir);

        $gridFile = $gridDir . '/' . $gridId . '.json';
        $layoutFile = $layoutDir . '/' . $gridId . '.xml';

        $gridDefinition = [
            'id' => $gridId,
            'dataSource' => [
                'provider' => 'Qoliber\\NebulaGrid\\Model\\DataProvider\\CollectionProvider',
                'config' => [
                    'collection' => 'Vendor\\Module\\Model\\ResourceModel\\Entity\\Collection',
                ],
            ],
            'settings' => [
                'pageSize' => 20,
                'pageSizes' => [10, 20, 50],
                'defaultSort' => [
                    'field' => 'entity_id',
                    'direction' => 'asc',
                ],
            ],
            'columns' => [
                'entity_id' => [
                    'label' => 'ID',
                    'type' => 'text',
                    'sortable' => true,
                    'filter' => 'range',
                    'position' => 10,
                ],
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'sortable' => true,
                    'filter' => 'text',
                    'searchable' => true,
                    'position' => 20,
                ],
            ],
        ];

        $jsonContent = $this->json->serialize($gridDefinition);
        $jsonContent = json_encode(
            json_decode($jsonContent, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $this->fileDriver->filePutContents($gridFile, $jsonContent . "\n");
        $output->writeln('<info>Created:</info> ' . $gridFile);

        $layoutXml = <<<XML
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="Qoliber\NebulaGrid\Block\Grid"
                   name="nebula.{$gridId}"
                   after="-">
                <arguments>
                    <argument name="grid_id" xsi:type="string">{$gridId}</argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>

XML;

        $this->fileDriver->filePutContents($layoutFile, $layoutXml);
        $output->writeln('<info>Created:</info> ' . $layoutFile);

        return Command::SUCCESS;
    }

    private function ensureDirectory(string $path): void
    {
        if (!$this->fileDriver->isExists($path)) {
            $this->fileDriver->createDirectory($path, 0755);
        }
    }
}
