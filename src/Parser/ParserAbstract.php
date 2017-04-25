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

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
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
            $message = 'Class ' . get_class($this) .
                ' method: ' . $method .
                ' is missing some data .' .
                $additionalInfo;
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
    public function getStoreUrl()
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
}