<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class UrlRewriteProvider implements DataProviderInterface
{
    public function __construct(
        private readonly UrlRewriteFactory $urlRewriteFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $urlRewrite = $this->urlRewriteFactory->create();
        $urlRewrite->load((int) $entityId);

        if (!$urlRewrite->getId()) {
            return [];
        }

        return [
            'url_rewrite_id' => $urlRewrite->getId(),
            'store_id' => $urlRewrite->getStoreId(),
            'request_path' => $urlRewrite->getRequestPath(),
            'target_path' => $urlRewrite->getTargetPath(),
            'redirect_type' => $urlRewrite->getRedirectType(),
            'description' => $urlRewrite->getDescription(),
        ];
    }
}
