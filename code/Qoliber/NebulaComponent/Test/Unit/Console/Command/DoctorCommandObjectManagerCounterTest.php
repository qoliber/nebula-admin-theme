<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Console\Command;

use Magento\Framework\App\Cache\TypeListInterface as CacheTypeListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Console\Command\DoctorCommand;
use Qoliber\NebulaComponent\Model\RendererPool;

/**
 * Focused test for {@see DoctorCommand::countObjectManagerUsagesInFile()}. Uses
 * reflection to exercise the private method with controlled inputs so the count
 * semantics are locked down: real usages count, commented/docstring mentions
 * and explicitly allowed lines don't.
 */
class DoctorCommandObjectManagerCounterTest extends TestCase
{
    public function testCountsRealObjectManagerUsage(): void
    {
        $count = $this->countInContents(<<<'PHP'
<?php
use Magento\Framework\ObjectManagerInterface;

class Foo
{
    public function __construct(private ObjectManagerInterface $om) {}
    public function bar(): void
    {
        $x = \Magento\Framework\App\ObjectManager::getInstance()->get(\stdClass::class);
    }
}
PHP);

        $this->assertSame(2, $count);
    }

    public function testDocstringMentionsAreIgnored(): void
    {
        $count = $this->countInContents(<<<'PHP'
<?php
/**
 * Explains why using \Magento\Framework\App\ObjectManager::getInstance() is bad
 * and points at \Magento\Framework\ObjectManagerInterface for the correct type.
 */
class Bar
{
    public function noop(): void {}
}
PHP);

        $this->assertSame(0, $count);
    }

    public function testInlineCommentMentionsAreIgnored(): void
    {
        $count = $this->countInContents(<<<'PHP'
<?php
// We could call \Magento\Framework\App\ObjectManager::getInstance() here but don't.
class Baz {}
PHP);

        $this->assertSame(0, $count);
    }

    public function testAllowOmMarkerExcludesFollowingUsage(): void
    {
        $count = $this->countInContents(<<<'PHP'
<?php
class Qux
{
    public function __construct(
        // nebula:allow-object-manager dynamic-fqcn-dispatch
        private \Magento\Framework\ObjectManagerInterface $objectManager
    ) {}
}
PHP);

        $this->assertSame(0, $count);
    }

    public function testAllowMarkerOnSameLineIsHonored(): void
    {
        $count = $this->countInContents(<<<'PHP'
<?php
$x = \Magento\Framework\App\ObjectManager::getInstance(); // nebula:allow-object-manager legacy-root-template
PHP);

        $this->assertSame(0, $count);
    }

    private function countInContents(string $contents): int
    {
        $command = new DoctorCommand(
            $this->createMock(DirReader::class),
            $this->createMock(FileDriver::class),
            $this->createMock(ModuleListInterface::class),
            new RendererPool([], []),
            $this->createMock(SnippetResolverInterface::class),
            $this->createMock(CacheTypeListInterface::class),
            $this->createMock(DirectoryList::class)
        );

        $reflection = new \ReflectionMethod($command, 'countObjectManagerUsagesInFile');
        $reflection->setAccessible(true);

        return (int) $reflection->invoke($command, $contents);
    }
}
