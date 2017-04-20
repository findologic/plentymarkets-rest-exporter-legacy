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
            Logger::configure('Logger/import.xml');
            $this->logger = Logger::getLogger('import.php');
        }
    }

    /*
     * Logger wrapper functions for logging messages to customer and internal logger by level (trace, debug, etc.)
     *
     * Wrapper functions allows easier modifications for logging in the future if some level message logging logic
     * should be changed.
     */

    public function trace($message)
    {
        $this->customerLogger->trace($message);
        $this->logger->trace($message);
    }

    public function debug($message)
    {
        $this->customerLogger->debug($message);
        $this->logger->debug($message);
    }

    public function info($message)
    {
        $this->customerLogger->info($message);
        $this->logger->info($message);
    }

    public function warn($message)
    {
        $this->customerLogger->warn($message);
        $this->logger->warn($message);
    }

    public function error($message)
    {
        $this->customerLogger->error($message);
        $this->logger->error($message);
    }

    public function fatal($message)
    {
        $this->customerLogger->fatal($message);
        $this->logger->fatal($message);
    }

    /*
     * Specific messages handling functions
     */

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
     * Handle exception logging
     *
     * @param \Exception $e
     * @return $this
     */
    public function handleException($e)
    {
        if ($e instanceof CriticalException) {
            $this->fatal('Critical error: ' . $e->getMessage());
            die();
        }

        $this->warn('An error occurred while running the exporter: ' . $e->getMessage());

        return $this;
    }
}