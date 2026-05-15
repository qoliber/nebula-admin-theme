<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\RequestInterface;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class ProductFormProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RequestInterface $request,
        private readonly EavConfig $eavConfig
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            // New product: seed defaults from request params (type + set) so
            // the EAV form block can resolve the attribute group layout.
            // Fall back to the catalog default attribute set + simple type if
            // the URL doesn't carry params (e.g. when an admin lands on
            // /catalog/product/new/ directly without using the split-button).
            $typeId  = (string) ($this->request->getParam('type')  ?? 'simple');
            $setId   = (string) ($this->request->getParam('set')   ?? '');
            $storeId = (string) ($this->request->getParam('store') ?? '0');

            if ($setId === '') {
                $setId = (string) $this->getDefaultProductAttributeSetId();
            }

            return [
                'type_id'          => $typeId !== '' ? $typeId : 'simple',
                'attribute_set_id' => $setId,
                'store_id'         => $storeId !== '' ? $storeId : '0',
            ];
        }

        try {
            $product = $this->productRepository->getById((int) $entityId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }

        $data = $product->getData();
        $data['_entity'] = $product;

        // For configurable products, include data needed to preserve associations on save
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $typeInstance = $product->getTypeInstance();

            // Super attribute IDs — needed by TypeTransitionManager plugin
            $superAttributes = $typeInstance->getConfigurableAttributes($product);
            $attrIds = [];
            foreach ($superAttributes as $superAttr) {
                $attrIds[] = $superAttr->getAttributeId();
            }
            $data['_configurable_attribute_ids'] = implode(',', $attrIds);

            // Child product IDs — needed by Initialization Helper plugin to preserve links
            $childIds = $typeInstance->getUsedProductIds($product);
            $data['_associated_product_ids_serialized'] = json_encode(array_values($childIds));
        }

        return $data;
    }

    /**
     * Resolve the catalog_product entity-type's default attribute set so a
     * new-product form lands on a usable set even without URL params.
     */
    private function getDefaultProductAttributeSetId(): int
    {
        try {
            return (int) $this->eavConfig->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
        } catch (\Throwable) {
            return 4;
        }
    }
}
