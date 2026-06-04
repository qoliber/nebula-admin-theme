<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\System;

class Backups extends AbstractPlaceholder
{
    protected function getPageCode(): string
    {
        return 'backups';
    }
}
