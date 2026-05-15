<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\SalesRule\Helper\Coupon as CouponHelper;

/**
 * ViewModel for the `coupon_generator` snippet.
 *
 * Supplies:
 *   - The admin URL of \Magento\SalesRule\Controller\Adminhtml\Promo\Quote\Generate
 *   - The current form key (Magento posts validate it on every admin POST)
 *   - The list of code-format options the controller accepts
 *     (`alphanum` / `alpha` / `num`, see \Magento\SalesRule\Helper\Coupon)
 *   - The configured defaults for length / format / prefix / suffix / dash
 *     so the panel pre-fills sensibly without round-tripping store config.
 */
class CouponGenerator implements ArgumentInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly CouponHelper $couponHelper
    ) {
    }

    /**
     * URL of the AJAX endpoint the panel POSTs to.
     */
    public function getGenerateUrl(): string
    {
        return $this->urlBuilder->getUrl('sales_rule/promo_quote/generate');
    }

    /**
     * Current admin form key. Magento backend POSTs are validated against it.
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Format options as a flat list ready for the Alpine `<select>`.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFormatOptions(): array
    {
        $options = [];
        foreach ($this->couponHelper->getFormatsList() as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $options;
    }

    /**
     * Pre-fill defaults read from store configuration. The panel uses these
     * as the initial Alpine state so the user starts from the same defaults
     * they'd see in stock Magento's coupon-generation form.
     *
     * @return array{length: int, format: string, prefix: string, suffix: string, dash: int}
     */
    public function getDefaults(): array
    {
        return [
            'length' => $this->couponHelper->getDefaultLength(),
            'format' => (string) $this->couponHelper->getDefaultFormat(),
            'prefix' => (string) $this->couponHelper->getDefaultPrefix(),
            'suffix' => (string) $this->couponHelper->getDefaultSuffix(),
            'dash' => (int) $this->couponHelper->getDefaultDashInterval(),
        ];
    }
}
