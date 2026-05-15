<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Controller\Adminhtml\Grid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Authorization\DefinitionAccessControl;

class Data extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly LayoutFactory $layoutFactory,
        private readonly JsonFactory $jsonFactory,
        private readonly DefinitionAccessControl $definitionAccessControl,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();
        $id = (string) $this->getRequest()->getParam('id');

        try {
            $definition = $this->definitionResolver->resolve('grid', $id);

            if (!$this->definitionAccessControl->isAllowed($definition)) {
                return $result->setData(['html' => '<tr><td class="nebula-grid__empty">Access denied.</td></tr>']);
            }

            $layout = $this->layoutFactory->create();

            /** @var \Qoliber\NebulaGrid\Block\Grid $block */
            $block = $layout->createBlock(\Qoliber\NebulaGrid\Block\Grid::class);
            $block->setData('grid_id', $id);
            $html = $block->toHtml();

            return $result->setData(['html' => $html]);
        } catch (\Throwable $e) {
            $this->logger->error('Nebula Grid/Data failed: ' . $e->getMessage(), ['exception' => $e, 'grid_id' => $id]);
            return $result->setData([
                'html' => '<tr><td class="nebula-grid__empty">Error loading grid.</td></tr>',
            ]);
        }
    }

    /**
     * Magento backend ACL hook — leading underscore is required by the
     * \Magento\Backend\App\AbstractAction contract.
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        $id = (string) $this->getRequest()->getParam('id');

        try {
            $definition = $this->definitionResolver->resolve('grid', $id);
            $acl = $definition['acl'] ?? 'Magento_Backend::admin';

            return $this->_authorization->isAllowed($acl);
        } catch (\Exception) {
            return false;
        }
    }
}
