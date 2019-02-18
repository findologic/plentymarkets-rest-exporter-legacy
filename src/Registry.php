<?php

namespace Findologic\Plentymarkets;

use Log4Php\Logger;

/**
 * A container for holding objects (parsers with parsed data, loggers and etc.)
 *
 * Class Registry
 * @package Findologic\Plentymarkets
 */
class Registry
{
    /**
     * Require log class for constructor to make sure it exists at all times.
     *
     * @codeCoverageIgnore
     * @param Logger $logger
     * @param Logger $customerLogger
     */
    public function __construct(Logger $logger, Logger $customerLogger)
    {
        $this->set('log', $logger);
        $this->set('customerLogger', $customerLogger);
    }

    /**
     * Holds all objects/other values
     *
     * @var array
     */
    protected $registry = array();

    /**
     * @param string $key
     * @param mixed $object
     */
    public function set($key, $object)
    {
        $key = strtolower($key);

        if (!array_key_exists($key, $this->registry)) {
            $this->registry[$key] = $object;
        }
    }

    /**
     * @param string $key
     * @return bool|mixed
     */
    public function get($key)
    {
        $key = strtolower($key);

        if (array_key_exists($key, $this->registry)) {
            return $this->registry[$key];
        } else {
            return false;
        }
    }
}