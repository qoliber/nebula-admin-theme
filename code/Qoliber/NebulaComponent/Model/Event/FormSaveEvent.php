<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Event;

/**
 * Typed payload for `nebula_form_save_before` and `nebula_form_save_after`.
 *
 * Before-save observers can mutate the inbound `data` payload (e.g. strip
 * secrets, rewrite keys). After-save observers receive the persisted
 * `entityId` and any messages produced by the save handler.
 *
 * @api
 */
class FormSaveEvent
{
    /**
     * @param string $formId form definition id
     * @param array<string, mixed> $data submitted form data (mutable for _before)
     * @param array<string, mixed> $context caller context (`entityId`, `storeId`, `fieldPrefix`, `handlerAlias`)
     * @param string|int|null $savedEntityId populated for `_after`; null for `_before`
     * @param list<string> $messages informational messages emitted by the save handler
     */
    public function __construct(
        private readonly string $formId,
        private array $data,
        private readonly array $context = [],
        private string|int|null $savedEntityId = null,
        private array $messages = []
    ) {
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getSavedEntityId(): string|int|null
    {
        return $this->savedEntityId;
    }

    public function setSavedEntityId(string|int|null $savedEntityId): void
    {
        $this->savedEntityId = $savedEntityId;
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
