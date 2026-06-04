<?php

declare(strict_types=1);

/**
 * Package-level registration loader. Lists only modules that exist in this
 * repository after the consolidation. Update this list whenever a module is
 * added or removed — composer install loads this file at autoload time, so
 * a stale entry here fatals the install.
 */
$moduleRegistrations = [
    'code/Qoliber/Nebula/registration.php',
    'code/Qoliber/NebulaComponent/registration.php',
    'code/Qoliber/NebulaCurrency/registration.php',
    'code/Qoliber/NebulaDirective/registration.php',
    'code/Qoliber/NebulaForm/registration.php',
    'code/Qoliber/NebulaGrid/registration.php',
    'code/Qoliber/NebulaMedia/registration.php',
    'code/Qoliber/NebulaMenu/registration.php',
    'code/Qoliber/NebulaQuill/registration.php',
    'code/Qoliber/NebulaReports/registration.php',
    'code/Qoliber/NebulaSkin/registration.php',
    'code/Qoliber/NebulaStore/registration.php',
    'code/Qoliber/NebulaSystem/registration.php',
    'code/Qoliber/NebulaTheme/registration.php',
    'code/Qoliber/NebulaUiRemoval/registration.php',
    'code/Qoliber/NebulaUser/registration.php',
    'design/adminhtml/Qoliber/Nebula/registration.php',
];

foreach ($moduleRegistrations as $file) {
    require_once __DIR__ . '/' . $file;
}
