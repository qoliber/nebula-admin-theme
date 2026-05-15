<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Field;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Field\EavFieldTypeMapper;

class EavFieldTypeMapperTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function frontendInputMapping(): array
    {
        return [
            'text' => ['text', 'text'],
            'textarea' => ['textarea', 'textarea'],
            'select' => ['select', 'select'],
            'multiselect' => ['multiselect', 'multiselect'],
            'boolean to toggle' => ['boolean', 'toggle'],
            'datetime to date' => ['datetime', 'date'],
            'price to number' => ['price', 'number'],
            'weight to number' => ['weight', 'number'],
            'media_image to image' => ['media_image', 'image'],
            'gallery skipped' => ['gallery', 'skip'],
            'hidden preserved' => ['hidden', 'hidden'],
            'unknown falls back to text' => ['something_custom', 'text'],
        ];
    }

    /**
     * @dataProvider frontendInputMapping
     */
    public function testMapReturnsExpectedType(string $frontendInput, string $expected): void
    {
        $mapper = new EavFieldTypeMapper();

        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);

        $this->assertSame($expected, $mapper->map($attribute));
    }

    public function testOverrideTypeBeatsFrontendInput(): void
    {
        $mapper = new EavFieldTypeMapper();

        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getFrontendInput')->willReturn('text');

        $this->assertSame('wysiwyg', $mapper->map($attribute, ['type' => 'wysiwyg']));
    }

    public function testGetOptionsReturnsSourceAllOptions(): void
    {
        $mapper = new EavFieldTypeMapper();

        $source = new class() {
            public function getAllOptions(): array
            {
                return [['value' => '1', 'label' => 'Yes']];
            }
        };

        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getSource')->willReturn($source);

        $this->assertSame([['value' => '1', 'label' => 'Yes']], $mapper->getOptions($attribute));
    }

    public function testGetOptionsReturnsEmptyWhenSourceThrows(): void
    {
        $mapper = new EavFieldTypeMapper();

        $source = new class() {
            public function getAllOptions(): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getSource')->willReturn($source);

        $this->assertSame([], $mapper->getOptions($attribute));
    }

    public function testGetOptionsReturnsEmptyWhenNoSource(): void
    {
        $mapper = new EavFieldTypeMapper();

        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getSource')->willReturn(null);

        $this->assertSame([], $mapper->getOptions($attribute));
    }
}
