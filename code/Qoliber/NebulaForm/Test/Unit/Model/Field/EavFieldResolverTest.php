<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Field;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Eav\ProductTypeResolver;
use Qoliber\NebulaForm\Model\Field\EavFieldResolver;

class EavFieldResolverTest extends TestCase
{
    public function testGetFieldOverridesReturnsEmptyArrayWhenMissing(): void
    {
        $resolver = new EavFieldResolver($this->createMock(ProductTypeResolver::class));

        $this->assertSame([], $resolver->getFieldOverrides([], 'sku'));
    }

    public function testGetFieldOverridesReturnsDefinitionSlice(): void
    {
        $resolver = new EavFieldResolver($this->createMock(ProductTypeResolver::class));

        $overrides = ['type' => 'hidden'];
        $result = $resolver->getFieldOverrides(
            ['overrides' => ['fields' => ['sku' => $overrides]]],
            'sku'
        );

        $this->assertSame($overrides, $result);
    }

    public function testIsApplicableDefaultsTrueWhenAttributeHasNoApplyTo(): void
    {
        $resolver = new EavFieldResolver($this->createMock(ProductTypeResolver::class));
        $attribute = $this->mockAttributeApplyTo(null);

        $this->assertTrue($resolver->isApplicable($attribute, []));
    }

    public function testIsApplicableMatchesCommaList(): void
    {
        $resolver = new EavFieldResolver($this->createMock(ProductTypeResolver::class));
        $attribute = $this->mockAttributeApplyTo('simple,configurable');

        $this->assertTrue($resolver->isApplicable($attribute, ['type_id' => 'configurable']));
        $this->assertFalse($resolver->isApplicable($attribute, ['type_id' => 'bundle']));
    }

    public function testIsApplicableAcceptsArrayApplyTo(): void
    {
        $resolver = new EavFieldResolver($this->createMock(ProductTypeResolver::class));
        $attribute = $this->mockAttributeApplyTo(['simple', 'virtual']);

        $this->assertTrue($resolver->isApplicable($attribute, ['type_id' => 'virtual']));
    }

    public function testIsCompositeDelegatesToProductTypeResolver(): void
    {
        $productTypeResolver = $this->createMock(ProductTypeResolver::class);
        $productTypeResolver->expects($this->once())->method('isComposite')->with('bundle')->willReturn(true);

        $resolver = new EavFieldResolver($productTypeResolver);

        $this->assertTrue($resolver->isComposite('bundle'));
    }

    public function testTracksQtyDelegatesToProductTypeResolver(): void
    {
        $productTypeResolver = $this->createMock(ProductTypeResolver::class);
        $productTypeResolver->expects($this->once())->method('tracksQty')->with('simple')->willReturn(false);

        $resolver = new EavFieldResolver($productTypeResolver);

        $this->assertFalse($resolver->tracksQty('simple'));
    }

    private function mockAttributeApplyTo(mixed $applyTo): AbstractAttribute
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getData')->with('apply_to')->willReturn($applyTo);

        return $attribute;
    }
}
