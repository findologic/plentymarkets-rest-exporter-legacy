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

    protected $storeUrl = '';

    protected $languageCode = '';

    protected $countryCode = '';

    protected $storePlentyId;

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
            $method = debug_backtrace();
            $method = $method[1]['function'];
            $message = 'Class ' . get_class($this) . ' method: ' . $method . ' is missing some data .';

            if ($additionalInfo) {
                $message .= ' Class message: ' . $additionalInfo;
            }

            $log->handleEmptyData($message);
        }

        return $this;
    }

    public function setLanguageCode($lang)
    {
        $this->languageCode = strtoupper($lang);

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    public function setTaxRateCountryCode($country)
    {
        $this->countryCode = strtoupper($country);

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getTaxRateCountryCode()
    {
        return $this->countryCode;
    }

    public function setStoreUrl($url)
    {
        $this->storeUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getStoreUrl()
    {
        return $this->storeUrl;
    }

    public function setStorePlentyId($storePlentyId)
    {
        $this->storePlentyId = $storePlentyId;

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getStorePlentyId()
    {
        return $this->storePlentyId;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getDefaultEmptyValue()
    {
        return '';
    }
}