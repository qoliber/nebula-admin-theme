<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

use Magento\Framework\App\RequestInterface;

/**
 * Computes the list of hidden `<input>` bodies a form should POST, based on
 * its `settings.hiddenInputs` definition.
 *
 * Prefix syntax:
 *   - `literal:foo` → the raw string `foo`.
 *   - `array:field` → comma-split into an array of strings.
 *   - otherwise     → the request param (if set) or entity-data value.
 */
class HiddenInputResolver
{
    /**
     * @param array<string, mixed>  $definition
     * @param array<string, mixed>  $entityData
     * @return array<string, string|string[]>
     */
    public function resolve(array $definition, RequestInterface $request, array $entityData): array
    {
        $inputs = $definition['settings']['hiddenInputs'] ?? [];
        $result = [];

        foreach ($inputs as $name => $source) {
            $source = (string) $source;

            if (str_starts_with($source, 'literal:')) {
                $result[$name] = substr($source, 8);
                continue;
            }

            $isArray = str_starts_with($source, 'array:');
            if ($isArray) {
                $source = substr($source, 6);
            }

            $value = $request->getParam($name) ?? ($entityData[$source] ?? '');

            if ($isArray && is_string($value) && $value !== '') {
                $result[$name] = explode(',', $value);
            } else {
                $result[$name] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Compute the URL params to pass to `UrlBuilder::getUrl()` when building
     * the save URL. Implements the hiddenParams + entityMap convention from
     * the EAV form settings.
     *
     * @param array<string, mixed>  $definition
     * @param array<string, mixed>  $entityData
     * @return array<string, string>
     */
    public function resolveSaveUrlParams(array $definition, RequestInterface $request, array $entityData): array
    {
        $paramList = $definition['settings']['hiddenParams'] ?? [];
        $entityMap = [
            'type' => 'type_id',
            'set' => 'attribute_set_id',
        ];

        $params = [];
        foreach ($paramList as $param) {
            $value = $request->getParam($param);
            if ($value === null || $value === '') {
                $entityField = $entityMap[$param] ?? $param;
                $value = $entityData[$entityField] ?? null;
            }
            if ($value !== null && $value !== '') {
                $params[$param] = (string) $value;
            }
        }

        // Always include the identifier param when we're editing an existing
        // entity. Without it the save controller (catalog/product/save,
        // customer/index/save, …) sees no id in the request and treats the
        // POST as a CREATE — silently spawning a duplicate record with a
        // suffixed SKU. The identifierParam name comes from the form
        // definition; the value is whatever the URL carried us in with.
        $identifierParam = (string) (
            $definition['dataSource']['config']['identifierParam']
            ?? $definition['settings']['identifierParam']
            ?? 'id'
        );
        $entityId = $request->getParam($identifierParam);
        if ($entityId !== null && $entityId !== '' && !isset($params[$identifierParam])) {
            $params[$identifierParam] = (string) $entityId;
        }

        return $params;
    }
}
