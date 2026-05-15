<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Condition;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Exception\MalformedConditionException;
use Qoliber\NebulaComponent\Model\Condition\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Session&MockObject $authSession;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->authSession = $this->createMock(Session::class);
        $this->evaluator = new ConditionEvaluator($this->scopeConfig, $this->authSession);
    }

    // ---------------- field ----------------

    public function testFieldEq(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'status', 'op' => 'eq', 'value' => 'enabled'],
            ['status' => 'enabled']
        ));
    }

    public function testFieldDefaultOpIsEq(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'status', 'value' => 'enabled'],
            ['status' => 'enabled']
        ));
    }

    public function testFieldNeq(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'status', 'op' => 'neq', 'value' => 'disabled'],
            ['status' => 'enabled']
        ));
    }

    public function testFieldGt(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'price', 'op' => 'gt', 'value' => 10],
            ['price' => 25]
        ));
        self::assertFalse($this->evaluator->evaluate(
            ['field' => 'price', 'op' => 'gt', 'value' => 25],
            ['price' => 10]
        ));
    }

    public function testFieldGteLteLt(): void
    {
        self::assertTrue($this->evaluator->evaluate(['field' => 'n', 'op' => 'gte', 'value' => 10], ['n' => 10]));
        self::assertTrue($this->evaluator->evaluate(['field' => 'n', 'op' => 'lte', 'value' => 10], ['n' => 10]));
        self::assertTrue($this->evaluator->evaluate(['field' => 'n', 'op' => 'lt', 'value' => 11], ['n' => 10]));
    }

    public function testFieldInAndNin(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'country', 'op' => 'in', 'value' => ['US', 'CA']],
            ['country' => 'US']
        ));
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'country', 'op' => 'nin', 'value' => ['US', 'CA']],
            ['country' => 'PL']
        ));
    }

    public function testFieldContains(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'sku', 'op' => 'contains', 'value' => 'WS'],
            ['sku' => 'MSH-WS-01']
        ));
    }

    public function testFieldEmptyAndNotEmpty(): void
    {
        self::assertTrue($this->evaluator->evaluate(['field' => 'name', 'op' => 'empty'], ['name' => null]));
        self::assertTrue($this->evaluator->evaluate(['field' => 'name', 'op' => 'empty'], ['name' => '']));
        self::assertTrue($this->evaluator->evaluate(['field' => 'name', 'op' => 'empty'], []));
        self::assertTrue($this->evaluator->evaluate(['field' => 'name', 'op' => 'notEmpty'], ['name' => 'x']));
    }

    public function testFieldNestedPath(): void
    {
        self::assertTrue($this->evaluator->evaluate(
            ['field' => 'address.country', 'op' => 'eq', 'value' => 'PL'],
            ['address' => ['country' => 'PL']]
        ));
    }

    public function testUnknownOperatorThrows(): void
    {
        $this->expectException(MalformedConditionException::class);
        $this->evaluator->evaluate(['field' => 'x', 'op' => 'matches', 'value' => 'y'], ['x' => 'y']);
    }

    // ---------------- role ----------------

    public function testRoleMatchesCurrentAdmin(): void
    {
        $role = new class() {
            public function getRoleName(): string
            {
                return 'Administrators';
            }
        };
        $user = new class($role) {
            public function __construct(private readonly object $role)
            {
            }

            public function getRole(): object
            {
                return $this->role;
            }
        };

        $this->authSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();
        $this->authSession->method('getUser')->willReturn($user);

        $evaluator = new ConditionEvaluator($this->scopeConfig, $this->authSession);

        self::assertTrue($evaluator->evaluate(['role' => 'Administrators']));
        self::assertTrue($evaluator->evaluate(['role' => 'administrators'])); // case-insensitive
        self::assertFalse($evaluator->evaluate(['role' => 'Editors']));
    }

    public function testRoleReturnsFalseWhenNoUser(): void
    {
        $this->authSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();
        $this->authSession->method('getUser')->willReturn(null);

        $evaluator = new ConditionEvaluator($this->scopeConfig, $this->authSession);

        self::assertFalse($evaluator->evaluate(['role' => 'Administrators']));
    }

    // ---------------- config ----------------

    public function testConfigEq(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('web/secure/use_in_adminhtml', 'store', null)
            ->willReturn('1');

        self::assertTrue($this->evaluator->evaluate([
            'config' => 'web/secure/use_in_adminhtml',
            'op' => 'eq',
            'value' => '1',
        ]));
    }

    // ---------------- composites ----------------

    public function testAllShortCircuits(): void
    {
        self::assertTrue($this->evaluator->evaluate([
            'all' => [
                ['field' => 'status', 'value' => 'enabled'],
                ['field' => 'active', 'value' => true],
            ],
        ], ['status' => 'enabled', 'active' => true]));
    }

    public function testAllFailsOnFirstFalsy(): void
    {
        self::assertFalse($this->evaluator->evaluate([
            'all' => [
                ['field' => 'status', 'value' => 'disabled'],
                ['field' => 'active', 'value' => true],
            ],
        ], ['status' => 'enabled', 'active' => true]));
    }

    public function testAnyMatchesFirstTruth(): void
    {
        self::assertTrue($this->evaluator->evaluate([
            'any' => [
                ['field' => 'status', 'value' => 'disabled'],
                ['field' => 'status', 'value' => 'enabled'],
            ],
        ], ['status' => 'enabled']));
    }

    public function testNotInvertsBranch(): void
    {
        self::assertTrue($this->evaluator->evaluate([
            'not' => ['field' => 'status', 'value' => 'disabled'],
        ], ['status' => 'enabled']));
    }

    public function testBooleanLiteralPassesThrough(): void
    {
        self::assertTrue($this->evaluator->evaluate(true));
        self::assertFalse($this->evaluator->evaluate(false));
    }

    public function testCompositeWithBoolBranch(): void
    {
        self::assertTrue($this->evaluator->evaluate([
            'all' => [
                true,
                ['field' => 'x', 'value' => 1],
            ],
        ], ['x' => 1]));
    }

    public function testUnknownShapeThrows(): void
    {
        $this->expectException(MalformedConditionException::class);
        $this->evaluator->evaluate(['weird' => 'thing']);
    }
}
