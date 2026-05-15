<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Test\Unit\Plugin;

use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaForm\Block\Form;
use Qoliber\NebulaForm\Model\Form\RouteFormMap;
use Qoliber\NebulaForm\Plugin\InjectFormLayout;

class InjectFormLayoutTest extends TestCase
{
    private InjectFormLayout $plugin;

    protected function setUp(): void
    {
        $this->plugin = new InjectFormLayout(
            $this->createMock(Http::class),
            $this->createMock(RouteFormMap::class)
        );
    }

    /**
     * Invoke the private buildUpdateXml() via reflection — the generated XML
     * string is the load-bearing output of the plugin and worth asserting.
     *
     * @param array<int, string> $replaces
     */
    private function buildUpdateXml(string $formId, array $replaces): string
    {
        $method = new \ReflectionMethod(InjectFormLayout::class, 'buildUpdateXml');
        $method->setAccessible(true);

        return (string) $method->invoke($this->plugin, $formId, $replaces);
    }

    public function testSingleReplacesEmitsOneRemoveLine(): void
    {
        $xml = $this->buildUpdateXml('cms_block_edit', ['cms_block_form']);

        $this->assertSame(
            '<referenceBlock name="cms_block_form" remove="true"/>'
            . '<referenceContainer name="content">'
            . '<block class="' . Form::class . '" name="nebula.cms_block_edit">'
            . '<arguments>'
            . '<argument name="form_id" xsi:type="string">cms_block_edit</argument>'
            . '</arguments>'
            . '</block>'
            . '</referenceContainer>',
            $xml
        );
    }

    public function testMultipleReplacesEmitsOneRemoveLinePerEntry(): void
    {
        $xml = $this->buildUpdateXml(
            'admin_user_edit',
            ['adminhtml.user.edit.tabs', 'adminhtml.user.edit', 'adminhtml.user.roles.grid.js']
        );

        $this->assertSame(
            '<referenceBlock name="adminhtml.user.edit.tabs" remove="true"/>'
            . '<referenceBlock name="adminhtml.user.edit" remove="true"/>'
            . '<referenceBlock name="adminhtml.user.roles.grid.js" remove="true"/>'
            . '<referenceContainer name="content">'
            . '<block class="' . Form::class . '" name="nebula.admin_user_edit">'
            . '<arguments>'
            . '<argument name="form_id" xsi:type="string">admin_user_edit</argument>'
            . '</arguments>'
            . '</block>'
            . '</referenceContainer>',
            $xml
        );

        $this->assertSame(3, substr_count($xml, 'remove="true"'));
    }

    public function testNoReplacesEmitsNoRemoveLines(): void
    {
        $xml = $this->buildUpdateXml('store_group_edit', []);

        $this->assertStringNotContainsString('referenceBlock', $xml);
        $this->assertSame(
            '<referenceContainer name="content">'
            . '<block class="' . Form::class . '" name="nebula.store_group_edit">'
            . '<arguments>'
            . '<argument name="form_id" xsi:type="string">store_group_edit</argument>'
            . '</arguments>'
            . '</block>'
            . '</referenceContainer>',
            $xml
        );
    }

    public function testReplacesAndFormIdAreXmlEscaped(): void
    {
        $xml = $this->buildUpdateXml('form&id', ['block"name']);

        $this->assertStringContainsString('<referenceBlock name="block&quot;name" remove="true"/>', $xml);
        $this->assertStringContainsString('name="nebula.form&amp;id"', $xml);
        $this->assertStringContainsString('xsi:type="string">form&amp;id</argument>', $xml);
    }
}
