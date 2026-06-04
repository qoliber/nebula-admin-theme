<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Block\Adminhtml\System;

use Magento\Backend\Block\Template;

class Placeholder extends Template
{
    protected $_template = 'Qoliber_NebulaSystem::system/placeholder.phtml';

    /**
     * @return array{title?: string, group?: string, original_route?: string, summary?: string, next_steps?: list<string>}
     */
    public function getPageDefinition(): array
    {
        $definition = $this->getData('page_definition');

        return is_array($definition) ? $definition : [];
    }
}
