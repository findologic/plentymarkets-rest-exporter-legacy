<?php

namespace Findologic\Plentymarkets\Parser;

class Manufacturers implements ParserInterface
{
    protected $results = array();

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            return $this->results;
        }

        foreach ($data['entries'] as $manufacturer) {
            $this->results[$manufacturer['id']] = $manufacturer['name'];
        }

        return $this->results;
    }

    /**
     * @param int $manufacturerId
     * @return string
     */
    public function getManufacturerName($manufacturerId)
    {
        if (array_key_exists($manufacturerId, $this->results)) {
            return $this->results[$manufacturerId];
        }

        return '';
    }

}