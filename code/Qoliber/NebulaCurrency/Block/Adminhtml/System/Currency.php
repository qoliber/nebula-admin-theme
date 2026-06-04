<?php

declare(strict_types=1);

namespace Qoliber\NebulaCurrency\Block\Adminhtml\System;

/**
 * Nebula-styled Currency Rates page block.
 *
 * Extends the stock CurrencySymbol Currency block and only swaps the
 * template. The parent's _prepareLayout() still registers the `rates_matrix`
 * and `import_services` children — the new template reads the matrix data
 * directly from those child blocks so the POST payload shape is identical
 * to the stock form (`rate[<base>][<target>]` and `rate_services`).
 *
 * The class is wired in via a DI preference (etc/di.xml) so that the
 * controller `\Magento\CurrencySymbol\Controller\Adminhtml\System\Currency\Index`,
 * which builds the block by FQDN, transparently picks this subclass up.
 */
class Currency extends \Magento\CurrencySymbol\Block\Adminhtml\System\Currency
{
    /** @var string */
    protected $_template = 'Qoliber_NebulaCurrency::currency/rates.phtml';

    /**
     * Keep the stock child blocks but remove the stock toolbar actions.
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $toolbar = $this->getToolbar();
        if ($toolbar !== false && $toolbar !== null) {
            $toolbar->unsetChild('save_button');
            $toolbar->unsetChild('options_button');
            $toolbar->unsetChild('reset_button');
        }

        return $this;
    }

    /**
     * Resolve the matrix child block instance (added by parent _prepareLayout).
     *
     * @return \Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Matrix|null
     */
    public function getMatrixBlock(): ?\Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Matrix
    {
        $child = $this->getChildBlock('rates_matrix');
        return $child instanceof \Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Matrix
            ? $child
            : null;
    }

    /**
     * Resolve the services child block instance.
     *
     * @return \Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Services|null
     */
    public function getServicesBlock(): ?\Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Services
    {
        $child = $this->getChildBlock('import_services');
        return $child instanceof \Magento\CurrencySymbol\Block\Adminhtml\System\Currency\Rate\Services
            ? $child
            : null;
    }

    /**
     * Resolve the `<select>` block built by Services::_prepareLayout().
     *
     * Returns the inner "import_services" child which is the actual select.
     *
     * @return \Magento\Framework\View\Element\Html\Select|null
     */
    public function getServicesSelectBlock(): ?\Magento\Framework\View\Element\Html\Select
    {
        $services = $this->getServicesBlock();
        if ($services === null) {
            return null;
        }
        $select = $services->getChildBlock('import_services');
        return $select instanceof \Magento\Framework\View\Element\Html\Select ? $select : null;
    }

    /**
     * Form action for the Save Rates submission (preserves stock route).
     */
    public function getRatesFormAction(): string
    {
        return $this->getUrl('adminhtml/system_currency/saveRates');
    }

    /**
     * Deep link to the Magento currency configuration section.
     */
    public function getCurrencyOptionsUrl(): string
    {
        return $this->getUrl(
            'adminhtml/system_config/edit',
            [
                'section' => 'currency',
                '_fragment' => 'currency_options-link',
            ]
        );
    }
}
