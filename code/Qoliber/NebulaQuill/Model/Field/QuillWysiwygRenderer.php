<?php

declare(strict_types=1);

namespace Qoliber\NebulaQuill\Model\Field;

use Magento\Framework\Escaper;
use Qoliber\NebulaForm\Model\Field\DefaultWysiwygRenderer;
use Qoliber\NebulaForm\Model\Field\WysiwygRendererInterface;
use Qoliber\NebulaQuill\Model\Config\QuillConfig;

class QuillWysiwygRenderer implements WysiwygRendererInterface
{
    public function __construct(
        private readonly QuillConfig $quillConfig,
        private readonly DefaultWysiwygRenderer $fallbackRenderer,
        private readonly Escaper $escaper
    ) {
    }

    public function render(
        string $fieldId,
        string $fieldName,
        string $value,
        string $json,
        string $validateAttr,
        string $inputClass
    ): string
    {
        if (!$this->quillConfig->shouldUseQuill()) {
            return $this->fallbackRenderer->render($fieldId, $fieldName, $value, $json, $validateAttr, $inputClass);
        }

        return '<div class="nebula-quill-field" x-data="nebulaField_quill(' . $json . ')">'
            . '<textarea id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-ref="fallbackInput"'
            . ' name="' . $this->escaper->escapeHtmlAttr($fieldName) . '"'
            . ' rows="5"'
            . $validateAttr
            . ' class="' . $this->escaper->escapeHtmlAttr($inputClass) . '">'
            . $this->escaper->escapeHtml($value)
            . '</textarea>'
            . '<div x-ref="editorHost" class="nebula-quill-host" hidden></div>'
            . $this->renderLinkDialog()
            . $this->renderTableDialog()
            . '</div>';
    }

    private function renderLinkDialog(): string
    {
        return '<div x-show="linkDialogOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center" @keydown.escape.window="closeLinkDialog()">'
            . '<div x-show="linkDialogOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeLinkDialog()"></div>'
            . '<div x-show="linkDialogOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative z-10 mx-4 w-full max-w-xl rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" @click.stop>'
            . '<form @submit.prevent="submitLinkDialog()">'
            . '<div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">'
            . '<h3 class="text-lg font-semibold text-gray-900">' . $this->escaper->escapeHtml((string) __('Insert Link')) . '</h3>'
            . '<button type="button" @click="closeLinkDialog()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">'
            . '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>'
            . '</button>'
            . '</div>'
            . '<div class="space-y-4 px-6 py-5">'
            . '<div><label class="mb-1 block text-sm font-medium text-gray-700">' . $this->escaper->escapeHtml((string) __('URL')) . '</label><input x-ref="linkUrlInput" x-model="linkDialogUrl" type="url" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none"></div>'
            . '<div><label class="mb-1 block text-sm font-medium text-gray-700">' . $this->escaper->escapeHtml((string) __('Text to display')) . '</label><input x-model="linkDialogText" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none"></div>'
            . '</div>'
            . '<div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">'
            . '<button type="button" @click="closeLinkDialog()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">' . $this->escaper->escapeHtml((string) __('Cancel')) . '</button>'
            . '<button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">' . $this->escaper->escapeHtml((string) __('Save')) . '</button>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';
    }

    private function renderTableDialog(): string
    {
        return '<div x-show="tableDialogOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center" @keydown.escape.window="closeTableDialog()">'
            . '<div x-show="tableDialogOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeTableDialog()"></div>'
            . '<div x-show="tableDialogOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative z-10 mx-4 w-full max-w-md rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" @click.stop>'
            . '<form @submit.prevent="submitTableDialog()">'
            . '<div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">'
            . '<h3 class="text-lg font-semibold text-gray-900">' . $this->escaper->escapeHtml((string) __('Insert Table')) . '</h3>'
            . '<button type="button" @click="closeTableDialog()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">'
            . '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>'
            . '</button>'
            . '</div>'
            . '<div class="grid grid-cols-2 gap-4 px-6 py-5">'
            . '<div><label class="mb-1 block text-sm font-medium text-gray-700">' . $this->escaper->escapeHtml((string) __('Rows')) . '</label><input x-ref="tableRowsInput" x-model.number="tableRows" type="number" min="1" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none"></div>'
            . '<div><label class="mb-1 block text-sm font-medium text-gray-700">' . $this->escaper->escapeHtml((string) __('Columns')) . '</label><input x-model.number="tableColumns" type="number" min="1" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none"></div>'
            . '</div>'
            . '<div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">'
            . '<button type="button" @click="closeTableDialog()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">' . $this->escaper->escapeHtml((string) __('Cancel')) . '</button>'
            . '<button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">' . $this->escaper->escapeHtml((string) __('Insert')) . '</button>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';
    }
}
