<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Registry;

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
}