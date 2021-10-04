<?php

ini_set('max_execution_time', 300);

require_once '../vendor/autoload.php';
require_once 'PlentyConfig.php';

use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Debugger;
use Findologic\Plentymarkets\Exporter;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Wrapper\Csv;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function configureLogger(Logger $logger): Logger
{
    $logger->pushHandler(new StreamHandler('logs/import.log', Logger::DEBUG));
    return $logger;
}

$log = configureLogger(new Logger('import.php'));
$customerLogger = configureLogger(new Logger('import.php'));
$debug = new Debugger($log);

$log->info('Initialising the plugin with DEBUG mode ON.');

$config = new PlentyConfig();

$config->setUsername('FINDOLOGIC API USER')
    ->setPassword('secure')
    ->setDomain('findologic.plentymarkets-cloud02.com')
    ->setMultishopId(0)
    ->setAvailabilityId(null)
    ->setPriceId(1)
    ->setRrpId(2) // price id for 'instead' field
    ->setLanguage('DE'); // Language code for texts

$registry = new Registry($log, $customerLogger);
$guzzleClient = new \GuzzleHttp\Client();
$client = new Client($config, $log, $customerLogger, $guzzleClient, $debug);
$wrapper = new Csv();
$exporter = new Exporter($client, $wrapper, $log, $customerLogger, $registry);
$exporter->init();

echo $exporter->getProducts();

$debug->writeCallTimingLog();