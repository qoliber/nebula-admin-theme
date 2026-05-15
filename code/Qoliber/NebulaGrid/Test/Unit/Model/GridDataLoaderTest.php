<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\DataProviderResolver;
use Qoliber\NebulaGrid\Model\GridDataLoader;

class GridDataLoaderTest extends TestCase
{
    public function testReturnsEmptyShapeWhenNoProviderConfigured(): void
    {
        $resolver = $this->createMock(DataProviderResolver::class);
        $resolver->expects($this->never())->method('fetch');

        $loader = $this->makeLoader($resolver);
        $result = $loader->load([], $this->createMock(RequestInterface::class), 1, 20, '', 'asc');

        $this->assertSame(['items' => [], 'totalCount' => 0], $result);
    }

    public function testForwardsRequestParamsToProviderAlias(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'filters' ? ['sku' => 'abc'] : ($key === 'search' ? 'laptop' : $default)
        );

        $resolver = $this->createMock(DataProviderResolver::class);
        $resolver->expects($this->once())
            ->method('fetch')
            ->with(
                'product.grid',
                $this->callback(function (array $config): bool {
                    return ($config['columns'] ?? null) === ['name' => []];
                }),
                $this->callback(function (array $params): bool {
                    return $params['page'] === 2
                        && $params['pageSize'] === 25
                        && $params['sort'] === 'created_at'
                        && $params['sortDir'] === 'desc'
                        && $params['filters'] === ['sku' => 'abc']
                        && $params['search'] === 'laptop';
                })
            )
            ->willReturn(['items' => [['id' => 1]], 'totalCount' => 7]);

        $loader = $this->makeLoader($resolver);
        $result = $loader->load(
            [
                'dataSource' => ['provider' => 'product.grid', 'config' => []],
                'columns' => ['name' => []],
            ],
            $request,
            2,
            25,
            'created_at',
            'desc'
        );

        $this->assertSame(7, $result['totalCount']);
        $this->assertSame([['id' => 1]], $result['items']);
    }

    private function makeLoader(DataProviderResolver $resolver): GridDataLoader
    {
        // The contractor added an event-manager dependency; support either
        // 1-arg or 2-arg constructor by reflection so this test survives both.
        $eventManager = $this->createMock(EventManagerInterface::class);
        $reflection = new \ReflectionClass(GridDataLoader::class);
        $params = $reflection->getConstructor()?->getParameters() ?? [];

        if (count($params) >= 2) {
            return new GridDataLoader($resolver, $eventManager);
        }

        // @phpstan-ignore-next-line — tolerate legacy single-arg constructor.
        return new GridDataLoader($resolver);
    }
}
