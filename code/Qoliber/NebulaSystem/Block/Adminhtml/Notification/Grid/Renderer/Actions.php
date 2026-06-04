<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Block\Adminhtml\Notification\Grid\Renderer;

use Magento\AdminNotification\Controller\Adminhtml\Notification\MarkAsRead;
use Magento\AdminNotification\Controller\Adminhtml\Notification\Remove;
use Magento\Backend\Block\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Url\Helper\Data;

class Actions extends \Magento\AdminNotification\Block\Grid\Renderer\Actions
{
    private const BUTTON_BASE_CLASSES =
        'inline-flex items-center rounded px-2 py-1 text-xs font-medium transition';

    private const NEUTRAL_BUTTON_CLASSES =
        self::BUTTON_BASE_CLASSES . ' text-gray-600 border border-gray-300 hover:bg-gray-50';

    private const DANGER_BUTTON_CLASSES =
        self::BUTTON_BASE_CLASSES . ' text-red-600 border border-red-300 hover:bg-red-50';

    public function __construct(
        Context $context,
        Data $urlHelper,
        array $data = []
    ) {
        parent::__construct($context, $urlHelper, $data);
    }

    public function render(DataObject $row): string
    {
        $actions = [];

        if ($row->getUrl()) {
            $actions[] = sprintf(
                '<a class="%s" target="_blank" rel="noopener noreferrer" href="%s">%s</a>',
                self::NEUTRAL_BUTTON_CLASSES,
                $this->escapeUrl((string) $row->getUrl()),
                $this->escapeHtml((string) __('Read Details'))
            );
        }

        if (!$row->getIsRead() && $this->_authorization->isAllowed(MarkAsRead::ADMIN_RESOURCE)) {
            $actions[] = sprintf(
                '<a class="%s" href="%s">%s</a>',
                self::NEUTRAL_BUTTON_CLASSES,
                $this->escapeUrl($this->getUrl(
                    'nebulasystem/notification/markAsRead',
                    ['id' => $row->getNotificationId()]
                )),
                $this->escapeHtml((string) __('Mark as Read'))
            );
        }

        if ($this->_authorization->isAllowed(Remove::ADMIN_RESOURCE)) {
            $removeUrl = $this->getUrl(
                'nebulasystem/notification/remove',
                [
                    'id' => $row->getNotificationId(),
                    ActionInterface::PARAM_NAME_URL_ENCODED => $this->_urlHelper->getEncodedUrl()
                ]
            );

            $actions[] = sprintf(
                '<a class="%s" href="%s" onclick="deleteConfirm(%s, this.href); return false;">%s</a>',
                self::DANGER_BUTTON_CLASSES,
                $this->escapeUrl($removeUrl),
                '\'' . $this->escapeJsQuote((string) __('Are you sure?')) . '\'',
                $this->escapeHtml((string) __('Remove'))
            );
        }

        if ($actions === []) {
            return '';
        }

        return '<div class="inline-flex items-center gap-1.5 whitespace-nowrap">' . implode('', $actions) . '</div>';
    }
}
