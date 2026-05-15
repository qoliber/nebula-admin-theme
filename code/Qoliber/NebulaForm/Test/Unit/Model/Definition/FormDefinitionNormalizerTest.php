<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Model\Definition;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Model\Definition\FormDefinitionNormalizer;

class FormDefinitionNormalizerTest extends TestCase
{
    private FormDefinitionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new FormDefinitionNormalizer();
    }

    public function testStripsBridgeMetadataKeys(): void
    {
        $definition = [
            '_warning' => 'auto-generated',
            '_generated' => true,
            '_meta' => ['source' => 'bridge'],
            'id' => 'my_form',
            'settings' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayNotHasKey('_warning', $result);
        $this->assertArrayNotHasKey('_generated', $result);
        $this->assertArrayNotHasKey('_meta', $result);
    }

    public function testPreservesOtherKeys(): void
    {
        $definition = [
            'id' => 'my_form',
            'settings' => ['saveUrl' => 'admin/save'],
            'fields' => ['name' => ['type' => 'text']],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('my_form', $result['id']);
        $this->assertArrayHasKey('settings', $result);
        $this->assertSame(['saveUrl' => 'admin/save'], $result['settings']);
        $this->assertArrayHasKey('fields', $result);
        $this->assertSame(['name' => ['type' => 'text']], $result['fields']);
    }

    public function testNoOpOnCleanDefinition(): void
    {
        $definition = [
            'id' => 'clean_form',
            'settings' => [],
            'fieldsets' => [],
        ];

        $result = $this->normalizer->normalize($definition);

        $this->assertSame($definition, $result);
    }
}
