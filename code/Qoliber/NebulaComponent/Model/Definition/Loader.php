<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Definition;

class Loader
{
    public function __construct(
        private readonly \Magento\Framework\Module\Dir\Reader $dirReader,
        private readonly \Magento\Framework\Filesystem\Driver\File $fileDriver,
        private readonly \Magento\Framework\Module\ModuleListInterface $moduleList,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {
    }

    /**
     * Load definition files from all modules for the given type and id.
     *
     * @param string $type
     * @param string $id
     * @return array
     */
    public function load(string $type, string $id): array
    {
        $definitions = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            $filePath = $moduleDir . '/view/adminhtml/' . $type . '/' . $id . '.json';

            try {
                if ($this->fileDriver->isExists($filePath)) {
                    $content = $this->fileDriver->fileGetContents($filePath);
                    $parsed = json_decode($content, true);

                    if (is_array($parsed)) {
                        $definitions[] = $parsed;
                    } else {
                        $this->logger->warning('Failed to parse JSON definition file.', [
                            'file' => $filePath,
                            'type' => $type,
                            'id' => $id,
                            'json_error' => json_last_error_msg(),
                        ]);
                    }
                }
            } catch (\Magento\Framework\Exception\FileSystemException $e) {
                $this->logger->warning('FileSystemException while loading definition file.', [
                    'file' => $filePath,
                    'type' => $type,
                    'id' => $id,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $definitions;
    }
}
