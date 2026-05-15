<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Widget\Form\Element;

/**
 * Nebula dependence emitter — emits only a JSON payload that nebula-core
 * (ts/components/config-depends.ts) reads on DOMContentLoaded. The vanilla
 * RequireJS-wrapped boot script never runs in nebula because the admin
 * layout strips RequireJS.
 */
class Dependence extends \Magento\Backend\Block\Widget\Form\Element\Dependence
{
    private const PAYLOAD_ID = 'nebula-config-depends-data';

    protected function _toHtml()
    {
        if (!$this->_depends) {
            // Marker so we can confirm from page source that the block ran but had no rules.
            return '<!-- nebula-depends: empty _depends -->';
        }

        $map = [];
        foreach ($this->_depends as $to => $sources) {
            $toId = $this->_fields[$to] ?? null;
            if ($toId === null) {
                continue;
            }
            foreach ($sources as $from => $field) {
                $fromId = $this->_fields[$from] ?? null;
                if ($fromId === null) {
                    continue;
                }
                /** @var \Magento\Config\Model\Config\Structure\Element\Dependency\Field $field */
                $map[$toId][] = [
                    'from' => $fromId,
                    'values' => array_map('strval', array_values($field->getValues())),
                    'negative' => (bool) $field->isNegative(),
                ];
            }
        }

        if (!$map) {
            return '';
        }

        $payload = $this->_jsonEncoder->encode($map);

        return /* @noEscape */ $this->secureRenderer->renderTag(
            'script',
            ['type' => 'application/json', 'id' => self::PAYLOAD_ID],
            $payload,
            false
        );
    }
}
