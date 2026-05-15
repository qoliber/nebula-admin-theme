<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaPageBuilder\Model\ContentType\Registry;

class PageBuilder extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaPageBuilder::stage.phtml');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getContentTypes(): array
    {
        return $this->registry->getAll();
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getContentTypesBySection(): array
    {
        return $this->registry->getByMenuSection();
    }

    public function getContentTypesJson(): string
    {
        return (string) json_encode($this->getContentTypes(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public function getRenderUrl(): string
    {
        return $this->getUrl('nebula/pageBuilder/render');
    }

    public function getParseUrl(): string
    {
        return $this->getUrl('nebula/pageBuilder/parse');
    }

    public function getFieldName(): string
    {
        return (string) ($this->getData('field_name') ?: 'content');
    }

    public function getFieldValue(): string
    {
        return (string) ($this->getData('field_value') ?: '');
    }
}
