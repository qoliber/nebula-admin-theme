<?php

declare(strict_types=1);

namespace Qoliber\NebulaSkin\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Qoliber\NebulaSkin\Model\SkinLoader;

class SkinList implements OptionSourceInterface
{
    public function __construct(
        private readonly SkinLoader $skinLoader,
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->skinLoader->getAvailableSkins() as $skin) {
            $options[] = [
                'value' => $skin['id'],
                'label' => __($skin['label']),
            ];
        }

        return $options;
    }
}
