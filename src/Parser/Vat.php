<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Data\Countries;
use Findologic\Plentymarkets\Registry;

class Vat extends ParserAbstract implements ParserInterface
{
    /**
     * Country id, country mapping could be found in \Findologic\Plentymarkets\Data\Countries;
     * Default value - 1 (Germany - DE)
     *
     * @var int
     */
    protected $countryId = 1;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $countryId = Countries::getCountryByIsoCode($this->getConfigTaxRateCountryCode());

        if ($countryId) {
            $this->countryId = $countryId;
        }
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
            return $this->getDefaultEmptyValue();
        }

        return $this->results[$this->countryId][$vatRate];
    }
}