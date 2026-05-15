<?php

declare(strict_types=1);

namespace Qoliber\NebulaQuill\Block\Adminhtml;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaQuill\Model\Config\QuillConfig;

class Assets extends Template
{
    public function __construct(
        Context $context,
        private readonly QuillConfig $quillConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldLoadAssets(): bool
    {
        return $this->quillConfig->shouldUseQuill();
    }
}
