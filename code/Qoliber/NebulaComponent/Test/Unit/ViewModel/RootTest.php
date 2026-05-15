<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\ViewModel\Root;
use Qoliber\NebulaSkin\Model\SkinLoader;

class RootTest extends TestCase
{
    public function testGetInlineSkinCssDelegatesToSkinLoader(): void
    {
        $skinLoader = $this->createMock(SkinLoader::class);
        $skinLoader->expects($this->once())
            ->method('getInlineCss')
            ->willReturn(':root { --x: 1; }');

        $viewModel = new Root($skinLoader);

        $this->assertSame(':root { --x: 1; }', $viewModel->getInlineSkinCss());
    }

    public function testImplementsArgumentInterface(): void
    {
        $viewModel = new Root($this->createMock(SkinLoader::class));

        $this->assertInstanceOf(\Magento\Framework\View\Element\Block\ArgumentInterface::class, $viewModel);
    }
}
