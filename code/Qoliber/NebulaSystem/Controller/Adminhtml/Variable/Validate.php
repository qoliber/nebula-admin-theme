<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Variable;

use Magento\Framework\DataObject;

class Validate extends AbstractVariable
{
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $response = new DataObject(['error' => false]);
        $variable = $this->initVariable();
        $postData = $this->getRequest()->getPost('variable');
        $variable->addData(is_array($postData) ? $postData : []);
        $result = $variable->validate();

        if ($result instanceof \Magento\Framework\Phrase) {
            $this->messageManager->addError($result->getText());
            $layout = $this->layoutFactory->create();
            $layout->initMessages();
            $response->setError(true);
            $response->setHtmlMessage($layout->getMessagesBlock()->getGroupedHtml());
        }

        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($response->toArray());
    }
}
