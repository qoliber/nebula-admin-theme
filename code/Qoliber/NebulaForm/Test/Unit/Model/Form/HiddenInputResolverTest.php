<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Form;

use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Form\HiddenInputResolver;

class HiddenInputResolverTest extends TestCase
{
    public function testLiteralPrefixIsReturnedVerbatim(): void
    {
        $resolver = new HiddenInputResolver();
        $result = $resolver->resolve(
            ['settings' => ['hiddenInputs' => ['type' => 'literal:simple']]],
            $this->createMock(RequestInterface::class),
            []
        );

        $this->assertSame(['type' => 'simple'], $result);
    }

    public function testArrayPrefixExplodesEntityValue(): void
    {
        $resolver = new HiddenInputResolver();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $result = $resolver->resolve(
            ['settings' => ['hiddenInputs' => ['stores' => 'array:store_ids']]],
            $request,
            ['store_ids' => '1,2,3']
        );

        $this->assertSame(['stores' => ['1', '2', '3']], $result);
    }

    public function testPlainSourcePrefersRequestParam(): void
    {
        $resolver = new HiddenInputResolver();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name): mixed => $name === 'type' ? 'configurable' : null
        );

        $result = $resolver->resolve(
            ['settings' => ['hiddenInputs' => ['type' => 'type_id']]],
            $request,
            ['type_id' => 'simple']
        );

        $this->assertSame(['type' => 'configurable'], $result);
    }

    public function testPlainSourceFallsBackToEntityData(): void
    {
        $resolver = new HiddenInputResolver();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $result = $resolver->resolve(
            ['settings' => ['hiddenInputs' => ['type' => 'type_id']]],
            $request,
            ['type_id' => 'simple']
        );

        $this->assertSame(['type' => 'simple'], $result);
    }

    public function testResolveSaveUrlParamsHonorsEntityMap(): void
    {
        $resolver = new HiddenInputResolver();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn(null);

        $params = $resolver->resolveSaveUrlParams(
            ['settings' => ['hiddenParams' => ['type', 'set']]],
            $request,
            ['type_id' => 'simple', 'attribute_set_id' => '4']
        );

        $this->assertSame(['type' => 'simple', 'set' => '4'], $params);
    }
}
