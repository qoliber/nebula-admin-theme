<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Console\Command;

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Strict JSON Schema validator for Nebula definitions.
 *
 * Validates every grid/form/snippet/content_type JSON against the Draft 2020-12
 * schemas shipped under NebulaComponent/schemas/. Supports `--fix` to reformat
 * files (alphabetize columns, auto-position, 2-space indent, trailing newline).
 */
class LintCommand extends Command
{
    private const SCHEMA_DIR = __DIR__ . '/../../schemas';

    /** @var array<string, string> directory slug => schema filename */
    private const SCHEMA_MAP = [
        'grid'         => 'grid.schema.json',
        'form'         => 'form.schema.json',
        'form_eav'     => 'eav-form.schema.json',
        'snippet'      => 'snippet.schema.json',
        'content_type' => 'pagebuilder-content-type.schema.json',
    ];

    public function __construct(
        private readonly DirReader $dirReader,
        private readonly FileDriver $fileDriver,
        private readonly ModuleListInterface $moduleList,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('nebula:lint')
            ->setDescription('Validate every Nebula JSON definition against its Draft 2020-12 schema.')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Apply automated fixes (reformat, alphabetize, auto-position)')
            ->addOption('no-strict', null, InputOption::VALUE_NONE, 'Relax validation: skip additionalProperties checks')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Validate a single file or directory instead of scanning all enabled modules'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fix    = (bool) $input->getOption('fix');
        $strict = !$input->getOption('no-strict');
        $path   = $input->getOption('path');

        $files = $path !== null
            ? $this->collectFilesFromPath((string) $path)
            : $this->collectFilesFromModules();

        if ($files === []) {
            $output->writeln('<comment>No Nebula JSON definitions found.</comment>');
            return Command::SUCCESS;
        }

        $schemas     = $this->loadSchemas();
        $totalErrors = 0;
        $fixed       = 0;
        $passed      = 0;

        foreach ($files as $file => $kind) {
            if ($fix) {
                $didFix = $this->applyFixes($file, $kind);
                if ($didFix) {
                    $fixed++;
                }
            }

            $errors = $this->validate($file, $kind, $schemas, $strict);

            if ($errors === []) {
                $passed++;
                continue;
            }

            $output->writeln('<comment>' . $file . '</comment>');
            foreach ($errors as $err) {
                $output->writeln('  <fg=red>' . $err . '</>');
                $totalErrors++;
            }
        }

        $output->writeln('');

        if ($totalErrors === 0) {
            $output->writeln(sprintf(
                '<info>All %d Nebula definitions pass schema validation.</info>',
                $passed
            ));
            if ($fix && $fixed > 0) {
                $output->writeln(sprintf('<info>%d file(s) auto-fixed.</info>', $fixed));
            }
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<fg=red>%d error(s) across %d file(s). %d file(s) passed.</>',
            $totalErrors,
            count($files) - $passed,
            $passed
        ));
        if ($fix && $fixed > 0) {
            $output->writeln(sprintf('<info>%d file(s) auto-fixed.</info>', $fixed));
        }

        return Command::FAILURE;
    }

    /**
     * @return array<string, string> absolute file path => definition kind
     */
    private function collectFilesFromModules(): array
    {
        $result = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Throwable) {
                continue;
            }

            foreach (['grid', 'form', 'snippet', 'content_type'] as $kind) {
                $dir = $moduleDir . '/view/adminhtml/' . $kind;

                try {
                    if (!$this->fileDriver->isExists($dir)) {
                        continue;
                    }
                    $entries = $this->fileDriver->readDirectory($dir);
                } catch (\Throwable) {
                    continue;
                }

                foreach ($entries as $entry) {
                    if (!str_ends_with($entry, '.json')) {
                        continue;
                    }
                    $result[$entry] = $kind;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function collectFilesFromPath(string $path): array
    {
        $result = [];

        if (is_file($path) && str_ends_with($path, '.json')) {
            $kind = $this->guessKindFromPath($path);
            if ($kind !== null) {
                $result[$path] = $kind;
            }
            return $result;
        }

        if (!is_dir($path)) {
            return [];
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $item */
        foreach ($iter as $item) {
            if (!$item->isFile() || $item->getExtension() !== 'json') {
                continue;
            }
            $full = (string) $item->getRealPath();
            $kind = $this->guessKindFromPath($full);
            if ($kind !== null) {
                $result[$full] = $kind;
            }
        }

        return $result;
    }

    private function guessKindFromPath(string $file): ?string
    {
        foreach (['grid', 'form', 'snippet', 'content_type'] as $kind) {
            if (str_contains($file, '/view/adminhtml/' . $kind . '/')) {
                return $kind;
            }
        }
        return null;
    }

    /**
     * @return array<string, object> schema key => decoded schema object
     */
    private function loadSchemas(): array
    {
        $schemas = [];
        foreach (self::SCHEMA_MAP as $key => $filename) {
            $path = self::SCHEMA_DIR . '/' . $filename;
            $raw  = file_get_contents($path);
            if ($raw === false) {
                throw new \RuntimeException('Cannot read schema ' . $path);
            }
            $decoded = json_decode($raw);
            if ($decoded === null) {
                throw new \RuntimeException('Cannot decode schema ' . $path . ': ' . json_last_error_msg());
            }
            $schemas[$key] = $decoded;
        }
        return $schemas;
    }

    /**
     * @param array<string, object> $schemas
     * @return array<int, string>
     */
    private function validate(string $file, string $kind, array $schemas, bool $strict): array
    {
        $errors = [];

        try {
            $raw = $this->fileDriver->fileGetContents($file);
        } catch (\Throwable $e) {
            return ['Cannot read file: ' . $e->getMessage()];
        }

        $dataObj = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['Invalid JSON: ' . json_last_error_msg()];
        }

        $dataArr = json_decode($raw, true);

        $schemaKey = $kind;
        if ($kind === 'form' && is_array($dataArr) && ($dataArr['type'] ?? null) === 'eav') {
            $schemaKey = 'form_eav';
        }

        if (!isset($schemas[$schemaKey])) {
            return ['No schema for kind "' . $kind . '"'];
        }

        $storage = new SchemaStorage();
        $storage->addSchema('file://nebula/form.v1.json', $schemas['form']);
        $storage->addSchema('https://nebula.qoliber.dev/schemas/form.v1.json', $schemas['form']);

        $validator = new Validator(new \JsonSchema\Constraints\Factory($storage));
        // Strict validation: no type coercion, no default injection.
        $validator->validate($dataObj, $schemas[$schemaKey], 0);

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $err) {
                $property = (string) ($err['property'] ?? '');
                $pointer  = $property === '' ? '#' : '#/' . $this->toJsonPointer($property);
                $errors[] = sprintf('[%s] %s', $pointer, $err['message']);
            }
        }

        // Additional structural rules not expressible in JSON Schema:
        if (is_array($dataArr)) {
            $basename = basename($file, '.json');
            if (isset($dataArr['id']) && is_string($dataArr['id']) && $dataArr['id'] !== $basename) {
                $errors[] = sprintf('[#/id] value "%s" must match filename "%s"', $dataArr['id'], $basename);
            }
            if ($kind === 'content_type' && isset($dataArr['name']) && is_string($dataArr['name'])) {
                $normalized = str_replace('-', '_', $dataArr['name']);
                if ($normalized !== $basename) {
                    $errors[] = sprintf(
                        '[#/name] value "%s" must match filename "%s" (dashes become underscores in filenames)',
                        $dataArr['name'],
                        $basename
                    );
                }
            }
        }

        // `--no-strict` is reserved for future use; today every schema is strict via additionalProperties: false.
        unset($strict);

        return $errors;
    }

    private function toJsonPointer(string $property): string
    {
        // justinrainbow emits paths like "columns.name" or "layout[1].children[0]".
        // Convert to JSON Pointer (slash-separated, array indexes as numbers).
        $pointer = str_replace('.', '/', $property);
        $pointer = preg_replace('/\[(\d+)]/', '/$1', $pointer) ?? $pointer;
        return ltrim($pointer, '/');
    }

    private function applyFixes(string $file, string $kind): bool
    {
        try {
            $raw = $this->fileDriver->fileGetContents($file);
        } catch (\Throwable) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }

        $before = $raw;

        if ($kind === 'grid' && isset($data['columns']) && is_array($data['columns'])) {
            $data['columns'] = $this->autoPosition($data['columns']);
        }
        if ($kind === 'form') {
            if (isset($data['fieldsets']) && is_array($data['fieldsets'])) {
                foreach ($data['fieldsets'] as $fsKey => $fieldset) {
                    if (isset($fieldset['fields']) && is_array($fieldset['fields'])) {
                        $data['fieldsets'][$fsKey]['fields'] = $this->autoPosition($fieldset['fields']);
                    }
                }
            }
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return false;
        }
        // Force 2-space indent (json_encode uses 4 spaces).
        $encoded = preg_replace_callback(
            '/^( +)/m',
            static fn (array $m) => str_repeat('  ', intdiv(strlen($m[1]), 4)),
            $encoded
        );
        $encoded = rtrim($encoded) . "\n";

        if ($encoded === $before) {
            return false;
        }

        try {
            $this->fileDriver->filePutContents($file, $encoded);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Add incremental `position` keys to items that lack one. Preserves given positions.
     *
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    private function autoPosition(array $map): array
    {
        $seen = [];
        foreach ($map as $item) {
            if (is_array($item) && isset($item['position']) && is_int($item['position'])) {
                $seen[] = $item['position'];
            }
        }
        $next = empty($seen) ? 10 : (max($seen) + 10);

        foreach ($map as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!isset($item['position'])) {
                $item['position'] = $next;
                $map[$key] = $item;
                $next += 10;
            }
        }

        return $map;
    }
}
