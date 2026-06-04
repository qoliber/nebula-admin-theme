<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Test\Unit\Model\Menu;

use PHPUnit\Framework\TestCase;
use Qoliber\Nebula\Api\MenuIconRendererInterface;
use Qoliber\Nebula\Model\Menu\MenuIconRegistry;

class MenuIconRegistryTest extends TestCase
{
    public function testEmptyRegistryFallsThroughToEmptyArrayDefault(): void
    {
        $registry = new MenuIconRegistry();

        $this->assertSame('', $registry->render('Anything::id'));
        $this->assertSame([], $registry->resolve('Anything::id'));
        $this->assertFalse($registry->hasIcon('Anything::id'));
    }

    public function testRegisteredIconResolvesWithInheritedDefaultType(): void
    {
        $registry = new MenuIconRegistry(
            icons: [
                'Magento_Backend::dashboard' => ['class' => 'fa-solid fa-gauge-high'],
            ],
        );

        $spec = $registry->resolve('Magento_Backend::dashboard');
        $this->assertSame('fa-solid fa-gauge-high', $spec['class']);
        $this->assertSame('fontawesome', $spec['type']);
        $this->assertTrue($registry->hasIcon('Magento_Backend::dashboard'));
    }

    public function testIconWithExplicitTypeKeepsIt(): void
    {
        $registry = new MenuIconRegistry(
            icons: [
                'Acme::brand' => ['type' => 'image', 'src' => '/static/logo.svg'],
            ],
        );

        $spec = $registry->resolve('Acme::brand');
        $this->assertSame('image', $spec['type']);
        $this->assertSame('/static/logo.svg', $spec['src']);
    }

    public function testDefaultSpecIsReturnedForUnknownItem(): void
    {
        $registry = new MenuIconRegistry(
            icons:   ['Magento_Backend::dashboard' => ['class' => 'fa-solid fa-gauge-high']],
            default: ['class' => 'fa-solid fa-circle-dot'],
        );

        $spec = $registry->resolve('Unknown::id');
        $this->assertSame('fa-solid fa-circle-dot', $spec['class']);
        $this->assertSame('fontawesome', $spec['type']);
        $this->assertFalse($registry->hasIcon('Unknown::id'));
    }

    public function testCustomDefaultTypeAppliesToBothEntriesAndDefault(): void
    {
        $registry = new MenuIconRegistry(
            icons:   ['x' => ['src' => '/x.svg']],
            default: ['src' => '/default.svg'],
            defaultType: 'image',
        );

        $this->assertSame('image', $registry->resolve('x')['type']);
        $this->assertSame('image', $registry->resolve('unknown')['type']);
    }

    public function testRenderDispatchesToRegisteredRenderer(): void
    {
        $renderer = $this->createMock(MenuIconRendererInterface::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with(
                $this->callback(static fn (array $spec): bool
                    => $spec['type'] === 'fontawesome'
                       && $spec['class'] === 'fa-solid fa-gauge-high'),
                'w-5 opacity-70'
            )
            ->willReturn('<i class="fa-solid fa-gauge-high w-5 opacity-70"></i>');

        $registry = new MenuIconRegistry(
            icons:     ['Magento_Backend::dashboard' => ['class' => 'fa-solid fa-gauge-high']],
            renderers: ['fontawesome' => $renderer],
        );

        $this->assertSame(
            '<i class="fa-solid fa-gauge-high w-5 opacity-70"></i>',
            $registry->render('Magento_Backend::dashboard', 'w-5 opacity-70')
        );
    }

    public function testRenderFallsBackToDefaultSpecForUnknownItem(): void
    {
        $renderer = $this->createMock(MenuIconRendererInterface::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->callback(static fn (array $spec): bool
                => $spec['class'] === 'fa-solid fa-circle-dot'), '')
            ->willReturn('<i class="fa-solid fa-circle-dot"></i>');

        $registry = new MenuIconRegistry(
            icons:     ['known' => ['class' => 'fa-solid fa-x']],
            default:   ['class' => 'fa-solid fa-circle-dot'],
            renderers: ['fontawesome' => $renderer],
        );

        $this->assertSame(
            '<i class="fa-solid fa-circle-dot"></i>',
            $registry->render('unknown::id')
        );
    }

    public function testRenderReturnsEmptyStringForUnknownType(): void
    {
        // A misconfigured icon (type without a matching renderer) should soft-fail
        // — never crash the whole menu render.
        $registry = new MenuIconRegistry(
            icons: ['x' => ['type' => 'never-registered', 'class' => 'whatever']],
        );

        $this->assertSame('', $registry->render('x'));
    }

    public function testCustomRendererCanBeRegisteredAlongsideBuiltIns(): void
    {
        $imageRenderer = $this->createMock(MenuIconRendererInterface::class);
        $imageRenderer->method('render')->willReturn('<img/>');

        $faRenderer = $this->createMock(MenuIconRendererInterface::class);
        $faRenderer->method('render')->willReturn('<i></i>');

        $registry = new MenuIconRegistry(
            icons: [
                'a' => ['class' => 'fa'],
                'b' => ['type'  => 'image', 'src' => '/x.svg'],
            ],
            renderers: [
                'fontawesome' => $faRenderer,
                'image'       => $imageRenderer,
            ],
        );

        $this->assertSame('<i></i>', $registry->render('a'));
        $this->assertSame('<img/>', $registry->render('b'));
    }
}
