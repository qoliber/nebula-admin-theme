<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Test\Unit\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\Nebula\Plugin\UseTabbedCmsBlockEditForm;
use Qoliber\NebulaForm\Block\Form;

class UseTabbedCmsBlockEditFormTest extends TestCase
{
    private MockObject $scopeConfig;

    private UseTabbedCmsBlockEditForm $plugin;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->plugin = new UseTabbedCmsBlockEditForm($this->scopeConfig);
    }

    public function testLeavesOtherFormIdentifiersUnchanged(): void
    {
        $this->scopeConfig->expects($this->never())->method('isSetFlag');

        $result = $this->plugin->afterGetFormId(
            $this->createMock(Form::class),
            'cms_page_edit'
        );

        $this->assertSame('cms_page_edit', $result);
    }

    public function testKeepsDefaultCmsBlockFormWhenFeatureIsDisabled(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('nebula/theme/enable_cms_block_edit_tabs')
            ->willReturn(false);

        $result = $this->plugin->afterGetFormId(
            $this->createMock(Form::class),
            'cms_block_edit'
        );

        $this->assertSame('cms_block_edit', $result);
    }

    public function testSwapsCmsBlockFormToTabbedVariantWhenFeatureIsEnabled(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('nebula/theme/enable_cms_block_edit_tabs')
            ->willReturn(true);

        $result = $this->plugin->afterGetFormId(
            $this->createMock(Form::class),
            'cms_block_edit'
        );

        $this->assertSame('cms_block_edit_tabs', $result);
    }
}
