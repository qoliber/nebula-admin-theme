<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

interface FieldRendererInterface
{
    /**
     * @return string
     */
    public function getComponentName(): string;

    /**
     * @return string
     */
    public function getTemplate(): string;
}
