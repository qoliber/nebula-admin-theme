<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address\Mapper as AddressMapper;
use Magento\Customer\Model\Customer\Mapper;
use Magento\Customer\Model\Logger as CustomerLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class CustomerFormProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Mapper $customerMapper,
        private readonly AddressMapper $addressMapper,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly SubscriberFactory $subscriberFactory,
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly CustomerLogger $customerLogger,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $customerId = (int) $entityId;

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        $data = $this->customerMapper->toFlatArray($customer);
        $data['id'] = $customer->getId();
        $data['entity_id'] = $customer->getId();

        $addresses = [];
        try {
            foreach ($customer->getAddresses() as $address) {
                $addressData = $this->addressMapper->toFlatArray($address);
                $addressData['id'] = $address->getId();
                $addresses[] = $addressData;
            }
        } catch (NoSuchEntityException $e) {
            // No addresses
        }

        $data['addresses'] = $addresses;
        $data['_stats'] = $this->getOrderStats($customerId);
        $data['_account'] = $this->getAccountStatus($customer);
        $data['_newsletter'] = $this->getNewsletterStatus($customerId);
        $data['_cart'] = $this->getCartSummary($customerId);

        return $data;
    }

    private function getOrderStats(int $customerId): array
    {
        $stats = [
            'order_count' => 0,
            'lifetime_revenue' => 0.0,
            'avg_order_value' => 0.0,
            'last_order_date' => null,
            'last_order_id' => null,
            'last_order_increment_id' => null,
            'review_count' => 0,
        ];

        try {
            /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', $customerId);
            $collection->getSelect()->columns([
                'order_count' => new \Zend_Db_Expr('COUNT(*)'),
                'lifetime_revenue' => new \Zend_Db_Expr('SUM(grand_total)'),
                'avg_order_value' => new \Zend_Db_Expr('AVG(grand_total)'),
                'last_order_date' => new \Zend_Db_Expr('MAX(created_at)'),
            ]);

            $row = $collection->getFirstItem();
            $stats['order_count'] = (int) $row->getData('order_count');
            $stats['lifetime_revenue'] = (float) $row->getData('lifetime_revenue');
            $stats['avg_order_value'] = (float) $row->getData('avg_order_value');
            $stats['last_order_date'] = $row->getData('last_order_date');

            if ($stats['order_count'] > 0) {
                /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $lastOrderCollection */
                $lastOrderCollection = $this->orderCollectionFactory->create();
                $lastOrderCollection->addFieldToFilter('customer_id', $customerId);
                $lastOrderCollection->setOrder('created_at', 'DESC');
                $lastOrderCollection->setPageSize(1);
                $lastOrder = $lastOrderCollection->getFirstItem();
                $stats['last_order_id'] = (int) $lastOrder->getId();
                $stats['last_order_increment_id'] = $lastOrder->getIncrementId();
            }
        } catch (\Exception $e) {
            // Return default stats
        }

        try {
            /** @var \Magento\Review\Model\ResourceModel\Review\Collection $reviewCollection */
            $reviewCollection = $this->reviewCollectionFactory->create();
            $reviewCollection->addFieldToFilter('detail.customer_id', $customerId);
            $stats['review_count'] = (int) $reviewCollection->getSize();
        } catch (\Exception $e) {
            // Return default review count
        }

        return $stats;
    }

    private function getAccountStatus(\Magento\Customer\Api\Data\CustomerInterface $customer): array
    {
        $account = [
            'is_confirmed' => true,
            'is_locked' => false,
            'created_at' => null,
            'last_login' => null,
            'store_name' => null,
        ];

        try {
            $confirmation = $customer->getConfirmation();
            $account['is_confirmed'] = $confirmation === null || $confirmation === '';

            $lockExpires = $customer->getCustomAttribute('lock_expires');
            if ($lockExpires && $lockExpires->getValue()) {
                $lockDate = new \DateTime($lockExpires->getValue());
                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $account['is_locked'] = $lockDate > $now;
            }

            $account['created_at'] = $customer->getCreatedAt();

            $customerId = (int) $customer->getId();
            $logData = $this->customerLogger->get($customerId);
            $lastLogin = $logData->getLastLoginAt();
            $account['last_login'] = $lastLogin ?: null;

            $storeId = (int) $customer->getStoreId();
            $store = $this->storeManager->getStore($storeId);
            $account['store_name'] = $store->getName();
        } catch (\Exception $e) {
            // Return default account info
        }

        return $account;
    }

    private function getNewsletterStatus(int $customerId): array
    {
        $newsletter = [
            'is_subscribed' => false,
            'subscriber_status' => 0,
        ];

        try {
            /** @var \Magento\Newsletter\Model\Subscriber $subscriber */
            $subscriber = $this->subscriberFactory->create()->loadByCustomerId($customerId);
            $newsletter['is_subscribed'] = $subscriber->isSubscribed();
            $newsletter['subscriber_status'] = (int) $subscriber->getSubscriberStatus();
        } catch (\Exception $e) {
            // Return default newsletter status
        }

        return $newsletter;
    }

    private function getCartSummary(int $customerId): array
    {
        $cart = [
            'item_count' => 0,
            'cart_total' => 0.0,
        ];

        try {
            /** @var \Magento\Quote\Model\ResourceModel\Quote\Collection $quoteCollection */
            $quoteCollection = $this->quoteCollectionFactory->create();
            $quoteCollection->addFieldToFilter('customer_id', $customerId);
            $quoteCollection->addFieldToFilter('is_active', 1);
            $quoteCollection->setOrder('updated_at', 'DESC');
            $quoteCollection->setPageSize(1);

            $quote = $quoteCollection->getFirstItem();

            if ($quote->getId()) {
                $cart['item_count'] = (int) $quote->getItemsCount();
                $cart['cart_total'] = (float) $quote->getGrandTotal();
            }
        } catch (\Exception $e) {
            // Return default cart data
        }

        return $cart;
    }
}
