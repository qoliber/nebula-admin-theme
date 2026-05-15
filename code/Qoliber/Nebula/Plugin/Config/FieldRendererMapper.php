<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin\Config;

use Magento\Config\Block\System\Config\Form;
use Magento\Config\Model\Config\Structure\Element\Group;
use Magento\Config\Model\Config\Structure\Element\Section;
use Magento\Framework\Data\Form\Element\Fieldset;
use Qoliber\Nebula\Block\Config\Form\Field\Color;

/**
 * Auto-maps config fields to Nebula custom renderers based on field characteristics.
 */
class FieldRendererMapper
{
    /** @var array<string, string> */
    private const FIELD_RENDERERS = [
        'color' => Color::class,
    ];

    /** @var array<string, object> */
    private array $rendererCache = [];

    /**
     * After initFields — swap renderers for matching fields.
     *
     * @param \Magento\Config\Block\System\Config\Form $subject
     * @param \Magento\Config\Block\System\Config\Form $result
     * @param \Magento\Framework\Data\Form\Element\Fieldset $fieldset
     * @param \Magento\Config\Model\Config\Structure\Element\Group $group
     * @param \Magento\Config\Model\Config\Structure\Element\Section $section
     * @param string $fieldPrefix
     * @param string $labelPrefix
     * @return \Magento\Config\Block\System\Config\Form
     */
    public function afterInitFields(
        Form $subject,
        Form $result,
        Fieldset $fieldset,
        Group $group,
        Section $section,
        $fieldPrefix = '',
        $labelPrefix = ''
    ): Form {
        $layout = $subject->getLayout();

        foreach ($fieldset->getElements() as $element) {
            $htmlId = strtolower((string) $element->getHtmlId());

            foreach (self::FIELD_RENDERERS as $pattern => $rendererClass) {
                if (str_contains($htmlId, $pattern)) {
                    if (!isset($this->rendererCache[$rendererClass])) {
                        $this->rendererCache[$rendererClass] = $layout->createBlock(
                            $rendererClass,
                            'nebula.renderer.' . $pattern . '.' . $htmlId
                        );
                    }
                    $renderer = $layout->createBlock(
                        $rendererClass,
                        'nebula.renderer.' . $htmlId
                    );
                    $renderer->setForm($subject);
                    $element->setRenderer($renderer);
                    break;
                }
            }
        }

        return $result;
    }
}
