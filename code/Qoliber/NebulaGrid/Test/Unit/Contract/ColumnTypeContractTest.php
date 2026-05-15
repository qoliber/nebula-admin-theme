<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

/**
 * Contract test: grid.schema.json column type enum ↔ DI-registered columnRenderers stay in sync.
 *
 * No Magento bootstrap required — reads files directly from the filesystem.
 */
class ColumnTypeContractTest extends TestCase
{
    private string $magentoRoot;
    private string $schemaPath;
    private string $diPath;

    protected function setUp(): void
    {
        // __DIR__ = .../NebulaGrid/Test/Unit/Contract
        // dirname 7 levels up: Contract->Unit->Test->NebulaGrid->Qoliber->code->app->winqoo
        $this->magentoRoot = dirname(__DIR__, 7);
        $this->schemaPath  = $this->magentoRoot . '/app/code/Qoliber/NebulaComponent/schemas/grid.schema.json';
        $this->diPath      = $this->magentoRoot . '/app/code/Qoliber/NebulaGrid/etc/di.xml';
    }

    /** @return list<string> */
    private function schemaColumnTypes(): array
    {
        $this->assertFileExists($this->schemaPath, 'grid.schema.json must exist');

        $schema = json_decode((string) file_get_contents($this->schemaPath), true);
        $this->assertIsArray($schema, 'grid.schema.json must be valid JSON');

        $enum = $schema['$defs']['column']['properties']['type']['enum'] ?? null;
        $this->assertIsArray($enum, 'Schema must contain $defs.column.properties.type.enum array');

        return array_values($enum);
    }

    /** @return list<string> */
    private function diRegisteredTypes(): array
    {
        $this->assertFileExists($this->diPath, 'NebulaGrid di.xml must exist');

        $xml = simplexml_load_file($this->diPath);
        $this->assertNotFalse($xml, 'di.xml must be parseable XML');

        $items = $xml->xpath('//argument[@name="columnRenderers"]/item');
        $this->assertIsArray($items, 'di.xml must contain argument[@name="columnRenderers"]');
        $this->assertNotEmpty($items, 'columnRenderers argument must have at least one item');

        return array_values(array_map(static fn (\SimpleXMLElement $item): string => (string) $item['name'], $items));
    }

    public function testSchemaTypesAllHaveRegisteredRenderers(): void
    {
        $schemaTypes     = $this->schemaColumnTypes();
        $registeredTypes = $this->diRegisteredTypes();

        $missing = array_values(array_diff($schemaTypes, $registeredTypes));

        $this->assertEmpty(
            $missing,
            sprintf(
                "Schema enum contains types with no DI renderer: [%s]\n" .
                "Schema types:    [%s]\n" .
                "Renderer types:  [%s]",
                implode(', ', $missing),
                implode(', ', $schemaTypes),
                implode(', ', $registeredTypes),
            )
        );
    }

    public function testRegisteredRenderersAllAppearInSchemaEnum(): void
    {
        $schemaTypes     = $this->schemaColumnTypes();
        $registeredTypes = $this->diRegisteredTypes();

        $missing = array_values(array_diff($registeredTypes, $schemaTypes));

        $this->assertEmpty(
            $missing,
            sprintf(
                "DI-registered renderers not present in schema enum: [%s]\n" .
                "Add them to \$defs.column.properties.type.enum in grid.schema.json.\n" .
                "Schema types:    [%s]\n" .
                "Renderer types:  [%s]",
                implode(', ', $missing),
                implode(', ', $schemaTypes),
                implode(', ', $registeredTypes),
            )
        );
    }

    public function testSchemaEnumContainsExpectedBaselineTypes(): void
    {
        $schemaTypes = $this->schemaColumnTypes();

        $baseline = ['text', 'date', 'price', 'thumbnail', 'badge', 'boolean', 'link', 'html', 'actions'];

        foreach ($baseline as $type) {
            $this->assertContains(
                $type,
                $schemaTypes,
                "Expected baseline type '$type' missing from schema enum"
            );
        }
    }
}
