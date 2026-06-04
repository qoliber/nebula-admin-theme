<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Block\Adminhtml;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Qoliber\NebulaDirective\Model\VariableProvider;
use Qoliber\NebulaDirective\Model\WidgetProvider;
use Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsPage;

class Assets extends Template
{
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly FormKey $formKey,
        private readonly VariableProvider $variableProvider,
        private readonly WidgetProvider $widgetProvider,
        private readonly WidgetChooserRegistry $chooserRegistry,
        private readonly CmsPage $cmsPageChooser,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'variableUrl'  => $this->getUrl('nebuladirective/variable/index'),
            'widgetTypesUrl' => $this->getUrl('nebuladirective/widget/types'),
            'widgetParamsUrl' => $this->getUrl('nebuladirective/widget/params'),
            'widgetBuildUrl' => $this->getUrl('nebuladirective/widget/build'),
            'formKey'      => $this->formKey->getFormKey(),
            'meta' => [
                'variables' => $this->variableProvider->getLookup(),
                'widgets' => $this->widgetProvider->getLookup(),
            ],
        ]);
    }

    public function renderModalChoosers(): string
    {
        $html = '';
        $renderedAliases = [];

        foreach ($this->chooserRegistry->modal() as $alias => $cfg) {
            $chooserBlock = $this->getLayout()->createBlock(
                Template::class,
                'nebula.directive.chooser.' . $alias,
                [
                    'data' => [
                        'template' => $cfg['template'],
                        'widget_chooser_' . $alias => $this->chooserRegistry->resolveViewModel($alias),
                    ],
                ],
            );

            $html .= $chooserBlock->toHtml();
            $renderedAliases[] = $alias;
        }

        if (!in_array('cms_page', $renderedAliases, true)) {
            $chooserBlock = $this->getLayout()->createBlock(
                Template::class,
                'nebula.directive.chooser.cms_page.fallback',
                [
                    'data' => [
                        'template' => 'Qoliber_NebulaTheme::widget/chooser/cms_page.phtml',
                        'widget_chooser_cms_page' => $this->cmsPageChooser,
                    ],
                ],
            );

            $html .= $chooserBlock->toHtml();
        }

        return $html;
    }
}
