<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\CriticalException;
use \Logger;

class Log
{
    protected $customerLogger;

    protected $logger;

    /**
     * Log constructor.
     * @param \Logger $customerLogger
     * @param \Logger|bool $logger
     */
    public function __construct($customerLogger, $logger = false)
    {
        $this->customerLogger = $customerLogger;

        if ($logger != null) {
            $this->logger = $logger;
        } else {
            Logger::configure('Logger/config.xml');
            $this->logger = Logger::getLogger('import.php');
        }
    }

    public function info($message)
    {

    }

    /**
     * Log information about missing data
     *
     * @param string|bool $message
     * @return $this
     */
    public function handleEmptyData($message)
    {
        $this->logger->info($message);

        return $this;
    }

    /**
     * @param \Exception $e
     * @return $this
     */
    public function handleException($e)
    {
        if ($e instanceof CriticalException) {
            $this->logger->fatal('Fatal error: ' . $e->getMessage());
            die();
        }

        $this->logger->warn('An error occurred while running the exporter: ' . $e->getMessage());

        return $this;
    }
}