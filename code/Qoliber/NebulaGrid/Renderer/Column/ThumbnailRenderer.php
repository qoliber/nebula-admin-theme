<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

class ThumbnailRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_thumbnail';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/thumbnail.phtml';
    }
}
