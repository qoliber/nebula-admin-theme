<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;

class CreditmemoView extends Template
{
    private ?CreditmemoInterface $creditmemo = null;

    public function __construct(
        Context $context,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly FormKey $formKey,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaTheme::creditmemo/view.phtml');
    }

    public function getCreditmemo(): ?CreditmemoInterface
    {
        if ($this->creditmemo === null) {
            $creditmemoId = (int) $this->getRequest()->getParam('creditmemo_id');

            if ($creditmemoId) {
                try {
                    $this->creditmemo = $this->creditmemoRepository->get($creditmemoId);
                } catch (\Exception $e) {
                    $this->creditmemo = null;
                }
            }
        }

        return $this->creditmemo;
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
        return $this->getUrl('sales/creditmemo/');
    }

    public function getOrderUrl(): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $this->getCreditmemo()?->getOrderId()]);
    }

    public function getStateBadgeClass(): string
    {
        return match ($this->getCreditmemo()?->getState()) {
            \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN => 'bg-amber-100 text-amber-800',
            \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED => 'bg-green-100 text-green-800',
            \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStateLabel(): string
    {
        return match ($this->getCreditmemo()?->getState()) {
            \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN => (string) __('Pending'),
            \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED => (string) __('Refunded'),
            \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED => (string) __('Canceled'),
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
            return (string) $this->getCreditmemo()?->getOrder()?->getPayment()?->getMethodInstance()?->getTitle();
        } catch (\Exception $e) {
            return (string) ($this->getCreditmemo()?->getOrder()?->getPayment()?->getMethod() ?? '');
        }
    }
}
