<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\CriticalException;
use \Logger;

/**
 * Class for wrapping logic for logging information into one unit so it would be easier to change in the future
 *
 * Class Log
 * @package Findologic\Plentymarkets
 */
class Log
{
    /**
     * This logger should be used for information which should be displayed to customer
     *
     * @var Logger
     */
    protected $customerLogger;

    /**
     * This logger should be used for logging data used by developers
     *
     * @var Logger
     */
    protected $logger;

    /**
     * List of available logger methods as they will be called using magic method
     *
     * @var array
     */
    protected $loggerMethods = array('trace', 'debug', 'info', 'warn', 'error', 'fatal');

    /**
     * Log constructor.
     *
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
            Logger::initialize();
            $this->logger = Logger::getLogger('import.php');
        }
    }

    /**
     * Magic method for wrapping the Logger class logging methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (!in_array($name, $this->loggerMethods)) {
            $this->logger->warn('Plugin has tried to call undefined logger class method: ' . $name);
            return false;
        }

        $message = $arguments[0];
        $isInternalMessage = false;

        // Use second parameter to log message only to internal logger
        if (isset($arguments[1])) {
            $isInternalMessage = $arguments[1];
        }

        if ($isInternalMessage) {
            $this->logger->$name($message);
        } else {
            $this->customerLogger->$name($message);
        }

        return $this;
    }

    /**
     * Log information about missing data
     *
     * @param string|bool $message
     * @return $this
     */
    public function handleEmptyData($message)
    {
        //TODO: maybe the message level for internal logger and customer logger should be different
        $this->trace($message);

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
        // CriticalException means that plugin will not function properly if execution would be continued so it
        // should be terminated
        // Example: wrong credentials provided so plugin can't login to api
        if ($e instanceof CriticalException) {
            $this->fatal('Critical error: ' . $e->getMessage());
            die();
        }

        $this->warn('An error occurred while running the exporter: ' . $e->getMessage());

        return $this;
    }
}