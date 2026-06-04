<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Product\Downloads;

/**
 * Pre-configured downloadable-product report collection for Nebula grids.
 *
 * Mirrors the setup the stock {@see \Magento\Reports\Block\Adminhtml\Product\Downloads\Grid}
 * performs in `_prepareCollection()`: select all attributes, filter to downloadable
 * products, and add the purchases/downloads summary join.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Product\Downloads\Collection
{
    private bool $nebulaConfigured = false;

    /**
     * @inheritDoc
     */
    protected function _beforeLoad()
    {
        $this->configureNebula();

        return parent::_beforeLoad();
    }

    /**
     * @inheritDoc
     */
    public function getSelectCountSql()
    {
        $this->configureNebula();

        return parent::getSelectCountSql();
    }

    private function configureNebula(): void
    {
        if ($this->nebulaConfigured) {
            return;
        }

        $this->nebulaConfigured = true;

        $this->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', [\Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE])
            ->addSummary();
    }
}
