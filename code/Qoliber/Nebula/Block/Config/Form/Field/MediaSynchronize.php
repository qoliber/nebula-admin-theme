<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\MediaStorage\Model\File\Storage as MediaStorage;
use Magento\MediaStorage\Model\File\Storage\Flag;
use Qoliber\Nebula\Block\Config\Form\Field;

/**
 * Nebula replacement for Magento_MediaStorage's Synchronize renderer.
 * Vanilla relies on Prototype + RequireJS, both stripped from the
 * Nebula admin — bound to nebulaMediaSync Alpine factory instead.
 */
class MediaSynchronize extends Field
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private readonly MediaStorage $fileStorage,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_Nebula::config/media-synchronize.phtml');
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function getSyncUrl(): string
    {
        return $this->getUrl('*/system_config_system_storage/synchronize');
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl('*/system_config_system_storage/status');
    }

    public function isInitiallyRunning(): bool
    {
        return (int) $this->fileStorage->getSyncFlag()->getState() === Flag::STATE_RUNNING;
    }
}
