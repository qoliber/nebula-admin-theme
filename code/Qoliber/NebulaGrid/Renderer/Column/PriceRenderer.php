<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;

class PriceRenderer extends AbstractColumnRenderer
{
    public function __construct(
        Escaper $escaper,
        UrlInterface $urlBuilder,
        LayoutInterface $layout,
        PhtmlRenderer $phtmlRenderer,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
        parent::__construct($escaper, $urlBuilder, $layout, $phtmlRenderer);
    }

    public function getComponentName(): string
    {
        return 'nebulaColumn_price';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/price.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        $raw = $item[$key] ?? null;

        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => $raw,
            'formatted' => $raw === null ? '' : (string) $this->priceCurrency->format($raw, true, 2),
        ];
    }
}
