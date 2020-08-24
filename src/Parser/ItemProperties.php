<?php

namespace Findologic\Plentymarkets\Parser;

/**
 * Class ItemProperties
 * @package Findologic\Plentymarkets\Parser
 */
class ItemProperties extends ParserAbstract implements ParserInterface
{
    const PROPERTY_TYPE_ITEM = 'item';

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing item properties.');
            return $this->results;
        }

        foreach ($data['entries'] as $property) {
            $this->results[$property['id']] = [
                'id' => $property['id'],
                'backendName' => $property['backendName'],
                'propertyGroupId' => $property['propertyGroupId'],
                'propertyGroups' => array(),
                'isSearchable' => $property['isSearchable'],
                'valueType' => $property['valueType'],
                'selections' => $property['selections'] ?? [],
                'valueInt' => $property['valueInt'] ?? null,
                'valueFloat' => $property['valueFloat'] ?? null,
            ];

            if (isset($property['names'])) {
                foreach ($property['names'] as $propertyName) {
                    $this->results[$property['id']]['names'][strtoupper($propertyName['lang'])] = [
                        'name' => $propertyName['name'],
                        'description' => $propertyName['description']
                    ];
                }
            }
        }

        return $this->results;
    }

    /**
     * @param int $propertyId
     * @return string
     */
    public function getPropertyName($propertyId)
    {
        if (isset($this->results[$propertyId]['names'][strtoupper($this->getLanguageCode())])) {
            return $this->results[$propertyId]['names'][strtoupper($this->getLanguageCode())]['name'];
        } else if (isset($this->results[$propertyId]['backendName'])) {
            return $this->results[$propertyId]['backendName'];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * Returns a single property by the given id. Null may be returned in case the given id can not be found.
     */
    public function getProperty(string $id): ?array
    {
        return $this->results[$id] ?? null;
    }
}
