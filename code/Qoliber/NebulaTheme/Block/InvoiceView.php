<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;

class InvoiceView extends Template
{
    private ?InvoiceInterface $invoice = null;

    public function __construct(
        Context $context,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FormKey $formKey,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaTheme::invoice/view.phtml');
    }

    public function getInvoice(): ?InvoiceInterface
    {
        if ($this->invoice === null) {
            $invoiceId = (int) $this->getRequest()->getParam('invoice_id');

            if ($invoiceId) {
                try {
                    $this->invoice = $this->invoiceRepository->get($invoiceId);
                } catch (\Exception $e) {
                    $this->invoice = null;
                }
            }
        }

        return $this->invoice;
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function formatPrice(float|string|null $price): string
    {
        return $this->priceCurrency->format((float) $price, false, 2);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('sales/invoice/');
    }

    public function getOrderUrl(): string
    {
        $invoice = $this->getInvoice();
        return $this->getUrl('sales/order/view', ['order_id' => $invoice?->getOrderId()]);
    }

    public function getStateBadgeClass(): string
    {
        return match ($this->getInvoice()?->getState()) {
            \Magento\Sales\Model\Order\Invoice::STATE_OPEN => 'bg-amber-100 text-amber-800',
            \Magento\Sales\Model\Order\Invoice::STATE_PAID => 'bg-green-100 text-green-800',
            \Magento\Sales\Model\Order\Invoice::STATE_CANCELED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStateLabel(): string
    {
        return match ($this->getInvoice()?->getState()) {
            \Magento\Sales\Model\Order\Invoice::STATE_OPEN => (string) __('Pending'),
            \Magento\Sales\Model\Order\Invoice::STATE_PAID => (string) __('Paid'),
            \Magento\Sales\Model\Order\Invoice::STATE_CANCELED => (string) __('Canceled'),
            default => (string) __('Unknown'),
        };
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

    public function getPaymentMethodTitle(): string
    {
        try {
            return (string) $this->getInvoice()?->getOrder()?->getPayment()?->getMethodInstance()?->getTitle();
        } catch (\Exception $e) {
            return (string) ($this->getInvoice()?->getOrder()?->getPayment()?->getMethod() ?? '');
        }
    }
}
