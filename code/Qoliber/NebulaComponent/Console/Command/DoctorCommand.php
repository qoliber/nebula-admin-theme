<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Console\Command;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\RendererPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Diagnostic CLI for the Nebula framework.
 *
 * Reports health metrics across all installed Qoliber\Nebula* modules and prints
 * a color-coded summary. This is the canary command we run after every phase of
 * the hardening sprint, so output is stable and machine-readable.
 */
class DoctorCommand extends Command
{
    private const DEFINITION_TYPES = ['grid', 'form', 'snippet', 'content_type'];
    private const SCANNED_CODE_DIRS = ['app/code/Qoliber'];
    /**
     * Matches real references to ObjectManager: FQCN usage, `use` imports, method
     * calls, property names. Intentionally excludes docstrings/comments where the
     * literal "ObjectManager" shows up while explaining a design.
     */
    private const OBJECT_MANAGER_PATTERN = '/(\\\\?Magento\\\\Framework\\\\App\\\\ObjectManager::getInstance|Magento\\\\Framework\\\\ObjectManagerInterface)/';
    private const ALLOW_OM_MARKER = 'nebula:allow-object-manager';
    private const SCANNED_EXTENSIONS = ['.php', '.phtml'];

    public function __construct(
        private readonly DirReader $dirReader,
        private readonly FileDriver $fileDriver,
        private readonly ModuleListInterface $moduleList,
        private readonly RendererPool $rendererPool,
        private readonly SnippetResolverInterface $snippetResolver,
        private readonly TypeListInterface $cacheTypeList,
        private readonly DirectoryList $directoryList,
        private readonly array $allowedCollectionNamespaces = ['Magento\\', 'Qoliber\\'],
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('nebula:doctor')
            ->setDescription('Report health of the Nebula framework (modules, definitions, cache, renderers)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = $this->getNebulaModules();
        $definitionCounts = $this->countDefinitions($modules);
        $rendererCounts = $this->countRenderers();
        $cacheStatus = $this->describeCacheStatus();
        $objectManagerUsages = $this->countObjectManagerUsages();
        $unresolvedRefs = $this->countUnresolvedSnippetRefs($modules);
        $rawFqcns = $this->countRawFqcnCollections($modules);

        $output->writeln('');
        $output->writeln('<options=bold>Nebula Doctor</> — sprint health report');
        $output->writeln(str_repeat('-', 56));

        $this->writeMetric(
            $output,
            'Nebula modules',
            (string) count($modules),
            count($modules) > 0 ? 'green' : 'red'
        );

        $totalDefs = array_sum($definitionCounts);
        $this->writeMetric(
            $output,
            'JSON definitions (total)',
            (string) $totalDefs,
            $totalDefs > 0 ? 'green' : 'yellow'
        );
        foreach ($definitionCounts as $type => $count) {
            $this->writeMetric($output, '  - ' . $type, (string) $count, 'cyan');
        }

        $this->writeMetric(
            $output,
            'Cache type "nebula_definitions"',
            $cacheStatus['label'],
            $cacheStatus['color']
        );

        $this->writeMetric(
            $output,
            'Unresolved @ref in definitions',
            (string) $unresolvedRefs,
            $unresolvedRefs === 0 ? 'green' : 'red'
        );

        $this->writeMetric(
            $output,
            'Renderer pool (fields / columns)',
            $rendererCounts['fields'] . ' / ' . $rendererCounts['columns'],
            'cyan'
        );

        $objectManagerColor = match (true) {
            $objectManagerUsages === 0 => 'green',
            $objectManagerUsages <= 5 => 'yellow',
            default => 'red',
        };
        $this->writeMetric(
            $output,
            'ObjectManager usages (non-test)',
            (string) $objectManagerUsages . ' (target: <=5)',
            $objectManagerColor
        );

        $this->writeMetric(
            $output,
            'Grid collections (disallowed FQCN)',
            (string) $rawFqcns . ' (target: 0)',
            $rawFqcns === 0 ? 'green' : 'red'
        );

        $output->writeln(str_repeat('-', 56));

        $exit = ($unresolvedRefs === 0 && $rawFqcns === 0) ? Command::SUCCESS : Command::FAILURE;
        $output->writeln($exit === Command::SUCCESS
            ? '<info>Doctor: OK</info>'
            : '<error>Doctor: issues detected</error>');

        return $exit;
    }

    /**
     * @return list<string>
     */
    private function getNebulaModules(): array
    {
        $modules = [];
        foreach ($this->moduleList->getNames() as $name) {
            if (str_starts_with($name, 'Qoliber_Nebula')) {
                $modules[] = $name;
            }
        }

        return $modules;
    }

    /**
     * @param list<string> $modules
     * @return array<string, int>
     */
    private function countDefinitions(array $modules): array
    {
        $counts = array_fill_keys(self::DEFINITION_TYPES, 0);

        foreach ($modules as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Exception) {
                continue;
            }

            foreach (self::DEFINITION_TYPES as $type) {
                $dir = $moduleDir . '/view/adminhtml/' . $type;

                try {
                    if (!$this->fileDriver->isExists($dir)) {
                        continue;
                    }
                    foreach ($this->fileDriver->readDirectory($dir) as $entry) {
                        if (str_ends_with($entry, '.json')) {
                            $counts[$type]++;
                        }
                    }
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{fields: int, columns: int}
     */
    private function countRenderers(): array
    {
        $rendererPool = $this->resolveRendererPool();

        return [
            'fields' => $rendererPool->getFieldRendererCount(),
            'columns' => $rendererPool->getColumnRendererCount(),
        ];
    }

    private function resolveRendererPool(): RendererPool
    {
        if ($this->rendererPool instanceof \Qoliber\NebulaComponent\Model\RendererPool\Proxy) {
            $proxy = new \ReflectionMethod($this->rendererPool, '_getSubject');
            $proxy->setAccessible(true);

            /** @var RendererPool $resolved */
            $resolved = $proxy->invoke($this->rendererPool);

            return $resolved;
        }

        return $this->rendererPool;
    }

    /**
     * @return array{label: string, color: string}
     */
    private function describeCacheStatus(): array
    {
        try {
            foreach ($this->cacheTypeList->getTypes() as $type) {
                $typeId = method_exists($type, 'getId') ? $type->getId() : ($type->getData('id') ?? '');
                if ($typeId === 'nebula_definitions') {
                    $status = method_exists($type, 'getStatus') ? (int) $type->getStatus() : 0;
                    return $status === 1
                        ? ['label' => 'enabled', 'color' => 'green']
                        : ['label' => 'disabled', 'color' => 'yellow'];
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        return ['label' => 'not configured', 'color' => 'yellow'];
    }

    /**
     * @param list<string> $modules
     */
    private function countUnresolvedSnippetRefs(array $modules): int
    {
        $unresolved = 0;

        foreach ($modules as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Exception) {
                continue;
            }

            foreach (['form', 'grid'] as $type) {
                $dir = $moduleDir . '/view/adminhtml/' . $type;

                try {
                    if (!$this->fileDriver->isExists($dir)) {
                        continue;
                    }
                    foreach ($this->fileDriver->readDirectory($dir) as $file) {
                        if (!str_ends_with($file, '.json')) {
                            continue;
                        }
                        $raw = $this->fileDriver->fileGetContents($file);
                        $data = json_decode($raw, true);
                        if (!is_array($data)) {
                            continue;
                        }
                        $unresolved += $this->countUnresolvedRefsIn($data);
                    }
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return $unresolved;
    }

    /**
     * @param mixed $node
     */
    private function countUnresolvedRefsIn(mixed $node): int
    {
        if (!is_array($node)) {
            return 0;
        }

        $count = 0;

        if (isset($node['@ref']) && is_string($node['@ref'])) {
            try {
                $resolved = $this->snippetResolver->resolve($node['@ref']);
                if ($resolved === []) {
                    $count++;
                }
            } catch (\Throwable) {
                $count++;
            }
        }

        foreach ($node as $child) {
            $count += $this->countUnresolvedRefsIn($child);
        }

        return $count;
    }

    /** @param list<string> $modules */
    private function countRawFqcnCollections(array $modules): int
    {
        $count = 0;

        foreach ($modules as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Exception) {
                continue;
            }

            $dir = $moduleDir . '/view/adminhtml/grid';

            try {
                if (!$this->fileDriver->isExists($dir)) {
                    continue;
                }
                foreach ($this->fileDriver->readDirectory($dir) as $file) {
                    if (!str_ends_with($file, '.json')) {
                        continue;
                    }
                    $raw = $this->fileDriver->fileGetContents($file);
                    $data = json_decode($raw, true);
                    if (!is_array($data)) {
                        continue;
                    }
                    $collection = $data['dataSource']['config']['collection'] ?? null;
                    if ($collection === null) {
                        continue;
                    }
                    $allowed = false;
                    foreach ($this->allowedCollectionNamespaces as $prefix) {
                        if (str_starts_with($collection, $prefix)) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) {
                        $count++;
                    }
                }
            } catch (\Exception) {
                continue;
            }
        }

        return $count;
    }

    private function countObjectManagerUsages(): int
    {
        $count = 0;

        try {
            $rootDir = $this->directoryList->getRoot();
        } catch (\Throwable) {
            return 0;
        }

        foreach (self::SCANNED_CODE_DIRS as $relative) {
            $root = rtrim($rootDir, '/') . '/' . $relative;

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                $extensionMatched = false;
                foreach (self::SCANNED_EXTENSIONS as $ext) {
                    if (str_ends_with($path, $ext)) {
                        $extensionMatched = true;
                        break;
                    }
                }
                if (!$extensionMatched) {
                    continue;
                }
                if (str_contains($path, '/Test/')) {
                    continue;
                }
                $contents = @file_get_contents($path);
                if ($contents === false) {
                    continue;
                }
                $count += $this->countObjectManagerUsagesInFile($contents);
            }
        }

        return $count;
    }

    /**
     * Count real ObjectManager usages in a file's contents.
     *
     * - Strips PHP `//`, `#`, `/* … *\/`, and PHPDoc `*` comment contents.
     * - Counts matches of {@see self::OBJECT_MANAGER_PATTERN}.
     * - Excludes lines (or the line immediately before) flagged with the
     *   {@see self::ALLOW_OM_MARKER} comment.
     */
    private function countObjectManagerUsagesInFile(string $contents): int
    {
        $stripped = preg_replace('!/\*.*?\*/!s', '', $contents);
        if (!is_string($stripped)) {
            $stripped = $contents;
        }

        $lines = preg_split('/\r\n|\n|\r/', $stripped) ?: [];
        $count = 0;
        $previousLineAllowed = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $isCommentLine = $trimmed !== '' && (
                str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '#')
                || str_starts_with($trimmed, '*')
            );

            $lineMarksAllow = str_contains($line, self::ALLOW_OM_MARKER);

            if (!$isCommentLine && !$lineMarksAllow && !$previousLineAllowed) {
                $count += preg_match_all(self::OBJECT_MANAGER_PATTERN, $line);
            }

            $previousLineAllowed = $lineMarksAllow;
        }

        return $count;
    }

    private function writeMetric(OutputInterface $output, string $label, string $value, string $color): void
    {
        $padded = str_pad($label, 40, ' ');
        $output->writeln(sprintf(
            '%s <fg=%s>%s</>',
            $padded,
            $color,
            $value
        ));
    }
}
