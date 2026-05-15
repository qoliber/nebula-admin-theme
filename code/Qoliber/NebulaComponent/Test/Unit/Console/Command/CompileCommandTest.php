<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Console\Command;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Console\Command\CompileCommand;
use Qoliber\NebulaComponent\Model\Definition\Loader;
use Qoliber\NebulaComponent\Model\Definition\Merger;
use Qoliber\NebulaComponent\Model\Definition\Resolver;
use Symfony\Component\Console\Tester\CommandTester;

class CompileCommandTest extends TestCase
{
    private string $tmpRoot;
    private string $moduleDir;
    private string $manifestPath;

    private DirReader&MockObject $dirReader;
    private ModuleListInterface&MockObject $moduleList;
    private DirectoryList&MockObject $directoryList;
    private State&MockObject $appState;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/nebula-compile-' . bin2hex(random_bytes(6));
        $this->moduleDir = $this->tmpRoot . '/app/code/Vendor/Demo';
        $this->manifestPath = $this->tmpRoot . '/' . Resolver::COMPILED_MANIFEST_RELATIVE_PATH;

        mkdir($this->moduleDir . '/view/adminhtml/grid', 0777, true);
        mkdir($this->moduleDir . '/view/adminhtml/form', 0777, true);

        file_put_contents(
            $this->moduleDir . '/view/adminhtml/grid/demo_listing.json',
            $this->demoGridJson('demo_listing')
        );
        file_put_contents(
            $this->moduleDir . '/view/adminhtml/form/demo_edit.json',
            $this->demoFormJson('demo_edit')
        );

        $this->dirReader = $this->createMock(DirReader::class);
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->appState = $this->createMock(State::class);

        $this->moduleList->method('getNames')->willReturn(['Vendor_Demo']);
        $this->dirReader->method('getModuleDir')
            ->with('', 'Vendor_Demo')
            ->willReturn($this->moduleDir);
        $this->directoryList->method('getRoot')->willReturn($this->tmpRoot);
        $this->appState->method('getMode')->willReturn(State::MODE_DEVELOPER);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpRoot);
    }

    public function testCompileWritesDeterministicManifest(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $code = $tester->execute([]);

        $this->assertSame(0, $code, $tester->getDisplay());
        $this->assertFileExists($this->manifestPath);

        $once = (string) file_get_contents($this->manifestPath);

        // Re-run — byte-identical.
        $tester2 = new CommandTester($this->makeCommand());
        $tester2->execute([]);
        $twice = (string) file_get_contents($this->manifestPath);

        $this->assertSame($once, $twice, 'Compile output must be byte-identical across runs.');

        // Structural assertions.
        $manifest = require $this->manifestPath;
        $this->assertArrayHasKey('grid:demo_listing', $manifest);
        $this->assertArrayHasKey('form:demo_edit', $manifest);
        $this->assertSame('demo_listing', $manifest['grid:demo_listing']['id']);
        $this->assertSame('demo_edit', $manifest['form:demo_edit']['id']);

        // Deterministic key order: sorted lexicographically.
        $keys = array_keys($manifest);
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys, 'Manifest keys must be sorted.');
    }

    public function testVerifyPassesWhenUpToDate(): void
    {
        // First, produce a manifest.
        (new CommandTester($this->makeCommand()))->execute([]);

        $tester = new CommandTester($this->makeCommand());
        $code = $tester->execute(['--verify' => true]);

        $this->assertSame(0, $code, $tester->getDisplay());
        $this->assertStringContainsString('Verify OK', $tester->getDisplay());
    }

    public function testVerifyFailsOnDrift(): void
    {
        (new CommandTester($this->makeCommand()))->execute([]);

        // Simulate drift: mutate the manifest.
        file_put_contents($this->manifestPath, "<?php\nreturn [];\n");

        $tester = new CommandTester($this->makeCommand());
        $code = $tester->execute(['--verify' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('drift', strtolower($tester->getDisplay()));
    }

    public function testOnlyCompilesSingleDefinition(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $code = $tester->execute(['--only' => 'grid:demo_listing']);

        $this->assertSame(0, $code, $tester->getDisplay());
        $manifest = require $this->manifestPath;
        $this->assertArrayHasKey('grid:demo_listing', $manifest);
        $this->assertArrayNotHasKey('form:demo_edit', $manifest);
    }

    public function testOnlyRejectsMalformedSpec(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['--only' => 'notAPair']);
    }

    // =========================================================================
    // helpers
    // =========================================================================

    private function makeCommand(): CompileCommand
    {
        // Build a real Loader + Merger + SnippetResolver so resolve() walks real files.
        $realFileDriver = new FileDriver();
        $logger = new \Psr\Log\NullLogger();

        $loader = new Loader($this->dirReader, $realFileDriver, $this->moduleList, $logger);
        $merger = new Merger($logger);
        $cache = $this->createMock(CacheInterface::class);
        $json = new Json();
        $snippet = $this->createMock(SnippetResolverInterface::class);
        // Snippet resolver passthrough for the 'layout' key.
        $snippet->method('resolveLayout')->willReturnArgument(0);

        $resolver = new Resolver(
            $loader,
            $merger,
            $cache,
            $json,
            $snippet,
            $this->appState,
            $this->moduleList,
            $this->directoryList
        );

        return new CompileCommand(
            $this->dirReader,
            $realFileDriver,
            $this->moduleList,
            $resolver,
            $this->directoryList
        );
    }

    private function demoGridJson(string $id): string
    {
        return json_encode([
            'id' => $id,
            'dataSource' => [
                'provider' => 'demo.listing',
            ],
            'columns' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'position' => 10,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function demoFormJson(string $id): string
    {
        return json_encode([
            'id' => $id,
            'fieldsets' => [
                'general' => [
                    'label' => 'General',
                    'position' => 10,
                    'fields' => [
                        'name' => [
                            'type' => 'text',
                            'label' => 'Name',
                            'position' => 10,
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
