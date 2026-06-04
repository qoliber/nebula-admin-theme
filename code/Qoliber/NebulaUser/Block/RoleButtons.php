<?php

declare(strict_types=1);

namespace Qoliber\NebulaUser\Block;

/**
 * Neutered user-role buttons block for the Nebula admin.
 *
 * Stock {@see \Magento\User\Block\Buttons::_prepareLayout()} adds Back / Reset /
 * Delete buttons to `page.actions.toolbar`. Nebula's flattened `admin-1column`
 * role-edit layout does not provide that toolbar, so {@see \Magento\Backend\Block\Template::getToolbar()}
 * returns false and the stock block fatals with "addChild() on false".
 *
 * The EditRole controller still hard-references this block
 * (`$layout->getBlock('adminhtml.user.role.buttons')->setRoleId(...)`), so it
 * cannot be removed. We keep it alive but skip the toolbar wiring — Nebula's
 * form-shell already renders the Save / Delete / Back action bar.
 */
class RoleButtons extends \Magento\User\Block\Buttons
{
    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        return $this;
    }
}
