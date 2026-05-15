<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

/**
 * @api
 *
 * Emits the raw `value` field for a row **without** escaping. This is an
 * opt-in renderer used by columns whose upstream already produced
 * trusted HTML (e.g. snippet renderers, migrated UI Component renderers).
 * The grid JSON must set `"type": "html"` to reach this renderer; default
 * routing goes through {@see TextRenderer} which escapes.
 */
class HtmlRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_html';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/html.phtml';
    }
}
