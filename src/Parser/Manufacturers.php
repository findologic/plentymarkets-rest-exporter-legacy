<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Manufacturers extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing manufacturers.');
            return $this->results;
        }

        foreach ($data['entries'] as $manufacturer) {
            $name = $manufacturer['name'];

            if (isset($manufacturer['externalName']) && $manufacturer['externalName']) {
                $name = $manufacturer['externalName'];
            }

            $this->results[$manufacturer['id']] = $name;
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

        return $this->getDefaultEmptyValue();
    }

}