<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Test\Unit\Model\Event;

use PHPUnit\Framework\TestCase;
use Qoliber\NebulaComponent\Model\Event\DefinitionResolveEvent;
use Qoliber\NebulaComponent\Model\Event\FormSaveEvent;
use Qoliber\NebulaComponent\Model\Event\GridDataEvent;

class EventPayloadTest extends TestCase
{
    public function testDefinitionResolveEventRoundTrip(): void
    {
        $event = new DefinitionResolveEvent('grid', 'product_listing', ['columns' => ['name' => []]]);

        self::assertSame('grid', $event->getType());
        self::assertSame('product_listing', $event->getId());
        self::assertSame(['columns' => ['name' => []]], $event->getDefinition());

        $event->setDefinition(['columns' => ['sku' => []]]);
        self::assertSame(['columns' => ['sku' => []]], $event->getDefinition());
    }

    public function testGridDataEventRoundTrip(): void
    {
        $event = new GridDataEvent(
            'customer_listing',
            ['collection' => 'Magento\\Customer\\Model\\ResourceModel\\Customer\\Collection'],
            ['page' => 1, 'pageSize' => 20],
            [['id' => 1], ['id' => 2]],
            2
        );

        self::assertSame('customer_listing', $event->getGridId());
        self::assertSame(['page' => 1, 'pageSize' => 20], $event->getParams());
        self::assertCount(2, $event->getItems());
        self::assertSame(2, $event->getTotalCount());

        $event->setItems([['id' => 99]]);
        $event->setTotalCount(1);

        self::assertCount(1, $event->getItems());
        self::assertSame(1, $event->getTotalCount());
    }

    public function testGridDataEventClampsTotalCount(): void
    {
        $event = new GridDataEvent('x', [], [], [], 5);
        $event->setTotalCount(-1);
        self::assertSame(0, $event->getTotalCount());
    }

    public function testFormSaveEventBeforeShape(): void
    {
        $event = new FormSaveEvent('customer_group_edit', ['code' => 'VIP'], ['entityId' => null]);

        self::assertSame('customer_group_edit', $event->getFormId());
        self::assertSame(['code' => 'VIP'], $event->getData());
        self::assertSame(['entityId' => null], $event->getContext());
        self::assertNull($event->getSavedEntityId());
        self::assertSame([], $event->getMessages());
    }

    public function testFormSaveEventMutationAndMessages(): void
    {
        $event = new FormSaveEvent('customer_edit', ['email' => 'a@b.com']);

        $event->setData(['email' => 'a+mask@b.com']);
        $event->setSavedEntityId(42);
        $event->addMessage('Synced to CRM');

        self::assertSame(['email' => 'a+mask@b.com'], $event->getData());
        self::assertSame(42, $event->getSavedEntityId());
        self::assertSame(['Synced to CRM'], $event->getMessages());
    }
}
