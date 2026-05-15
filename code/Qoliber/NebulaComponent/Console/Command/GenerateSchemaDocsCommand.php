<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders the Nebula JSON schemas into human-readable Markdown.
 *
 * Output is deterministic: re-running the command on unchanged schemas
 * produces byte-identical files. That makes it safe to wire into CI as a
 * drift check (`--check` exits non-zero if regeneration would change the
 * committed docs).
 */
class GenerateSchemaDocsCommand extends Command
{
    private const SCHEMA_DIR = __DIR__ . '/../../schemas';
    private const DOCS_DIR   = __DIR__ . '/../../docs/schemas';

    /** @var array<string, array{schema: string, out: string, title: string}> */
    private const TARGETS = [
        'grid' => [
            'schema' => 'grid.schema.json',
            'out'    => 'GRID.md',
            'title'  => 'Nebula Grid Definition',
        ],
        'form' => [
            'schema' => 'form.schema.json',
            'out'    => 'FORM.md',
            'title'  => 'Nebula Form Definition',
        ],
        'eav_form' => [
            'schema' => 'eav-form.schema.json',
            'out'    => 'EAV_FORM.md',
            'title'  => 'Nebula EAV Form Definition',
        ],
        'snippet' => [
            'schema' => 'snippet.schema.json',
            'out'    => 'SNIPPET.md',
            'title'  => 'Nebula Snippet Definition',
        ],
        'pagebuilder' => [
            'schema' => 'pagebuilder-content-type.schema.json',
            'out'    => 'PAGEBUILDER.md',
            'title'  => 'Nebula PageBuilder Content Type',
        ],
    ];

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('nebula:generate:schema-docs')
            ->setDescription('Render Nebula JSON schemas as Markdown reference docs.')
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Exit non-zero if generated docs would differ from committed docs (CI drift check).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $check = (bool) $input->getOption('check');
        $drift = [];

        foreach (self::TARGETS as $key => $target) {
            $schemaPath = self::SCHEMA_DIR . '/' . $target['schema'];
            $outPath    = self::DOCS_DIR . '/' . $target['out'];

            $raw = @file_get_contents($schemaPath);
            if ($raw === false) {
                $output->writeln('<fg=red>Missing schema: ' . $schemaPath . '</>');
                return Command::FAILURE;
            }

            $schema = json_decode($raw, true);
            if (!is_array($schema)) {
                $output->writeln('<fg=red>Invalid schema JSON: ' . $schemaPath . '</>');
                return Command::FAILURE;
            }

            $md = $this->renderSchema($target['title'], $schema);

            if ($check) {
                $existing = @file_get_contents($outPath);
                if ($existing === false || $existing !== $md) {
                    $drift[] = $outPath;
                }
                continue;
            }

            if (!is_dir(dirname($outPath))) {
                mkdir(dirname($outPath), 0o755, true);
            }
            file_put_contents($outPath, $md);
            $output->writeln('<info>wrote</info> ' . $outPath);
        }

        if ($check) {
            if ($drift !== []) {
                $output->writeln('<fg=red>Schema docs drift detected. Re-run `bin/magento nebula:generate:schema-docs`.</>');
                foreach ($drift as $path) {
                    $output->writeln('  - ' . $path);
                }
                return Command::FAILURE;
            }
            $output->writeln('<info>Schema docs are up to date.</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function renderSchema(string $title, array $schema): string
    {
        $lines = [];
        $lines[] = '# ' . $title;
        $lines[] = '';
        $lines[] = '> Generated from `' . ($schema['$id'] ?? '(no $id)') . '` by `bin/magento nebula:generate:schema-docs`.';
        $lines[] = '> Do not edit by hand — re-run the generator instead.';
        $lines[] = '';
        if (!empty($schema['description'])) {
            $lines[] = $schema['description'];
            $lines[] = '';
        }

        $lines[] = '## Root object';
        $lines[] = '';
        $lines[] = $this->renderPropertiesTable($schema);

        if (isset($schema['$defs']) && is_array($schema['$defs'])) {
            $lines[] = '';
            $lines[] = '## Definitions';
            $lines[] = '';
            $defs = $schema['$defs'];
            ksort($defs);
            foreach ($defs as $name => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $lines[] = '### `' . $name . '`';
                $lines[] = '';
                if (!empty($def['title'])) {
                    $lines[] = '**' . $def['title'] . '**';
                    $lines[] = '';
                }
                if (!empty($def['description'])) {
                    $lines[] = $def['description'];
                    $lines[] = '';
                }
                $lines[] = $this->renderPropertiesTable($def);
                $lines[] = '';
            }
        }

        $md = implode("\n", $lines);
        return rtrim($md) . "\n";
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderPropertiesTable(array $node): string
    {
        $props = $node['properties'] ?? null;
        if (!is_array($props) || $props === []) {
            $enum = $node['enum'] ?? null;
            if (is_array($enum)) {
                return '*Enum:* ' . implode(', ', array_map(static fn ($v) => '`' . json_encode($v) . '`', $enum));
            }
            return '*(no named properties)*';
        }
        $required = $node['required'] ?? [];
        if (!is_array($required)) {
            $required = [];
        }
        $requiredSet = array_flip($required);

        $lines   = [];
        $lines[] = '| Property | Type | Required | Default | Description |';
        $lines[] = '|---|---|---|---|---|';

        ksort($props);
        foreach ($props as $name => $schema) {
            if (!is_array($schema)) {
                continue;
            }
            $type     = $this->describeType($schema);
            $isReq    = isset($requiredSet[$name]) ? 'yes' : 'no';
            $default  = array_key_exists('default', $schema) ? '`' . json_encode($schema['default']) . '`' : '—';
            $desc     = $this->describeDescription($schema);

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                $name,
                $type,
                $isReq,
                $default,
                $desc
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function describeType(array $schema): string
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return '`' . $this->shortRef($schema['$ref']) . '`';
        }
        if (isset($schema['const'])) {
            return 'const `' . json_encode($schema['const']) . '`';
        }
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            return 'enum ' . implode('\\|', array_map(static fn ($v) => '`' . json_encode($v) . '`', $schema['enum']));
        }
        if (isset($schema['type'])) {
            $type = $schema['type'];
            if (is_array($type)) {
                return implode('\\|', array_map(static fn ($t) => '`' . $t . '`', $type));
            }
            $label = '`' . $type . '`';
            if ($type === 'array' && isset($schema['items']['$ref'])) {
                $label .= ' of `' . $this->shortRef($schema['items']['$ref']) . '`';
            } elseif ($type === 'array' && isset($schema['items']['type'])) {
                $label .= ' of `' . (is_array($schema['items']['type']) ? implode('|', $schema['items']['type']) : $schema['items']['type']) . '`';
            } elseif ($type === 'object' && isset($schema['additionalProperties']['$ref'])) {
                $label .= ' of `' . $this->shortRef($schema['additionalProperties']['$ref']) . '`';
            }
            return $label;
        }
        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            return '*(union — see schema)*';
        }
        return '*(any)*';
    }

    private function shortRef(string $ref): string
    {
        $parts = explode('/', $ref);
        return end($parts) ?: $ref;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function describeDescription(array $schema): string
    {
        $title = isset($schema['title']) && is_string($schema['title']) ? $schema['title'] : '';
        $desc  = isset($schema['description']) && is_string($schema['description']) ? $schema['description'] : '';

        $combined = trim($title);
        if ($desc !== '') {
            if ($combined !== '') {
                $combined .= '. ';
            }
            $combined .= $desc;
        }

        if (isset($schema['pattern']) && is_string($schema['pattern'])) {
            $combined .= ' Pattern: `' . $schema['pattern'] . '`.';
        }
        if (isset($schema['minimum'])) {
            $combined .= ' Min: ' . $schema['minimum'] . '.';
        }
        if (isset($schema['maximum'])) {
            $combined .= ' Max: ' . $schema['maximum'] . '.';
        }

        // Markdown escape pipes.
        $combined = str_replace('|', '\\|', $combined);
        $combined = str_replace("\n", ' ', $combined);
        return $combined !== '' ? trim($combined) : '—';
    }
}
