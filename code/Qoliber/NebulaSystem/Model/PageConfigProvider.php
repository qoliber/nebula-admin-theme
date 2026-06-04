<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Model;

use InvalidArgumentException;

class PageConfigProvider
{
    /**
     * @var array<string, array{title: string, menu_id: string, resource: string, group: string, original_route: string, summary: string, next_steps: list<string>}>
     */
    private const PAGES = [
        'import' => [
            'title' => 'Import',
            'menu_id' => 'Qoliber_NebulaSystem::system_convert_import',
            'resource' => 'Magento_ImportExport::import',
            'group' => 'Data Transfer',
            'original_route' => 'adminhtml/import',
            'summary' => 'Nebula does not yet provide a native Import page. This placeholder restores System menu parity while the real page is planned.',
            'next_steps' => [
                'Decide whether the legacy Import page can be wrapped inside Nebula chrome.',
                'If needed, generate a Nebula draft with the Nebula UI migration tooling.',
                'Replace the placeholder only after route behavior and ACL are stable.',
            ],
        ],
        'export' => [
            'title' => 'Export',
            'menu_id' => 'Qoliber_NebulaSystem::system_convert_export',
            'resource' => 'Magento_ImportExport::export',
            'group' => 'Data Transfer',
            'original_route' => 'adminhtml/export',
            'summary' => 'Nebula does not yet provide a native Export page. This placeholder keeps the menu complete while the real experience is designed.',
            'next_steps' => [
                'Evaluate a wrapped core Export page versus a native Nebula form.',
                'Use the Nebula UI migration tooling if a native migration is chosen.',
                'Swap route ownership only when the export flow is complete.',
            ],
        ],
        'history' => [
            'title' => 'Import History',
            'menu_id' => 'Qoliber_NebulaSystem::system_convert_history',
            'resource' => 'Magento_ImportExport::history',
            'group' => 'Data Transfer',
            'original_route' => 'adminhtml/history',
            'summary' => 'Nebula does not yet provide a native Import History page. This placeholder keeps the Data Transfer group visible for MVP parity.',
            'next_steps' => [
                'Assess whether the Magento page can be wrapped cleanly inside Nebula.',
                'If a native version is preferred, generate a draft migration pack first.',
                'Migrate to Nebula ownership only after filters and listing behavior match expectations.',
            ],
        ],
        'taxrates' => [
            'title' => 'Import/Export Tax Rates',
            'menu_id' => 'Qoliber_NebulaSystem::system_convert_tax',
            'resource' => 'Magento_TaxImportExport::import_export',
            'group' => 'Data Transfer',
            'original_route' => 'tax/rate/importExport',
            'summary' => 'Nebula does not yet provide a native tax rates import and export page. This placeholder restores visibility without promising full workflow support yet.',
            'next_steps' => [
                'Keep this item aligned with the broader Data Transfer migration strategy.',
                'Evaluate whether the legacy page can be wrapped before any native rewrite.',
                'Use transformer output only if a true Nebula page is worth the investment.',
            ],
        ],
        'backups' => [
            'title' => 'Backups',
            'menu_id' => 'Qoliber_NebulaSystem::system_tools_backup',
            'resource' => 'Magento_Backup::backup',
            'group' => 'Tools',
            'original_route' => 'backup/index',
            'summary' => 'Nebula does not yet provide a native Backups page. This placeholder restores the Tools group while the safest long term presentation is decided.',
            'next_steps' => [
                'Review whether the core page can run safely inside Nebula chrome.',
                'Validate permissions and destructive actions before exposing a wrapped page.',
                'Only plan a native Nebula replacement if the wrapped experience is not acceptable.',
            ],
        ],
        'locks' => [
            'title' => 'Locked Users',
            'menu_id' => 'Qoliber_NebulaSystem::system_acl_locks',
            'resource' => 'Magento_User::locks',
            'group' => 'Permissions',
            'original_route' => 'adminhtml/locks',
            'summary' => 'Nebula does not yet provide a native Locked Users page. This placeholder restores the missing permissions link for MVP parity.',
            'next_steps' => [
                'Check whether the Magento page can be wrapped cleanly in Nebula.',
                'If needed, build a small Nebula listing for locked admin users.',
                'Keep ACL behavior identical to the original page.',
            ],
        ],
        'notifications' => [
            'title' => 'Notifications',
            'menu_id' => 'Qoliber_NebulaSystem::system_adminnotification',
            'resource' => 'Magento_AdminNotification::adminnotification',
            'group' => 'Other Settings',
            'original_route' => 'adminhtml/notification',
            'summary' => 'Nebula does not yet provide a native Notifications page. This placeholder restores navigation while the real page strategy is defined.',
            'next_steps' => [
                'Evaluate a wrapped Magento page inside Nebula chrome.',
                'If a native page is needed, map notification listing behavior before building it.',
                'Validate actions and active menu state when the placeholder is replaced.',
            ],
        ],
        'variables' => [
            'title' => 'Custom Variables',
            'menu_id' => 'Qoliber_NebulaSystem::system_variable',
            'resource' => 'Magento_Variable::variable',
            'group' => 'Other Settings',
            'original_route' => 'adminhtml/system_variable',
            'summary' => 'Nebula does not yet provide a native Custom Variables page. This placeholder keeps the System menu complete until that surface is migrated.',
            'next_steps' => [
                'Decide between wrapping the Magento page and a full Nebula migration.',
                'Use the Nebula UI migration tooling if native ownership is chosen.',
                'Enable Nebula UI removal only after the replacement fully owns the route.',
            ],
        ],
        'bulkactions' => [
            'title' => 'Bulk Actions',
            'menu_id' => 'Qoliber_NebulaSystem::system_magento_logging_bulk_operations',
            'resource' => 'Magento_Logging::system_magento_logging_bulk_operations',
            'group' => 'Action Logs',
            'original_route' => 'bulk/index/',
            'summary' => 'Nebula does not yet provide a native Bulk Actions page. This placeholder restores the Action Logs path while asynchronous operations coverage is planned.',
            'next_steps' => [
                'Check whether the existing Magento page can be shown in Nebula chrome.',
                'If a native page is required, generate a draft from the source UI components first.',
                'Preserve ACL behavior tied to the logging resource throughout the migration.',
            ],
        ],
        'storestermsconditions' => [
            'title' => 'Terms and Conditions',
            'menu_id' => 'Qoliber_NebulaMenu::stores_terms_conditions',
            'resource' => 'Magento_CheckoutAgreements::checkoutagreement',
            'group' => 'Settings',
            'original_route' => 'checkout/agreement/',
            'summary' => 'Nebula does not yet provide a native Terms and Conditions page. This placeholder restores the Stores menu path while the final ownership model is decided.',
            'next_steps' => [
                'Evaluate whether the Magento agreements listing can be wrapped cleanly inside Nebula chrome.',
                'If needed, migrate the listing and edit flow into a Nebula owned page.',
                'Keep ACL behavior and agreement editing workflows aligned with core Magento.',
            ],
        ],
        'storesrating' => [
            'title' => 'Rating',
            'menu_id' => 'Qoliber_NebulaMenu::stores_rating',
            'resource' => 'Magento_Review::ratings',
            'group' => 'Attributes',
            'original_route' => 'review/rating/',
            'summary' => 'Nebula does not yet provide a native Rating page. This placeholder restores the Stores Attributes path while review settings are planned.',
            'next_steps' => [
                'Check whether the Magento Rating page can be wrapped cleanly in Nebula.',
                'If a native Nebula page is preferred, map the listing and edit flows first.',
                'Preserve ACL behavior and active menu state when the placeholder is replaced.',
            ],
        ],
    ];

    /**
     * @return array{title: string, menu_id: string, resource: string, group: string, original_route: string, summary: string, next_steps: list<string>}
     */
    public function get(string $pageCode): array
    {
        if (!isset(self::PAGES[$pageCode])) {
            throw new InvalidArgumentException(sprintf('Unknown Nebula System placeholder page "%s".', $pageCode));
        }

        return self::PAGES[$pageCode];
    }
}
