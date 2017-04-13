<?php

ini_set('max_execution_time', 300);

require_once '../vendor/autoload.php';

use Findologic\Plentymarkets\Config;


$debug = false;

if (Config::DEBUG) {
    $debug = new \Findologic\Plentymarkets\Debugger();
}

$logger = Logger::getLogger("main");
$log = new Findologic\Plentymarkets\Log($logger);
$client = new \Findologic\Plentymarkets\Client(Config::USERNAME, Config::PASSWORD, Config::URL, $log, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$registry = new \Findologic\Plentymarkets\Registry($log);
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $log, $registry);
$exporter->init();

echo $exporter->getProducts(Config::NUMBER_OR_ITEMS_PER_PAGE);