<?php

declare(strict_types=1);

namespace Qoliber\Nebula\ViewModel;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Dashboard implements ArgumentInterface
{
    public function __construct(
        private readonly Session $authSession
    ) {
    }

    public function getAdminFirstName(): string
    {
        $user = $this->authSession->getUser();
        return $user ? (string) $user->getFirstName() : '';
    }

    public function getGreeting(): string
    {
        $hour = (int) date('G');
        if ($hour < 12) {
            return (string) __('Good morning');
        }
        if ($hour < 18) {
            return (string) __('Good afternoon');
        }
        return (string) __('Good evening');
    }
}
