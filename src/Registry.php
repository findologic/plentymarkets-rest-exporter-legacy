<?php

namespace Findologic\Plentymarkets;

use \Logger;

class Registry
{
    /**
     * Registry constructor.
     * @param \Logger $logger
     */
    public function __construct($logger)
    {
        $this->set('logger', $logger);
    }

    /**
     * @var array
     */
    protected $registry = array();

    public function set($key, $object)
    {
        $key = strtolower($key);
        if (!array_key_exists($key, $this->registry)) {
            $this->registry[$key] = $object;
        }
    }

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