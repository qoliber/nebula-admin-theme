<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Definition;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\Cache\Type\NebulaDefinitions;
use Qoliber\NebulaComponent\Model\Definition\Loader;
use Qoliber\NebulaComponent\Model\Definition\Merger;
use Qoliber\NebulaComponent\Model\Definition\Resolver;

/**
 * Tests the cache/mode wiring added in Phase 3.
 */
class ResolverCacheTest extends TestCase
{
    private Loader&MockObject $loader;
    private Merger&MockObject $merger;
    private CacheInterface&MockObject $cache;
    private Json&MockObject $json;
    private SnippetResolverInterface&MockObject $snippetResolver;
    private State&MockObject $appState;
    private ModuleListInterface&MockObject $moduleList;
    private DirectoryList&MockObject $directoryList;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(Loader::class);
        $this->merger = $this->createMock(Merger::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->json = $this->createMock(Json::class);
        $this->snippetResolver = $this->createMock(SnippetResolverInterface::class);
        $this->appState = $this->createMock(State::class);
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->directoryList = $this->createMock(DirectoryList::class);

        $this->moduleList->method('getNames')->willReturn(['Vendor_A', 'Vendor_B']);
        $this->resetStaticManifest();
    }

    protected function tearDown(): void
    {
        $this->resetStaticManifest();
    }

    // =========================================================================
    // Developer mode
    // =========================================================================

    public function testDeveloperModeAlwaysBypassesCache(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEVELOPER);

        $this->loader->expects($this->once())->method('load')->willReturn([['a' => 1]]);
        $this->merger->expects($this->once())->method('merge')->willReturn(['a' => 1]);
        $this->cache->expects($this->never())->method('load');
        $this->cache->expects($this->never())->method('save');

        $result = $this->newResolver()->resolve('grid', 'product_listing');

        $this->assertSame(['a' => 1], $result);
    }

    // =========================================================================
    // Default mode — cache miss, then hit
    // =========================================================================

    public function testDefaultModeMissResolvesLiveAndSaves(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEFAULT);

        $resolver = $this->newResolver();
        $key = Resolver::CACHE_PREFIX . sha1('grid:product_listing:' . $resolver->modulesHash());

        $this->loader->expects($this->once())->method('load')->willReturn([['a' => 1]]);
        $this->merger->expects($this->once())->method('merge')->willReturn(['a' => 1]);

        $this->cache->expects($this->once())->method('load')->with($key)->willReturn(false);
        $this->json->expects($this->once())->method('serialize')->with(['a' => 1])->willReturn('{"a":1}');
        $this->cache->expects($this->once())
            ->method('save')
            ->with('{"a":1}', $key, [NebulaDefinitions::CACHE_TAG], null);

        $result = $resolver->resolve('grid', 'product_listing');
        $this->assertSame(['a' => 1], $result);
    }

    public function testDefaultModeHitDeserializesAndSkipsLoader(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEFAULT);

        $resolver = $this->newResolver();
        $key = Resolver::CACHE_PREFIX . sha1('form:customer_edit:' . $resolver->modulesHash());

        $this->cache->expects($this->once())->method('load')->with($key)->willReturn('{"title":"Cached"}');
        $this->json->expects($this->once())->method('unserialize')->with('{"title":"Cached"}')
            ->willReturn(['title' => 'Cached']);

        $this->loader->expects($this->never())->method('load');
        $this->merger->expects($this->never())->method('merge');
        $this->cache->expects($this->never())->method('save');

        $result = $resolver->resolve('form', 'customer_edit');
        $this->assertSame(['title' => 'Cached'], $result);
    }

    public function testCorruptedCachePayloadRecomputes(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEFAULT);

        $this->cache->method('load')->willReturn('not-json-at-all');
        $this->json->method('unserialize')->willThrowException(new \Exception('boom'));
        $this->loader->expects($this->once())->method('load')->willReturn([['ok' => true]]);
        $this->merger->expects($this->once())->method('merge')->willReturn(['ok' => true]);
        $this->json->method('serialize')->willReturn('{"ok":true}');
        $this->cache->expects($this->once())->method('save');

        $result = $this->newResolver()->resolve('grid', 'anything');
        $this->assertSame(['ok' => true], $result);
    }

    public function testModuleChangeInvalidatesCacheKey(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEFAULT);

        $resolverA = $this->newResolver();

        $moduleListB = $this->createMock(ModuleListInterface::class);
        $moduleListB->method('getNames')->willReturn(['Vendor_A', 'Vendor_B', 'Vendor_C']);
        $resolverB = new Resolver(
            $this->loader,
            $this->merger,
            $this->cache,
            $this->json,
            $this->snippetResolver,
            $this->appState,
            $moduleListB,
            $this->directoryList
        );

        $this->assertNotSame(
            $resolverA->modulesHash(),
            $resolverB->modulesHash(),
            'Modules hash must change when the enabled-module list changes.'
        );
    }

    // =========================================================================
    // Production mode — compiled manifest only
    // =========================================================================

    public function testProductionModeReadsCompiledManifest(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_PRODUCTION);

        $tmp = $this->makeTempRoot();
        file_put_contents(
            $tmp . '/generated/nebula/definitions.php',
            "<?php\nreturn " . var_export([
                'grid:product_listing' => ['title' => 'Products'],
            ], true) . ";\n"
        );
        $this->directoryList->method('getRoot')->willReturn($tmp);

        $this->loader->expects($this->never())->method('load');
        $this->cache->expects($this->never())->method('load');

        $result = $this->newResolver()->resolve('grid', 'product_listing');
        $this->assertSame(['title' => 'Products'], $result);
    }

    public function testProductionMissingDefinitionThrows(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_PRODUCTION);

        $tmp = $this->makeTempRoot();
        file_put_contents(
            $tmp . '/generated/nebula/definitions.php',
            "<?php\nreturn " . var_export(['grid:other' => ['x' => 1]], true) . ";\n"
        );
        $this->directoryList->method('getRoot')->willReturn($tmp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/grid:product_listing.*nebula:compile/s');

        $this->newResolver()->resolve('grid', 'product_listing');
    }

    public function testProductionMissingManifestThrows(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_PRODUCTION);

        $tmp = $this->makeTempRoot(writeManifest: false);
        $this->directoryList->method('getRoot')->willReturn($tmp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/compiled manifest is missing/');

        $this->newResolver()->resolve('grid', 'product_listing');
    }

    // =========================================================================
    // helpers
    // =========================================================================

    private function newResolver(): Resolver
    {
        return new Resolver(
            $this->loader,
            $this->merger,
            $this->cache,
            $this->json,
            $this->snippetResolver,
            $this->appState,
            $this->moduleList,
            $this->directoryList
        );
    }

    private function makeTempRoot(bool $writeManifest = true): string
    {
        $root = sys_get_temp_dir() . '/nebula-resolver-' . bin2hex(random_bytes(6));
        mkdir($root . '/generated/nebula', 0777, true);
        if ($writeManifest) {
            // caller writes the file
        }
        return $root;
    }

    private function resetStaticManifest(): void
    {
        $ref = new \ReflectionClass(Resolver::class);
        $prop = $ref->getProperty('compiledManifest');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
