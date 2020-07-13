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
     * @param int $propertyId Id of the relation.
     * @param array $ids Ids of the properties, that are associated to this property.
     * @return array|null
     */
    public function getPropertySelectionValue(int $propertyId, array $ids): ?array
    {
        if (!isset($this->results[$propertyId])) {
            return null;
        }

        $selection = $this->results[$propertyId]['selections'];

        $values = [];
        foreach ($ids as $id) {
            if (isset($selection[$id['value']])) {
                $values[] = $selection[$id['value']][strtoupper($this->getLanguageCode())];
            }
        }

        return $values ?? null;
    }
}
