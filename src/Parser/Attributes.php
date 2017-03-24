<?php

namespace Findologic\Plentymarkets\Parser;

class Attributes implements ParserInterface
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
        }

        return $this->results;
    }

    /**
     * @param array $data
     * @return array
     */
    public function parseAttributeName($data)
    {
        if (!is_array($data) || empty($data)) {
            return $this->results;
        }

        foreach ($data as $name) {
            //TODO; Language check
            //if ($name['lang] == INSERT LANGUAGE CODE)
            if (empty($name['name'])) {
                continue;
            }

            $this->results[$name['attributeId']]['name'] = $name['name'];
        }

        return $this->results;
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
            $values[$value['id']] = $value['backendName'];
        }

        if ($attributeId) {
            $this->results[$attributeId]['values'] = $values;
        }

        return $values;
    }

    /**
     * @param string $attributeId
     * @param array $data
     * @return array
     */
    public function parseValueNames($attributeId, $data)
    {
        if (!is_array($data) || empty($data)) {
            return $this->results;
        }

        //TODO: maybe check if attribute value exists ?
        foreach ($data as $value) {
            $this->results[$attributeId]['values'][$value['valueId']] = $value['name'];
        }

        return $this->results;
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
        $result = '';

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