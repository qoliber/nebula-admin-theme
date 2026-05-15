<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Block\Adminhtml;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Cms\Helper\Wysiwyg\Images;

class Assets extends Template
{
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly FormKey $formKey,
        private readonly Images $imagesHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldLoadAssets(): bool
    {
        return true;
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'treeUrl' => $this->getUrl('nebulamedia/browser/tree'),
            'contentsUrl' => $this->getUrl('nebulamedia/browser/contents'),
            'uploadUrl' => $this->getUrl('nebulamedia/browser/upload'),
            'createDirectoryUrl' => $this->getUrl('nebulamedia/browser/createDirectory'),
            'deleteFileUrl' => $this->getUrl('nebulamedia/browser/deleteFile'),
            'formKey' => $this->formKey->getFormKey(),
            'defaultPathId' => $this->imagesHelper->idEncode('wysiwyg'),
        ]);
    }
}
