<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Api;

interface MasterFormatRendererInterface
{
    /**
     * Render tree JSON into master format HTML.
     *
     * @param array<int, array<string, mixed>> $tree
     * @return string
     */
    public function render(array $tree): string;
}
