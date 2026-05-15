<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `customer_addresses` snippet. Supplies country +
 * region lookups that previously came from ObjectManager inside the
 * template.
 */
class CustomerAddresses implements ArgumentInterface
{
    public function __construct(
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getCountries(): array
    {
        $collection = $this->countryCollectionFactory->create()->loadByStore();
        $countries = [];
        foreach ($collection as $country) {
            $countries[] = [
                'value' => (string) $country->getCountryId(),
                'label' => (string) $country->getName(),
            ];
        }

        return $countries;
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function getRegionsByCountry(): array
    {
        // No country filter — return every region grouped by its country.
        $collection = $this->regionCollectionFactory->create();

        $regionsByCountry = [];
        foreach ($collection as $region) {
            $countryId = (string) $region->getCountryId();
            $regionsByCountry[$countryId][] = [
                'value' => (string) $region->getId(),
                'label' => (string) $region->getName(),
            ];
        }

        return $regionsByCountry;
    }
}
