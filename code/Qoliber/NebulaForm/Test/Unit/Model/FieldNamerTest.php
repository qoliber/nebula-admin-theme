<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\FieldNamer;

class FieldNamerTest extends TestCase
{
    public function testNameWithoutPrefixReturnsRawCode(): void
    {
        $namer = new FieldNamer();

        $this->assertSame('sku', $namer->name('sku'));
        $this->assertSame('sku', $namer->name('sku', null));
        $this->assertSame('sku', $namer->name('sku', ''));
    }

    public function testNameWithPrefixWrapsInBrackets(): void
    {
        $namer = new FieldNamer();

        $this->assertSame('product[sku]', $namer->name('sku', 'product'));
        $this->assertSame('customer[firstname]', $namer->name('firstname', 'customer'));
    }

    public function testBracketedNameAppendsArraySuffix(): void
    {
        $namer = new FieldNamer();

        $this->assertSame('product[websites][]', $namer->bracketedName('websites', 'product'));
        $this->assertSame('websites[]', $namer->bracketedName('websites'));
    }

    public function testDomIdBuildsConsistentIds(): void
    {
        $namer = new FieldNamer();

        $this->assertSame('nebula-field-sku', $namer->domId('sku'));
        $this->assertSame('nebula-eav-sku', $namer->domId('sku', 'nebula-eav'));
    }
}
