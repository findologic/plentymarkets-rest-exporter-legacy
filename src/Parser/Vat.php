<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;
use Findologic\Plentymarkets\Data\Countries;

class Vat implements ParserInterface
{
    protected $results = array();

    /**
     * Country id, country mapping could be found in \Findologic\Plentymarkets\Data\Countries;
     * Default value - 1 (Germany - DE)
     *
     * @var int
     */
    protected $countryId = 1;

    public function __construct()
    {
        $countryId = Countries::getCountryByIsoCode(strtoupper(Config::TAXRATE_COUNTRY_CODE));

        if ($countryId) {
            $this->countryId = $countryId;
        }
    }

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

    public function getVatRateByVatId($vatRate)
    {
        if (!isset($this->results[$this->countryId][$vatRate])) {
            return null;
        }

        return $this->results[$this->countryId][$vatRate];
    }
}