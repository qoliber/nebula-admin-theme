<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\ContentType;

class Registry
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $types = null;

    public function __construct(
        private readonly \Magento\Framework\Module\Dir\Reader $dirReader,
        private readonly \Magento\Framework\Filesystem\Driver\File $fileDriver,
        private readonly \Magento\Framework\Module\ModuleListInterface $moduleList,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {
    }

    /**
     * Get all registered content type definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        if ($this->types === null) {
            $this->load();
        }

        return $this->types;
    }

    /**
     * Get a single content type definition by name.
     *
     * @param string $name
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->getAll()[$name] ?? null;
    }

    /**
     * Get content types grouped by menu section.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getByMenuSection(): array
    {
        $grouped = [];
        foreach ($this->getAll() as $name => $type) {
            $section = $type['menuSection'] ?? 'general';
            $grouped[$section][$name] = $type;
        }

        return $grouped;
    }

    private function load(): void
    {
        $this->types = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            $dir = $moduleDir . '/view/adminhtml/content_type';

            try {
                if (!$this->fileDriver->isDirectory($dir)) {
                    continue;
                }

                $files = $this->fileDriver->readDirectory($dir);
                foreach ($files as $filePath) {
                    if (!str_ends_with($filePath, '.json')) {
                        continue;
                    }

                    $content = $this->fileDriver->fileGetContents($filePath);
                    $parsed = json_decode($content, true);

                    if (is_array($parsed) && !empty($parsed['name'])) {
                        $name = $parsed['name'];
                        if (isset($this->types[$name])) {
                            $this->types[$name] = array_replace_recursive($this->types[$name], $parsed);
                        } else {
                            $this->types[$name] = $parsed;
                        }
                    }
                }
            } catch (\Magento\Framework\Exception\FileSystemException $e) {
                $this->logger->warning('FileSystemException while loading content type definitions.', [
                    'module' => $moduleName,
                    'directory' => $dir,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Sort by sortOrder
        uasort($this->types, static function (array $a, array $b): int {
            return ($a['sortOrder'] ?? 999) <=> ($b['sortOrder'] ?? 999);
        });
    }
}
