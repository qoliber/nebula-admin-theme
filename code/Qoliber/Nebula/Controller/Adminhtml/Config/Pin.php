<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * AJAX endpoint to toggle pinned config sections per admin user.
 * Stored in user extra data under 'nebulaPinnedSections'.
 */
class Pin extends Action
{
    public function __construct(
        Context $context,
        private readonly Session $authSession,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sectionId = $this->getRequest()->getParam('section');

        if (!$sectionId) {
            return $result->setData(['success' => false]);
        }

        $user = $this->authSession->getUser();
        $extra = $user->getExtra() ?: [];
        $pinned = $extra['nebulaPinnedSections'] ?? [];

        if (in_array($sectionId, $pinned)) {
            $pinned = array_values(array_diff($pinned, [$sectionId]));
        } else {
            $pinned[] = $sectionId;
        }

        $extra['nebulaPinnedSections'] = $pinned;
        $user->saveExtra($extra);

        return $result->setData([
            'success' => true,
            'pinned' => in_array($sectionId, $pinned),
        ]);
    }
}
