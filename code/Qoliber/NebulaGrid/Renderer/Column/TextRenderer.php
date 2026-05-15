<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

/**
 * Default renderer: escape the raw scalar value.
 */
class TextRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_text';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/text.phtml';
    }
}
