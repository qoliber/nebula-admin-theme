<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Email\Model\TemplateFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class EmailTemplateProvider implements DataProviderInterface
{
    public function __construct(
        private readonly TemplateFactory $templateFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $template = $this->templateFactory->create()->load((int) $entityId);

        if (!$template->getId()) {
            return [];
        }

        return [
            'template_id' => $template->getId(),
            'template_code' => $template->getTemplateCode(),
            'template_subject' => $template->getTemplateSubject(),
            'template_text' => $template->getTemplateText(),
            'template_styles' => $template->getTemplateStyles(),
            'orig_template_code' => $template->getOrigTemplateCode(),
            'orig_template_variables' => $template->getOrigTemplateVariables(),
        ];
    }
}
