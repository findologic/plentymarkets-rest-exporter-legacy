<?php

namespace Findologic\Plentymarkets\Parser;

class PropertyGroups extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing properties.');
            return $this->results;
        }

        foreach ($data['entries'] as $property) {
            $this->results[$property['id']] = $property['backendName'];

            if (!isset($property['names']) || !is_array($property['names'])) {
                continue;
            }

            foreach ($property['names'] as $name) {
                if (strtoupper($name['lang']) == $this->getLanguageCode() && !empty($name['name'])) {
                    $this->results[$property['id']] = $name['name'];
                    break;
                }
            }
        }

        return $this->results;
    }

    /**
     * @param int $propertyGroupId
     * @return string
     */
    public function getPropertyGroupName($propertyGroupId)
    {
        if (array_key_exists($propertyGroupId, $this->results)) {
            return $this->results[$propertyGroupId];
        }

        return $this->getDefaultEmptyValue();
    }
}