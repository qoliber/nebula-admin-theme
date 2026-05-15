<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class CategoryFormProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly RequestInterface $request
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            // New category: seed parent_id / store_id from request params so
            // the EAV form block can resolve the attribute group layout.
            $defaults = [];
            $parentId = (string) ($this->request->getParam('parent') ?? '');
            if ($parentId !== '') {
                $defaults['parent_id'] = $parentId;
            }
            $storeId = (string) ($this->request->getParam('store') ?? '');
            if ($storeId !== '') {
                $defaults['store_id'] = $storeId;
            }
            return $defaults;
        }

        try {
            $storeId = (int) ($params['storeId'] ?? 0);
            $category = $this->categoryRepository->get((int) $entityId, $storeId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }

        $data = $category->getData();
        $data['_entity'] = $category;

        return $data;
    }
}
