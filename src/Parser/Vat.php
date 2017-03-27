<?php

namespace Findologic\Plentymarkets\Parser;

class Vat implements ParserInterface
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

        foreach ($data['entries'] as $vatCountries) {
            $countryId = $vatCountries['countryId'];
            foreach ($vatCountries['vatRates'] as $vatRate) {
                $this->results[$countryId][$vatRate['id']] = $vatRate['vatRate'];
            }
        }

        return $this->results;
    }
}