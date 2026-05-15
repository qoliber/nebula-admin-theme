<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Definition;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Model\Definition\Loader;

class LoaderTest extends TestCase
{
    private Loader $loader;

    /** @var \Magento\Framework\Module\Dir\Reader&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $dirReader;

    /** @var \Magento\Framework\Filesystem\Driver\File&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $fileDriver;

    /** @var \Magento\Framework\Module\ModuleListInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $moduleList;

    /** @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->dirReader = $this->createMock(Reader::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->loader = new Loader(
            $this->dirReader,
            $this->fileDriver,
            $this->moduleList,
            $this->logger
        );
    }

    // =========================================================================
    // Empty / no results
    // =========================================================================

    public function testLoadReturnsEmptyForNoModules(): void
    {
        $this->moduleList->method('getNames')->willReturn([]);

        $result = $this->loader->load('grid', 'product_listing');

        $this->assertSame([], $result);
    }

    public function testLoadReturnsEmptyWhenNoFilesExist(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA', 'Vendor_ModuleB']);
        $this->dirReader->method('getModuleDir')->willReturnMap([
            ['', 'Vendor_ModuleA', '/app/code/Vendor/ModuleA'],
            ['', 'Vendor_ModuleB', '/app/code/Vendor/ModuleB'],
        ]);
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->loader->load('grid', 'product_listing');

        $this->assertSame([], $result);
    }

    // =========================================================================
    // Single and multiple definitions
    // =========================================================================

    public function testLoadReturnsSingleDefinitionFromOneModule(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $jsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/product_listing.json';
        $djsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/product_listing.djson';

        $this->fileDriver->method('isExists')->willReturnMap([
            [$jsonPath, true],
            [$djsonPath, false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($jsonPath)
            ->willReturn('{"title":"Products"}');

        $result = $this->loader->load('grid', 'product_listing');

        $this->assertCount(1, $result);
        $this->assertSame(['title' => 'Products'], $result[0]);
    }

    public function testLoadReturnsMultipleDefinitionsFromMultipleModules(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA', 'Vendor_ModuleB']);
        $this->dirReader->method('getModuleDir')->willReturnMap([
            ['', 'Vendor_ModuleA', '/app/code/Vendor/ModuleA'],
            ['', 'Vendor_ModuleB', '/app/code/Vendor/ModuleB'],
        ]);

        $jsonA = '/app/code/Vendor/ModuleA/view/adminhtml/grid/test.json';
        $jsonB = '/app/code/Vendor/ModuleB/view/adminhtml/grid/test.json';

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn (string $path): bool => $path === $jsonA || $path === $jsonB
        );
        $this->fileDriver->method('fileGetContents')->willReturnCallback(
            function (string $path) use ($jsonA, $jsonB): string {
                return match ($path) {
                    $jsonA => '{"source":"A"}',
                    $jsonB => '{"source":"B"}',
                    default => throw new \LogicException("Unexpected path: {$path}"),
                };
            }
        );

        $result = $this->loader->load('grid', 'test');

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['source']);
        $this->assertSame('B', $result[1]['source']);
    }

    // =========================================================================
    // Invalid JSON / non-array JSON
    // =========================================================================

    public function testLoadSkipsInvalidJson(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $jsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/broken.json';
        $djsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/broken.djson';

        $this->fileDriver->method('isExists')->willReturnMap([
            [$jsonPath, true],
            [$djsonPath, false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($jsonPath)
            ->willReturn('{invalid json content');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Failed to parse JSON definition file.', $this->anything());

        $result = $this->loader->load('grid', 'broken');

        $this->assertSame([], $result);
    }

    public function testLoadSkipsNonArrayJson(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $jsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/scalar.json';
        $djsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/scalar.djson';

        $this->fileDriver->method('isExists')->willReturnMap([
            [$jsonPath, true],
            [$djsonPath, false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($jsonPath)
            ->willReturn('"just a string"');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Failed to parse JSON definition file.', $this->anything());

        $result = $this->loader->load('grid', 'scalar');

        $this->assertSame([], $result);
    }

    // =========================================================================
    // Exception handling
    // =========================================================================

    public function testLoadHandlesFileSystemException(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $this->fileDriver->method('isExists')
            ->willThrowException(new FileSystemException(__('File not accessible')));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('FileSystemException while loading definition file.', $this->anything());

        $result = $this->loader->load('grid', 'error');

        $this->assertSame([], $result);
    }

    public function testLoadContinuesAfterExceptionInOneModule(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_Broken', 'Vendor_Working']);
        $this->dirReader->method('getModuleDir')->willReturnMap([
            ['', 'Vendor_Broken', '/app/code/Vendor/Broken'],
            ['', 'Vendor_Working', '/app/code/Vendor/Working'],
        ]);

        $brokenJson = '/app/code/Vendor/Broken/view/adminhtml/grid/test.json';
        $brokenDjson = '/app/code/Vendor/Broken/view/adminhtml/grid/test.djson';
        $workingJson = '/app/code/Vendor/Working/view/adminhtml/grid/test.json';
        $workingDjson = '/app/code/Vendor/Working/view/adminhtml/grid/test.djson';

        $this->fileDriver->method('isExists')->willReturnCallback(
            function (string $path) use ($brokenJson, $brokenDjson, $workingJson, $workingDjson): bool {
                if ($path === $brokenJson) {
                    throw new FileSystemException(__('Disk error'));
                }
                if ($path === $workingJson) {
                    return true;
                }
                return false;
            }
        );

        $this->fileDriver->method('fileGetContents')
            ->with($workingJson)
            ->willReturn('{"status":"ok"}');

        $result = $this->loader->load('grid', 'test');

        $this->assertCount(1, $result);
        $this->assertSame(['status' => 'ok'], $result[0]);
    }

    // =========================================================================
    // Module order preservation
    // =========================================================================

    public function testLoadPreservesModuleOrder(): void
    {
        $modules = ['Vendor_First', 'Vendor_Second', 'Vendor_Third'];
        $this->moduleList->method('getNames')->willReturn($modules);

        $this->dirReader->method('getModuleDir')->willReturnMap([
            ['', 'Vendor_First', '/app/code/Vendor/First'],
            ['', 'Vendor_Second', '/app/code/Vendor/Second'],
            ['', 'Vendor_Third', '/app/code/Vendor/Third'],
        ]);

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn (string $path): bool => str_ends_with($path, '.json')
        );

        $this->fileDriver->method('fileGetContents')->willReturnCallback(
            function (string $path): string {
                if (str_contains($path, 'First')) {
                    return '{"order":1}';
                }
                if (str_contains($path, 'Second')) {
                    return '{"order":2}';
                }
                return '{"order":3}';
            }
        );

        $result = $this->loader->load('grid', 'ordered');

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['order']);
        $this->assertSame(2, $result[1]['order']);
        $this->assertSame(3, $result[2]['order']);
    }

    // =========================================================================
    // Edge cases: empty objects and arrays
    // =========================================================================

    public function testLoadEmptyJsonObject(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $jsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/empty_obj.json';
        $djsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/empty_obj.djson';

        $this->fileDriver->method('isExists')->willReturnMap([
            [$jsonPath, true],
            [$djsonPath, false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($jsonPath)
            ->willReturn('{}');

        $result = $this->loader->load('grid', 'empty_obj');

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]);
    }

    public function testLoadEmptyJsonArray(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')->willReturn('/app/code/Vendor/ModuleA');

        $jsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/empty_arr.json';
        $djsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/grid/empty_arr.djson';

        $this->fileDriver->method('isExists')->willReturnMap([
            [$jsonPath, true],
            [$djsonPath, false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($jsonPath)
            ->willReturn('[]');

        $result = $this->loader->load('grid', 'empty_arr');

        // `[]` is a valid array, so is_array returns true — it should be included
        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]);
    }

    // =========================================================================
    // Path construction
    // =========================================================================

    public function testLoadPathConstruction(): void
    {
        $this->moduleList->method('getNames')->willReturn(['Vendor_ModuleA']);
        $this->dirReader->method('getModuleDir')
            ->with('', 'Vendor_ModuleA')
            ->willReturn('/app/code/Vendor/ModuleA');

        $expectedJsonPath = '/app/code/Vendor/ModuleA/view/adminhtml/form/product_edit.json';

        $checkedPaths = [];
        $this->fileDriver->method('isExists')->willReturnCallback(
            function (string $path) use (&$checkedPaths): bool {
                $checkedPaths[] = $path;
                return false;
            }
        );

        $this->loader->load('form', 'product_edit');

        $this->assertSame([$expectedJsonPath], $checkedPaths);
    }
}
