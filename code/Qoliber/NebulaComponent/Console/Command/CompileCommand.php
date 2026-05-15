<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Console\Command;

use JsonSchema\Constraints\Factory as JsonSchemaFactory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator as JsonSchemaValidator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use Qoliber\NebulaComponent\Model\Definition\Resolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compile all Nebula JSON definitions into a single deterministic PHP manifest.
 *
 * Scans `view/adminhtml/{grid,form,snippet,content_type}/*.json` across every enabled module,
 * resolves each fully (Loader+Merger+SnippetResolver via Resolver::resolveLive()), validates
 * each merged payload against its schema, and writes the result to
 * `generated/nebula/definitions.php` keyed by `"<type>:<id>"`.
 *
 * The manifest is what production-mode Nebula loads; it must be deterministic so the file only
 * changes when definitions change (not on re-runs with the same inputs).
 */
class CompileCommand extends Command
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

    private const DEFINITION_TYPES = ['grid', 'form', 'snippet', 'content_type'];

    public function __construct(
        private readonly DirReader $dirReader,
        private readonly FileDriver $fileDriver,
        private readonly ModuleListInterface $moduleList,
        private readonly Resolver $resolver,
        private readonly DirectoryList $directoryList,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('nebula:compile')
            ->setDescription('Compile every Nebula definition into generated/nebula/definitions.php')
            ->addOption(
                'verify',
                null,
                InputOption::VALUE_NONE,
                'Compile and compare against the existing manifest; fail if drift detected.'
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_REQUIRED,
                'Compile a single definition by "type:id" (e.g. grid:product_listing).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verify = (bool) $input->getOption('verify');
        $only   = $input->getOption('only');

        $pairs = $only !== null
            ? $this->parseOnly((string) $only)
            : $this->discoverPairs();

        if ($pairs === []) {
            $output->writeln('<comment>No Nebula definitions to compile.</comment>');
            return Command::SUCCESS;
        }

        // Deterministic iteration order: sort by "type:id" key.
        ksort($pairs);

        $schemas = $this->loadSchemas();
        $schemaStorage = new SchemaStorage();
        $schemaStorage->addSchema('file://nebula/form.v1.json', $schemas['form']);
        $schemaStorage->addSchema('https://nebula.qoliber.dev/schemas/form.v1.json', $schemas['form']);

        $manifest   = [];
        $errors     = [];
        $warnings   = [];
        $validated  = 0;

        $progress = new ProgressBar($output, count($pairs));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  <info>%message%</info>');
        $progress->setMessage('starting...');
        $progress->start();

        foreach ($pairs as $key => $kind) {
            [$type, $id] = explode(':', $key, 2);
            $progress->setMessage($key);

            try {
                $merged = $this->resolver->resolveLive($type, $id);
            } catch (\Throwable $e) {
                $errors[] = sprintf('[%s] resolve failed: %s', $key, $e->getMessage());
                $progress->advance();
                continue;
            }

            $schemaKey = $this->schemaKeyFor($kind, $merged);
            if (isset($schemas[$schemaKey])) {
                // Post-merge schema validation is a warning, not a blocker. `nebula:lint` is the
                // authoritative gate and runs against raw JSON files where string-vs-integer keys
                // and empty-object vs empty-array are preserved. Post-merge PHP assoc arrays
                // lose both distinctions, producing false positives here. Keep the validation as
                // a smoke signal; never block the manifest on it.
                $schemaErrors = $this->validate($merged, $schemas[$schemaKey], $schemaStorage);
                if ($schemaErrors !== []) {
                    foreach ($schemaErrors as $err) {
                        $warnings[] = sprintf('[%s] %s', $key, $err);
                    }
                } else {
                    $validated++;
                }
            }

            $manifest[$key] = self::sortRecursive($merged);
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('');

        if ($warnings !== []) {
            foreach ($warnings as $warn) {
                $output->writeln('<comment>warn: ' . $warn . '</comment>');
            }
            $output->writeln(sprintf(
                '<comment>%d post-merge schema warning(s) (non-blocking; run "bin/magento nebula:lint" for authoritative raw-JSON validation).</comment>',
                count($warnings)
            ));
        }

        if ($errors !== []) {
            foreach ($errors as $err) {
                $output->writeln('<fg=red>' . $err . '</>');
            }
            $output->writeln(sprintf(
                '<fg=red>%d compile error(s). Manifest NOT written.</>',
                count($errors)
            ));
            return Command::FAILURE;
        }

        $rendered = $this->renderManifest($manifest);
        $destination = $this->manifestPath();

        if ($verify) {
            $current = is_file($destination) ? (string) file_get_contents($destination) : '';
            if ($current !== $rendered) {
                $output->writeln('<fg=red>Manifest drift detected. Re-run "bin/magento nebula:compile" and commit the result.</>');
                return Command::FAILURE;
            }
            $output->writeln(sprintf(
                '<info>Verify OK: %d definition(s), %d validated against schemas.</info>',
                count($manifest),
                $validated
            ));
            return Command::SUCCESS;
        }

        $this->ensureDirectory(dirname($destination));
        file_put_contents($destination, $rendered);

        $output->writeln(sprintf(
            '<info>Wrote %s (%d definition(s), %d validated against schemas).</info>',
            $destination,
            count($manifest),
            $validated
        ));
        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> "type:id" => kind slug
     */
    private function discoverPairs(): array
    {
        $result = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Throwable) {
                continue;
            }

            foreach (self::DEFINITION_TYPES as $kind) {
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
                    $id = basename($entry, '.json');
                    $key = $kind . ':' . $id;
                    // Two modules may expose the same (type, id) pair — dedupe; Resolver merges them.
                    $result[$key] = $kind;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function parseOnly(string $spec): array
    {
        if (!str_contains($spec, ':')) {
            throw new \InvalidArgumentException('--only expects "<type>:<id>", e.g. grid:product_listing');
        }
        [$type, $id] = explode(':', $spec, 2);
        if (!in_array($type, self::DEFINITION_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                '--only type must be one of %s; got "%s"',
                implode(', ', self::DEFINITION_TYPES),
                $type
            ));
        }
        if ($id === '') {
            throw new \InvalidArgumentException('--only id is empty');
        }
        return [$type . ':' . $id => $type];
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function schemaKeyFor(string $kind, array $merged): string
    {
        if ($kind === 'form' && ($merged['type'] ?? null) === 'eav') {
            return 'form_eav';
        }
        return $kind;
    }

    /**
     * @return array<string, object>
     */
    private function loadSchemas(): array
    {
        $schemas = [];
        foreach (self::SCHEMA_MAP as $key => $filename) {
            $path = self::SCHEMA_DIR . '/' . $filename;
            $raw  = @file_get_contents($path);
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
     * @param array<string, mixed> $merged
     * @return array<int, string>
     */
    private function validate(array $merged, object $schema, SchemaStorage $storage): array
    {
        $errors = [];

        // JSON schema validators want stdClass for object-typed nodes; PHP assoc arrays (including
        // empty ones) must be converted. json_encode + json_decode collapses empty `{}` to `[]`,
        // so we walk the tree ourselves and keep associative vs sequential arrays distinct.
        $normalized = self::toSchemaValue($merged);

        $validator = new JsonSchemaValidator(new JsonSchemaFactory($storage));
        $validator->validate($normalized, $schema, 0);

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $err) {
                $property = (string) ($err['property'] ?? '');
                $pointer  = $property === '' ? '#' : '#/' . self::toJsonPointer($property);
                $errors[] = sprintf('[%s] %s', $pointer, $err['message']);
            }
        }

        return $errors;
    }

    /**
     * Convert PHP data into a JSON-Schema-compatible value:
     * - empty arrays → stdClass (JSON `{}`, not `[]`)
     * - associative arrays → stdClass with converted children
     * - sequential arrays → list with converted children
     * - scalars and objects → unchanged
     */
    private static function toSchemaValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new \stdClass();
        }

        if (array_keys($value) === range(0, count($value) - 1)) {
            return array_map([self::class, 'toSchemaValue'], $value);
        }

        $obj = new \stdClass();
        foreach ($value as $k => $v) {
            $obj->{(string) $k} = self::toSchemaValue($v);
        }
        return $obj;
    }

    private static function toJsonPointer(string $property): string
    {
        $pointer = str_replace('.', '/', $property);
        $pointer = preg_replace('/\[(\d+)]/', '/$1', $pointer) ?? $pointer;
        return ltrim($pointer, '/');
    }

    /**
     * @param array<string, array> $manifest
     */
    private function renderManifest(array $manifest): string
    {
        ksort($manifest);

        // Render as `return [ 'type:id' => [...], ... ];` with two-space indent via var_export
        // normalization. var_export is inherently deterministic for arrays of scalars and
        // arrays whose keys we control, which we do (ksort above + sortRecursive at write time).
        $body = var_export($manifest, true);
        $body = self::normalizeExport($body);

        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "// AUTO-GENERATED by bin/magento nebula:compile. Do not edit by hand.\n"
            . "// Source: every view/adminhtml/{grid,form,snippet,content_type}/*.json across enabled modules.\n\n"
            . "return " . $body . ";\n";
    }

    /**
     * Canonicalise var_export output:
     *  - "array (" → "["; trailing ")" → "]".
     *  - Strip numeric-key prefixes "0 => ".
     *  - Re-indent from 2 spaces per level.
     */
    private static function normalizeExport(string $code): string
    {
        // Convert long-form array() to short [] syntax.
        $code = preg_replace('/\barray \(/', '[', $code) ?? $code;
        // Close paren on its own line (possibly preceded by whitespace+comma handled by var_export)
        $code = preg_replace('/(^|\n)(\s*)\)/', "$1$2]", $code) ?? $code;
        // Drop sequential numeric keys: "  0 => 'x'," → "  'x',"
        $code = preg_replace('/^(\s*)\d+ => /m', '$1', $code) ?? $code;
        return $code;
    }

    /**
     * Recursively ksort associative arrays and preserve the sequential order of numeric arrays.
     * This is the last line of defense against nondeterministic dictionary iteration order.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::sortRecursive($v);
        }
        if ($isAssoc) {
            ksort($out);
        }
        return $out;
    }

    private function manifestPath(): string
    {
        try {
            $root = $this->directoryList->getRoot();
        } catch (\Throwable) {
            $root = getcwd() ?: '.';
        }
        return rtrim($root, '/') . '/' . Resolver::COMPILED_MANIFEST_RELATIVE_PATH;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
