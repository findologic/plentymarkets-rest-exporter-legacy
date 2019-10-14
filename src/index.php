<?php

ini_set('max_execution_time', 300);

require_once '../vendor/autoload.php';
require_once 'PlentyConfig.php';

use Findologic\Plentymarkets\Debugger;
use Log4Php\Logger;
use Log4Php\Configurators\LoggerConfigurationAdapterXML;

$configurationAdapter = new LoggerConfigurationAdapterXML();
Logger::configure($configurationAdapter->convert('Logger/import.xml'));

$log = Logger::getLogger('import.php');
$customerLogger = Logger::getLogger('import.php');
$debug = new Debugger($log);

$log->info('Initialising the plugin with DEBUG mode ON.');

$config = new PlentyConfig();

$config->setUsername('username')
    ->setPassword('password')
    ->setDomain('www.store.com')
    ->setMultishopId(0)
    ->setAvailabilityId(5)
    ->setPriceId(1)
    ->setRrpId(7) // price id for 'instead' field
    ->setLanguage('EN'); // Language code for texts

$registry = new \Findologic\Plentymarkets\Registry($log, $customerLogger);
$guzzleClient = new \GuzzleHttp\Client();
$client = new \Findologic\Plentymarkets\Client($config, $log, $customerLogger, $guzzleClient, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $log, $customerLogger, $registry);
$exporter->init();

echo $exporter->getProducts();

$debug->writeCallTimingLog();