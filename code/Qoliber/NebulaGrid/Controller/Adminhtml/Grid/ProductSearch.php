<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Controller\Adminhtml\Grid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\Store;

class ProductSearch extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageHelper $imageHelper
    ) {
        parent::__construct($context);
    }

    /** Hard server-side ceiling for the autocomplete result set. */
    private const MAX_LIMIT = 100;

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $query = (string) $this->getRequest()->getParam('q', '');
        // Clamp the requested limit. The endpoint feeds an admin autocomplete;
        // an uncapped limit lets a client pull the entire catalog in one
        // request (memory + DB pressure) just by passing ?limit=999999.
        $rawLimit = (int) $this->getRequest()->getParam('limit', 20);
        $limit = max(1, min($rawLimit, self::MAX_LIMIT));
        $exclude = (string) $this->getRequest()->getParam('exclude', '');
        $excludeIds = $exclude ? array_map('intval', explode(',', $exclude)) : [];

        if (strlen($query) < 2) {
            return $result->setData(['items' => []]);
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'status', 'type_id', 'thumbnail']);

        $collection->addAttributeToFilter(
            [
                ['attribute' => 'name', 'like' => '%' . $query . '%'],
                ['attribute' => 'sku', 'like' => '%' . $query . '%'],
            ]
        );

        if (!empty($excludeIds)) {
            $collection->addFieldToFilter('entity_id', ['nin' => $excludeIds]);
        }

        $collection->setPageSize($limit);

        $items = [];
        foreach ($collection as $product) {
            $thumbUrl = '';

            try {
                $imgHelper = clone $this->imageHelper;
                $thumbUrl = (string) $imgHelper->init($product, 'product_listing_thumbnail')
                    ->setImageFile($product->getThumbnail())
                    ->resize(80)
                    ->getUrl();
            } catch (\Exception $e) {
                // ignore
            }

            $items[] = [
                'id' => (int) $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'price' => $product->getPrice() ? (float) $product->getPrice() : null,
                'type_id' => $product->getTypeId(),
                'thumbnail' => $thumbUrl,
            ];
        }

        return $result->setData(['items' => $items]);
    }

    /**
     * Magento backend ACL hook — leading underscore is required by the
     * \Magento\Backend\App\AbstractAction contract.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Magento_Catalog::products');
    }
}
