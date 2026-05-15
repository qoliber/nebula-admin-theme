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

class ResolverTest extends TestCase
{
    private Resolver $resolver;
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

        // Default to developer mode in base setUp so baseline behavior is cache-bypass.
        $this->appState->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->moduleList->method('getNames')->willReturn(['Vendor_A', 'Vendor_B']);

        $this->resolver = new Resolver(
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

    // =========================================================================
    // Developer mode — cache bypass (original contract preserved)
    // =========================================================================

    public function testResolveLoadsAndMerges(): void
    {
        $definitions = [['title' => 'Test']];
        $merged = ['title' => 'Test'];

        $this->loader->method('load')->willReturn($definitions);
        $this->merger->method('merge')->willReturn($merged);
        $this->cache->expects($this->never())->method('load');
        $this->cache->expects($this->never())->method('save');

        $result = $this->resolver->resolve('grid', 'product_listing');

        $this->assertSame($merged, $result);
    }

    public function testResolveCallsSnippetResolverWhenLayoutExists(): void
    {
        $merged = ['layout' => [['id' => 'row1', 'type' => 'row']]];
        $resolved = ['layout' => [['id' => 'row1', 'type' => 'row', 'resolved' => true]]];

        $this->loader->method('load')->willReturn([[]]);
        $this->merger->method('merge')->willReturn($merged);
        $this->snippetResolver->expects($this->once())
            ->method('resolveLayout')
            ->with($merged)
            ->willReturn($resolved);

        $result = $this->resolver->resolve('form', 'product_edit');

        $this->assertSame($resolved, $result);
    }

    public function testResolveSkipsSnippetResolverWhenNoLayout(): void
    {
        $merged = ['columns' => ['name' => ['label' => 'Name']]];

        $this->loader->method('load')->willReturn([[]]);
        $this->merger->method('merge')->willReturn($merged);
        $this->snippetResolver->expects($this->never())->method('resolveLayout');

        $result = $this->resolver->resolve('grid', 'product_listing');

        $this->assertSame($merged, $result);
    }

    public function testResolveReturnsEmptyForNoDefinitions(): void
    {
        $this->loader->method('load')->willReturn([]);
        $this->merger->method('merge')->with([])->willReturn([]);

        $result = $this->resolver->resolve('grid', 'nonexistent');

        $this->assertSame([], $result);
    }

    public function testResolvePassesCorrectTypeAndIdToLoader(): void
    {
        $this->loader->expects($this->once())
            ->method('load')
            ->with('form', 'customer_edit')
            ->willReturn([]);
        $this->merger->method('merge')->willReturn([]);

        $this->resolver->resolve('form', 'customer_edit');
    }

    public function testResolveMergesMultipleDefinitions(): void
    {
        $definitions = [
            ['title' => 'Base', 'columns' => ['a' => ['label' => 'A']]],
            ['title' => 'Override', 'columns' => ['b' => ['label' => 'B']]],
        ];
        $merged = ['title' => 'Override', 'columns' => ['a' => ['label' => 'A'], 'b' => ['label' => 'B']]];

        $this->loader->method('load')->willReturn($definitions);
        $this->merger->expects($this->once())
            ->method('merge')
            ->with($definitions)
            ->willReturn($merged);

        $result = $this->resolver->resolve('grid', 'product_listing');

        $this->assertSame($merged, $result);
    }

    public function testResolveWithEmptyLayout(): void
    {
        $merged = ['layout' => []];

        $this->loader->method('load')->willReturn([[]]);
        $this->merger->method('merge')->willReturn($merged);

        $this->snippetResolver->expects($this->once())
            ->method('resolveLayout')
            ->with($merged)
            ->willReturn($merged);

        $result = $this->resolver->resolve('form', 'product_edit');

        $this->assertSame([], $result['layout']);
    }

    public function testResolvePreservesNonLayoutKeys(): void
    {
        $merged = [
            'settings' => ['pageSize' => 20, 'title' => 'Products'],
            'columns' => ['name' => ['label' => 'Name']],
            'filters' => ['status' => ['type' => 'select']],
        ];

        $this->loader->method('load')->willReturn([[]]);
        $this->merger->method('merge')->willReturn($merged);
        $this->snippetResolver->expects($this->never())->method('resolveLayout');

        $result = $this->resolver->resolve('grid', 'product_listing');

        $this->assertSame(20, $result['settings']['pageSize']);
        $this->assertSame('Products', $result['settings']['title']);
        $this->assertSame('Name', $result['columns']['name']['label']);
        $this->assertSame('select', $result['filters']['status']['type']);
    }

    // =========================================================================
    // clearCache()
    // =========================================================================

    public function testClearCacheRemovesSpecificKey(): void
    {
        $expectedHashKey = Resolver::CACHE_PREFIX
            . sha1('grid:product_listing:' . sha1("Vendor_A\nVendor_B"));

        $this->cache->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function (string $key) use ($expectedHashKey): bool {
                $this->assertContains($key, [
                    $expectedHashKey,
                    Resolver::CACHE_PREFIX . 'grid_product_listing',
                ]);
                return true;
            });

        $this->resolver->clearCache('grid', 'product_listing');
    }

    public function testClearCacheCleansByTag(): void
    {
        $this->cache->expects($this->once())
            ->method('clean')
            ->with([NebulaDefinitions::CACHE_TAG]);

        $this->resolver->clearCache();
    }
}
