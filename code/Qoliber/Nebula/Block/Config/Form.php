<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Config;

use Magento\Config\Model\Config\Structure\Element\Group;
use Magento\Config\Model\Config\Structure\Element\Section;
use Magento\Framework\Data\Form\Element\Fieldset;

/**
 * Nebula config form — auto-maps fields to custom renderers after init.
 */
class Form extends \Magento\Config\Block\System\Config\Form
{
    /** @var array<string, class-string> Pattern in field HTML ID to renderer FQCN */
    private const FIELD_RENDERER_MAP = [
        'color' => \Qoliber\Nebula\Block\Config\Form\Field\Color::class,
    ];

    /**
     * Suppress the inner <form> wrapper that vendor Data\Form emits when
     * useContainer is true (the default). Our edit.phtml already wraps
     * everything in <form id="config-edit-form"> — keeping the inner form
     * triggers Chrome's "Multiple forms should be contained in their own
     * form elements" warning and creates an invalid nested-form DOM.
     */
    public function initForm()
    {
        parent::initForm();
        $form = $this->getForm();
        if ($form instanceof \Magento\Framework\Data\Form) {
            // Magic setter — equivalent to $form->setUseContainer(false). Use
            // setData() so PHPStan sees a real method (Form's setUseContainer
            // is resolved via __call which static analysis can't follow).
            $form->setData('use_container', false);
        }

        return $this;
    }

    public function initFields(
        Fieldset $fieldset,
        Group $group,
        Section $section,
        $fieldPrefix = '',
        $labelPrefix = ''
    ) {
        parent::initFields($fieldset, $group, $section, $fieldPrefix, $labelPrefix);

        foreach ($fieldset->getElements() as $element) {
            $htmlId = strtolower((string) $element->getHtmlId());

            foreach (self::FIELD_RENDERER_MAP as $pattern => $rendererClass) {
                if (str_contains($htmlId, $pattern)) {
                    $renderer = $this->getLayout()->createBlock(
                        $rendererClass,
                        'nebula.field.' . $element->getHtmlId()
                    );
                    $renderer->setForm($this);
                    $renderer->setConfigData($this->_configData);
                    $element->setRenderer($renderer);
                    break;
                }
            }
        }

        return $this;
    }
}
