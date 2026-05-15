<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Api;

interface MasterFormatParserInterface
{
    /**
     * Parse master format HTML into tree JSON.
     *
     * @param string $html
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $html): array;
}
