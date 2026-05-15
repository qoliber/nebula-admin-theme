<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Plugin\Definition;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\Condition\ConditionEvaluator;
use Qoliber\NebulaComponent\Plugin\Definition\ConditionalBranchResolver;

class ConditionalBranchResolverTest extends TestCase
{
    private ConditionalBranchResolver $plugin;
    private SnippetResolverInterface&MockObject $subject;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();

        $this->plugin = new ConditionalBranchResolver(new ConditionEvaluator($scopeConfig, $session));
        $this->subject = $this->createMock(SnippetResolverInterface::class);
    }

    public function testPassesThroughWithoutLayout(): void
    {
        $definition = ['columns' => ['sku' => ['label' => 'SKU']]];

        self::assertSame(
            $definition,
            $this->plugin->afterResolveLayout($this->subject, $definition)
        );
    }

    public function testResolvesThenBranch(): void
    {
        $definition = [
            'settings' => ['enabled' => true],
            'layout' => [
                ['type' => 'row', 'children' => [
                    [
                        'if' => ['field' => 'settings.enabled', 'value' => true],
                        'then' => [['type' => 'section', 'id' => 'live']],
                        'else' => [['type' => 'section', 'id' => 'hidden']],
                    ],
                ]],
            ],
        ];

        $result = $this->plugin->afterResolveLayout($this->subject, $definition);

        self::assertSame('live', $result['layout'][0]['children'][0]['id']);
    }

    public function testResolvesElseBranch(): void
    {
        $definition = [
            'settings' => ['enabled' => false],
            'layout' => [
                [
                    'if' => ['field' => 'settings.enabled', 'value' => true],
                    'then' => [['type' => 'section', 'id' => 'live']],
                    'else' => [['type' => 'section', 'id' => 'hidden']],
                ],
            ],
        ];

        $result = $this->plugin->afterResolveLayout($this->subject, $definition);

        self::assertSame('hidden', $result['layout'][0]['id']);
    }

    public function testMissingBranchCollapsesToEmpty(): void
    {
        $definition = [
            'settings' => ['enabled' => false],
            'layout' => [
                [
                    'if' => true,
                    'then' => [['type' => 'section', 'id' => 'keep']],
                ],
                [
                    'if' => false,
                    'then' => [['type' => 'section', 'id' => 'drop']],
                ],
            ],
        ];

        $result = $this->plugin->afterResolveLayout($this->subject, $definition);
        $layout = array_values($result['layout']);

        self::assertCount(1, $layout);
        self::assertSame('keep', $layout[0]['id']);
    }

    public function testExplicitContextOverridesDefinition(): void
    {
        $definition = [
            'context' => ['role' => 'admin'],
            'layout' => [
                [
                    'if' => ['field' => 'role', 'value' => 'admin'],
                    'then' => [['type' => 'section', 'id' => 'admin-only']],
                ],
            ],
        ];

        $result = $this->plugin->afterResolveLayout($this->subject, $definition);
        $layout = array_values($result['layout']);

        self::assertSame('admin-only', $layout[0]['id']);
    }
}
