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

class GenerateFormCommand extends Command
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
        $this->setName('nebula:generate:form')
            ->setDescription('Generate a skeleton Nebula form definition and layout XML')
            ->addArgument(self::ARG_MODULE, InputArgument::REQUIRED, 'Module name, e.g. Vendor_Module')
            ->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Form identifier, e.g. my_entity_edit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = $input->getArgument(self::ARG_MODULE);
        $formId = $input->getArgument(self::ARG_ID);

        try {
            $moduleDir = $this->dirReader->getModuleDir('', $module);
        } catch (\Exception $e) {
            $output->writeln('<error>Module "' . $module . '" not found or not registered.</error>');
            return Command::FAILURE;
        }

        $formDir = $moduleDir . '/view/adminhtml/form';
        $layoutDir = $moduleDir . '/view/adminhtml/layout';

        $this->ensureDirectory($formDir);
        $this->ensureDirectory($layoutDir);

        $formFile = $formDir . '/' . $formId . '.json';
        $layoutFile = $layoutDir . '/' . $formId . '.xml';

        $formDefinition = [
            'id' => $formId,
            'dataSource' => [
                'provider' => 'Vendor\\Module\\Model\\DataProvider\\EntityProvider',
                'config' => [
                    'identifierParam' => 'entity_id',
                ],
            ],
            'settings' => [
                'title' => 'Entity',
                'identifierField' => 'entity_id',
                'backUrl' => 'module/entity/index',
                'deleteUrl' => 'module/entity/delete',
                'saveUrl' => 'module/entity/save',
            ],
            'fieldsets' => [
                'general' => [
                    'label' => 'General',
                    'position' => 10,
                    'open' => true,
                    'fields' => [
                        'name' => [
                            'label' => 'Name',
                            'type' => 'text',
                            'required' => true,
                            'position' => 10,
                        ],
                        'is_active' => [
                            'label' => 'Enabled',
                            'type' => 'toggle',
                            'position' => 20,
                        ],
                    ],
                ],
            ],
        ];

        $jsonContent = $this->json->serialize($formDefinition);
        $jsonContent = json_encode(
            json_decode($jsonContent, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $this->fileDriver->filePutContents($formFile, $jsonContent . "\n");
        $output->writeln('<info>Created:</info> ' . $formFile);

        $layoutXml = <<<XML
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="Qoliber\NebulaForm\Block\Form"
                   name="nebula.{$formId}">
                <arguments>
                    <argument name="form_id" xsi:type="string">{$formId}</argument>
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
