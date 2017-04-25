<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Attributes extends ParserAbstract implements ParserInterface
{
    protected $results = array();

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        return $this->results;
    }

    public function setResults($data)
    {
        $this->results = $data;

        return $this;
    }

    /**
     * Parse the attributes ids from $data
     *
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            return $this->results;
        }

        foreach ($data['entries'] as $attribute) {
            $this->results[$attribute['id']] = array(
                'name' => $attribute['backendName'],
                'values' => array()
            );

            if (isset($attribute['attributeNames']) && ($name = $this->parseAttributeName($attribute['attributeNames']))) {
                $this->results[$attribute['id']]['name'] = $name;
            }
        }

        return $this->results;
    }

    /**
     * @param array $data
     * @return string
     */
    public function parseAttributeName($data)
    {
        $name = $this->getDefaultEmptyValue();

        if (!is_array($data) || empty($data)) {
            return $name;
        }

        foreach ($data as $attributeName) {
            if (strtoupper($attributeName['lang']) == $this->getConfigLanguageCode()) {
                $name = $attributeName['name'];
                break;
            }
        }

        return $name;
    }

    /**
     * @param array $data
     * @return array
     */
    public function parseValues($data)
    {
        if (!isset($data['entries'])) {
            return $this->results;
        }

        $values = array();
        $attributeId = false;

        foreach ($data['entries'] as $value) {
            $attributeId = $value['attributeId'];
            $values[$value['id']] = $this->parseValueName($value['valueNames']);
        }

        $this->results[$attributeId]['values'] = $values;

        return $values;
    }

    /**
     * @param string $attributeId
     * @param array $data
     * @return string
     */
    public function parseValueName($data)
    {
        $name = $this->getDefaultEmptyValue();
        if (!is_array($data) || empty($data)) {
            return $name;
        }

        foreach ($data as $value) {
            if (strtoupper($value['lang']) == $this->getConfigLanguageCode()) {
                $name = $value['name'];
            }
        }

        return $name;
    }

    /**
     * @param int $attributeId
     * @param int|bool $valueId
     * @return bool
     */
    public function attributeValueExists($attributeId, $valueId = false)
    {
        if (!isset($this->results[$attributeId])) {
            return false;
        }

        if ($valueId !== false && !isset($this->results[$attributeId]['values'][$valueId])) {
            return false;
        }

        return true;
    }

    /**
     * @param int $attributeId
     * @return string
     */
    public function getAttributeName($attributeId)
    {
        $result = $this->getDefaultEmptyValue();

        if ($this->attributeValueExists($attributeId)) {
            $result = $this->results[$attributeId]['name'];
        }

        return $result;
    }

    /**
     * @param int $attributeId
     * @param int $valueId
     * @return string
     */
    public function getAttributeValueName($attributeId, $valueId)
    {
        $result = '';

        if ($this->attributeValueExists($attributeId, $valueId)) {
            $result = $this->results[$attributeId]['values'][$valueId];
        }

        return $result;
    }
}