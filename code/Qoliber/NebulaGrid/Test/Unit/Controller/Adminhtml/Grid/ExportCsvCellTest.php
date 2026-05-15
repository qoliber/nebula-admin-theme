<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Controller\Adminhtml\Grid;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaGrid\Controller\Adminhtml\Grid\Export;

/**
 * Pins the CSV-injection-defusal behaviour added in P1-7.
 *
 * The Export controller has private csvCell(); we exercise it via reflection
 * because it's a pure string transform and deserves direct test coverage —
 * stubbing out FileFactory + DataProviderResolver + DefinitionResolver to
 * exercise it through execute() would be five times the test for the same
 * coverage.
 */
class ExportCsvCellTest extends TestCase
{
    private function csvCell(string $value): string
    {
        // Reflection-backed thin shim — keeps the test focused on behaviour
        // without instantiating the controller's heavy dependency graph.
        $rc = new \ReflectionClass(Export::class);
        $method = $rc->getMethod('csvCell');
        $method->setAccessible(true);

        $instance = $rc->newInstanceWithoutConstructor();
        return (string) $method->invoke($instance, $value);
    }

    public function testPlainTextIsQuotedNotPrefixed(): void
    {
        $this->assertSame('"hello"', $this->csvCell('hello'));
    }

    public function testEmptyStringIsQuotedNotPrefixed(): void
    {
        $this->assertSame('""', $this->csvCell(''));
    }

    public function testEmbeddedQuotesAreDoubled(): void
    {
        $this->assertSame('"she said ""hi"""', $this->csvCell('she said "hi"'));
    }

    /**
     * @dataProvider injectionTriggers
     */
    public function testFormulaTriggersGetApostrophePrefix(string $value): void
    {
        $expected = '"\'' . str_replace('"', '""', $value) . '"';
        $this->assertSame($expected, $this->csvCell($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionTriggers(): array
    {
        return [
            'equals'         => ['=SUM(A1:A10)'],
            'plus'           => ['+1+1'],
            'minus'          => ['-2+3'],
            'at'             => ['@cmd|"calc"!A1'],
            'tab'            => ["\tEvil"],
            'carriage'       => ["\rNasty"],
        ];
    }

    public function testTriggerCharInTheMiddleIsHarmless(): void
    {
        // Only leading-character triggers fire formula evaluation. A literal
        // dash in the middle of a sentence must not be prefixed.
        $this->assertSame('"hello-world"', $this->csvCell('hello-world'));
    }
}
