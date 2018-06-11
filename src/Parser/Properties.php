<?php

namespace Findologic\Plentymarkets\Parser;

/**
 * Class Properties
 * @package Findologic\Plentymarkets\Parser
 */
class Properties extends ParserAbstract implements ParserInterface
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
            if (isset($property['typeIdentifier']) && $property['typeIdentifier'] != self::PROPERTY_TYPE_ITEM) {
                continue;
            }

            $this->results[$property['id']] = [
                'propertyGroupId' => $property['propertyGroupId'],
                'propertyGroups' => array()
            ];

            if (isset($property['names'])) {
                foreach ($property['names'] as $propertyName) {
                    $this->results[$property['id']]['names'][strtoupper($propertyName['lang'])] = $propertyName['name'];
                }
            }

            if (isset($property['groups']) && !empty($property['groups'])) {
                foreach ($property['groups'] as $propertyGroup) {
                    $names = [];

                    foreach ($propertyGroup['names'] as $propertyGroupName) {
                        $names[strtoupper($propertyGroupName['lang'])] = $propertyGroupName['name'];
                    }

                    $this->results[$property['id']]['propertyGroups'][$propertyGroup['id']] = $names;
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
            return $this->results[$propertyId]['names'][strtoupper($this->getLanguageCode())];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param int $propertyId
     * @param int|null $groupId
     * @return string
     */
    public function getPropertyGroupName($propertyId, $groupId = null)
    {
        if (!$groupId) {
            if (isset($this->results[$propertyId]['propertyGroups'])) {
                foreach ($this->results[$propertyId]['propertyGroups'] as $propertyGroup) {
                    foreach ($propertyGroup as $languageCode => $value) {
                        if (strtoupper($languageCode) != strtoupper($this->getLanguageCode())) {
                            continue;
                        }

                        return $value;
                    }
                }
            }
        } else if (isset($this->results[$propertyId]['propertyGroups'][$groupId][strtoupper($this->getLanguageCode())])) {
            return $this->results[$propertyId]['propertyGroups'][$groupId][strtoupper($this->getLanguageCode())];
        }

        return $this->getDefaultEmptyValue();
    }
}