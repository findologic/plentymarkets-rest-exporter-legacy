<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Data\Countries;

class Vat extends ParserAbstract implements ParserInterface
{
    /**
     * Country id, country mapping could be found in \Findologic\Plentymarkets\Data\Countries;
     * Default value - 1 (Germany - DE)
     *
     * @var int
     */
    protected $countryId = 1;

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing VAT.');
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

    /**
     * Get vat rate value by id
     *
     * @param int $vatId
     * @return string
     */
    public function getVatRateByVatId($vatId, $countryId = null)
    {
        if (isset($this->results[$this->countryId][$vatId])) {
            return $this->results[$this->countryId][$vatId];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param string $country
     * @return $this
     */
    public function setTaxRateCountryCode($country)
    {
        parent::setTaxRateCountryCode($country);

        $this->countryId = Countries::getCountryByIsoCode($country);

        return $this;
    }
}