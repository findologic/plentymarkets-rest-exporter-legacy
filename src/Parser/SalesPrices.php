<?php

namespace Findologic\Plentymarkets\Parser;

class SalesPrices extends ParserAbstract implements ParserInterface
{
    const RRP_TYPE = 'rrp';

    protected $rrp = false;

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            return $this->results;
        }

        foreach ($data['entries'] as $price) {
            $this->results[$price['id']] = $price['type'];
        }

        return $this->results;
    }

    /**
     * Filter sales prices by rrp type
     *
     * @return array|bool
     */
    public function getRRP()
    {
        if (!$this->rrp) {
            $rrp = array();

            foreach ($this->results as $id => $type) {
                if ($type == self::RRP_TYPE) {
                    $rrp[] = $id;
                }
            }

            // Avoid multiple filtering of sales prices for rrp type and save filtered result to property
            $this->rrp = $rrp;
        }

        return $this->rrp;
    }
}