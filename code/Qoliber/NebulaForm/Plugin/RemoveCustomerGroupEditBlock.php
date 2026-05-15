<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Plugin;

use Magento\Backend\Model\View\Result\Page;
use Magento\Customer\Controller\Adminhtml\Group\NewAction;

class RemoveCustomerGroupEditBlock
{
    public function afterExecute(NewAction $subject, Page $result): Page
    {
        $result->getLayout()->unsetElement('group');

        return $result;
    }
}
