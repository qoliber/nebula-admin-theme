<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;

class ShipmentView extends Template
{
    private ?ShipmentInterface $shipment = null;

    public function __construct(
        Context $context,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaTheme::shipment/view.phtml');
    }

    public function getShipment(): ?ShipmentInterface
    {
        if ($this->shipment === null) {
            $shipmentId = (int) $this->getRequest()->getParam('shipment_id');

            if ($shipmentId) {
                try {
                    $this->shipment = $this->shipmentRepository->get($shipmentId);
                } catch (\Exception $e) {
                    $this->shipment = null;
                }
            }
        }

        return $this->shipment;
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('sales/shipment/');
    }

    public function getOrderUrl(): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $this->getShipment()?->getOrderId()]);
    }

    public function getFormattedAddress(\Magento\Sales\Api\Data\OrderAddressInterface $address): string
    {
        $parts = array_filter([
            $address->getFirstname() . ' ' . $address->getLastname(),
            $address->getCompany(),
            implode(', ', $address->getStreet() ?? []),
            $address->getCity() . ', ' . $address->getRegion() . ' ' . $address->getPostcode(),
            $address->getCountryId(),
            'T: ' . $address->getTelephone(),
        ]);

        return implode("\n", $parts);
    }
}
