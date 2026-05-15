<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model\Registry;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaGrid\Model\Registry\CollectionRegistry;

/**
 * Covers the alias → FQCN registry that replaced CollectionProvider's
 * prefix-allowlist in P0-6. Pure data-structure behaviour — no Magento
 * boot or ObjectManager dependency — so this test runs anywhere PHPUnit
 * does.
 */
class CollectionRegistryTest extends TestCase
{
    public function testResolveReturnsBoundFqcn(): void
    {
        $registry = new CollectionRegistry([
            'cms_page.collection' => 'Magento\Cms\Model\ResourceModel\Page\Collection',
        ]);

        $this->assertSame(
            'Magento\Cms\Model\ResourceModel\Page\Collection',
            $registry->resolve('cms_page.collection')
        );
    }

    public function testHasReportsRegisteredAliases(): void
    {
        $registry = new CollectionRegistry(['cms_page.collection' => 'Foo\\Bar']);
        $this->assertTrue($registry->has('cms_page.collection'));
        $this->assertFalse($registry->has('made.up.alias'));
    }

    public function testResolveThrowsUnknownAliasException(): void
    {
        $registry = new CollectionRegistry(['cms_page.collection' => 'Foo\\Bar']);

        $this->expectException(UnknownAliasException::class);
        $registry->resolve('unknown.alias');
    }

    public function testRuntimeRegisterOverridesDiBinding(): void
    {
        $registry = new CollectionRegistry([
            'cms_page.collection' => 'Foo\\Original',
        ]);
        $registry->register('cms_page.collection', 'Foo\\Override');

        $this->assertSame('Foo\\Override', $registry->resolve('cms_page.collection'));
    }

    public function testRegisterRejectsEmptyAlias(): void
    {
        $registry = new CollectionRegistry();
        $this->expectException(\InvalidArgumentException::class);
        $registry->register('   ', 'Foo\\Bar');
    }

    public function testAllReturnsMergedBindings(): void
    {
        $registry = new CollectionRegistry([
            'a' => 'A',
            'b' => 'B',
        ]);
        $registry->register('b', 'B-overridden');
        $registry->register('c', 'C');

        $all = $registry->all();
        $this->assertSame('A', $all['a']);
        $this->assertSame('B-overridden', $all['b']);
        $this->assertSame('C', $all['c']);
    }

    public function testNormaliseDropsBlankBindings(): void
    {
        $registry = new CollectionRegistry([
            'real.alias' => 'Foo\\Bar',
            ''           => 'Foo\\Baz',   // blank alias — drop
            'blank.fqcn' => '   ',         // blank class name — drop
        ]);

        $this->assertTrue($registry->has('real.alias'));
        $this->assertFalse($registry->has(''));
        $this->assertFalse($registry->has('blank.fqcn'));
    }
}
