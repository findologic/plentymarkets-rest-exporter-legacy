<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Config;

abstract class ParserAbstract
{
    /**
     * @var \Findologic\Plentymarkets\Registry
     */
    protected $registry;

    /**
     * Holds the parsed values
     *
     * @var array
     */
    protected $results = array();

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Return parsed values
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Set results, only should be used for testing and mocking
     *
     * @param array $data
     * @return $this
     */
    public function setResults($data)
    {
        $this->results = $data;

        return $this;
    }

    /**
     * Get method name which is missing some data and pass the message to log class
     *
     * @param string $additionalInfo
     * @return $this
     */
    protected function handleEmptyData($additionalInfo = '')
    {
        if ($this->registry && ($log = $this->registry->get('log'))) {
            $method = debug_backtrace()[1]['function'];
            $message = 'Class ' . get_class($this) . ' method: ' . $method . ' is missing some data .';

            if ($additionalInfo) {
                $message .= ' Class message: ' . $additionalInfo;
            }

            $log->handleEmptyData($message);
        }

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigLanguageCode()
    {
        return strtoupper(Config::TEXT_LANGUAGE_CODE);
    }


    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigTaxRateCountryCode()
    {
        return strtoupper(Config::TAXRATE_COUNTRY_CODE);
    }


    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigStoreUrl()
    {
        return rtrim(Config::URL, '/');
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getDefaultEmptyValue()
    {
        return Config::DEFAULT_EMPTY_VALUE;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getIncludeInactiveProductsFlag()
    {
        return Config::INCLUDE_INACTIVE_PRODUCTS_FLAG;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getIncludeUnavailableProductsFlag()
    {
        return Config::INCLUDE_UNAVAILABLE_PRODUCTS_FLAG;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigAvailabilityIds()
    {
        $ids = array();

        if (Config::AVAILABILITY_ID !== null) {
            $ids[] = Config::AVAILABILITY_ID;
        }

        return $ids;
    }

}