<?php

namespace Findologic\Plentymarkets\Parser;

/**
 * Class Properties
 * @package Findologic\Plentymarkets\Parser
 */
class PropertySelections extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritDoc
     */
    public function parse($data): array
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing item property selections.');
            return $this->results;
        }

        foreach ($data['entries'] as $entry) {
            if ($entry['property']['cast'] !== 'multiSelection') {
                continue;
            }

            $selectionRelationId = $entry['relation']['selectionRelationId'];

            foreach ($entry['relation']['relationValues'] as $value) {
                $this->results[$entry['propertyId']]['selections'][$selectionRelationId][strtoupper($value['lang'])] = $value['value'];
            }
        }
        return $this->results;
    }

    /**
     * @param int $propertyId
     * @param array $selections
     * @return array|null
     */
    public function getPropertySelectionValue(int $propertyId, array $selections): ?array
    {
        $values = [];
        foreach ($selections as $selection) {
            if ($value = $this->results[$propertyId]['selections'][$selection['value']][strtoupper($this->getLanguageCode())] ?? null) {
                $values[] = $value;
            }
        }

        return $values ?: null;
    }
}
