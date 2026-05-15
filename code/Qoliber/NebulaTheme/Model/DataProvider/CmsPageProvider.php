<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Cms\Api\PageRepositoryInterface;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class CmsPageProvider implements DataProviderInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $page = $this->pageRepository->getById((int) $entityId);

        return [
            'page_id' => $page->getId(),
            'title' => $page->getTitle(),
            'identifier' => $page->getIdentifier(),
            'is_active' => $page->isActive() ? '1' : '0',
            'store_id' => $page->getStoreId(),
            'content_heading' => $page->getContentHeading(),
            'content' => $page->getContent(),
            'meta_title' => $page->getMetaTitle(),
            'meta_keywords' => $page->getMetaKeywords(),
            'meta_description' => $page->getMetaDescription(),
            'page_layout' => $page->getPageLayout(),
        ];
    }
}
