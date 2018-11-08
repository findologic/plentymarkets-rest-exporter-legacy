<?php

namespace Findologic\Plentymarkets\Parser;

class SalesPrices extends ParserAbstract implements ParserInterface
{
    const PRICE_TYPE = 'default';
    const RRP_TYPE = 'rrp';

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing sales prices.');
            return $this->results;
        }

        foreach ($data['entries'] as $price) {
            $this->results[$price['type']][] = $price['id'];
        }

        array_multisort($this->results[self::PRICE_TYPE], $this->results[self::RRP_TYPE]);

        return $this->results;
    }

    /**
     * Get default price id, if no prices data was parsed return false.
     *
     * @return false|int
     */
    public function getDefaultPrice()
    {
        if (isset($this->results[self::PRICE_TYPE][0])) {
            return $this->results[self::PRICE_TYPE][0];
        }

        return false;
    }

    /**
     * Get default rrp price id, if no prices data was parsed return false.
     *
     * @return false|int
     */
    public function getDefaultRrp()
    {
        if (isset($this->results[self::RRP_TYPE][0])) {
            return $this->results[self::RRP_TYPE][0];
        }

        return false;
    }
}