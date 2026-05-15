<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Authorization;

use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;

/**
 * Covers the default-deny ACL behaviour introduced by P0-3.
 *
 * Pre-P0-3 this returned `true` when a definition omitted `acl` — which
 * silently turned every misconfigured definition into a permissions hole.
 * It now falls back to Magento_Backend::admin so a non-admin still gets
 * rejected even without an explicit acl key.
 */
class DefinitionAccessControlTest extends TestCase
{
    public function testReturnsTrueWhenDefinitionAclIsAllowed(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->expects($this->once())
            ->method('isAllowed')
            ->with('Magento_Catalog::products')
            ->willReturn(true);

        $sut = new DefinitionAccessControl($auth);
        $this->assertTrue($sut->isAllowed(['acl' => 'Magento_Catalog::products']));
    }

    public function testReturnsFalseWhenAuthorizationDenies(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isAllowed')->willReturn(false);

        $sut = new DefinitionAccessControl($auth);
        $this->assertFalse($sut->isAllowed(['acl' => 'Magento_Catalog::products']));
    }

    public function testMissingAclFallsBackToBackendAdmin(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->expects($this->once())
            ->method('isAllowed')
            ->with('Magento_Backend::admin')
            ->willReturn(true);

        $sut = new DefinitionAccessControl($auth);
        $this->assertTrue($sut->isAllowed([]));
    }

    public function testEmptyStringAclFallsBackToBackendAdmin(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->expects($this->once())
            ->method('isAllowed')
            ->with('Magento_Backend::admin')
            ->willReturn(true);

        $sut = new DefinitionAccessControl($auth);
        $this->assertTrue($sut->isAllowed(['acl' => '']));
    }

    public function testFallbackResourceCanBeDeniedToNonAdmin(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->expects($this->once())
            ->method('isAllowed')
            ->with('Magento_Backend::admin')
            ->willReturn(false);

        $sut = new DefinitionAccessControl($auth);
        $this->assertFalse($sut->isAllowed([]));
    }
}
