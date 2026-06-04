<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Helper\Reorder as ReorderHelper;

class OrderView extends Template
{
    private ?OrderInterface $order = null;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FormKey $formKey,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly AuthorizationInterface $authorization,
        private readonly ReorderHelper $reorderHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaTheme::order/view.phtml');
    }

    public function getOrder(): ?OrderInterface
    {
        if ($this->order === null) {
            $orderId = (int) $this->getRequest()->getParam('order_id');

            if ($orderId) {
                try {
                    $this->order = $this->orderRepository->get($orderId);
                } catch (\Exception $e) {
                    $this->order = null;
                }
            }
        }

        return $this->order;
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
        return $this->getUrl('sales/order/');
    }

    public function getInvoiceUrl(): string
    {
        return $this->getUrl('sales/order_invoice/start', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function canSendEmail(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && !$order->isCanceled()
            && $this->authorization->isAllowed('Magento_Sales::email');
    }

    public function getEmailUrl(): string
    {
        return $this->getUrl('sales/order/email', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getShipUrl(): string
    {
        return $this->getUrl('sales/order_shipment/start', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getCreditMemoUrl(): string
    {
        return $this->getUrl('sales/order_creditmemo/start', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getCancelUrl(): string
    {
        return $this->getUrl('sales/*/cancel', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getHoldUrl(): string
    {
        return $this->getUrl('sales/*/hold', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getUnholdUrl(): string
    {
        return $this->getUrl('sales/*/unhold', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function canReorder(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && $this->authorization->isAllowed('Magento_Sales::reorder')
            && $this->reorderHelper->isAllowed($order->getStore())
            && $order->canReorderIgnoreSalable();
    }

    public function getReorderUrl(): string
    {
        return $this->getUrl('sales/order_create/reorder', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getCommentUrl(): string
    {
        return $this->getUrl('sales/order/addComment', ['order_id' => $this->getOrder()?->getEntityId()]);
    }

    public function getStatusLabel(): string
    {
        $order = $this->getOrder();

        return $order ? (string) $order->getStatusLabel() : '';
    }

    public function getStatusBadgeClass(): string
    {
        $order = $this->getOrder();

        if (!$order) {
            return 'bg-gray-100 text-gray-800';
        }

        return match ($order->getState()) {
            'new' => 'bg-amber-100 text-amber-800',
            'pending_payment' => 'bg-amber-100 text-amber-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'complete' => 'bg-green-100 text-green-800',
            'closed' => 'bg-purple-100 text-purple-800',
            'canceled' => 'bg-red-100 text-red-800',
            'holded' => 'bg-gray-100 text-gray-800',
            'payment_review' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
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
            return (string) $this->getOrder()?->getPayment()?->getMethodInstance()?->getTitle();
        } catch (\Exception $e) {
            return (string) ($this->getOrder()?->getPayment()?->getMethod() ?? '');
        }
    }
}
