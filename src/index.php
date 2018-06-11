<?php

ini_set('max_execution_time', 300);

require_once '../vendor/autoload.php';

use Findologic\Plentymarkets\Config;

$debug = false;

Logger::configure('Logger/import.xml');
Logger::initialize();
$log = Logger::getLogger('import.php');
$customerLogger = Logger::getLogger('import.php');

if (Config::DEBUG) {
    $log->info('Initialising the plugin with DEBUG mode ON.', false);
    $debug = new \Findologic\Plentymarkets\Debugger($log);
}

$config = new PlentyConfig();

$config->setUsername('py30228')
    ->setPassword('6d1ff92a')
    ->setDomain('findologic-adeo.plentymarkets-cloud02.com')
    ->setPriceId(1)
    ->setRrpId(2) // price id for 'instead' field
    ->setCountry('EN') // Country code for tax rates
    ->setLanguage('EN'); // Language code for texts

$registry = new \Findologic\Plentymarkets\Registry($log, $customerLogger);
$client = new \Findologic\Plentymarkets\Client($config, $log, $customerLogger, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $log, $customerLogger, $registry);
$exporter->init();

echo $exporter->getProducts(Config::NUMBER_OF_ITEMS_PER_PAGE);

$debug->writeCallTimingLog();