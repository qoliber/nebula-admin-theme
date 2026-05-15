<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Test\Unit\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Type as EntityType;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaGrid\Model\AddButtonUrlBuilder;

class AddButtonUrlBuilderTest extends TestCase
{
    private function makeBuilder(int $setId): AddButtonUrlBuilder
    {
        $entityType = $this->createMock(EntityType::class);
        $entityType->method('getDefaultAttributeSetId')->willReturn($setId);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityType')
            ->with('catalog_product')
            ->willReturn($entityType);

        return new AddButtonUrlBuilder($eavConfig);
    }

    public function testReplacesPlaceholderWithDefaultSetId(): void
    {
        $builder = $this->makeBuilder(7);

        $definition = [
            'settings' => [
                'addButton' => [
                    'options' => [
                        ['label' => 'Simple', 'url' => 'catalog/product/new/set/{{defaultAttributeSet}}/type/simple'],
                        ['label' => 'Virtual', 'url' => 'catalog/product/new/set/{{defaultAttributeSet}}/type/virtual'],
                    ],
                ],
            ],
        ];

        $result = $builder->resolve($definition);

        $this->assertSame(
            'catalog/product/new/set/7/type/simple',
            $result['settings']['addButton']['options'][0]['url']
        );
        $this->assertSame(
            'catalog/product/new/set/7/type/virtual',
            $result['settings']['addButton']['options'][1]['url']
        );
    }

    public function testNoOpWhenNoOptions(): void
    {
        $builder = $this->makeBuilder(4);

        $definition = ['settings' => ['addButton' => ['label' => 'Add']]];
        $result = $builder->resolve($definition);

        $this->assertSame($definition, $result);
    }

    public function testNoOpWhenNoAddButton(): void
    {
        $builder = $this->makeBuilder(4);

        $definition = ['settings' => ['pageSize' => 20]];
        $result = $builder->resolve($definition);

        $this->assertSame($definition, $result);
    }
}
